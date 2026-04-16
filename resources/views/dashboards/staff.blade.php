<x-app-layout>
<style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=DM+Mono:wght@400;500&display=swap');

    .ops-root { font-family: 'DM Sans', sans-serif; background: #f8f9fc; min-height: 100vh; }

    /* ---- Header ---- */
    .ops-header {
        background: rgba(255,255,255,0.97);
        backdrop-filter: blur(12px);
        border-bottom: 1px solid #e8eaf0;
        position: sticky; top: 64px; z-index: 20;
    }

    /* ---- KPI Cards ---- */
    .kpi-card {
        background: #ffffff;
        border: 1px solid #e8eaf0;
        border-radius: 16px;
        padding: 20px;
        position: relative; overflow: hidden;
        transition: box-shadow 0.2s, transform 0.2s;
    }
    .kpi-card:hover { box-shadow: 0 8px 28px rgba(30,41,59,0.09); transform: translateY(-2px); }
    .kpi-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0; height: 3px;
        border-radius: 16px 16px 0 0;
    }
    .kpi-blue::before   { background: linear-gradient(90deg,#2563eb,#60a5fa); }
    .kpi-emerald::before{ background: linear-gradient(90deg,#059669,#34d399); }
    .kpi-amber::before  { background: linear-gradient(90deg,#d97706,#fbbf24); }
    .kpi-red::before    { background: linear-gradient(90deg,#dc2626,#f87171); }

    .kpi-icon {
        width: 40px; height: 40px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }

    /* ---- Section Cards ---- */
    .dash-card { background: #ffffff; border: 1px solid #e8eaf0; border-radius: 16px; overflow: hidden; }
    .dash-card-header {
        padding: 18px 20px 14px;
        border-bottom: 1px solid #f1f3f7;
        display: flex; align-items: center; justify-content: space-between;
    }
    .section-title { font-size: 15px; font-weight: 700; color: #0f172a; }
    .section-sub   { font-size: 12px; color: #94a3b8; margin-top: 1px; }

    /* ---- List Rows ---- */
    .list-row {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 20px;
        border-bottom: 1px solid #f7f8fa;
        text-decoration: none;
        transition: background 0.15s;
    }
    .list-row:hover { background: #f8f9fc; }
    .list-row:last-child { border-bottom: none; }

    /* ---- Avatar ---- */
    .avatar {
        width: 36px; height: 36px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 12px; font-weight: 700; flex-shrink: 0;
        background: #eff6ff; color: #2563eb;
    }
    .avatar-amber  { background: #fffbeb; color: #b45309; }
    .avatar-violet { background: #f5f3ff; color: #7c3aed; }
    .avatar-slate  { background: #f1f5f9; color: #475569; }

    /* ---- Badges ---- */
    .badge {
        font-size: 11px; font-weight: 600; letter-spacing: 0.03em;
        padding: 3px 8px; border-radius: 20px; flex-shrink: 0;
    }
    .badge-red    { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .badge-amber  { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
    .badge-slate  { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
    .badge-blue   { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
    .badge-emerald{ background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }

    /* ---- Chevron ---- */
    .chevron { color: #cbd5e1; transition: color 0.15s; }
    .list-row:hover .chevron { color: #64748b; }

    /* ---- Date pill on upcoming ---- */
    .date-pill {
        background: #f1f5f9; border-radius: 8px;
        padding: 6px 10px; text-align: center; flex-shrink: 0; min-width: 48px;
    }
    .date-pill .day { font-size: 18px; font-weight: 800; color: #0f172a; line-height: 1; }
    .date-pill .mon { font-size: 10px; font-weight: 600; color: #94a3b8; text-transform: uppercase; margin-top: 2px; }

    /* ---- Empty state ---- */
    .empty-state { padding: 40px 20px; text-align: center; }
    .empty-icon  {
        width: 44px; height: 44px; border-radius: 50%; background: #f1f5f9;
        display: flex; align-items: center; justify-content: center; margin: 0 auto 12px;
    }

    .mono { font-family: 'DM Mono', monospace; }

    /* ---- Today divider ---- */
    .today-bar {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 20px;
        background: linear-gradient(90deg, #eff6ff 0%, #f8f9fc 100%);
        border-bottom: 1px solid #e8eaf0;
    }
</style>

<div class="ops-root">

    {{-- ==================== HEADER ==================== --}}
    <div class="ops-header">
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <div class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></div>
                        <span class="text-xs font-semibold text-blue-600 tracking-widest uppercase">Daily Operations</span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Operations Center</h1>
                    <p class="text-sm text-slate-400 mt-0.5">{{ Carbon\Carbon::today()->format('l, F j, Y') }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('patients.create') }}"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-medium hover:bg-slate-50 transition shadow-sm">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                            <line x1="19" y1="8" x2="19" y2="14"/><line x1="16" y1="11" x2="22" y2="11"/>
                        </svg>
                        New Patient
                    </a>
                    <a href="{{ route('prenatal-visits.create') }}"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 transition shadow-sm">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2z"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>
                        </svg>
                        Record Visit
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== MAIN ==================== --}}
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-7 space-y-6">

        {{-- ======= KPI ROW ======= --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

            <div class="kpi-card kpi-blue">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Patients Today</p>
                        <p class="text-3xl font-bold text-slate-900 mono">{{ $patientsToday }}</p>
                        <p class="text-xs text-slate-400 mt-2">Seen today</p>
                    </div>
                    <div class="kpi-icon bg-blue-50">
                        <svg width="18" height="18" fill="none" stroke="#2563eb" stroke-width="1.8" viewBox="0 0 24 24">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="kpi-card kpi-emerald">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Appointments</p>
                        <p class="text-3xl font-bold text-slate-900 mono">{{ $appointmentsToday }}</p>
                        <p class="text-xs text-slate-400 mt-2">Scheduled today</p>
                    </div>
                    <div class="kpi-icon bg-emerald-50">
                        <svg width="18" height="18" fill="none" stroke="#059669" stroke-width="1.8" viewBox="0 0 24 24">
                            <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="kpi-card kpi-amber">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Pending</p>
                        <p class="text-3xl font-bold text-slate-900 mono">{{ $pendingCheckups }}</p>
                        <p class="text-xs text-slate-400 mt-2">Awaiting checkup</p>
                    </div>
                    <div class="kpi-icon bg-amber-50">
                        <svg width="18" height="18" fill="none" stroke="#d97706" stroke-width="1.8" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="kpi-card kpi-red">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-3">Priority Alerts</p>
                        <p class="text-3xl font-bold text-red-600 mono">{{ count($highRiskAlerts) }}</p>
                        <p class="text-xs text-slate-400 mt-2">Needs attention</p>
                    </div>
                    <div class="kpi-icon bg-red-50">
                        <svg width="18" height="18" fill="none" stroke="#dc2626" stroke-width="1.8" viewBox="0 0 24 24">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                            <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        {{-- ======= ROW 2: Priority Alerts + Upcoming ======= --}}
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

            {{-- Priority Alerts (2/5) --}}
            <div class="lg:col-span-2 dash-card">
                <div class="dash-card-header">
                    <div>
                        <p class="section-title">Priority Alerts</p>
                        <p class="section-sub">Immediate clinical attention required</p>
                    </div>
                    @if(count($highRiskAlerts) > 0)
                    <span class="badge badge-red">
                        {{ count($highRiskAlerts) }} Active
                    </span>
                    @endif
                </div>

                <div class="max-h-80 overflow-y-auto">
                    @forelse($highRiskAlerts as $visit)
                    <a href="{{ route('patients.show', $visit->patient) }}" class="list-row group">
                        <div class="flex items-center gap-3">
                            <div class="avatar avatar-amber">
                                {{ strtoupper(substr($visit->patient->first_name,0,1)) }}{{ strtoupper(substr($visit->patient->last_name,0,1)) }}
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-800">{{ $visit->patient->first_name }} {{ $visit->patient->last_name }}</p>
                                <p class="text-xs text-slate-400 mt-0.5">Last visit: {{ $visit->visit_date->format('M d, Y') }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="badge badge-red">{{ $visit->risk_level }}</span>
                            <svg class="chevron" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                        </div>
                    </a>
                    @empty
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg width="20" height="20" fill="none" stroke="#059669" stroke-width="1.8" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        </div>
                        <p class="text-sm font-medium text-slate-600">All Clear</p>
                        <p class="text-xs text-slate-400 mt-1">No priority alerts today</p>
                    </div>
                    @endforelse
                </div>
            </div>

            {{-- Upcoming Appointments (3/5) --}}
            <div class="lg:col-span-3 dash-card">
                <div class="dash-card-header">
                    <div>
                        <p class="section-title">Upcoming Appointments</p>
                        <p class="section-sub">Next 7 days</p>
                    </div>
                    <span class="badge badge-blue">{{ count($upcomingAppointments) }} Scheduled</span>
                </div>

                <div class="max-h-80 overflow-y-auto">
                    @forelse($upcomingAppointments as $appt)
                    <a href="{{ route('patients.show', $appt->patient) }}" class="list-row group">
                        <div class="flex items-center gap-3">
                            <div class="date-pill">
                                <p class="day">{{ $appt->visit_date->format('d') }}</p>
                                <p class="mon">{{ $appt->visit_date->format('M') }}</p>
                            </div>
                            <div class="avatar">
                                {{ strtoupper(substr($appt->patient->first_name,0,1)) }}{{ strtoupper(substr($appt->patient->last_name,0,1)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-800 truncate">{{ $appt->patient->first_name }} {{ $appt->patient->last_name }}</p>
                                <div class="flex items-center gap-2 mt-1 flex-wrap">
                                    <p class="text-xs text-slate-400">{{ $appt->visit_date->format('l') }}</p>
                                    @if($appt->gestational_age)
                                    <span class="badge badge-slate">GA {{ $appt->gestational_age }}w</span>
                                    @endif
                                    @if($appt->risk_level === 'HIGH')
                                    <span class="badge badge-red">Priority</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <svg class="chevron flex-shrink-0" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                    @empty
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg width="20" height="20" fill="none" stroke="#94a3b8" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>
                        <p class="text-sm font-medium text-slate-600">No appointments</p>
                        <p class="text-xs text-slate-400 mt-1">Nothing scheduled for the next 7 days</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ======= ROW 3: Follow-Ups + Recent Visits ======= --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Follow-Up Tasks --}}
            <div class="dash-card">
                <div class="dash-card-header">
                    <div>
                        <p class="section-title">Follow-Up Schedule</p>
                        <p class="section-sub">Patients with upcoming return visits</p>
                    </div>
                    <div class="w-8 h-8 rounded-xl bg-violet-50 flex items-center justify-center flex-shrink-0">
                        <svg width="15" height="15" fill="none" stroke="#7c3aed" stroke-width="1.8" viewBox="0 0 24 24">
                            <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                        </svg>
                    </div>
                </div>

                <div class="max-h-96 overflow-y-auto">
                    @forelse($followUpTasks as $task)
                    <a href="{{ route('patients.show', $task->patient) }}" class="list-row group">
                        <div class="flex items-center gap-3">
                            <div class="avatar avatar-violet">
                                {{ strtoupper(substr($task->patient->first_name,0,1)) }}{{ strtoupper(substr($task->patient->last_name,0,1)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-800 truncate">{{ $task->patient->first_name }} {{ $task->patient->last_name }}</p>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    Return visit:
                                    @if($task->next_visit_date)
                                        <span class="font-medium text-violet-600">{{ Carbon\Carbon::parse($task->next_visit_date)->format('M d, Y') }}</span>
                                    @else
                                        <span class="text-slate-400">Not scheduled</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($task->next_visit_date)
                            <span class="badge badge-slate mono text-xs">
                                {{ Carbon\Carbon::parse($task->next_visit_date)->diffForHumans() }}
                            </span>
                            @endif
                            <svg class="chevron" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                        </div>
                    </a>
                    @empty
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg width="20" height="20" fill="none" stroke="#94a3b8" stroke-width="1.8" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                        </div>
                        <p class="text-sm font-medium text-slate-600">No follow-ups pending</p>
                        <p class="text-xs text-slate-400 mt-1">All tasks are up to date</p>
                    </div>
                    @endforelse
                </div>
            </div>

            {{-- Recent Visits --}}
            <div class="dash-card">
                <div class="dash-card-header">
                    <div>
                        <p class="section-title">Recent Visits</p>
                        <p class="section-sub">Today &amp; yesterday</p>
                    </div>
                    <div class="w-8 h-8 rounded-xl bg-slate-50 flex items-center justify-center flex-shrink-0">
                        <svg width="15" height="15" fill="none" stroke="#64748b" stroke-width="1.8" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                    </div>
                </div>

                <div class="max-h-96 overflow-y-auto">
                    @forelse($recentVisits as $visit)
                    <a href="{{ route('patients.show', $visit->patient) }}" class="list-row group">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="avatar avatar-slate">
                                {{ strtoupper(substr($visit->patient->first_name,0,1)) }}{{ strtoupper(substr($visit->patient->last_name,0,1)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-slate-800 truncate">{{ $visit->patient->first_name }} {{ $visit->patient->last_name }}</p>
                                <div class="flex items-center gap-2 mt-1 flex-wrap">
                                    @if($visit->bp_sys && $visit->bp_dia)
                                    <span class="badge badge-slate mono" style="font-size:10px;">BP {{ $visit->bp_sys }}/{{ $visit->bp_dia }}</span>
                                    @endif
                                    @if($visit->gestational_age)
                                    <span class="badge badge-slate" style="font-size:10px;">GA {{ $visit->gestational_age }}w</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <p class="text-xs text-slate-400 text-right hidden sm:block">{{ $visit->visit_date->format('M d') }}<br>{{ $visit->visit_date->format('g:i A') }}</p>
                            <svg class="chevron" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                        </div>
                    </a>
                    @empty
                    <div class="empty-state">
                        <div class="empty-icon">
                            <svg width="20" height="20" fill="none" stroke="#94a3b8" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </div>
                        <p class="text-sm font-medium text-slate-600">No recent visits</p>
                        <p class="text-xs text-slate-400 mt-1">No activity in the last 48 hours</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

    </div>
</div>
</x-app-layout>