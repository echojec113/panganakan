<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;
use App\Support\TrimesterClassifier;
use Illuminate\Support\Facades\Schema;

function trimesterProfilePatient(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Glance',
        'last_name' => 'Profile',
        'age' => 30,
        'address' => 'Test address',
        'contact_number' => '09170000001',
        'status' => 'ONGOING',
    ], $overrides));
}

function trimesterProfileVisit(int $patientId, array $overrides = []): PrenatalVisit
{
    return PrenatalVisit::create(array_merge([
        'patient_id' => $patientId,
        'visit_date' => now()->toDateString(),
        'gestational_age' => 30.0,
    ], $overrides));
}

function trimesterProfileDom(string $html): DOMDocument
{
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $dom;
}

/**
 * Returns the flattened text of the existing Gestational Age card, i.e. the
 * only rounded-xl cell inside "Pregnancy at a Glance" that holds the GA.
 */
function trimesterProfileGaCardText(string $html): string
{
    $dom = trimesterProfileDom($html);

    foreach ($dom->getElementsByTagName('div') as $div) {
        if (!str_contains($div->getAttribute('class'), 'rounded-xl')) {
            continue;
        }

        $text = preg_replace('/\s+/', ' ', trim($div->textContent));

        if (str_contains($text, 'Gestational Age')) {
            return $text;
        }
    }

    throw new RuntimeException('The Gestational Age card was not rendered on the Patient Profile.');
}

function trimesterProfileLabelCount(string $html): int
{
    $count = 0;

    foreach (trimesterProfileDom($html)->getElementsByTagName('div') as $div) {
        if (trim($div->textContent) === 'Trimester') {
            $count++;
        }
    }

    return $count;
}

// ------------------------------------------------------------------
// A. Trimester matches the GA actually displayed in the same card
// ------------------------------------------------------------------

it('classifies the exact gestational age rendered in the Pregnancy at a Glance card', function () {
    $user = User::factory()->create(['role' => 'staff']);

    foreach ([8 => '1st Trimester', 18 => '2nd Trimester', 30 => '3rd Trimester'] as $weeks => $expected) {
        $patient = trimesterProfilePatient();
        trimesterProfileVisit($patient->id, ['gestational_age' => (float) $weeks]);

        $html = $this->actingAs($user)
            ->get(route('patients.show', $patient->id))
            ->assertOk()
            ->getContent();

        $card = trimesterProfileGaCardText($html);

        expect($card)->toContain($weeks . ' weeks');
        expect($card)->toContain($expected);
    }
});

it('applies the approved trimester boundaries without rounding the profile GA', function () {
    $user = User::factory()->create(['role' => 'staff']);

    $cases = [
        12.9 => '1st Trimester',
        13.0 => '2nd Trimester',
        27.9 => '2nd Trimester',
        28.0 => '3rd Trimester',
    ];

    foreach ($cases as $weeks => $expected) {
        $patient = trimesterProfilePatient();
        trimesterProfileVisit($patient->id, ['gestational_age' => $weeks]);

        $html = $this->actingAs($user)
            ->get(route('patients.show', $patient->id))
            ->assertOk()
            ->getContent();

        expect(trimesterProfileGaCardText($html))->toContain($expected);
    }
});

it('keeps the trimester inside the existing Gestational Age card only', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = trimesterProfilePatient();
    trimesterProfileVisit($patient->id, ['gestational_age' => 30.0]);

    $html = $this->actingAs($user)
        ->get(route('patients.show', $patient->id))
        ->assertOk()
        ->getContent();

    // One label, inside the same card as GA: no extra card and no extra row.
    expect(trimesterProfileLabelCount($html))->toBe(1);
    expect(substr_count($html, '3rd Trimester'))->toBe(1);
    expect($html)->not->toContain('name="trimester"');
    expect($html)->not->toContain('name="gestational_age"');
});

// ------------------------------------------------------------------
// B. Missing / invalid GA never produces a guessed trimester
// ------------------------------------------------------------------

it('shows an unavailable trimester when the profile has no gestational age', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = trimesterProfilePatient();
    trimesterProfileVisit($patient->id, ['gestational_age' => null]);

    $card = trimesterProfileGaCardText(
        $this->actingAs($user)
            ->get(route('patients.show', $patient->id))
            ->assertOk()
            ->getContent()
    );

    expect($card)->toContain('Not recorded');
    expect($card)->toContain('—');
    expect(preg_match('/[123](st|nd|rd) Trimester/', $card))->toBe(0);
});

