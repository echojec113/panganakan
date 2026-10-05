<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\Ultrasound;
use App\Models\User;
use App\Services\AssessmentContextBuilder;
use App\ValueObjects\AssessmentContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Carbon::setTestNow('2026-10-01 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

function gaAutofillPatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'GA',
        'last_name' => 'Patient',
        'age' => 28,
        'address' => 'Test address',
        'contact_number' => '09170000000',
        'status' => 'ONGOING',
    ], $overrides));
}

function gaAutofillExpected(Patient $patient, string $date): string
{
    return number_format(app(\App\Services\GestationalAgeCalculator::class)->calculate(
        $patient->lmp?->toDateString(),
        $date
    ), 1, '.', '');
}

function gaAutofillStaff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

it('renders the locked prenatal create page with LMP-backed GA immediately', function () {
    $patient = gaAutofillPatient(['lmp' => today()->subDays(268)->toDateString()]);
    $expected = gaAutofillExpected($patient, today()->toDateString());

    $response = $this->actingAs(gaAutofillStaff())
        ->get(route('prenatal-visits.create', ['patient_id' => $patient->id]));

    $response->assertOk()
        ->assertSee('Locked from profile')
        ->assertSee('data-lmp="' . $patient->lmp->toDateString() . '"', false)
        ->assertSee('value="' . $expected . '"', false)
        ->assertSee('Expected GA: ' . $expected . ' weeks based on LMP');
});

it('keeps old prenatal create input ahead of its calculated initial value', function () {
    $patient = gaAutofillPatient(['lmp' => today()->subDays(268)->toDateString()]);
    $expected = gaAutofillExpected($patient, today()->toDateString());

    $response = $this->actingAs(gaAutofillStaff())
        ->withSession(['_old_input' => [
            'patient_id' => $patient->id,
            'visit_date' => today()->toDateString(),
            'gestational_age' => '38.0',
        ]])
        ->get(route('prenatal-visits.create'));

    $response->assertOk()
        ->assertSee('value="38.0"', false)
        ->assertSee('data-ga-old-input="true"', false)
        ->assertSee('Expected GA: ' . $expected . ' weeks based on LMP');
});

it('renders prenatal edit with calculated GA and preserves old input after redirect', function () {
    $patient = gaAutofillPatient(['lmp' => today()->subDays(268)->toDateString()]);
    $visit = PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => today()->toDateString(),
        'gestational_age' => 31.0,
    ]);
    $expected = gaAutofillExpected($patient, today()->toDateString());

    $response = $this->actingAs(gaAutofillStaff())
        ->get(route('prenatal-visits.edit', $visit->id));

    $response->assertOk()
        ->assertSee('value="' . $expected . '"', false)
        ->assertSee('Expected GA: ' . $expected . ' weeks based on LMP');

    $redirectResponse = $this->withSession(['_old_input' => [
        'visit_date' => today()->toDateString(),
        'gestational_age' => '38.0',
    ]])->get(route('prenatal-visits.edit', $visit->id));

    $redirectResponse->assertOk()->assertSee('value="38.0"', false);
});

it('renders ultrasound create with calculated GA and edit with saved GA', function () {
    $patient = gaAutofillPatient(['lmp' => today()->subDays(255)->toDateString()]);
    $expected = gaAutofillExpected($patient, today()->toDateString());

    $create = $this->actingAs(gaAutofillStaff())
        ->get(route('ultrasound.create', $patient->id));

    $create->assertOk()
        ->assertSee('data-ga-lmp="' . $patient->lmp->toDateString() . '"', false)
        ->assertSee('value="' . $expected . '"', false)
        ->assertSee('Expected GA: ' . $expected . ' weeks based on LMP');

    $ultrasound = Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => today()->toDateString(),
        'gestational_age_scan' => 20.0,
    ]);
    $edit = $this->actingAs(gaAutofillStaff())
        ->get(route('ultrasound.edit', $ultrasound->id));

    $edit->assertOk()
        ->assertSee('value="20.0"', false)
        ->assertSee('value="' . today()->toDateString() . '"', false)
        ->assertSee('Expected GA: ' . $expected . ' weeks based on LMP');

    $this->withSession(['_old_input' => [
        'scan_date' => today()->toDateString(),
        'gestational_age_scan' => '36.0',
    ]])->get(route('ultrasound.create', $patient->id))
        ->assertOk()
        ->assertSee('value="36.0"', false)
        ->assertSee('data-ga-old-input="true"', false);

    $this->withSession(['_old_input' => [
        'scan_date' => today()->toDateString(),
        'gestational_age_scan' => '36.0',
    ]])->get(route('ultrasound.edit', $ultrasound->id))
        ->assertOk()
        ->assertSee('value="36.0"', false)
        ->assertSee('data-ga-old-input="true"', false);
});

