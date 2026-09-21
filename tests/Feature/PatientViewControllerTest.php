<?php

use App\Models\Baby;
use App\Models\Patient;
use App\Models\PregnancyOutcome;
use App\Models\PrenatalVisit;
use App\Models\Referral;
use App\Models\User;

function recordsAdmin(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function recordsPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Maria',
        'middle_name' => 'Santos',
        'last_name' => 'Reyes',
        'birthdate' => '1990-01-10',
        'age' => 36,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 2,
        'para' => 1,
        'status' => 'ONGOING',
    ], $overrides));
}

function recordsVisit(Patient $patient, array $overrides = []): PrenatalVisit
{
    return PrenatalVisit::create(array_merge([
        'patient_id' => $patient->id,
        'visit_date' => '2026-09-01',
        'bp_sys' => 120,
        'bp_dia' => 80,
        'weight' => 60,
        'gestational_age' => 24,
        'risk_level' => 'LOW',
        'assessment' => 'Routine prenatal assessment',
        'recommendation' => 'Continue routine care',
    ], $overrides));
}

function recordsReferral(Patient $patient, string $status = 'Pending'): Referral
{
    return Referral::create([
        'patient_id' => $patient->id,
        'created_by' => User::factory()->create(['role' => 'staff'])->id,
        'referred_to' => 'Test Hospital',
        'reason' => 'Referral test record',
        'referral_date' => '2026-09-02',
        'status' => $status,
    ]);
}

it('allows an admin to access View All Records with the default Ongoing filter', function () {
    $ongoing = recordsPatient(['first_name' => 'Ongoing']);
    recordsPatient(['first_name' => 'Delivered', 'status' => 'DELIVERED']);

    $response = $this->actingAs(recordsAdmin())->get(route('view-all-records.index'));

    $response->assertOk();
    $response->assertSeeText('View All Records');
    $response->assertSee('option value="ONGOING" selected', false);
    $response->assertSeeText('Ongoing Santos Reyes');
    $response->assertDontSeeText('Delivered Santos Reyes');
    $response->assertSee(route('view-all-records.history', $ongoing), false);
});

it('denies View All Records access to staff and patient users', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $patientUser = User::factory()->create(['role' => 'patient']);

    $this->actingAs($staff)->get(route('view-all-records.index'))->assertForbidden();
    $this->actingAs($patientUser)->get(route('view-all-records.index'))->assertForbidden();
});

it('filters persons by Ongoing and Delivered pregnancy statuses', function () {
    recordsPatient(['first_name' => 'OngoingOnly']);
    recordsPatient(['first_name' => 'DeliveredOnly', 'status' => 'DELIVERED']);

    $admin = recordsAdmin();
    $ongoing = $this->actingAs($admin)->get(route('view-all-records.index', ['status' => 'ONGOING']));
    $ongoing->assertSeeText('OngoingOnly Santos Reyes');
    $ongoing->assertDontSeeText('DeliveredOnly Santos Reyes');

    $delivered = $this->actingAs($admin)->get(route('view-all-records.index', ['status' => 'DELIVERED']));
    $delivered->assertSeeText('DeliveredOnly Santos Reyes');
    $delivered->assertDontSeeText('OngoingOnly Santos Reyes');
});

it('includes pending referrals and legacy REFERRED records in the Referred filter', function () {
    $pendingReferralPatient = recordsPatient(['first_name' => 'PendingReferral']);
    recordsReferral($pendingReferralPatient);
    $legacyReferredPatient = recordsPatient(['first_name' => 'LegacyReferred', 'status' => 'REFERRED']);
    $closedReferralPatient = recordsPatient(['first_name' => 'ClosedReferral']);
    recordsReferral($closedReferralPatient, 'Completed');

    $response = $this->actingAs(recordsAdmin())->get(route('view-all-records.index', ['status' => 'REFERRED']));

    $response->assertOk();
    $response->assertSeeText('PendingReferral Santos Reyes');
    $response->assertSeeText('LegacyReferred Santos Reyes');
    $response->assertDontSeeText('ClosedReferral Santos Reyes');
    $response->assertSee(route('view-all-records.history', $legacyReferredPatient), false);
});

it('shows one main-list row per identity and keeps duplicate names with different birthdates separate', function () {
    $firstPregnancy = recordsPatient(['first_name' => 'Ana', 'middle_name' => null, 'last_name' => 'Cruz', 'birthdate' => '1991-03-05']);
    recordsPatient(['first_name' => 'Ana', 'middle_name' => null, 'last_name' => 'Cruz', 'birthdate' => '1991-03-05']);
    $sameNameDifferentBirthdate = recordsPatient(['first_name' => 'Ana', 'middle_name' => null, 'last_name' => 'Cruz', 'birthdate' => '1992-03-05']);

    $response = $this->actingAs(recordsAdmin())->get(route('view-all-records.index'));
    $content = $response->getContent();

    expect(substr_count($content, route('view-all-records.history', $firstPregnancy)))->toBe(1);
    expect(substr_count($content, route('view-all-records.history', $sameNameDifferentBirthdate)))->toBe(1);
});

