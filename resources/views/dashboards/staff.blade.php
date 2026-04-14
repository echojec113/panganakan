<x-app-layout>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-blue-50">

        {{-- ==================== HEADER ==================== --}}
        <div class="bg-white border-b border-blue-100 sticky top-16 z-20 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Daily Operations</h1>
                        <p class="text-gray-600 text-sm mt-1">{{ Carbon\Carbon::today()->format('l, F j, Y') }}</p>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('patients.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            Add Patient
                        </a>
                        <a href="{{ route('prenatal-visits.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2c6.627 0 12 1.34 12 3v7c0 1.66-5.373 3-12 3S0 13.66 0 12V5c0-1.66 5.373-3 12-3z"></path></svg>
                            Record Visit
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================== MAIN CONTENT ==================== --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">

            {{-- TODAY'S QUICK STATS --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                
                <!-- Patients Today -->
                <div class="bg-white rounded-xl shadow-sm border border-blue-100 p-5 hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Patients Today</p>
                            <h3 class="text-3xl font-bold text-gray-900 mt-2">{{ $patientsToday }}</h3>
                        </div>
                        <div class="h-12 w-12 rounded-lg bg-blue-100 flex items-center justify-center text-xl">
                            👥
                        </div>
                    </div>
                </div>

                <!-- Appointments Today -->
                <div class="bg-white rounded-xl shadow-sm border border-green-100 p-5 hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Appointments</p>
                            <h3 class="text-3xl font-bold text-gray-900 mt-2">{{ $appointmentsToday }}</h3>
                        </div>
                        <div class="h-12 w-12 rounded-lg bg-green-100 flex items-center justify-center text-xl">
                            📅
                        </div>
                    </div>
                </div>

                <!-- Pending Checkups -->
                <div class="bg-white rounded-xl shadow-sm border border-orange-100 p-5 hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">Pending</p>
                            <h3 class="text-3xl font-bold text-gray-900 mt-2">{{ $pendingCheckups }}</h3>
                        </div>
                        <div class="h-12 w-12 rounded-lg bg-orange-100 flex items-center justify-center text-xl">
                            ⏳
                        </div>
                    </div>
                </div>

                <!-- High-Risk Alerts -->
                <div class="bg-white rounded-xl shadow-sm border border-red-100 p-5 hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-sm font-medium">High-Risk</p>
                            <h3 class="text-3xl font-bold text-red-600 mt-2">{{ count($highRiskAlerts) }}</h3>
                        </div>
                        <div class="h-12 w-12 rounded-lg bg-red-100 flex items-center justify-center text-xl">
                            🚨
                        </div>
                    </div>
                </div>
            </div>

            {{-- MAIN GRID --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

                {{-- HIGH-RISK ALERTS PANEL --}}
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow-sm border border-red-100 overflow-hidden">
                        <div class="bg-gradient-to-r from-red-50 to-red-100 px-6 py-4 border-b border-red-200">
                            <h2 class="text-lg font-bold text-red-900">🚨 High-Risk Alerts</h2>
                            <p class="text-sm text-red-700 mt-1">Requiring immediate attention</p>
                        </div>
                        <div class="p-6 space-y-3 max-h-96 overflow-y-auto">
                            @forelse($highRiskAlerts as $visit)
                                <a href="{{ route('patients.show', $visit->patient) }}" class="block p-4 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition group">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex-1 min-w-0">
                                            <p class="font-semibold text-gray-900 truncate">
                                                {{ $visit->patient->first_name }} {{ $visit->patient->last_name }}
                                            </p>
                                            <p class="text-xs text-gray-600 mt-1">
                                                Risk Level: <span class="font-bold text-red-600">{{ $visit->risk_level }}</span>
                                            </p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                Last Visit: {{ $visit->visit_date->format('M d, Y') }}
                                            </p>
                                        </div>
                                        <span class="text-xl group-hover:translate-x-1 transition flex-shrink-0">→</span>
                                    </div>
                                </a>
                            @empty
                                <div class="text-center py-8">
                                    <p class="text-4xl mb-2">✅</p>
                                    <p class="text-green-600 font-semibold">All Clear</p>
                                    <p class="text-xs text-gray-500 mt-1">No high-risk alerts</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- UPCOMING APPOINTMENTS --}}
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-sm border border-blue-100 overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-50 to-blue-100 px-6 py-4 border-b border-blue-200">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h2 class="text-lg font-bold text-blue-900">📅 Upcoming Appointments</h2>
                                    <p class="text-sm text-blue-700 mt-1">Next 7 days</p>
                                </div>
                                <span class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-blue-200 text-blue-900 font-bold text-sm">
                                    {{ count($upcomingAppointments) }}
                                </span>
                            </div>
                        </div>
                        <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                            @forelse($upcomingAppointments as $appt)
                                <a href="{{ route('patients.show', $appt->patient) }}" class="block p-5 hover:bg-blue-50 transition group">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-3">
                                                <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center text-sm font-bold text-blue-600 flex-shrink-0">
                                                    {{ strtoupper(substr($appt->patient->first_name, 0, 1)) }}
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="font-semibold text-gray-900 truncate">
                                                        {{ $appt->patient->first_name }} {{ $appt->patient->last_name }}
                                                    </p>
                                                    <p class="text-xs text-gray-500 mt-0.5">
                                                        📅 {{ $appt->visit_date->format('l, M d') }} at {{ $appt->visit_date->format('g:i A') ?? 'TBA' }}
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="flex gap-2 mt-2 flex-wrap">
                                                @if($appt->risk_level === 'HIGH')
                                                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700">🚨 High Risk</span>
                                                @endif
                                                <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                                                    GA: {{ $appt->gestational_age ?? '-' }} wk
                                                </span>
                                            </div>
                                        </div>
                                        <span class="text-lg group-hover:translate-x-1 transition flex-shrink-0">→</span>
                                    </div>
                                </a>
                            @empty
                                <div class="p-8 text-center text-gray-500">
                                    <p class="text-sm">No upcoming appointments</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- FOLLOW-UP TASKS & RECENT VISITS --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- FOLLOW-UP TASKS --}}
                <div class="bg-white rounded-xl shadow-sm border border-purple-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-50 to-purple-100 px-6 py-4 border-b border-purple-200">
                        <h2 class="text-lg font-bold text-purple-900">📋 Follow-Up Tasks</h2>
                        <p class="text-sm text-purple-700 mt-1">Action items needing attention</p>
                    </div>
                    <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                        @forelse($followUpTasks as $task)
                            <a href="{{ route('patients.show', $task->patient) }}" class="block p-5 hover:bg-purple-50 transition group">
                                <div class="flex items-start gap-4">
                                    <div class="h-10 w-10 rounded-lg bg-purple-100 flex items-center justify-center text-purple-600 text-lg flex-shrink-0">
                                        ✓
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-gray-900">{{ $task->patient->first_name }} {{ $task->patient->last_name }}</p>
                                        <p class="text-sm text-gray-600 mt-1">Follow-up scheduled</p>
                                        <p class="text-xs text-gray-500 mt-2">
                                            Next visit: 
                                            @if($task->next_visit_date)
                                                {{ Carbon\Carbon::parse($task->next_visit_date)->format('M d, Y') }}
                                            @else
                                                Not scheduled
                                            @endif
                                        </p>
                                    </div>
                                    <span class="text-lg group-hover:translate-x-1 transition flex-shrink-0">→</span>
                                </div>
                            </a>
                        @empty
                            <div class="p-8 text-center text-gray-500">
                                <p class="text-sm">No follow-up tasks</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- RECENT VISITS --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-900">🕐 Recent Visits</h2>
                        <p class="text-sm text-gray-600 mt-1">Today & yesterday</p>
                    </div>
                    <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                        @forelse($recentVisits as $visit)
                            <a href="{{ route('patients.show', $visit->patient) }}" class="block p-5 hover:bg-gray-50 transition group">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-gray-900">{{ $visit->patient->first_name }} {{ $visit->patient->last_name }}</p>
                                        <div class="flex flex-wrap gap-2 mt-2">
                                            <span class="inline-flex px-2 py-1 rounded text-xs font-mono bg-gray-100 text-gray-700">
                                                BP: {{ $visit->bp_sys }}/{{ $visit->bp_dia }}
                                            </span>
                                            <span class="inline-flex px-2 py-1 rounded text-xs bg-gray-100 text-gray-700">
                                                GA: {{ $visit->gestational_age ?? '-' }} wk
                                            </span>
                                        </div>
                                        <p class="text-xs text-gray-500 mt-2">
                                            {{ $visit->visit_date->format('M d, Y g:i A') }}
                                        </p>
                                    </div>
                                    <span class="text-lg group-hover:translate-x-1 transition flex-shrink-0">→</span>
                                </div>
                            </a>
                        @empty
                            <div class="p-8 text-center text-gray-500">
                                <p class="text-sm">No recent visits</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
