<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\PrenatalVisit;
use Carbon\Carbon;
use App\Services\RiskAnalyticsService;
use App\Services\ReferralAnalyticsService;
use App\Services\RiskMonitoringDataService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private RiskAnalyticsService $riskAnalytics,
         private ReferralAnalyticsService $referralAnalytics,
        private RiskMonitoringDataService $riskMonitoringData
    ) {
    }

    public function index(Request $request)
    {
        // Check user role and return appropriate dashboard
        if (auth()->user()->role === 'admin') {
        return $this->adminDashboard($request);
        }else {
            return $this->staffDashboard($request);
        }
    }

    private function latestVisitSubquery(): \Illuminate\Database\Query\Builder
    {
        return PrenatalVisit::latestAssessmentIds();
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
    private function adminDashboard(Request $request)
    {
        // ======================
        // KPI DATA
        // ======================

        $totalPatients = Patient::count();
        $newPatientsThisMonth = Patient::query()
            ->whereYear('created_at', Carbon::now()->year)
            ->whereMonth('created_at', Carbon::now()->month)
            ->count();
        $activePregnancies = Patient::where('status', 'ONGOING')->count();
        $visitsThisMonth = PrenatalVisit::query()
            ->whereYear('visit_date', Carbon::now()->year)
            ->whereMonth('visit_date', Carbon::now()->month)
            ->count();

        // ======================
        // VISIT TREND ANALYTICS
        // ======================

$selectedYear = $request->filled('year')
    ? (int) $request->year
    : Carbon::now()->year;

$selectedMonth = $request->filled('month')
    ? (int) $request->month
    : null;

$availableYears = PrenatalVisit::query()
    ->whereNotNull('visit_date')
    ->selectRaw('YEAR(visit_date) as year')
    ->distinct()
    ->orderByDesc('year')
    ->pluck('year')
    ->map(fn ($year) => (int) $year);

if (!$availableYears->contains($selectedYear)) {
    $availableYears->push($selectedYear);
    $availableYears = $availableYears->sortDesc()->values();    
}

if ($selectedMonth) {
    // Specific month selected → daily visit counts
    $daysInMonth = Carbon::create($selectedYear, $selectedMonth, 1)->daysInMonth;

    $dailyCounts = PrenatalVisit::query()
        ->whereYear('visit_date', $selectedYear)
        ->whereMonth('visit_date', $selectedMonth)
        ->selectRaw('DAY(visit_date) as day, COUNT(*) as total')
        ->groupByRaw('DAY(visit_date)')
        ->pluck('total', 'day');

    $trendLabels = collect(range(1, $daysInMonth));
    $trendData = $trendLabels
        ->map(fn ($day) => (int) ($dailyCounts[$day] ?? 0));

    $trendGranularity = 'daily';
} else {
    // Whole year selected → monthly visit counts
    $monthlyCounts = PrenatalVisit::query()
        ->whereYear('visit_date', $selectedYear)
        ->selectRaw('MONTH(visit_date) as month, COUNT(*) as total')
        ->groupByRaw('MONTH(visit_date)')
        ->pluck('total', 'month');

    $trendLabels = collect(range(1, 12));
    $trendData = $trendLabels
        ->map(fn ($month) => (int) ($monthlyCounts[$month] ?? 0));

    $trendGranularity = 'monthly';
}
// ======================
// PATIENT REGISTRATION TREND
// ======================

if ($selectedMonth) {

    // Specific month selected → daily patient registrations
    $daysInMonth = Carbon::create(
        $selectedYear,
        $selectedMonth,
        1
    )->daysInMonth;

    $dailyRegistrationCounts = Patient::query()
        ->whereYear('created_at', $selectedYear)
        ->whereMonth('created_at', $selectedMonth)
        ->selectRaw('DAY(created_at) as day, COUNT(*) as total')
        ->groupByRaw('DAY(created_at)')
        ->pluck('total', 'day');

    $registrationLabels = collect(range(1, $daysInMonth));

    $registrationData = $registrationLabels
        ->map(fn ($day) => (int) ($dailyRegistrationCounts[$day] ?? 0));

    $registrationGranularity = 'daily';

} else {

    // Whole year selected → monthly patient registrations
    $monthlyRegistrationCounts = Patient::query()
        ->whereYear('created_at', $selectedYear)
        ->selectRaw('MONTH(created_at) as month, COUNT(*) as total')
        ->groupByRaw('MONTH(created_at)')
        ->pluck('total', 'month');

    $registrationLabels = collect(range(1, 12));

    $registrationData = $registrationLabels
        ->map(fn ($month) => (int) ($monthlyRegistrationCounts[$month] ?? 0));

    $registrationGranularity = 'monthly';
}
// ======================
// ADMIN ANALYTICS SUMMARY
// ======================

// Total prenatal visits in the selected reporting period
$totalVisitsSelectedPeriod = collect($trendData)->sum();

// Total new patient registrations in the selected reporting period
$totalRegistrationsSelectedPeriod = collect($registrationData)->sum();

// Busiest month or day based on prenatal visit volume
$peakVisitCount = collect($trendData)->max() ?? 0;
$peakVisitIndex = collect($trendData)->search($peakVisitCount);

if ($peakVisitCount > 0 && $peakVisitIndex !== false) {

    if ($selectedMonth) {
        // Specific month selected → busiest day
        $busiestPeriodLabel = Carbon::create(
            $selectedYear,
            $selectedMonth,
            $peakVisitIndex + 1
        )->format('M j');

        $busiestPeriodType = 'Busiest Day';
    } else {
        // Whole year selected → busiest month
        $busiestPeriodLabel = Carbon::create(
            $selectedYear,
            $peakVisitIndex + 1,
            1
        )->format('F');

        $busiestPeriodType = 'Busiest Month';
    }

} else {
    $busiestPeriodLabel = '—';
    $busiestPeriodType = $selectedMonth
        ? 'Busiest Day'
        : 'Busiest Month';
}

// REFERRAL PERFORMANCE

$referralAnalytics = $this->referralAnalytics->get(
    $selectedYear,
    $selectedMonth
);

$referralsSelectedPeriod = $referralAnalytics['summary']['totalReferrals'];

$referralTrendLabels = $referralAnalytics['trend']['labels'];
$referralTrendData = $referralAnalytics['trend']['data'];
$referralTrendGranularity = $referralAnalytics['trend']['granularity'];

$referralPending = $referralAnalytics['status']['pending'];
$referralCompleted = $referralAnalytics['status']['completed'];
$referralRefused = $referralAnalytics['status']['refused'];
$referralCancelled = $referralAnalytics['status']['cancelled'];

$mostReferredFacility = $referralAnalytics['summary']['mostReferredFacility'];

// ======================
// RISK FACTOR ANALYTICS
// ======================

$adminRiskAnalytics = $this->riskAnalytics->get(
    $selectedYear,
    $selectedMonth,
    'HIGH'
);

$conditions = collect($adminRiskAnalytics['topHighRiskConditions'])
    ->map(function ($factor) {
        return [
            'name' => $factor['label'],
            'count' => $factor['count'],
        ];
    });

        return view('dashboards.admin', compact(
            'totalPatients',
            'newPatientsThisMonth',
            'activePregnancies',
            'trendLabels',
            'trendData',
            'selectedYear',
            'selectedMonth',
            'availableYears',
            'trendGranularity',
            'registrationLabels',
            'registrationData',
            'registrationGranularity',
            'conditions',
            'visitsThisMonth',
            'busiestPeriodType',
            'busiestPeriodLabel',
            'peakVisitCount',
            'totalVisitsSelectedPeriod',
            'totalRegistrationsSelectedPeriod',
            'referralsSelectedPeriod',
            'referralTrendLabels',
            'referralTrendData',
            'referralTrendGranularity',
            'referralPending',
            'referralCompleted',
            'referralRefused',
            'referralCancelled',
            'mostReferredFacility',
        ));
    }

    /**
     * Staff Dashboard - Daily Operations & Tasks View
     */
    private function staffDashboard(Request $request)
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
            ->whereHas('patient', fn ($query) => $query->where('status', 'ONGOING'))
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->get();

        // ======================
        // EXPLAINABLE RISK COUNTS (LATEST PER PATIENT)
        // ======================

        $staffHighRiskCount = PrenatalVisit::whereIn('id', $this->latestVisitSubquery())
            ->whereHas('patient', fn ($query) => $query->where('status', 'ONGOING'))
            ->where('risk_level', 'HIGH')
            ->count();
        $staffLowRiskCount = PrenatalVisit::whereIn('id', $this->latestVisitSubquery())
            ->whereHas('patient', fn ($query) => $query->where('status', 'ONGOING'))
            ->where('risk_level', 'LOW')
            ->count();
        $staffIncompleteCount = PrenatalVisit::whereIn('id', $this->latestVisitSubquery())
            ->whereHas('patient', fn ($query) => $query->where('status', 'ONGOING'))
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
// FOLLOW-UP ATTENTION
// ======================



// Ongoing patients whose scheduled follow-up has already passed
$followUpOverdueCount = PrenatalVisit::whereIn(
        'id',
        $this->latestVisitSubquery()
    )
    ->whereNotNull('next_visit_date')
    ->whereDate('next_visit_date', '<', Carbon::today())
    ->whereHas('patient', function ($query) {
        $query->where('status', 'ONGOING');
    })
    ->count();


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

        $visits = $this->riskMonitoringData->visits($request);

$analytics = $this->riskAnalytics->get(
    $request->filled('year') ? (int) $request->year : null,
    $this->riskMonitoringData->monthFilter($request->month),
    $this->riskMonitoringData->riskTypeFilter($request->risk_type)
);

$ageDistribution = $this->riskAnalytics->ageDistribution();

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
            'staffPendingRepeatCount',
            'visits',
            'analytics',
            'ageDistribution',
            'followUpOverdueCount'
            
        ));
    }

}