it('shows all ongoing and delivered pregnancies for the selected person', function () {
    $delivered = recordsPatient(['status' => 'DELIVERED', 'delivery_date' => '2026-06-15']);
    $ongoing = recordsPatient(['status' => 'ONGOING']);
    recordsPatient(['first_name' => 'Other', 'status' => 'DELIVERED']);

    $response = $this->actingAs(recordsAdmin())->get(route('view-all-records.history', $ongoing));

    $response->assertOk();
    $response->assertSeeText('Maria Santos Reyes');
    $response->assertSeeText('Pregnancy 1');
    $response->assertSeeText('Pregnancy 2');
    $response->assertSeeText('DELIVERED');
    $response->assertSeeText('ONGOING');
    $response->assertSee(route('view-all-records.pregnancy', $delivered), false);
    $response->assertSee(route('view-all-records.pregnancy.print', $ongoing), false);
    $response->assertDontSeeText('Other Santos Reyes');
});

it('displays only the selected pregnancy relationships and every baby', function () {
    $selected = recordsPatient(['status' => 'DELIVERED', 'delivery_date' => '2026-06-15']);
    $otherPregnancy = recordsPatient(['status' => 'DELIVERED', 'delivery_date' => '2025-06-15']);

    $selectedVisit = recordsVisit($selected, ['assessment' => 'Selected pregnancy visit']);
    recordsVisit($otherPregnancy, ['assessment' => 'Other pregnancy visit']);

    PregnancyOutcome::create([
        'patient_id' => $selected->id,
        'outcome_type' => 'DELIVERED',
        'delivery_location' => 'Selected Birth Center',
        'confirmation_source' => 'FACILITY_RECORD',
        'confirmed_at' => now(),
        'notes' => 'Selected outcome note',
    ]);
    PregnancyOutcome::create([
        'patient_id' => $otherPregnancy->id,
        'outcome_type' => 'DELIVERED',
        'delivery_location' => 'Other Birth Center',
        'confirmation_source' => 'FACILITY_RECORD',
        'confirmed_at' => now(),
        'notes' => 'Other outcome note',
    ]);

    Baby::create(['patient_id' => $selected->id, 'first_name' => 'First', 'last_name' => 'Selected', 'sex' => 'Female', 'date_of_birth' => '2026-06-15', 'time_of_birth' => '08:00']);
    Baby::create(['patient_id' => $selected->id, 'first_name' => 'Second', 'last_name' => 'Selected', 'sex' => 'Male', 'date_of_birth' => '2026-06-15', 'time_of_birth' => '08:05']);
    Baby::create(['patient_id' => $otherPregnancy->id, 'first_name' => 'Other', 'last_name' => 'Baby', 'sex' => 'Female', 'date_of_birth' => '2025-06-15', 'time_of_birth' => '08:00']);

    $response = $this->actingAs(recordsAdmin())->get(route('view-all-records.pregnancy', $selected));

    $response->assertOk();
    $response->assertSeeText('Selected pregnancy visit');
    $response->assertSeeText('Selected Birth Center');
    $response->assertSeeText('First Selected');
    $response->assertSeeText('Second Selected');
    $response->assertSee(route('prenatal-visits.print', $selectedVisit), false);
    $response->assertDontSeeText('Other pregnancy visit');
    $response->assertDontSeeText('Other Birth Center');
    $response->assertDontSeeText('Other Baby');
});

it('prints only the selected pregnancy and preserves individual visit print isolation', function () {
    $selected = recordsPatient(['status' => 'DELIVERED']);
    $otherPregnancy = recordsPatient(['status' => 'DELIVERED']);
    $selectedVisit = recordsVisit($selected, ['assessment' => 'Selected print visit']);
    recordsVisit($otherPregnancy, ['assessment' => 'Other print visit']);
    Baby::create(['patient_id' => $selected->id, 'first_name' => 'Print', 'last_name' => 'Selected', 'sex' => 'Female', 'date_of_birth' => '2026-06-15', 'time_of_birth' => '08:00']);
    Baby::create(['patient_id' => $otherPregnancy->id, 'first_name' => 'Print', 'last_name' => 'Other', 'sex' => 'Male', 'date_of_birth' => '2025-06-15', 'time_of_birth' => '08:00']);

    $pregnancyPrint = $this->actingAs(recordsAdmin())->get(route('view-all-records.pregnancy.print', $selected));
    $pregnancyPrint->assertOk();
    $pregnancyPrint->assertSeeText('Selected print visit');
    $pregnancyPrint->assertSeeText('Print Selected');
    $pregnancyPrint->assertDontSeeText('Other print visit');
    $pregnancyPrint->assertDontSeeText('Print Other');

    $visitPrint = $this->actingAs(recordsAdmin())->get(route('prenatal-visits.print', $selectedVisit));
    $visitPrint->assertOk();
    $visitPrint->assertSeeText('Selected print visit');
    $visitPrint->assertDontSeeText('Other print visit');
});

