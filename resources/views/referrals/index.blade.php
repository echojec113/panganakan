<x-app-layout>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">

    {{-- Header --}}
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 24px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">Referral Management</h1>
        <p style="font-size: 14px; color: var(--text-muted);">Track and manage patient referrals to hospitals and specialists</p>
    </div>

    {{-- Success Message --}}
    @if(session('success'))
        <div style="margin-bottom: 20px; padding: 14px 16px; background: #d1fae5; border: 1px solid #6ee7b7; border-radius: 11px; color: #065f46; font-size: 14px;">
            {{ session('success') }}
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="ra-grid-3">
        {{-- Total --}}
        <div style="background: white; border: 1px solid var(--border); border-radius: 12px; padding: 18px; box-shadow: 0 1px 3px rgba(30,70,140,0.06);">
            <p style="font-size: 11px; color: var(--text-muted); font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 8px;">Total Referrals</p>
            <p style="font-size: 28px; font-weight: 700; color: var(--blue-accent);">{{ $total }}</p>
        </div>

        {{-- Pending --}}
        <div style="background: white; border: 1px solid var(--border); border-radius: 12px; padding: 18px; box-shadow: 0 1px 3px rgba(30,70,140,0.06);">
            <p style="font-size: 11px; color: var(--text-muted); font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 8px;">Pending</p>
            <p style="font-size: 28px; font-weight: 700; color: #f59e0b;">{{ $pending }}</p>
        </div>

        {{-- Completed --}}
        <div style="background: white; border: 1px solid var(--border); border-radius: 12px; padding: 18px; box-shadow: 0 1px 3px rgba(30,70,140,0.06);">
            <p style="font-size: 11px; color: var(--text-muted); font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 8px;">Completed</p>
            <p style="font-size: 28px; font-weight: 700; color: #10b981;">{{ $completed }}</p>
        </div>
    </div>

    {{-- Analytics Section --}}
    <div class="ra-card" style="margin-bottom: 24px; overflow: hidden;">
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between;">
            <div>
                <p style="font-size: 15px; font-weight: 700; color: var(--text-primary); margin-bottom: 2px;">Referral Analytics</p>
                <p id="referralAnalyticsSubtitle" style="font-size: 12px; color: var(--text-muted);">Showing referral analytics for {{ $analytics['year'] ?? now()->year }}</p>
            </div>
            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <label for="referralAnalyticsMonth" style="font-size: 12px; color: var(--text-muted);">Month</label>
                <select id="referralAnalyticsMonth" style="padding: 8px 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 13px; background: white;">
                    <option value="">All Months</option>
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ ($analytics['month'] ?? null) === $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                    @endfor
                </select>
                <span id="referralAnalyticsLoading" style="font-size: 12px; color: var(--text-muted); display: none;">Loading&hellip;</span>
            </div>
        </div>

        <div style="padding: 20px;">
            {{-- Summary Cards --}}
            <div class="ra-grid-4">
                <div class="ra-box">
                    <p class="ra-title">Most Referred Hospital</p>
                    <p id="referralSummaryHospital" class="ra-value">—</p>
                </div>
                <div class="ra-box">
                    <p class="ra-title">Completion Rate</p>
                    <p id="referralSummaryRate" class="ra-value">—</p>
                </div>
                <div class="ra-box">
                    <p class="ra-title" id="referralSummaryBusiestTitle">Busiest Month</p>
                    <p id="referralSummaryBusiest" class="ra-value">—</p>
                    <p id="referralSummaryBusiestSub" class="ra-value-sub" style="display:none;">—</p>
                </div>
                <div class="ra-box">
                    <p class="ra-title">Most Common Reason</p>
                    <p id="referralSummaryReason" class="ra-value">—</p>
                </div>
            </div>

            {{-- 2-col charts: trend + status --}}
            <div class="ra-grid-2">
                <div class="ra-chart-card">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <p class="ra-label" style="margin-bottom: 0;">Referrals by Month</p>
                        <span id="referralTrendEmpty" class="ra-empty">No referral data available.</span>
                    </div>
                    <div class="ra-chart" style="margin-top: 10px;"><canvas id="referralTrendChart"></canvas></div>
                </div>
                <div class="ra-chart-card">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <p class="ra-label" style="margin-bottom: 0;">Pending vs Completed</p>
                        <span id="referralStatusEmpty" class="ra-empty">No data available.</span>
                    </div>
                    <div class="ra-chart" style="margin-top: 10px;"><canvas id="referralStatusChart"></canvas></div>
                </div>
            </div>

            {{-- 2-col charts: destinations + reasons --}}
            <div class="ra-grid-2">
                <div class="ra-chart-card">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <p class="ra-label" style="margin-bottom: 0;">Top Destinations</p>
                        <span id="referralDestinationsEmpty" class="ra-empty">No data available.</span>
                    </div>
                    <div class="ra-chart" style="margin-top: 10px;"><canvas id="referralDestinationsChart"></canvas></div>
                </div>
                <div class="ra-chart-card">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <p class="ra-label" style="margin-bottom: 0;">Referral Reasons</p>
                        <span id="referralReasonsEmpty" class="ra-empty">No data available.</span>
                    </div>
                    <div class="ra-chart" style="margin-top: 10px;"><canvas id="referralReasonsChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    {{-- Table Card --}}
    <div style="background: white; border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 1px 3px rgba(30,70,140,0.06); overflow: hidden;">

        {{-- Search & Filter Bar --}}
        <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: grid; grid-template-columns: 1fr 140px 120px; gap: 12px; align-items: center;">
            {{-- Search --}}
            <div style="position: relative;">
                <svg style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 15px; height: 15px; color: var(--text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <form method="GET" action="{{ route('referrals.index') }}" style="display: contents;">
                    <input type="text" name="search" placeholder="Search by patient name..." value="{{ request('search') }}"
                        style="width: 100%; padding: 10px 12px 10px 36px; border: 1px solid var(--border); border-radius: 9px; font-size: 13px; background: var(--surface);">
                </form>
            </div>

            {{-- Status Filter --}}
            <form method="GET" action="{{ route('referrals.index') }}" style="display: contents;">
                <select name="status" onchange="this.form.submit()"
                    style="padding: 10px 12px; border: 1px solid var(--border); border-radius: 9px; font-size: 13px; background: white;">
                    <option value="all" {{ request('status') === 'all' || !request('status') ? 'selected' : '' }}>All Status</option>
                    <option value="Pending" {{ request('status') === 'Pending' ? 'selected' : '' }}>Pending</option>
                    <option value="Completed" {{ request('status') === 'Completed' ? 'selected' : '' }}>Completed</option>
                </select>
            </form>

            {{-- Clear Filters --}}
            @if(request('search') || request('status'))
                <a href="{{ route('referrals.index') }}"
                    style="padding: 10px 12px; border: 1px solid #ef4444; border-radius: 9px; font-size: 13px; color: #ef4444; text-decoration: none; text-align: center;">
                    Clear Filters
                </a>
            @endif
        </div>

        {{-- Table --}}
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="background: var(--bg-base); border-bottom: 1px solid var(--border);">
                        <th style="padding: 14px 16px; text-align: left; font-weight: 600; color: var(--text-muted); text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">Patient Name</th>
                        <th style="padding: 14px 16px; text-align: left; font-weight: 600; color: var(--text-muted); text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">Referred To</th>
                        <th style="padding: 14px 16px; text-align: left; font-weight: 600; color: var(--text-muted); text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">Reason</th>
                        <th style="padding: 14px 16px; text-align: left; font-weight: 600; color: var(--text-muted); text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">Date</th>
                        <th style="padding: 14px 16px; text-align: left; font-weight: 600; color: var(--text-muted); text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">Status</th>
                        <th style="padding: 14px 16px; text-align: right; font-weight: 600; color: var(--text-muted); text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($referrals as $referral)
                        <tr style="border-bottom: 1px solid var(--border); transition: background 0.15s;">
                            {{-- Patient --}}
                            <td style="padding: 14px 16px; color: var(--text-primary); font-weight: 500;">
                                {{ $referral->patient->first_name }} {{ $referral->patient->last_name }}
                            </td>

                            {{-- Referred To --}}
                            <td style="padding: 14px 16px; color: var(--text-primary);">
                                {{ $referral->referred_to }}
                            </td>

                            {{-- Reason --}}
                            <td style="padding: 14px 16px; color: var(--text-muted); font-size: 12px;">
                                {{ \Str::limit($referral->reason, 50) }}
                            </td>

                            {{-- Date --}}
                            <td style="padding: 14px 16px; color: var(--text-primary);">
                                {{ $referral->referral_date->format('M d, Y') }}
                            </td>

                            {{-- Status --}}
                            <td style="padding: 14px 16px;">
                                @if($referral->status === 'Pending')
                                    <span style="display: inline-block; padding: 6px 12px; background: #fef3c7; color: #92400e; border-radius: 8px; font-size: 11px; font-weight: 600;">Pending</span>
                                @elseif($referral->status === 'Completed')
                                    <span style="display: inline-block; padding: 6px 12px; background: #d1fae5; color: #065f46; border-radius: 8px; font-size: 11px; font-weight: 600;">Completed</span>
                                @else
                                    <span style="display: inline-block; padding: 6px 12px; background: #fee2e2; color: #991b1b; border-radius: 8px; font-size: 11px; font-weight: 600;">Cancelled</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td style="padding: 14px 16px; text-align: right;">
                                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                    {{-- Print --}}
                                    <a href="{{ route('referrals.print', $referral->id) }}"
                                        style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; background: var(--blue-accent); color: white; border-radius: 8px; text-decoration: none; font-size: 12px; font-weight: 500; transition: background 0.15s;">
                                        Print
                                    </a>

                                    {{-- Complete --}}
                                    @if($referral->status === 'Pending')
                                        <form method="POST" action="{{ route('referrals.complete', $referral->id) }}" style="display: contents;">
                                            @csrf
                                            <button type="submit" onclick="return confirm('Mark this referral as completed?')"
                                                style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; background: #10b981; color: white; border-radius: 8px; border: none; font-size: 12px; font-weight: 500; cursor: pointer; transition: background 0.15s;">
                                                Complete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 40px 16px; text-align: center; color: var(--text-muted);">
                                No referrals found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($referrals->hasPages())
        <div style="padding: 16px 20px; border-top: 1px solid var(--border); background: var(--bg-base);">
            {{ $referrals->links() }}
        </div>
        @endif

    </div>

</div>

<style>
    .ra-card { background: white; border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 1px 3px rgba(30,70,140,0.06); }
    .ra-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
    .ra-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 20px; }
    .ra-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
    .ra-box {
        background: var(--bg-base);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 14px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-height: 92px;
        height: 100%;
    }
    .ra-title { font-size: 11px; color: var(--text-muted); font-weight: 600; letter-spacing: 0.4px; text-transform: uppercase; margin-bottom: 6px; }
    .ra-value { font-size: 14px; font-weight: 600; color: var(--text-primary); word-break: break-word; }
    .ra-value-sub { font-size: 12px; font-weight: 500; color: var(--text-muted); margin-top: 2px; }
    .ra-label { font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 10px; }
    .ra-empty { font-size: 12px; color: var(--text-muted); display: none; text-align: right; }
    .ra-chart { position: relative; height: 260px; }
    .ra-chart-card {
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 14px;
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .ra-chart-card .ra-label { line-height: 1.3; }

    @media (max-width: 1100px) {
        .ra-grid-3 { grid-template-columns: repeat(2, 1fr); }
        .ra-grid-4 { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
        .ra-grid-3 { grid-template-columns: 1fr; }
        .ra-grid-4 { grid-template-columns: 1fr; }
        .ra-grid-2 { grid-template-columns: 1fr; }
    }
</style>

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
