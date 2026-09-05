<?php

namespace App\Http\Controllers;

use App\Models\PrenatalVisit;
use App\Models\Patient;
use App\Services\RiskAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiskMonitoringController extends Controller
{
    public function __construct(private RiskAnalyticsService $riskAnalytics)
    {
    }

    /**
     * Normalize the month filter: 'all' or a missing/invalid value becomes
     * null (All Months); integers 1–12 are kept. Anything else defaults to
     * All Months so invalid input never reaches the analytics queries.
     */
    private function monthFilter($value): ?int
    {
        if ($value === 'all' || $value === null || $value === '') {
            return null;
        }

        $month = (int) $value;

        if ($month < 1 || $month > 12) {
            return null;
        }

        return $month;
    }

    /**
     * Normalize the analytics risk type filter: only HIGH and LOW are
     * allowed. Anything else defaults to HIGH so invalid input never reaches
     * the analytics queries.
     */
    private function riskTypeFilter($value): string
    {
        if ($value === 'LOW') {
            return 'LOW';
        }

        return 'HIGH';
    }

    private function latestVisitSubquery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('prenatal_visits')
            ->whereNull('deleted_at')
            ->selectRaw('MAX(id) as id')
            ->groupBy('patient_id');
    }

    public function index(Request $request)
    {
        $latestIds = $this->latestVisitSubquery();

        $query = PrenatalVisit::with('patient.referrals')
            ->whereIn('id', $latestIds);

        $query->whereHas('patient', function ($q) {
            $q->whereIn('status', ['ONGOING', 'DELIVERED', 'REFERRED']);
        });

        // Apply risk filter if specified
        $allowedRiskFilters = ['HIGH', 'LOW', 'ASSESSMENT INCOMPLETE'];
        if ($request->risk_filter && in_array($request->risk_filter, $allowedRiskFilters)) {
            $query->where('risk_level', $request->risk_filter);
        } else {
            $query->orderByRaw("CASE WHEN risk_level = 'HIGH' THEN 0 WHEN risk_level = 'LOW' THEN 1 ELSE 2 END");
        }

        // Apply decision source filter
        $allowedSources = ['COMPLETENESS', 'RULE_BASED', 'MACHINE_LEARNING', 'MACHINE_LEARNING_INVALID'];
        if ($request->decision_source && in_array($request->decision_source, $allowedSources)) {
            $query->where('decision_source', $request->decision_source);
        }

        // Apply urgency filter
        $allowedUrgencies = ['URGENT_CLINICAL_REVIEW', 'PROMPT'];
        if ($request->urgency && in_array($request->urgency, $allowedUrgencies)) {
            $query->where('urgency', $request->urgency);
        }

        // Apply BP verification status filter
        $allowedVerificationStatuses = ['PENDING_REPEAT', 'REPEAT_COMPLETED', 'UNABLE_TO_REPEAT', 'NOT_REQUIRED'];
        if ($request->bp_verification_status && in_array($request->bp_verification_status, $allowedVerificationStatuses)) {
            $query->where('bp_verification_status', $request->bp_verification_status);
        }

        // SEARCH
        if ($request->search) {
            $query->whereHas('patient', function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->search . '%')
                  ->orWhere('last_name', 'like', '%' . $request->search . '%')
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ['%' . $request->search . '%']);
            });
        }

        // Get paginated results
        $visits = $query->paginate(15);

        // Latest-visit-per-patient counts
        $baseLatest = PrenatalVisit::whereIn('id', $this->latestVisitSubquery());
        $highRiskCount = (clone $baseLatest)->where('risk_level', 'HIGH')->count();
        $lowRiskCount = (clone $baseLatest)->where('risk_level', 'LOW')->count();
        $incompleteCount = (clone $baseLatest)->where('risk_level', 'ASSESSMENT INCOMPLETE')->count();
        $urgentBpCount = (clone $baseLatest)->where('urgency', 'URGENT_CLINICAL_REVIEW')->count();
        $pendingRepeatCount = (clone $baseLatest)->where('bp_verification_status', 'PENDING_REPEAT')->count();
        $totalPatients = Patient::count();

        $analytics = $this->riskAnalytics->get(
            $this->monthFilter($request->month),
            $this->riskTypeFilter($request->risk_type)
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
            $this->monthFilter($request->month),
            $this->riskTypeFilter($request->risk_type)
        ));
    }
}