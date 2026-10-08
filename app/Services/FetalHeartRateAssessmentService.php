<?php

declare(strict_types=1);

namespace App\Services;

use App\ValueObjects\FetalAssessmentOutcome;

final class FetalHeartRateAssessmentService
{
    /**
     * Display-only interpretation of the staff-selected fetal heartbeat
     * category against the ISUOG normal band of 120–160 bpm, whose published
     * clinical context is second-trimester fetal cardiac screening
     * (ISUOG Practice Guidelines, UOG 2023, doi:10.1002/uog.26224),
     * corroborated by the mid-trimester screening guideline (ISUOG 2022,
     * UOG 59:840-856) describing a normal regular heart rate of typically
     * 120–160 bpm at the routine 18–24 week scan. The display window therefore covers
     * 14+0 to 27+6 weeks only; gestational ages outside it, missing values,
     * or unrecognized categories produce "Unable to Assess Reliably" rather
     * than an unsupported normal classification. Outside the window the
     * recorded finding still drives safety guidance, and an Absent finding
     * always prompts clinical assessment and confirmation by an appropriate
     * examination method. This service never diagnoses and never derives or
     * stores a beats-per-minute value.
     */
    public const MIN_GA = 14.0;

    public const MAX_GA_EXCLUSIVE = 28.0;

    public const REFERENCE_LOW = 120;

    public const REFERENCE_HIGH = 160;

    public const CATEGORIES = [
        'Normal 120-160',
        'Tachycardia >160',
        'Bradycardia <120',
        'Weak',
        'Absent',
    ];

    public const LABEL_WITHIN = 'Within Applicable Reference';

    public const LABEL_REVIEW = 'Needs Clinical Review';

    public const LABEL_UNABLE = 'Unable to Assess Reliably';

    public const PENDING_NOTE = 'Recommendation text pending review by the clinic’s qualified healthcare professional.';

    public const SOURCE_ISUOG = [
        'label' => 'ISUOG Practice Guidelines — fetal cardiac screening (UOG 2023) and mid-trimester screening (UOG 2022): normal fetal heart rate 120–160 bpm',
        'url' => 'https://www.isuog.org/clinical-resources/isuog-guidelines/practice-guidelines-english.html',
    ];

    public const SOURCE_NICE = [
        'label' => 'NICE NG229 (intrapartum reference — thresholds deliberately not applied to this antenatal assessment; recommendation 1.2.15)',
        'url' => 'https://www.nice.org.uk/guidance/ng229/chapter/recommendations',
    ];

    public const CONTEXT = 'Reference context: ISUOG guidelines state a normal fetal heart rate of 120–160 bpm in the second-trimester fetal cardiac screening context (UOG 2023), corroborated by the mid-trimester screening guideline describing a normal regular heart rate of typically 120–160 bpm at the routine 18–24 week scan (UOG 2022). This display applies that reference only within 14+0 to 27+6 weeks (second trimester).';

    public const LIMITATION = 'The recorded value is a staff-selected category, not a measured heart rate, so this is a consistency check only: it cannot diagnose arrhythmia, fetal compromise, or fetal demise, and no beats-per-minute value is derived or stored. ISUOG notes a transient 160–180 bpm can be normal with fetal movement, and NICE NG229 rate thresholds apply to labour, not antenatal assessment.';

    public const REC_NORMAL = 'The recorded category matches the applicable ISUOG reference (120–160 bpm) for this gestational-age window. This is not a diagnosis; continue routine antenatal care as advised by a qualified healthcare professional.';

    public const REC_TACHY = 'The recorded category indicates a heart rate above 160 bpm. Verify the assessment and review clinically with a qualified healthcare professional. ISUOG guidance notes a transient rate of 160–180 bpm can be normal with fetal movement, while a persistent rate of 180 bpm or more warrants specialist evaluation. This display does not diagnose a fetal arrhythmia.';