it('returns 404 for an invalid pregnancy record ID', function () {
    $this->actingAs(recordsAdmin())
        ->get(route('view-all-records.pregnancy', ['patient' => 999999]))
        ->assertNotFound();
});

it('filters main list patients by search across first, middle, and last name', function () {
    $maria = recordsPatient(['first_name' => 'Maria', 'middle_name' => 'Santos', 'last_name' => 'Reyes']);
    $juanita = recordsPatient(['first_name' => 'Juanita', 'middle_name' => 'Dela', 'last_name' => 'Cruz']);
    $ana = recordsPatient(['first_name' => 'Ana', 'middle_name' => 'Reyes', 'last_name' => 'Santos']);

    $response = $this->actingAs(recordsAdmin())->get(route('view-all-records.index', ['search' => 'Reyes']));

    $response->assertOk();
    $response->assertSeeText('Maria Santos Reyes');
    $response->assertSeeText('Ana Reyes Santos');
    $response->assertDontSeeText('Juanita Dela Cruz');
});

it('combines search with the Ongoing status filter', function () {
    recordsPatient(['first_name' => 'Maria', 'status' => 'ONGOING']);
    recordsPatient(['first_name' => 'Marianne', 'status' => 'DELIVERED']);

    $response = $this->actingAs(recordsAdmin())->get(route('view-all-records.index', [
        'status' => 'ONGOING',
        'search' => 'Maria',
    ]));

    $response->assertOk();
    $response->assertSeeText('Maria Santos Reyes');
    $response->assertDontSeeText('Marianne Santos Reyes');
});

it('never lets search bypass the selected status filter', function () {
    recordsPatient(['first_name' => 'Maria', 'status' => 'DELIVERED']);
    recordsPatient(['first_name' => 'Mariana', 'status' => 'ONGOING']);

    $response = $this->actingAs(recordsAdmin())->get(route('view-all-records.index', [
        'status' => 'ONGOING',
        'search' => 'Maria',
    ]));

    $response->assertOk();
    $response->assertSeeText('Mariana Santos Reyes');
    $response->assertDontSeeText('Maria Santos Reyes');
});

it('keeps one row per person when a search matches multiple pregnancies of the same identity', function () {
    $firstPregnancy = recordsPatient(['first_name' => 'Ana', 'middle_name' => null, 'last_name' => 'Cruz', 'birthdate' => '1991-03-05']);
    recordsPatient(['first_name' => 'Ana', 'middle_name' => null, 'last_name' => 'Cruz', 'birthdate' => '1991-03-05']);

    $response = $this->actingAs(recordsAdmin())->get(route('view-all-records.index', ['search' => 'Ana']));
    $content = $response->getContent();

    expect(substr_count($content, route('view-all-records.history', $firstPregnancy)))->toBe(1);
});

it('treats an empty search exactly like no search was provided', function () {
    recordsPatient(['first_name' => 'Maria']);
    $admin = recordsAdmin();

    $withEmptySearch = $this->actingAs($admin)->get(route('view-all-records.index', ['search' => '']));
    $withoutSearch = $this->actingAs($admin)->get(route('view-all-records.index'));

    $withEmptySearch->assertOk();
    $withEmptySearch->assertSeeText('Maria Santos Reyes');
    expect($withEmptySearch->getContent())->toBe($withoutSearch->getContent());
});

it('sorts Ongoing patients by latest prenatal visit activity, newest first', function () {
    $olderActivity = recordsPatient(['first_name' => 'OlderActivity']);
    recordsVisit($olderActivity, ['visit_date' => '2026-09-01']);

    $newerActivity = recordsPatient(['first_name' => 'NewerActivity']);
    recordsVisit($newerActivity, ['visit_date' => '2026-09-10']);

    $noVisits = recordsPatient(['first_name' => 'NoVisits']);
    $noVisits->forceFill(['created_at' => now()->subYear()])->save();

    $response = $this->actingAs(recordsAdmin())->get(route('view-all-records.index', ['status' => 'ONGOING']));
    $content = $response->getContent();

    $posNewer = strpos($content, 'NewerActivity Santos Reyes');
    $posOlder = strpos($content, 'OlderActivity Santos Reyes');
    $posNone = strpos($content, 'NoVisits Santos Reyes');

    expect($posNewer)->not->toBeFalse();
    expect($posOlder)->not->toBeFalse();
    expect($posNone)->not->toBeFalse();
    expect($posNewer)->toBeLessThan($posOlder);
    expect($posOlder)->toBeLessThan($posNone);
});

