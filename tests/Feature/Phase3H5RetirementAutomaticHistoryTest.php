<?php

use App\Models\Patient;
use App\Models\User;
use App\Models\UserPregnancyLink;
use App\Services\PregnancyOutcomeRecordingService;
use App\Support\PregnancyOutcomeVocabulary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * DEPLA Phase 3H.5 Retirement + Automatic Pregnancy History (Task A).
 * SQLite :memory: only (phpunit.xml forces isolation).
 *
 * Verifies the lifecycle rule (owned + DELIVERED => visible in Pregnancy
 * History on the next request, with or without Start New Pregnancy), the
 * ownership boundaries (unlinked / cross-account / archived / ONGOING stay
 * inaccessible), that the retired Admin linking workflow (routes, UI,
 * service) is fully gone, that the portal remains GET-only read-only, and
 * that existing ownership link rows survive every read path untouched.
 */

beforeEach(function () {
    $this->withoutVite();
});

function ret3h5User(string $role = 'patient'): User
{
    return User::factory()->create(['role' => $role]);
}

function ret3h5Patient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Ana',
        'last_name' => 'Reyes',
        'middle_name' => 'Cruz',
        'age' => 29,
        'birthdate' => '1997-05-14',
        'email' => strtolower(uniqid('ret3h5.', true)) . '@ret3h5.test',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
        'lmp' => '2026-01-05',
        'edd' => '2026-10-12',
    ], $overrides));
}

function ret3h5LinkRow(User $user, Patient $patient): UserPregnancyLink
{
    $link = new UserPregnancyLink();
    $link->user_id = $user->id;
    $link->patient_id = $patient->id;
    $link->verified_at = now();
    $link->save();

    return $link;
}

/** Pointer + membership row: the real post-provisioning owned state. */
function ret3h5OwnedEpisode(User $user, array $overrides = []): Patient
{
    $patient = ret3h5Patient($overrides);
    $patient->user_id = $user->id;
    $patient->save();
    ret3h5LinkRow($user, $patient);

    return $patient;
}

function ret3h5OwnedDelivered(User $user, array $overrides = []): Patient
{
    return ret3h5OwnedEpisode($user, array_merge([
        'status' => 'DELIVERED',
        'lmp' => '2024-02-01',
        'edd' => '2024-11-08',
        'delivery_date' => '2024-11-05',
        'gravida' => 2,
        'para' => 1,
    ], $overrides));
}

/** Previous episode: membership row only, never the pointer (UNIQUE patients.user_id). */
function ret3h5PreviousDelivered(User $user, array $overrides = []): Patient
{
    $patient = ret3h5Patient(array_merge([
        'status' => 'DELIVERED',
        'lmp' => '2024-02-01',
        'edd' => '2024-11-08',
        'delivery_date' => '2024-11-05',
        'gravida' => 2,
        'para' => 1,
    ], $overrides));

    ret3h5LinkRow($user, $patient);

    return $patient;
}

/*
|--------------------------------------------------------------------------
| 1–3. Lifecycle: ONGOING hidden; delivery recording surfaces immediately
|--------------------------------------------------------------------------
*/

test('an ONGOING pregnancy never appears in Pregnancy History', function () {
    $user = ret3h5User();
    ret3h5OwnedEpisode($user); // pointer + link, still ONGOING

    $html = $this->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('You have no previous delivered pregnancy records available yet.')
        ->and($html)->not->toContain('Oct 12, 2026'); // the ONGOING EDD
});

