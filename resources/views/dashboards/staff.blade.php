<x-app-layout>
<style>
    .ops-root { font-family: 'DM Sans', sans-serif; background: #FCFBF8; min-height: 100vh; }
    .ops-header {
    background: rgba(255,255,255,.97);
    border-bottom: 1px solid #e8eaf0;
}
    .kpi-card, .dash-card { background: #fff; border: 1px solid #e8eaf0; border-radius: 16px; }
    .kpi-card { padding: 20px; position: relative; overflow: hidden; }
    .kpi-card::before { content: ''; position: absolute; inset: 0 0 auto; height: 3px; border-radius: 16px 16px 0 0; }
    .kpi-blue::before { background: linear-gradient(90deg,#55B85A,#86efac); }
    .kpi-emerald::before { background: linear-gradient(90deg,#059669,#34d399); }
    .kpi-amber::before { background: linear-gradient(90deg,#d97706,#fbbf24); }
    .kpi-red::before { background: linear-gradient(90deg,#dc2626,#f87171); }
    .kpi-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
    .dash-card { overflow: hidden; }
    .dash-card-header { padding: 18px 20px 14px; border-bottom: 1px solid #f1f3f7; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .section-title { font-size: 15px; font-weight: 700; color: #0f172a; }
    .section-sub { font-size: 12px; color: #94a3b8; margin-top: 1px; }
    .list-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 20px; border-bottom: 1px solid #f7f8fa; text-decoration: none; }
    .list-row:last-child { border-bottom: 0; }
    .avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0; }
    .avatar-amber { background: #fffbeb; color: #b45309; }
    .avatar-violet { background: #f5f3ff; color: #7c3aed; }
    .avatar-slate { background: #f1f5f9; color: #475569; }
    .badge { font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 20px; white-space: nowrap; }
    .badge-red { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .badge-amber { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
    .badge-green { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .badge-slate { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .mono { font-family: 'DM Mono', monospace; }
    .chart-wrap { height: 280px; position: relative; }
    .empty-state { padding: 40px 20px; text-align: center; color: #94a3b8; }

    /* Page-local containment: layout follows usable width after the sidebar. */
    .ops-root { min-width: 0; overflow-wrap: anywhere; }
    .ops-root .grid > *, .ops-root .dash-card, .ops-root .kpi-card { min-width: 0; }
    .ops-root .chart-wrap { position: relative; width: 100%; min-width: 0; max-width: 100%; }
    .ops-root .chart-wrap canvas { max-width: 100%; }
    .ops-root .dash-card-header { flex-wrap: wrap; gap: 12px; }
    .ops-root .dashboard-toolbar { flex-wrap: wrap; }
    .ops-root .dashboard-filters { min-width: 0; max-width: 100%; }
    .ops-root .dashboard-filters > div { min-width: 0; max-width: 100%; }
    .ops-root .dashboard-filters select { min-width: 0; width: 100%; max-width: 100%; }
    @media (max-width: 639px) {
        .ops-root .dashboard-filters { width: 100%; }
        .ops-root .dashboard-filters > div { flex: 1 1 100%; width: 100%; }
    }

    .ops-root .dashboard-content { container-type: inline-size; container-name: staff-dashboard; }
    @container staff-dashboard (min-width: 960px) {
        .ops-root .dashboard-kpis { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }

    .ops-root .list-row { flex-wrap: wrap; }
    .ops-root .list-row > * { min-width: 0; max-width: 100%; }
    .ops-root .badge { max-width: 100%; white-space: normal; overflow-wrap: anywhere; }
    @container staff-dashboard (min-width: 900px) {
        .ops-root .dashboard-priority { grid-template-columns: minmax(0, 3fr) minmax(280px, 2fr); }
        .ops-root .dashboard-schedule { grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); }
    }
    @container staff-dashboard (min-width: 1200px) {
        .ops-root .dashboard-priority { grid-template-columns: minmax(0, 7fr) minmax(320px, 3fr); }
    }
</style>

<div class="ops-root">
    <div class="ops-header">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="dashboard-toolbar flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2 mb-0.5"><span class="w-2 h-2 rounded-full bg-[#55B85A] animate-pulse"></span><span class="text-xs font-semibold text-[#19355F] tracking-widest uppercase">Daily Operations</span></div>
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Operations Center</h1>
                    <p class="text-sm text-slate-400 mt-0.5">{{ Carbon\Carbon::today()->format('l, F j, Y') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('patients.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-medium shadow-sm">New Patient</a>
                    <a href="{{ route('prenatal-visits.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#55B85A] text-white text-sm font-medium shadow-sm">Record Visit</a>
                </div>
            </div>
        </div>
    </div>

    <main class="dashboard-content max-w-screen-xl mx-auto px-2 sm:px-6 lg:px-8 py-7 space-y-6">
        {{-- Four summary cards --}}
        <section class="dashboard-kpis grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="kpi-card kpi-red">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">High Risk</p>
                <p data-testid="staff-high-count" class="text-3xl font-bold text-red-600 mono mt-2">{{ $staffHighRiskCount }}</p>
                <p class="text-xs text-slate-400 mt-1">Latest assessments, ongoing patients</p>
            </div>
            <div class="kpi-card kpi-emerald">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Low Risk</p>
                <p data-testid="staff-low-count" class="text-3xl font-bold text-green-600 mono mt-2">{{ $staffLowRiskCount }}</p>
                <p class="text-xs text-slate-400 mt-1">Latest assessments, ongoing patients</p>
            </div>
            <div class="kpi-card kpi-blue">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Visits Today</p>
                <p class="text-3xl font-bold text-slate-900 mono mt-2">{{ $patientsToday }}</p>
                <p class="text-xs text-slate-400 mt-1">Visits recorded today</p>
            </div>
            <div class="kpi-card kpi-blue">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Ongoing Patients</p>
            <p class="text-3xl font-bold text-slate-900 mono mt-2">{{ $activePatients }}</p>
            <p class="text-xs text-slate-400 mt-1">Currently under prenatal care</p>
            </div>
        </section>

        {{-- Patients for review and age distribution --}}
<section class="dashboard-priority grid grid-cols-1 gap-6">

    {{-- Patients for Review --}}
    <div class="dash-card">
        <div class="dash-card-header">
    <div>
        <p class="section-title">High-Risk Patients</p>
        <p class="section-sub">Ongoing patients whose latest assessment is HIGH</p>
    </div>
</div>

        <div class="max-h-80 overflow-y-auto">
            @forelse($highRiskAlerts as $visit)
                @php
                    $source = $visit->decision_source;

                    $sourceLabel = match($source) {
                        'COMPLETENESS' => 'Completeness Check',
                        'RULE_BASED' => 'Clinical Rules',
                        'MACHINE_LEARNING' => 'Machine Learning',
                        'MACHINE_LEARNING_INVALID' => 'ML Assessment Unavailable',
                        null => 'Legacy Assessment',
                        default => $source,
                    };

                    /*
                     * Use existing assessment evidence only.
                     * Do not calculate or create new clinical findings here.
                     */
                    $structuredFactors = \App\ValueObjects\ClinicalFactorEvidence::normalizeList(
                        $visit->factor_evidence
                    );

                    $reviewFindings = collect();

                    if (!empty($structuredFactors)) {
                        $reviewFindings = collect($structuredFactors)
                            ->map(fn ($factor) =>
                                $factor['label']
                                ?? $factor['code']
                                ?? 'Clinical finding'
                            )
                            ->filter()
                            ->values();
                    } elseif ($source === 'RULE_BASED' && !empty($visit->rule_reasons)) {
                        $reviewFindings = collect($visit->rule_reasons)
                            ->filter()
                            ->values();
                    } elseif (!empty($visit->risk_reasons)) {
                        $reviewFindings = collect($visit->risk_reasons)
                            ->filter()
                            ->values();
                    }

                    $visibleFindings = $reviewFindings->take(2);
                    $additionalFindings = max(0, $reviewFindings->count() - 2);
                @endphp

                <a
    href="{{ route('patients.show', $visit->patient) }}"
    class="list-row group"
>
    <div class="min-w-0 flex-1">

        {{-- Patient name --}}
        <p class="text-sm font-semibold text-slate-800 [overflow-wrap:anywhere]">
            {{ $visit->patient->first_name }}
            {{ $visit->patient->last_name }}
        </p>

        {{-- Visit date + risk factors --}}
        <div class="mt-1 flex flex-col sm:flex-row sm:flex-wrap sm:items-start sm:gap-6">

            <p class="text-xs text-slate-400 shrink-0">
                Last visit:
                {{ \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') }}
            </p>

            <div class="text-xs min-w-0">
                @if($visibleFindings->isNotEmpty())
                    <span class="font-semibold text-slate-500">
                        Risk factors:
                    </span>

                    <span class="text-slate-600">
                        {{ $visibleFindings->implode(' · ') }}
                    </span>

                    @if($additionalFindings > 0)
                        <span class="text-slate-400">
                            · +{{ $additionalFindings }} more
                        </span>
                    @endif
                @else
                    <span class="text-slate-400">
                        Risk details available in patient record
                    </span>
                @endif
            </div>

        </div>
    </div>

    {{-- Whole row is clickable --}}
    <div
        class="shrink-0 self-center ml-3 text-slate-300 group-hover:text-slate-500 transition-colors"
        aria-hidden="true"
    >
        <svg
            xmlns="http://www.w3.org/2000/svg"
            class="w-4 h-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M9 5l7 7-7 7"
            />
        </svg>
    </div>
</a>

            @empty
                <div class="empty-state">
                    No patients currently require high-risk assessment review.
                </div>
            @endforelse
        </div>
    </div>
    {{-- Follow-Up Attention --}}
<div class="dash-card">
    <div class="dash-card-header">
        <div>
            <p class="section-title">Follow-Up Attention</p>
            <p class="section-sub">Return visits requiring attention</p>
        </div>
    </div>

    <div class="px-5 py-6">
        <div class="rounded-xl border border-red-100 bg-red-50/50 px-4 py-5">
            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-700">
                        Overdue Follow-Ups
                    </p>

                    <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                        Scheduled return dates that have already passed
                    </p>
                </div>

                <span class="shrink-0 min-w-[44px] text-center text-2xl font-bold text-red-700">
                    {{ $followUpOverdueCount }}
                </span>
            </div>
        </div>

        <p class="mt-3 text-xs leading-relaxed text-slate-400">
            Based on the latest prenatal visit of ongoing patients.
        </p>
    </div>
</div>

    

</section>

        {{-- Follow-up schedule and recent visits --}}
        <section class="dashboard-schedule grid grid-cols-1 gap-6">
            <div class="dash-card"><div class="dash-card-header"><div><p class="section-title">Follow-Up Schedule</p><p class="section-sub">Patients with upcoming return visits</p></div></div><div class="max-h-96 overflow-y-auto">
                @forelse($followUpTasks as $task)
                    <a href="{{ route('patients.show', $task->patient) }}" class="list-row"><div class="flex items-center gap-3 min-w-0"><div class="avatar avatar-violet">{{ strtoupper(substr($task->patient->first_name,0,1)) }}{{ strtoupper(substr($task->patient->last_name,0,1)) }}</div><div class="min-w-0"><p class="text-sm font-semibold text-slate-800 [overflow-wrap:anywhere]">{{ $task->patient->first_name }} {{ $task->patient->last_name }}</p><p class="text-xs text-slate-400">Return visit: {{ $task->next_visit_date ? Carbon\Carbon::parse($task->next_visit_date)->format('M d, Y') : 'Not scheduled' }}</p></div></div></a>
                @empty<div class="empty-state">No follow-ups pending.</div>@endforelse
            </div></div>
            <div class="dash-card"><div class="dash-card-header"><div><p class="section-title">Recent Visits</p><p class="section-sub">Today &amp; yesterday</p></div></div><div class="max-h-96 overflow-y-auto">
                @forelse($recentVisits as $visit)
                    <a href="{{ route('patients.show', $visit->patient) }}" class="list-row"><div class="flex items-center gap-3 min-w-0"><div class="avatar avatar-slate">{{ strtoupper(substr($visit->patient->first_name,0,1)) }}{{ strtoupper(substr($visit->patient->last_name,0,1)) }}</div><div class="min-w-0"><p class="text-sm font-semibold text-slate-800 [overflow-wrap:anywhere]">{{ $visit->patient->first_name }} {{ $visit->patient->last_name }}</p><p class="text-xs text-slate-400">{{ \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') }}</p></div></div><span class="badge {{ $visit->risk_level === 'HIGH' ? 'badge-red' : 'badge-green' }}">{{ $visit->risk_level }}</span></a>
                @empty<div class="empty-state">No recent visits.</div>@endforelse
            </div></div>
        </section>

        

        {{-- Risk Monitoring Dashboard / Risk Analytics --}}
<section class="mb-6 sm:mb-8 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

    {{-- Dashboard Header --}}
    <div class="px-4 sm:px-6 py-4 bg-gray-50 dashboard-toolbar flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h3 class="text-base sm:text-lg font-semibold text-gray-800">
                Risk Monitoring Dashboard
            </h3>
            <p class="text-xs sm:text-sm text-gray-500">
                Clinical risk assessments with decision-source explainability
            </p>
        </div>

        <div class="text-xs sm:text-sm text-gray-500">
            Last updated: {{ now()->format('M d, Y g:i a') }}
        </div>
    </div>

    {{-- Analytics Header / Filters --}}
    <div class="border-t border-b border-gray-100 px-4 sm:px-6 py-4">
                <div class="dashboard-toolbar flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h3 class="text-base sm:text-lg font-semibold text-gray-800">Risk Analytics</h3>
                        <p id="staffAnalyticsSubtitle" class="text-xs sm:text-sm text-gray-500">Showing risk analytics for {{ data_get($analytics, 'year', now()->year) }}</p>
                    </div>
                    <div class="dashboard-filters flex items-end gap-3 flex-wrap">

    <div>
        <label for="staffRiskType"
               class="block text-xs sm:text-sm text-gray-600 font-medium mb-1">
            Risk Type
        </label>

        <select id="staffRiskType"
                class="min-w-[130px] pr-10 pl-4 py-2 text-sm border border-gray-200 rounded-lg bg-white focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
            <option value="HIGH"
                @selected(data_get($analytics, 'riskType', 'HIGH') === 'HIGH')>
                High Risk
            </option>

            <option value="LOW"
                @selected(data_get($analytics, 'riskType', 'HIGH') === 'LOW')>
                Low Risk
            </option>
        </select>
    </div>

    <div>
        <label for="staffRiskYear"
               class="block text-xs sm:text-sm text-gray-600 font-medium mb-1">
            Year
        </label>

        <select id="staffRiskYear"
                class="min-w-[120px] pr-10 pl-4 py-2 text-sm border border-gray-200 rounded-lg bg-white focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
            @for($year = now()->year; $year >= now()->year - 5; $year--)
                <option value="{{ $year }}"
                    @selected((int) data_get($analytics, 'year', now()->year) === $year)>
                    {{ $year }}
                </option>
            @endfor
        </select>
    </div>

    <div>
        <label for="staffRiskMonth"
               class="block text-xs sm:text-sm text-gray-600 font-medium mb-1">
            Month
        </label>

        <select id="staffRiskMonth"
                class="min-w-[150px] pr-10 pl-4 py-2 text-sm border border-gray-200 rounded-lg bg-white focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
            <option value="">All Months</option>

            @for($m = 1; $m <= 12; $m++)
                <option value="{{ $m }}"
                    @selected((string) data_get($analytics, 'month', '') === (string) $m)>
                    {{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}
                </option>
            @endfor
        </select>
    </div>

    <span id="staffAnalyticsLoading"
          class="text-xs text-gray-400 pb-2"
          style="display:none;">
        Loading&hellip;
    </span>

</div>
                </div>
            </div>
            <div class="p-4 sm:p-6 space-y-6">
                <div><p id="staffTrendTitle" class="text-sm font-semibold text-gray-700 mb-2">{{ data_get($analytics, 'riskType', 'HIGH') === 'LOW' ? 'Low-Risk Patients' : 'High-Risk Patients' }}</p><div class="chart-wrap"><canvas id="staffHighRiskTrendChart"></canvas><p id="staffHighRiskTrendEmpty" class="empty-state absolute inset-0" style="display:none;">No risk assessment data available.</p></div></div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4"><p id="staffHighestTitle" class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Highest High-Risk Month</p><p id="staffHighestValue" class="text-xl font-bold text-gray-800 mt-1">{{ data_get($analytics, 'summary.highestHighRiskPeriod.label') ? data_get($analytics, 'summary.highestHighRiskPeriod.label') . ' · ' . data_get($analytics, 'summary.highestHighRiskPeriod.count') : '—' }}</p><p id="staffHighestSub" class="text-xs text-slate-500 mt-1" style="display:none;"></p></div>
                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4"><p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Most Common Condition</p><p id="staffConditionValue" class="text-xl font-bold text-gray-800 mt-1">{{ data_get($analytics, 'summary.mostCommonCondition.name', '—') }}</p></div>
                </div>
            </div>
        


    <div id="staffHighRiskBreakdownHeader"
     class="border-b border-gray-100 px-4 sm:px-6 py-4 bg-gray-50">
        <div>
            <h3 class="text-base sm:text-lg font-semibold text-gray-800">
                Risk Analytics Breakdown
            </h3>
            <p class="text-xs sm:text-sm text-gray-500">
                Factors associated with high-risk assessments
            </p>
        </div>
    </div>

    <div id="staffHighRiskBreakdownBody" class="p-4 sm:p-6">

        

        {{-- High-Risk Factors --}}
        <div class="rounded-xl border border-gray-100 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                <p class="text-sm font-semibold text-gray-700">
                    Top Factors Triggering High Risk
                </p>

                <span id="staffTopHighRiskConditionsEmpty"
                      class="text-xs text-gray-400"
                      style="display:none;">
                     No high-risk factors recorded for the selected period.
                </span>
            </div>

            <p class="text-xs text-gray-400 mb-4">
                Most frequently recorded clinical factors among high-risk assessments.
            </p>

            <div class="overflow-x-auto" tabindex="0" role="region" aria-label="High-risk factor chart; scroll horizontally for more detail"><div class="min-w-[28rem]"><div class="chart-wrap" style="height:260px;">
                <canvas id="staffTopHighRiskConditionsChart"></canvas>
            </div></div></div>
        </div>

    </div>
</section>

        
        
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    
    let analytics = {!! json_encode($analytics ?? []) !!};
    const palette = ['#2563eb', '#059669', '#d97706', '#7c3aed', '#dc2626'];
    const charts = {};
    const tooltip = { backgroundColor: '#0f172a', padding: 10, cornerRadius: 8 };
    const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value || '—'; };
    const render = (id, config, emptyId, hasData) => {
        const canvas = document.getElementById(id), empty = document.getElementById(emptyId);
        if (charts[id]) charts[id].destroy();
        if (empty) empty.style.display = hasData ? 'none' : 'block';
        if (canvas) canvas.style.display = hasData ? 'block' : 'none';
        if (canvas && hasData) charts[id] = new Chart(canvas.getContext('2d'), config);
    };
    function renderAnalytics(data) {
        analytics = data || {};

const isLowRisk = analytics.riskType === 'LOW';

const breakdownHeader = document.getElementById('staffHighRiskBreakdownHeader');
const breakdownBody = document.getElementById('staffHighRiskBreakdownBody');

if (breakdownHeader) {
    breakdownHeader.style.display = isLowRisk ? 'none' : 'block';
}

if (breakdownBody) {
    breakdownBody.style.display = isLowRisk ? 'none' : 'block';
}

const labels = analytics.labels || [];
        const trend = analytics.riskTrend || analytics.highRiskTrend || [];
        const summary = analytics.summary || {};
        const subtitle = document.getElementById('staffAnalyticsSubtitle');
        const trendTitle = document.getElementById('staffTrendTitle');
        const selectedMonthName = analytics.month
            ? new Date(analytics.year, analytics.month - 1, 1)
                .toLocaleString('en-US', {
                    month: 'long',
                    year: 'numeric'
        })
            : null;

            if (subtitle) {
    subtitle.textContent = analytics.month
        ? 'Showing risk analytics for ' + selectedMonthName
        : 'Showing risk analytics for ' + (analytics.year || '');
}

if (trendTitle) {
    trendTitle.textContent = isLowRisk
        ? 'Low-Risk Patients'
        : 'High-Risk Patients';
}

const highest = summary.highestRiskPeriod;
const highestTitle = document.getElementById('staffHighestTitle');
const highestSub = document.getElementById('staffHighestSub');
const selectedMonthTotal = trend.reduce(
    (total, value) => total + Number(value || 0),
    0
);
const hasRiskData = selectedMonthTotal > 0;

        if (analytics.month) {
    if (highestTitle) {
        highestTitle.textContent = 'Busiest Day';
    }

    setText(
        'staffHighestValue',
        hasRiskData && highest
    ? highest.label + ' · ' + highest.count
    : null
    );

    if (highestSub) {
        highestSub.style.display = 'block';

        if (highest && highest.count > 0) {
            highestSub.textContent =
                selectedMonthTotal + ' ' +
                (selectedMonthTotal === 1 ? 'Assessment this month' : 'Assessments this month');
        } else {
            highestSub.textContent = '';
            highestSub.style.display = 'none';
        }
    }
} else {
    if (highestTitle) {
        highestTitle.textContent = isLowRisk
        ? 'Busiest Low-Risk Month'
        : 'Busiest High-Risk Month';
    }

    setText(
    'staffHighestValue',
    hasRiskData && highest
        ? highest.label + ' · ' + highest.count
        : null
);

        if (highestSub) {
        highestSub.style.display = 'none';
    }
}

        setText(
    'staffConditionValue',
    hasRiskData && summary.mostCommonCondition
        ? summary.mostCommonCondition.name
        : null
);

const trendColor = isLowRisk ? '#059669' : '#dc2626';

render(
    'staffHighRiskTrendChart',
    {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: isLowRisk ? 'Low Risk' : 'High Risk',
                data: trend,
                borderColor: trendColor,
                backgroundColor: isLowRisk
                    ? 'rgba(5,150,102,.14)'
                    : 'rgba(220,38,38,.14)',
                borderWidth: 2.5,
                pointRadius: 3,
                pointHoverRadius: 6,
                pointBorderWidth: 2,
                pointBackgroundColor: '#ffffff',
                tension: .45,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0,
                        stepSize: 1
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        autoSkip: true,
                        maxTicksLimit: analytics.month ? 10 : 12
                    }
                }
            }
        }
    },
    'staffHighRiskTrendEmpty',
    labels.length > 0 && trend.some(Number)
);
        
       
        
        const top = (analytics.topHighRiskConditions || []).slice(0, 5);
        render('staffTopHighRiskConditionsChart', { type: 'bar', data: { labels: top.map((item) => item.label), datasets: [{ label: 'High-Risk Assessments', data: top.map((item) => item.count), backgroundColor: '#dc2626', borderRadius: 4, borderSkipped: false }] }, options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip }, scales: {
    x: {
        beginAtZero: true,
        ticks: {
            precision: 0,
            stepSize: 1
        }
    },
    y: {
        grid: {
            display: false
        }
    }
} } }, 'staffTopHighRiskConditionsEmpty', top.length > 0);
    }
    renderAnalytics(analytics);
    


    
    const yearSelect = document.getElementById('staffRiskYear');
    const monthSelect = document.getElementById('staffRiskMonth');
    const typeSelect = document.getElementById('staffRiskType');
    const loading = document.getElementById('staffAnalyticsLoading');

const refresh = () => {
    if (loading) loading.style.display = 'inline';

    const params = new URLSearchParams({
        year: yearSelect.value,
        month: monthSelect.value,
        risk_type: typeSelect.value
    });

    fetch('{{ route('risk.monitoring.analytics', [], false) }}?' + params.toString())
        .then((response) => response.json())
        .then(renderAnalytics)
        .finally(() => {
            if (loading) loading.style.display = 'none';
        });
};

if (yearSelect) yearSelect.addEventListener('change', refresh);
if (monthSelect) monthSelect.addEventListener('change', refresh);
if (typeSelect) typeSelect.addEventListener('change', refresh);
});
</script>
</x-app-layout>
