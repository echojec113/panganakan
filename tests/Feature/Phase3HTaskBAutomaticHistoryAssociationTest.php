<?php

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Models\UserPregnancyLink;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * DEPLA Task B — Automatic Legacy Pregnancy History Association.
 * SQLite :memory: only (phpunit.xml forces isolation).
 *
 * Covers the approved four-field identity expansion during Patient Portal
 * account provisioning, the conflict/rollback doctrine, DELIVERED-only
 * account behavior, the idempotent reconciliation command (dry-run
 * default, --apply, counts-only output) and the preserved portal
 * ownership boundaries.
 */

beforeEach(function () {
    $this->withoutVite();
});

function taskbPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Teresa',
        'middle_name' => 'Lim',
        'last_name' => 'Cruz',
        'age' => 32,
        'birthdate' => '1993-07-21',
        'address' => 'Task B test address',
        'contact_number' => '09170001111',
        'email' => strtolower(uniqid('taskb.', true)) . '@taskb.test',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
        'lmp' => '2026-02-01',
        'edd' => '2026-11-08',
    ], $overrides));
}

function taskbDelivered(array $overrides = []): Patient
{
    return taskbPatient(array_merge([
        'status' => 'DELIVERED',
        'lmp' => '2022-01-05',
        'edd' => '2022-10-11',
        'delivery_date' => '2022-10-08',
        'gravida' => 2,
        'para' => 1,
    ], $overrides));
}

function taskbStaff(string $role = 'staff'): User
{
    return User::factory()->create(['role' => $role]);
}

function taskbLink(User $user, Patient $patient): UserPregnancyLink
{
    $link = new UserPregnancyLink();
    $link->user_id = $user->id;
    $link->patient_id = $patient->id;
    $link->verified_by = $user->id;
    $link->verified_at = now();
    $link->save();

    return $link;
}

function taskbPayload(array $overrides = []): array
{
    // Phase 1.5: email-only provisioning; password fields no longer exist.
    return array_merge([
        'email' => 'teresa.cruz@taskb.test',
    ], $overrides);
}

/** Provision through the real HTTP endpoint; returns the fresh portal user with the first-login password gate cleared. */
function taskbProvision(Patient $patient, string $email = 'teresa.cruz@taskb.test'): User
{
    $staff = taskbStaff();

    test()->actingAs($staff)
        ->post(route('patients.account.store', $patient->id), taskbPayload(['email' => $email]))
        ->assertRedirect(route('patients.show', $patient->id))
        ->assertSessionHas('success');

    $user = User::where('email', $email)->firstOrFail();

    // Simulate the completed first-login password change so portal GETs
    // are not bounced by the must-change-password workflow.
    $user->forceFill(['must_change_password' => false])->save();

    return $user;
}

/** Pre-existing account state: pointer episode + its membership row (the real post-provisioning shape). */
function taskbOwned(User $user, Patient $episode): Patient
{
    $episode->user_id = $user->id;
    $episode->save();
    taskbLink($user, $episode);

    return $episode;
}

/*
|--------------------------------------------------------------------------
| 1–4. Provisioning expansion: single, multiple, DELIVERED-only, first login
|--------------------------------------------------------------------------
*/

test('provisioning from an ONGOING episode automatically links a previous DELIVERED episode', function () {
    $ongoing = taskbPatient();
    $previous = taskbDelivered();

    $user = taskbProvision($ongoing);

    expect(UserPregnancyLink::where('user_id', $user->id)->pluck('patient_id')->sort()->values()->all())
        ->toBe([$ongoing->id, $previous->id])
        ->and($ongoing->fresh()->user_id)->toBe($user->id)
        ->and($previous->fresh()->user_id)->toBeNull();
});

