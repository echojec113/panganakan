<?php

namespace App\Http\Controllers;

use App\Models\PrenatalVisit;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiskMonitoringController extends Controller
{
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

        $query = PrenatalVisit::with('patient')
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
        $totalPatients = Patient::count();

        return view('risk.monitoring', compact(
            'visits',
            'highRiskCount',
            'lowRiskCount',
            'incompleteCount',
            'totalPatients'
        ));
    }
}