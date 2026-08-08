<x-app-layout>

@php
    $hasPendingAny = $pending > 0;
    $sourceLabel = function ($referral) {
        return ($referral->prenatal_visit_id && is_array($referral->assessment_snapshot) && count($referral->assessment_snapshot) > 0)
            ? 'Assessment-linked'
            : 'Manual Referral';
    };
    $sourceIndigo = function ($referral) {
        return ($referral->prenatal_visit_id && is_array($referral->assessment_snapshot) && count($referral->assessment_snapshot) > 0);
    };
    $statusColor = [
        'Pending'   => 'bg-amber-50 text-amber-700 ring-amber-200',
        'Completed' => 'bg-green-50 text-green-700 ring-green-200',
        'Refused'   => 'bg-orange-50 text-orange-700 ring-orange-200',
        'Cancelled' => 'bg-gray-100 text-gray-600 ring-gray-200',
    ];
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    {{-- Header --}}
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-800">Referral Management</h1>
        <p class="mt-1 text-sm text-gray-500">Track referral decisions and clinical follow-through.</p>
    </div>

    @if(session('success'))
        <div class="mb-6 rounded-2xl border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    {{-- Action Required Summary (Pending is the visual lead; others quiet) --}}
    <div class="mb-8 rounded-2xl border border-gray-100 bg-white shadow-sm overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-stretch divide-y sm:divide-y-0 sm:divide-x divide-gray-100">
            <div class="flex-1 p-6 sm:p-5">
                @if($hasPendingAny)
                    <div class="text-xs font-semibold uppercase tracking-widest text-amber-600">Action Required</div>
                    <p class="mt-2 text-2xl font-bold leading-none text-amber-700">{{ $pending }}</p>
                    <p class="mt-1 text-sm font-medium text-gray-700">Pending Referrals</p>
                    <p class="mt-2 text-xs text-gray-400">Referrals awaiting clinic follow-through.</p>
                @else
                    <div class="text-xs font-semibold uppercase tracking-widest text-green-600">No Action Required</div>
                    <p class="mt-2 text-2xl font-bold leading-none text-green-700">0</p>
                    <p class="mt-1 text-sm font-medium text-gray-700">Pending Referrals</p>
                    <p class="mt-2 text-xs text-gray-400">No referrals awaiting follow-through.</p>
                @endif
            </div>

            <div class="flex-1 p-6 sm:p-5">
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <p class="text-xl font-semibold text-green-600">{{ $completed }}</p>
                        <p class="mt-1 text-xs font-medium uppercase tracking-wide text-gray-500">Completed</p>
                    </div>
                    <div>
                        <p class="text-xl font-semibold text-orange-600">{{ $refused }}</p>
                        <p class="mt-1 text-xs font-medium uppercase tracking-wide text-gray-500">Refused</p>
                    </div>
                    <div>
                        <p class="text-xl font-semibold text-gray-500">{{ $cancelled }}</p>
                        <p class="mt-1 text-xs font-medium uppercase tracking-wide text-gray-500">Cancelled</p>
                    </div>
                </div>
            </div>

            <div class="w-full sm:w-40 p-6 sm:p-5 flex items-center justify-between sm:justify-center gap-4">
                <span class="text-xs font-medium uppercase tracking-wide text-gray-400">Total</span>
                <span class="text-xl font-semibold text-gray-600">{{ $total }}</span>
            </div>
        </div>
    </div>

    {{-- Operational Referral Management --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-8">

        {{-- Search & Filter Bar --}}
        <div class="px-4 sm:px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row gap-3 sm:items-center">
            <div class="relative flex-1">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <form method="GET" action="{{ route('referrals.index') }}">
                    <input type="text" name="search" placeholder="Search by patient name..."
                        value="{{ request('search') }}"
                        class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </form>
            </div>

            <div class="flex items-center gap-2 sm:gap-3">
                <form method="GET" action="{{ route('referrals.index') }}" class="w-full sm:w-auto">
                    <select name="status" onchange="this.form.submit()"
                        class="w-full sm:w-auto px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-400">
                        <option value="all" {{ request('status') === 'all' || !request('status') ? 'selected' : '' }}>All Status</option>
                        <option value="Pending" {{ request('status') === 'Pending' ? 'selected' : '' }}>Pending</option>
                        <option value="Completed" {{ request('status') === 'Completed' ? 'selected' : '' }}>Completed</option>
                        <option value="Refused" {{ request('status') === 'Refused' ? 'selected' : '' }}>Refused</option>
                        <option value="Cancelled" {{ request('status') === 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
                    </select>
                </form>
                @if(request('search') || request('status'))
                    <a href="{{ route('referrals.index') }}"
                        class="px-3 py-2 text-sm border border-red-200 text-red-600 rounded-lg hover:bg-red-50 transition whitespace-nowrap">
                        Clear
                    </a>
                @endif
            </div>
        </div>

        {{-- Mobile Cards --}}
        <div class="lg:hidden divide-y divide-gray-100">
            @forelse($referrals as $referral)
                <div class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <a href="{{ route('patients.show', $referral->patient_id) }}" class="text-sm font-semibold text-gray-800 hover:text-blue-600">
                                {{ $referral->patient->first_name }} {{ $referral->patient->last_name }}
                            </a>
                            <p class="text-xs text-gray-500 truncate mt-0.5">
                                {{ $referral->referred_to }}
                                <span class="text-gray-400">·</span>
                                {{ $referral->referral_date->format('M d, Y') }}
                            </p>
                            @if($referral->prenatal_visit_id)
                                <p class="text-[11px] text-gray-400 mt-0.5">Visit: {{ $referral->prenatalVisit?->visit_date?->format('M d, Y') ?? $referral->referral_date->format('M d, Y') }}</p>
                            @endif
                        </div>
                        <span class="inline-flex items-center rounded-full ring-1 ring-inset px-2.5 py-0.5 text-[11px] font-semibold shrink-0 {{ $statusColor[$referral->status] ?? 'bg-gray-100 text-gray-600 ring-gray-200' }}">
                            {{ $referral->status }}
                        </span>
                    </div>

                    <p class="mt-2 text-xs text-gray-600 line-clamp-2">{{ \Str::limit($referral->reason, 50) }}</p>

                    <div class="mt-2 flex items-center gap-2">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $sourceIndigo($referral) ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ $sourceLabel($referral) }}
                        </span>
                        @if($referral->status === 'Refused' && $referral->refusal_recorded_at)
                            <span class="text-[11px] text-gray-400">Recorded {{ $referral->refusal_recorded_at->format('M d') }}</span>
                        @elseif($referral->status === 'Completed' && $referral->completed_at)
                            <span class="text-[11px] text-gray-400">Completed {{ $referral->completed_at->format('M d, Y') }}</span>
                        @endif
                    </div>

                    <div class="mt-3 flex items-center gap-2">
                        <a href="{{ route('referrals.show', $referral->id) }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition">
                            View Referral
                        </a>
                        <a href="{{ route('referrals.print', $referral->id) }}"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-50 transition">
                            Print
                        </a>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-500">No referrals found.</div>
            @endforelse
        </div>

        {{-- Desktop Table --}}
        <div class="hidden lg:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destination</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referral Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($referrals as $referral)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <a href="{{ route('patients.show', $referral->patient_id) }}" class="font-medium hover:text-blue-600">
                                    {{ $referral->patient->first_name }} {{ $referral->patient->last_name }}
                                </a>
                                @if($referral->prenatal_visit_id)
                                    <div class="text-[11px] text-gray-400">Visit: {{ $referral->prenatalVisit?->visit_date?->format('M d, Y') ?? $referral->referral_date->format('M d, Y') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-800">{{ $referral->referred_to }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{{ $referral->referral_date->format('M d, Y') }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ \Str::limit($referral->reason, 50) }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $sourceIndigo($referral) ? 'bg-indigo-50 text-indigo-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $sourceLabel($referral) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full ring-1 ring-inset px-2.5 py-0.5 text-[11px] font-semibold {{ $statusColor[$referral->status] ?? 'bg-gray-100 text-gray-600 ring-gray-200' }}">
                                    {{ $referral->status }}
                                </span>
                                @if($referral->status === 'Refused' && $referral->refusal_recorded_at)
                                    <div class="text-[11px] text-gray-400 mt-0.5">Recorded {{ $referral->refusal_recorded_at->format('M d') }}</div>
                                @elseif($referral->status === 'Completed' && $referral->completed_at)
                                    <div class="text-[11px] text-gray-400 mt-0.5">{{ $referral->completed_at->format('M d, Y') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-2">
                                    <a href="{{ route('referrals.show', $referral->id) }}"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition">
                                        View Referral
                                    </a>
                                    <a href="{{ route('referrals.print', $referral->id) }}"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 border border-gray-200 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-50 transition">
                                        Print
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-500">No referrals found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($referrals->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-100 bg-gray-50">
                {{ $referrals->links() }}
            </div>
        @endif
    </div>

    {{-- Referral Analytics (below operational management) --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-8">
        <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap gap-3 items-center justify-between">
            <div>
                <p class="text-sm font-semibold text-gray-800">Referral Analytics</p>
                <p id="referralAnalyticsSubtitle" class="text-xs text-gray-500 mt-0.5">Showing referral analytics for {{ $analytics['year'] ?? now()->year }}</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <label for="referralAnalyticsMonth" class="text-xs text-gray-500">Month</label>
                <select id="referralAnalyticsMonth" class="px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Months</option>
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ ($analytics['month'] ?? null) === $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                    @endfor
                </select>
                <span id="referralAnalyticsLoading" class="text-xs text-gray-500" style="display:none;">Loading&hellip;</span>
            </div>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="rounded-xl bg-gray-50 border border-gray-100 p-4">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Most Referred Hospital</p>
                    <p id="referralSummaryHospital" class="mt-1 text-sm font-semibold text-gray-800">—</p>
                </div>
                <div class="rounded-xl bg-gray-50 border border-gray-100 p-4">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Completion Rate</p>
                    <p id="referralSummaryRate" class="mt-1 text-sm font-semibold text-gray-800">—</p>
                </div>
                <div class="rounded-xl bg-gray-50 border border-gray-100 p-4">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500" id="referralSummaryBusiestTitle">Busiest Month</p>
                    <p id="referralSummaryBusiest" class="mt-1 text-sm font-semibold text-gray-800">—</p>
                    <p id="referralSummaryBusiestSub" class="text-xs text-gray-400 mt-0.5" style="display:none;">—</p>
                </div>
                <div class="rounded-xl bg-gray-50 border border-gray-100 p-4">
                    <p class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Most Common Reason</p>
                    <p id="referralSummaryReason" class="mt-1 text-sm font-semibold text-gray-800">—</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
                <div class="rounded-xl border border-gray-100 p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-700">Referrals by Month</p>
                        <span id="referralTrendEmpty" class="text-xs text-gray-400" style="display:none;">No referral data available.</span>
                    </div>
                    <div style="height: 260px; margin-top: 10px;"><canvas id="referralTrendChart"></canvas></div>
                </div>
                <div class="rounded-xl border border-gray-100 p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-700">Pending vs Completed</p>
                        <span id="referralStatusEmpty" class="text-xs text-gray-400" style="display:none;">No data available.</span>
                    </div>
                    <div style="height: 260px; margin-top: 10px;"><canvas id="referralStatusChart"></canvas></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="rounded-xl border border-gray-100 p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-700">Top Destinations</p>
                        <span id="referralDestinationsEmpty" class="text-xs text-gray-400" style="display:none;">No data available.</span>
                    </div>
                    <div style="height: 260px; margin-top: 10px;"><canvas id="referralDestinationsChart"></canvas></div>
                </div>
                <div class="rounded-xl border border-gray-100 p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-700">Referral Reasons</p>
                        <span id="referralReasonsEmpty" class="text-xs text-gray-400" style="display:none;">No data available.</span>
                    </div>
                    <div style="height: 260px; margin-top: 10px;"><canvas id="referralReasonsChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

</div>

{{-- Referral Analytics Charts --}}
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

    function renderAnalytics(analytics) {
        const summary = analytics.summary || {};
        const isSingleMonth = !!analytics.month;
        const subtitleEl = document.getElementById('referralAnalyticsSubtitle');

        if (subtitleEl) {
            subtitleEl.textContent = isSingleMonth
                ? 'Showing referral analytics for ' + ((analytics.labels || [])[0] || 'the selected month')
                : 'Showing referral analytics for ' + (analytics.year || '');
        }

        const noDataMessage = isSingleMonth
            ? 'No referral data for the selected month.'
            : 'No referral data available.';

        ['referralTrendEmpty', 'referralStatusEmpty', 'referralDestinationsEmpty', 'referralReasonsEmpty'].forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.textContent = noDataMessage;
        });

        setText('referralSummaryHospital', summary.mostReferredHospital ? summary.mostReferredHospital.label : null);
        setText('referralSummaryRate', typeof summary.completionRate === 'number' ? summary.completionRate.toFixed(1) + '%' : null);
        setText('referralSummaryReason', summary.mostCommonReason ? summary.mostCommonReason.label : null);

        const busiestTitleEl = document.getElementById('referralSummaryBusiestTitle');
        const busiestSubEl = document.getElementById('referralSummaryBusiestSub');
        if (isSingleMonth) {
            const monthLabel = (analytics.labels || [])[0] || '—';
            const monthCount = (analytics.referralTrend || [])[0] || 0;
            if (busiestTitleEl) busiestTitleEl.textContent = 'Selected Month Referrals';
            setText('referralSummaryBusiest', monthLabel);
            if (busiestSubEl) {
                busiestSubEl.style.display = 'block';
                busiestSubEl.textContent = monthCount + (monthCount === 1 ? ' Referral' : ' Referrals');
            }
        } else {
            if (busiestTitleEl) busiestTitleEl.textContent = 'Busiest Month';
            if (busiestSubEl) busiestSubEl.style.display = 'none';
            setText('referralSummaryBusiest', summary.busiestPeriod ? summary.busiestPeriod.label + ' · ' + summary.busiestPeriod.count : null);
        }

        const trendTotal = (analytics.referralTrend || []).reduce((a, b) => a + b, 0);
        const hasTrend = isSingleMonth ? trendTotal > 0 : (analytics.labels || []).length > 0;
        toggleEmpty('referralTrendChart', 'referralTrendEmpty', hasTrend);
        if (hasTrend) {
            makeChart('referralTrendChart', {
                type: 'line',
                data: {
                    labels: analytics.labels,
                    datasets: [{
                        label: 'Referrals',
                        data: analytics.referralTrend,
                        borderColor: palette.blue,
                        backgroundColor: (c) => {
                            const g = c.chart.ctx.createLinearGradient(0, 0, 0, 260);
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
                    plugins: { legend: { display: false }, tooltip: sharedTooltip },
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
        }

        const status = analytics.statusTrend || { pending: [], completed: [] };
        const hasStatus = hasTrend && (status.pending.concat(status.completed).reduce((a, b) => a + b, 0) > 0);
        toggleEmpty('referralStatusChart', 'referralStatusEmpty', hasStatus);
        if (hasStatus) {
            makeChart('referralStatusChart', {
                type: 'bar',
                data: {
                    labels: analytics.labels,
                    datasets: [
                        { label: 'Pending', data: status.pending, backgroundColor: palette.amber, borderRadius: 6, borderSkipped: false },
                        { label: 'Completed', data: status.completed, backgroundColor: palette.emerald, borderRadius: 6, borderSkipped: false },
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { font: baseFont, color: '#64748b', boxWidth: 10 } }, tooltip: sharedTooltip },
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
        }

        const dests = analytics.destinations || [];
        toggleEmpty('referralDestinationsChart', 'referralDestinationsEmpty', dests.length > 0);
        if (dests.length > 0) {
            makeChart('referralDestinationsChart', {
                type: 'bar',
                data: {
                    labels: dests.map((d) => d.label),
                    datasets: [{
                        label: 'Referrals',
                        data: dests.map((d) => d.count),
                        backgroundColor: palette.emerald,
                        borderRadius: 6, borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, indexAxis: 'y',
                    plugins: { legend: { display: false }, tooltip: sharedTooltip },
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
        }

        const reasons = analytics.reasons || [];
        toggleEmpty('referralReasonsChart', 'referralReasonsEmpty', reasons.length > 0);
        if (reasons.length > 0) {
            makeChart('referralReasonsChart', {
                type: 'bar',
                data: {
                    labels: reasons.map((d) => d.label),
                    datasets: [{
                        label: 'Referrals',
                        data: reasons.map((d) => d.count),
                        backgroundColor: palette.violet,
                        borderRadius: 6, borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, indexAxis: 'y',
                    plugins: { legend: { display: false }, tooltip: sharedTooltip },
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
        }
    }

    renderAnalytics(initialAnalytics);

    const monthSelect = document.getElementById('referralAnalyticsMonth');
    const loading = document.getElementById('referralAnalyticsLoading');

    function loadAnalytics() {
        if (!monthSelect) return;

        const month = monthSelect.value;
        if (loading) loading.style.display = 'inline';

        fetch('{{ route('referrals.analytics') }}?month=' + encodeURIComponent(month))
            .then((r) => r.json())
            .then(renderAnalytics)
            .catch(() => { /* keep previous charts on failure */ })
            .finally(() => { if (loading) loading.style.display = 'none'; });
    }

    if (monthSelect) monthSelect.addEventListener('change', loadAnalytics);
});
</script>

</x-app-layout>