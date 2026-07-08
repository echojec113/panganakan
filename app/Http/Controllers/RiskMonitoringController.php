<?php

namespace App\Http\Controllers;

use App\Models\PrenatalVisit;
use App\Models\Patient;
use Illuminate\Http\Request;

class RiskMonitoringController extends Controller
{
    public function index(Request $request)
    {
        $query = PrenatalVisit::with('patient')->latest();

        $query->whereHas('patient', function ($q) {
            $q->whereIn('status', ['ONGOING', 'DELIVERED', 'REFERRED']);
        });
        
        // Apply risk filter if specified
        if ($request->risk_filter && in_array($request->risk_filter, ['HIGH', 'LOW'])) {
            $query->where('risk_level', $request->risk_filter);
        } else {
            // Default: show all, but we'll highlight high risk first
            $query->orderByRaw("CASE WHEN risk_level = 'HIGH' THEN 0 WHEN risk_level = 'LOW' THEN 1 ELSE 2 END");
        }
        
        // 🔍 SEARCH
        if ($request->search) {
            $query->whereHas('patient', function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->search . '%')
                  ->orWhere('last_name', 'like', '%' . $request->search . '%')
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ['%' . $request->search . '%']);
            });
        }
        
        // Get paginated results
        $highRiskVisits = $query->paginate(15);
        
        // Counts for summary cards
        $highRiskCount = PrenatalVisit::where('risk_level', 'HIGH')->count();
        $lowRiskCount = PrenatalVisit::where('risk_level', 'LOW')->count();
        $totalPatients = Patient::count();
        
        return view('risk.monitoring', compact(
            'highRiskVisits',
            'highRiskCount',
            'lowRiskCount',
            'totalPatients'
        ));
    }
}