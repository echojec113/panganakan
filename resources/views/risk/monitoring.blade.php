<x-app-layout>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
        
        <!-- Page Header - Responsive -->
        <div class="mb-6 sm:mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center gap-2">
                        <svg class="w-6 h-6 sm:w-8 sm:h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        Risk Monitoring Dashboard
                    </h1>
                    <p class="text-sm sm:text-base text-gray-600 mt-1">ML-powered risk assessment with clinical rule-based override system</p>
                </div>
                <div class="text-xs sm:text-sm text-gray-500">
                    Last updated: {{ now()->format('M d, Y g:i a') }}
                </div>
            </div>
        </div>

        <!-- Search and Filter Bar - Responsive -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-3 sm:p-4 mb-6">
            <form method="GET" class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Search Patient</label>
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 sm:w-5 sm:h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <input type="text" name="search"
                            placeholder="Search by patient name..."
                            value="{{ request('search') }}"
                            class="w-full pl-9 sm:pl-10 pr-3 sm:pr-4 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                    </div>
                </div>
                
                <div class="sm:w-40 lg:w-48">
                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Risk Level</label>
                    <select name="risk_filter" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        <option value="">All Risks</option>
                        <option value="HIGH" {{ request('risk_filter') == 'HIGH' ? 'selected' : '' }}>High Risk</option>
                        <option value="LOW" {{ request('risk_filter') == 'LOW' ? 'selected' : '' }}>Low Risk</option>
                    </select>
                </div>
                
                <div class="flex gap-2 items-end">
                    <button type="submit"
                        class="flex-1 sm:flex-none px-4 sm:px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-sm">
                        Apply
                    </button>
                    @if(request('search') || request('risk_filter'))
                        <a href="{{ route('risk.monitoring') }}" 
                            class="flex-1 sm:flex-none px-3 sm:px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition text-sm text-center">
                            Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Risk Summary Cards - Responsive Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mb-6 sm:mb-8">
            <!-- High Risk Card -->
            <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg overflow-hidden transform hover:scale-105 transition duration-300">
                <div class="p-4 sm:p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-red-100 text-xs sm:text-sm font-medium">Critical Risk Patients</p>
                            <p class="text-3xl sm:text-4xl font-bold text-white mt-1 sm:mt-2">{{ $highRiskCount ?? 0 }}</p>
                        </div>
                        <div class="bg-white/20 rounded-full p-2 sm:p-3">
                            <svg class="w-6 h-6 sm:w-8 sm:h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 sm:mt-4">
                        <div class="flex items-center text-red-100 text-xs sm:text-sm">
                            <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            <span>Next visit: 3 days</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Low Risk Card -->
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg overflow-hidden transform hover:scale-105 transition duration-300">
                <div class="p-4 sm:p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-xs sm:text-sm font-medium">Low Risk Patients</p>
                            <p class="text-3xl sm:text-4xl font-bold text-white mt-1 sm:mt-2">{{ $lowRiskCount ?? 0 }}</p>
                        </div>
                        <div class="bg-white/20 rounded-full p-2 sm:p-3">
                            <svg class="w-6 h-6 sm:w-8 sm:h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-3 sm:mt-4">
                        <div class="flex items-center text-green-100 text-xs sm:text-sm">
                            <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Next visit: 30 days</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Risk Statistics Overview - Responsive 3-column on desktop, 1-column on mobile -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mb-6 sm:mb-8">
            <div class="bg-white border border-gray-200 rounded-lg p-3 sm:p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs sm:text-sm text-gray-500">Total Patients</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-800">{{ $totalPatients ?? \App\Models\Patient::count() }}</p>
                    </div>
                    <div class="bg-blue-100 rounded-full p-2 sm:p-3">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="bg-white border border-gray-200 rounded-lg p-3 sm:p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs sm:text-sm text-gray-500">Active High Risk</p>
                        <p class="text-xl sm:text-2xl font-bold text-red-600">{{ $highRiskCount ?? 0 }}</p>
                    </div>
                    <div class="bg-red-100 rounded-full p-2 sm:p-3">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="bg-white border border-gray-200 rounded-lg p-3 sm:p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs sm:text-sm text-gray-500">High Risk %</p>
                        <p class="text-xl sm:text-2xl font-bold text-gray-800">
                            @php
                                $total = $totalPatients ?? \App\Models\Patient::count();
                                $highRiskPercent = $total > 0 ? round(($highRiskCount / $total) * 100) : 0;
                            @endphp
                            {{ $highRiskPercent }}%
                        </p>
                    </div>
                    <div class="bg-purple-100 rounded-full p-2 sm:p-3">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- High Risk Patients List - Horizontal Scroll on Mobile -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="border-b border-gray-100 px-4 sm:px-6 py-3 sm:py-4 bg-gradient-to-r from-gray-50 to-white">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div class="bg-red-100 rounded-lg p-1.5 sm:p-2">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base sm:text-lg font-semibold text-gray-800">High Risk Patient Registry</h3>
                            <p class="text-xs sm:text-sm text-gray-500">Patients requiring immediate attention</p>
                        </div>
                    </div>
                    <button onclick="exportToCSV()" class="self-start sm:self-auto px-3 py-1.5 text-xs sm:text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition flex items-center gap-1">
                        <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        Export CSV
                    </button>
                </div>
            </div>

            <!-- Mobile: Card View, Desktop: Table View -->
            <div class="block lg:hidden">
                @forelse($highRiskVisits as $visit)
                <div class="p-4 border-b border-gray-100 hover:bg-gray-50">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-full bg-gradient-to-br from-red-100 to-red-200 flex items-center justify-center flex-shrink-0">
                                <span class="text-red-800 font-medium text-sm">
                                    {{ strtoupper(substr($visit->patient->first_name, 0, 1)) }}{{ strtoupper(substr($visit->patient->last_name, 0, 1)) }}
                                </span>
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">{{ $visit->patient->first_name }} {{ $visit->patient->last_name }}</p>
                                <p class="text-xs text-gray-500">Age: {{ $visit->patient->age }} • G{{ $visit->patient->gravida }} P{{ $visit->patient->para }}</p>
                            </div>
                        </div>
                        <span class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                            HIGH RISK
                        </span>
                    </div>
                    
                    @if($visit->risk_reasons)
                        @php
                            $riskReasons = is_array($visit->risk_reasons)
                                ? $visit->risk_reasons
                                : json_decode($visit->risk_reasons, true) ?? [];
                        @endphp
                        @if(count($riskReasons))
                            <div class="mb-3">
                                <p class="text-xs font-medium text-gray-700 mb-1">Risk Factors:</p>
                                <div class="space-y-1">
                                    @foreach(array_slice($riskReasons, 0, 2) as $reason)
                                        <div class="flex items-center text-xs text-gray-600">
                                            <span class="w-1 h-1 bg-red-400 rounded-full mr-1.5"></span>
                                            {{ $reason }}
                                        </div>
                                    @endforeach
                                    @if(count($riskReasons) > 2)
                                        <button type="button" class="show-reasons-btn text-xs text-blue-600" data-reasons='@json($riskReasons)'>
                                            +{{ count($riskReasons) - 2 }} more
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endif
                    
                    <div class="grid grid-cols-2 gap-2 text-xs mb-3">
                        <div>
                            <span class="text-gray-500">Last Visit:</span>
                            <span class="text-gray-700 ml-1">{{ \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500">Next Visit:</span>
                            <span class="text-gray-700 ml-1">
                                @if($visit->next_visit_date)
                                    {{ \Carbon\Carbon::parse($visit->next_visit_date)->format('M d, Y') }}
                                    @php
                                        $nextVisit = \Carbon\Carbon::parse($visit->next_visit_date);
                                        $daysUntil = \Carbon\Carbon::now()->diffInDays($nextVisit, false);
                                    @endphp
                                    @if($daysUntil <= 3 && $daysUntil >= 0)
                                        <span class="text-orange-600 ml-1">⚠️ Due</span>
                                    @elseif($daysUntil < 0)
                                        <span class="text-red-600 ml-1">🔴 Overdue</span>
                                    @endif
                                @else
                                    N/A
                                @endif
                            </span>
                        </div>
                    </div>
                    
                    <div class="flex justify-end pt-2">
    <x-action-buttons 
        :viewRoute="route('patients.show', ['patient' => $visit->patient_id, 'from' => 'risk-monitoring'])"
        :editRoute="route('prenatal-visits.edit', $visit->id)" />
</div>
                </div>
                @empty
                <div class="p-8 text-center text-gray-500">
                    No high-risk patients found
                </div>
                @endforelse
            </div>

            <!-- Desktop Table View -->
            <div class="hidden lg:block overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Risk Level</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Risk Factors</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assessment</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Visit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Next Visit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($highRiskVisits as $visit)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-red-100 to-red-200 flex items-center justify-center">
                                            <span class="text-red-800 font-medium text-sm">
                                                {{ strtoupper(substr($visit->patient->first_name, 0, 1)) }}{{ strtoupper(substr($visit->patient->last_name, 0, 1)) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $visit->patient->first_name }} {{ $visit->patient->last_name }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Age: {{ $visit->patient->age }} • G{{ $visit->patient->gravida }} P{{ $visit->patient->para }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500 mr-1.5"></span>
                                    HIGH RISK
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                @if($visit->risk_reasons)
                                    @php
                                        $riskReasons = is_array($visit->risk_reasons)
                                            ? $visit->risk_reasons
                                            : json_decode($visit->risk_reasons, true) ?? [];
                                    @endphp
                                    @if(count($riskReasons))
                                        <div class="space-y-1 max-w-xs">
                                            @foreach(array_slice($riskReasons, 0, 2) as $reason)
                                                <div class="flex items-center text-xs text-gray-700">
                                                    <span class="w-1 h-1 bg-red-400 rounded-full mr-1.5"></span>
                                                    {{ $reason }}
                                                </div>
                                            @endforeach
                                            @if(count($riskReasons) > 2)
                                                <button type="button" class="show-reasons-btn text-xs text-blue-600 hover:text-blue-800 font-medium" data-reasons='@json($riskReasons)'>
                                                    +{{ count($riskReasons) - 2 }} more
                                                </button>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">ML-based classification</span>
                                    @endif
                                @else
                                    <span class="text-xs text-gray-400">ML-based classification</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 max-w-xs">
                                    {{ Str::limit($visit->assessment, 60) }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($visit->next_visit_date)
                                    @php
                                        $nextVisit = \Carbon\Carbon::parse($visit->next_visit_date);
                                        $daysUntil = \Carbon\Carbon::now()->floatDiffInDays($nextVisit, false);
                                        $formattedDaysUntil = abs($daysUntil) == floor(abs($daysUntil))
                                            ? number_format(abs($daysUntil), 0)
                                            : number_format(abs($daysUntil), 1);
                                    @endphp
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ \Carbon\Carbon::parse($visit->next_visit_date)->format('M d, Y') }}
                                    </div>
                                    @if($daysUntil <= 3 && $daysUntil >= 0)
                                        <div class="text-xs text-orange-600 mt-1">⚠️ Due in {{ $formattedDaysUntil }} day(s)</div>
                                    @elseif($daysUntil < 0)
                                        <div class="text-xs text-red-600 mt-1">🔴 Overdue by {{ $formattedDaysUntil }} day(s)</div>
                                    @endif
                                @else
                                    <span class="text-xs text-gray-400">Not scheduled</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex justify-end pt-2">
    <x-action-buttons 
        :viewRoute="route('patients.show', ['patient' => $visit->patient_id, 'from' => 'risk-monitoring'])"
        :editRoute="route('prenatal-visits.edit', $visit->id)" />
</div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                No high-risk patients found
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($highRiskVisits, 'links'))
            <div class="border-t border-gray-200 px-4 sm:px-6 py-3 sm:py-4">
                <div class="overflow-x-auto">
                    {{ $highRiskVisits->appends(request()->query())->links() }}
                </div>
            </div>
            @endif
        </div>

        <!-- Low Risk Section - Responsive Collapsible -->
        @if(request('risk_filter') == 'LOW' || !request('risk_filter'))
        <div class="mt-6">
            <button onclick="toggleLowRiskSection()" 
                class="w-full bg-white rounded-xl shadow-sm border border-gray-100 p-3 sm:p-4 text-left hover:bg-gray-50 transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="font-semibold text-gray-800 text-sm sm:text-base">Low Risk Patients ({{ $lowRiskCount }})</span>
                    </div>
                    <svg id="toggleIcon" class="w-4 h-4 sm:w-5 sm:h-5 text-gray-500 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </div>
            </button>
            <div id="lowRiskSection" class="hidden mt-3 sm:mt-4">
                @php
                    $lowRiskVisits = \App\Models\PrenatalVisit::with('patient')
                        ->where('risk_level', 'LOW')
                        ->when(request('search'), function($query) {
                            $query->whereHas('patient', function($q) {
                                $q->where('first_name', 'like', '%' . request('search') . '%')
                                  ->orWhere('last_name', 'like', '%' . request('search') . '%');
                            });
                        })
                        ->latest()
                        ->take(10)
                        ->get();
                @endphp
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <!-- Mobile Card View for Low Risk -->
                    <div class="block lg:hidden">
                        @foreach($lowRiskVisits as $visit)
                        <div class="p-4 border-b border-gray-100">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $visit->patient->first_name }} {{ $visit->patient->last_name }}</p>
                                    <p class="text-xs text-gray-500">Age: {{ $visit->patient->age }}</p>
                                </div>
                                <a href="{{ route('patients.show', ['patient' => $visit->patient_id, 'from' => 'risk-monitoring']) }}" class="text-blue-600 text-sm">View</a>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>Last: {{ \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') }}</span>
                                <span>Next: {{ $visit->next_visit_date ? \Carbon\Carbon::parse($visit->next_visit_date)->format('M d, Y') : 'N/A' }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    
                    <!-- Desktop Table View for Low Risk -->
                    <div class="hidden lg:block overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patient</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Visit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Next Visit</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($lowRiskVisits as $visit)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $visit->patient->first_name }} {{ $visit->patient->last_name }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            Age: {{ $visit->patient->age }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        {{ \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ $visit->next_visit_date ? \Carbon\Carbon::parse($visit->next_visit_date)->format('M d, Y') : 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="{{ route('patients.show', ['patient' => $visit->patient_id, 'from' => 'risk-monitoring']) }}" class="text-blue-600 hover:text-blue-800 text-sm">
                                            View Profile
                                        </a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- System Information Card - Responsive -->
        <div class="mt-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-3 sm:p-4 border border-blue-100">
            <div class="flex items-start gap-2 sm:gap-3">
                <div class="bg-blue-600 rounded-lg p-1.5 sm:p-2 flex-shrink-0">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xs sm:text-sm font-medium text-blue-900">Risk Assessment System</p>
                    <p class="text-xs text-blue-700 mt-1 leading-relaxed">
                        Uses <strong>Random Forest ML model</strong> + <strong>clinical rule-based override</strong> (DOH guidelines). 
                        High-risk: next visit in <strong>3 days</strong> • Low-risk: next visit in <strong>30 days</strong>
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Export Confirm Modal --}}
    <div id="exportConfirmModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4 py-6 sm:px-6">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full mx-4 p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Export Data</h3>
                    <p class="text-sm text-gray-500">Do you want to download the prenatal visit data as CSV?</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" id="cancelExportBtn" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">Cancel</button>
                <button type="button" id="confirmExportBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition font-medium">Download</button>
            </div>
        </div>
    </div>

    {{-- Export Success Modal --}}
    <div id="exportSuccessModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4 py-6 sm:px-6">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full mx-4 p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Downloaded</h3>
                    <p class="text-sm text-gray-500">CSV file has been successfully downloaded.</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" id="closeExportSuccessBtn" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">OK</button>
            </div>
        </div>
    </div>

    <script>
        function showAllReasons(reasons) {
    let reasonsList = reasons.map(r => `• ${r}`).join('\n');
    alert('🚨 RISK FACTORS:\n\n' + reasonsList);
}   
        
        function exportToCSV() {
            openExportConfirmModal();
        }

        function runExportToCSV() {
            const table = document.querySelector('table');
            if (!table) return;

            const rows = table.querySelectorAll('tr');
            let csv = [];

            rows.forEach(row => {
                const cols = row.querySelectorAll('td, th');
                const rowData = Array.from(cols).map(col => {
                    let text = col.innerText.replace(/\n/g, ' ').replace(/,/g, ';');
                    return `"${text.trim()}"`;
                });
                if (rowData.length > 0) csv.push(rowData.join(','));
            });

            const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `risk_monitoring_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            openExportSuccessModal();
        }

        function openExportConfirmModal() {
            const modal = document.getElementById('exportConfirmModal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        }

        function closeExportConfirmModal() {
            const modal = document.getElementById('exportConfirmModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        }

        function openExportSuccessModal() {
            const modal = document.getElementById('exportSuccessModal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }
        }

        function closeExportSuccessModal() {
            const modal = document.getElementById('exportSuccessModal');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }
        }

        window.addEventListener('DOMContentLoaded', function() {
            document.getElementById('confirmExportBtn')?.addEventListener('click', function() {
                closeExportConfirmModal();
                runExportToCSV();
            });
            document.getElementById('cancelExportBtn')?.addEventListener('click', closeExportConfirmModal);
            document.getElementById('closeExportSuccessBtn')?.addEventListener('click', closeExportSuccessModal);
            document.getElementById('exportConfirmModal')?.addEventListener('click', function(e) {
                if (e.target === this) closeExportConfirmModal();
            });
            document.getElementById('exportSuccessModal')?.addEventListener('click', function(e) {
                if (e.target === this) closeExportSuccessModal();
            });

            document.querySelectorAll('.show-reasons-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const reasonsJson = this.dataset.reasons || '[]';
                    const reasons = JSON.parse(reasonsJson);
                    showAllReasons(reasons);
                });
            });
        });
        
        function toggleLowRiskSection() {
            const section = document.getElementById('lowRiskSection');
            const icon = document.getElementById('toggleIcon');
            if (section && icon) {
                section.classList.toggle('hidden');
                icon.classList.toggle('rotate-180');
            }
        }
        
        // Close mobile menu when clicking outside (if needed)
        document.addEventListener('click', function(event) {
            // Add any mobile-specific interactions here
        });
    </script>
    
    <style>
        @media (max-width: 640px) {
            .pagination {
                flex-wrap: wrap;
                justify-content: center;
            }
            .pagination .page-item {
                margin: 2px;
            }
        }
        .rotate-180 {
            transform: rotate(180deg);
        }
        .transition-transform {
            transition: transform 0.2s ease;
        }
    </style>
</x-app-layout>