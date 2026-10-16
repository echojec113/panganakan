<?php

use App\Models\Patient;
use App\Models\User;
use App\Models\UserPregnancyLink;
use App\Services\PatientAccountCreationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * DEPLA Phase 3H.2 — verified multi-pregnancy links foundation (Option B).
 *
 * Schema, verified-only backfill, rollback guard, account-creation
 * integration and security regressions for user_pregnancy_links - all on
 * isolated SQLite :memory: only. No current-pregnancy switching, no
 * historical-records UI and no startNewPregnancy changes exist in this
 * phase; these tests prove the data foundation alone.
 */
beforeEach(function () {
    $this->withoutVite();
});

function phase3h2Patient(string $firstName = 'Ana', array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => $firstName,
        'last_name' => 'Cruz',
        'age' => 25,
        'address' => 'Phase 3H.2 test address',
        'contact_number' => '09171234567',
        'email' => strtolower($firstName) . '@phase3h2.test',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ], $overrides));
}

function phase3h2User(string $role = 'patient'): User
{
    return User::factory()->create(['role' => $role]);
}

function phase3h2Migration(): object
{
    return require database_path('migrations/2026_10_14_000001_create_user_pregnancy_links_table.php');
}

function phase3h2Link(User $user, Patient $patient, ?User $verifier = null): UserPregnancyLink
{
    $link = new UserPregnancyLink();
    $link->user_id = $user->id;
    $link->patient_id = $patient->id;
    $link->verified_by = $verifier?->id;
    $link->verified_at = now();
    $link->save();

    return $link;
}

/*
|--------------------------------------------------------------------------
| Schema and constraints
|--------------------------------------------------------------------------
*/

it('creates the user_pregnancy_links table during a fresh migration run', function () {
    expect(Schema::hasTable('user_pregnancy_links'))->toBeTrue();
});

it('stores the required columns with unique pair and restrictive foreign keys', function () {
    $columns = collect(Schema::getColumns('user_pregnancy_links'))->pluck('name');

    foreach (['id', 'user_id', 'patient_id', 'verified_by', 'verified_at', 'created_at', 'updated_at'] as $name) {
        expect($columns)->toContain($name);
    }

    $indexes = collect(Schema::getIndexes('user_pregnancy_links'));

    expect(
        $indexes->first(fn (array $index): bool => $index['unique']
            && ! $index['primary']
            && $index['columns'] === ['user_id', 'patient_id'])
    )->not->toBeNull();

    // Phase 3H.2 shape: ordinary (non-unique) patient_id index for history
    // lookups; no UNIQUE(patient_id) - the Phase 3H.5 hardening migration
    // was retired before ever applying that index.
    expect(
        $indexes->first(fn (array $index): bool => ! $index['unique']
            && ! $index['primary']
            && $index['columns'] === ['patient_id'])
    )->not->toBeNull();

    expect(
        $indexes->first(fn (array $index): bool => $index['unique']
            && ! $index['primary']
            && $index['columns'] === ['patient_id'])
    )->toBeNull();

    // Phase 3H.5 revocation columns were never part of the 3H.2 contract
    // and are absent on a fresh migration run (the retired hardening
    // migration is no longer in the project).
    foreach (['revoked_at', 'revoked_by', 'revocation_reason'] as $name) {
        expect($columns)->not->toContain($name);
    }

    $foreignKeys = collect(Schema::getForeignKeys('user_pregnancy_links'));

    $userFk = $foreignKeys->first(fn (array $fk): bool => $fk['columns'] === ['user_id']);
    expect($userFk)->not->toBeNull();
    expect(strtolower((string) $userFk['foreign_table']))->toBe('users');
    expect(strtolower((string) $userFk['on_delete']))->toBe('restrict');

    $patientFk = $foreignKeys->first(fn (array $fk): bool => $fk['columns'] === ['patient_id']);
    expect($patientFk)->not->toBeNull();
    expect(strtolower((string) $patientFk['foreign_table']))->toBe('patients');
    expect(strtolower((string) $patientFk['on_delete']))->toBe('restrict');

    $verifierFk = $foreignKeys->first(fn (array $fk): bool => $fk['columns'] === ['verified_by']);
    expect($verifierFk)->not->toBeNull();
    expect(strtolower((string) $verifierFk['foreign_table']))->toBe('users');
    expect(strtolower((string) $verifierFk['on_delete']))->toBe('set null');
});

