<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReferralAnalyticsService extends AnalyticsService
{
    private const TOP_LIMIT = 8;

    /**
     * Build referral analytics for a selected year and optional month.
     *
     * - No month selected: analytics cover the entire selected year
     *   and the referral trend is grouped by month.
     *
     * - Month selected: analytics cover only that month
     *   and the referral trend is grouped by day.
     */
    public function get(?int $year = null, ?int $month = null): array
    {
        $year = $year ?? (int) Carbon::now()->year;

        /*
         |--------------------------------------------------------------------------
         | Referral Window
         |--------------------------------------------------------------------------
         |
         | Retrieve only referrals belonging to the selected year.
         | If a month is selected, narrow the dataset to that month.
         |
         */

        $query = DB::table('referrals')
            ->whereYear('referral_date', $year);

        if ($month !== null) {
            $query->whereMonth('referral_date', $month);
        }

        $windowRows = $query
            ->get([
                'referral_date',
                'status',
                'referred_to',
            ])
            ->all();


        /*
         |--------------------------------------------------------------------------
         | Referral Trend
         |--------------------------------------------------------------------------
         |
         | All Months:
         |   Jan, Feb, Mar ... Dec
         |
         | Specific Month:
         |   Sep 1, Sep 2, Sep 3 ... Sep 30
         |
         */

        $trend = $month === null
            ? $this->buildMonthlyTrend($windowRows, $year)
            : $this->buildDailyTrend($windowRows, $year, $month);


        /*
         |--------------------------------------------------------------------------
         | Referral Status
         |--------------------------------------------------------------------------
         |
         | Count every valid referral status in the selected time window.
         |
         */

        $statusCounts = [
            'pending' => 0,
            'completed' => 0,
            'refused' => 0,
            'cancelled' => 0,
        ];

        foreach ($windowRows as $row) {
            switch ($row->status) {
                case 'Pending':
                    $statusCounts['pending']++;
                    break;

                case 'Completed':
                    $statusCounts['completed']++;
                    break;

                case 'Refused':
                    $statusCounts['refused']++;
                    break;

                case 'Cancelled':
                    $statusCounts['cancelled']++;
                    break;
            }
        }


        /*
         |--------------------------------------------------------------------------
         | Top Referral Destinations
         |--------------------------------------------------------------------------
         */

        $destinations = $this->groupedTop(
            collect($windowRows)
                ->pluck('referred_to')
                ->all(),
            self::TOP_LIMIT
        );


        /*
         |--------------------------------------------------------------------------
         | Summary
         |--------------------------------------------------------------------------
         */

        $totalReferrals = count($windowRows);

        $completedReferrals = $statusCounts['completed'];

        $mostReferredFacility = $destinations[0] ?? null;

        $busiestPeriod = $this->maxPeriod(
            $trend['labels'],
            $trend['data']
        );


        /*
         |--------------------------------------------------------------------------
         | Available Years
         |--------------------------------------------------------------------------
         |
         | Used later by the Year dropdown.
         |
         | Include years that actually contain referral records.
         | Also include the current year even when it has no referrals yet.
         |
         */

        $availableYears = DB::table('referrals')
            ->whereNotNull('referral_date')
            ->selectRaw('YEAR(referral_date) as year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($value) => (int) $value)
            ->all();

        $currentYear = (int) Carbon::now()->year;

        if (!in_array($currentYear, $availableYears, true)) {
            $availableYears[] = $currentYear;
            rsort($availableYears);
        }


        /*
         |--------------------------------------------------------------------------
         | Analytics Payload
         |--------------------------------------------------------------------------
         */

        return [
            'year' => $year,
            'month' => $month,

            'availableYears' => $availableYears,

            'trend' => [
                'granularity' => $month === null ? 'month' : 'day',
                'labels' => $trend['labels'],
                'data' => $trend['data'],
            ],

            'status' => [
                'pending' => $statusCounts['pending'],
                'completed' => $statusCounts['completed'],
                'refused' => $statusCounts['refused'],
                'cancelled' => $statusCounts['cancelled'],
            ],

            'destinations' => $destinations,

            'summary' => [
                'totalReferrals' => $totalReferrals,
                'completedReferrals' => $completedReferrals,
                'mostReferredFacility' => $mostReferredFacility,
                'busiestPeriod' => $busiestPeriod,
            ],
        ];
    }


    /**
     * Build a January-December referral trend for a selected year.
     */
    private function buildMonthlyTrend(array $rows, int $year): array
    {
        $buckets = $this->monthBuckets($year, null);

        $counts = array_fill(
            0,
            count($buckets['keys']),
            0
        );

        $keyIndexes = array_flip($buckets['keys']);

        foreach ($rows as $row) {
            $key = Carbon::parse($row->referral_date)
                ->format('Y-m');

            if (isset($keyIndexes[$key])) {
                $counts[$keyIndexes[$key]]++;
            }
        }

        return [
            'labels' => $buckets['labels'],
            'data' => $counts,
        ];
    }


    /**
     * Build a day-by-day referral trend for a selected month.
     *
     * The number of days is determined automatically:
     *
     * February -> 28/29
     * April    -> 30
     * January  -> 31
     * etc.
     */
    private function buildDailyTrend(
        array $rows,
        int $year,
        int $month
    ): array {
        $date = Carbon::create($year, $month, 1);

        $daysInMonth = $date->daysInMonth;

        $labels = [];
        $counts = array_fill(0, $daysInMonth, 0);

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $labels[] = Carbon::create(
                $year,
                $month,
                $day
            )->format('M j');
        }

        foreach ($rows as $row) {
            $referralDate = Carbon::parse($row->referral_date);

            $day = (int) $referralDate->day;

            if ($day >= 1 && $day <= $daysInMonth) {
                $counts[$day - 1]++;
            }
        }

        return [
            'labels' => $labels,
            'data' => $counts,
        ];
    }
}