it('uses stored edit GA and explains missing LMP when calculation is unavailable', function () {
    $patient = gaAutofillPatient();
    $visit = PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => today()->toDateString(),
        'gestational_age' => 20.0,
    ]);
    $ultrasound = Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => today()->toDateString(),
        'gestational_age_scan' => 21.0,
    ]);

    $this->actingAs(gaAutofillStaff())
        ->get(route('prenatal-visits.edit', $visit->id))
        ->assertOk()
        ->assertSee('value="20.0"', false)
        ->assertSee('No LMP available for automatic GA calculation.');

    $this->get(route('ultrasound.edit', $ultrasound->id))
        ->assertOk()
        ->assertSee('value="21.0"', false)
        ->assertSee('No LMP available for automatic GA calculation.');

    $this->get(route('ultrasound.create', $patient->id))
        ->assertOk()
        ->assertSee('No LMP available for automatic GA calculation.')
        ->assertSee('value=""', false);
});

it('does not auto-fill for missing LMP, reversed dates, or calculated GA outside 4–42 weeks', function () {
    $staff = gaAutofillStaff();
    $noLmpPatient = gaAutofillPatient();
    $recentLmpPatient = gaAutofillPatient(['lmp' => today()->subDays(20)->toDateString()]);
    $lateLmpPatient = gaAutofillPatient(['lmp' => today()->subDays(300)->toDateString()]);
    $futureLmpPatient = gaAutofillPatient(['lmp' => today()->addDays(1)->toDateString()]);

    $this->actingAs($staff)
        ->get(route('prenatal-visits.create', ['patient_id' => $noLmpPatient->id]))
        ->assertOk()
        ->assertSee('No LMP available for automatic GA calculation.')
        ->assertSee('name="gestational_age" id="gestational_age"', false)
        ->assertSee('value=""', false);

    $this->withSession(['_old_input' => ['visit_date' => today()->toDateString()]])
        ->get(route('prenatal-visits.create', ['patient_id' => $futureLmpPatient->id]))
        ->assertOk()
        ->assertSee('Visit date cannot be before the patient’s LMP.')
        ->assertSee('name="gestational_age" id="gestational_age"', false)
        ->assertSee('value=""', false);

    $this->get(route('prenatal-visits.create', ['patient_id' => $recentLmpPatient->id]))
        ->assertOk()
        ->assertSee('Calculated GA: 2.9 weeks is outside the allowed 4–42 week range.')
        ->assertSee('value=""', false);

    $this->get(route('ultrasound.create', $lateLmpPatient->id))
        ->assertOk()
        ->assertSee('Calculated GA: 42.9 weeks is outside the allowed 4–42 week range.')
        ->assertSee('value=""', false);

    $this->withSession(['_old_input' => ['scan_date' => today()->toDateString()]])
        ->get(route('ultrasound.create', $futureLmpPatient->id))
        ->assertOk()
        ->assertSee('Scan date cannot be before the patient’s LMP.')
        ->assertSee('value=""', false);
});

it('preserves decimal gestational age in assessment context and model storage', function () {
    $patient = gaAutofillPatient();
    $visit = PrenatalVisit::create([
        'patient_id' => $patient->id,
        'visit_date' => today()->toDateString(),
        'gestational_age' => 38.2,
    ]);

    expect((float) $visit->fresh()->gestational_age)->toBe(38.2);
    expect(Schema::getColumnType('prenatal_visits', 'gestational_age'))->toBeIn(['decimal', 'numeric']);
    expect((new AssessmentContextBuilder)->buildForPatient(
        $patient,
        $visit->fresh(),
        ['gestational_age' => '38.2'],
        today()->toDateString()
    )->gestational_age)->toBe(38.2);

    $context = new AssessmentContext(patient_id: (int) $patient->id, gestational_age: 38.2);
    expect(AssessmentContext::fromArray($context->toArray())->gestational_age)->toBe(38.2);

    $ultrasound = Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => today()->toDateString(),
        'gestational_age_scan' => 38.2,
    ]);

    expect((float) $ultrasound->fresh()->gestational_age_scan)->toBe(38.2);
    expect(Schema::getColumnType('ultrasounds', 'gestational_age_scan'))->toBeIn(['decimal', 'numeric']);
});

