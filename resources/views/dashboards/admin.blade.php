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

    /* Page-local containment: layout follows usable width after the sidebar. */
    .dash-root { min-width: 0; overflow-wrap: anywhere; }
    .dash-root .grid > *, .dash-root .dash-card, .dash-root .kpi-card { min-width: 0; }
    .dash-root .chart-wrap { position: relative; width: 100%; min-width: 0; max-width: 100%; }
    .dash-root .chart-wrap canvas { max-width: 100%; }
    .dash-root .dash-card-header { flex-wrap: wrap; gap: 12px; }
    .dash-root .dashboard-toolbar { flex-wrap: wrap; }
    .dash-root .dashboard-filters { min-width: 0; max-width: 100%; }
    .dash-root .dashboard-filters > div { min-width: 0; max-width: 100%; }
    .dash-root .dashboard-filters select { min-width: 0; width: 100%; max-width: 100%; }
    @media (max-width: 639px) {
        .dash-root .dashboard-filters { width: 100%; }
        .dash-root .dashboard-filters > div { flex: 1 1 100%; width: 100%; }
    }

    .dash-root .dashboard-content { container-type: inline-size; container-name: admin-dashboard; }
    @container admin-dashboard (min-width: 960px) {
        .dash-root .dashboard-kpis { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }

    @container admin-dashboard (min-width: 1000px) {
        .dash-root .dashboard-primary { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .dash-root .dashboard-primary > :first-child { grid-column: span 2; }
        .dash-root .dashboard-secondary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    .dash-root .referral-summary { grid-template-columns: repeat(auto-fit, minmax(min(100%, 100px), 1fr)); }
</style>

<div class="dash-root">

    

    {{-- ==================== MAIN CONTENT ==================== --}}
    <div class="dashboard-content max-w-screen-xl mx-auto px-2 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- ======= ROW 1: BUSINESS SUMMARY ======= --}}
<div class="dashboard-kpis grid grid-cols-1 sm:grid-cols-2 gap-4">

    {{-- Total Patients --}}
    <div class="kpi-card kpi-blue">
        <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">
                    Total Patients
                </p>

                <p class="text-3xl sm:text-4xl font-bold text-slate-900 mono">
                    {{ $totalPatients }}
                </p>

                <p class="text-xs text-slate-400 mt-2">
                    All patient records in the system
                </p>
            </div>

            <div class="kpi-icon bg-blue-50">
                <svg width="20" height="20" fill="none" stroke="#2563eb"
                    stroke-width="1.8" viewBox="0 0 24 24">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
        </div>
    </div>

    {{-- Ongoing Patients --}}
    <div class="kpi-card kpi-emerald">
        <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">
                    Ongoing Patients
                </p>

                <p class="text-3xl sm:text-4xl font-bold text-slate-900 mono">
                    {{ $activePregnancies }}
                </p>

                <p class="text-xs text-slate-400 mt-2">
                    Currently under prenatal care
                </p>
            </div>

            <div class="kpi-icon bg-emerald-50">
                <svg width="20" height="20" fill="none" stroke="#059669"
                    stroke-width="1.8" viewBox="0 0 24 24">
                    <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                </svg>
            </div>
        </div>
    </div>

    {{-- Visits This Month --}}
    <div class="kpi-card" style="border-top: 2px solid #7c3aed;">
        <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">
                    Visits This Month
                </p>

                <p class="text-3xl sm:text-4xl font-bold text-slate-900 mono">
                    {{ $visitsThisMonth }}
                </p>

                <p class="text-xs text-slate-400 mt-2">
                    Prenatal visits recorded this month
                </p>
            </div>

            <div class="kpi-icon bg-violet-50">
                <svg width="20" height="20" fill="none" stroke="#7c3aed"
                    stroke-width="1.8" viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="17" rx="2"/>
                    <path d="M16 2v4M8 2v4M3 10h18"/>
                    <path d="M8 14h3M8 17h5"/>
                </svg>
            </div>
        </div>
    </div>

    {{-- New Patients This Month --}}
    <div class="kpi-card" style="border-top: 2px solid #0891b2;">
        <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">
                    New Patients This Month
                </p>

                <p class="text-3xl sm:text-4xl font-bold text-slate-900 mono">
                    {{ $newPatientsThisMonth }}
                </p>

                <p class="text-xs text-slate-400 mt-2">
                    Patient records registered this month
                </p>
            </div>

            <div class="kpi-icon bg-cyan-50">
                <svg width="20" height="20" fill="none" stroke="#0891b2"
                    stroke-width="1.8" viewBox="0 0 24 24">
                    <path d="M15 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="8" cy="7" r="4"/>
                    <path d="M19 8v6M16 11h6"/>
                </svg>
            </div>
        </div>
    </div>

</div>

        <section class="space-y-6" aria-labelledby="analytics-overview-title">
            <div class="dashboard-toolbar flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 id="analytics-overview-title" class="text-lg font-bold text-slate-900">Analytics Overview</h2>
                    <p class="mt-1 text-sm text-slate-500">Clinic activity for the selected reporting period</p>
                </div>
        <form
            method="GET"
            action="{{ route('dashboard') }}"
            class="dashboard-filters flex items-end gap-2 flex-wrap"
        >
            <div>
                <label
                    for="adminTrendYear"
                    class="block text-xs font-medium text-slate-500 mb-1"
                >
                    Year
                </label>

                <select
                    id="adminTrendYear"
                    name="year"
                    onchange="this.form.submit()"
                    class="rounded-lg border-slate-300 text-sm text-slate-700 focus:border-green-500 focus:ring-green-500"
                >
                    @foreach($availableYears as $year)
                        <option
                            value="{{ $year }}"
                            {{ (int) $selectedYear === (int) $year ? 'selected' : '' }}
                        >
                            {{ $year }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label
                    for="adminTrendMonth"
                    class="block text-xs font-medium text-slate-500 mb-1"
                >
                    Month
                </label>

                <select
                    id="adminTrendMonth"
                    name="month"
                    onchange="this.form.submit()"
                    class="rounded-lg border-slate-300 text-sm text-slate-700 focus:border-green-500 focus:ring-green-500"
                >
                    <option value="">All Months</option>

                    @foreach(range(1, 12) as $month)
                        <option
                            value="{{ $month }}"
                            {{ (int) $selectedMonth === $month ? 'selected' : '' }}
                        >
                            {{ \Carbon\Carbon::create()->month($month)->format('F') }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
            </div>

            <div class="dashboard-kpis grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-medium text-slate-500">{{ $busiestPeriodType }}</p>
                    <p class="mt-2 text-xl font-semibold text-slate-900 mono">{{ $busiestPeriodLabel }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $peakVisitCount }} {{ $peakVisitCount === 1 ? 'visit' : 'visits' }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-medium text-slate-500">Total Visits</p>
                    <p class="mt-2 text-xl font-semibold text-slate-900 mono">{{ $totalVisitsSelectedPeriod }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $selectedMonth ? 'Selected month' : 'Selected year' }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-medium text-slate-500">New Patients</p>
                    <p class="mt-2 text-xl font-semibold text-slate-900 mono">{{ $totalRegistrationsSelectedPeriod }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $selectedMonth ? 'Selected month' : 'Selected year' }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <p class="text-xs font-medium text-slate-500">Referrals</p>
                    <p class="mt-2 text-xl font-semibold text-slate-900 mono">{{ $referralsSelectedPeriod }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $selectedMonth ? 'Selected month' : 'Selected year' }}</p>
                </div>
            </div>

        {{-- ======= ROW 3: Monthly Trend + Conditions ======= --}}
        <div class="dashboard-primary grid grid-cols-1 gap-6">

            {{-- Monthly Visits Trend (2/3) --}}
<div class="dash-card">

    <div class="dash-card-header gap-4 flex-wrap">
        <div>
            <h2 class="text-base font-bold text-slate-900">
                {{ $selectedMonth ? 'Daily Visits Trend' : 'Monthly Visits Trend' }}
            </h2>

            <p class="text-xs text-slate-400 mt-0">
                @if($selectedMonth)
                    Prenatal visit volume for
                    {{ \Carbon\Carbon::create($selectedYear, $selectedMonth, 1)->format('F Y') }}
                @else
                    Prenatal visit volume for {{ $selectedYear }}
                @endif
            </p>
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
                        <h2 class="text-base font-bold text-slate-900">Top High-Risk Factors</h2>
                        <p class="text-xs text-slate-400 mt-0">Most frequent factors in selected period</p>
                    </div>
                </div>
                <div class="p-5">
                    <div class="overflow-x-auto" tabindex="0" role="region" aria-label="High-risk factor chart; scroll horizontally for more detail"><div class="min-w-[28rem]"><div class="chart-wrap" style="height:200px;">
                        <canvas id="conditionsChart"></canvas>
                    </div></div></div>
                </div>
            </div>
        </div>

        {{-- ======= ROW 4: Risk Distribution +  Referral Performance  ======= --}}
        <div class="dashboard-secondary grid grid-cols-1 gap-6">

            

            {{-- Patient Registration Trend --}}
<div class="dash-card">
    <div class="dash-card-header">
        <div>
            <h2 class="text-base font-bold text-slate-900">
                Patient Registration Trend
            </h2>

            <p class="text-xs text-slate-400 mt-0">
                {{ $registrationGranularity === 'daily'
                    ? 'Daily new patient registrations for the selected month'
                    : 'Monthly new patient registrations for the selected year' }}
            </p>
        </div>
    </div>

    <div class="p-5">

        {{-- Registration Chart --}}
        <div class="pt-4 border-t border-slate-100">
            <div class="chart-wrap" style="height:220px;">
                <canvas id="newPatientsChart"></canvas>
            </div>
        </div>

    </div>
</div>
{{-- Referral Performance --}}
<div class="dash-card">
    <div class="dash-card-header">
        <div>
            <h2 class="text-base font-bold text-slate-900">
                Referral Performance
            </h2>

            <p class="text-xs text-slate-400 mt-0">
                {{ $referralTrendGranularity === 'day'
                    ? 'Daily referral activity for the selected month'
                    : 'Monthly referral activity for the selected year' }}
            </p>
        </div>
    </div>

    <div class="p-5">

        {{-- Referral Status Summary --}}
        <div class="referral-summary grid gap-3 mb-5">

            <div class="rounded-lg bg-emerald-50 px-3 py-3">
                <p class="text-xs font-medium text-emerald-700">
                    Completed
                </p>
                <p class="mt-1 text-xl font-bold text-slate-900 mono">
                    {{ $referralCompleted }}
                </p>
            </div>

            <div class="rounded-lg bg-amber-50 px-3 py-3">
                <p class="text-xs font-medium text-amber-700">
                    Pending
                </p>
                <p class="mt-1 text-xl font-bold text-slate-900 mono">
                    {{ $referralPending }}
                </p>
            </div>

            <div class="rounded-lg bg-slate-50 px-3 py-3">
                <p class="text-xs font-medium text-slate-600">
                    Refused
                </p>
                <p class="mt-1 text-xl font-bold text-slate-900 mono">
                    {{ $referralRefused }}
                </p>
            </div>

            <div class="rounded-lg bg-red-50 px-3 py-3">
                <p class="text-xs font-medium text-red-700">
                    Cancelled
                </p>
                <p class="mt-1 text-xl font-bold text-slate-900 mono">
                    {{ $referralCancelled }}
                </p>
            </div>

        </div>

        {{-- Referral Activity Chart --}}
        <div class="pt-4 border-t border-slate-100">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">
                Referral Activity
            </p>

            <div class="chart-wrap" style="height:160px;">
                <canvas id="referralTrendChart"></canvas>
            </div>
        </div>

        {{-- Most Referred Facility --}}
        <div class="pt-4 mt-4 border-t border-slate-100">
            <p class="text-xs text-slate-400">
                Most Referred Facility
            </p>

            <p class="mt-1 text-sm font-semibold text-slate-800">
                {{ $mostReferredFacility['label'] ?? 'No referral destination recorded' }}
            </p>

            @if(!empty($mostReferredFacility))
                <p class="mt-1 text-xs text-slate-400">
                    {{ $mostReferredFacility['count'] }}
                    {{ $mostReferredFacility['count'] === 1 ? 'referral' : 'referrals' }}
                </p>
            @endif
        </div>

    </div>
</div>

        </div>

        </section>

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

 
    // ---- 2. Monthly Visits Trend ----
    const monthNames = [
    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
];

const trendGranularity = @json($trendGranularity);
const rawTrendLabels = @json($trendLabels);
const trendData = @json($trendData);

const trendLabels = trendGranularity === 'daily'
    ? rawTrendLabels.map(day => String(day))
    : rawTrendLabels.map(month => monthNames[month - 1] || '');

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

    // ---- 4. Patient Registration Trend ----
const registrationLabels = @json($registrationLabels);
const registrationData = @json($registrationData);
const registrationGranularity = @json($registrationGranularity);

new Chart(document.getElementById('newPatientsChart').getContext('2d'), {
    type: 'bar',

    data: {
        labels: registrationLabels,

        datasets: [{
            label: 'New Patient Registrations',
            data: registrationData,

            backgroundColor: 'rgba(8, 145, 178, 0.18)',
            borderColor: '#0891b2',
            borderWidth: 2,
            borderRadius: 5,
            borderSkipped: false
        }]
    },

    options: {
        responsive: true,
        maintainAspectRatio: false,

        plugins: {
            legend: {
                display: false
            },

            tooltip: {
                ...sharedTooltip,
                callbacks: {
                    title: function(items) {
                        const value = items[0].label;

                        if (registrationGranularity === 'daily') {
                            return `Day ${value}`;
                        }

                        const months = [
                            'January', 'February', 'March', 'April',
                            'May', 'June', 'July', 'August',
                            'September', 'October', 'November', 'December'
                        ];

                        return months[Number(value) - 1] ?? value;
                    },

                    label: function(context) {
                        const count = context.parsed.y;

                        return `${count} new patient${count === 1 ? '' : 's'}`;
                    }
                }
            }
        },

        scales: {
            y: {
                beginAtZero: true,

                ticks: {
                    ...baseFont,
                    size: 10,
                    color: '#94a3b8',
                    precision: 0
                },

                grid: {
                    color: palette.gridLine
                },

                border: {
                    display: false
                }
            },

            x: {
                grid: {
                    display: false
                },

                ticks: {
                    ...baseFont,
                    size: 10,
                    color: '#94a3b8',
                    autoSkip: registrationGranularity === 'daily',
                    maxTicksLimit: registrationGranularity === 'daily' ? 16 : 12
                },

                border: {
                    display: false
                }
            }
        }
        }
});


// ---- 5. Referral Activity Trend ----

const referralTrendLabels = @json($referralTrendLabels);
const referralTrendData = @json($referralTrendData);
const referralTrendGranularity = @json($referralTrendGranularity);

const referralCanvas = document.getElementById('referralTrendChart');

if (referralCanvas) {
    new Chart(referralCanvas.getContext('2d'), {
        type: 'line',

        data: {
            labels: referralTrendLabels,

            datasets: [{
                label: 'Referrals',
                data: referralTrendData,

                borderColor: palette.emerald,

                backgroundColor: context => {
                    const gradient = context.chart.ctx.createLinearGradient(
                        0,
                        0,
                        0,
                        160
                    );

                    gradient.addColorStop(
                        0,
                        'rgba(5, 150, 105, 0.15)'
                    );

                    gradient.addColorStop(
                        1,
                        'rgba(5, 150, 105, 0)'
                    );

                    return gradient;
                },

                borderWidth: 2.5,
                tension: 0.4,
                fill: true,

                pointBackgroundColor: palette.emerald,
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 5,
            }]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            plugins: {
                legend: {
                    display: false
                },

                tooltip: {
                    ...sharedTooltip,

                    callbacks: {
                        label: function(context) {
                            const count = context.parsed.y;

                            return `${count} referral${count === 1 ? '' : 's'}`;
                        }
                    }
                }
            },

            scales: {
                y: {
                    beginAtZero: true,

                    ticks: {
                        font: baseFont,
                        color: '#94a3b8',
                        precision: 0,
                        stepSize: 1
                    },

                    grid: {
                        color: palette.gridLine
                    },

                    border: {
                        display: false
                    }
                },

                x: {
                    grid: {
                        display: false
                    },

                    ticks: {
                        font: {
                            ...baseFont,
                            size: 10
                        },

                        color: '#94a3b8',

                        autoSkip: referralTrendGranularity === 'day',

                        maxTicksLimit:
                            referralTrendGranularity === 'day'
                                ? 12
                                : 12
                    },

                    border: {
                        display: false
                    }
                }
            }
        }
    });
}


});
</script>
</x-app-layout>
