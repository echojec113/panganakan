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

    private function latestVisitSubquery(): \Illuminate\Database\Query\Builder
    {
        return \Illuminate\Support\Facades\DB::table('prenatal_visits')
            ->whereNull('deleted_at')
            ->selectRaw('MAX(id) as id')
            ->groupBy('patient_id');
    }

    private function countLatestByRisk(string $riskLevel): int
    {
        return PrenatalVisit::whereIn('id', $this->latestVisitSubquery())
            ->where('risk_level', $riskLevel)
            ->count();
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
        $activePregnancies = Patient::where('status', 'ONGOING')->count();
        $upcomingAppointments = PrenatalVisit::whereDate('next_visit_date', '>=', Carbon::today())->count();

        // Latest visit per patient counts
        $highRisk = $this->countLatestByRisk('HIGH');
        $lowRisk = $this->countLatestByRisk('LOW');
        $incompleteCount = $this->countLatestByRisk('ASSESSMENT INCOMPLETE');

        // Urgent BP alerts & pending repeats (latest visit per patient)
        $urgentBpCount = PrenatalVisit::whereIn('id', $this->latestVisitSubquery())
            ->where('urgency', 'URGENT_CLINICAL_REVIEW')
            ->count();
        $pendingRepeatCount = PrenatalVisit::whereIn('id', $this->latestVisitSubquery())
            ->where('bp_verification_status', 'PENDING_REPEAT')
            ->count();

        // ======================
        // CONDITION COUNTS
        // ======================

        $hypertensionCount = PrenatalVisit::where('hypertension', 1)->count();
        $diabetesCount = PrenatalVisit::where('diabetes', 1)->count();
        $anemiaCount = PrenatalVisit::where('anemia', 1)->count();

        // ======================
        // MONTHLY TREND DATA
        // ======================

        $trend = PrenatalVisit::select('visit_date')
            ->get()
            ->groupBy(fn ($v) => Carbon::parse($v->visit_date)->format('n'))
            ->map(fn ($group) => $group->count());

        $trendLabels = $trend->keys();
        $trendData = $trend->values();

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
        // HIGH RISK PATIENTS (UNIQUE, WITH EXPLAINABILITY)
        // ======================

        $highRiskPatients = PrenatalVisit::with('patient')
            ->where('risk_level', 'HIGH')
            ->whereIn('id', $this->latestVisitSubquery())
            ->orderByDesc('visit_date')
            ->take(5)
            ->get();

        // ======================
        // URGENT BP ALERTS (UNIQUE, LATEST)
        // ======================

        $urgentBpPatients = PrenatalVisit::with('patient')
            ->where('urgency', 'URGENT_CLINICAL_REVIEW')
            ->whereIn('id', $this->latestVisitSubquery())
            ->orderByDesc('visit_date')
            ->take(5)
            ->get();

        // ======================
        // PENDING REPEAT BP (UNIQUE, LATEST)
        // ======================

        $pendingRepeatPatients = PrenatalVisit::with('patient')
            ->where('bp_verification_status', 'PENDING_REPEAT')
            ->whereIn('id', $this->latestVisitSubquery())
            ->orderByDesc('visit_date')
            ->take(5)
            ->get();

        // ======================
        // INCOMPLETE ASSESSMENTS (UNIQUE)
        // ======================

        $incompletePatients = PrenatalVisit::with('patient')
            ->where('risk_level', 'ASSESSMENT INCOMPLETE')
            ->whereIn('id', $this->latestVisitSubquery())
            ->orderByDesc('visit_date')
            ->take(5)
            ->get();

        // ======================
        // OVERDUE FOLLOW-UPS
        // ======================

        $overdueFollowUps = PrenatalVisit::with('patient')
            ->whereHas('patient', function ($q) {
                $q->where('status', 'ONGOING');
            })
            ->whereNotNull('next_visit_date')
            ->where('next_visit_date', '<', Carbon::today())
            ->whereIn('id', function ($q) {
                $q->selectRaw('MAX(id)')
                    ->from('prenatal_visits')
                    ->whereNull('deleted_at')
                    ->groupBy('patient_id');
            })
            ->orderBy('next_visit_date')
            ->take(5)
            ->get();

        $overdueCount = $overdueFollowUps->count();

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
            'visitsThisMonth',
            'incompleteCount',
            'incompletePatients',
            'overdueCount',
            'overdueFollowUps',
            'urgentBpCount',
            'pendingRepeatCount',
            'urgentBpPatients',
            'pendingRepeatPatients'
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
        $patientsToday = PrenatalVisit::whereDate('visit_date', $today)
            ->count();
        $appointmentsToday = $patientsToday;
        $pendingCheckups = PrenatalVisit::whereNull('next_visit_date')
            ->count();

        // ======================
        // HIGH RISK ALERTS (LATEST PER PATIENT)
        // ======================

        $highRiskAlerts = PrenatalVisit::with('patient')
            ->where('risk_level', 'HIGH')
            ->whereIn('id', $this->latestVisitSubquery())
            ->latest()
            ->take(5)
            ->get();

        // ======================
        // EXPLAINABLE RISK COUNTS (LATEST PER PATIENT)
        // ======================

        $staffHighRiskCount = PrenatalVisit::whereIn('id', $this->latestVisitSubquery())
            ->where('risk_level', 'HIGH')
            ->count();
        $staffLowRiskCount = PrenatalVisit::whereIn('id', $this->latestVisitSubquery())
            ->where('risk_level', 'LOW')
            ->count();
        $staffIncompleteCount = PrenatalVisit::whereIn('id', $this->latestVisitSubquery())
            ->where('risk_level', 'ASSESSMENT INCOMPLETE')
            ->count();

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
            ->whereNotNull('next_visit_date')
            ->where('next_visit_date', '>', Carbon::today())
            ->orderBy('next_visit_date')
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

        // ======================
        // URGENT BP & PENDING REPEAT COUNTS (CLINIC-WIDE)
        // ======================

        $staffUrgentBpCount = PrenatalVisit::whereIn('id', $this->latestVisitSubquery())
            ->where('urgency', 'URGENT_CLINICAL_REVIEW')
            ->count();
        $staffPendingRepeatCount = PrenatalVisit::whereIn('id', $this->latestVisitSubquery())
            ->where('bp_verification_status', 'PENDING_REPEAT')
            ->count();

        return view('dashboards.staff', compact(
            'patientsToday',
            'appointmentsToday',
            'pendingCheckups',
            'highRiskAlerts',
            'upcomingAppointments',
            'followUpTasks',
            'totalPatients',
            'activePatients',
            'recentVisits',
            'staffHighRiskCount',
            'staffLowRiskCount',
            'staffIncompleteCount',
            'staffUrgentBpCount',
            'staffPendingRepeatCount'
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