<?php

use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;

function trimesterVisibilityStaff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

function trimesterVisibilityPatient(): Patient
{
    // 12.0 weeks today, so an update posting GA 12.0 stays inside the
    // controller's existing ±3 week LMP tolerance.
    return Patient::create([
        'first_name' => 'Trimester',
        'last_name' => 'Visibility',
        'age' => 30,
        'address' => 'Test address',
        'contact_number' => '09170000000',
        'status' => 'ONGOING',
        'lmp' => today()->subDays(84)->toDateString(),
    ]);
}

function trimesterVisibilityVisit(): PrenatalVisit
{
    return PrenatalVisit::create([
        'patient_id' => trimesterVisibilityPatient()->id,
        'visit_date' => today()->toDateString(),
        'gestational_age' => 20.0,
        'bp_sys' => 110,
        'bp_dia' => 70,
        'weight' => 60,
        'hypertension' => 0,
        'diabetes' => 0,
        'anemia' => 0,
        'fundic_height' => '28',
        'fetal_heart_tone' => 'Regular 120-160',
        'fetal_movement' => 'Active',
        'presenting_part' => 'Cephalic',
        'uterine_activity' => 'Normal',
        'cervical_dilation' => '3',
        'bag_of_water' => 'Intact',
    ]);
}

function trimesterVisibilityDom(string $html): DOMDocument
{
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $dom;
}

function trimesterVisibilityComponent(): string
{
    return file_get_contents(resource_path('views/components/trimester-visibility.blade.php'));
}

/**
 * Returns the executable JavaScript of the component, so assertions never
 * trip over explanatory Blade comments.
 */
function trimesterVisibilityScript(): string
{
    $component = trimesterVisibilityComponent();

    if (!preg_match('/<script>(.*)<\/script>/s', $component, $matches)) {
        return '';
    }

    return $matches[1];
}

/**
 * Returns the first ancestor of a field that is marked
 * data-trimester-visible, i.e. the ancestor that would hide it.
 */
function trimesterVisibilityBlockingAncestor(DOMNode $node): ?DOMNode
{
    while ($node !== null) {
        if ($node->attributes !== null
            && $node->attributes->getNamedItem('data-trimester-visible') !== null) {
            return $node;
        }
        $node = $node->parentNode;
    }

    return null;
}

function trimesterVisibilityNamedNode(DOMDocument $dom, string $name): ?DOMNode
{
    foreach ($dom->getElementsByTagName('*') as $node) {
        if ($node->attributes !== null
            && $node->attributes->getNamedItem('name')?->value === $name) {
            return $node;
        }
    }

    return null;
}

// ------------------------------------------------------------------
// A. Visibility markers exist on both forms, and only where allowed
// ------------------------------------------------------------------

it('marks every trimester-controlled wrapper on the prenatal create form', function () {
    $html = $this->actingAs(trimesterVisibilityStaff())
        ->get(route('prenatal-visits.create'))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'data-trimester-visible="2,3"'))->toBe(3);
    expect(substr_count($html, 'data-trimester-visible="3"'))->toBe(5);

    foreach ([
        'fundic_height', 'fetal_heart_tone', 'fetal_movement',
        'presenting_part', 'uterine_activity', 'cervical_dilation', 'bag_of_water',
    ] as $name) {
        expect($html)->toContain('name="' . $name . '"');
    }

    expect($html)->toContain('id="trimester_indicator"');
    expect($html)->toContain('classifyTrimester');
});

it('marks every trimester-controlled wrapper on the prenatal edit form', function () {
    $visit = trimesterVisibilityVisit();

    $html = $this->actingAs(trimesterVisibilityStaff())
        ->get(route('prenatal-visits.edit', $visit->id))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'data-trimester-visible="2,3"'))->toBe(3);
    expect(substr_count($html, 'data-trimester-visible="3"'))->toBe(5);
    expect($html)->toContain('id="trimester_indicator"');
    expect($html)->toContain('classifyTrimester');
});

