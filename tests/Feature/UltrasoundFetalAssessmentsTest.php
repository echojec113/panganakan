<?php

use App\Models\Patient;
use App\Models\Ultrasound;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;

function fwaFeaturePatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'FWA',
        'last_name' => 'Feature',
        'age' => 28,
        'address' => 'Test address',
        'contact_number' => '09170000000',
        'status' => 'ONGOING',
    ], $overrides));
}

function fwaFeatureStaff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

function fwaFeaturePayload(Patient $patient, array $overrides = []): array
{
    return array_merge([
        'patient_id' => $patient->id,
        'scan_date' => today()->toDateString(),
        'gestational_age_scan' => '32.7',
        'estimated_fetal_weight' => '1800',
        'fetal_heartbeat' => 'Normal 120-160',
        'fetal_movement' => 'Active',
        'presentation' => 'Cephalic',
        'amniotic_fluid' => 'Normal',
        'placenta_position' => 'Posterior',
        'report_file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
        'remarks' => 'FWA regression record',
    ], $overrides);
}

it('renders both assessment panels with the live config on ultrasound create', function () {
    $patient = fwaFeaturePatient();

    $response = $this->actingAs(fwaFeatureStaff())
        ->get(route('ultrasound.create', $patient->id));

    $response->assertOk()
        ->assertSee('data-assessment="fwa"', false)
        ->assertSee('data-assessment="fhr"', false)
        ->assertSee('Fetal Weight Assessment', false)
        ->assertSee('Fetal Heart Rate Assessment', false)
        ->assertSee('data-fetal-assessment-live', false)
        ->assertSee('Recorded Fetal Weight', false)
        ->assertSee('WHO Expected Range (P10–P90)', false)
        ->assertSee('WHO Median (P50)', false)
        ->assertSee('Recorded Fetal Heartbeat', false)
        ->assertSee('Unable to Assess Reliably', false)
        ->assertSee('pending review by the clinic', false)
        ->assertSee('https://pmc.ncbi.nlm.nih.gov/articles/PMC5261648/', false)
        ->assertSee('https://www.isuog.org/clinical-resources/isuog-guidelines/practice-guidelines-english.html', false)
        ->assertSee('https://www.nice.org.uk/guidance/ng229/chapter/recommendations', false)
        ->assertSee('staff-selected category, not a measured heart rate', false)
        ->assertSee('does not diagnose fetal demise', false);

    $html = $response->getContent();
    expect($html)->toContain('data-fetal-assessment-live');
    expect(substr_count($html, 'data-assessment="fwa" data-status'))->toBe(1);
    expect(substr_count($html, 'data-assessment="fhr" data-status'))->toBe(1);
});

it('embeds config-only recommendation text for live client updates', function () {
    $patient = fwaFeaturePatient();

    $response = $this->actingAs(fwaFeatureStaff())
        ->get(route('ultrasound.create', $patient->id));

    // Server renders the unable state on an empty form, so these strings can
    // only come from the embedded client config used for live updates.
    // The en dash in the second string is JSON unicode-escaped by @json.
    $response->assertSee('Recorded weight is within the WHO expected reference range', false)
        ->assertSee('160\u2013180 bpm can be normal with fetal movement', false);
});

it('classifies attempted input on create after validation errors', function () {
    $patient = fwaFeaturePatient();

    $this->actingAs(fwaFeatureStaff())
        ->withSession(['_old_input' => [
            'estimated_fetal_weight' => '1755',
            'gestational_age_scan' => '32.7',
            'fetal_heartbeat' => 'Normal 120-160',
        ]])
        ->get(route('ultrasound.create', $patient->id))
        ->assertOk()
        ->assertSee('Below Expected', false)
        ->assertSee('1,755 g – 2,349 g', false)
        ->assertSee('32.7 weeks', false)
        ->assertSee('Precision note', false)
        ->assertSee('1,755.4', false)
        ->assertSee('Within Applicable Reference', false);
});

it('shows saved-value assessments on edit and read-only panels in history', function () {
    $patient = fwaFeaturePatient();
    $ultrasound = Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => today()->toDateString(),
        'gestational_age_scan' => 22.5,
        'estimated_fetal_weight' => 500,
        'fetal_heartbeat' => 'Normal 120-160',
        'fetal_movement' => 'Active',
        'presentation' => 'Cephalic',
        'amniotic_fluid' => 'Normal',
        'placenta_position' => 'Posterior',
        'remarks' => 'Saved observations',
    ]);

    $this->actingAs(fwaFeatureStaff())
        ->get(route('ultrasound.edit', $ultrasound->id))
        ->assertOk()
        ->assertSee('data-assessment="fwa"', false)
        ->assertSee('Within Expected Range', false)
        ->assertSee('451 g – 599 g', false)
        ->assertSee('500 g', false)
        ->assertSee('22.5 weeks', false)
        ->assertSee('Within Applicable Reference', false);

    $this->actingAs(fwaFeatureStaff())
        ->get(route('patients.show', $patient->id))
        ->assertOk()
        ->assertSee('Fetal Assessments (Display Only)', false)
        ->assertSee('data-assessment="fwa"', false)
        ->assertSee('data-assessment="fhr"', false)
        ->assertSee('451 g – 599 g', false)
        ->assertSee('Within Applicable Reference', false);
});

