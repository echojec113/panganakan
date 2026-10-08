<?php

declare(strict_types=1);

namespace App\Services;

use App\ValueObjects\FetalAssessmentOutcome;

final class FetalWeightAssessmentService
{
    /**
     * Display-only assessment of the staff-entered estimated fetal weight
     * against WHO Table 11 (growth chart for estimated fetal weight
     * regardless of fetal sex), Kiserud et al., PLoS Med 2017;14(1):e1002220,
     * https://pmc.ncbi.nlm.nih.gov/articles/PMC5261648/
     *
     * The published reference covers 14+0 to 40+0 weeks only. Fractional
     * gestational ages are linearly interpolated between the published weekly
     * anchors. Classification uses full-precision thresholds; whole-gram
     * rounding is applied for display only. Nothing is persisted: the
     * staff-entered weight and gestational age are never modified.
     */
    public const MIN_GA = 14.0;

    public const MAX_GA = 40.0;

    public const SOURCE_URL = 'https://pmc.ncbi.nlm.nih.gov/articles/PMC5261648/';

    public const SOURCE_LABEL = 'WHO Fetal Growth Charts (Kiserud et al., PLoS Med 2017;14(1):e1002220), Table 11';

    public const PENDING_NOTE = 'Recommendation text pending review by the clinic’s qualified healthcare professional.';

    public const FOOTNOTE = 'Percentile thresholds are classified at full precision by linear interpolation between published WHO weekly values; gram figures in this panel are rounded for display only.';

    public const LABEL_BELOW = 'Below Expected';

    public const LABEL_WITHIN = 'Within Expected Range';

    public const LABEL_ABOVE = 'Above Expected Range';

    public const LABEL_UNABLE = 'Unable to Assess';

    public const REC_BELOW = 'Recorded weight is below the WHO expected reference range (10th–90th percentile) for {ga} weeks of gestation. Review the recorded measurement with a qualified healthcare professional; this display-only panel never modifies the saved value and is not a diagnosis.';

    public const REC_WITHIN = 'Recorded weight is within the WHO expected reference range (10th–90th percentile) for {ga} weeks of gestation. Continue routine antenatal care as advised by a qualified healthcare professional; this display-only panel never modifies the saved value.';

    public const REC_ABOVE = 'Recorded weight is above the WHO expected reference range (10th–90th percentile) for {ga} weeks of gestation. Review the recorded measurement with a qualified healthcare professional; this display-only panel never modifies the saved value and is not a diagnosis.';

    public const REC_UNABLE = 'No automated assessment can be given for this record. Review with a qualified healthcare professional; this display-only panel never modifies the saved value.';

    public const REASON_GA_MISSING = 'Gestational age is missing or invalid, so the WHO reference cannot be applied.';

    public const REASON_GA_OUTSIDE = 'Gestational age {ga} weeks is outside the supported WHO reference range (14+0 to 40+0 weeks); no validated WHO value exists for interpolation or extrapolation beyond it.';

    public const REASON_WEIGHT_MISSING = 'Estimated fetal weight is missing or invalid, so no comparison can be made.';

    public const CLARIFY_BELOW = 'Precision note: the exact 10th-percentile threshold is {p10} g while the range above is rounded to whole grams ({p10r} g); the recorded {weight} g is below {p10} g, so it is classified Below Expected.';

    public const CLARIFY_ABOVE_ROUND = 'Precision note: the exact 90th-percentile threshold is {p90} g while the range above is rounded to whole grams ({p90r} g); the recorded {weight} g meets or exceeds {p90} g, so it is classified Above Expected.';

    public const CLARIFY_ABOVE_EQ = 'Precision note: the recorded {weight} g is exactly the 90th-percentile threshold ({p90} g); classification rule: at or above the 90th percentile is classified Above Expected.';