test('recording delivery makes the episode appear in History immediately, without Start New Pregnancy', function () {
    $user = ret3h5User();
    $staff = ret3h5User('staff');
    $episode = ret3h5OwnedEpisode($user); // ONGOING + owned

    // Not visible while ONGOING.
    $this->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->assertDontSee('Nov 05, 2025');

    app(PregnancyOutcomeRecordingService::class)->recordConfirmedDelivery(
        $episode,
        $staff,
        '2025-11-05',
        PregnancyOutcomeVocabulary::DELIVERY_LOCATION_THIS_CLINIC,
        PregnancyOutcomeVocabulary::CONFIRMATION_SOURCE_CLINIC_RECORD,
        [[
            'first_name' => 'Baby',
            'last_name' => 'Reyes',
            'sex' => 'Female',
            'date_of_birth' => '2025-11-05',
            'time_of_birth' => '09:15:00',
            'birth_weight' => 3.1,
            'birth_length' => 49.5,
        ]]
    );

    expect($episode->fresh()->status)->toBe('DELIVERED');

    // Same page request cycle: listed AND openable, no continuation step.
    $this->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->assertSee('Nov 05, 2025')
        ->assertSee(route('patient.pregnancies.show', ['episodeId' => $episode->id]));

    $this->actingAs($user)
        ->get(route('patient.pregnancies.show', ['episodeId' => $episode->id]))
        ->assertOk()
        ->assertSee('Back to Pregnancy History');

    // No new episode was invented - Start New Pregnancy was never used.
    expect(Patient::count())->toBe(1)
        ->and($episode->fresh()->user_id)->toBe($user->id);
});

/*
|--------------------------------------------------------------------------
| 4–6. Read-only records, Start New Pregnancy preservation, history list
|--------------------------------------------------------------------------
*/

test('after Start New Pregnancy the previous DELIVERED episode remains in History and the new ONGOING is current', function () {
    $user = ret3h5User();
    $staff = ret3h5User('staff');
    $old = ret3h5OwnedDelivered($user);

    $this->actingAs($staff)
        ->post(route('patients.start-new-pregnancy', $old->id), [
            'lmp' => today()->subWeeks(8)->toDateString(),
            'edd' => today()->subWeeks(8)->addDays(280)->toDateString(),
            'address' => 'Purok 3, Barangay Uno',
            'contact_number' => '09191234567',
        ])
        ->assertRedirect();

    $new = Patient::where('status', 'ONGOING')->first();
    expect($new)->not->toBeNull()
        ->and($new->user_id)->toBe($user->id)
        ->and($old->fresh()->status)->toBe('DELIVERED')
        ->and($old->fresh()->user_id)->toBeNull();

    // Previous episode still listed; new ONGOING episode is not.
    $html = $this->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain(route('patient.pregnancies.show', ['episodeId' => $old->id]))
        ->and($html)->not->toContain(route('patient.pregnancies.show', ['episodeId' => $new->id]));

    // Both ownership link rows survive the continuation.
    expect(UserPregnancyLink::where('user_id', $user->id)->count())->toBe(2);
});

test('multiple owned DELIVERED episodes are all listed, most recent delivery first', function () {
    $user = ret3h5User();
    $older = ret3h5PreviousDelivered($user, ['delivery_date' => '2022-05-01']);
    $newer = ret3h5PreviousDelivered($user, ['delivery_date' => '2024-08-20']);

    // A fresh current pointer episode (ONGOING) for portal eligibility.
    ret3h5OwnedEpisode($user);

    $html = $this->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain(route('patient.pregnancies.show', ['episodeId' => $older->id]))
        ->and($html)->toContain(route('patient.pregnancies.show', ['episodeId' => $newer->id]));

    $newerPos = strpos($html, route('patient.pregnancies.show', ['episodeId' => $newer->id]));
    $olderPos = strpos($html, route('patient.pregnancies.show', ['episodeId' => $older->id]));
    expect($newerPos)->toBeLessThan($olderPos);
});

/*
|--------------------------------------------------------------------------
| 8–10. Ownership boundaries: unlinked, cross-account, archived, ONGOING
|--------------------------------------------------------------------------
*/