it('sorts Delivered patients by delivery_date, newest first', function () {
    recordsPatient(['first_name' => 'OldestDelivery', 'status' => 'DELIVERED', 'delivery_date' => '2026-08-30']);
    recordsPatient(['first_name' => 'NewestDelivery', 'status' => 'DELIVERED', 'delivery_date' => '2026-09-20']);
    recordsPatient(['first_name' => 'MiddleDelivery', 'status' => 'DELIVERED', 'delivery_date' => '2026-09-12']);

    $response = $this->actingAs(recordsAdmin())->get(route('view-all-records.index', ['status' => 'DELIVERED']));
    $content = $response->getContent();

    $posNewest = strpos($content, 'NewestDelivery Santos Reyes');
    $posMiddle = strpos($content, 'MiddleDelivery Santos Reyes');
    $posOldest = strpos($content, 'OldestDelivery Santos Reyes');

    expect($posNewest)->toBeLessThan($posMiddle);
    expect($posMiddle)->toBeLessThan($posOldest);
});

it('sorts Referred patients by the latest Pending referral_date, newest first, with created_at fallback for legacy records', function () {
    $olderReferral = recordsPatient(['first_name' => 'OlderReferral']);
    recordsReferral($olderReferral);
    $olderReferral->referrals()->update(['referral_date' => '2026-09-05']);

    $newerReferral = recordsPatient(['first_name' => 'NewerReferral']);
    recordsReferral($newerReferral);
    $newerReferral->referrals()->update(['referral_date' => '2026-09-18']);

    $legacyReferred = recordsPatient(['first_name' => 'LegacyReferred', 'status' => 'REFERRED']);
    $legacyReferred->forceFill(['created_at' => now()->subYear()])->save();

    $response = $this->actingAs(recordsAdmin())->get(route('view-all-records.index', ['status' => 'REFERRED']));
    $content = $response->getContent();

    $posNewer = strpos($content, 'NewerReferral Santos Reyes');
    $posOlder = strpos($content, 'OlderReferral Santos Reyes');
    $posLegacy = strpos($content, 'LegacyReferred Santos Reyes');

    expect($posNewer)->not->toBeFalse();
    expect($posOlder)->not->toBeFalse();
    expect($posLegacy)->not->toBeFalse();
    expect($posNewer)->toBeLessThan($posOlder);
    expect($posOlder)->toBeLessThan($posLegacy);
});

it('represents multiple pregnancy rows for the same person as a single sorted row', function () {
    $firstPregnancy = recordsPatient(['status' => 'DELIVERED', 'delivery_date' => '2026-01-10']);
    $secondPregnancy = recordsPatient(['status' => 'DELIVERED', 'delivery_date' => '2026-09-15']);
    recordsPatient(['first_name' => 'Unrelated', 'status' => 'DELIVERED', 'delivery_date' => '2026-05-01']);

    $response = $this->actingAs(recordsAdmin())->get(route('view-all-records.index', ['status' => 'DELIVERED']));
    $content = $response->getContent();

    expect(substr_count($content, 'Maria Santos Reyes'))->toBe(1);
    expect(strpos($content, 'Maria Santos Reyes'))->toBeLessThan(strpos($content, 'Unrelated Santos Reyes'));

    $historyLink = route('view-all-records.history', $firstPregnancy);
    $historyLinkAlt = route('view-all-records.history', $secondPregnancy);
    expect(substr_count($content, $historyLink) + substr_count($content, $historyLinkAlt))->toBe(1);
});

it('keeps patients with the same name but different birthdates as separate people', function () {
    $first = recordsPatient(['first_name' => 'Ana', 'middle_name' => null, 'last_name' => 'Cruz', 'birthdate' => '1991-03-05']);
    $second = recordsPatient(['first_name' => 'Ana', 'middle_name' => null, 'last_name' => 'Cruz', 'birthdate' => '1992-03-05']);

    $response = $this->actingAs(recordsAdmin())->get(route('view-all-records.index', ['search' => 'Ana']));
    $content = $response->getContent();

    expect(substr_count($content, 'Ana Cruz'))->toBe(2);
    expect(substr_count($content, route('view-all-records.history', $first)))->toBe(1);
    expect(substr_count($content, route('view-all-records.history', $second)))->toBe(1);
});
