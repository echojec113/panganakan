<?php

use App\Services\FetalHeartRateAssessmentService;
use App\ValueObjects\FetalAssessmentOutcome;

uses(Tests\TestCase::class);

function fhrService(): FetalHeartRateAssessmentService
{
    return app(FetalHeartRateAssessmentService::class);
}

test('exposes the conservative Option A second-trimester window', function () {
    expect(FetalHeartRateAssessmentService::MIN_GA)->toBe(14.0);
    expect(FetalHeartRateAssessmentService::MAX_GA_EXCLUSIVE)->toBe(28.0);
    expect(FetalHeartRateAssessmentService::REFERENCE_LOW)->toBe(120);
    expect(FetalHeartRateAssessmentService::REFERENCE_HIGH)->toBe(160);
});

test('classifies the normal category as Within Applicable Reference inside the window', function () {
    $service = fhrService();

    foreach ([14.0, 20.0, 27.9] as $ga) {
        $outcome = $service->assess('Normal 120-160', $ga);
        expect($outcome->status)->toBe('within');
        expect($outcome->label)->toBe(FetalHeartRateAssessmentService::LABEL_WITHIN);
        expect($outcome->recommendation)->toContain('matches the applicable ISUOG reference');
        expect($outcome->reason)->toBe('');
    }

    expect($service->assess('Normal 120-160', 20)->chipClass())
        ->toBe(FetalAssessmentOutcome::CHIP_CLASSES['within']);
});

test('routes every non-normal category to Needs Clinical Review inside the window', function () {
    $service = fhrService();

    $tachy = $service->assess('Tachycardia >160', 20);
    expect($tachy->status)->toBe('review');
    expect($tachy->label)->toBe(FetalHeartRateAssessmentService::LABEL_REVIEW);
    expect($tachy->recommendation)->toContain('160–180 bpm can be normal with fetal movement');
    expect($tachy->recommendation)->toContain('180 bpm or more warrants specialist evaluation');
    expect($tachy->chipClass())->toBe(FetalAssessmentOutcome::CHIP_CLASSES['review']);

    $brady = $service->assess('Bradycardia <120', 20);
    expect($brady->status)->toBe('review');
    expect($brady->recommendation)->toContain('110 bpm or less warrants timely specialist evaluation');

    $weak = $service->assess('Weak', 20);
    expect($weak->status)->toBe('review');
    expect($weak->recommendation)->toContain('qualitative finding');
    expect($weak->recommendation)->toContain('cannot be compared numerically');

    $absent = $service->assess('Absent', 20);
    expect($absent->status)->toBe('review');
    expect($absent->recommendation)->toContain('prompt clinical assessment');
    expect($absent->recommendation)->toContain('confirmation by an appropriate examination method');
    expect($absent->recommendation)->toContain('does not diagnose fetal demise');
});

test('shows Unable to Assess Reliably outside the supported window', function () {
    $service = fhrService();

    $thirdTrimester = $service->assess('Normal 120-160', 30.0);
    expect($thirdTrimester->status)->toBe('unable');
    expect($thirdTrimester->label)->toBe(FetalHeartRateAssessmentService::LABEL_UNABLE);
    expect($thirdTrimester->reason)->toContain('14+0 to 27+6 weeks');
    expect($thirdTrimester->reason)->toContain('30 weeks falls outside that window');
    expect($thirdTrimester->recommendation)->toBe(FetalHeartRateAssessmentService::REC_UNABLE_BASE);

    expect($service->assess('Normal 120-160', 28.0)->status)->toBe('unable');
    expect($service->assess('Normal 120-160', 13.9)->status)->toBe('unable');
    expect($service->assess('Normal 120-160', 4.0)->reason)->toContain('falls outside that window');
});

test('retains safety guidance for abnormal categories outside the window', function () {
    $service = fhrService();

    $tachy = $service->assess('Tachycardia >160', 36.0);
    expect($tachy->status)->toBe('unable');
    expect($tachy->label)->toBe(FetalHeartRateAssessmentService::LABEL_UNABLE);
    expect($tachy->recommendation)->toContain(FetalHeartRateAssessmentService::REC_UNABLE_BASE);
    expect($tachy->recommendation)->toContain(FetalHeartRateAssessmentService::SAFETY_OUT_OF_RANGE);

    $weak = $service->assess('Weak', 36.0);
    expect($weak->status)->toBe('unable');
    expect($weak->recommendation)->toContain(FetalHeartRateAssessmentService::SAFETY_WEAK);

    $absent = $service->assess('Absent', 36.0);
    expect($absent->status)->toBe('unable');
    expect($absent->recommendation)->toContain('regardless of whether numerical reference interpretation is available');
    expect($absent->recommendation)->toContain('does not diagnose fetal demise');
    expect($absent->label)->toBe(FetalHeartRateAssessmentService::LABEL_UNABLE);
});

