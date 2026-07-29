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
                    <p class="text-sm sm:text-base text-gray-600 mt-1">Clinical risk assessments with decision-source explainability</p>
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
                
                <div class="sm:w-40 lg:w-44">
                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Risk Level</label>
                    <select name="risk_filter" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        <option value="">All Risks</option>
                        <option value="HIGH" {{ request('risk_filter') == 'HIGH' ? 'selected' : '' }}>High Risk</option>
                        <option value="LOW" {{ request('risk_filter') == 'LOW' ? 'selected' : '' }}>Low Risk</option>
                        <option value="ASSESSMENT INCOMPLETE" {{ request('risk_filter') == 'ASSESSMENT INCOMPLETE' ? 'selected' : '' }}>Assessment Incomplete</option>
                    </select>
                </div>

                <div class="sm:w-44 lg:w-48">
                    <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Decision Source</label>
                    <select name="decision_source" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                        <option value="">All Sources</option>
                        <option value="COMPLETENESS" {{ request('decision_source') == 'COMPLETENESS' ? 'selected' : '' }}>Completeness Check</option>
                        <option value="RULE_BASED" {{ request('decision_source') == 'RULE_BASED' ? 'selected' : '' }}>Clinical Rules</option>
                        <option value="MACHINE_LEARNING" {{ request('decision_source') == 'MACHINE_LEARNING' ? 'selected' : '' }}>Machine Learning</option>
                        <option value="MACHINE_LEARNING_INVALID" {{ request('decision_source') == 'MACHINE_LEARNING_INVALID' ? 'selected' : '' }}>ML Assessment Unavailable</option>
                    </select>
                </div>
                
                <div class="flex gap-2 items-end">
                    <button type="submit"
                        class="flex-1 sm:flex-none px-4 sm:px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-sm">
                        Apply
                    </button>
                    @if(request('search') || request('risk_filter') || request('decision_source'))
                        <a href="{{ route('risk.monitoring') }}" 
                            class="flex-1 sm:flex-none px-3 sm:px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition text-sm text-center">
                            Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>

        <!-- Risk Summary Cards - Responsive Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 mb-6 sm:mb-8">
            <!-- High Risk Card -->
            <div class="rounded-xl shadow-sm border overflow-hidden" style="border-left: 4px solid #dc2626;">
                <div class="p-4 sm:p-5 bg-white">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">HIGH Risk</p>
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-50">
                            <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </span>
                    </div>
                    <p class="text-3xl font-bold text-red-600 mono">{{ $highRiskCount ?? 0 }}</p>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">Patients whose latest assessment identified elevated-risk findings and require clinic review.</p>
                    <a href="{{ route('risk.monitoring', ['risk_filter' => 'HIGH']) }}" class="inline-block mt-2 text-xs font-semibold text-red-600 hover:text-red-800 underline">View HIGH &rarr;</a>
                </div>
            </div>

            <!-- Low Risk Card -->
            <div class="rounded-xl shadow-sm border overflow-hidden" style="border-left: 4px solid #16a34a;">
                <div class="p-4 sm:p-5 bg-white">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">LOW Risk</p>
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-green-50">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </span>
                    </div>
                    <p class="text-3xl font-bold text-green-600 mono">{{ $lowRiskCount ?? 0 }}</p>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">Patients whose latest completed assessment found no deterministic HIGH-risk rule and received a valid LOW model result.</p>
                    <a href="{{ route('risk.monitoring', ['risk_filter' => 'LOW']) }}" class="inline-block mt-2 text-xs font-semibold text-green-600 hover:text-green-800 underline">View LOW &rarr;</a>
                </div>
            </div>

            <!-- Assessment Incomplete Card -->
            <div class="rounded-xl shadow-sm border overflow-hidden" style="border-left: 4px solid #d97706;">
                <div class="p-4 sm:p-5 bg-white">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Assessment Incomplete</p>
                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-amber-50">
                            <svg class="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </span>
                    </div>
                    <p class="text-3xl font-bold text-amber-600 mono">{{ $incompleteCount ?? 0 }}</p>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">Patients whose latest assessment could not be finalized because required data or a valid model result was unavailable.</p>
                    <a href="{{ route('risk.monitoring', ['risk_filter' => 'ASSESSMENT INCOMPLETE']) }}" class="inline-block mt-2 text-xs font-semibold text-amber-600 hover:text-amber-800 underline">View incomplete &rarr;</a>
                </div>
            </div>
        </div>

        <!-- Risk Statistics Overview - Responsive 4-column -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-6 sm:mb-8">
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
                        <p class="text-xs sm:text-sm text-gray-500">HIGH</p>
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
                        <p class="text-xs sm:text-sm text-gray-500">LOW</p>
                        <p class="text-xl sm:text-2xl font-bold text-green-600">{{ $lowRiskCount ?? 0 }}</p>
                    </div>
                    <div class="bg-green-100 rounded-full p-2 sm:p-3">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 rounded-lg p-3 sm:p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs sm:text-sm text-gray-500">Incomplete</p>
                        <p class="text-xl sm:text-2xl font-bold text-amber-600">{{ $incompleteCount ?? 0 }}</p>
                    </div>
                    <div class="bg-amber-100 rounded-full p-2 sm:p-3">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Patients List - Show all risk levels with decision source and evidence -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="border-b border-gray-100 px-4 sm:px-6 py-3 sm:py-4 bg-gradient-to-r from-gray-50 to-white">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div>
                            <h3 class="text-base sm:text-lg font-semibold text-gray-800">Patient Assessments</h3>
                            <p class="text-xs sm:text-sm text-gray-500">Latest assessment per patient with decision-source explainability</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile: Card View -->
            <div class="block lg:hidden">
                @forelse($visits as $visit)
                @php
                    $rl = $visit->risk_level;
                    $ds = $visit->decision_source;
                    $badgeColor = match($rl) {
                        'HIGH' => 'bg-red-100 text-red-800',
                        'LOW' => 'bg-green-100 text-green-800',
                        'ASSESSMENT INCOMPLETE' => 'bg-amber-100 text-amber-800',
                        default => 'bg-gray-100 text-gray-800',
                    };
                    $badgeLabel = match($rl) {
                        'HIGH' => 'HIGH',
                        'LOW' => 'LOW',
                        'ASSESSMENT INCOMPLETE' => 'INCOMPLETE',
                        default => $rl,
                    };
                    $dsLabel = match($ds) {
                        'COMPLETENESS' => 'Completeness Check',
                        'RULE_BASED' => 'Clinical Rules',
                        'MACHINE_LEARNING' => 'Machine Learning',
                        'MACHINE_LEARNING_INVALID' => 'ML Assessment Unavailable',
                        null => 'Legacy Assessment',
                        default => $ds,
                    };
                    $evidenceLines = collect();
                    if ($ds === 'RULE_BASED' && !empty($visit->rule_reasons)) {
                        $evidenceLines = collect($visit->rule_reasons)->take(2);
                        $extraCount = count($visit->rule_reasons) - 2;
                    } elseif ($ds === 'COMPLETENESS' && !empty($visit->missing_records)) {
                        $evidenceLines = collect($visit->missing_records)->take(2);
                        $extraCount = count($visit->missing_records) - 2;
                    } elseif ($ds === 'MACHINE_LEARNING' && $visit->ml_prediction) {
                        $evidenceLines = collect(['Prediction: ' . $visit->ml_prediction . ' (Valid)']);
                    } elseif ($ds === 'MACHINE_LEARNING_INVALID') {
                        $evidenceLines = collect(['Model assessment unavailable']);
                    } elseif ($ds === null) {
                        $evidenceLines = collect(['Legacy assessment — explanation metadata unavailable']);
                    }
                    $mlOrNote = '';
                    if ($ds === 'MACHINE_LEARNING' && $visit->ml_prediction) {
                        $mlOrNote = 'No HIGH-risk rule triggered';
                    } elseif ($ds === 'MACHINE_LEARNING_INVALID') {
                        $mlOrNote = 'No HIGH-risk rule triggered; model did not produce valid result';
                    }
                    $nextLabel = $visit->getMonitoringNextVisitLabel();
                    $isOverdue = $visit->isMonitoringOverdue();
                @endphp
                <div class="p-4 border-b border-gray-100 hover:bg-gray-50">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <div class="h-10 w-10 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center flex-shrink-0">
                                <span class="text-gray-700 font-medium text-sm">
                                    {{ strtoupper(substr($visit->patient->first_name, 0, 1)) }}{{ strtoupper(substr($visit->patient->last_name, 0, 1)) }}
                                </span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-gray-900 truncate">{{ $visit->patient->first_name }} {{ $visit->patient->last_name }}</p>
                                <p class="text-xs text-gray-500">Age: {{ $visit->patient->age }} • G{{ $visit->patient->gravida }} P{{ $visit->patient->para }}</p>
                            </div>
                        </div>
                        <span class="px-2 py-1 rounded-full text-xs font-medium {{ $badgeColor }} flex-shrink-0 ml-2">
                            {{ $badgeLabel }}
                        </span>
                    </div>

                    <div class="flex items-center gap-1.5 mb-1.5 flex-wrap">
                        <span class="text-xs font-medium @if($ds === 'COMPLETENESS') text-amber-700 @elseif($ds === 'RULE_BASED') text-orange-700 @elseif($ds === 'MACHINE_LEARNING') text-blue-700 @elseif($ds === 'MACHINE_LEARNING_INVALID') text-gray-700 @else text-gray-500 @endif">{{ $dsLabel }}</span>
                    </div>

                    @if($evidenceLines->isNotEmpty())
                    <div class="mb-2 space-y-0.5">
                        @foreach($evidenceLines as $line)
                            <div class="flex items-start text-xs text-gray-600">
                                <span class="w-1 h-1 mt-1.5 rounded-full flex-shrink-0 mr-1.5
                                    @if($ds === 'RULE_BASED') bg-orange-400
                                    @elseif($ds === 'COMPLETENESS') bg-amber-400
                                    @elseif($ds === 'MACHINE_LEARNING') bg-blue-400
                                    @else bg-gray-400 @endif"></span>
                                <span>{{ $line }}</span>
                            </div>
                        @endforeach
                        @if(isset($extraCount) && $extraCount > 0)
                            <div class="text-xs text-gray-400 ml-3">+ {{ $extraCount }} more</div>
                        @endif
                        @if($mlOrNote)
                            <div class="flex items-start text-xs text-gray-500 mt-1">
                                <span class="w-1 h-1 mt-1.5 bg-blue-300 rounded-full flex-shrink-0 mr-1.5"></span>
                                <span>{{ $mlOrNote }}</span>
                            </div>
                        @endif
                    </div>
                    @endif

                    <div class="grid grid-cols-2 gap-2 text-xs text-gray-500 mt-2">
                        <div>Last: {{ \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') }}</div>
                        <div>Next: {{ $nextLabel }} @if($isOverdue)<span class="text-red-600 font-semibold ml-1">Overdue</span>@endif</div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <x-action-buttons 
                            :viewRoute="route('patients.show', ['patient' => $visit->patient_id, 'from' => 'risk-monitoring'])"
                            :editRoute="auth()->user()->role !== 'admin' ? route('prenatal-visits.edit', $visit->id) : null" />
                    </div>
                </div>
                @empty
                <div class="p-8 text-center text-gray-500">
                    No patients found matching the current filters.
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
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Decision Source</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evidence Summary</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Visit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Next Visit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($visits as $visit)
                        @php
                            $rl = $visit->risk_level;
                            $ds = $visit->decision_source;
                            $badgeColor = match($rl) {
                                'HIGH' => 'bg-red-100 text-red-800',
                                'LOW' => 'bg-green-100 text-green-800',
                                'ASSESSMENT INCOMPLETE' => 'bg-amber-100 text-amber-800',
                                default => 'bg-gray-100 text-gray-800',
                            };
                            $badgeLabel = match($rl) {
                                'HIGH' => 'HIGH',
                                'LOW' => 'LOW',
                                'ASSESSMENT INCOMPLETE' => 'INCOMPLETE',
                                default => $rl,
                            };
                            $dsLabel = match($ds) {
                                'COMPLETENESS' => 'Completeness Check',
                                'RULE_BASED' => 'Clinical Rules',
                                'MACHINE_LEARNING' => 'Machine Learning',
                                'MACHINE_LEARNING_INVALID' => 'ML Assessment Unavailable',
                                null => 'Legacy Assessment',
                                default => $ds,
                            };
                            $dsColor = match($ds) {
                                'COMPLETENESS' => 'text-amber-700 bg-amber-50',
                                'RULE_BASED' => 'text-orange-700 bg-orange-50',
                                'MACHINE_LEARNING' => 'text-blue-700 bg-blue-50',
                                'MACHINE_LEARNING_INVALID' => 'text-gray-700 bg-gray-100',
                                null => 'text-gray-500 bg-gray-50',
                                default => 'text-gray-600 bg-gray-50',
                            };
                            $evidenceLines = collect();
                            $extraCount = 0;
                            if ($ds === 'RULE_BASED' && !empty($visit->rule_reasons)) {
                                $evidenceLines = collect($visit->rule_reasons)->take(2);
                                $extraCount = max(0, count($visit->rule_reasons) - 2);
                            } elseif ($ds === 'COMPLETENESS' && !empty($visit->missing_records)) {
                                $evidenceLines = collect($visit->missing_records)->take(2);
                                $extraCount = max(0, count($visit->missing_records) - 2);
                            } elseif ($ds === 'MACHINE_LEARNING' && $visit->ml_prediction) {
                                $evidenceLines = collect(['Prediction: ' . $visit->ml_prediction . ' (Valid)']);
                            } elseif ($ds === 'MACHINE_LEARNING_INVALID') {
                                $evidenceLines = collect(['Model assessment unavailable']);
                            } elseif ($ds === null) {
                                $evidenceLines = collect(['Legacy assessment — explanation metadata unavailable']);
                            }
                            $mlOrNote = '';
                            if ($ds === 'MACHINE_LEARNING') {
                                $mlOrNote = 'No HIGH-risk rule triggered';
                            } elseif ($ds === 'MACHINE_LEARNING_INVALID') {
                                $mlOrNote = 'No HIGH-risk rule triggered; model did not produce valid result';
                            }
                            $nextLabel = $visit->getMonitoringNextVisitLabel();
                            $isOverdue = $visit->isMonitoringOverdue();
                        @endphp
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center">
                                            <span class="text-gray-700 font-medium text-sm">
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
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $badgeColor }}">
                                    <span class="w-1.5 h-1.5 rounded-full mr-1.5
                                        @if($rl == 'HIGH') bg-red-500
                                        @elseif($rl == 'LOW') bg-green-500
                                        @elseif($rl == 'ASSESSMENT INCOMPLETE') bg-amber-500
                                        @else bg-gray-500 @endif"></span>
                                    {{ $badgeLabel }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium {{ $dsColor }}">
                                    {{ $dsLabel }}
                                </span>
                            </td>
                            <td class="px-6 py-4 max-w-xs">
                                <div class="space-y-1">
                                    @foreach($evidenceLines as $line)
                                        <div class="flex items-start text-xs text-gray-700">
                                            <span class="w-1 h-1 mt-1.5 rounded-full flex-shrink-0 mr-1.5
                                                @if($ds === 'RULE_BASED') bg-orange-400
                                                @elseif($ds === 'COMPLETENESS') bg-amber-400
                                                @elseif($ds === 'MACHINE_LEARNING') bg-blue-400
                                                @else bg-gray-400 @endif"></span>
                                            <span class="leading-tight">{{ $line }}</span>
                                        </div>
                                    @endforeach
                                    @if($extraCount > 0)
                                        <div class="text-xs text-gray-400 ml-3">+ {{ $extraCount }} more</div>
                                    @endif
                                    @if($mlOrNote)
                                        <div class="flex items-start text-xs text-gray-500 mt-0.5">
                                            <span class="w-1 h-1 mt-1.5 bg-blue-300 rounded-full flex-shrink-0 mr-1.5"></span>
                                            <span class="leading-tight">{{ $mlOrNote }}</span>
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $nextLabel }}
                                </div>
                                @if($visit->patient->status === 'ONGOING' && $visit->next_visit_date)
                                    @php
                                        $nextVisitDate = \Carbon\Carbon::parse($visit->next_visit_date);
                                        $daysUntil = \Carbon\Carbon::now()->floatDiffInDays($nextVisitDate, false);
                                        $formattedDaysUntil = abs($daysUntil) == floor(abs($daysUntil))
                                            ? number_format(abs($daysUntil), 0)
                                            : number_format(abs($daysUntil), 1);
                                    @endphp
                                    @if($daysUntil <= 3 && $daysUntil >= 0)
                                        <div class="text-xs text-orange-600 mt-1">Due in {{ $formattedDaysUntil }} day(s)</div>
                                    @elseif($isOverdue)
                                        <div class="text-xs text-red-600 mt-1">Overdue by {{ $formattedDaysUntil }} day(s)</div>
                                    @endif
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <x-action-buttons 
                                    :viewRoute="route('patients.show', ['patient' => $visit->patient_id, 'from' => 'risk-monitoring'])"
                                    :editRoute="auth()->user()->role !== 'admin' ? route('prenatal-visits.edit', $visit->id) : null" />
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                No patients found matching the current filters.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($visits, 'links'))
            <div class="border-t border-gray-200 px-4 sm:px-6 py-3 sm:py-4">
                <div class="overflow-x-auto">
                    {{ $visits->appends(request()->query())->links() }}
                </div>
            </div>
            @endif
        </div>

        <!-- System Information Card - Responsive -->
        <div class="mt-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-3 sm:p-4 border border-blue-100">
            <div class="flex items-start gap-2 sm:gap-3">
                <div class="bg-blue-600 rounded-lg p-1.5 sm:p-2 flex-shrink-0">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div>
                    <p class="text-xs sm:text-sm font-medium text-blue-900">Clinical Decision Support System</p>
                    <p class="text-xs text-blue-700 mt-1 leading-relaxed">
                        This system combines deterministic clinical rules with a machine-learning model to support clinical decision-making.
                        Each assessment displays its decision source, triggered rules, and ML contribution for staff review.
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- No JavaScript modals needed — inline evidence is displayed directly in the table --}}

    
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