it('keeps risk factors, gestational age and assessment fields outside trimester control', function () {
    $html = $this->actingAs(trimesterVisibilityStaff())
        ->get(route('prenatal-visits.create'))
        ->assertOk()
        ->getContent();

    $dom = trimesterVisibilityDom($html);

    foreach (['hypertension', 'diabetes', 'anemia', 'gestational_age', 'treatment_plan'] as $name) {
        $field = trimesterVisibilityNamedNode($dom, $name);

        expect($field)->not->toBeNull();
        expect(trimesterVisibilityBlockingAncestor($field))->toBeNull();
    }
});

// ------------------------------------------------------------------
// B. Hidden fields stay in the DOM, enabled, named and populated
// ------------------------------------------------------------------

it('never disables, renames or empties a trimester-controlled field', function () {
    $visit = trimesterVisibilityVisit();

    $html = $this->actingAs(trimesterVisibilityStaff())
        ->get(route('prenatal-visits.edit', $visit->id))
        ->assertOk()
        ->getContent();

    $dom = trimesterVisibilityDom($html);

    foreach ([
        'fundic_height', 'fetal_heart_tone', 'fetal_movement',
        'presenting_part', 'uterine_activity', 'cervical_dilation', 'bag_of_water',
    ] as $name) {
        $field = trimesterVisibilityNamedNode($dom, $name);

        expect($field)->not->toBeNull();
        expect($field->attributes->getNamedItem('disabled'))->toBeNull();

        $value = $field->attributes->getNamedItem('value')?->value ?? $field->textContent;

        expect(trim((string) $value))->not->toBe('');
    }
});

it('exposes every historical value on the edit form even though the wrapper is trimester-hidden', function () {
    $visit = trimesterVisibilityVisit();

    $html = $this->actingAs(trimesterVisibilityStaff())
        ->get(route('prenatal-visits.edit', $visit->id))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('value="28"');
    expect($html)->toContain('value="Cephalic" selected');
    expect($html)->toContain('value="Normal" selected');
    expect($html)->toContain('value="Intact" selected');
    expect($html)->toContain('value="3"');
});

it('ships only visual CSS toggling in the trimester component', function () {
    $component = trimesterVisibilityComponent();
    $script = trimesterVisibilityScript();

    expect($component)->toContain('.trimester-hidden');
    expect($script)->toContain("classList.toggle('trimester-hidden', !visible)");
    expect($script)->not->toContain('disabled');
    expect($script)->not->toContain('.value =');
    expect($script)->not->toContain('removeAttribute');
    expect($script)->not->toContain("setAttribute('name'");
    expect($script)->not->toContain('removeChild');
});

it('renders a larger display-only trimester chip beside the gestational age field', function () {
    $html = $this->actingAs(trimesterVisibilityStaff())
        ->get(route('prenatal-visits.create'))
        ->assertOk()
        ->getContent();

    $indicator = trimesterVisibilityDom($html)->getElementById('trimester_indicator');

    expect($indicator)->not->toBeNull();

    // Display only: never named, so it can never be submitted.
    expect($indicator->attributes->getNamedItem('name'))->toBeNull();

    $class = $indicator->getAttribute('class');

    expect($class)->toContain('inline-flex');
    expect($class)->toContain('rounded-full');
    expect($class)->toContain('px-3');
    expect($class)->toContain('py-1.5');
    expect($class)->toContain('text-sm');
    expect($class)->toContain('font-semibold');

    // No standalone label: the chip itself carries the trimester value.
    expect($html)->not->toContain('>Trimester</div>');
    expect($html)->not->toContain('Trimester: —');
    expect($html)->not->toContain('Trimester: 1st');
    expect($html)->toContain('whitespace-nowrap">—</span>');
});

it('keeps the trimester indicator updating dynamically with the GA field', function () {
    $script = trimesterVisibilityScript();

    expect($script)->toContain("gaField.addEventListener('input', updateTrimesterVisibility)");
    expect($script)->toContain("gaField.addEventListener('change', updateTrimesterVisibility)");
    expect($script)->toContain('setTimeout(updateTrimesterVisibility, 0)');
    expect($script)->toContain('TRIMESTER_LABELS[trimester]');
});