test('provisioning links every matching DELIVERED sibling and audits each new association by ID only', function () {
    $ongoing = taskbPatient();
    $first = taskbDelivered(['delivery_date' => '2019-03-01']);
    $second = taskbDelivered(['delivery_date' => '2021-06-15']);
    $third = taskbDelivered(['delivery_date' => '2023-09-30']);

    $user = taskbProvision($ongoing);

    expect(UserPregnancyLink::where('user_id', $user->id)->count())->toBe(4);

    $html = test()->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain(route('patient.pregnancies.show', ['episodeId' => $first->id]))
        ->toContain(route('patient.pregnancies.show', ['episodeId' => $second->id]))
        ->toContain(route('patient.pregnancies.show', ['episodeId' => $third->id]))
        ->not->toContain(route('patient.pregnancies.show', ['episodeId' => $ongoing->id]));

    // One ID-only audit row per newly created association - no names.
    $audits = AuditLog::where('action', 'PREGNANCY_HISTORY_LINKED')->get();
    expect($audits)->toHaveCount(3);
    foreach ($audits as $audit) {
        expect($audit->description)
            ->toContain('user ID ' . $user->id)
            ->toContain('pregnancy episode ID')
            ->not->toContain('Teresa')
            ->not->toContain('Cruz');
    }
});

test('provisioning from a DELIVERED-only patient links matching history without inventing an ONGOING pregnancy', function () {
    $anchor = taskbDelivered();
    $sibling = taskbDelivered(['delivery_date' => '2020-02-14']);

    $user = taskbProvision($anchor);

    expect(Patient::count())->toBe(2)
        ->and(Patient::where('status', 'ONGOING')->count())->toBe(0)
        ->and(UserPregnancyLink::where('user_id', $user->id)->count())->toBe(2);

    $html = test()->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain(route('patient.pregnancies.show', ['episodeId' => $anchor->id]))
        ->toContain(route('patient.pregnancies.show', ['episodeId' => $sibling->id]));

    // My Medical Records keeps working with a DELIVERED current pointer.
    test()->actingAs($user)
        ->get(route('patient.medical-records.index'))
        ->assertOk();
});

test('all associated episodes are visible on first login with no further staff action', function () {
    $ongoing = taskbPatient();
    $previous = taskbDelivered();

    $user = taskbProvision($ongoing);

    // First login: dashboard, My Medical Records and Pregnancy History.
    test()->actingAs($user)
        ->get(route('patient.portal.coming-soon'))
        ->assertOk()
        ->assertSee('Teresa');

    test()->actingAs($user)
        ->get(route('patient.medical-records.index'))
        ->assertOk();

    test()->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->assertSee(route('patient.pregnancies.show', ['episodeId' => $previous->id]));
});

/*
|--------------------------------------------------------------------------
| 5–8. Four-field identity rule: NULL middle name, birthdate, mismatches, email
|--------------------------------------------------------------------------
*/

test('NULL middle names match exactly while a sibling carrying a middle name never matches', function () {
    $ongoing = taskbPatient(['middle_name' => null]);
    $matching = taskbDelivered(['middle_name' => null]);
    $different = taskbDelivered(['middle_name' => 'Ann', 'delivery_date' => '2018-05-05']);

    $user = taskbProvision($ongoing);

    expect(UserPregnancyLink::where('user_id', $user->id)->pluck('patient_id')->sort()->values()->all())
        ->toBe([$ongoing->id, $matching->id])
        ->and(UserPregnancyLink::where('patient_id', $different->id)->exists())->toBeFalse();
});

test('NULL birthdates match under the NULL-safe rule', function () {
    $ongoing = taskbPatient(['birthdate' => null]);
    $matching = taskbDelivered(['birthdate' => null]);

    $user = taskbProvision($ongoing);

    expect(UserPregnancyLink::where('user_id', $user->id)->count())->toBe(2);
});