it('shows an unavailable trimester when the patient has no prenatal visit at all', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = trimesterProfilePatient();

    $card = trimesterProfileGaCardText(
        $this->actingAs($user)
            ->get(route('patients.show', $patient->id))
            ->assertOk()
            ->getContent()
    );

    expect($card)->toContain('Not recorded');
    expect($card)->toContain('—');
    expect(preg_match('/[123](st|nd|rd) Trimester/', $card))->toBe(0);
});

// ------------------------------------------------------------------
// C. Ongoing vs delivered: mirror the displayed GA, invent no new state
// ------------------------------------------------------------------

it('classifies the displayed GA for a delivered pregnancy exactly as it does for an ongoing one', function () {
    $user = User::factory()->create(['role' => 'staff']);

    foreach (['ONGOING', 'DELIVERED'] as $status) {
        $patient = trimesterProfilePatient(['status' => $status]);
        trimesterProfileVisit($patient->id, ['gestational_age' => 30.0]);

        $card = trimesterProfileGaCardText(
            $this->actingAs($user)
                ->get(route('patients.show', $patient->id))
                ->assertOk()
                ->getContent()
        );

        expect($card)->toContain('30 weeks');
        expect($card)->toContain('3rd Trimester');
    }
});

it('never shows a trimester for a delivered pregnancy whose GA was never recorded', function () {
    $user = User::factory()->create(['role' => 'staff']);
    $patient = trimesterProfilePatient(['status' => 'DELIVERED']);
    trimesterProfileVisit($patient->id, ['gestational_age' => null]);

    $card = trimesterProfileGaCardText(
        $this->actingAs($user)
            ->get(route('patients.show', $patient->id))
            ->assertOk()
            ->getContent()
    );

    expect(preg_match('/[123](st|nd|rd) Trimester/', $card))->toBe(0);
});

// ------------------------------------------------------------------
// D. Shared classifier boundaries (identical to the browser classifier)
// ------------------------------------------------------------------

it('classifies gestational age boundaries in the shared server-side helper', function () {
    expect(TrimesterClassifier::classify(1.0))->toBe(1);
    expect(TrimesterClassifier::classify(12.9))->toBe(1);
    expect(TrimesterClassifier::classify(13.0))->toBe(2);
    expect(TrimesterClassifier::classify(27.9))->toBe(2);
    expect(TrimesterClassifier::classify(28.0))->toBe(3);
    expect(TrimesterClassifier::classify(30.0))->toBe(3);
    expect(TrimesterClassifier::classify(42.0))->toBe(3);

    expect(TrimesterClassifier::label(1.0))->toBe('1st Trimester');
    expect(TrimesterClassifier::label(12.9))->toBe('1st Trimester');
    expect(TrimesterClassifier::label(13.0))->toBe('2nd Trimester');
    expect(TrimesterClassifier::label(27.9))->toBe('2nd Trimester');
    expect(TrimesterClassifier::label(28.0))->toBe('3rd Trimester');
    expect(TrimesterClassifier::label(42.0))->toBe('3rd Trimester');
});

it('refuses to guess a trimester for blank, invalid or below-range values', function () {
    foreach ([null, '', 'abc', 0.9, -3, NAN, INF] as $value) {
        expect(TrimesterClassifier::classify($value))->toBeNull();
        expect(TrimesterClassifier::label($value))->toBeNull();
    }

    // Decimal strings are accepted without rounding first.
    expect(TrimesterClassifier::label('12.9'))->toBe('1st Trimester');
    expect(TrimesterClassifier::label('27.9'))->toBe('2nd Trimester');
});

// ------------------------------------------------------------------
// E. Trimester is derived and never persisted
// ------------------------------------------------------------------

it('does not store a trimester anywhere in the schema', function () {
    expect(Schema::hasColumn('prenatal_visits', 'trimester'))->toBeFalse();
    expect(Schema::hasColumn('patients', 'trimester'))->toBeFalse();
    expect(Schema::hasColumn('prenatal_visits', 'trimester_label'))->toBeFalse();
});