it('prevents duplicate user-to-pregnancy relationships at the database level', function () {
    $user = phase3h2User();
    $patient = phase3h2Patient();

    phase3h2Link($user, $patient);

    $duplicate = new UserPregnancyLink();
    $duplicate->user_id = $user->id;
    $duplicate->patient_id = $patient->id;
    $duplicate->verified_at = now();

    expect(fn () => $duplicate->save())->toThrow(QueryException::class);

    expect(UserPregnancyLink::count())->toBe(1);
});

it('rejects a link row referencing a non-existent user', function () {
    $patient = phase3h2Patient();

    $orphan = new UserPregnancyLink();
    $orphan->user_id = (int) User::max('id') + 1000;
    $orphan->patient_id = $patient->id;
    $orphan->verified_at = now();

    expect(fn () => $orphan->save())->toThrow(QueryException::class);

    expect(UserPregnancyLink::count())->toBe(0);
});

it('rejects a link row referencing a non-existent patient', function () {
    $user = phase3h2User();

    $orphan = new UserPregnancyLink();
    $orphan->user_id = $user->id;
    $orphan->patient_id = (int) Patient::max('id') + 1000;
    $orphan->verified_at = now();

    expect(fn () => $orphan->save())->toThrow(QueryException::class);

    expect(UserPregnancyLink::count())->toBe(0);
});

it('allows verified_by to be null', function () {
    $user = phase3h2User();
    $patient = phase3h2Patient();

    $link = phase3h2Link($user, $patient);

    expect($link->fresh()->verified_by)->toBeNull();
    expect($link->fresh()->verified_at)->not->toBeNull();
});

it('keeps the existing patients.user_id unique constraint intact', function () {
    $user = phase3h2User();
    $first = phase3h2Patient('Ana');
    $second = phase3h2Patient('Bea');

    $first->user_id = $user->id;
    $first->save();
    $second->user_id = $user->id;

    expect(fn () => $second->save())->toThrow(QueryException::class);

    expect($second->fresh()->user_id)->toBeNull();
    expect($first->fresh()->user_id)->toBe($user->id);
});

/*
|--------------------------------------------------------------------------
| Verified-only backfill
|--------------------------------------------------------------------------
*/

it('backfills verified links only for patients with an explicit user_id', function () {
    $user = phase3h2User();
    $linked = phase3h2Patient('Linked');
    $linked->user_id = $user->id;
    $linked->save();

    $unlinked = phase3h2Patient('Unlinked');

    $inserted = phase3h2Migration()->backfillExistingLinks();

    expect($inserted)->toBe(1);
    expect(UserPregnancyLink::count())->toBe(1);

    $link = UserPregnancyLink::sole();

    expect($link->user_id)->toBe($user->id);
    expect($link->patient_id)->toBe($linked->id);
    expect($link->verified_by)->toBeNull();
    expect($link->verified_at)->not->toBeNull();

    expect(
        UserPregnancyLink::where('patient_id', $unlinked->id)->exists()
    )->toBeFalse();
});

it('never backfills links from matching names or birthdates alone', function () {
    $user = phase3h2User();

    $demographicTwinA = phase3h2Patient('Maria', [
        'last_name' => 'Cruz',
        'birthdate' => '1995-06-15',
        'email' => 'maria.a@phase3h2.test',
    ]);
    $demographicTwinA->user_id = $user->id;
    $demographicTwinA->save();

    // Identical name and birthdate, but NO explicit user_id pointer: it
    // must never inherit ownership from its demographic twin.
    $demographicTwinB = phase3h2Patient('Maria', [
        'last_name' => 'Cruz',
        'birthdate' => '1995-06-15',
        'email' => 'maria.b@phase3h2.test',
    ]);

    phase3h2Migration()->backfillExistingLinks();

    expect(UserPregnancyLink::count())->toBe(1);
    expect(UserPregnancyLink::sole()->patient_id)->toBe($demographicTwinA->id);
    expect(
        UserPregnancyLink::where('patient_id', $demographicTwinB->id)->exists()
    )->toBeFalse();
});