    /**
     * WHO Table 11: estimated fetal weight (g) at percentiles 10, 50 and 90
     * for each completed gestational week from 14 through 40, regardless of
     * fetal sex. Verified against the published table row by row.
     *
     * @var array<int, array{10: int, 50: int, 90: int}>
     */
    public const TABLE = [
        14 => [10 => 78, 50 => 90, 90 => 104],
        15 => [10 => 99, 50 => 114, 90 => 132],
        16 => [10 => 124, 50 => 144, 90 => 166],
        17 => [10 => 155, 50 => 179, 90 => 207],
        18 => [10 => 192, 50 => 222, 90 => 255],
        19 => [10 => 235, 50 => 272, 90 => 313],
        20 => [10 => 286, 50 => 330, 90 => 380],
        21 => [10 => 345, 50 => 398, 90 => 458],
        22 => [10 => 412, 50 => 476, 90 => 548],
        23 => [10 => 489, 50 => 565, 90 => 650],
        24 => [10 => 576, 50 => 665, 90 => 765],
        25 => [10 => 673, 50 => 778, 90 => 894],
        26 => [10 => 780, 50 => 902, 90 => 1038],
        27 => [10 => 898, 50 => 1039, 90 => 1196],
        28 => [10 => 1026, 50 => 1189, 90 => 1368],
        29 => [10 => 1165, 50 => 1350, 90 => 1554],
        30 => [10 => 1313, 50 => 1523, 90 => 1753],
        31 => [10 => 1470, 50 => 1707, 90 => 1964],
        32 => [10 => 1635, 50 => 1901, 90 => 2187],
        33 => [10 => 1807, 50 => 2103, 90 => 2419],
        34 => [10 => 1985, 50 => 2312, 90 => 2659],
        35 => [10 => 2167, 50 => 2527, 90 => 2904],
        36 => [10 => 2352, 50 => 2745, 90 => 3153],
        37 => [10 => 2537, 50 => 2966, 90 => 3403],
        38 => [10 => 2723, 50 => 3186, 90 => 3652],
        39 => [10 => 2905, 50 => 3403, 90 => 3897],
        40 => [10 => 3084, 50 => 3617, 90 => 4135],
    ];

    /**
     * Percentile value at a gestational age inside [MIN_GA, MAX_GA].
     * Anchors return the published integer; fractional weeks are linearly
     * interpolated between the surrounding published weeks.
     */
    public function percentile(float $ga, int $percentile): float
    {
        $lower = (int) floor($ga);
        $upper = (int) ceil($ga);

        if ($lower === $upper) {
            return (float) self::TABLE[$lower][$percentile];
        }

        $fraction = $ga - $lower;

        return self::TABLE[$lower][$percentile]
            + $fraction * (self::TABLE[$upper][$percentile] - self::TABLE[$lower][$percentile]);
    }

