<?php

namespace App\Http\Controllers;

use App\Models\PrenatalVisit;
use App\Services\RiskAnalyticsService;
use App\Services\RiskMonitoringDataService;
use Illuminate\Http\Request;

class RiskMonitoringController extends Controller
{
    public function __construct(
        private RiskAnalyticsService $riskAnalytics,
        private RiskMonitoringDataService $riskMonitoringData
    )
    {
    }

    public function index(Request $request)
    {
        $analytics = $this->riskAnalytics->get(
        $request->filled('year') ? (int) $request->year : null,
        $this->riskMonitoringData->monthFilter($request->month),
        $this->riskMonitoringData->riskTypeFilter($request->risk_type)
        );

        $availableYears = PrenatalVisit::query()
            ->whereNotNull('visit_date')
            ->distinct()
            ->pluck('visit_date')
            ->map(fn ($date) => (int) \Carbon\Carbon::parse($date)->year)
            ->push((int) now()->year, $analytics['year'])
            ->map(fn ($year) => (int) $year)
            ->unique()
            ->sortDesc()
            ->values();

        return view('risk.monitoring', compact(
            'analytics',
            'availableYears'
        ));
    }

    /**
     * JSON analytics payload for the month/risk-type filters (aggregated
     * totals only).
     */
    public function analytics(Request $request)
    {
        return response()->json($this->riskAnalytics->get(
        $request->filled('year') ? (int) $request->year : null,
        $this->riskMonitoringData->monthFilter($request->month),
        $this->riskMonitoringData->riskTypeFilter($request->risk_type)
    ));
    }
}