it('keeps unrelated users isolated during backfill', function () {
    $userA = phase3h2User();
    $userB = phase3h2User();

    $patientA = phase3h2Patient('Ana');
    $patientA->user_id = $userA->id;
    $patientA->save();

    $patientB = phase3h2Patient('Bea');
    $patientB->user_id = $userB->id;
    $patientB->save();

    phase3h2Migration()->backfillExistingLinks();

    expect(UserPregnancyLink::count())->toBe(2);
    expect(UserPregnancyLink::where('user_id', $userA->id)->sole()->patient_id)->toBe($patientA->id);
    expect(UserPregnancyLink::where('user_id', $userB->id)->sole()->patient_id)->toBe($patientB->id);
});

it('preserves existing patients.user_id values through the backfill', function () {
    $user = phase3h2User();
    $linked = phase3h2Patient();
    $linked->user_id = $user->id;
    $linked->save();

    $before = DB::table('patients')->orderBy('id')->pluck('user_id', 'id');

    phase3h2Migration()->backfillExistingLinks();

    $after = DB::table('patients')->orderBy('id')->pluck('user_id', 'id');

    expect($after->all())->toBe($before->all());
    expect($linked->fresh()->user_id)->toBe($user->id);
});

it('is idempotent: repeated backfill creates no duplicates', function () {
    $user = phase3h2User();
    $patient = phase3h2Patient();
    $patient->user_id = $user->id;
    $patient->save();

    $migration = phase3h2Migration();

    expect($migration->backfillExistingLinks())->toBe(1);
    expect($migration->backfillExistingLinks())->toBe(0);

    expect(UserPregnancyLink::count())->toBe(1);
});

it('backfills explicit links added after a previous run without restamping earlier rows', function () {
    $userA = phase3h2User();
    $first = phase3h2Patient('First');
    $first->user_id = $userA->id;
    $first->save();

    $migration = phase3h2Migration();

    \Illuminate\Support\Carbon::setTestNow('2026-10-14 08:00:00');
    expect($migration->backfillExistingLinks())->toBe(1);

    // A second episode gains an explicit pointer AFTER the first run (e.g.
    // a later Phase 2B pairing replayed through backfill): only the new
    // pair may be inserted on the next run.
    $userB = phase3h2User();
    $second = phase3h2Patient('Second');
    $second->user_id = $userB->id;
    $second->save();

    \Illuminate\Support\Carbon::setTestNow('2026-10-14 09:00:00');
    expect($migration->backfillExistingLinks())->toBe(1);

    \Illuminate\Support\Carbon::setTestNow();

    expect(UserPregnancyLink::count())->toBe(2);

    // Earlier evidence keeps its original provenance timestamps; the new
    // row carries the later run's timestamp. Nothing is restamped.
    $firstLink = UserPregnancyLink::where('patient_id', $first->id)->sole();
    expect($firstLink->verified_at->toDateTimeString())->toBe('2026-10-14 08:00:00');

    $secondLink = UserPregnancyLink::where('patient_id', $second->id)->sole();
    expect($secondLink->verified_at->toDateTimeString())->toBe('2026-10-14 09:00:00');
});

it('never reassigns or restamps an already-linked pair that reappears in the pointer source', function () {
    $user = phase3h2User();
    $verifier = phase3h2User('staff');

    $patient = phase3h2Patient();
    $patient->user_id = $user->id;
    $patient->save();

    // Verified evidence recorded through the Phase 2B flow: a real
    // verifier and a past timestamp. A later backfill run must leave it
    // completely untouched - the anti-join excludes the pair from the
    // INSERT entirely.
    $existing = phase3h2Link($user, $patient, $verifier);
    DB::table('user_pregnancy_links')
        ->where('id', $existing->id)
        ->update(['verified_at' => '2026-01-15 10:00:00']);

    $migration = phase3h2Migration();

    expect($migration->backfillExistingLinks())->toBe(0);

    $link = UserPregnancyLink::sole();

    expect($link->id)->toBe($existing->id);
    expect($link->user_id)->toBe($user->id);
    expect($link->patient_id)->toBe($patient->id);
    expect($link->verified_by)->toBe($verifier->id);
    expect($link->verified_at->toDateTimeString())->toBe('2026-01-15 10:00:00');
});