it('shows Unable to Assess Reliably for third-trimester FHR while retaining Absent safety guidance', function () {
    $patient = fwaFeaturePatient();
    $ultrasound = Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => today()->toDateString(),
        'gestational_age_scan' => 30,
        'estimated_fetal_weight' => 1500,
        'fetal_heartbeat' => 'Absent',
        'fetal_movement' => 'Absent',
        'presentation' => 'Cephalic',
        'amniotic_fluid' => 'Normal',
        'placenta_position' => 'Posterior',
        'remarks' => 'Third trimester record',
    ]);

    $this->actingAs(fwaFeatureStaff())
        ->get(route('ultrasound.edit', $ultrasound->id))
        ->assertOk()
        ->assertSee('Unable to Assess Reliably', false)
        ->assertSee('30 weeks falls outside that window', false)
        ->assertSee('regardless of whether numerical reference interpretation is available', false)
        ->assertSee('does not diagnose fetal demise', false)
        ->assertSee('prompt clinical assessment', false);
});

it('stores previously blocked weights unchanged on create', function () {
    $patient = fwaFeaturePatient();

    $response = $this->actingAs(fwaFeatureStaff())
        ->post(route('ultrasound.store'), fwaFeaturePayload($patient));

    $response->assertSessionHasNoErrors();
    $saved = Ultrasound::latest('id')->first();
    expect($saved)->not->toBeNull();
    expect((float) $saved->estimated_fetal_weight)->toBe(1800.0);
    expect((float) $saved->gestational_age_scan)->toBe(32.7);
    expect($saved->fetal_heartbeat)->toBe('Normal 120-160');

    $low = $this->actingAs(fwaFeatureStaff())
        ->post(route('ultrasound.store'), fwaFeaturePayload($patient, [
            'gestational_age_scan' => '22.5',
            'estimated_fetal_weight' => '500',
        ]));
    $low->assertSessionHasNoErrors();
    expect((float) Ultrasound::latest('id')->first()->estimated_fetal_weight)->toBe(500.0);

    $high = $this->actingAs(fwaFeatureStaff())
        ->post(route('ultrasound.store'), fwaFeaturePayload($patient, [
            'gestational_age_scan' => '24',
            'estimated_fetal_weight' => '3500',
        ]));
    $high->assertSessionHasNoErrors();
    expect((float) Ultrasound::latest('id')->first()->estimated_fetal_weight)->toBe(3500.0);
});

it('updates previously blocked weights unchanged on update', function () {
    $patient = fwaFeaturePatient();
    $ultrasound = Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => today()->toDateString(),
        'gestational_age_scan' => 20,
        'estimated_fetal_weight' => 1500,
        'fetal_heartbeat' => 'Normal 120-160',
        'fetal_movement' => 'Active',
        'presentation' => 'Cephalic',
        'amniotic_fluid' => 'Normal',
        'placenta_position' => 'Posterior',
        'remarks' => 'Before update',
    ]);

    $this->actingAs(fwaFeatureStaff())
        ->put(route('ultrasound.update', $ultrasound->id), [
            'patient_id' => $patient->id,
            'scan_date' => today()->toDateString(),
            'gestational_age_scan' => '22.5',
            'estimated_fetal_weight' => '500',
            'fetal_heartbeat' => 'Weak',
            'fetal_movement' => 'Active',
            'presentation' => 'Cephalic',
            'amniotic_fluid' => 'Normal',
            'placenta_position' => 'Posterior',
            'remarks' => 'After update',
        ])
        ->assertSessionHasNoErrors();

    $fresh = $ultrasound->fresh();
    expect((float) $fresh->estimated_fetal_weight)->toBe(500.0);
    expect((float) $fresh->gestational_age_scan)->toBe(22.5);
    expect($fresh->fetal_heartbeat)->toBe('Weak');
    expect($fresh->remarks)->toBe('After update');
});

it('keeps basic EFW validation and the exact stored value untouched', function () {
    $patient = fwaFeaturePatient();

    $this->actingAs(fwaFeatureStaff())
        ->post(route('ultrasound.store'), fwaFeaturePayload($patient, [
            'estimated_fetal_weight' => '150',
        ]))
        ->assertSessionHasErrors(['estimated_fetal_weight']);

    $this->actingAs(fwaFeatureStaff())
        ->post(route('ultrasound.store'), fwaFeaturePayload($patient, [
            'estimated_fetal_weight' => '6000',
        ]))
        ->assertSessionHasErrors(['estimated_fetal_weight']);

    expect(Ultrasound::count())->toBe(0);
});

