<?php

use App\Services\FetalWeightAssessmentService;
use App\ValueObjects\FetalAssessmentOutcome;

uses(Tests\TestCase::class);

function fwaService(): FetalWeightAssessmentService
{
    return app(FetalWeightAssessmentService::class);
}

test('WHO Table 11 anchor percentiles match the published chart', function () {
    $anchors = [
        14 => [78, 90, 104],
        20 => [286, 330, 380],
        24 => [576, 665, 765],
        28 => [1026, 1189, 1368],
        32 => [1635, 1901, 2187],
        36 => [2352, 2745, 3153],
        40 => [3084, 3617, 4135],
    ];

    foreach ($anchors as $week => $values) {
        expect(fwaService()->percentile((float) $week, 10))->toBe((float) $values[0]);
        expect(fwaService()->percentile((float) $week, 50))->toBe((float) $values[1]);
        expect(fwaService()->percentile((float) $week, 90))->toBe((float) $values[2]);
    }
});

test('WHO table covers every week 14 to 40 with monotonically increasing percentiles', function () {
    $table = FetalWeightAssessmentService::TABLE;

    expect(array_keys($table))->toBe(range(14, 40));

    foreach ([10, 50, 90] as $percentile) {
        $previous = null;
        foreach ($table as $week => $row) {
            expect($row[$percentile])->toBeInt()->toBeGreaterThan(0);
            if ($previous !== null) {
                expect($row[$percentile])->toBeGreaterThan($previous);
            }
            $previous = $row[$percentile];
        }
    }
});

test('interpolates fractional gestational ages linearly between weekly anchors', function () {
    $service = fwaService();

    expect(round($service->percentile(32.7, 10), 1))->toBe(1755.4);
    expect(round($service->percentile(32.7, 50), 1))->toBe(2042.4);
    expect(round($service->percentile(32.7, 90), 1))->toBe(2349.4);
    expect(round($service->percentile(22.5, 10), 1))->toBe(450.5);
    expect($service->percentile(22.5, 90))->toBe(599.0);
    expect($service->percentile(14.0, 10))->toBe(78.0);
    expect($service->percentile(40.0, 90))->toBe(4135.0);
});

test('classifies exact 32-week boundaries against full-precision thresholds', function () {
    $service = fwaService();

    expect($service->assess(1634, 32)->status)->toBe('below');
    expect($service->assess(1635, 32)->status)->toBe('within');
    expect($service->assess(2186, 32)->status)->toBe('within');
    expect($service->assess(2187, 32)->status)->toBe('above');
});

test('classifies fractional boundaries at full precision and rounds for display only', function () {
    $service = fwaService();

    $below = $service->assess(1755, '32.7');
    expect($below->status)->toBe('below');
    expect($below->label)->toBe(FetalWeightAssessmentService::LABEL_BELOW);
    expect($below->rows['range']['value'])->toBe('1,755 g – 2,349 g');
    expect($below->rows['median']['value'])->toBe('2,042 g');
    expect($below->clarification)->toContain('1,755.4');
    expect($below->clarification)->toContain('rounded to whole grams');

    expect($service->assess(1756, '32.7')->status)->toBe('within');
    expect($service->assess(2349, '32.7')->status)->toBe('within');
    expect($service->assess(2350, '32.7')->status)->toBe('above');
});

test('explains classification when the weight equals a rounded or exact upper threshold', function () {
    $service = fwaService();

    $exact = $service->assess(2187, 32);
    expect($exact->status)->toBe('above');
    expect($exact->clarification)->toContain('exactly the 90th-percentile threshold');
    expect($exact->clarification)->toContain('at or above the 90th percentile');

    expect($service->assess(2190, 32)->clarification)->toBeNull();
    expect($service->assess(1635, 32)->clarification)->toBeNull();
});

test('supports gestational ages through 40.0 weeks and refuses anything beyond', function () {
    $service = fwaService();

    expect($service->assess(3084, 40)->status)->toBe('within');
    expect($service->assess(3617, 40)->status)->toBe('within');
    expect($service->assess(3083, 40)->status)->toBe('below');
    expect($service->assess(4134, 40)->status)->toBe('within');
    expect($service->assess(4135, 40)->status)->toBe('above');

    $tooLate = $service->assess(3500, '40.1');
    expect($tooLate->status)->toBe('unable');
    expect($tooLate->label)->toBe(FetalWeightAssessmentService::LABEL_UNABLE);
    expect($tooLate->reason)->toContain(
        'Gestational age 40.1 weeks is outside the supported WHO reference range (14+0 to 40+0 weeks)'
    );
    expect($tooLate->rows['range']['value'])->toBe('Not available');

    expect($service->assess(3500, 13.9)->status)->toBe('unable');
    expect($service->assess(3500, 4)->reason)->toContain('outside the supported WHO reference range');
});

