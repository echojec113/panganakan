<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * DEPLA Phase 2B — authoritative owner of the patient login-account
 * provisioning transaction.
 *
 * The controller never creates a user and then separately links the patient:
 * the whole provisioning (users row + patients.user_id link + mandatory audit
 * entry) commits in ONE database transaction or not at all.
 *
 * Ownership boundary (server-controlled, never client-supplied):
 *   - role ('patient'), password hash, must_change_password (true),
 *     email_verified_at (null), patients.user_id, the audit entry.
 *   - Browser input is restricted to the login email and the initial password
 *     (plus its confirmation), both re-validated at the HTTP layer.
 *
 * Safety invariants:
 *   - the patient row is re-read under lockForUpdate() inside the transaction,
 *     so two simultaneous requests for the same patient are serialized and the
 *     loser sees the link already present.
 *   - the patient must exist and must not be archived.
 *   - the patient must not already be linked to ANY account, including a
 *     soft-deleted one (user_id stays set until the account is hard-deleted,
 *     because the Phase 2A foreign key is ON DELETE SET NULL).
 *   - the email must be unused across ALL users, including soft-deleted ones.
 *   - patients.user_id is assigned by direct property write, never mass
 *     assignment, and assigned_staff_id / clinical columns are never touched.
 *   - a second patients row is never created and no automatic patient/user
 *     matching by name, email or contact number is ever performed.
 *   - the audit row is written INSIDE the transaction, so an account can never
 *     exist without its required audit record.
 */
class PatientAccountCreationService
{
    /**
     * Provision a login account for an existing patient.
     *
     * @throws DomainException when a business rule refuses the operation
     * @throws UniqueConstraintViolationException when a concurrent request won
     *         the unique `users.email` race (the transaction has been rolled
     *         back by DB::transaction before this escapes)
     */
    public function createForPatient(
        Patient $patient,
        User $actor,
        string $email,
        string $plainTextPassword
    ): User {
        return DB::transaction(function () use ($patient, $actor, $email, $plainTextPassword) {
            // Re-read the patient under the transaction's lock rather than
            // trusting the controller-loaded model (TOCTOU protection).
            $locked = Patient::withTrashed()
                ->whereKey($patient->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                throw new DomainException('This patient record no longer exists.');
            }

            if ($locked->trashed()) {
                throw new DomainException('Archived patient records cannot be given a login account.');
            }

            // Reject a link to ANY account, active or soft-deleted.
            if ($locked->user_id !== null) {
                throw new DomainException('This patient already has a login account.');
            }

            // Re-validate at the authoritative boundary (defence in depth):
            // the HTTP layer already validated this payload, but a direct
            // caller must not be able to provision an unusable credential.
            $email = $this->normalizeEmail($email);
            $this->assertCredentialsAreValid($email, $plainTextPassword);

            // Pre-insert check for a clean, non-technical error message. The
            // unique index on users.email is what actually enforces this under
            // concurrency; the duplicate-key path is handled below.
            if (User::withTrashed()->where('email', $email)->exists()) {
                throw new DomainException('This email is already used by an existing account, including deactivated accounts.');
            }

            $user = $this->buildUser($locked, $email, $plainTextPassword);

            try {
                $user->save();
            } catch (UniqueConstraintViolationException) {
                // A concurrent request inserted the same email between the
                // check above and this insert. The transaction is already
                // unwound by DB::transaction; translate the raw constraint
                // failure into a safe business error (no SQL is surfaced).
                throw new DomainException('This email is already used by an existing account, including deactivated accounts.');
            }

            // Controlled assignment: patients.user_id is intentionally absent
            // from Patient::$fillable, so it can only ever be written here.
            $locked->user_id = $user->id;
            $locked->save();

            // Mandatory audit, inside the transaction: either the account and
            // its audit entry both persist, or neither does. It records the
            // acting staff/admin, the patient and the new account id - never a
            // password, hash, token or session credential.
            AuditLog::create([
                'user_id' => $actor->id,
                'patient_id' => $locked->id,
                'action' => 'PATIENT_ACCOUNT_CREATED',
                'module' => 'PATIENT',
                'description' => sprintf(
                    'Patient login account (user ID %d) created for %s (patient ID %d).',
                    $user->id,
                    $this->patientLabel($locked),
                    $locked->id
                ),
            ]);

            return $user;
        });
    }

    /**
     * Same normalization the HTTP layer applies: trim and lower-case, so case
     * variants cannot slip past the global uniqueness requirement (the unique
     * index is case-sensitive on SQLite and case-insensitive on MySQL).
     */
    private function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    /**
     * Required sequence step 5: validate the email format and the initial
     * password against the project's existing policy (Password::defaults() -
     * the same rule StaffProfileRequest uses), re-raising failures as safe
     * DomainExceptions with no SQL and no credential material.
     */
    private function assertCredentialsAreValid(string $email, string $plainTextPassword): void
    {
        $validator = Validator::make(
            ['email' => $email, 'password' => $plainTextPassword],
            [
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => ['required', 'string', Password::defaults()],
            ]
        );

        if ($validator->fails()) {
            throw new DomainException($validator->errors()->first());
        }
    }

    /**
     * Build the account from the locked patient with explicit attribute
     * assignment, so no mass-assignment surface is involved for role, password
     * or the first-login flag.
     *
     * Name fields are copied from the patient record (users.first_name and
     * friends are string(100) while patients.first_name is string(255), so the
     * copies are capped at the users column width). The patient's own email,
     * contact details and clinical columns are deliberately NOT copied - this
     * creates a login, not a clinical record.
     */
    private function buildUser(Patient $patient, string $email, string $plainTextPassword): User
    {
        $user = new User();

        $user->name = $this->displayName($patient);
        $user->first_name = $this->cap($patient->first_name);
        $user->middle_name = $this->cap($patient->middle_name);
        $user->last_name = $this->cap($patient->last_name);
        $user->email = $email;
        $user->password = Hash::make($plainTextPassword);
        $user->role = 'patient';
        $user->email_verified_at = null;
        $user->must_change_password = true;

        return $user;
    }

    /**
     * Assemble users.name the same way StaffProfileRequest does (first, middle
     * and last joined with single spaces), falling back to a stable, non-empty
     * label if a legacy record somehow carries only whitespace names -
     * users.name is NOT NULL and cannot be left empty.
     */
    private function displayName(Patient $patient): string
    {
        $parts = array_filter(
            [$patient->first_name, $patient->middle_name, $patient->last_name],
            fn ($value): bool => is_string($value) && trim($value) !== ''
        );

        $name = trim(preg_replace('/\s+/u', ' ', implode(' ', $parts)));

        if ($name === '') {
            $name = 'Patient ' . $patient->id;
        }

        return $this->cap($name, 255);
    }

    private function patientLabel(Patient $patient): string
    {
        return trim($patient->first_name . ' ' . $patient->last_name);
    }

    private function cap(?string $value, int $limit = 100): ?string
    {
        return $value === null ? null : mb_substr($value, 0, $limit);
    }
}
