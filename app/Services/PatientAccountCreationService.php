<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Models\UserPregnancyLink;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * DEPLA Phase 2B — authoritative owner of the patient login-account
 * provisioning transaction.
 *
 * The controller never creates a user and then separately links the patient:
 * the whole provisioning (users row + patients.user_id link + mandatory audit
 * entry) commits in ONE database transaction or not at all.
 *
 * Ownership boundary (server-controlled, never client-supplied):
 *   - role ('patient'), the internally generated random initial password
 *     hash, must_change_password (true), email_verified_at (null),
 *     patients.user_id, the audit entry.
 *   - DEPLA Phase 1.5: browser input is restricted to the login email,
 *     re-validated at the HTTP layer. There is NO staff-supplied password
 *     any more - a cryptographically secure random password is generated
 *     here, stored only as a hash, never displayed, emailed, logged or
 *     returned, and the patient chooses their own password through the
 *     strictly single-use email setup link (PatientSetupTokenService).
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
 *   - DEPLA Phase 3H.2: a user_pregnancy_links row for the SAME authorized
 *     pairing is created inside the same transaction (verified ownership
 *     evidence). It never selects the current pregnancy and never grants
 *     portal access on its own; patients.user_id remains the only portal
 *     pointer. A patient that already carries link evidence is refused
 *     instead of silently reassigning ownership.
 *   - a second patients row is never created and no automatic patient/user
 *     matching by name, email or contact number is ever performed.
 *   - the audit row is written INSIDE the transaction, so an account can never
 *     exist without its required audit record.
 *   - DEPLA Task B: after the pointer episode's own verified link is
 *     recorded, every other LIVE DELIVERED episode sharing the pointer's
 *     exact four-field identity (the approved PatientViewController::
 *     pregnanciesFor convention) is automatically associated with the new
 *     account inside the SAME transaction - so previous pregnancies appear
 *     in Pregnancy History on first login with no second staff action.
 *     Association is anchored on THIS explicit provisioning action only;
 *     it never discovers an account by demographics. A candidate episode
 *     carrying any foreign ownership (pointer or link row of another
 *     account) aborts the whole provisioning via the shared
 *     PregnancyHistoryAssociationService conflict doctrine - ownership is
 *     never reassigned. Each newly created association writes its own
 *     ID-only audit row.
 */
class PatientAccountCreationService
{
    public function __construct(
        private readonly PregnancyHistoryAssociationService $historyAssociations
    ) {
    }

    /**
     * Provision a login account for an existing patient.
     *
     * DEPLA Phase 1.5: no password argument any more. A cryptographically
     * secure random initial password is generated internally (40 characters
     * of random_bytes-backed entropy), stored ONLY as a bcrypt hash, never
     * displayed/emailed/logged/returned, and immediately superseded by the
     * patient's own choice once the emailed setup link is completed.
     *
     * @throws DomainException when a business rule refuses the operation
     * @throws UniqueConstraintViolationException when a concurrent request won
     *         the unique `users.email` race (the transaction has been rolled
     *         back by DB::transaction before this escapes)
     */
    public function createForPatient(
        Patient $patient,
        User $actor,
        string $email
    ): User {
        return DB::transaction(function () use ($patient, $actor, $email) {
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
            $this->assertEmailIsValid($email);

            // Pre-insert check for a clean, non-technical error message. The
            // unique index on users.email is what actually enforces this under
            // concurrency; the duplicate-key path is handled below.
            if (User::withTrashed()->where('email', $email)->exists()) {
                throw new DomainException('This email is already used by an existing account, including deactivated accounts.');
            }

            $user = $this->buildUser($locked, $email);

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

            // DEPLA Phase 3H.2 - verified pregnancy link, SAME transaction.
            // patients.user_id above remains the CURRENT-pregnancy pointer
            // (the only selector the Patient Portal resolves through). The
            // user_pregnancy_links row is the durable verified-ownership
            // evidence for this explicitly authorized pairing - never a
            // demographic match, never an automatic attach. Conflict rule:
            // the pointer check above already refuses a patient linked to
            // ANY account; this second check refuses a pairing when link
            // evidence exists for the patient WITHOUT a current pointer
            // (inconsistent state or a future unlink flow), so ownership is
            // never silently reassigned. $actor is a required service
            // argument, so the verifier identity is reliably available and
            // recorded; verified_at is the pairing timestamp. If any step
            // after the users insert fails (including this one), the whole
            // provisioning - user, pointer, link and audit - rolls back.
            if (UserPregnancyLink::where('patient_id', $locked->id)->exists()) {
                throw new DomainException('This patient already has a verified pregnancy link to a login account.');
            }

            $link = new UserPregnancyLink();
            $link->user_id = $user->id;
            $link->patient_id = $locked->id;
            $link->verified_by = $actor->id;
            $link->verified_at = now();
            $link->save();

            // DEPLA Task B - automatic association of legacy DELIVERED
            // pregnancy history, SAME transaction. The explicit provisioning
            // action above is the ownership anchor; every other live
            // DELIVERED episode sharing this episode's exact four-field
            // identity (first + last exact, middle name + birthdate
            // NULL-safe - the clinic's existing same-person convention)
            // becomes part of the new account's Pregnancy History. ONGOING,
            // REFERRED and archived siblings are never candidates. A
            // candidate already owned by a DIFFERENT account (pointer or
            // link row) aborts the whole provisioning so ownership is never
            // reassigned; nothing partial survives because the transaction
            // rolls back. Same-account pairs are preserved without
            // duplication (unreachable for a brand-new user, kept for
            // idempotency with the shared service).
            $plan = $this->historyAssociations->planFor($locked, $user->id);

            if ($plan['conflict']) {
                throw new DomainException(
                    'A previous pregnancy record for this patient is already owned by a different login account. Account creation was cancelled so ownership is never reassigned. Please contact the administrator.'
                );
            }

            $this->historyAssociations->applyPlan($plan, $locked, $user, $actor, $actor->id);

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
     * Required sequence step 5: validate the email format and length at the
     * service boundary, re-raising failures as safe DomainExceptions with no
     * SQL and no credential material. Password validation no longer belongs
     * here - no password is ever supplied to this service (Phase 1.5).
     */
    private function assertEmailIsValid(string $email): void
    {
        $validator = Validator::make(
            ['email' => $email],
            [
                'email' => ['required', 'string', 'email', 'max:255'],
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
    private function buildUser(Patient $patient, string $email): User
    {
        $user = new User();

        $user->name = $this->displayName($patient);
        $user->first_name = $this->cap($patient->first_name);
        $user->middle_name = $this->cap($patient->middle_name);
        $user->last_name = $this->cap($patient->last_name);
        $user->email = $email;
        // Phase 1.5: cryptographically secure random placeholder (never
        // revealed to anyone). The patient replaces it through the
        // single-use setup link; until then the account cannot be signed
        // into by anyone who does not control the random value.
        $user->password = Hash::make(Str::random(40));
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
