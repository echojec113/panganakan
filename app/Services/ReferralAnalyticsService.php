<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReferralAnalyticsService extends AnalyticsService
{
    private const TOP_LIMIT = 8;

    public function get(?int $month = null): array
    {
        $year = (int) Carbon::now()->year;

        $query = DB::table('referrals')->whereYear('referral_date', $year);

        if ($month !== null) {
            $query->whereMonth('referral_date', $month);
        }

        $windowRows = $query->get(['referral_date', 'status', 'referred_to', 'reason'])->all();

        $groups = [];

        foreach ($windowRows as $row) {
            $key = Carbon::parse($row->referral_date)->format('Y-m');
            $groups[$key][] = $row;
        }

        $buckets = $this->monthBuckets($year, $month);
        $keys = $buckets['keys'];
        $labels = $buckets['labels'];

        $referralTrend = [];
        $pendingTrend = [];
        $completedTrend = [];

        foreach ($keys as $key) {
            $total = 0;
            $pending = 0;
            $completed = 0;

            foreach ($groups[$key] ?? [] as $row) {
                $total++;

                if ($row->status === 'Pending') {
                    $pending++;
                } elseif ($row->status === 'Completed') {
                    $completed++;
                }
            }

            $referralTrend[] = $total;
            $pendingTrend[] = $pending;
            $completedTrend[] = $completed;
        }

        $destinations = $this->groupedTop(collect($windowRows)->pluck('referred_to')->all(), self::TOP_LIMIT);
        $reasons = $this->groupedTop(collect($windowRows)->pluck('reason')->all(), self::TOP_LIMIT);

        return [
            'year' => $year,
            'month' => $month,
            'labels' => $labels,
            'referralTrend' => $referralTrend,
            'statusTrend' => [
                'pending' => $pendingTrend,
                'completed' => $completedTrend,
            ],
            'destinations' => $destinations,
            'reasons' => $reasons,
            'summary' => [
                'mostReferredHospital' => $destinations[0] ?? null,
                'completionRate' => $this->completionRate($windowRows),
                'busiestPeriod' => $this->maxPeriod($labels, $referralTrend),
                'mostCommonReason' => $reasons[0] ?? null,
            ],
        ];
    }

    private function completionRate(array $rows): float
    {
        $pending = 0;
        $completed = 0;

        foreach ($rows as $row) {
            if ($row->status === 'Pending') {
                $pending++;
            } elseif ($row->status === 'Completed') {
                $completed++;
            }
        }

        $total = $pending + $completed;

        return $total > 0 ? round(($completed / $total) * 100, 1) : 0.0;
    }
}
