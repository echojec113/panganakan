<?php

use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Ultrasound;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| QA correction - Patient Profile text-action colors
|--------------------------------------------------------------------------
| The header text actions on the Patient Profile (Ultrasound Edit,
| Medical History Edit, Referral Manage) plus the prenatal-visit
| Edit/Print pair must share the neutral-gray text-link styling used by
| the prenatal Print action: `text-sm font-medium text-gray-600
| hover:text-gray-900` (Edit icon: gray-600/gray-900 with the gray
| hover background). Destinations, permissions, labels and functionality
| are untouched - only the color classes change.
*/

function actionColorPatient(): Patient
{
    return Patient::create([
        'first_name' => 'Action',
        'last_name' => 'Colors',
        'age' => 28,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function actionColorStaff(): User
{
    return User::factory()->create(['role' => 'staff', 'name' => 'Action Color Staff']);
}

/** Capture the class attribute of the anchor whose href is exactly $href. */
function actionColorAnchorClass(string $content, string $href): ?string
{
    $pattern = '/<a\b[^>]*href="' . preg_quote($href, '/') . '"[^>]*class="([^"]*)"/';

    if (! preg_match($pattern, $content, $matches)) {
        return null;
    }

    return $matches[1];
}

/** Capture the class of the Referral Manage link (href also appears in the nav and other panels). */
function actionColorManageClass(string $content): ?string
{
    $pattern = '/<a\b[^>]*href="' . preg_quote(route('referrals.index'), '/') . '"[^>]*class="([^"]*)"[^>]*>\s*Manage\s*<\/a>/';

    if (! preg_match($pattern, $content, $matches)) {
        return null;
    }

    return $matches[1];
}

it('renders the Ultrasound Edit, Medical History Edit and Referral Manage links in neutral gray', function () {
    $staff = actionColorStaff();
    $patient = actionColorPatient();
    $ultrasound = Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => now()->subDays(2)->toDateString(),
        'presentation' => 'Breech',
        'amniotic_fluid' => 'Normal',
        'fetal_heartbeat' => 'Normal',
    ]);
    MedicalHistory::create(['patient_id' => $patient->id, 'diabetes' => true, 'anemia' => false]);

    $content = $this->actingAs($staff)
        ->get(route('patients.show', $patient->id))
        ->assertOk()
        ->assertSee(route('ultrasound.edit', $ultrasound->id), false)
        ->assertSee(route('medical-histories.edit', $patient->medicalHistory->id), false)
        ->assertSee(route('referrals.index'), false)
        ->getContent();

    $grayTextLink = 'text-sm font-medium text-gray-600 hover:text-gray-900';

    // Each action keeps its original destination...
    // ...and now carries the neutral-gray text styling, never the old blue.
    $perRecordActions = [
        'ultrasound.edit' => actionColorAnchorClass($content, route('ultrasound.edit', $ultrasound->id)),
        'medical-histories.edit' => actionColorAnchorClass($content, route('medical-histories.edit', $patient->medicalHistory->id)),
        'referrals.index (Manage)' => actionColorManageClass($content),
    ];

    foreach ($perRecordActions as $label => $class) {
        expect($class, "anchor for {$label}")->not->toBeNull()
            ->and($class)->toBe($grayTextLink)
            ->and($class)->not->toContain('blue');
    }
});

it('keeps the prenatal-visit Edit and Print actions in matching neutral gray', function () {
    $staff = actionColorStaff();
    $patient = actionColorPatient();
    $visit = PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => now()->toDateString(),
        'risk_level' => 'LOW',
    ]);

    $content = $this->actingAs($staff)
        ->get(route('patients.show', $patient->id))
        ->assertOk()
        ->getContent();

    $editClass = actionColorAnchorClass($content, route('prenatal-visits.edit', $visit->id));
    $printClass = actionColorAnchorClass($content, route('prenatal-visits.print', $visit->id));

    expect($editClass)->toContain('text-gray-600')
        ->and($editClass)->toContain('hover:text-gray-900')
        ->and($editClass)->not->toContain('emerald')
        ->and($printClass)->toBe('text-sm font-medium text-gray-600 hover:text-gray-900');
});
