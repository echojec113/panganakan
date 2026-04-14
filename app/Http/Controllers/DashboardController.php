<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PrenatalVisit;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // Check user role and return appropriate dashboard
        if (auth()->user()->role === 'admin') {
            return $this->adminDashboard();
        } else {
            return $this->staffDashboard();
        }
    }

    /**
     * Admin Dashboard - Business & Analytics View
     */
    private function adminDashboard()
    {
        // ======================
        // KPI DATA
        // ======================

        $totalPatients = Patient::count();
        $highRisk = PrenatalVisit::where('risk_level', 'HIGH')->count();
        $lowRisk = PrenatalVisit::where('risk_level', 'LOW')->count();
        $activePregnancies = Patient::where('status', 'ONGOING')->count();
        $upcomingAppointments = PrenatalVisit::whereDate('next_visit_date', '>=', Carbon::today())->count();

        // ======================
        // CONDITION COUNTS
        // ======================

        $hypertensionCount = PrenatalVisit::where('hypertension', 1)->count();
        $diabetesCount = PrenatalVisit::where('diabetes', 1)->count();
        $anemiaCount = PrenatalVisit::where('anemia', 1)->count();

        // ======================
        // MONTHLY TREND DATA
        // ======================

        $trend = PrenatalVisit::selectRaw('MONTH(visit_date) as month, COUNT(*) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $trendLabels = $trend->pluck('month');
        $trendData = $trend->pluck('total');

        // ======================
        // GROWTH METRICS
        // ======================

        $currentMonth = Carbon::now()->month;
        $lastMonth = Carbon::now()->subMonth()->month;

        $visitsThisMonth = PrenatalVisit::whereMonth('visit_date', $currentMonth)->count();
        $visitsLastMonth = PrenatalVisit::whereMonth('visit_date', $lastMonth)->count();
        $visitGrowthPercent = $visitsLastMonth > 0 ? round((($visitsThisMonth - $visitsLastMonth) / $visitsLastMonth) * 100, 1) : 0;

        $patientsThisMonth = Patient::whereMonth('created_at', $currentMonth)->count();
        $patientsLastMonth = Patient::whereMonth('created_at', $lastMonth)->count();
        $patientGrowthPercent = $patientsLastMonth > 0 ? round((($patientsThisMonth - $patientsLastMonth) / $patientsLastMonth) * 100, 1) : 0;

        // ======================
        // BUSINESS INSIGHTS
        // ======================

        $insights = $this->generateAdminInsights(
            $highRisk, 
            $hypertensionCount, 
            $diabetesCount, 
            $anemiaCount, 
            $visitGrowthPercent
        );

        // ======================
        // HIGH RISK PATIENTS
        // ======================

        $highRiskPatients = PrenatalVisit::with('patient')
            ->where('risk_level', 'HIGH')
            ->latest()
            ->take(5)
            ->get();

        // ======================
        // MOST COMMON CONDITIONS
        // ======================

        $conditions = collect([
            ['name' => 'Hypertension', 'count' => $hypertensionCount, 'icon' => '🩸'],
            ['name' => 'Diabetes', 'count' => $diabetesCount, 'icon' => '🍬'],
            ['name' => 'Anemia', 'count' => $anemiaCount, 'icon' => '🫀'],
        ])->sortByDesc('count')->take(3);

        return view('dashboards.admin', compact(
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
            'insights',
            'visitGrowthPercent',
            'patientGrowthPercent',
            'conditions',
            'visitsThisMonth'
        ));
    }

    /**
     * Staff Dashboard - Daily Operations & Tasks View
     */
    private function staffDashboard()
    {
        // ======================
        // TODAY'S SUMMARY
        // ======================

        $today = Carbon::today();
        $patientsToday = PrenatalVisit::whereDate('visit_date', $today)->count();
        $appointmentsToday = PrenatalVisit::whereDate('visit_date', $today)
            ->where('status', 'SCHEDULED')->count();
        $pendingCheckups = PrenatalVisit::where('status', 'PENDING')->count();

        // ======================
        // HIGH RISK ALERTS
        // ======================

        $highRiskAlerts = PrenatalVisit::with('patient')
            ->where('risk_level', 'HIGH')
            ->latest()
            ->take(5)
            ->get();

        // ======================
        // UPCOMING APPOINTMENTS (NEXT 7 DAYS)
        // ======================

        $upcomingAppointments = PrenatalVisit::with('patient')
            ->whereBetween('visit_date', [Carbon::today(), Carbon::today()->addDays(7)])
            ->orderBy('visit_date')
            ->get();

        // ======================
        // FOLLOW-UP TASKS
        // ======================

        $followUpTasks = PrenatalVisit::with('patient')
            ->where('status', 'FOLLOW_UP')
            ->orWhere('follow_up_required', 1)
            ->latest()
            ->take(8)
            ->get();

        // ======================
        // TODAY'S QUICK STATS
        // ======================

        $totalPatients = Patient::count();
        $activePatients = Patient::where('status', 'ONGOING')->count();

        // ======================
        // RECENT VISITS (TODAY & YESTERDAY)
        // ======================

        $recentVisits = PrenatalVisit::with('patient')
            ->whereBetween('visit_date', [Carbon::today()->subDay(), Carbon::today()])
            ->latest()
            ->take(10)
            ->get();

        return view('dashboards.staff', compact(
            'patientsToday',
            'appointmentsToday',
            'pendingCheckups',
            'highRiskAlerts',
            'upcomingAppointments',
            'followUpTasks',
            'totalPatients',
            'activePatients',
            'recentVisits'
        ));
    }

    /**
     * Generate Admin Dashboard Insights
     */
    private function generateAdminInsights($highRisk, $hypertension, $diabetes, $anemia, $growthPercent)
    {
        $insights = [];

        if ($highRisk > 10) {
            $insights[] = "⚠️ High-risk cases have increased significantly. Consider scheduling urgent reviews.";
        }

        if ($hypertension > 5) {
            $insights[] = "🩸 Hypertension is prominent. Implement blood pressure monitoring protocols.";
        }

        if ($diabetes > 5) {
            $insights[] = "🍬 Diabetes management needed. Consider dietary counseling programs.";
        }

        if ($growthPercent > 20) {
            $insights[] = "📈 Excellent growth this month. Continue current strategies.";
        } elseif ($growthPercent < -10) {
            $insights[] = "📉 Visit numbers declined. Review marketing or scheduling efficiency.";
        }

        if (empty($insights)) {
            $insights[] = "✅ Operations running smoothly. All metrics are healthy.";
        }

        return $insights;
    }
}