test('unlinked episodes are never listed and never openable', function () {
    $user = ret3h5User();
    ret3h5OwnedEpisode($user);
    $unlinked = ret3h5Patient(['status' => 'DELIVERED', 'delivery_date' => '2023-04-01']);

    $html = $this->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain(route('patient.pregnancies.show', ['episodeId' => $unlinked->id]));

    $this->actingAs($user)
        ->get(route('patient.pregnancies.show', ['episodeId' => $unlinked->id]))
        ->assertNotFound();
});

test("another account's episode returns 404 and is never listed", function () {
    $owner = ret3h5User();
    $intruder = ret3h5User();
    $foreign = ret3h5OwnedDelivered($owner);
    ret3h5OwnedEpisode($intruder);

    $html = $this->actingAs($intruder)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain(route('patient.pregnancies.show', ['episodeId' => $foreign->id]));

    $this->actingAs($intruder)
        ->get(route('patient.pregnancies.show', ['episodeId' => $foreign->id]))
        ->assertNotFound();
});

test('archived episodes remain inaccessible even with an ownership link', function () {
    $user = ret3h5User();
    $episode = ret3h5PreviousDelivered($user);
    ret3h5OwnedEpisode($user); // live current pointer for portal eligibility
    $episode->delete(); // soft-delete (archived)

    $this->actingAs($user)
        ->get(route('patient.pregnancies.show', ['episodeId' => $episode->id]))
        ->assertNotFound();

    $html = $this->actingAs($user)
        ->get(route('patient.pregnancies.index'))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain(route('patient.pregnancies.show', ['episodeId' => $episode->id]));
});

