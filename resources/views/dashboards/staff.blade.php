<x-app-layout>
<style>
    .ops-root { font-family: 'DM Sans', sans-serif; background: #FCFBF8; min-height: 100vh; }
    .ops-header { background: rgba(255,255,255,.97); border-bottom: 1px solid #e8eaf0; position: sticky; top: 64px; z-index: 20; }
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
</style>

<div class="ops-root">
    <div class="ops-header">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2 mb-0.5"><span class="w-2 h-2 rounded-full bg-[#55B85A] animate-pulse"></span><span class="text-xs font-semibold text-[#19355F] tracking-widest uppercase">Daily Operations</span></div>
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Operations Center</h1>
                    <p class="text-sm text-slate-400 mt-0.5">{{ Carbon\Carbon::today()->format('l, F j, Y') }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('patients.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-medium shadow-sm">New Patient</a>
                    <a href="{{ route('prenatal-visits.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#55B85A] text-white text-sm font-medium shadow-sm">Record Visit</a>
                </div>
            </div>
        </div>
    </div>

    <main class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-7 space-y-6">
        {{-- Four summary cards --}}
        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="kpi-card kpi-red">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">High Risk</p>
                <p data-testid="staff-high-count" class="text-3xl font-bold text-red-600 mono mt-2">{{ $staffHighRiskCount }}</p>
                <p class="text-xs text-slate-400 mt-1">Requires clinical review</p>
                <a href="{{ route('dashboard', ['risk_filter' => 'HIGH']) }}#patient-assessments" class="inline-block mt-2 text-xs font-semibold text-red-600 hover:text-red-800 underline">View High &rarr;</a>
            </div>
            <div class="kpi-card kpi-emerald">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Low Risk</p>
                <p data-testid="staff-low-count" class="text-3xl font-bold text-green-600 mono mt-2">{{ $staffLowRiskCount }}</p>
                <p class="text-xs text-slate-400 mt-1">No elevated-risk findings</p>
                <a href="{{ route('dashboard', ['risk_filter' => 'LOW']) }}#patient-assessments" class="inline-block mt-2 text-xs font-semibold text-green-600 hover:text-green-800 underline">View Low &rarr;</a>
            </div>
            <div class="kpi-card kpi-blue">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Patients Today</p>
                <p class="text-3xl font-bold text-slate-900 mono mt-2">{{ $patientsToday }}</p>
                <p class="text-xs text-slate-400 mt-1">Visits recorded today</p>
            </div>
            <div class="kpi-card kpi-red">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Priority Alerts</p>
                <p class="text-3xl font-bold text-red-600 mono mt-2">{{ count($highRiskAlerts) }}</p>
                <p class="text-xs text-slate-400 mt-1">Needs attention</p>
            </div>
        </section>

        {{-- Priority alerts and age distribution --}}
        <section class="grid grid-cols-1 lg:grid-cols-10 gap-6">
            <div class="dash-card lg:col-span-7">
                <div class="dash-card-header"><div><p class="section-title">Priority Alerts</p><p class="section-sub">Immediate clinical attention required</p></div><span class="badge badge-red">{{ count($highRiskAlerts) }} Active</span></div>
                <div class="max-h-80 overflow-y-auto">
                    @forelse($highRiskAlerts as $visit)
                        @php
                            $source = $visit->decision_source;
                            $sourceLabel = match($source) { 'COMPLETENESS' => 'Completeness Check', 'RULE_BASED' => 'Clinical Rules', 'MACHINE_LEARNING' => 'Machine Learning', 'MACHINE_LEARNING_INVALID' => 'ML Assessment Unavailable', null => 'Legacy Assessment', default => $source };
                        @endphp
                        <a href="{{ route('patients.show', $visit->patient) }}" class="list-row">
                            <div class="flex items-center gap-3 min-w-0"><div class="avatar avatar-amber">{{ strtoupper(substr($visit->patient->first_name,0,1)) }}{{ strtoupper(substr($visit->patient->last_name,0,1)) }}</div><div class="min-w-0"><p class="text-sm font-semibold text-slate-800 truncate">{{ $visit->patient->first_name }} {{ $visit->patient->last_name }}</p><p class="text-xs text-slate-400">Last visit: {{ \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') }}</p><p class="text-xs text-orange-600 mt-0.5">{{ $sourceLabel }}</p></div></div>
                            <span class="badge badge-red">HIGH</span>
                        </a>
                    @empty
                        <div class="empty-state">No priority alerts today.</div>
                    @endforelse
                </div>
            </div>
            <div class="dash-card lg:col-span-3">
                <div class="dash-card-header"><div><p class="section-title">Age Distribution</p><p class="section-sub">Current risk-monitoring population</p></div></div>
                <div class="p-4"><div class="chart-wrap"><canvas id="staffAgeDistributionChart"></canvas><p id="staffAgeDistributionEmpty" class="empty-state absolute inset-0" style="display:none;">No age data available.</p></div></div>
            </div>
        </section>

        {{-- Risk Monitoring Dashboard header --}}
        <section class="mb-6 sm:mb-8 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="border-b border-gray-100 px-4 sm:px-6 py-4 bg-gray-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h3 class="text-base sm:text-lg font-semibold text-gray-800">Risk Monitoring Dashboard</h3>
                    <p class="text-xs sm:text-sm text-gray-500">Clinical risk assessments with decision-source explainability</p>
                </div>
                <div class="text-xs sm:text-sm text-gray-500">Last updated: {{ now()->format('M d, Y g:i a') }}</div>
            </div>
        </section>

        {{-- Risk analytics --}}
        <section class="mb-6 sm:mb-8 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="border-b border-gray-100 px-4 sm:px-6 py-4 bg-gray-50">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h3 class="text-base sm:text-lg font-semibold text-gray-800">Risk Analytics</h3>
                        <p id="staffAnalyticsSubtitle" class="text-xs sm:text-sm text-gray-500">Showing risk analytics for {{ data_get($analytics, 'year', now()->year) }}</p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <label for="staffRiskType" class="text-xs sm:text-sm text-gray-600 font-medium">Risk Type</label>
                        <select id="staffRiskType" class="px-4 py-2 text-sm border border-gray-200 rounded-lg bg-white focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                            <option value="HIGH" @selected(data_get($analytics, 'riskType', 'HIGH') === 'HIGH')>High Risk</option>
                            <option value="LOW" @selected(data_get($analytics, 'riskType', 'HIGH') === 'LOW')>Low Risk</option>
                        </select>
                        <label for="staffRiskMonth" class="text-xs sm:text-sm text-gray-600 font-medium">Month</label>
                        <select id="staffRiskMonth" class="px-4 py-2 text-sm border border-gray-200 rounded-lg bg-white focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                            <option value="">All Months</option>
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" @selected((string) data_get($analytics, 'month', '') === (string) $m)>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                            @endfor
                        </select>
                        <span id="staffAnalyticsLoading" class="text-xs text-gray-400" style="display:none;">Loading&hellip;</span>
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
        </section>

        {{-- Risk analytics breakdown --}}
        <section class="mb-6 sm:mb-8 bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="border-b border-gray-100 px-4 sm:px-6 py-4 bg-gray-50">
                <div>
                    <h3 class="text-base sm:text-lg font-semibold text-gray-800">Risk Analytics Breakdown</h3>
                    <p class="text-xs sm:text-sm text-gray-500">Monthly risk trends, maternal conditions, patient ages, and high-risk factors</p>
                </div>
            </div>
            <div class="p-4 sm:p-6 space-y-6">
                <div class="rounded-xl border border-gray-100 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm font-semibold text-gray-700">Maternal Conditions by Month</p>
                        <span id="staffMaternalConditionsEmpty" class="text-xs text-gray-400" style="display:none;">No data available.</span>
                    </div>
                    <p class="text-xs text-gray-400 mb-2">Hypertension, Diabetes, and Anemia are examples of maternal conditions tracked over time — see <span class="font-medium text-gray-500">Top 5 Conditions Triggering High Risk</span> below for the broader range of clinical factors used in risk assessment.</p>
                    <div class="chart-wrap" style="height:260px;"><canvas id="staffMaternalConditionsChart"></canvas></div>
                </div>
                <div class="rounded-xl border border-gray-100 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-sm font-semibold text-gray-700">Top 5 Conditions Triggering High Risk</p>
                        <span id="staffTopHighRiskConditionsEmpty" class="text-xs text-gray-400" style="display:none;">No data available.</span>
                    </div>
                    <div class="chart-wrap" style="height:260px;"><canvas id="staffTopHighRiskConditionsChart"></canvas></div>
                </div>
            </div>
        </section>

        {{-- Follow-up schedule and recent visits --}}
        <section class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <div class="dash-card lg:col-span-3"><div class="dash-card-header"><div><p class="section-title">Follow-Up Schedule</p><p class="section-sub">Patients with upcoming return visits</p></div></div><div class="max-h-96 overflow-y-auto">
                @forelse($followUpTasks as $task)
                    <a href="{{ route('patients.show', $task->patient) }}" class="list-row"><div class="flex items-center gap-3 min-w-0"><div class="avatar avatar-violet">{{ strtoupper(substr($task->patient->first_name,0,1)) }}{{ strtoupper(substr($task->patient->last_name,0,1)) }}</div><div class="min-w-0"><p class="text-sm font-semibold text-slate-800 truncate">{{ $task->patient->first_name }} {{ $task->patient->last_name }}</p><p class="text-xs text-slate-400">Return visit: {{ $task->next_visit_date ? Carbon\Carbon::parse($task->next_visit_date)->format('M d, Y') : 'Not scheduled' }}</p></div></div></a>
                @empty<div class="empty-state">No follow-ups pending.</div>@endforelse
            </div></div>
            <div class="dash-card lg:col-span-2"><div class="dash-card-header"><div><p class="section-title">Recent Visits</p><p class="section-sub">Today &amp; yesterday</p></div></div><div class="max-h-96 overflow-y-auto">
                @forelse($recentVisits as $visit)
                    <a href="{{ route('patients.show', $visit->patient) }}" class="list-row"><div class="flex items-center gap-3 min-w-0"><div class="avatar avatar-slate">{{ strtoupper(substr($visit->patient->first_name,0,1)) }}{{ strtoupper(substr($visit->patient->last_name,0,1)) }}</div><div class="min-w-0"><p class="text-sm font-semibold text-slate-800 truncate">{{ $visit->patient->first_name }} {{ $visit->patient->last_name }}</p><p class="text-xs text-slate-400">{{ \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') }}</p></div></div><span class="badge {{ $visit->risk_level === 'HIGH' ? 'badge-red' : 'badge-green' }}">{{ $visit->risk_level }}</span></a>
                @empty<div class="empty-state">No recent visits.</div>@endforelse
            </div></div>
        </section>

        {{-- Risk Monitoring filters --}}
        <section id="patient-assessments" class="dash-card">
            <div class="dash-card-header"><div><p class="section-title">Risk Monitoring Filters</p><p class="section-sub">Filter the latest assessment per patient</p></div></div>
            <form method="GET" class="p-5 flex flex-col lg:flex-row lg:flex-nowrap gap-3">
                <label class="flex-1 min-w-0 text-xs font-medium text-gray-700">Search Patient<input name="search" value="{{ request('search') }}" placeholder="Search patient" class="mt-1 w-full rounded-lg border-gray-200 text-sm"></label>
                <label class="lg:w-40 text-xs font-medium text-gray-700">Risk Level<select name="risk_filter" class="mt-1 w-full rounded-lg border-gray-200 text-sm"><option value="">All</option><option value="HIGH" @selected(request('risk_filter') === 'HIGH')>HIGH</option><option value="LOW" @selected(request('risk_filter') === 'LOW')>LOW</option><option value="ASSESSMENT INCOMPLETE" @selected(request('risk_filter') === 'ASSESSMENT INCOMPLETE')>Incomplete</option></select></label>
                <label class="lg:w-44 text-xs font-medium text-gray-700">Decision Source<select name="decision_source" class="mt-1 w-full rounded-lg border-gray-200 text-sm"><option value="">All</option><option value="COMPLETENESS" @selected(request('decision_source') === 'COMPLETENESS')>Completeness</option><option value="RULE_BASED" @selected(request('decision_source') === 'RULE_BASED')>Clinical Rules</option><option value="MACHINE_LEARNING" @selected(request('decision_source') === 'MACHINE_LEARNING')>Machine Learning</option><option value="MACHINE_LEARNING_INVALID" @selected(request('decision_source') === 'MACHINE_LEARNING_INVALID')>ML Unavailable</option></select></label>
                <label class="lg:w-40 text-xs font-medium text-gray-700">Urgency<select name="urgency" class="mt-1 w-full rounded-lg border-gray-200 text-sm"><option value="">All</option><option value="URGENT_CLINICAL_REVIEW" @selected(request('urgency') === 'URGENT_CLINICAL_REVIEW')>Urgent Review</option><option value="PROMPT" @selected(request('urgency') === 'PROMPT')>Prompt</option></select></label>
                <label class="lg:w-44 text-xs font-medium text-gray-700">BP Verification<select name="bp_verification_status" class="mt-1 w-full rounded-lg border-gray-200 text-sm"><option value="">All</option><option value="PENDING_REPEAT" @selected(request('bp_verification_status') === 'PENDING_REPEAT')>Pending Repeat</option><option value="REPEAT_COMPLETED" @selected(request('bp_verification_status') === 'REPEAT_COMPLETED')>Repeat Completed</option><option value="UNABLE_TO_REPEAT" @selected(request('bp_verification_status') === 'UNABLE_TO_REPEAT')>Unable to Repeat</option><option value="NOT_REQUIRED" @selected(request('bp_verification_status') === 'NOT_REQUIRED')>Not Required</option></select></label>
                <div class="flex items-end gap-2 lg:flex-shrink-0"><button class="rounded-lg bg-[#55B85A] px-4 py-2 text-sm font-medium text-white">Apply</button>@if(request()->hasAny(['search','risk_filter','decision_source','urgency','bp_verification_status']))<a href="{{ route('dashboard') }}" class="rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-700">Clear</a>@endif</div>
            </form>
        </section>

        {{-- Patient assessments --}}
        <section class="dash-card">
            <div class="dash-card-header"><div><p class="section-title">Patient Assessments</p><p class="section-sub">Latest assessment per patient with decision-source explainability</p></div></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="sticky top-0 z-10 bg-gray-50 text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-5 py-3">Patient</th><th class="px-5 py-3">Risk</th><th class="px-5 py-3">Decision Source</th><th class="px-5 py-3">Urgency</th><th class="px-5 py-3">BP Verification</th><th class="px-5 py-3">Last Visit</th><th class="px-5 py-3">Actions</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                    @forelse($visits as $visit)
                        @php
                            $risk = $visit->risk_level;
                            $riskClass = $risk === 'HIGH' ? 'badge-red' : ($risk === 'LOW' ? 'badge-green' : 'badge-amber');
                            $source = $visit->decision_source;
                            $sourceLabel = match($source) { 'COMPLETENESS' => 'Completeness Check', 'RULE_BASED' => 'Clinical Rules', 'MACHINE_LEARNING' => 'Machine Learning', 'MACHINE_LEARNING_INVALID' => 'ML Assessment Unavailable', null => 'Legacy Assessment', default => $source };
                            $sourceClass = match($source) { 'COMPLETENESS' => 'bg-amber-50 text-amber-700', 'RULE_BASED' => 'bg-orange-50 text-orange-700', 'MACHINE_LEARNING' => 'bg-blue-50 text-blue-700', 'MACHINE_LEARNING_INVALID' => 'bg-gray-100 text-gray-700', default => 'bg-gray-50 text-gray-500' };
                            $structuredFactors = \App\ValueObjects\ClinicalFactorEvidence::normalizeList($visit->factor_evidence);
                            $bpAlertFactor = collect($structuredFactors)->first(fn ($factor) => in_array($factor['code'] ?? null, ['BP-H', 'BP-URG'], true));
                            $evidenceLines = collect();
                            $extraCount = 0;
                            if (!empty($structuredFactors)) {
                                $labels = array_map(static fn ($factor) => $factor['label'] ?? ($factor['code'] ?? 'Clinical factor'), $structuredFactors);
                                $evidenceLines = collect(array_slice($labels, 0, 2));
                                $extraCount = max(0, count($labels) - 2);
                            } elseif ($source === 'RULE_BASED' && !empty($visit->rule_reasons)) {
                                $evidenceLines = collect($visit->rule_reasons)->take(2);
                                $extraCount = max(0, count($visit->rule_reasons) - 2);
                            } elseif ($source === 'COMPLETENESS' && !empty($visit->missing_records)) {
                                $evidenceLines = collect($visit->missing_records)->take(2);
                                $extraCount = max(0, count($visit->missing_records) - 2);
                            } elseif ($source === 'MACHINE_LEARNING' && $visit->ml_prediction) {
                                $evidenceLines = collect(['Prediction: ' . $visit->ml_prediction . ' (Valid)']);
                            } elseif ($source === 'MACHINE_LEARNING_INVALID') {
                                $evidenceLines = collect(['Model assessment unavailable']);
                            }
                            $mlOrNote = $source === 'MACHINE_LEARNING' ? 'No HIGH-risk rule triggered' : ($source === 'MACHINE_LEARNING_INVALID' ? 'No HIGH-risk rule triggered; model did not produce valid result' : '');
                            $nextLabel = $visit->getMonitoringNextVisitLabel();
                            $isOverdue = $visit->isMonitoringOverdue();
                            $hasActiveReferral = $visit->patient?->hasActiveReferral() ?? false;
                            $verificationCount = count(is_array($visit->assessment_metadata['data_quality_flags'] ?? null) ? $visit->assessment_metadata['data_quality_flags'] : []);
                            $interactionEvidence = \App\ValueObjects\ClinicalInteractionEvidence::normalizeList(is_array($visit->assessment_metadata) ? ($visit->assessment_metadata['interaction_evidence'] ?? null) : null);
                            $interactionCount = count($interactionEvidence);
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-4"><a class="font-semibold text-gray-800 hover:text-[#55B85A]" href="{{ route('patients.show', ['patient' => $visit->patient_id, 'from' => 'risk-monitoring']) }}">{{ $visit->patient->first_name }} {{ $visit->patient->last_name }}</a><p class="text-xs text-gray-500 mt-1">Age {{ $visit->patient->age }} · G{{ $visit->patient->gravida }} P{{ $visit->patient->para }}</p></td>
                            <td class="px-5 py-4"><span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $riskClass }}"><span class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $risk === 'HIGH' ? 'bg-red-500' : ($risk === 'LOW' ? 'bg-green-500' : 'bg-amber-500') }}"></span>{{ $risk === 'ASSESSMENT INCOMPLETE' ? 'INCOMPLETE' : $risk }}</span></td>
                            <td class="px-5 py-4"><span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium {{ $sourceClass }}">{{ $sourceLabel }}</span><div class="mt-2 space-y-1">@foreach($evidenceLines as $line)<div class="flex items-start text-xs text-gray-700"><span class="w-1 h-1 mt-1.5 rounded-full flex-shrink-0 mr-1.5 {{ $source === 'RULE_BASED' ? 'bg-orange-400' : ($source === 'COMPLETENESS' ? 'bg-amber-400' : ($source === 'MACHINE_LEARNING' ? 'bg-blue-400' : 'bg-gray-400')) }}"></span><span class="leading-tight">{{ $line }}</span></div>@endforeach @if($extraCount > 0)<p class="text-xs text-gray-400 ml-3">+ {{ $extraCount }} more</p>@endif @if($bpAlertFactor)<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 text-red-800">{{ $bpAlertFactor['label'] ?? 'BP alert' }}</span>@endif @if($mlOrNote)<p class="text-xs text-gray-500">{{ $mlOrNote }}</p>@endif @if($verificationCount > 0)<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-200 text-slate-700">Verify {{ $verificationCount }}</span>@endif @if($interactionCount > 0)<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-violet-100 text-violet-700">{{ $interactionCount }} {{ Str::plural('interaction', $interactionCount) }}</span>@endif</div></td>
                            <td class="px-5 py-4">@if($visit->urgency === 'URGENT_CLINICAL_REVIEW')<span class="badge badge-red">URGENT</span>@elseif($visit->urgency === 'PROMPT')<span class="badge badge-amber">PROMPT</span>@else<span class="text-xs text-gray-400">—</span>@endif</td>
                            <td class="px-5 py-4 text-xs text-gray-600">{{ str_replace('_', ' ', $visit->bp_verification_status ?: '—') }}</td>
                            <td class="px-5 py-4 whitespace-nowrap text-xs text-gray-600">{{ \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') }}<div class="mt-1 text-gray-900">{{ $nextLabel }}</div>@if($hasActiveReferral)<span class="badge badge-amber mt-1">Pending Referral</span>@endif @if($visit->patient->status === 'ONGOING' && $visit->next_visit_date) @php $nextVisitDate = \Carbon\Carbon::parse($visit->next_visit_date); $daysUntil = \Carbon\Carbon::now()->floatDiffInDays($nextVisitDate, false); $formattedDaysUntil = abs($daysUntil) == floor(abs($daysUntil)) ? number_format(abs($daysUntil), 0) : number_format(abs($daysUntil), 1); @endphp @if($daysUntil <= 3 && $daysUntil >= 0)<div class="text-xs text-orange-600">Due in {{ $formattedDaysUntil }} day(s)</div>@elseif($isOverdue)<div class="text-xs text-red-600">Overdue by {{ $formattedDaysUntil }} day(s)</div>@endif @endif</td>
                            <td class="px-5 py-4 whitespace-nowrap"><x-action-buttons :viewRoute="route('patients.show', ['patient' => $visit->patient_id, 'from' => 'risk-monitoring'])" :editRoute="auth()->user()->role !== 'admin' ? route('prenatal-visits.edit', $visit->id) : null" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="empty-state">No patients found matching the current filters.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if(method_exists($visits, 'links'))<div class="border-t border-gray-200 px-5 py-4 overflow-x-auto">{{ $visits->appends(request()->query())->links() }}</div>@endif
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
        const labels = analytics.labels || [];
        const trend = analytics.riskTrend || analytics.highRiskTrend || [];
        const summary = analytics.summary || {};
        const subtitle = document.getElementById('staffAnalyticsSubtitle');
        const trendTitle = document.getElementById('staffTrendTitle');
        if (subtitle) subtitle.textContent = analytics.month ? 'Showing risk analytics for ' + (labels[0] || 'the selected month') : 'Showing risk analytics for ' + (analytics.year || '');
        if (trendTitle) trendTitle.textContent = isLowRisk ? 'Low-Risk Patients' : 'High-Risk Patients';
        const highest = summary.highestHighRiskPeriod;
        const highestTitle = document.getElementById('staffHighestTitle');
        const highestSub = document.getElementById('staffHighestSub');
        if (analytics.month) {
            if (highestTitle) highestTitle.textContent = 'Selected Month';
            setText('staffHighestValue', labels[0]);
            if (highestSub) { highestSub.style.display = 'block'; highestSub.textContent = (trend[0] || 0) + ' ' + ((trend[0] || 0) === 1 ? 'Assessment' : 'Assessments'); }
        } else {
            if (highestTitle) highestTitle.textContent = 'Highest High-Risk Month';
            setText('staffHighestValue', highest ? highest.label + ' · ' + highest.count : null);
            if (highestSub) highestSub.style.display = 'none';
        }
        setText('staffConditionValue', summary.mostCommonCondition ? summary.mostCommonCondition.name : null);
        const trendColor = isLowRisk ? '#059669' : '#dc2626';
        render('staffHighRiskTrendChart', { type: 'line', data: { labels, datasets: [{ label: isLowRisk ? 'Low Risk' : 'High Risk', data: trend, borderColor: trendColor, backgroundColor: isLowRisk ? 'rgba(5,150,102,.14)' : 'rgba(220,38,38,.14)', borderWidth: 2.5, tension: .45, fill: true }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip }, scales: { y: { beginAtZero: true }, x: { grid: { display: false } } } } }, 'staffHighRiskTrendEmpty', labels.length > 0 && trend.some(Number));
        const age = analytics.ageDistribution || { labels: [], data: [] };
        render('staffAgeDistributionChart', { type: 'pie', data: { labels: age.labels || [], datasets: [{ data: age.data || [], backgroundColor: palette, borderColor: '#fff', borderWidth: 2 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' }, tooltip } } }, 'staffAgeDistributionEmpty', (age.data || []).some(Number));
        const conditions = analytics.conditions || {};
        const datasets = ['Hypertension', 'Diabetes', 'Anemia'].map((name, index) => ({ label: name, data: conditions[name] || [], backgroundColor: palette[index], borderRadius: 4 }));
        render('staffMaternalConditionsChart', { type: 'bar', data: { labels, datasets }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' }, tooltip }, scales: { y: { beginAtZero: true }, x: { grid: { display: false } } } } }, 'staffMaternalConditionsEmpty', labels.length > 0 && datasets.some((dataset) => dataset.data.some(Number)));
        const top = (analytics.topHighRiskConditions || []).slice(0, 5);
        render('staffTopHighRiskConditionsChart', { type: 'bar', data: { labels: top.map((item) => item.label), datasets: [{ label: 'High-Risk Assessments', data: top.map((item) => item.count), backgroundColor: '#dc2626', borderRadius: 4, borderSkipped: false }] }, options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip }, scales: { x: { beginAtZero: true }, y: { grid: { display: false } } } } }, 'staffTopHighRiskConditionsEmpty', top.length > 0);
    }
    renderAnalytics(analytics);
    const monthSelect = document.getElementById('staffRiskMonth');
    const typeSelect = document.getElementById('staffRiskType');
    const loading = document.getElementById('staffAnalyticsLoading');
    const refresh = () => {
        if (loading) loading.style.display = 'inline';
        fetch('{{ route('risk.monitoring.analytics') }}?month=' + encodeURIComponent(monthSelect.value) + '&risk_type=' + encodeURIComponent(typeSelect.value))
            .then((response) => response.json())
            .then(renderAnalytics)
            .finally(() => { if (loading) loading.style.display = 'none'; });
    };
    if (monthSelect) monthSelect.addEventListener('change', refresh);
    if (typeSelect) typeSelect.addEventListener('change', refresh);
});
</script>
</x-app-layout>
