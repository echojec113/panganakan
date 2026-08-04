<?php

namespace App\Services;

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

    public function get(?int $month = null): array
    {
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
        $distribution = ['high' => [], 'low' => [], 'incomplete' => []];
        $conditions = ['Hypertension' => [], 'Diabetes' => [], 'Anemia' => []];
        $bpFollowUp = ['urgent' => [], 'pendingRepeat' => [], 'cleared' => []];

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
            'labels' => $labels,
            'highRiskTrend' => $highRiskTrend,
            'riskDistribution' => $distribution,
            'conditions' => $conditions,
            'bpFollowUp' => $bpFollowUp,
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
            ->whereIn('id', $latestIds)
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
        ])->all();
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