it('surfaces unexpected database failures during backfill instead of silently suppressing them', function () {
    $user = phase3h2User();
    $patient = phase3h2Patient();
    $patient->user_id = $user->id;
    $patient->save();

    // Deterministic mid-backfill failure that insertOrIgnore() would have
    // silently swallowed: a SQLite trigger aborts the INSERT for this
    // pair. A safe backfill must let the QueryException propagate (the
    // migration run fails loudly) rather than returning a partial count
    // that hides the failure. The prior insertOrIgnore implementation was
    // exactly this hazard on MySQL, where INSERT IGNORE also discards
    // truncation, range and NULL-into-NOT-NULL errors.
    DB::statement(
        'CREATE TRIGGER phase3h2_inject_backfill_failure BEFORE INSERT ON user_pregnancy_links '
        . 'FOR EACH ROW WHEN NEW.patient_id = ' . (int) $patient->id . ' '
        . 'BEGIN SELECT RAISE(ABORT, \'injected backfill failure\'); END;'
    );

    expect(fn () => phase3h2Migration()->backfillExistingLinks())
        ->toThrow(QueryException::class);

    DB::statement('DROP TRIGGER phase3h2_inject_backfill_failure');

    // The aborted run left no partial evidence for the failing pair, and
    // once the injected fault is removed the backfill completes normally -
    // the failure corrupted nothing.
    expect(UserPregnancyLink::where('patient_id', $patient->id)->exists())->toBeFalse();

    expect(phase3h2Migration()->backfillExistingLinks())->toBe(1);
    expect(UserPregnancyLink::count())->toBe(1);
});

it('keeps unrelated episodes unlinked across repeated backfill runs', function () {
    $linked = phase3h2Patient('Linked');
    $linked->user_id = phase3h2User()->id;
    $linked->save();

    $unlinkedA = phase3h2Patient('UnlinkedA');
    $unlinkedB = phase3h2Patient('UnlinkedB');

    $migration = phase3h2Migration();

    expect($migration->backfillExistingLinks())->toBe(1);
    expect($migration->backfillExistingLinks())->toBe(0);

    expect(UserPregnancyLink::count())->toBe(1);
    expect(UserPregnancyLink::sole()->patient_id)->toBe($linked->id);
    expect(UserPregnancyLink::where('patient_id', [$unlinkedA->id, $unlinkedB->id])->exists())->toBeFalse();
});

