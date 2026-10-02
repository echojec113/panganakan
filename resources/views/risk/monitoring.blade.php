<x-app-layout>
<style>
    /* Page-local containment: layout follows usable width after the sidebar. */
    .risk-dashboard { min-width: 0; overflow-wrap: anywhere; }
    .risk-dashboard .grid > *, .risk-dashboard .dash-card, .risk-dashboard .kpi-card { min-width: 0; }
    .risk-dashboard .chart-wrap { position: relative; width: 100%; min-width: 0; max-width: 100%; }
    .risk-dashboard .chart-wrap canvas { max-width: 100%; }
    .risk-dashboard .dash-card-header { flex-wrap: wrap; gap: 12px; }
    .risk-dashboard .dashboard-toolbar { flex-wrap: wrap; }
    .risk-dashboard .dashboard-filters { min-width: 0; max-width: 100%; }
    .risk-dashboard .dashboard-filters > div { min-width: 0; max-width: 100%; }
    .risk-dashboard .dashboard-filters select { min-width: 0; width: 100%; max-width: 100%; }
    @media (max-width: 639px) {
        .risk-dashboard .dashboard-filters { width: 100%; }
        .risk-dashboard .dashboard-filters > div { flex: 1 1 100%; width: 100%; }
    }
</style>
    
    <div class="risk-dashboard min-h-screen bg-[#FCFBF8]">
    <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8 py-8">
        
        <!-- Page Header - Responsive -->
        <div class="mb-8">
            <div class="dashboard-toolbar flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <x-app-header
                    title="Risk Monitoring Dashboard"
                    subtitle="Risk trends and factors affecting patient risk classification"
                >
                    <x-slot name="actions">
                        <svg class="hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </x-slot>
                </x-app-header>
                <div class="text-xs sm:text-sm text-gray-500">
                    Last updated: {{ now()->format('M d, Y g:i a') }}
                </div>
            </div>
        </div>

        <!-- Risk Analytics -->
        <div class="mb-6 sm:mb-8 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="border-b border-gray-100 px-4 sm:px-6 py-4 bg-gray-50">
                <div class="dashboard-toolbar flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h3 class="text-base sm:text-lg font-semibold text-gray-800">Risk Analytics</h3>
                        <p id="riskAnalyticsSubtitle" class="text-xs sm:text-sm text-gray-500">{{ $analytics['month'] ? \Carbon\Carbon::create($analytics['year'], $analytics['month'], 1)->format('F Y') : $analytics['year'] }}</p>
                    </div>
                    <fieldset class="w-full min-w-0 sm:ml-auto sm:w-auto">
                        <legend class="mb-2 text-xs font-semibold text-gray-600">Reporting Filters</legend>
                        <div class="dashboard-filters flex flex-wrap gap-3">
                        <div class="w-36 max-w-full">
                        <label for="riskAnalyticsType" class="mb-1 block text-xs font-medium text-gray-600">Risk Type</label>
                        <select id="riskAnalyticsType" class="h-11 w-full pl-3 pr-10 py-2 text-sm border border-gray-200 rounded-lg bg-white focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                            <option value="HIGH" {{ ($analytics['riskType'] ?? 'HIGH') === 'HIGH' ? 'selected' : '' }}>High Risk</option>
                            <option value="LOW" {{ ($analytics['riskType'] ?? 'HIGH') === 'LOW' ? 'selected' : '' }}>Low Risk</option>
                        </select>
                        </div>
                        <div class="w-28 max-w-full">
                        <label for="riskAnalyticsYear" class="mb-1 block text-xs font-medium text-gray-600">Year</label>
                        <select id="riskAnalyticsYear" class="h-11 w-full pl-3 pr-10 py-2 text-sm border border-gray-200 rounded-lg bg-white focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A]">
                            @foreach($availableYears as $year)
                                <option value="{{ $year }}" {{ $analytics['year'] === $year ? 'selected' : '' }}>{{ $year }}</option>
                            @endforeach
                        </select>
                        </div>
                        <div class="w-40 max-w-full">
                        <label for="riskAnalyticsMonth" class="mb-1 block text-xs font-medium text-gray-600">Month</label>
                        <select id="riskAnalyticsMonth" class="h-11 w-full pl-3 pr-10 py-2 text-sm border border-gray-200 rounded-lg bg-white focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                            <option value="">All Months</option>
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" {{ ($analytics['month'] ?? null) === $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                            @endfor
                        </select>
                        </div>
                        </div>
                        <span id="riskAnalyticsLoading" role="status" class="text-xs text-gray-500" style="display:none;">Loading&hellip;</span>
                    </fieldset>
                </div>
            </div>

            <div class="p-4 sm:p-6 space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Selected Risk Assessments</p>
                        <p id="selectedRiskAssessments" class="mt-1 text-2xl font-bold text-gray-800">{{ $analytics['summary']['selectedRiskAssessments'] }}</p>
                        <p id="selectedRiskAssessmentsSubtitle" class="mt-1 text-xs text-slate-500">{{ $analytics['riskType'] === 'LOW' ? 'Low-Risk' : 'High-Risk' }} assessments in selected period</p>
                    </div>
                    <div class="rounded-xl border border-amber-100 bg-amber-50 p-4">
                        <p class="text-xs font-semibold text-amber-800 uppercase tracking-wider">Assessment Incomplete</p>
                        <p id="incompleteAssessments" class="mt-1 text-2xl font-bold text-amber-800">{{ $analytics['summary']['incompleteAssessments'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">Incomplete assessments in selected period</p>
                    </div>
                </div>
                <!-- Risk Trend (full width) -->
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <p id="riskTrendTitle" class="text-sm font-semibold text-gray-700">{{ ($analytics['riskType'] ?? 'HIGH') === 'LOW' ? 'Low-Risk Patient Trend' : 'High-Risk Patient Trend' }}</p>
                        <span id="riskTrendEmpty" class="text-xs text-gray-400" style="display:none;">No risk assessment data available.</span>
                    </div>
                    <div class="chart-wrap" style="height:260px;">
                        <canvas id="riskHighRiskTrendChart"></canvas>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                        <p id="riskSummaryHighestTitle" class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Busiest High-Risk Month</p>
                        <p id="riskSummaryHighest" class="text-xl font-bold text-gray-800 mt-1">—</p>
                        <p id="riskSummaryHighestSub" class="text-xs font-medium text-slate-500 mt-1" style="display:none;">—</p>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                        <p id="riskSummaryConditionTitle" class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Most Common High-Risk Factor</p>
                        <p id="riskSummaryCondition" class="text-xl font-bold text-gray-800 mt-1">—</p>
                    </div>
                </div>
            </div>

        <!-- Risk Analytics Breakdown -->
        <div id="riskAnalyticsBreakdown" @if($analytics['riskType'] === 'LOW') hidden @endif class="border-t border-gray-100">
            <div class="border-b border-gray-100 px-4 sm:px-6 py-4 bg-gray-50">
                <div>
                    <h3 class="text-base sm:text-lg font-semibold text-gray-800">High-Risk Factors</h3>
                </div>
            </div>

            <div class="p-4 sm:p-6 space-y-6">
                <div class="rounded-xl border border-gray-100 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <p class="text-sm font-semibold text-gray-700">Top Factors Triggering High Risk</p>
                        <span id="topHighRiskConditionsEmpty" class="text-xs text-gray-400" style="display:none;">No data available.</span>
                    </div>
                    <div class="overflow-x-auto" tabindex="0" role="region" aria-label="High-risk factor chart; scroll horizontally for more detail"><div class="min-w-[28rem]"><div class="chart-wrap" style="height:260px;">
                        <canvas id="topHighRiskConditionsChart"></canvas>
                    </div></div></div>
                </div>
            </div>
        </div>

        </div>
    </div>
    </div>

    {{-- Risk Analytics Charts --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const initialAnalytics = {!! json_encode($analytics ?? []) !!};

        const palette = {
            blue: '#2563eb', emerald: '#059669', amber: '#d97706',
            violet: '#7c3aed', red: '#dc2626', slate: '#64748b',
            gridLine: 'rgba(0,0,0,0.04)',
        };

        const baseFont = { family: "'DM Sans', sans-serif", size: 12 };

        const sharedTooltip = {
            backgroundColor: '#0f172a',
            titleFont: { ...baseFont, size: 12, weight: '600' },
            bodyFont: { ...baseFont, size: 12 },
            padding: 10,
            cornerRadius: 8,
            displayColors: true,
            boxWidth: 10, boxHeight: 10, boxPadding: 4,
        };

        const charts = {};

        function destroyChart(id) {
            if (charts[id]) {
                charts[id].destroy();
                delete charts[id];
            }
        }

        function makeChart(id, config) {
            destroyChart(id);
            const canvas = document.getElementById(id);
            if (!canvas) return;
            charts[id] = new Chart(canvas.getContext('2d'), config);
        }

        function toggleEmpty(canvasId, emptyId, hasData) {
            const canvas = document.getElementById(canvasId);
            const empty = document.getElementById(emptyId);
            if (canvas) canvas.style.display = hasData ? 'block' : 'none';
            if (empty) empty.style.display = hasData ? 'none' : 'block';
        }

        function setText(id, value) {
            const el = document.getElementById(id);
            if (el) el.textContent = value || '—';
        }

        let currentAnalytics = initialAnalytics;
        function renderAnalytics(analytics) {
            currentAnalytics = analytics;
            const trend = analytics.trend;
            const isLowRisk = analytics.riskType === 'LOW';
            const riskLabel = isLowRisk ? 'Low-Risk' : 'High-Risk';
            document.getElementById('selectedRiskAssessments').textContent = analytics.summary.selectedRiskAssessments;
            document.getElementById('incompleteAssessments').textContent = analytics.summary.incompleteAssessments;
            setText('selectedRiskAssessmentsSubtitle', riskLabel + ' assessments in selected period');
            const isDaily = trend.granularity === 'day';
            const period = analytics.month
                ? new Date(analytics.year, analytics.month - 1, 1).toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
                : String(analytics.year);
            setText('riskAnalyticsSubtitle', period);
            setText('riskTrendTitle', riskLabel + ' Patient Trend');
            setText('riskTrendEmpty', 'No ' + riskLabel.toLowerCase() + ' assessments for the selected period.');
            setText('riskSummaryHighestTitle', 'Busiest ' + riskLabel + (isDaily ? ' Day' : ' Month'));
            const highest = analytics.summary?.highestRiskPeriod;
            setText('riskSummaryHighest', highest && highest.count > 0 ? highest.label : 'No assessments');
            const highestSub = document.getElementById('riskSummaryHighestSub');
            highestSub.style.display = highest && highest.count > 0 ? 'block' : 'none';
            highestSub.textContent = highest ? highest.count + (highest.count === 1 ? ' assessment' : ' assessments') : '';
            const factors = analytics.topHighRiskConditions || [];
            setText('riskSummaryConditionTitle', isLowRisk ? 'No High-Risk Factor Applicable' : 'Most Common High-Risk Factor');
            setText('riskSummaryCondition', isLowRisk ? 'Not applicable to Low-Risk assessments' : (factors[0]?.label || 'No factors recorded'));
            document.getElementById('riskAnalyticsBreakdown').hidden = isLowRisk;
            const trendSeries = trend.data;
            destroyChart('riskHighRiskTrendChart');
            destroyChart('topHighRiskConditionsChart');
            const trendColor = isLowRisk ? palette.emerald : palette.red;
            const trendFill = isLowRisk ? 'rgba(5,150,102,0.14)' : 'rgba(220,38,38,0.14)';
            const trendTotal = trendSeries.reduce((a, b) => a + b, 0);
            const hasTrend = trendTotal > 0;
            toggleEmpty('riskHighRiskTrendChart', 'riskTrendEmpty', hasTrend);
            if (hasTrend) {
                makeChart('riskHighRiskTrendChart', {
                    type: 'line',
                    data: {
                        labels: trend.labels,
                        datasets: [{
                            label: isLowRisk ? 'Low Risk' : 'High Risk',
                            data: trendSeries,
                            borderColor: trendColor,
                            backgroundColor: (c) => {
                                const g = c.chart.ctx.createLinearGradient(0, 0, 0, 260);
                                g.addColorStop(0, trendFill);
                                g.addColorStop(1, isLowRisk ? 'rgba(5,150,102,0)' : 'rgba(220,38,38,0)');
                                return g;
                            },
                            borderWidth: 2.5,
                            tension: 0.45,
                            fill: true,
                            pointBackgroundColor: trendColor,
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        plugins: { legend: { display: false }, tooltip: sharedTooltip },
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: { color: palette.gridLine },
                                ticks: { font: baseFont, color: '#94a3b8', precision: 0, maxTicksLimit: 5 },
                                border: { display: false },
                            },
                            x: {
                                grid: { display: false },
                                ticks: { font: baseFont, color: '#94a3b8', precision: 0 },
                                border: { display: false },
                            }
                        }
                    }
                });
            }

            const topHighRiskConditions = analytics.topHighRiskConditions || [];
            const hasTopHighRiskConditions = !isLowRisk && topHighRiskConditions.length > 0;
            toggleEmpty('topHighRiskConditionsChart', 'topHighRiskConditionsEmpty', hasTopHighRiskConditions);
            if (hasTopHighRiskConditions) {
                makeChart('topHighRiskConditionsChart', {
                    type: 'bar',
                    data: {
                        labels: topHighRiskConditions.map((item) => item.label),
                        datasets: [
                            { label: 'High-Risk Assessments', data: topHighRiskConditions.map((item) => item.count), backgroundColor: palette.red, borderRadius: 4, borderSkipped: false },
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        indexAxis: 'y',
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
                                beginAtZero: true,
                                grid: { color: palette.gridLine },
                                ticks: { font: baseFont, color: '#94a3b8' },
                                border: { display: false },
                            }
                        }
                    }
                });
            }
        }

        renderAnalytics(initialAnalytics);

        const monthSelect = document.getElementById('riskAnalyticsMonth');
        const yearSelect = document.getElementById('riskAnalyticsYear');
        const riskTypeSelect = document.getElementById('riskAnalyticsType');
        const loading = document.getElementById('riskAnalyticsLoading');
        let pendingRequest;
        let requestVersion = 0;

        async function loadAnalytics() {
            const version = ++requestVersion;
            pendingRequest?.abort();
            pendingRequest = new AbortController();
            loading.style.display = 'inline';
            loading.textContent = 'Loading...';
            const params = new URLSearchParams({ year: yearSelect.value, month: monthSelect.value, risk_type: riskTypeSelect.value });
            try {
                const response = await fetch('{{ route('risk.monitoring.analytics') }}?' + params, { signal: pendingRequest.signal });
                if (!response.ok) throw new Error('Analytics request failed');
                const analytics = await response.json();
                if (version !== requestVersion) return;
                renderAnalytics(analytics);
                loading.style.display = 'none';
            } catch (error) {
                if (version !== requestVersion || error.name === 'AbortError') return;
                yearSelect.value = String(currentAnalytics.year);
                monthSelect.value = currentAnalytics.month == null ? '' : String(currentAnalytics.month);
                riskTypeSelect.value = currentAnalytics.riskType;
                loading.textContent = 'Unable to load analytics. Previous selection restored; please try again.';
            }
        }
        [yearSelect, monthSelect, riskTypeSelect].forEach(select => select.addEventListener('change', loadAnalytics));

    });
    </script>
</x-app-layout>