    public const REC_BRADY = 'The recorded category indicates a heart rate below 120 bpm. Verify the assessment and review clinically with a qualified healthcare professional. ISUOG guidance notes that a persistent rate of 110 bpm or less warrants timely specialist evaluation. This display does not diagnose a fetal arrhythmia.';

    public const REC_WEAK = 'A weak heartbeat is a qualitative finding that cannot be compared numerically to the 120–160 bpm reference. Verify the assessment and review clinically with a qualified healthcare professional.';

    public const REC_ABSENT = 'An absent heartbeat finding requires prompt clinical assessment and confirmation by an appropriate examination method (for example, a targeted ultrasound examination) with a qualified healthcare professional. This system does not diagnose fetal demise.';

    public const REC_UNABLE_BASE = 'No automated assessment can be given for this record. Review with a qualified healthcare professional.';

    public const SAFETY_OUT_OF_RANGE = 'The recorded out-of-range heartbeat finding must still be verified and reviewed promptly with a qualified healthcare professional.';

    public const SAFETY_WEAK = 'The recorded weak heartbeat finding must still be verified and reviewed promptly with a qualified healthcare professional.';

    public const SAFETY_ABSENT = 'The recorded absent heartbeat finding requires prompt clinical assessment and confirmation by an appropriate examination method (for example, a targeted ultrasound examination) with a qualified healthcare professional, regardless of whether numerical reference interpretation is available. This system does not diagnose fetal demise.';

    public const REASON_NO_CATEGORY = 'No fetal heartbeat finding is recorded.';

    public const REASON_UNKNOWN = 'The recorded fetal heartbeat value is not a recognized category, so no classification can be made.';

    public const REASON_GA_MISSING = 'Gestational age is missing or invalid, so the applicable reference window cannot be determined.';

    public const REASON_GA_OUTSIDE = 'The applicable ISUOG reference (120–160 bpm) covers second-trimester screening from 14+0 to 27+6 weeks; the recorded gestational age {ga} weeks falls outside that window, so no reference-based classification is shown.';

    public const IN_WINDOW_STATUSES = [
        'Normal 120-160' => 'within',
        'Tachycardia >160' => 'review',
        'Bradycardia <120' => 'review',
        'Weak' => 'review',
        'Absent' => 'review',
    ];

    public const IN_WINDOW_RECOMMENDATIONS = [
        'Normal 120-160' => self::REC_NORMAL,
        'Tachycardia >160' => self::REC_TACHY,
        'Bradycardia <120' => self::REC_BRADY,
        'Weak' => self::REC_WEAK,
        'Absent' => self::REC_ABSENT,
    ];

    public const SAFETY_BY_CATEGORY = [
        'Tachycardia >160' => self::SAFETY_OUT_OF_RANGE,
        'Bradycardia <120' => self::SAFETY_OUT_OF_RANGE,
        'Weak' => self::SAFETY_WEAK,
        'Absent' => self::SAFETY_ABSENT,
    ];

