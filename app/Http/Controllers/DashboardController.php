<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PrenatalVisit;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // ======================
        // KPI DATA
        // ======================

        // Total patients
        $totalPatients = Patient::count();

        // Risk counts
        $highRisk = PrenatalVisit::where('risk_level', 'HIGH')->count();
        $lowRisk = PrenatalVisit::where('risk_level', 'LOW')->count();

        // ======================
        // NEW KPI LOGIC (FIXED)
        // ======================

        // Active pregnancies (simple logic)
        $activePregnancies = Patient::where('status', 'ONGOING')->count();

        // Upcoming appointments (based on next_visit_date)
        $upcomingAppointments = PrenatalVisit::whereDate('next_visit_date', '>=', Carbon::today())
            ->count();

        // ======================
        // CONDITION COUNTS
        // ======================

        $hypertensionCount = PrenatalVisit::where('hypertension', 1)->count();
        $diabetesCount = PrenatalVisit::where('diabetes', 1)->count();
        $anemiaCount = PrenatalVisit::where('anemia', 1)->count();

        // ======================
        // TREND DATA (MONTHLY)
        // ======================

        $trend = PrenatalVisit::selectRaw('MONTH(visit_date) as month, COUNT(*) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $trendLabels = $trend->pluck('month'); // 1–12
        $trendData = $trend->pluck('total');

        // ======================
        // HIGH RISK PATIENTS
        // ======================

        $highRiskPatients = PrenatalVisit::with('patient')
            ->where('risk_level', 'HIGH')
            ->latest()
            ->take(5)
            ->get();

            // ======================
// SMART RECOMMENDATIONS
// ======================

$recommendations = [];

// High-risk alert
if ($highRisk > 0) {
    $recommendations[] = "There are high-risk pregnancies. Immediate monitoring is recommended.";
}

// Hypertension insight
if ($hypertensionCount > 5) {
    $recommendations[] = "Hypertension is common among patients. Monitor blood pressure regularly.";
}

// Diabetes insight
if ($diabetesCount > 5) {
    $recommendations[] = "Several patients show signs of diabetes. Consider dietary management.";
}

// Anemia insight
if ($anemiaCount > 5) {
    $recommendations[] = "Anemia cases detected. Iron supplementation may be required.";
}

// Default fallback
if (empty($recommendations)) {
    $recommendations[] = "All patients are currently stable. Continue regular prenatal monitoring.";
}

        // ======================
        // RETURN VIEW
        // ======================
        // ======================
// RECENT PRENATAL VISITS
// ======================

$recentVisits = PrenatalVisit::with('patient')
    ->latest()
    ->take(10)
    ->get();

        return view('dashboard', compact(
    'totalPatients',
    'highRisk',
    'lowRisk',
    'activePregnancies',
    'upcomingAppointments',
    'hypertensionCount',
    'diabetesCount',
    'anemiaCount',
    'trendLabels',
    'trendData',
    'highRiskPatients',
    'recommendations',   
    'recentVisits'
));
    }
}