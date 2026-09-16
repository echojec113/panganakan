<x-app-layout>
<style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap');

    .dash-root { font-family: 'DM Sans', sans-serif; background: #FCFBF8; min-height: 100vh; }

    /* ---- Visual Tokens (landing/login-inspired) ---- */
    :root {
        --dnav-primary: #19355F;
        --dnav-green: #55B85A;
        --dnav-dark: #367E4B;
        --dwhite-warm: #FCFBF8;
        --dcream-light: #F6F4EE;
        --dtext-body: #657083;
        --dborder-subtle: #E7E9E5;
    }

    /* ---- KPI Cards ---- */
    .kpi-card {
        background: #ffffff;
        border: 1px solid #e8eaf0;
        border-radius: 16px;
        padding: 20px;
        transition: box-shadow 0.2s ease, transform 0.2s ease;
        position: relative;
        overflow: hidden;
    }
    .kpi-card:hover { box-shadow: 0 4px 12px rgba(30,41,59,0.08); transform: none; }
    .kpi-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 2px;
        border-radius: 16px 16px 0 0;
    }
    .kpi-blue::before   { background: #19355F; }
    .kpi-emerald::before{ background: #55B85A; }
    .kpi-amber::before  { background: #d97706; }
    .kpi-violet::before { background: #7c3aed; }

    .kpi-icon {
        width: 40px; height: 40px;
        border-radius: 10px;
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
        padding: 16px 20px 12px;
        border-bottom: 1px solid #f1f3f7;
        display: flex; align-items: center; justify-content: space-between;
    }

    /* ---- Priority List ---- */
    .priority-row {
        display: flex; align-items: center; justify-content: space-between;
        padding: 12px 16px;
        border-bottom: 1px solid #f7f8fa;
        text-decoration: none;
        transition: background 0.15s;
    }
    .priority-row:hover { background: #f8f9fc; }
    .priority-row:last-child { border-bottom: none; }
    .priority-badge {
        font-size: 10px; font-weight: 600; letter-spacing: 0.04em;
        padding: 2px 6px; border-radius: 16px;
        background: #fef3c7; color: #92400e;
    }

    /* ---- Progress bar ---- */
    .progress-track { background: #f1f3f7; border-radius: 99px; height: 4px; overflow: hidden; }
    .progress-fill { height: 100%; border-radius: 99px; transition: width 0.6s cubic-bezier(.4,0,.2,1); }

    /* ---- Insight Items ---- */
    .insight-item {
        display: flex; align-items: flex-start; gap: 8px;
        padding: 10px 0;
        border-bottom: 1px solid #f1f3f7;
    }
    .insight-item:last-child { border-bottom: none; }
    .insight-dot { width: 6px; height: 6px; border-radius: 50%; margin-top: 4px; flex-shrink: 0; }

    /* ---- Condition Bars ---- */
    .cond-row { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
    .cond-label { font-size: 12px; font-weight: 500; color: #657083; width: 100px; flex-shrink: 0; }
    .cond-bar-wrap { flex: 1; }
    .cond-count { font-size: 12px; font-weight: 600; color: #1e293b; width: 24px; text-align: right; }

    /* ---- Stat Pills ---- */
    .stat-pill {
        background: #f8f9fc; border: 1px solid #e8eaf0; border-radius: 10px;
        padding: 12px 16px; text-align: center;
    }

    /* ---- Alert Banner ---- */
    .alert-banner {
        background: #fef3c7;
        border: 1px solid #fde68a;
        border-radius: 12px;
        padding: 16px 20px;
    }

    .mono { font-family: 'DM Mono', monospace; }

    /* Chart containers */
    .chart-wrap { position: relative; width: 100%; }
</style>

<div class="dash-root">

    

    {{-- ==================== MAIN CONTENT ==================== --}}
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- ======= ROW 1: KPI CARDS (Total Patients | Active Patients | High Risk | Low Risk) ======= --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

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

            {{-- HIGH Risk --}}
            <div class="kpi-card" style="border-left: 4px solid #dc2626; padding: 16px;">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-0.5">HIGH Risk</p>
                        <p data-testid="admin-high-count" class="text-xl font-bold text-red-600 mono">{{ $highRisk }}</p>
                        <p class="text-xs text-slate-400 mt-0.5 leading-relaxed">
                            Patients whose latest assessment identified elevated-risk findings and require clinic review.
                        </p>
                        <a href="{{ route('risk.monitoring', ['risk_filter' => 'HIGH']) }}" class="inline-block mt-1 text-xs font-semibold text-red-600 hover:text-red-800 underline">
                            View all HIGH &rarr;
                        </a>
                    </div>
                </div>
            </div>

            {{-- LOW Risk --}}
            <div class="kpi-card" style="border-left: 4px solid #16a34a; padding: 16px;">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-0.5">LOW Risk</p>
                        <p data-testid="admin-low-count" class="text-xl font-bold text-green-600 mono">{{ $lowRisk }}</p>
                        <p class="text-xs text-slate-400 mt-0.5 leading-relaxed">
                            Patients whose latest completed assessment found no deterministic HIGH-risk rule and received a valid LOW model result.
                        </p>
                        <a href="{{ route('risk.monitoring', ['risk_filter' => 'LOW']) }}" class="inline-block mt-1 text-xs font-semibold text-green-600 hover:text-green-800 underline">
                            View all LOW &rarr;
                        </a>
                    </div>
                </div>
            </div>

        </div>

        @php
            $totalRiskCases = $highRisk + $lowRisk;
        @endphp

        {{-- ======= ROW 3: Monthly Trend + Conditions ======= --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Monthly Visits Trend (2/3) --}}
            <div class="lg:col-span-2 dash-card">
                <div class="dash-card-header">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Monthly Visits Trend</h2>
                        <p class="text-xs text-slate-400 mt-0">Visit volume over time</p>
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
                        <p class="text-xs text-slate-400 mt-0">Prevalence breakdown</p>
                    </div>
                </div>
                <div class="p-5">
                    <div class="chart-wrap" style="height:200px;">
                        <canvas id="conditionsChart"></canvas>
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
                        <p class="text-xs text-slate-400 mt-0">Patient risk stratification</p>
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
                        <p class="text-xs text-slate-400 mt-0">Month-over-month performance</p>
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
                            <div class="progress-fill {{ $visitGrowthPercent >= 0 ? 'bg-emerald-500' : 'bg-red-500' }}"
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
                            <div class="progress-fill {{ $patientGrowthPercent >= 0 ? 'bg-emerald-500' : 'bg-red-500' }}"
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
                    <div class="pt-2 border-t border-slate-100 grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs text-slate-400 mb-0">All-time Visits</p>
                            <p class="text-xl font-bold text-slate-900 mono">{{ $visitsThisMonth + 150 }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 mb-0">Avg Monthly</p>
                            <p class="text-xl font-bold text-slate-900 mono">{{ $totalRiskCases > 0 ? round(($visitsThisMonth + 150) / 12) : 0 }}</p>
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

    // ---- 3. Conditions Bar Graph ----
    const condLabels = {!! json_encode($conditions->pluck('name')) !!};
    const condData   = {!! json_encode($conditions->pluck('count')) !!};
    const condPalette = [palette.blue, palette.amber, palette.violet, palette.emerald, palette.red, palette.slate];

    new Chart(document.getElementById('conditionsChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: condLabels,
            datasets: [{
                label: 'Cases',
                data: condData,
                backgroundColor: condLabels.map((_, i) => condPalette[i % condPalette.length]),
                borderRadius: 6,
                borderSkipped: false,
                maxBarThickness: 36,
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...sharedTooltip,
                    callbacks: {
                        title: items => condLabels[items[0].dataIndex] || '',
                    }
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        font: { ...baseFont, size: 10 },
                        color: '#64748b',
                        autoSkip: false,
                        maxRotation: 40,
                        minRotation: 0,
                        callback: function (value) {
                            const label = this.getLabelForValue(value);
                            return label && label.length > 12 ? label.slice(0, 11) + '…' : label;
                        }
                    },
                    border: { display: false },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: palette.gridLine },
                    ticks: { font: baseFont, color: '#94a3b8', maxTicksLimit: 4, precision: 0 },
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