    /**
     * Display-only assessment. Never writes or transforms stored values.
     */
    public function assess(mixed $category, mixed $ga): FetalAssessmentOutcome
    {
        $categoryValue = $this->normalizeCategory($category);
        $gaFloat = $this->parseNumber($ga);
        $gaDisplay = $gaFloat === null ? null : $this->formatGa($ga);

        $rows = [
            'category' => [
                'label' => 'Recorded Fetal Heartbeat',
                'value' => $categoryValue === '' ? 'Not recorded' : $categoryValue,
            ],
            'ga' => [
                'label' => 'Gestational Age',
                'value' => $gaDisplay === null ? 'Not recorded' : $gaDisplay . ' weeks',
            ],
        ];

        $reasons = [];
        if ($categoryValue === '') {
            $reasons[] = self::REASON_NO_CATEGORY;
        } elseif (!isset(self::IN_WINDOW_STATUSES[$categoryValue])) {
            $reasons[] = self::REASON_UNKNOWN;
        }

        if ($gaFloat === null) {
            $reasons[] = self::REASON_GA_MISSING;
        } elseif ($gaFloat < self::MIN_GA || $gaFloat >= self::MAX_GA_EXCLUSIVE) {
            $reasons[] = strtr(self::REASON_GA_OUTSIDE, ['{ga}' => $gaDisplay]);
        }

        if ($reasons !== []) {
            return new FetalAssessmentOutcome(
                status: 'unable',
                label: self::LABEL_UNABLE,
                rows: $rows,
                recommendation: $this->unableRecommendation($categoryValue),
                reason: implode(' ', $reasons),
                context: self::CONTEXT,
                limitation: self::LIMITATION,
                sources: [self::SOURCE_ISUOG, self::SOURCE_NICE],
                pendingNote: self::PENDING_NOTE,
            );
        }

        return new FetalAssessmentOutcome(
            status: self::IN_WINDOW_STATUSES[$categoryValue],
            label: self::IN_WINDOW_STATUSES[$categoryValue] === 'within'
                ? self::LABEL_WITHIN
                : self::LABEL_REVIEW,
            rows: $rows,
            recommendation: self::IN_WINDOW_RECOMMENDATIONS[$categoryValue],
            context: self::CONTEXT,
            limitation: self::LIMITATION,
            sources: [self::SOURCE_ISUOG, self::SOURCE_NICE],
            pendingNote: self::PENDING_NOTE,
        );
    }

    /**
     * Data consumed by the live update script on Add/Edit Ultrasound. The
     * messages are the exact constants used by assess(), so server and
     * client render identical text.
     *
     * @return array<string, mixed>
     */
    public function clientConfig(): array
    {
        $categories = [];
        foreach (self::IN_WINDOW_STATUSES as $category => $status) {
            $categories[$category] = [
                'status' => $status,
                'recommendation' => self::IN_WINDOW_RECOMMENDATIONS[$category],
            ];
        }

        return [
            'window' => ['min' => self::MIN_GA, 'maxExclusive' => self::MAX_GA_EXCLUSIVE],
            'labels' => [
                'within' => self::LABEL_WITHIN,
                'review' => self::LABEL_REVIEW,
                'unable' => self::LABEL_UNABLE,
            ],
            'chipClass' => FetalAssessmentOutcome::CHIP_CLASSES,
            'categories' => $categories,
            'messages' => [
                'unableBase' => self::REC_UNABLE_BASE,
                'safety' => self::SAFETY_BY_CATEGORY,
                'reasons' => [
                    'noCategory' => self::REASON_NO_CATEGORY,
                    'unknown' => self::REASON_UNKNOWN,
                    'gaMissing' => self::REASON_GA_MISSING,
                    'gaOutside' => self::REASON_GA_OUTSIDE,
                ],
                'notRecorded' => 'Not recorded',
                'gaUnit' => ' weeks',
            ],
            'context' => self::CONTEXT,
            'limitation' => self::LIMITATION,
            'sources' => [self::SOURCE_ISUOG, self::SOURCE_NICE],
            'pendingNote' => self::PENDING_NOTE,
        ];
    }

    private function unableRecommendation(string $categoryValue): string
    {
        $safety = self::SAFETY_BY_CATEGORY[$categoryValue] ?? '';

        return $safety === ''
            ? self::REC_UNABLE_BASE
            : self::REC_UNABLE_BASE . ' ' . $safety;
    }

    private function normalizeCategory(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function parseNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if (!is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return is_finite($number) ? $number : null;
    }

    private function formatGa(mixed $value): string
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return 'Not recorded';
        }

        $text = is_string($value) ? trim($value) : (string) $value;

        if (str_contains($text, '.')) {
            $text = rtrim(rtrim($text, '0'), '.');
        }

        return $text;
    }
}