test('reports missing or invalid inputs as Unable to Assess with explicit reasons', function () {
    $service = fwaService();

    $noGa = $service->assess(2000, null);
    expect($noGa->status)->toBe('unable');
    expect($noGa->reason)->toBe(FetalWeightAssessmentService::REASON_GA_MISSING);
    expect($noGa->rows['ga']['value'])->toBe('Not recorded');

    $noWeight = $service->assess(null, 32);
    expect($noWeight->status)->toBe('unable');
    expect($noWeight->reason)->toBe(FetalWeightAssessmentService::REASON_WEIGHT_MISSING);
    expect($noWeight->rows['weight']['value'])->toBe('Not recorded');

    $neither = $service->assess('abc', ' ');
    expect($neither->status)->toBe('unable');
    expect($neither->reason)->toContain(FetalWeightAssessmentService::REASON_GA_MISSING);
    expect($neither->reason)->toContain(FetalWeightAssessmentService::REASON_WEIGHT_MISSING);

    expect($noWeight->recommendation)->toBe(FetalWeightAssessmentService::REC_UNABLE);
});

test('displays the recorded values without altering them', function () {
    $service = fwaService();

    $outcome = $service->assess('1800', '32.7');
    expect($outcome->rows['weight']['value'])->toBe('1,800 g');
    expect($outcome->rows['ga']['value'])->toBe('32.7 weeks');

    expect($service->assess('1800.50', 32)->rows['weight']['value'])->toBe('1,800.5 g');
    expect($service->assess(1800, 32)->rows['weight']['value'])->toBe('1,800 g');
    expect($service->assess(' 500 ', '32.70')->rows['weight']['value'])->toBe('500 g');
    expect($service->assess(' 500 ', '32.70')->rows['ga']['value'])->toBe('32.7 weeks');
});

test('attaches recommendation, precision footnote, WHO source and pending-review note', function () {
    $service = fwaService();

    $within = $service->assess(1901, 32);
    expect($within->status)->toBe('within');
    expect($within->label)->toBe(FetalWeightAssessmentService::LABEL_WITHIN);
    expect($within->recommendation)->toContain('within the WHO expected reference range');
    expect($within->footnote)->toContain('full precision');
    expect($within->footnote)->toContain('rounded for display only');
    expect($within->sources[0]['url'])->toBe('https://pmc.ncbi.nlm.nih.gov/articles/PMC5261648/');
    expect($within->sources[0]['label'])->toContain('Table 11');
    expect($within->pendingNote)->toContain('pending review by the clinic');
    expect($within->reason)->toBe('');
    expect($within->chipClass())->toBe(FetalAssessmentOutcome::CHIP_CLASSES['within']);
});

test('uses non-alarming amber chips for below and above classifications', function () {
    $service = fwaService();

    expect($service->assess(1000, 32)->chipClass())->toBe(FetalAssessmentOutcome::CHIP_CLASSES['below']);
    expect($service->assess(4000, 32)->chipClass())->toBe(FetalAssessmentOutcome::CHIP_CLASSES['above']);
    expect($service->assess(4000, 41)->chipClass())->toBe(FetalAssessmentOutcome::CHIP_CLASSES['unable']);
});

test('client config embeds the same table, window and messages used server side', function () {
    $config = fwaService()->clientConfig();

    expect($config['table'])->toBe(FetalWeightAssessmentService::TABLE);
    expect($config['window'])->toBe(['min' => 14.0, 'max' => 40.0]);
    expect($config['messages']['below'])->toBe(FetalWeightAssessmentService::REC_BELOW);
    expect($config['messages']['within'])->toBe(FetalWeightAssessmentService::REC_WITHIN);
    expect($config['messages']['above'])->toBe(FetalWeightAssessmentService::REC_ABOVE);
    expect($config['messages']['unable'])->toBe(FetalWeightAssessmentService::REC_UNABLE);
    expect($config['messages']['reasons']['gaOutside'])->toBe(FetalWeightAssessmentService::REASON_GA_OUTSIDE);
    expect($config['messages']['clarify']['below'])->toBe(FetalWeightAssessmentService::CLARIFY_BELOW);
    expect($config['chipClass'])->toBe(FetalAssessmentOutcome::CHIP_CLASSES);
    expect($config['pendingNote'])->toBe(FetalWeightAssessmentService::PENDING_NOTE);
});
