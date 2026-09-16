<?php

namespace App\Services;

use App\ValueObjects\ClinicalFactorEvidence;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RiskAnalyticsService extends AnalyticsService
{
    private const RISK_HIGH = 'HIGH';
    private const RISK_LOW = 'LOW';
    private const RISK_INCOMPLETE = 'ASSESSMENT INCOMPLETE';

    private const URGENCY_URGENT = 'URGENT_CLINICAL_REVIEW';
    private const VERIFICATION_PENDING = 'PENDING_REPEAT';
    private const VERIFICATION_COMPLETED = 'REPEAT_COMPLETED';

    private const CLEARED_SYS_THRESHOLD = 140;
    private const CLEARED_DIA_THRESHOLD = 90;

    public function get(?int $month = null, ?string $riskType = null): array
    {
        $riskType = in_array($riskType, [self::RISK_HIGH, self::RISK_LOW], true) ? $riskType : self::RISK_HIGH;

        $year = (int) Carbon::now()->year;
        $rows = $this->latestAssessments($year, $month);

        $buckets = $this->monthBuckets($year, $month);
        $keys = $buckets['keys'];
        $labels = $buckets['labels'];

        $groups = [];

        foreach ($rows as $row) {
            $key = Carbon::parse($row->visit_date)->format('Y-m');
            $groups[$key][] = $row;
        }

        $highRiskTrend = [];
        $lowRiskTrend = [];
        $distribution = ['high' => [], 'low' => [], 'incomplete' => []];
        $conditions = ['Hypertension' => [], 'Diabetes' => [], 'Anemia' => []];
        $bpFollowUp = ['urgent' => [], 'pendingRepeat' => [], 'cleared' => []];
        $ageGroups = [
            'Under 19' => 0,
            '19-24' => 0,
            '25-34' => 0,
            '35-44' => 0,
            '45 and older' => 0,
        ];
        $highRiskFactors = [];

        foreach ($keys as $key) {
            $high = 0;
            $low = 0;
            $incomplete = 0;
            $hypertension = 0;
            $diabetes = 0;
            $anemia = 0;
            $urgent = 0;
            $pendingRepeat = 0;
            $cleared = 0;

            foreach ($groups[$key] ?? [] as $row) {
                if ($row->risk_level === self::RISK_HIGH) {
                    $high++;
                } elseif ($row->risk_level === self::RISK_LOW) {
                    $low++;
                } elseif ($row->risk_level === self::RISK_INCOMPLETE) {
                    $incomplete++;
                }

                if ((bool) $row->hypertension) {
                    $hypertension++;
                }

                if ((bool) $row->diabetes) {
                    $diabetes++;
                }

                if ((bool) $row->anemia) {
                    $anemia++;
                }

                $age = is_numeric($row->age) ? (int) $row->age : null;
                if ($age !== null) {
                    $ageGroup = match (true) {
                        $age < 19 => 'Under 19',
                        $age <= 24 => '19-24',
                        $age <= 34 => '25-34',
                        $age <= 44 => '35-44',
                        default => '45 and older',
                    };
                    $ageGroups[$ageGroup]++;
                }

                if ($row->risk_level === self::RISK_HIGH) {
                    $storedFactors = is_string($row->factor_evidence)
                        ? json_decode($row->factor_evidence, true)
                        : $row->factor_evidence;
                    foreach (ClinicalFactorEvidence::normalizeList($storedFactors) as $factor) {
                        $label = trim((string) ($factor['label'] ?? ''));
                        if ($label === '') {
                            continue;
                        }
                        $key = mb_strtolower($label);
                        if (!isset($highRiskFactors[$key])) {
                            $highRiskFactors[$key] = ['label' => $label, 'count' => 0];
                        }
                        $highRiskFactors[$key]['count']++;
                    }
                }

                if ($row->urgency === self::URGENCY_URGENT) {
                    $urgent++;
                }

                if ($row->bp_verification_status === self::VERIFICATION_PENDING) {
                    $pendingRepeat++;
                }

                if ($row->bp_verification_status === self::VERIFICATION_COMPLETED
                    && is_numeric($row->repeat_bp_sys)
                    && is_numeric($row->repeat_bp_dia)
                    && (int) $row->repeat_bp_sys < self::CLEARED_SYS_THRESHOLD
                    && (int) $row->repeat_bp_dia < self::CLEARED_DIA_THRESHOLD) {
                    $cleared++;
                }
            }

            $highRiskTrend[] = $high;
            $lowRiskTrend[] = $low;
            $distribution['high'][] = $high;
            $distribution['low'][] = $low;
            $distribution['incomplete'][] = $incomplete;
            $conditions['Hypertension'][] = $hypertension;
            $conditions['Diabetes'][] = $diabetes;
            $conditions['Anemia'][] = $anemia;
            $bpFollowUp['urgent'][] = $urgent;
            $bpFollowUp['pendingRepeat'][] = $pendingRepeat;
            $bpFollowUp['cleared'][] = $cleared;
        }

        return [
            'year' => $year,
            'month' => $month,
            'riskType' => $riskType,
            'labels' => $labels,
            'riskTrend' => $riskType === self::RISK_LOW ? $lowRiskTrend : $highRiskTrend,
            'highRiskTrend' => $highRiskTrend,
            'lowRiskTrend' => $lowRiskTrend,
            'riskDistribution' => $distribution,
            'conditions' => $conditions,
            'bpFollowUp' => $bpFollowUp,
            'ageDistribution' => [
                'labels' => array_keys($ageGroups),
                'data' => array_values($ageGroups),
            ],
            'topHighRiskConditions' => $this->topHighRiskFactors($highRiskFactors),
            'summary' => [
                'highestHighRiskPeriod' => $this->maxPeriod($labels, $highRiskTrend),
                'mostCommonCondition' => $this->mostCommonCondition($conditions),
            ],
        ];
    }

    /**
     * One row per patient: the latest non-deleted assessment within the
     * selected month (and current year).
     */
    private function latestAssessments(int $year, ?int $month): array
    {
        $latestIds = DB::table('prenatal_visits')
            ->whereNull('deleted_at')
            ->selectRaw('MAX(id) as id')
            ->groupBy('patient_id');

        $query = DB::table('prenatal_visits')
            ->join('patients', 'patients.id', '=', 'prenatal_visits.patient_id')
            ->whereIn('prenatal_visits.id', $latestIds)
            ->whereNull('patients.deleted_at')
            ->whereYear('visit_date', $year);

        if ($month !== null) {
            $query->whereMonth('visit_date', $month);
        }

        return $query->get([
            'visit_date',
            'risk_level',
            'urgency',
            'bp_verification_status',
            'repeat_bp_sys',
            'repeat_bp_dia',
            'hypertension',
            'diabetes',
            'anemia',
            'patients.age',
            'factor_evidence',
        ])->all();
    }

    /**
     * Return the five most common persisted clinical factors among HIGH
     * assessments, with ties resolved alphabetically for stable charts.
     *
     * @param array<string, array{label: string, count: int}> $factors
     * @return array<int, array{label: string, count: int}>
     */
    private function topHighRiskFactors(array $factors): array
    {
        $items = array_values($factors);

        usort($items, function (array $a, array $b): int {
            return $b['count'] <=> $a['count'] ?: strcmp($a['label'], $b['label']);
        });

        return array_slice($items, 0, 5);
    }

    /**
     * Condition totals within the monthly window, for the summary card.
     */
    private function mostCommonCondition(array $conditions): ?array
    {
        $totals = [];

        foreach ($conditions as $name => $counts) {
            $totals[] = ['name' => $name, 'count' => array_sum($counts)];
        }

        return $this->mostCommon($totals, 'name');
    }
}