it('exposes dropdown patient LMP and the shared date/patient recalculation hooks', function () {
    $patient = gaAutofillPatient(['lmp' => today()->subDays(268)->toDateString()]);

    $response = $this->actingAs(gaAutofillStaff())->get(route('prenatal-visits.create'));

    $response->assertOk()
        ->assertSee('data-lmp="' . $patient->lmp->toDateString() . '"', false)
        ->assertSee("form.querySelector('select[name=\"patient_id\"]')?.addEventListener('change'", false)
        ->assertSee("referenceDate?.addEventListener('change'", false)
        ->assertSee("refresh(true)", false);
});

it('populates every ultrasound edit field and shows attempted values and field errors', function () {
    $patient = gaAutofillPatient(['lmp' => today()->subWeeks(30)->toDateString()]);
    $values = [
        'scan_date' => today()->toDateString(),
        'gestational_age_scan' => '29.5',
        'estimated_fetal_weight' => 2400,
        'fetal_heartbeat' => 'Normal 120-160',
        'fetal_movement' => 'Active',
        'presentation' => 'Breech',
        'amniotic_fluid' => 'Normal',
        'placenta_position' => 'Posterior',
        'remarks' => 'Saved observations',
    ];
    $ultrasound = Ultrasound::create(['patient_id' => $patient->id] + $values);
    $response = $this->actingAs(gaAutofillStaff())->get(route('ultrasound.edit', $ultrasound->id));
    $response->assertOk();
    $document = new DOMDocument();
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    foreach ($values as $field => $value) {
        $element = $xpath->query('//*[@name="' . $field . '"]')->item(0);
        expect($element)->not->toBeNull();
        expect($element->hasAttribute('required'))->toBeTrue();
        if ($element->nodeName === 'select') {
            $selected = $xpath->query('.//option[@selected]', $element)->item(0);
            expect($selected?->getAttribute('value'))->toBe((string) $value);
        } elseif ($element->nodeName === 'textarea') {
            expect(trim($element->textContent))->toBe($value);
        } else {
            expect($element->getAttribute('value'))->toBe((string) $value);
        }
    }
    expect($xpath->query('//*[@name="report_file"]')->item(0)->hasAttribute('required'))->toBeFalse();

    $attempted = array_fill_keys(array_keys($values), '');
    $errors = new \Illuminate\Support\MessageBag();
    foreach ($attempted as $field => $value) {
        $errors->add($field, 'Please complete ' . $field);
    }
    $this->withSession([
        '_old_input' => $attempted,
        'errors' => (new \Illuminate\Support\ViewErrorBag())->put('default', $errors),
    ])->get(route('ultrasound.edit', $ultrasound->id))
        ->assertOk()
        ->assertSee('Please complete scan_date')
        ->assertSee('Please complete gestational_age_scan')
        ->assertSee('Please complete remarks')
        ->assertDontSee('Saved observations');
    expect($ultrasound->fresh()->remarks)->toBe('Saved observations');
});

it('returns friendly required errors for blank ultrasound edit fields', function () {
    $patient = gaAutofillPatient();
    $ultrasound = Ultrasound::create(['patient_id' => $patient->id, 'scan_date' => today()->toDateString()]);
    $this->actingAs(gaAutofillStaff())
        ->put(route('ultrasound.update', $ultrasound->id), [])
        ->assertSessionHasErrors([
            'scan_date' => 'Scan date is required.',
            'fetal_heartbeat' => 'Fetal heartbeat is required.',
            'fetal_movement' => 'Fetal movement is required.',
            'presentation' => 'Presentation is required.',
            'amniotic_fluid' => 'Amniotic fluid is required.',
            'placenta_position' => 'Placenta position is required.',
            'gestational_age_scan' => 'Gestational age is required.',
            'estimated_fetal_weight' => 'Estimated fetal weight is required.',
            'remarks' => 'Remarks are required.',
        ])
        ->assertSessionDoesntHaveErrors('report_file');
});