test('keeps Absent and other safety guidance when gestational age is missing', function () {
    $service = fhrService();

    $absent = $service->assess('Absent', null);
    expect($absent->status)->toBe('unable');
    expect($absent->reason)->toBe(FetalHeartRateAssessmentService::REASON_GA_MISSING);
    expect($absent->recommendation)->toContain('prompt clinical assessment');
    expect($absent->recommendation)->toContain('regardless of whether numerical reference interpretation is available');

    $tachy = $service->assess('Tachycardia >160', 'abc');
    expect($tachy->status)->toBe('unable');
    expect($tachy->recommendation)->toContain(FetalHeartRateAssessmentService::SAFETY_OUT_OF_RANGE);

    $normal = $service->assess('Normal 120-160', null);
    expect($normal->status)->toBe('unable');
    expect($normal->reason)->toBe(FetalHeartRateAssessmentService::REASON_GA_MISSING);
    expect($normal->recommendation)->toBe(FetalHeartRateAssessmentService::REC_UNABLE_BASE);
});

test('handles missing and unrecognized categories without inventing a classification', function () {
    $service = fhrService();

    $empty = $service->assess(null, 20);
    expect($empty->status)->toBe('unable');
    expect($empty->reason)->toContain(FetalHeartRateAssessmentService::REASON_NO_CATEGORY);
    expect($empty->rows['category']['value'])->toBe('Not recorded');
    expect($empty->recommendation)->toBe(FetalHeartRateAssessmentService::REC_UNABLE_BASE);

    $emptyString = $service->assess('   ', 20);
    expect($emptyString->status)->toBe('unable');

    $unknown = $service->assess('Gallop', 20);
    expect($unknown->status)->toBe('unable');
    expect($unknown->reason)->toContain(FetalHeartRateAssessmentService::REASON_UNKNOWN);
    expect($unknown->rows['category']['value'])->toBe('Gallop');
});

test('never converts a category into a beats-per-minute value and labels the reference context', function () {
    $service = fhrService();

    $outcome = $service->assess('Normal 120-160', 20);

    expect($outcome->rows)->toHaveKeys(['category', 'ga']);
    expect($outcome->rows['category']['value'])->toBe('Normal 120-160');
    expect($outcome->rows['ga']['value'])->toBe('20 weeks');
    expect($outcome->context)->toContain('second-trimester fetal cardiac screening context');
    expect($outcome->context)->toContain('18–24 week scan');
    expect($outcome->context)->toContain('14+0 to 27+6 weeks');
    expect($outcome->limitation)->toContain('staff-selected category, not a measured heart rate');
    expect($outcome->limitation)->toContain('no beats-per-minute value is derived or stored');
    expect($outcome->limitation)->toContain('NICE NG229 rate thresholds apply to labour');

    expect($outcome->sources)->toHaveCount(2);
    expect($outcome->sources[0]['url'])
        ->toBe('https://www.isuog.org/clinical-resources/isuog-guidelines/practice-guidelines-english.html');
    expect($outcome->sources[1]['url'])
        ->toBe('https://www.nice.org.uk/guidance/ng229/chapter/recommendations');
    expect($outcome->sources[1]['label'])->toContain('not applied to this antenatal assessment');
    expect($outcome->pendingNote)->toContain('pending review by the clinic');
});

test('client config mirrors the server-side constants exactly', function () {
    $config = fhrService()->clientConfig();

    expect($config['window'])->toBe(['min' => 14.0, 'maxExclusive' => 28.0]);
    expect($config['labels']['within'])->toBe(FetalHeartRateAssessmentService::LABEL_WITHIN);
    expect($config['labels']['review'])->toBe(FetalHeartRateAssessmentService::LABEL_REVIEW);
    expect($config['labels']['unable'])->toBe(FetalHeartRateAssessmentService::LABEL_UNABLE);
    expect($config['categories']['Normal 120-160']['status'])->toBe('within');
    expect($config['categories']['Normal 120-160']['recommendation'])
        ->toBe(FetalHeartRateAssessmentService::REC_NORMAL);
    expect($config['categories']['Absent']['status'])->toBe('review');
    expect($config['categories']['Absent']['recommendation'])
        ->toBe(FetalHeartRateAssessmentService::REC_ABSENT);
    expect($config['messages']['safety']['Absent'])->toBe(FetalHeartRateAssessmentService::SAFETY_ABSENT);
    expect($config['messages']['safety']['Bradycardia <120'])
        ->toBe(FetalHeartRateAssessmentService::SAFETY_OUT_OF_RANGE);
    expect($config['messages']['reasons']['gaOutside'])->toBe(FetalHeartRateAssessmentService::REASON_GA_OUTSIDE);
    expect($config['context'])->toBe(FetalHeartRateAssessmentService::CONTEXT);
    expect($config['limitation'])->toBe(FetalHeartRateAssessmentService::LIMITATION);
    expect($config['chipClass'])->toBe(FetalAssessmentOutcome::CHIP_CLASSES);
});
