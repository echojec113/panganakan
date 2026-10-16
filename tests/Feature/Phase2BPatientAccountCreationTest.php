<?php

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;
use App\Services\PatientAccountCreationService;
use Illuminate\Auth\SessionGuard;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * DEPLA Phase 2B — secure backend provisioning of a Patient Portal login
 * account for an EXISTING patient record.
 *
 * Backend only: there is no Blade form, no Patient Portal page, no
 * password entry any more (Phase 1.5 replaced the staff-chosen initial
 * password with the emailed single-use setup link) and no mandatory
 * password-change flow yet (Phase 2C). These tests cover validation,
 * authorization, transactional integrity, audit safety and the
 * must_change_password column - all against isolated SQLite :memory:.
 */
beforeEach(function () {
    $this->withoutVite();
});

function phase2bPatient(string $firstName = 'Ana', string $lastName = 'Santos'): Patient
{
    return Patient::create([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'age' => 25,
        'address' => 'Phase 2B test address',
        'contact_number' => '09171234567',
        'email' => strtolower($firstName) . '@phase2b.test',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function phase2bActor(string $role = 'staff'): User
{
    return User::factory()->create(['role' => $role]);
}

function phase2bPayload(array $overrides = []): array
{
    // Phase 1.5: the login email is the ONLY credential input.
    return array_merge([
        'email' => 'patient.login@phase2b.test',
    ], $overrides);
}

function phase2bMigration(): object
{
    return require database_path('migrations/2026_10_09_000002_add_must_change_password_to_users_table.php');
}

/*
|--------------------------------------------------------------------------
| Authorization boundary
|--------------------------------------------------------------------------
*/

it('lets an admin create a login account for an existing patient', function () {
    $admin = phase2bActor('admin');
    $patient = phase2bPatient();

    $this->actingAs($admin)
        ->post(route('patients.account.store', $patient->id), phase2bPayload())
        ->assertRedirect(route('patients.show', $patient->id))
        ->assertSessionHas('success');

    $user = User::where('email', 'patient.login@phase2b.test')->first();

    expect($user)->not->toBeNull();
    expect($patient->fresh()->user_id)->toBe($user->id);
});

it('lets a staff member create a login account for an existing patient', function () {
    $staff = phase2bActor('staff');
    $patient = phase2bPatient();

    $this->actingAs($staff)
        ->post(route('patients.account.store', $patient->id), phase2bPayload())
        ->assertRedirect(route('patients.show', $patient->id))
        ->assertSessionHas('success');

    expect(User::where('email', 'patient.login@phase2b.test')->exists())->toBeTrue();
    expect($patient->fresh()->user_id)->not->toBeNull();
});

it('rejects a guest', function () {
    $patient = phase2bPatient();

    $this->post(route('patients.account.store', $patient->id), phase2bPayload())
        ->assertRedirect(route('login'));

    expect(User::where('role', 'patient')->count())->toBe(0);
    expect($patient->fresh()->user_id)->toBeNull();
});

it('rejects a patient-role user', function () {
    $patient = phase2bPatient();
    $portalUser = User::factory()->create(['role' => 'patient']);

    $this->actingAs($portalUser)
        ->post(route('patients.account.store', $patient->id), phase2bPayload())
        ->assertForbidden();

    expect(User::where('email', 'patient.login@phase2b.test')->count())->toBe(0);
    expect($patient->fresh()->user_id)->toBeNull();
});

it('rejects an unknown-role user', function () {
    $patient = phase2bPatient();
    $unknown = User::factory()->create(['role' => 'clinic_observer']);

    $this->actingAs($unknown)
        ->post(route('patients.account.store', $patient->id), phase2bPayload())
        ->assertForbidden();

    expect(User::where('email', 'patient.login@phase2b.test')->count())->toBe(0);
    expect($patient->fresh()->user_id)->toBeNull();
});

it('rejects an archived clinic user', function () {
    $staff = phase2bActor('staff');
    $patient = phase2bPatient();
    $staff->delete();

    // (1) Existing authentication safeguard: a real session for an archived
    // account cannot be rehydrated, because the Eloquent user provider applies
    // the SoftDeletes global scope - auth middleware treats it as a guest.
    // This runs FIRST because actingAs() pins the user onto the shared guard
    // for the remainder of the test.
    $this->withSession(['login_web_' . sha1(SessionGuard::class) => $staff->id])
        ->post(route('patients.account.store', $patient->id), phase2bPayload())
        ->assertRedirect(route('login'));

    // (2) Endpoint-level defense in depth: even when an archived account is
    // forced onto the request, the FormRequest still refuses it.
    $this->actingAs($staff)
        ->post(route('patients.account.store', $patient->id), phase2bPayload())
        ->assertForbidden();

    expect(User::where('email', 'patient.login@phase2b.test')->count())->toBe(0);
    expect($patient->fresh()->user_id)->toBeNull();
});

it('does not expose a GET account-creation page', function () {
    $staff = phase2bActor('staff');
    $patient = phase2bPatient();

    $this->actingAs($staff)
        ->get(route('patients.account.store', $patient->id))
        ->assertStatus(405);
});

/*
|--------------------------------------------------------------------------
| Account creation results
|--------------------------------------------------------------------------
*/

it('links the existing patient record without creating a second patient', function () {
    $staff = phase2bActor('staff');
    $patient = phase2bPatient();

    $this->actingAs($staff)
        ->post(route('patients.account.store', $patient->id), phase2bPayload())
        ->assertRedirect(route('patients.show', $patient->id));

    expect(Patient::count())->toBe(1);
    expect(Patient::onlyTrashed()->count())->toBe(0);

    $fresh = $patient->fresh();

    expect($fresh->id)->toBe($patient->id);
    expect($fresh->first_name)->toBe('Ana');
    expect($fresh->last_name)->toBe('Santos');
    expect($fresh->user_id)->not->toBeNull();

    expect(User::where('role', 'patient')->count())->toBe(1);
});

it('creates the account with role patient', function () {
    $staff = phase2bActor('staff');
    $patient = phase2bPatient();

    $this->actingAs($staff)
        ->post(route('patients.account.store', $patient->id), phase2bPayload());

    $user = User::where('email', 'patient.login@phase2b.test')->first();

    expect($user->role)->toBe('patient');
});

it('marks the new account as requiring a password change', function () {
    $staff = phase2bActor('staff');
    $patient = phase2bPatient();

    $this->actingAs($staff)
        ->post(route('patients.account.store', $patient->id), phase2bPayload());

    $user = User::where('email', 'patient.login@phase2b.test')->first();

    expect($user->must_change_password)->toBeTrue();
    expect($user->fresh()->must_change_password)->toBeTrue();
});

it('leaves the new account unverified', function () {
    $staff = phase2bActor('staff');
    $patient = phase2bPatient();

    $this->actingAs($staff)
        ->post(route('patients.account.store', $patient->id), phase2bPayload());

    $user = User::where('email', 'patient.login@phase2b.test')->first();

    expect($user->email_verified_at)->toBeNull();
});

it('generates a random initial password that ignores any posted password fields', function () {
    $staff = phase2bActor('staff');
    $patient = phase2bPatient();

    // A stale or crafted form may still post password fields: they are
    // ignored entirely and never become the account password (Phase 1.5).
    $this->actingAs($staff)
        ->post(route('patients.account.store', $patient->id), phase2bPayload([
            'password' => 'Initial-Passw0rd!',
            'password_confirmation' => 'Initial-Passw0rd!',
        ]))
        ->assertRedirect(route('patients.show', $patient->id))
        ->assertSessionHasNoErrors();

    $user = User::where('email', 'patient.login@phase2b.test')->first();

    expect($user->password)->not->toBe('Initial-Passw0rd!');
    expect(Hash::isHashed($user->password))->toBeTrue();
    expect(Hash::check('Initial-Passw0rd!', $user->password))->toBeFalse();
    expect(Hash::check('password', $user->password))->toBeFalse();
});

it('leaves assigned_staff_id unchanged', function () {
    $actor = phase2bActor('staff');
    $assigned = phase2bActor('staff');
    $patient = phase2bPatient();
    $patient->assigned_staff_id = $assigned->id;
    $patient->save();

    $this->actingAs($actor)
        ->post(route('patients.account.store', $patient->id), phase2bPayload())
        ->assertRedirect(route('patients.show', $patient->id));

    $fresh = $patient->fresh();

    expect($fresh->assigned_staff_id)->toBe($assigned->id);
    expect($fresh->assignedStaff()->first()->id)->toBe($assigned->id);
});

it('leaves the patient clinical record unchanged', function () {
    $staff = phase2bActor('staff');
    $patient = phase2bPatient();

    $visit = PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'risk_level' => 'LOW',
    ]);

    $before = (array) DB::table('patients')->where('id', $patient->id)->first();

    $this->actingAs($staff)
        ->post(route('patients.account.store', $patient->id), phase2bPayload())
        ->assertRedirect(route('patients.show', $patient->id));

    $after = (array) DB::table('patients')->where('id', $patient->id)->first();

    // Only the login link and its bookkeeping timestamp may differ.
    unset($before['user_id'], $before['updated_at']);
    unset($after['user_id'], $after['updated_at']);

    expect($after)->toBe($before);
    expect(Patient::count())->toBe(1);
    expect(PrenatalVisit::count())->toBe(1);
    expect($visit->fresh()->risk_level)->toBe('LOW');
    expect($patient->fresh()->email)->toBe('ana@phase2b.test');
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

it('creates the account when no password fields are posted at all', function () {
    $staff = phase2bActor('staff');
    $patient = phase2bPatient();

    $this->actingAs($staff)
        ->post(route('patients.account.store', $patient->id), [
            'email' => 'patient.login@phase2b.test',
        ])
        ->assertRedirect(route('patients.show', $patient->id))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect(User::where('email', 'patient.login@phase2b.test')->count())->toBe(1);
    expect($patient->fresh()->user_id)->not->toBeNull();
});

it('ignores weak or mismatched posted password fields without rejecting the request', function () {
    $staff = phase2bActor('staff');
    $patient = phase2bPatient();

    // Neither a weak password nor a mismatched confirmation can fail the
    // request any more: the fields are no longer validated or read.
    $this->actingAs($staff)
        ->post(route('patients.account.store', $patient->id), phase2bPayload([
            'password' => 'short',
            'password_confirmation' => 'Different-Confirm!',
        ]))
        ->assertRedirect(route('patients.show', $patient->id))
        ->assertSessionHasNoErrors();

    $user = User::where('email', 'patient.login@phase2b.test')->first();

    expect($user)->not->toBeNull();
    expect(Hash::check('short', $user->password))->toBeFalse();
    expect($patient->fresh()->user_id)->toBe($user->id);
});

it('rejects an invalid email address', function () {
    $staff = phase2bActor('staff');
    $patient = phase2bPatient();

    $this->actingAs($staff)
        ->post(route('patients.account.store', $patient->id), phase2bPayload([
            'email' => 'not-an-email-address',
        ]))
        ->assertSessionHasErrors('email');

    expect($patient->fresh()->user_id)->toBeNull();
});

it('rejects an email already used by an active user', function () {
    $staff = phase2bActor('staff');
    $patient = phase2bPatient();
    User::factory()->create(['email' => 'patient.login@phase2b.test']);

    $this->actingAs($staff)
        ->post(route('patients.account.store', $patient->id), phase2bPayload())
        ->assertSessionHasErrors('email');

    expect(User::where('email', 'patient.login@phase2b.test')->count())->toBe(1);
    expect($patient->fresh()->user_id)->toBeNull();
});

it('rejects an email already used by a soft-deleted user', function () {
    $staff = phase2bActor('staff');
    $patient = phase2bPatient();

    $archived = User::factory()->create(['email' => 'patient.login@phase2b.test']);
    $archived->delete();

    $this->actingAs($staff)
        ->post(route('patients.account.store', $patient->id), phase2bPayload())
        ->assertSessionHasErrors('email');

    expect(User::withTrashed()->where('email', 'patient.login@phase2b.test')->count())->toBe(1);
    expect($patient->fresh()->user_id)->toBeNull();
});

it('rejects a patient already linked to any account, including an archived one', function () {
    $staff = phase2bActor('staff');
    $patient = phase2bPatient();

    $linked = User::factory()->create(['email' => 'existing.account@phase2b.test']);
    $patient->user_id = $linked->id;
    $patient->save();

    // Soft-delete the linked account: the link must still block provisioning.
    $linked->delete();

    $this->actingAs($staff)
        ->post(route('patients.account.store', $patient->id), phase2bPayload([
            'email' => 'another.login@phase2b.test',
        ]))
        ->assertSessionHasErrors('status');

    expect(User::where('email', 'another.login@phase2b.test')->count())->toBe(0);
    expect($patient->fresh()->user_id)->toBe($linked->id);
});

it('rejects an archived patient record', function () {
    $staff = phase2bActor('staff');
    $patient = phase2bPatient();
    $patient->delete();

    $this->actingAs($staff)
        ->post(route('patients.account.store', $patient->id), phase2bPayload())
        ->assertSessionHasErrors('status');

    expect(User::where('email', 'patient.login@phase2b.test')->count())->toBe(0);
    expect(Patient::onlyTrashed()->count())->toBe(1);
});

it('returns 404 for a missing patient', function () {
    $staff = phase2bActor('staff');

    $this->actingAs($staff)
        ->post(route('patients.account.store', 999999), phase2bPayload())
        ->assertNotFound();

    expect(User::where('email', 'patient.login@phase2b.test')->count())->toBe(0);
});

it('re-validates the email at the service boundary', function () {
    $staff = phase2bActor('staff');
    $patient = phase2bPatient();
    $service = app(PatientAccountCreationService::class);

    $refused = null;

    try {
        $service->createForPatient($patient, $staff, 'not-an-email');
    } catch (DomainException $exception) {
        $refused = $exception->getMessage();
    }

    expect($refused)->not->toBeNull();
    expect(User::where('role', 'patient')->count())->toBe(0);
    expect($patient->fresh()->user_id)->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Transactional integrity
|--------------------------------------------------------------------------
*/

it('leaves no partial user, link or audit row when creation fails after the user insert', function () {
    $patient = phase2bPatient();

    // Deterministic post-insert failure injection: the audit_logs.user_id
    // foreign key rejects a non-existent actor, so the mandatory audit write
    // happens AFTER the users insert and AFTER the patient link. If the
    // transaction did not roll everything back, either would survive.
    $ghostActor = new User();
    $ghostActor->id = 424242;

    $refused = false;

    try {
        app(PatientAccountCreationService::class)->createForPatient(
            $patient,
            $ghostActor,
            'partial.failure@phase2b.test'
        );
    } catch (Throwable) {
        $refused = true;
    }

    expect($refused)->toBeTrue();
    expect(User::where('email', 'partial.failure@phase2b.test')->count())->toBe(0);
    expect($patient->fresh()->user_id)->toBeNull();
    expect(AuditLog::count())->toBe(0);
    expect(Patient::count())->toBe(1);
});

it('prevents one account from being linked to two patients at the database level', function () {
    $user = User::factory()->create();
    $first = phase2bPatient('Ana');
    $second = phase2bPatient('Bianca');

    $first->user_id = $user->id;
    $first->save();

    $second->user_id = $user->id;

    expect(fn () => $second->save())->toThrow(QueryException::class);

    expect($second->fresh()->user_id)->toBeNull();
    expect($first->fresh()->user_id)->toBe($user->id);
});

it('prevents two accounts from sharing an email at the database level, soft-deleted or not', function () {
    $existing = User::factory()->create(['email' => 'shared.login@phase2b.test']);
    $existing->delete();

    $duplicate = new User();
    $duplicate->name = 'Duplicate Login';
    $duplicate->email = 'shared.login@phase2b.test';
    $duplicate->password = Hash::make('Initial-Passw0rd!');
    $duplicate->role = 'patient';

    expect(fn () => $duplicate->save())->toThrow(QueryException::class);

    expect(User::withTrashed()->where('email', 'shared.login@phase2b.test')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Audit logging and sensitive-data exposure
|--------------------------------------------------------------------------
*/

it('records the expected audit event on success', function () {
    $admin = phase2bActor('admin');
    $patient = phase2bPatient();

    $this->actingAs($admin)
        ->post(route('patients.account.store', $patient->id), phase2bPayload())
        ->assertRedirect(route('patients.show', $patient->id));

    $user = User::where('email', 'patient.login@phase2b.test')->first();
    $log = AuditLog::latest('id')->first();

    expect($log)->not->toBeNull();
    expect($log->action)->toBe('PATIENT_ACCOUNT_CREATED');
    expect($log->module)->toBe('PATIENT');
    expect($log->user_id)->toBe($admin->id);
    expect($log->patient_id)->toBe($patient->id);
    expect($log->created_at)->not->toBeNull();
    expect($log->description)->toContain('user ID ' . $user->id);
    expect($log->description)->toContain('patient ID ' . $patient->id);
    expect(AuditLog::count())->toBe(1);
});

it('never records the password or its hash in the audit log', function () {
    $staff = phase2bActor('staff');
    $patient = phase2bPatient();

    $this->actingAs($staff)
        ->post(route('patients.account.store', $patient->id), phase2bPayload());

    $user = User::where('email', 'patient.login@phase2b.test')->first();

    $dump = AuditLog::query()->get()
        ->map(fn (AuditLog $log): string => json_encode($log->getAttributes()))
        ->implode("\n");

    expect($dump)->not->toContain('Initial-Passw0rd!');
    expect($dump)->not->toContain($user->password);
    expect($dump)->not->toContain('password_confirmation');
    expect($dump)->not->toContain('$2y$');
});

it('never stores or flashes a posted password in the response or the session', function () {
    $staff = phase2bActor('staff');
    $patient = phase2bPatient();

    $response = $this->actingAs($staff)
        ->post(route('patients.account.store', $patient->id), phase2bPayload([
            'password' => 'Posted-Staff-Password!',
            'password_confirmation' => 'Posted-Staff-Password!',
        ]));

    $response
        ->assertRedirect(route('patients.show', $patient->id))
        ->assertDontSee('Posted-Staff-Password!', false);

    $user = User::where('email', 'patient.login@phase2b.test')->first();

    // The posted password is never the account password (Phase 1.5).
    expect(Hash::check('Posted-Staff-Password!', $user->password))->toBeFalse();
    expect(session('success'))->not->toContain('Posted-Staff-Password!');
    expect(session()->getOldInput('password'))->toBeNull();
    expect(session()->getOldInput('password_confirmation'))->toBeNull();

    // The internally generated random password never leaks either.
    $dump = json_encode($response->getSession()->all());
    expect($dump)->not->toContain('$2y$');

    $auditDump = AuditLog::query()->get()
        ->map(fn (AuditLog $log): string => json_encode($log->getAttributes()))
        ->implode("\n");

    expect($auditDump)->not->toContain('Posted-Staff-Password!');
    expect($auditDump)->not->toContain('$2y$');
});

/*
|--------------------------------------------------------------------------
| must_change_password column
|--------------------------------------------------------------------------
*/

it('defaults the first-login flag to false for ordinary accounts and keeps it off the mass-assignment surface', function () {
    $factoryUser = User::factory()->create();

    expect($factoryUser->fresh()->must_change_password)->toBeFalse();

    // Same shape as StaffController::store: the flag is silently ignored
    // because it is deliberately absent from User::$fillable.
    $staffCreated = User::create([
        'name' => 'Existing Staff',
        'email' => 'existing.staff@phase2b.test',
        'password' => Hash::make('password'),
        'role' => 'staff',
        'must_change_password' => true,
    ]);

    expect($staffCreated->fresh()->must_change_password)->toBeFalse();
    expect($staffCreated->getFillable())->not->toContain('must_change_password');
});

it('rolls the must_change_password migration back and forward safely on SQLite', function () {
    $existing = User::factory()->create();
    $migration = phase2bMigration();

    expect(Schema::hasColumn('users', 'must_change_password'))->toBeTrue();

    $migration->down();
    expect(Schema::hasColumn('users', 'must_change_password'))->toBeFalse();

    $migration->up();
    expect(Schema::hasColumn('users', 'must_change_password'))->toBeTrue();
    expect($existing->fresh()->must_change_password)->toBeFalse();

    // Ownership refusal: a pre-existing column is never adopted, and nothing
    // is written or dropped while refusing.
    $migration->down();
    Schema::table('users', function ($table) {
        $table->boolean('must_change_password')->default(false);
    });

    expect(fn () => $migration->up())->toThrow(RuntimeException::class);
    expect(Schema::hasColumn('users', 'must_change_password'))->toBeTrue();

    // Restore the migration-owned column for the remainder of the run.
    Schema::table('users', function ($table) {
        $table->dropColumn('must_change_password');
    });
    $migration->up();

    expect(Schema::hasColumn('users', 'must_change_password'))->toBeTrue();
    expect($existing->fresh()->must_change_password)->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Existing behavior preserved
|--------------------------------------------------------------------------
*/

it('keeps existing clinic route and patient-role restrictions intact', function () {
    // Guest assertions run before any actingAs(), because actingAs() pins the
    // user onto the shared guard for the rest of the test.
    $patient = phase2bPatient();

    $this->post(route('patients.account.store', $patient->id), phase2bPayload())
        ->assertRedirect(route('login'));
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('patients.index'))->assertRedirect(route('login'));

    $portalUser = User::factory()->create(['role' => 'patient']);
    $unknown = User::factory()->create(['role' => 'clinic_observer']);
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create(['role' => 'staff']);

    // Phase 2C: the holding page requires a live patients link, so this
    // portal fixture gets its OWN record - $patient must stay unlinked,
    // which the assertions at the bottom of this test still prove.
    $portalPatient = phase2bPatient('Portal', 'Account');
    $portalPatient->user_id = $portalUser->id;
    $portalPatient->save();

    $this->actingAs($portalUser)
        ->post(route('patients.account.store', $patient->id), phase2bPayload())
        ->assertForbidden();
    $this->actingAs($unknown)
        ->post(route('patients.account.store', $patient->id), phase2bPayload())
        ->assertForbidden();

    $this->actingAs($portalUser)->get(route('patient.portal.coming-soon'))->assertOk();
    $this->actingAs($staff)->get(route('patients.index'))->assertOk();
    $this->actingAs($admin)->get(route('patients.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('audit-logs.index'))->assertOk();
    $this->actingAs($staff)->get(route('audit-logs.index'))->assertForbidden();

    expect(User::where('role', 'patient')->count())->toBe(1);
    expect($patient->fresh()->user_id)->toBeNull();
});