it('includes archived explicitly linked pregnancies in the backfill while the portal stays denied', function () {
    $user = phase3h2User();
    $patient = phase3h2Patient();
    $patient->user_id = $user->id;
    $patient->save();
    $patient->delete();

    phase3h2Migration()->backfillExistingLinks();

    // The explicit relationship evidence is preserved even for an archived
    // episode...
    expect(UserPregnancyLink::where('patient_id', $patient->id)->exists())->toBeTrue();

    // ...but the stored link does NOT override the existing portal
    // eligibility middleware: an archived current pregnancy stays 403.
    $this->actingAs($user)
        ->get(route('patient.portal.coming-soon'))
        ->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Account creation integration
|--------------------------------------------------------------------------
*/

it('creates exactly one verified link when provisioning a patient portal account', function () {
    $staff = phase3h2User('staff');
    $patient = phase3h2Patient();

    app(PatientAccountCreationService::class)->createForPatient(
        $patient,
        $staff,
        'portal.link@phase3h2.test'
    );

    $account = User::where('email', 'portal.link@phase3h2.test')->first();

    expect($account)->not->toBeNull();
    expect(UserPregnancyLink::count())->toBe(1);

    $link = UserPregnancyLink::sole();

    expect($link->user_id)->toBe($account->id);
    expect($link->patient_id)->toBe($patient->id);
    expect($link->verified_by)->toBe($staff->id);
    expect($link->verified_at)->not->toBeNull();
    expect($link->user()->first()->id)->toBe($account->id);
    expect($link->patient()->first()->id)->toBe($patient->id);
    expect($link->verifier()->first()->id)->toBe($staff->id);
});

it('does not link any unrelated pregnancy episode during account creation', function () {
    $staff = phase3h2User('staff');
    $patient = phase3h2Patient('Ana');
    $unrelated = phase3h2Patient('Bea');

    app(PatientAccountCreationService::class)->createForPatient(
        $patient,
        $staff,
        'only.ana@phase3h2.test'
    );

    expect(UserPregnancyLink::count())->toBe(1);
    expect(UserPregnancyLink::sole()->patient_id)->toBe($patient->id);
    expect($unrelated->fresh()->user_id)->toBeNull();
});

it('does not create duplicate links when account creation is attempted twice', function () {
    $staff = phase3h2User('staff');
    $patient = phase3h2Patient();

    $service = app(PatientAccountCreationService::class);

    $service->createForPatient($patient, $staff, 'once.only@phase3h2.test');

    expect(fn () => $service->createForPatient(
        $patient,
        $staff,
        'twice.now@phase3h2.test'
    ))->toThrow(DomainException::class, 'This patient already has a login account.');

    expect(UserPregnancyLink::count())->toBe(1);
    expect(User::where('email', 'twice.now@phase3h2.test')->count())->toBe(0);
});

it('rolls back the account, pointer and verified link together when the link insert fails', function () {
    $patient = phase3h2Patient();

    // Deterministic mid-transaction failure: the verified_by foreign key
    // rejects a non-existent actor, so the link insert (AFTER the users
    // insert and AFTER the patients.user_id write) fails inside the same
    // transaction. If any step were not covered, a partial account, a
    // dangling pointer or a stray link row would survive.
    $ghostActor = new User();
    $ghostActor->id = 424242;

    $refused = false;

    try {
        app(PatientAccountCreationService::class)->createForPatient(
            $patient,
            $ghostActor,
            'rollback.link@phase3h2.test'
        );
    } catch (Throwable) {
        $refused = true;
    }

    expect($refused)->toBeTrue();
    expect(User::where('email', 'rollback.link@phase3h2.test')->count())->toBe(0);
    expect($patient->fresh()->user_id)->toBeNull();
    expect(UserPregnancyLink::count())->toBe(0);
});

it('refuses to provision when conflicting verified link evidence already exists and reassigns nothing', function () {
    $staff = phase3h2User('staff');
    $patient = phase3h2Patient();
    $priorOwner = phase3h2User();

    // Link evidence exists WITHOUT a current pointer (inconsistent state or
    // a future unlink flow): ownership must never be silently reassigned.
    phase3h2Link($priorOwner, $patient);

    expect(fn () => app(PatientAccountCreationService::class)->createForPatient(
        $patient,
        $staff,
        'conflict.link@phase3h2.test'
    ))->toThrow(DomainException::class, 'This patient already has a verified pregnancy link to a login account.');

    expect(User::where('email', 'conflict.link@phase3h2.test')->count())->toBe(0);
    expect($patient->fresh()->user_id)->toBeNull();

    $link = UserPregnancyLink::sole();

    expect($link->user_id)->toBe($priorOwner->id);
    expect($link->patient_id)->toBe($patient->id);
});

it('keeps existing account-creation eligibility restrictions effective', function () {
    $staff = phase3h2User('staff');
    $service = app(PatientAccountCreationService::class);

    $archived = phase3h2Patient('Archived');
    $archived->delete();

    expect(fn () => $service->createForPatient(
        $archived,
        $staff,
        'archived.case@phase3h2.test'
    ))->toThrow(DomainException::class, 'Archived patient records cannot be given a login account.');

    $alreadyLinked = phase3h2Patient('Already');
    $alreadyLinked->user_id = phase3h2User()->id;
    $alreadyLinked->save();

    expect(fn () => $service->createForPatient(
        $alreadyLinked,
        $staff,
        'already.case@phase3h2.test'
    ))->toThrow(DomainException::class, 'This patient already has a login account.');

    expect(User::whereIn('email', ['archived.case@phase3h2.test', 'already.case@phase3h2.test'])->count())->toBe(0);
    expect(UserPregnancyLink::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Rollback safety
|--------------------------------------------------------------------------
*/

it('refuses to roll back the migration while verified links exist', function () {
    $user = phase3h2User();
    $patient = phase3h2Patient();
    $patient->user_id = $user->id;
    $patient->save();
    phase3h2Link($user, $patient);

    $migration = phase3h2Migration();

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);

    expect(Schema::hasTable('user_pregnancy_links'))->toBeTrue();
    expect(UserPregnancyLink::count())->toBe(1);

    // Existing account pointers are never rewritten by a refused rollback.
    expect($patient->fresh()->user_id)->toBe($user->id);
});

it('rolls back an empty user_pregnancy_links table safely', function () {
    $migration = phase3h2Migration();

    expect(UserPregnancyLink::count())->toBe(0);

    $migration->down();
    expect(Schema::hasTable('user_pregnancy_links'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('user_pregnancy_links'))->toBeTrue();
    expect(UserPregnancyLink::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Security and regressions
|--------------------------------------------------------------------------
*/

it('does not grant portal access from a link row alone - the pointer still decides', function () {
    $pointerOwner = phase3h2User();
    $patient = phase3h2Patient();
    $patient->user_id = $pointerOwner->id;
    $patient->save();

    // A second account holds verified link evidence to the SAME pregnancy,
    // but holds no patients.user_id pointer: the portal must still refuse
    // it - the link table is history evidence, never an access grant.
    $historyOnly = phase3h2User();
    phase3h2Link($historyOnly, $patient);

    $this->actingAs($historyOnly)
        ->get(route('patient.portal.coming-soon'))
        ->assertForbidden();

    $this->actingAs($pointerOwner)
        ->get(route('patient.portal.coming-soon'))
        ->assertOk();

    // The pointer itself is untouched by the existence of the other link.
    expect($patient->fresh()->user_id)->toBe($pointerOwner->id);
});

it('keeps existing portal routes resolving through patients.user_id', function () {
    $staff = phase3h2User('staff');
    $patient = phase3h2Patient();

    app(PatientAccountCreationService::class)->createForPatient(
        $patient,
        $staff,
        'portal.resolve@phase3h2.test'
    );

    $account = User::where('email', 'portal.resolve@phase3h2.test')->first();

    // A freshly provisioned account must still change its password first
    // (must-change-password middleware redirects); simulate the completed
    // first-login change so the established-session path is what resolves.
    $account->must_change_password = false;
    $account->save();

    $this->actingAs($account)
        ->get(route('patient.portal.coming-soon'))
        ->assertOk();
});

it('denies portal access when the current linked pregnancy is archived', function () {
    $staff = phase3h2User('staff');
    $patient = phase3h2Patient();

    app(PatientAccountCreationService::class)->createForPatient(
        $patient,
        $staff,
        'archived.portal@phase3h2.test'
    );

    $account = User::where('email', 'archived.portal@phase3h2.test')->first();

    $patient->delete();

    $this->actingAs($account)
        ->get(route('patient.portal.coming-soon'))
        ->assertForbidden();

    // The verified link row still exists but grants nothing over the
    // archived current pregnancy.
    expect(UserPregnancyLink::where('patient_id', $patient->id)->exists())->toBeTrue();
});

it('adds no historical pregnancy routes to the application', function () {
    $historyRoutes = collect(Route::getRoutes())
        ->filter(function ($route): bool {
            $uri = $route->uri();

            return str_contains($uri, '/patient/history')
                || str_contains($uri, '/patient/pregnancies')
                || str_contains($uri, '/patient-portal/history')
                || str_contains($uri, '/patient-portal/pregnancies')
                || str_contains($uri, '/patient/pregnancy-history');
        });

    expect($historyRoutes)->toHaveCount(0);
});