it('removed the weight-versus-GA blocker from the controller and added no schema columns', function () {
    $controller = file_get_contents(app_path('Http/Controllers/UltrasoundController.php'));

    expect($controller)->not->toContain('Weight seems low for');
    expect($controller)->not->toContain('Weight seems high for');
    expect($controller)->not->toContain('expectedWeightMin');
    expect($controller)->not->toContain('expectedWeightMax');
    expect($controller)->toContain("'estimated_fetal_weight' => 'required|numeric|min:200|max:5000'");

    $columns = Schema::getColumnListing('ultrasounds');
    sort($columns);

    expect($columns)->toBe([
        'amniotic_fluid',
        'created_at',
        'deleted_at',
        'estimated_fetal_weight',
        'fetal_heartbeat',
        'fetal_movement',
        'gestational_age_scan',
        'id',
        'patient_id',
        'placenta_position',
        'presentation',
        'remarks',
        'report_file',
        'report_image',
        'scan_date',
        'updated_at',
    ]);
});

it('renders both assessment cards collapsed by default with top-right badges and Absent safety visible', function () {
    $patient = fwaFeaturePatient();

    $create = $this->actingAs(fwaFeatureStaff())
        ->get(route('ultrasound.create', $patient->id));

    $create->assertOk()
        ->assertSee('View Details', false)
        ->assertSee('Hide Details', false)
        ->assertSee('data-slot="summary"', false)
        ->assertSee('Enter fetal weight and gestational age to view the assessment.', false)
        ->assertSee('Reference context: ISUOG guidelines', false);

    $createHtml = $create->getContent();

    preg_match_all('/<details[^>]*data-assessment="(?:fwa|fhr)"[^>]*>/', $createHtml, $createTags);
    expect($createTags[0])->toHaveCount(2);
    foreach ($createTags[0] as $tag) {
        expect($tag)->not->toContain(' open');
    }

    // The collapsed header carries title, then the status badge, then the summary.
    $fwaTitleAt = strpos($createHtml, 'Fetal Weight Assessment');
    $fwaChipAt = strpos($createHtml, 'data-slot="chip"', $fwaTitleAt);
    $fwaSummaryAt = strpos($createHtml, 'data-slot="summary"', $fwaChipAt);
    expect($fwaTitleAt)->toBeLessThan($fwaChipAt);
    expect($fwaChipAt)->toBeLessThan($fwaSummaryAt);

    // The live script keeps the collapsed summary in sync on every update path.
    expect(substr_count($createHtml, "setSlotText(root, 'summary'"))->toBe(4);

    $absent = Ultrasound::create([
        'patient_id' => $patient->id,
        'scan_date' => today()->toDateString(),
        'gestational_age_scan' => 22.5,
        'estimated_fetal_weight' => 500,
        'fetal_heartbeat' => 'Absent',
        'fetal_movement' => 'Active',
        'presentation' => 'Cephalic',
        'amniotic_fluid' => 'Normal',
        'placenta_position' => 'Posterior',
        'remarks' => 'Absent heartbeat record',
    ]);

    $edit = $this->actingAs(fwaFeatureStaff())
        ->get(route('ultrasound.edit', $absent->id));

    $edit->assertOk()
        ->assertSee('500 g at 22.5 weeks — Within Expected Range', false)
        ->assertSee('Absent — Prompt clinical assessment required.', false)
        ->assertSee('Needs Clinical Review', false)
        ->assertSee('does not diagnose fetal demise', false);

    $editHtml = $edit->getContent();

    preg_match_all('/<details[^>]*data-assessment="(?:fwa|fhr)"[^>]*>/', $editHtml, $editTags);
    expect($editTags[0])->toHaveCount(2);
    foreach ($editTags[0] as $tag) {
        expect($tag)->not->toContain(' open');
    }

    // The Absent safety warning lives inside the FHR <summary> (visible while
    // collapsed), with the status badge before the summary text.
    $fhrTitleAt = strpos($editHtml, 'Fetal Heart Rate Assessment');
    $fhrSummaryEnd = strpos($editHtml, '</summary>', $fhrTitleAt);
    $fhrHeader = substr($editHtml, $fhrTitleAt, $fhrSummaryEnd - $fhrTitleAt);
    expect($fhrHeader)->toContain('Prompt clinical assessment required.');
    $fhrChipAt = strpos($fhrHeader, 'data-slot="chip"');
    $fhrSummaryTextAt = strpos($fhrHeader, 'data-slot="summary"');
    expect($fhrChipAt)->not->toBe(false);
    expect($fhrSummaryTextAt)->not->toBe(false);
    expect($fhrChipAt)->toBeLessThan($fhrSummaryTextAt);
});