test('an ONGOING episode with an ownership link is still refused by a direct History URL', function () {
    $user = ret3h5User();
    // Membership row on an ONGOING episode (e.g. pre-continuation link) -
    // still out of scope for History, even via direct URL.
    $ongoingHistorical = ret3h5Patient(['status' => 'ONGOING']);
    ret3h5LinkRow($user, $ongoingHistorical);
    // Separate owned current pointer for portal eligibility.
    ret3h5OwnedEpisode($user);

    $this->actingAs($user)
        ->get(route('patient.pregnancies.show', ['episodeId' => $ongoingHistorical->id]))
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| 11–12. Retired Admin linking workflow: routes and UI are gone
|--------------------------------------------------------------------------
*/

test('the retired Admin pregnancy-link routes no longer resolve for any role', function () {
    $admin = ret3h5User('admin');
    $staff = ret3h5User('staff');
    $patientUser = ret3h5User();
    $patient = ret3h5OwnedEpisode($patientUser);

    $uris = [
        '/patients/' . $patient->id . '/pregnancy-link-candidates?q=1',
        '/patients/' . $patient->id . '/pregnancy-links',
        '/patients/' . $patient->id . '/pregnancy-links/1/revoke',
        '/patients/' . $patient->id . '/pregnancy-links/1/restore',
    ];

    foreach ([$admin, $staff, $patientUser] as $actor) {
        foreach ($uris as $uri) {
            $this->actingAs($actor)->get($uri)->assertNotFound();
            $this->actingAs($actor)->post($uri, [])->assertNotFound();
        }
    }

    // Guests too: the URIs no longer exist, so no auth redirect is involved.
    foreach ($uris as $uri) {
        $this->get($uri)->assertNotFound();
        $this->post($uri, [])->assertNotFound();
    }

    foreach ([
        'patients.pregnancy-links.candidates',
        'patients.pregnancy-links.store',
        'patients.pregnancy-links.revoke',
        'patients.pregnancy-links.restore',
    ] as $name) {
        expect(Route::has($name))->toBeFalse();
    }
});

test('the patient profile page no longer renders the Admin Previous Pregnancy Links UI', function () {
    $admin = ret3h5User('admin');
    $patientUser = ret3h5User();
    $patient = ret3h5OwnedEpisode($patientUser);

    $html = $this->actingAs($admin)
        ->get(route('patients.show', $patient->id))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('Previous Pregnancy Links')
        ->and($html)->not->toContain('Add Previous Pregnancy')
        ->and($html)->not->toContain('linkPregnancyModal')
        ->and($html)->not->toContain('openLinkPregnancyModal')
        ->and($html)->not->toContain('pregnancy-link-candidates');
});

/*
|--------------------------------------------------------------------------
| 13–14. Read-only portal + ownership links preserved
|--------------------------------------------------------------------------
*/

test('pregnancy History routes stay GET-only and no write or link route exists', function () {
    $user = ret3h5User();
    ret3h5OwnedDelivered($user);

    $methods = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_contains($route->uri(), 'patient/pregnancies'))
        ->flatMap(fn ($route) => $route->methods())
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect($methods)->toBe(['GET', 'HEAD']);
});

test('history GET requests preserve every ownership link row and pointer', function () {
    $user = ret3h5User();
    ret3h5PreviousDelivered($user, ['delivery_date' => '2023-01-10']);
    $current = ret3h5OwnedEpisode($user);

    $linksBefore = DB::table('user_pregnancy_links')->orderBy('id')->get();
    $pointersBefore = DB::table('patients')->orderBy('id')->pluck('user_id', 'id')->all();

    $this->actingAs($user)->get(route('patient.pregnancies.index'))->assertOk();
    foreach ($linksBefore as $link) {
        $episode = Patient::find($link->patient_id);

        // DELIVERED episodes open read-only; the ONGOING current is 404 here.
        $response = $this->actingAs($user)
            ->get(route('patient.pregnancies.show', ['episodeId' => $link->patient_id]));

        if ($episode->status === 'DELIVERED') {
            $response->assertOk();
        } else {
            $response->assertNotFound();
        }
    }

    expect(DB::table('user_pregnancy_links')->orderBy('id')->get()->map(fn ($row) => [
        'user_id' => $row->user_id,
        'patient_id' => $row->patient_id,
        'verified_at' => (string) $row->verified_at,
    ])->all())->toBe($linksBefore->map(fn ($row) => [
        'user_id' => $row->user_id,
        'patient_id' => $row->patient_id,
        'verified_at' => (string) $row->verified_at,
    ])->all());

    expect(DB::table('patients')->orderBy('id')->pluck('user_id', 'id')->all())->toBe($pointersBefore)
        ->and($current->fresh()->user_id)->toBe($user->id);
});

test('the Phase 3H.2 pair-unique schema is preserved after the 3H.5 retirement', function () {
    $userA = ret3h5User();
    $userB = ret3h5User();
    $patient = ret3h5OwnedDelivered($userA);

    // Same pair is refused (pair unique intact).
    $duplicate = new UserPregnancyLink();
    $duplicate->user_id = $userA->id;
    $duplicate->patient_id = $patient->id;
    $duplicate->verified_at = now();

    expect(fn () => $duplicate->save())
        ->toThrow(\Illuminate\Database\UniqueConstraintViolationException::class);

    // Ordinary patient_id index (not UNIQUE) - the 3H.2 shape.
    $indexes = collect(\Illuminate\Support\Facades\Schema::getIndexes('user_pregnancy_links'));
    expect($indexes->first(fn (array $index): bool => ! $index['unique']
        && ! $index['primary']
        && $index['columns'] === ['patient_id']))->not->toBeNull();
    expect($indexes->first(fn (array $index): bool => $index['unique']
        && ! $index['primary']
        && $index['columns'] === ['patient_id']))->toBeNull();

    // A different account CAN hold a row for the same episode under 3H.2
    // pair-unique schema (application guards, not the DB, refuse that).
    $second = new UserPregnancyLink();
    $second->user_id = $userB->id;
    $second->patient_id = $patient->id;
    $second->verified_at = now();
    $second->save();

    expect(UserPregnancyLink::where('patient_id', $patient->id)->count())->toBe(2);
});
