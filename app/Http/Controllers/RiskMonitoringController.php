<?php

namespace App\Http\Controllers;

use App\Models\PrenatalVisit;
use App\Models\Patient;
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

    private function latestVisitSubquery(): \Illuminate\Database\Query\Builder
    {
        return PrenatalVisit::latestAssessmentIds();
    }

    public function index(Request $request)
    {
        $visits = $this->riskMonitoringData->visits($request);

        // Latest-visit-per-patient counts
        $baseLatest = PrenatalVisit::whereIn('id', $this->latestVisitSubquery());
        $highRiskCount = (clone $baseLatest)->where('risk_level', 'HIGH')->count();
        $lowRiskCount = (clone $baseLatest)->where('risk_level', 'LOW')->count();
        $incompleteCount = (clone $baseLatest)->where('risk_level', 'ASSESSMENT INCOMPLETE')->count();
        $urgentBpCount = (clone $baseLatest)->where('urgency', 'URGENT_CLINICAL_REVIEW')->count();
        $pendingRepeatCount = (clone $baseLatest)->where('bp_verification_status', 'PENDING_REPEAT')->count();
        $totalPatients = Patient::count();

        $analytics = $this->riskAnalytics->get(
        $request->filled('year') ? (int) $request->year : null,
        $this->riskMonitoringData->monthFilter($request->month),
        $this->riskMonitoringData->riskTypeFilter($request->risk_type)
        );

        return view('risk.monitoring', compact(
            'visits',
            'highRiskCount',
            'lowRiskCount',
            'incompleteCount',
            'urgentBpCount',
            'pendingRepeatCount',
            'totalPatients',
            'analytics'
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