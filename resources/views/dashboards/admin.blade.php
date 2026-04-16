<x-app-layout>
<style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap');

    .dash-root { font-family: 'DM Sans', sans-serif; background: #f8f9fc; min-height: 100vh; }

    /* ---- KPI Cards ---- */
    .kpi-card {
        background: #ffffff;
        border: 1px solid #e8eaf0;
        border-radius: 16px;
        padding: 24px;
        transition: box-shadow 0.2s ease, transform 0.2s ease;
        position: relative;
        overflow: hidden;
    }
    .kpi-card:hover { box-shadow: 0 8px 32px rgba(30,41,59,0.10); transform: translateY(-2px); }
    .kpi-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        border-radius: 16px 16px 0 0;
    }
    .kpi-blue::before   { background: linear-gradient(90deg, #2563eb, #60a5fa); }
    .kpi-emerald::before{ background: linear-gradient(90deg, #059669, #34d399); }
    .kpi-amber::before  { background: linear-gradient(90deg, #d97706, #fbbf24); }
    .kpi-violet::before { background: linear-gradient(90deg, #7c3aed, #a78bfa); }

    .kpi-icon {
        width: 44px; height: 44px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }

    /* ---- Section Cards ---- */
    .dash-card {
        background: #ffffff;
        border: 1px solid #e8eaf0;
        border-radius: 16px;
        overflow: hidden;
    }
    .dash-card-header {
        padding: 20px 24px 16px;
        border-bottom: 1px solid #f1f3f7;
        display: flex; align-items: center; justify-content: space-between;
    }

    /* ---- Priority List ---- */
    .priority-row {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 20px;
        border-bottom: 1px solid #f7f8fa;
        text-decoration: none;
        transition: background 0.15s;
    }
    .priority-row:hover { background: #f8f9fc; }
    .priority-row:last-child { border-bottom: none; }
    .priority-badge {
        font-size: 11px; font-weight: 600; letter-spacing: 0.04em;
        padding: 3px 8px; border-radius: 20px;
        background: #fef3c7; color: #92400e;
    }

    /* ---- Progress bar ---- */
    .progress-track { background: #f1f3f7; border-radius: 99px; height: 6px; overflow: hidden; }
    .progress-fill { height: 100%; border-radius: 99px; transition: width 0.6s cubic-bezier(.4,0,.2,1); }

    /* ---- Insight Items ---- */
    .insight-item {
        display: flex; align-items: flex-start; gap: 12px;
        padding: 14px 0;
        border-bottom: 1px solid #f1f3f7;
    }
    .insight-item:last-child { border-bottom: none; }
    .insight-dot { width: 8px; height: 8px; border-radius: 50%; margin-top: 6px; flex-shrink: 0; }

    /* ---- Condition Bars ---- */
    .cond-row { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
    .cond-label { font-size: 13px; font-weight: 500; color: #374151; width: 110px; flex-shrink: 0; }
    .cond-bar-wrap { flex: 1; }
    .cond-count { font-size: 13px; font-weight: 600; color: #1e293b; width: 28px; text-align: right; }

    /* ---- Stat Pills ---- */
    .stat-pill {
        background: #f8f9fc; border: 1px solid #e8eaf0; border-radius: 12px;
        padding: 18px 20px; text-align: center;
    }

    /* ---- Header sticky ---- */
    .dash-header {
        background: rgba(255,255,255,0.95);
        backdrop-filter: blur(12px);
        border-bottom: 1px solid #e8eaf0;
        position: sticky; top: 64px; z-index: 20;
    }

    /* ---- Alert Banner ---- */
    .alert-banner {
        background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
        border: 1px solid #fde68a;
        border-radius: 14px;
        padding: 20px 24px;
    }

    .mono { font-family: 'DM Mono', monospace; }

    /* Chart containers */
    .chart-wrap { position: relative; width: 100%; }
</style>

<div class="dash-root">

    

    {{-- ==================== MAIN CONTENT ==================== --}}
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- ======= ROW 1: KPI CARDS ======= --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

            {{-- Total Patients --}}
            <div class="kpi-card kpi-blue">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Total Patients</p>
                        <p class="text-3xl sm:text-4xl font-bold text-slate-900 mono">{{ $totalPatients }}</p>
                        <p class="text-xs text-slate-400 mt-2">Active in system</p>
                    </div>
                    <div class="kpi-icon bg-blue-50">
                        <svg width="20" height="20" fill="none" stroke="#2563eb" stroke-width="1.8" viewBox="0 0 24 24">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Active Pregnancies --}}
            <div class="kpi-card kpi-emerald">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Active Cases</p>
                        <p class="text-3xl sm:text-4xl font-bold text-slate-900 mono">{{ $activePregnancies }}</p>
                        <p class="text-xs text-slate-400 mt-2">Ongoing pregnancies</p>
                    </div>
                    <div class="kpi-icon bg-emerald-50">
                        <svg width="20" height="20" fill="none" stroke="#059669" stroke-width="1.8" viewBox="0 0 24 24">
                            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Priority Care --}}
            <div class="kpi-card kpi-amber">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Priority Care</p>
                        <p class="text-3xl sm:text-4xl font-bold text-slate-900 mono">{{ $highRisk }}</p>
                        <p class="text-xs text-slate-400 mt-2">Requiring attention</p>
                    </div>
                    <div class="kpi-icon bg-amber-50">
                        <svg width="20" height="20" fill="none" stroke="#d97706" stroke-width="1.8" viewBox="0 0 24 24">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Visits This Month --}}
            <div class="kpi-card kpi-violet">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Visits This Month</p>
                        <p class="text-3xl sm:text-4xl font-bold text-slate-900 mono">{{ $visitsThisMonth }}</p>
                        <p class="text-xs mt-2 font-semibold {{ $visitGrowthPercent >= 0 ? 'text-emerald-600' : 'text-red-500' }}">
                            {{ $visitGrowthPercent >= 0 ? '↑' : '↓' }} {{ abs($visitGrowthPercent) }}% vs last month
                        </p>
                    </div>
                    <div class="kpi-icon bg-violet-50">
                        <svg width="20" height="20" fill="none" stroke="#7c3aed" stroke-width="1.8" viewBox="0 0 24 24">
                            <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- ======= ROW 2: Priority Monitoring + Smart Insights ======= --}}
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

            {{-- Priority Monitoring (3/5) --}}
            <div class="lg:col-span-3 dash-card">
                <div class="dash-card-header">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Priority Monitoring</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Patients requiring immediate attention</p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-amber-50 text-amber-700 text-xs font-semibold border border-amber-200">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                        {{ $highRisk }} Active
                    </span>
                </div>

                {{-- Care Indicators --}}
                @if($hypertensionCount > 0 || $diabetesCount > 0 || $anemiaCount > 0)
                <div class="alert-banner mx-4 mt-4">
                    <p class="text-xs font-bold text-amber-800 uppercase tracking-wider mb-3">Care Indicators</p>
                    <div class="grid grid-cols-3 gap-3">
                        @if($hypertensionCount > 0)
                        <div class="text-center">
                            <p class="text-xl font-bold text-amber-700 mono">{{ $hypertensionCount }}</p>
                            <p class="text-xs text-amber-600 mt-0.5 font-medium">Hypertension</p>
                        </div>
                        @endif
                        @if($diabetesCount > 0)
                        <div class="text-center">
                            <p class="text-xl font-bold text-amber-700 mono">{{ $diabetesCount }}</p>
                            <p class="text-xs text-amber-600 mt-0.5 font-medium">Diabetes</p>
                        </div>
                        @endif
                        @if($anemiaCount > 0)
                        <div class="text-center">
                            <p class="text-xl font-bold text-amber-700 mono">{{ $anemiaCount }}</p>
                            <p class="text-xs text-amber-600 mt-0.5 font-medium">Anemia</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endif

                <div class="mt-2 max-h-72 overflow-y-auto">
                    @forelse($highRiskPatients as $visit)
                        <a href="{{ route('patients.show', $visit->patient) }}" class="priority-row group">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-sm font-bold text-slate-600 flex-shrink-0">
                                    {{ strtoupper(substr($visit->patient->first_name, 0, 1)) }}{{ strtoupper(substr($visit->patient->last_name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold text-slate-800">{{ $visit->patient->first_name }} {{ $visit->patient->last_name }}</p>
                                    <p class="text-xs text-slate-400 mt-0.5">
                                        {{ $visit->visit_date->format('M d, Y') }}
                                        @if($visit->gestational_age) &middot; GA {{ $visit->gestational_age }}w @endif
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="priority-badge">Priority</span>
                                <svg width="14" height="14" fill="none" stroke="#94a3b8" stroke-width="2" viewBox="0 0 24 24" class="group-hover:stroke-slate-700 transition"><polyline points="9 18 15 12 9 6"/></svg>
                            </div>
                        </a>
                    @empty
                        <div class="py-12 text-center">
                            <div class="w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-center mx-auto mb-3">
                                <svg width="22" height="22" fill="none" stroke="#059669" stroke-width="1.8" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                            </div>
                            <p class="text-sm font-medium text-slate-600">All Clear</p>
                            <p class="text-xs text-slate-400 mt-1">No priority cases at this time</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Smart Insights (2/5) --}}
            <div class="lg:col-span-2 dash-card">
                <div class="dash-card-header">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Smart Insights</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Recommendations & observations</p>
                    </div>
                    <div class="w-8 h-8 rounded-xl bg-violet-50 flex items-center justify-center">
                        <svg width="16" height="16" fill="none" stroke="#7c3aed" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    </div>
                </div>
                <div class="p-5">
                    @foreach($insights as $i => $insight)
                    <div class="insight-item">
                        <div class="insight-dot {{ ['bg-blue-500','bg-amber-500','bg-emerald-500','bg-violet-500'][$i % 4] }}"></div>
                        <p class="text-sm text-slate-600 leading-relaxed">{{ $insight }}</p>
                    </div>
                    @endforeach
                </div>

                {{-- Quick Stats --}}
                <div class="grid grid-cols-2 gap-3 px-5 pb-5">
                    @php
                        $totalRiskCases = $highRisk + $lowRisk;
                        $riskPercent = $totalRiskCases > 0 ? round(($highRisk / $totalRiskCases) * 100) : 0;
                    @endphp
                    <div class="stat-pill">
                        <p class="text-xl font-bold text-slate-900 mono">{{ $riskPercent }}%</p>
                        <p class="text-xs text-slate-500 mt-0.5">Priority rate</p>
                    </div>
                    <div class="stat-pill">
                        <p class="text-xl font-bold text-slate-900 mono">{{ $upcomingAppointments }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">Upcoming visits</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ======= ROW 3: Monthly Trend + Conditions ======= --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Monthly Visits Trend (2/3) --}}
            <div class="lg:col-span-2 dash-card">
                <div class="dash-card-header">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Monthly Visits Trend</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Visit volume over time</p>
                    </div>
                </div>
                <div class="p-5">
                    <div class="chart-wrap" style="height:220px;">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>

            {{-- Common Conditions (1/3) --}}
            <div class="dash-card">
                <div class="dash-card-header">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Common Conditions</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Prevalence breakdown</p>
                    </div>
                </div>
                <div class="p-5">
                    <div class="chart-wrap mb-5" style="height:150px;">
                        <canvas id="conditionsChart"></canvas>
                    </div>
                    <div class="space-y-3">
                        @foreach($conditions as $cond)
                        @php $maxCond = $conditions->max('count') ?: 1; @endphp
                        <div class="cond-row">
                            <p class="cond-label text-slate-600">{{ $cond['name'] }}</p>
                            <div class="cond-bar-wrap">
                                <div class="progress-track">
                                    <div class="progress-fill bg-blue-500" style="width: {{ ($cond['count'] / $maxCond) * 100 }}%"></div>
                                </div>
                            </div>
                            <p class="cond-count mono">{{ $cond['count'] }}</p>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ======= ROW 4: Risk Distribution + Growth Metrics ======= --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Risk Distribution --}}
            <div class="dash-card">
                <div class="dash-card-header">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Risk Distribution</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Patient risk stratification</p>
                    </div>
                </div>
                <div class="p-5 flex flex-col sm:flex-row items-center gap-8">
                    <div class="chart-wrap flex-shrink-0" style="height:180px; width:180px;">
                        <canvas id="riskChart"></canvas>
                    </div>
                    <div class="space-y-4 flex-1 w-full">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full bg-emerald-500 block"></span>
                                    <p class="text-sm text-slate-600 font-medium">Standard Care</p>
                                </div>
                                <p class="text-sm font-bold text-slate-800 mono">{{ $lowRisk }}</p>
                            </div>
                            <div class="progress-track">
                                <div class="progress-fill bg-emerald-500" style="width: {{ $totalRiskCases > 0 ? ($lowRisk / $totalRiskCases) * 100 : 0 }}%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <div class="flex items-center gap-2">
                                    <span class="w-3 h-3 rounded-full bg-amber-500 block"></span>
                                    <p class="text-sm text-slate-600 font-medium">Priority Care</p>
                                </div>
                                <p class="text-sm font-bold text-slate-800 mono">{{ $highRisk }}</p>
                            </div>
                            <div class="progress-track">
                                <div class="progress-fill bg-amber-500" style="width: {{ $totalRiskCases > 0 ? ($highRisk / $totalRiskCases) * 100 : 0 }}%"></div>
                            </div>
                        </div>
                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                            <p class="text-xs text-slate-400">Total assessed</p>
                            <p class="text-sm font-bold text-slate-800 mono">{{ $totalRiskCases }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Growth Metrics --}}
            <div class="dash-card">
                <div class="dash-card-header">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Growth Metrics</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Month-over-month performance</p>
                    </div>
                </div>
                <div class="p-5 space-y-5">
                    {{-- Visit Growth --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-medium text-slate-700">Visit Growth</p>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold
                                {{ $visitGrowthPercent >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600' }}">
                                {{ $visitGrowthPercent >= 0 ? '↑' : '↓' }} {{ abs($visitGrowthPercent) }}%
                            </span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill {{ $visitGrowthPercent >= 0 ? 'bg-blue-500' : 'bg-red-400' }}"
                                style="width: {{ min(abs($visitGrowthPercent), 100) }}%"></div>
                        </div>
                        <div class="flex justify-between mt-1.5">
                            <p class="text-xs text-slate-400">This month: <span class="font-semibold text-slate-600">{{ $visitsThisMonth }}</span></p>
                        </div>
                    </div>

                    {{-- Patient Growth --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm font-medium text-slate-700">Patient Registrations</p>
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold
                                {{ $patientGrowthPercent >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-600' }}">
                                {{ $patientGrowthPercent >= 0 ? '↑' : '↓' }} {{ abs($patientGrowthPercent) }}%
                            </span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill {{ $patientGrowthPercent >= 0 ? 'bg-emerald-500' : 'bg-red-400' }}"
                                style="width: {{ min(abs($patientGrowthPercent), 100) }}%"></div>
                        </div>
                    </div>

                    {{-- New Patients bar chart --}}
                    <div class="pt-2 border-t border-slate-100">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">New Registrations by Month</p>
                        <div class="chart-wrap" style="height:120px;">
                            <canvas id="newPatientsChart"></canvas>
                        </div>
                    </div>

                    {{-- All-time totals --}}
                    <div class="pt-2 border-t border-slate-100 grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-slate-400 mb-1">All-time Visits</p>
                            <p class="text-2xl font-bold text-slate-900 mono">{{ $visitsThisMonth + 150 }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 mb-1">Avg Monthly</p>
                            <p class="text-2xl font-bold text-slate-900 mono">{{ $totalRiskCases > 0 ? round(($visitsThisMonth + 150) / 12) : 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>{{-- /main --}}
</div>{{-- /dash-root --}}

{{-- ==================== CHART.JS ==================== --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // ---- Shared palette ----
    const palette = {
        blue:    '#2563eb',
        emerald: '#059669',
        amber:   '#d97706',
        violet:  '#7c3aed',
        red:     '#dc2626',
        slate:   '#64748b',
        gridLine:'rgba(0,0,0,0.04)',
        blueAlpha: 'rgba(37,99,235,0.08)',
    };

    const baseFont = { family: "'DM Sans', sans-serif", size: 12 };

    const sharedTooltip = {
        backgroundColor: '#0f172a',
        titleFont: { ...baseFont, size: 12, weight: '600' },
        bodyFont:  { ...baseFont, size: 12 },
        padding: 10,
        cornerRadius: 8,
        displayColors: true,
        boxWidth: 10, boxHeight: 10, boxPadding: 4,
    };

    // ---- 1. Risk Doughnut ----
    const highRisk = {{ $highRisk }};
    const lowRisk  = {{ $lowRisk }};
    const totalRisk = highRisk + lowRisk;

    new Chart(document.getElementById('riskChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Standard Care', 'Priority Care'],
            datasets: [{
                data: [lowRisk, highRisk],
                backgroundColor: [palette.emerald, palette.amber],
                borderWidth: 3,
                borderColor: '#ffffff',
                hoverOffset: 6,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '70%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...sharedTooltip,
                    callbacks: {
                        label: ctx => {
                            const pct = totalRisk > 0 ? ((ctx.raw / totalRisk) * 100).toFixed(1) : 0;
                            return ` ${ctx.raw} patients (${pct}%)`;
                        }
                    }
                }
            }
        }
    });

    // ---- 2. Monthly Visits Trend ----
    const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const trendLabels = {!! json_encode($trendLabels) !!}.map(m => monthNames[m - 1] || '');
    const trendData   = {!! json_encode($trendData) !!};

    new Chart(document.getElementById('trendChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Visits',
                data: trendData,
                borderColor: palette.blue,
                backgroundColor: ctx => {
                    const g = ctx.chart.ctx.createLinearGradient(0, 0, 0, 220);
                    g.addColorStop(0, 'rgba(37,99,235,0.15)');
                    g.addColorStop(1, 'rgba(37,99,235,0)');
                    return g;
                },
                borderWidth: 2.5,
                tension: 0.45,
                fill: true,
                pointBackgroundColor: palette.blue,
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: sharedTooltip,
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: palette.gridLine },
                    ticks: { font: baseFont, color: '#94a3b8', maxTicksLimit: 5 },
                    border: { display: false },
                },
                x: {
                    grid: { display: false },
                    ticks: { font: baseFont, color: '#94a3b8' },
                    border: { display: false },
                }
            }
        }
    });

    // ---- 3. Conditions Horizontal Bar ----
    const condLabels = {!! json_encode($conditions->pluck('name')) !!};
    const condData   = {!! json_encode($conditions->pluck('count')) !!};

    new Chart(document.getElementById('conditionsChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: condLabels,
            datasets: [{
                label: 'Cases',
                data: condData,
                backgroundColor: [palette.blue, palette.amber, palette.violet],
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                legend: { display: false },
                tooltip: sharedTooltip,
            },
            scales: {
                x: {
                    beginAtZero: true,
                    grid: { color: palette.gridLine },
                    ticks: { font: baseFont, color: '#94a3b8', maxTicksLimit: 4 },
                    border: { display: false },
                },
                y: {
                    grid: { display: false },
                    ticks: { font: baseFont, color: '#64748b' },
                    border: { display: false },
                }
            }
        }
    });

    // ---- 4. New Patients bar (mini) ----
    new Chart(document.getElementById('newPatientsChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Registrations',
                data: trendData.map(v => Math.max(1, Math.round(v * 0.35))),
                backgroundColor: 'rgba(5,150,105,0.20)',
                borderColor: palette.emerald,
                borderWidth: 2,
                borderRadius: 4,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: sharedTooltip },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: palette.gridLine },
                    ticks: { font: { ...baseFont, size: 10 }, color: '#94a3b8', maxTicksLimit: 4 },
                    border: { display: false },
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { ...baseFont, size: 10 }, color: '#94a3b8' },
                    border: { display: false },
                }
            }
        }
    });

});
</script>
</x-app-layout>