test('a mismatch in any single identity field prevents association', function () {
    $ongoing = taskbPatient();
    $trueSibling = taskbDelivered();
    $otherFirst = taskbDelivered(['first_name' => 'Tereza', 'delivery_date' => '2018-01-01']);
    $otherLast = taskbDelivered(['last_name' => 'Santos', 'delivery_date' => '2018-02-02']);
    $otherMiddle = taskbDelivered(['middle_name' => 'Rosa', 'delivery_date' => '2018-03-03']);
    $otherBirthdate = taskbDelivered(['birthdate' => '1993-07-22', 'delivery_date' => '2018-04-04']);

    $user = taskbProvision($ongoing);

    expect(UserPregnancyLink::where('user_id', $user->id)->pluck('patient_id')->sort()->values()->all())
        ->toBe([$ongoing->id, $trueSibling->id]);

    foreach ([$otherFirst, $otherLast, $otherMiddle, $otherBirthdate] as $distractor) {
        expect(UserPregnancyLink::where('patient_id', $distractor->id)->exists())->toBeFalse();
    }
});

test('email similarity or a shared email never causes association', function () {
    $ongoing = taskbPatient(['email' => 'shared@taskb.test']);
    $trueSibling = taskbDelivered(['email' => 'someone.else@elsewhere.test']);
    $emailOnly = taskbDelivered(['email' => 'shared@taskb.test', 'first_name' => 'Maria', 'delivery_date' => '2017-07-07']);

    $user = taskbProvision($ongoing);

    expect(UserPregnancyLink::where('user_id', $user->id)->pluck('patient_id')->sort()->values()->all())
        ->toBe([$ongoing->id, $trueSibling->id])
        ->and(UserPregnancyLink::where('patient_id', $emailOnly->id)->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| 9–10. Scope exclusions: archived and ONGOING siblings
|--------------------------------------------------------------------------
*/

test('archived DELIVERED episodes are excluded from automatic association', function () {
    $ongoing = taskbPatient();
    $archived = taskbDelivered();
    $archived->delete();

    $user = taskbProvision($ongoing);

    expect(UserPregnancyLink::where('user_id', $user->id)->pluck('patient_id')->all())
        ->toBe([$ongoing->id])
        ->and(UserPregnancyLink::where('patient_id', $archived->id)->exists())->toBeFalse();
});

test('ONGOING siblings are never auto-linked into Pregnancy History', function () {
    $ongoing = taskbPatient();
    $ongoingSibling = taskbPatient(['lmp' => '2025-12-01', 'edd' => '2026-09-07']);

    $user = taskbProvision($ongoing);

    expect(UserPregnancyLink::where('patient_id', $ongoingSibling->id)->exists())->toBeFalse();

    $html = test()->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain(route('patient.pregnancies.show', ['episodeId' => $ongoingSibling->id]))
        ->not->toContain('Sep 07, 2026');
});

/*
|--------------------------------------------------------------------------
| 12–14. Conflict doctrine: abort + rollback, no duplicates, guard preserved
|--------------------------------------------------------------------------
*/

test('a foreign ownership link on a candidate aborts account creation with a full rollback', function () {
    $ongoing = taskbPatient();
    $sibling = taskbDelivered();
    $foreignOwner = User::factory()->create(['role' => 'patient']);
    taskbLink($foreignOwner, $sibling);

    $staff = taskbStaff();
    $linksBefore = UserPregnancyLink::count();

    test()->actingAs($staff)
        ->post(route('patients.account.store', $ongoing->id), taskbPayload())
        ->assertRedirect()
        ->assertSessionHasErrors('status');

    expect(User::where('email', 'teresa.cruz@taskb.test')->exists())->toBeFalse()
        ->and($ongoing->fresh()->user_id)->toBeNull()
        ->and(UserPregnancyLink::count())->toBe($linksBefore)
        ->and(UserPregnancyLink::where('patient_id', $sibling->id)->first()->user_id)->toBe($foreignOwner->id)
        ->and(AuditLog::where('action', 'PATIENT_ACCOUNT_CREATED')->count())->toBe(0)
        ->and(AuditLog::where('action', 'PREGNANCY_HISTORY_LINKED')->count())->toBe(0);
});

test('a foreign ownership pointer on a candidate aborts account creation with a full rollback', function () {
    $ongoing = taskbPatient();
    $sibling = taskbDelivered();
    $foreignOwner = User::factory()->create(['role' => 'patient']);
    $sibling->user_id = $foreignOwner->id;
    $sibling->save();

    $staff = taskbStaff();

    test()->actingAs($staff)
        ->post(route('patients.account.store', $ongoing->id), taskbPayload())
        ->assertRedirect()
        ->assertSessionHasErrors('status');

    expect(User::where('email', 'teresa.cruz@taskb.test')->exists())->toBeFalse()
        ->and($ongoing->fresh()->user_id)->toBeNull()
        ->and(UserPregnancyLink::where('patient_id', $ongoing->id)->exists())->toBeFalse()
        ->and($sibling->fresh()->user_id)->toBe($foreignOwner->id);
});

test('a linked episode refuses a second account and no duplicate links appear', function () {
    $ongoing = taskbPatient();
    $sibling = taskbDelivered();

    $user = taskbProvision($ongoing);
    expect(UserPregnancyLink::where('user_id', $user->id)->count())->toBe(2);

    // A second provisioning attempt on the now-linked sibling episode is
    // refused by the preserved Phase 2B/3H.2 conflict guard.
    $staff = taskbStaff();
    test()->actingAs($staff)
        ->post(route('patients.account.store', $sibling->id), taskbPayload(['email' => 'second.login@taskb.test']))
        ->assertRedirect()
        ->assertSessionHasErrors('status');

    expect(User::where('email', 'second.login@taskb.test')->exists())->toBeFalse()
        ->and(UserPregnancyLink::where('user_id', $user->id)->count())->toBe(2)
        ->and(UserPregnancyLink::where('patient_id', $sibling->id)->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| 18–20. Preserved portal ownership boundaries
|--------------------------------------------------------------------------
*/

test("a cross-account historical episode URL returns 404 and is never listed", function () {
    $ongoingA = taskbPatient();
    $previousA = taskbDelivered();
    $userA = taskbProvision($ongoingA);

    $ongoingB = taskbPatient(['first_name' => 'Bianca']);
    $userB = taskbProvision($ongoingB, 'bianca@taskb.test');

    $html = test()->actingAs($userB)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain(route('patient.pregnancies.show', ['episodeId' => $previousA->id]));

    test()->actingAs($userB)
        ->get(route('patient.pregnancies.show', ['episodeId' => $previousA->id]))
        ->assertNotFound();
});

test('the ONGOING current episode never appears in Pregnancy History while the DELIVERED sibling does', function () {
    $ongoing = taskbPatient();
    $previous = taskbDelivered();

    $user = taskbProvision($ongoing);

    $html = test()->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('Nov 08, 2026') // the ONGOING EDD
        ->toContain(route('patient.pregnancies.show', ['episodeId' => $previous->id]));
});

test('a DELIVERED current pointer episode itself appears in Pregnancy History', function () {
    $anchor = taskbDelivered();
    $sibling = taskbDelivered(['delivery_date' => '2015-11-11']);

    $user = taskbProvision($anchor);

    $html = test()->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain(route('patient.pregnancies.show', ['episodeId' => $anchor->id]))
        ->toContain(route('patient.pregnancies.show', ['episodeId' => $sibling->id]));

    test()->actingAs($user)
        ->get(route('patient.pregnancies.show', ['episodeId' => $anchor->id]))
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| 21–22. Start New Pregnancy preservation + existing portal flows
|--------------------------------------------------------------------------
*/

test('Start New Pregnancy after Task B provisioning keeps every previous DELIVERED episode in History', function () {
    $anchor = taskbDelivered();
    $sibling = taskbDelivered(['delivery_date' => '2018-08-08']);

    $user = taskbProvision($anchor);
    expect(UserPregnancyLink::where('user_id', $user->id)->count())->toBe(2);

    $staff = taskbStaff();
    test()->actingAs($staff)
        ->post(route('patients.start-new-pregnancy', $anchor->id), [
            'lmp' => today()->subWeeks(6)->toDateString(),
            'edd' => today()->subWeeks(6)->addDays(280)->toDateString(),
            'address' => 'Purok 5, Barangay Dos',
            'contact_number' => '09190002222',
        ])
        ->assertRedirect();

    $new = Patient::where('status', 'ONGOING')->first();
    expect($new)->not->toBeNull()
        ->and($new->user_id)->toBe($user->id)
        ->and(UserPregnancyLink::where('user_id', $user->id)->count())->toBe(3);

    $html = test()->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain(route('patient.pregnancies.show', ['episodeId' => $anchor->id]))
        ->toContain(route('patient.pregnancies.show', ['episodeId' => $sibling->id]))
        ->not->toContain(route('patient.pregnancies.show', ['episodeId' => $new->id]));
});

test('existing Patient Portal flows keep working after Task B provisioning', function () {
    $ongoing = taskbPatient();
    taskbDelivered();

    $user = taskbProvision($ongoing);

    test()->actingAs($user)->get(route('patient.portal.coming-soon'))->assertOk();
    test()->actingAs($user)->get(route('patient.profile.show'))->assertOk();
    test()->actingAs($user)->get(route('patient.medical-records.index'))->assertOk();
    test()->actingAs($user)->get(route('patient.pregnancies.index'))->assertOk();
});

/*
|--------------------------------------------------------------------------
| 15–17 + reconciliation: dry-run, apply, idempotency, preservation, refusal
|--------------------------------------------------------------------------
*/

test('reconciliation dry-run reports aggregate counts and performs no writes', function () {
    $owner = User::factory()->create(['role' => 'patient']);
    $pointer = taskbOwned($owner, taskbPatient());
    $missing = taskbDelivered();

    $linksBefore = UserPregnancyLink::count();
    $auditsBefore = AuditLog::count();
    $pointersBefore = DB::table('patients')->orderBy('id')->pluck('user_id', 'id')->all();

    $exit = Artisan::call('depla:reconcile-pregnancy-history');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('DRY RUN (no changes written)')
        ->toContain('Missing verified ownership links: 1')
        ->toContain('Links created: 0')
        ->and(UserPregnancyLink::count())->toBe($linksBefore)
        ->and(AuditLog::count())->toBe($auditsBefore)
        ->and(DB::table('patients')->orderBy('id')->pluck('user_id', 'id')->all())->toBe($pointersBefore)
        ->and(UserPregnancyLink::where('patient_id', $missing->id)->exists())->toBeFalse()
        ->and($pointer->fresh()->user_id)->toBe($owner->id);
});

test('reconciliation --apply creates the missing verified links with ID-only audits and matching History', function () {
    $owner = User::factory()->create(['role' => 'patient']);
    taskbOwned($owner, taskbPatient());
    $missing = taskbDelivered();

    $exit = Artisan::call('depla:reconcile-pregnancy-history', ['--apply' => true]);

    expect($exit)->toBe(0)
        ->and(Artisan::output())
        ->toContain('APPLY')
        ->toContain('Links created: 1')
        ->and(UserPregnancyLink::where('user_id', $owner->id)->where('patient_id', $missing->id)->exists())->toBeTrue();

    $link = UserPregnancyLink::where('user_id', $owner->id)->where('patient_id', $missing->id)->first();
    expect($link->verified_by)->toBeNull()
        ->and($link->verified_at)->not->toBeNull();

    $audit = AuditLog::where('action', 'PREGNANCY_HISTORY_LINKED')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($owner->id)
        ->and($audit->description)
        ->toContain('console reconciliation')
        ->toContain('user ID ' . $owner->id)
        ->not->toContain('Teresa')
        ->not->toContain('Cruz');

    $owner->forceFill(['must_change_password' => false])->save();
    test()->actingAs($owner)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->assertSee(route('patient.pregnancies.show', ['episodeId' => $missing->id]));
});

test('reconciliation --apply is idempotent across repeated runs', function () {
    $owner = User::factory()->create(['role' => 'patient']);
    taskbOwned($owner, taskbPatient());
    taskbDelivered();

    Artisan::call('depla:reconcile-pregnancy-history', ['--apply' => true]);
    $afterFirst = UserPregnancyLink::count();

    $exit = Artisan::call('depla:reconcile-pregnancy-history', ['--apply' => true]);

    expect($exit)->toBe(0)
        ->and(Artisan::output())
        ->toContain('Missing verified ownership links: 0')
        ->toContain('Matching episodes already linked (preserved): 1')
        ->toContain('Links created: 0')
        ->and(UserPregnancyLink::count())->toBe($afterFirst)
        ->and(UserPregnancyLink::where('user_id', $owner->id)->count())->toBe(2);
});

test('reconciliation preserves an existing same-account link without duplication', function () {
    $owner = User::factory()->create(['role' => 'patient']);
    $pointer = taskbOwned($owner, taskbPatient());
    $sibling = taskbDelivered();
    taskbLink($owner, $sibling); // already associated earlier

    $exit = Artisan::call('depla:reconcile-pregnancy-history', ['--apply' => true]);

    expect($exit)->toBe(0)
        ->and(Artisan::output())
        ->toContain('Missing verified ownership links: 0')
        ->toContain('Matching episodes already linked (preserved): 1')
        ->toContain('Links created: 0')
        ->and(UserPregnancyLink::where('user_id', $owner->id)->count())->toBe(2)
        ->and(UserPregnancyLink::where('patient_id', $sibling->id)->count())->toBe(1)
        ->and($pointer->fresh()->user_id)->toBe($owner->id);
});

test('reconciliation refuses a conflicting cluster as a whole and still processes clean ones', function () {
    // Clean cluster: ownerA with one missing sibling.
    $ownerA = User::factory()->create(['role' => 'patient']);
    taskbOwned($ownerA, taskbPatient());
    $missingA = taskbDelivered();

    // Conflicting cluster: ownerB's pointer + a candidate owned by a third account.
    $ownerB = User::factory()->create(['role' => 'patient']);
    taskbOwned($ownerB, taskbPatient(['first_name' => 'Bianca']));
    $conflicted = taskbDelivered(['first_name' => 'Bianca']);
    $third = User::factory()->create(['role' => 'patient']);
    taskbLink($third, $conflicted);

    $exit = Artisan::call('depla:reconcile-pregnancy-history', ['--apply' => true]);

    expect($exit)->toBe(0)
        ->and(Artisan::output())
        ->toContain('Conflicting clusters refused: 1')
        ->toContain('Links created: 1')
        ->and(UserPregnancyLink::where('user_id', $ownerA->id)->where('patient_id', $missingA->id)->exists())->toBeTrue()
        ->and(UserPregnancyLink::where('patient_id', $conflicted->id)->count())->toBe(1)
        ->and(UserPregnancyLink::where('user_id', $ownerB->id)->where('patient_id', $conflicted->id)->exists())->toBeFalse();
});

test('reconciliation skips archived accounts and never restores or links them', function () {
    $archived = User::factory()->create(['role' => 'patient']);
    taskbOwned($archived, taskbPatient());
    taskbDelivered();
    $archived->delete();

    $exit = Artisan::call('depla:reconcile-pregnancy-history', ['--apply' => true]);

    expect($exit)->toBe(0)
        ->and(Artisan::output())
        ->toContain('Skipped (archived or missing account): 1')
        ->toContain('Links created: 0')
        ->and($archived->fresh()->trashed())->toBeTrue()
        ->and(UserPregnancyLink::where('user_id', $archived->id)->count())->toBe(1);
});