    /**
     * Display-only assessment. Never writes or transforms stored values.
     */
    public function assess(mixed $weight, mixed $ga): FetalAssessmentOutcome
    {
        $gaFloat = $this->parseNumber($ga);
        $weightFloat = $this->parseNumber($weight);

        $reasons = [];
        if ($gaFloat === null) {
            $reasons[] = self::REASON_GA_MISSING;
        } elseif ($gaFloat < self::MIN_GA || $gaFloat > self::MAX_GA) {
            $reasons[] = strtr(self::REASON_GA_OUTSIDE, ['{ga}' => $this->formatGa($ga)]);
        }
        if ($weightFloat === null) {
            $reasons[] = self::REASON_WEIGHT_MISSING;
        }

        $rows = [
            'weight' => [
                'label' => 'Recorded Fetal Weight',
                'value' => $weightFloat === null ? 'Not recorded' : $this->formatWeight($weight) . ' g',
            ],
            'ga' => [
                'label' => 'Gestational Age',
                'value' => $gaFloat === null ? 'Not recorded' : $this->formatGa($ga) . ' weeks',
            ],
            'range' => [
                'label' => 'WHO Expected Range (P10–P90)',
                'value' => 'Not available',
            ],
            'median' => [
                'label' => 'WHO Median (P50)',
                'value' => 'Not available',
            ],
        ];

        if ($reasons !== []) {
            return new FetalAssessmentOutcome(
                status: 'unable',
                label: self::LABEL_UNABLE,
                rows: $rows,
                recommendation: self::REC_UNABLE,
                reason: implode(' ', $reasons),
                footnote: self::FOOTNOTE,
                sources: [$this->source()],
                pendingNote: self::PENDING_NOTE,
            );
        }

        $p10 = $this->percentile($gaFloat, 10);
        $p50 = $this->percentile($gaFloat, 50);
        $p90 = $this->percentile($gaFloat, 90);

        $rows['range']['value'] = number_format(round($p10)) . ' g – ' . number_format(round($p90)) . ' g';
        $rows['median']['value'] = number_format(round($p50)) . ' g';

        $gaDisplay = $this->formatGa($ga);

        if ($weightFloat < $p10) {
            $status = 'below';
            $label = self::LABEL_BELOW;
            $recommendation = strtr(self::REC_BELOW, ['{ga}' => $gaDisplay]);
            $clarification = null;
            if ($p10 !== round($p10) && $weightFloat === round($p10)) {
                $clarification = strtr(self::CLARIFY_BELOW, [
                    '{p10}' => $this->formatThreshold($p10),
                    '{p10r}' => number_format(round($p10)),
                    '{weight}' => number_format(round($weightFloat)),
                ]);
            }
        } elseif ($weightFloat < $p90) {
            $status = 'within';
            $label = self::LABEL_WITHIN;
            $recommendation = strtr(self::REC_WITHIN, ['{ga}' => $gaDisplay]);
            $clarification = null;
        } else {
            $status = 'above';
            $label = self::LABEL_ABOVE;
            $recommendation = strtr(self::REC_ABOVE, ['{ga}' => $gaDisplay]);
            $clarification = null;
            if ($p90 !== round($p90) && $weightFloat === round($p90)) {
                $clarification = strtr(self::CLARIFY_ABOVE_ROUND, [
                    '{p90}' => $this->formatThreshold($p90),
                    '{p90r}' => number_format(round($p90)),
                    '{weight}' => number_format(round($weightFloat)),
                ]);
            } elseif ($weightFloat === $p90) {
                $clarification = strtr(self::CLARIFY_ABOVE_EQ, [
                    '{p90}' => $this->formatThreshold($p90),
                    '{weight}' => number_format(round($weightFloat)),
                ]);
            }
        }

        return new FetalAssessmentOutcome(
            status: $status,
            label: $label,
            rows: $rows,
            recommendation: $recommendation,
            clarification: $clarification,
            footnote: self::FOOTNOTE,
            sources: [$this->source()],
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
        return [
            'window' => ['min' => self::MIN_GA, 'max' => self::MAX_GA],
            'table' => self::TABLE,
            'labels' => [
                'below' => self::LABEL_BELOW,
                'within' => self::LABEL_WITHIN,
                'above' => self::LABEL_ABOVE,
                'unable' => self::LABEL_UNABLE,
            ],
            'chipClass' => FetalAssessmentOutcome::CHIP_CLASSES,
            'messages' => [
                'below' => self::REC_BELOW,
                'within' => self::REC_WITHIN,
                'above' => self::REC_ABOVE,
                'unable' => self::REC_UNABLE,
                'reasons' => [
                    'gaMissing' => self::REASON_GA_MISSING,
                    'gaOutside' => self::REASON_GA_OUTSIDE,
                    'weightMissing' => self::REASON_WEIGHT_MISSING,
                ],
                'clarify' => [
                    'below' => self::CLARIFY_BELOW,
                    'aboveRound' => self::CLARIFY_ABOVE_ROUND,
                    'aboveEq' => self::CLARIFY_ABOVE_EQ,
                ],
                'footnote' => self::FOOTNOTE,
                'notRecorded' => 'Not recorded',
                'notAvailable' => 'Not available',
                'weightUnit' => ' g',
                'gaUnit' => ' weeks',
            ],
            'sources' => [$this->source()],
            'pendingNote' => self::PENDING_NOTE,
        ];
    }

    /**
     * @return array{label: string, url: string}
     */
    public function source(): array
    {
        return ['label' => self::SOURCE_LABEL, 'url' => self::SOURCE_URL];
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

    private function formatWeight(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Not recorded';
        }

        if (is_string($value)) {
            $value = trim($value);
            if (!is_numeric($value)) {
                return $value === '' ? 'Not recorded' : $value;
            }
        }

        if (!is_numeric($value)) {
            return 'Not recorded';
        }

        $number = (float) $value;

        if (floor($number) === $number) {
            return number_format($number);
        }

        return rtrim(rtrim(number_format($number, 4, '.', ','), '0'), '.');
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

    private function formatThreshold(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ','), '0'), '.');
    }
}