// ------------------------------------------------------------------
// C. Trimester classification (boundary behaviour)
// ------------------------------------------------------------------

it('classifies gestational age boundaries without rounding, against the shipped script', function () {
    if (!function_exists('shell_exec')) {
        $this->markTestSkipped('shell_exec is unavailable; cannot execute the shipped classifier.');
    }

    $nodeVersion = @shell_exec('node --version 2>&1');

    if (!is_string($nodeVersion) || trim($nodeVersion) === '') {
        $this->markTestSkipped('Node.js is unavailable; cannot execute the shipped classifier.');
    }

    $matches = [];

    preg_match(
        '/function classifyTrimester\(rawValue\) \{.*?\n        \}/s',
        trimesterVisibilityComponent(),
        $matches
    );

    if ($matches === []) {
        $this->fail('classifyTrimester() was not found in the trimester visibility component.');
    }

    $cases = [
        [1.0, 1], [12.9, 1],
        [13.0, 2], [27.9, 2],
        [28.0, 3], [42.0, 3],
        [0.9, null], [-3, null], ['', null], ['abc', null], [null, null],
    ];

    $script = $matches[0] . <<<'JS'

const cases = CASES;
for (const [ga, expected] of cases) {
    const actual = classifyTrimester(ga);
    if (actual !== expected) {
        console.error('FAIL ga=' + JSON.stringify(ga)
            + ' expected=' + JSON.stringify(expected)
            + ' got=' + JSON.stringify(actual));
        process.exit(1);
    }
}
console.log('OK ' + cases.length);
JS;
    $script = str_replace('CASES', json_encode($cases), $script);

    $path = tempnam(sys_get_temp_dir(), 'trimester_');

    file_put_contents($path, $script);

    try {
        $output = @shell_exec('node ' . escapeshellarg($path) . ' 2>&1');
    } finally {
        @unlink($path);
    }

    expect(trim((string) $output))->toContain('OK ' . count($cases));
});

// ------------------------------------------------------------------
// D. Update safety: a trimester-hidden value must still be persisted
// ------------------------------------------------------------------

it('preserves trimester-controlled values on update even when the posted GA hides them', function () {
    $visit = trimesterVisibilityVisit();

    $this->actingAs(trimesterVisibilityStaff())
        ->put(route('prenatal-visits.update', $visit->id), [
            'patient_id' => $visit->patient_id,
            'visit_date' => today()->toDateString(),
            'bp_sys' => '110',
            'bp_dia' => '70',
            'weight' => '60',
            'gestational_age' => '12.0',
            'hypertension' => '0',
            'diabetes' => '0',
            'anemia' => '0',
            'fundic_height' => '28',
            'fetal_heart_tone' => 'Regular 120-160',
            'fetal_movement' => 'Active',
            'presenting_part' => 'Cephalic',
            'uterine_activity' => 'Normal',
            'cervical_dilation' => '3',
            'bag_of_water' => 'Intact',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $fresh = $visit->fresh();

    expect((float) $fresh->gestational_age)->toBe(12.0);
    expect($fresh->fundic_height)->toBe('28');
    expect($fresh->fetal_heart_tone)->toBe('Regular 120-160');
    expect($fresh->fetal_movement)->toBe('Active');
    expect($fresh->presenting_part)->toBe('Cephalic');
    expect($fresh->uterine_activity)->toBe('Normal');
    expect((float) $fresh->cervical_dilation)->toBe(3.0);
    expect($fresh->bag_of_water)->toBe('Intact');
});

it('keeps every controlled field nullable in validation so a hidden field may post empty', function () {
    $controller = file_get_contents(app_path('Http/Controllers/PrenatalVisitController.php'));

    foreach ([
        'fundic_height', 'fetal_heart_tone', 'fetal_movement',
        'presenting_part', 'uterine_activity', 'cervical_dilation', 'bag_of_water',
    ] as $name) {
        expect($controller)->toMatch("/'{$name}' => 'nullable\|[^']*'/");
    }
});
