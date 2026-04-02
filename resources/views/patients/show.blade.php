<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Back Button -->
        <div class="mb-6">
            @php
                if (request('from') === 'prenatal-visits') {
                    $backRoute = route('prenatal-visits.index');
                    $backLabel = 'Back to Prenatal Visits';
                } elseif (request('from') === 'risk-monitoring') {
                    $backRoute = route('risk.monitoring');
                    $backLabel = 'Back to Risk Monitoring';
                } elseif (request('from') === 'delivered-patients') {
                    $backRoute = route('patients.delivered');
                    $backLabel = 'Back to Delivered Patients';
                } else {
                    $backRoute = route('patients.index');
                    $backLabel = 'Back to Patient List';
                }
            @endphp
            <a href="{{ $backRoute }}" class="inline-flex items-center text-gray-600 hover:text-blue-600 transition group">
                <svg class="w-5 h-5 mr-2 group-hover:-translate-x-1 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                {{ $backLabel }}
            </a>
        </div>

        <!-- Patient Header -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-8 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 px-6 sm:px-8 py-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div class="flex items-center space-x-4">
                        <div class="bg-blue-100 rounded-full p-3">
                            <svg class="w-12 h-12 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">
                                {{ $patient->first_name }} {{ $patient->middle_name ? $patient->middle_name . ' ' : '' }}{{ $patient->last_name }}
                            </h1>
                            <div class="flex flex-wrap gap-3 sm:gap-4 mt-2 text-xs sm:text-sm text-gray-600">
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    {{ $patient->age }} years
                                </span>
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                    {{ $patient->contact_number }}
                                </span>
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    {{ Str::limit($patient->address, 50) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('patients.edit', $patient->id) }}" 
                           class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-medium text-sm shadow-sm inline-flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Edit Profile
                        </a>
                        
                        <a href="{{ route('prenatal-visits.create', ['patient_id' => $patient->id]) }}" 
                           class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition font-medium text-sm shadow-sm inline-flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Add Record
                        </a>
                        
                        @if($patient->status === 'ONGOING')
                        <button onclick="openDeliveryModal()" 
                                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium text-sm shadow-sm inline-flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Mark as Delivered
                        </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- Main 2-Column Layout -->
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- LEFT COLUMN (70%) -->
            <div class="lg:w-2/3 space-y-8">
                <!-- Prenatal Visits Section -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="border-b border-gray-100 px-6 py-4 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <h2 class="text-lg font-semibold text-gray-800">Prenatal Visits</h2>
                            </div>
                            <span class="text-sm text-gray-500">{{ $patient->prenatalVisits->count() }} visits</span>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">BP</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GA</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Risk</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($patient->prenatalVisits as $visit)
                                <tr class="hover:bg-gray-50 transition cursor-pointer" onclick="toggleVisitDetails({{ $visit->id }})">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $visit->visit_date }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $visit->bp_sys }}/{{ $visit->bp_dia }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $visit->weight }} kg</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $visit->gestational_age }} wks</td>
                                    <td class="px-4 py-3">
                                        @if($visit->risk_level == 'HIGH')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">High</span>
                                        @elseif($visit->risk_level == 'MODERATE')
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Moderate</span>
                                        @else
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Low</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex space-x-2">
                                            <a href="{{ route('prenatal-visits.edit', $visit->id) }}" class="text-blue-600 hover:text-blue-800 text-sm">Edit</a>
                                            <form action="{{ route('prenatal-visits.destroy', $visit->id) }}" method="POST" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" onclick="return confirm('Delete this visit?')" class="text-red-600 hover:text-red-800 text-sm">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <tr id="visit-details-{{ $visit->id }}" class="hidden bg-gray-50">
                                    <td colspan="6" class="px-4 py-4">
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                            <div><span class="font-medium">Temp:</span> {{ $visit->temperature }}°C</div>
                                            <div><span class="font-medium">Fundic Height:</span> {{ $visit->fundic_height }} cm</div>
                                            <div><span class="font-medium">FHT:</span> {{ $visit->fetal_heart_tone }}</div>
                                            <div><span class="font-medium">Fetal Movement:</span> {{ $visit->fetal_movement }}</div>
                                            <div><span class="font-medium">Presenting Part:</span> {{ $visit->presenting_part }}</div>
                                            <div><span class="font-medium">Cervical Dilation:</span> {{ $visit->cervical_dilation }} cm</div>
                                            <div><span class="font-medium">Assessment:</span> {{ $visit->assessment }}</div>
                                            <div><span class="font-medium">Next Visit:</span> {{ $visit->next_visit_date }}</div>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($patient->prenatalVisits->isEmpty())
                    <div class="text-center py-8 text-gray-500">No prenatal visits recorded yet</div>
                    @endif
                </div>

                <!-- Ultrasound Records -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="border-b border-gray-100 px-6 py-4 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <h2 class="text-lg font-semibold text-gray-800">Ultrasound Records</h2>
                            </div>
                            <a href="{{ route('ultrasound.create', $patient->id) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Add Ultrasound Record
                            </a>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Heartbeat</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Movement</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GA</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Report</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($patient->ultrasounds as $u)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $u->scan_date }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            {{ $u->fetal_heartbeat }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $u->fetal_movement }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $u->gestational_age_scan }} wks</td>
                                    <td class="px-4 py-3">
                                        @if($u->report_file)
                                            <a href="{{ asset('storage/' . $u->report_file) }}" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm">View PDF</a>
                                        @else
                                            <span class="text-gray-400 text-sm">No report</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('ultrasound.edit', $u->id) }}" class="text-blue-600 hover:text-blue-800 text-sm">Edit</a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-gray-500">No ultrasound records yet</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Medical History -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="border-b border-gray-100 px-6 py-4 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                                <h2 class="text-lg font-semibold text-gray-800">Medical History</h2>
                            </div>
                            @if($patient->medicalHistory)
                                <a href="{{ route('medical-histories.edit', $patient->medicalHistory->id) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Edit</a>
                            @else
                                <a href="{{ route('medical-histories.create', ['patient_id' => $patient->id]) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add Medical History
                                </a>
                            @endif
                        </div>
                    </div>
                    <div class="p-6">
                        @if($patient->medicalHistory)
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            @php
                                $conditions = [
                                    'epilepsy' => 'Epilepsy',
                                    'severe_headache' => 'Severe Headache',
                                    'visual_disturbance' => 'Visual Disturbance',
                                    'chest_pain' => 'Chest Pain',
                                    'shortness_breath' => 'Shortness of Breath',
                                    'breast_mass' => 'Breast Mass',
                                    'liver_disease' => 'Liver Disease',
                                    'smoking' => 'Smoking',
                                    'allergies' => 'Allergies',
                                    'drug_intake' => 'Drug Intake',
                                    'std_history' => 'STD History'
                                ];
                            @endphp
                            @foreach($conditions as $field => $label)
                                @if($patient->medicalHistory->$field)
                                    <div class="flex items-center space-x-2 p-2 bg-red-50 rounded-lg border border-red-100">
                                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                        <span class="text-sm text-gray-700">{{ $label }}</span>
                                    </div>
                                @else
                                    <div class="flex items-center space-x-2 p-2 bg-green-50 rounded-lg border border-green-100 opacity-60">
                                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        <span class="text-sm text-gray-500">{{ $label }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        @else
                        <p class="text-gray-500 text-center py-4">No medical history recorded</p>
                        @endif
                    </div>
                </div>

                <!-- Birth Plan -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="border-b border-gray-100 px-6 py-4 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                </svg>
                                <h2 class="text-lg font-semibold text-gray-800">Birth Plan</h2>
                            </div>
                            @if($patient->birthPlan)
                                <a href="{{ route('birth-plans.edit', $patient->birthPlan->id) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Edit Birth Plan
                                </a>
                            @else
                                <a href="{{ route('birth-plans.create', ['patient_id' => $patient->id]) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add Birth Plan
                                </a>
                            @endif
                        </div>
                    </div>
                    <div class="p-6">
                        @if($patient->birthPlan)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <div class="flex items-start space-x-2">
                                    <svg class="w-4 h-4 text-gray-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <div><span class="font-medium text-gray-700">Planned Visits:</span> {{ optional($patient->birthPlan)->planned_visits }}</div>
                                </div>
                                <div class="flex items-start space-x-2">
                                    <svg class="w-4 h-4 text-gray-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                    </svg>
                                    <div><span class="font-medium text-gray-700">Delivery Location:</span> {{ optional($patient->birthPlan)->delivery_location }}</div>
                                </div>
                                <div class="flex items-start space-x-2">
                                    <svg class="w-4 h-4 text-gray-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <div><span class="font-medium text-gray-700">Transportation:</span> {{ optional($patient->birthPlan)->transportation }}</div>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex items-start space-x-2">
                                    <svg class="w-4 h-4 text-gray-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <div><span class="font-medium text-gray-700">Payment Method:</span> {{ optional($patient->birthPlan)->payment_method }}</div>
                                </div>
                                <div class="flex items-start space-x-2">
                                    <svg class="w-4 h-4 text-gray-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                    <div><span class="font-medium text-gray-700">Birth Companion:</span> {{ optional($patient->birthPlan)->birth_companion }}</div>
                                </div>
                                <div class="flex items-start space-x-2">
                                    <svg class="w-4 h-4 text-gray-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    <div><span class="font-medium text-gray-700">Family Planning Method:</span> {{ optional($patient->birthPlan)->family_planning_method }}</div>
                                </div>
                            </div>
                        </div>
                        @else
                        <p class="text-gray-500 text-center py-4">No birth plan recorded</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN (30%) -->
            <div class="lg:w-1/3 space-y-6">
                <!-- Risk Assessment Card -->
                @php
                    $latest = $patient->prenatalVisits->sortByDesc('visit_date')->first();
                @endphp
                @if($latest)
                <div class="rounded-2xl shadow-sm border overflow-hidden 
                    @if($latest->risk_level == 'HIGH') bg-red-50 border-red-200
                    @elseif($latest->risk_level == 'MODERATE') bg-yellow-50 border-yellow-200
                    @else bg-green-50 border-green-200 @endif">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center space-x-2">
                                <svg class="w-6 h-6 @if($latest->risk_level == 'HIGH') text-red-600
                                    @elseif($latest->risk_level == 'MODERATE') text-yellow-600
                                    @else text-green-600 @endif" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                <h3 class="font-semibold text-gray-800">Risk Assessment</h3>
                            </div>
                            <span class="px-3 py-1 rounded-full text-sm font-bold
                                @if($latest->risk_level == 'HIGH') bg-red-600 text-white
                                @elseif($latest->risk_level == 'MODERATE') bg-yellow-600 text-white
                                @else bg-green-600 text-white @endif">
                                {{ $latest->risk_level }}
                            </span>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <p class="text-sm font-medium text-gray-700 mb-1">Assessment</p>
                                <p class="text-sm text-gray-600">{{ $latest->assessment }}</p>
                            </div>
                            @if(!empty($latest->risk_reasons))
                            <div>
                                <p class="text-sm font-medium text-gray-700 mb-1">Risk Factors</p>
                                <div class="space-y-1">
                                    @foreach(is_array($latest->risk_reasons) ? $latest->risk_reasons : json_decode($latest->risk_reasons, true) ?? [] as $reason)
                                        <div class="flex items-center space-x-2 text-sm text-gray-600">
                                            <span class="w-1.5 h-1.5 bg-red-500 rounded-full"></span>
                                            <span>{{ $reason }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                            <div>
                                <p class="text-sm font-medium text-gray-700 mb-1">Recommendation</p>
                                <p class="text-sm text-gray-600">{{ $latest->recommendation }}</p>
                            </div>
                            <div class="pt-2 border-t border-gray-200">
                                <div class="flex items-center space-x-2">
                                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <span class="text-sm font-medium text-gray-700">Next Visit:</span>
                                    <span class="text-sm text-gray-600">{{ $latest->next_visit_date }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @else
                <div class="bg-gray-50 rounded-2xl shadow-sm border border-gray-200 p-6 text-center">
                    <p class="text-gray-500">No risk assessment available</p>
                    <a href="{{ route('prenatal-visits.create', ['patient_id' => $patient->id]) }}" class="mt-2 inline-block text-blue-600 text-sm">Add first visit</a>
                </div>
                @endif

                <!-- Pregnancy Summary Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="border-b border-gray-100 px-6 py-4 bg-gray-50">
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                            </svg>
                            <h3 class="font-semibold text-gray-800">Pregnancy Summary</h3>
                        </div>
                    </div>
                    <div class="p-6 space-y-3">
                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-sm text-gray-600">Gravida</span>
                            <span class="text-lg font-semibold text-gray-800">{{ $patient->gravida }}</span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-sm text-gray-600">Para</span>
                            <span class="text-lg font-semibold text-gray-800">{{ $patient->para }}</span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-sm text-gray-600">Previous CS</span>
                            <span class="px-2 py-1 rounded-full text-xs font-medium {{ $patient->previous_cs ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' }}">
                                {{ $patient->previous_cs ? 'Yes' : 'No' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-sm text-gray-600">Miscarriage</span>
                            <span class="px-2 py-1 rounded-full text-xs font-medium {{ $patient->miscarriage ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                {{ $patient->miscarriage ? 'Yes' : 'No' }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-gray-100">
                            <span class="text-sm text-gray-600">LMP</span>
                            <span class="text-sm font-medium text-gray-800">{{ $patient->lmp }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-2">
                            <span class="text-sm text-gray-600">EDD</span>
                            <span class="text-sm font-bold text-pink-600">{{ $patient->edd }}</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Info Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="border-b border-gray-100 px-6 py-4 bg-gray-50">
                        <div class="flex items-center space-x-2">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h3 class="font-semibold text-gray-800">Quick Information</h3>
                        </div>
                    </div>
                    <div class="p-6 space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Birthdate</span>
                            <span class="text-sm text-gray-800">{{ $patient->birthdate }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">Civil Status</span>
                            <span class="text-sm text-gray-800">{{ $patient->civil_status }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-600">PhilHealth Member</span>
                            <span class="px-2 py-1 rounded-full text-xs font-medium {{ $patient->philhealth_member ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                {{ $patient->philhealth_member ? 'Yes' : 'No' }}
                            </span>
                        </div>
                        @if($patient->philhealth_number)
                        <div class="flex justify-between items-center pt-2 border-t border-gray-100">
                            <span class="text-sm text-gray-600">PhilHealth Number</span>
                            <span class="text-sm font-mono text-gray-800">{{ $patient->philhealth_number }}</span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Simplified Delivery Modal -->
<div id="deliveryModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75" onclick="closeDeliveryModal()"></div>
        
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="POST" action="{{ route('patients.deliver', $patient->id) }}">
                @csrf
                
                <div class="bg-gradient-to-r from-green-50 to-green-100 px-6 py-4 border-b border-green-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="bg-green-600 rounded-full p-2">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Complete Delivery</h3>
                                <p class="text-sm text-gray-600">{{ $patient->first_name }} {{ $patient->last_name }}</p>
                            </div>
                        </div>
                        <button type="button" onclick="closeDeliveryModal()" class="text-gray-400 hover:text-gray-500">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="px-6 py-6 space-y-4">
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    <strong>Important:</strong> This will mark the patient as delivered. Please confirm the delivery date.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Delivery Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" 
                               name="delivery_date" 
                               value="{{ now()->format('Y-m-d') }}"
                               max="{{ now()->format('Y-m-d') }}"
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <p class="text-xs text-gray-500 mt-1">Date of delivery</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Additional Notes (Optional)
                        </label>
                        <textarea name="notes" 
                                  rows="3"
                                  placeholder="Any notes about the delivery..."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row justify-end gap-3">
                    <button type="button" onclick="closeDeliveryModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100 transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        Confirm Delivery
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script>
        // Toggle visit details
        function toggleVisitDetails(visitId) {
            const detailsRow = document.getElementById(`visit-details-${visitId}`);
            if (detailsRow) {
                detailsRow.classList.toggle('hidden');
            }
        }
        
        // Modal functions
        function openDeliveryModal() {
            const modal = document.getElementById('deliveryModal');
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        
        function closeDeliveryModal() {
            const modal = document.getElementById('deliveryModal');
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
        
        // Close modal on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modal = document.getElementById('deliveryModal');
                if (!modal.classList.contains('hidden')) {
                    closeDeliveryModal();
                }
            }
        });
        
        // CS Details Toggle
        const deliveryType = document.getElementById('delivery_type');
        const csDetails = document.getElementById('csDetails');
        
        if (deliveryType) {
            deliveryType.addEventListener('change', function() {
                if (this.value === 'cs') {
                    csDetails.classList.remove('hidden');
                } else {
                    csDetails.classList.add('hidden');
                }
            });
        }
        
        // Form Validation
        const deliveryForm = document.getElementById('deliveryForm');
        const deliveryDate = document.getElementById('delivery_date');
        const babyWeight = document.getElementById('baby_weight');
        
        if (deliveryForm) {
            deliveryForm.addEventListener('submit', function(e) {
                let isValid = true;
                let errorMessages = [];
                
                // Remove existing error notification
                const existingError = document.querySelector('.delivery-error-notification');
                if (existingError) existingError.remove();
                
                // Validate delivery date
                if (!deliveryDate.value) {
                    errorMessages.push('Delivery date is required');
                    isValid = false;
                    deliveryDate.classList.add('border-red-500');
                } else {
                    const selectedDate = new Date(deliveryDate.value);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    
                    if (selectedDate > today) {
                        errorMessages.push('Delivery date cannot be in the future');
                        isValid = false;
                        deliveryDate.classList.add('border-red-500');
                    } else {
                        deliveryDate.classList.remove('border-red-500');
                    }
                }
                
                // Validate delivery type
                const deliveryTypeValue = document.getElementById('delivery_type').value;
                if (!deliveryTypeValue) {
                    errorMessages.push('Please select a delivery type');
                    isValid = false;
                    document.getElementById('delivery_type').classList.add('border-red-500');
                } else {
                    document.getElementById('delivery_type').classList.remove('border-red-500');
                }
                
                // Validate baby weight if provided
                if (babyWeight.value) {
                    const weight = parseFloat(babyWeight.value);
                    if (isNaN(weight) || weight < 0.5 || weight > 6.0) {
                        errorMessages.push('Baby weight must be between 0.5 kg and 6.0 kg');
                        isValid = false;
                        babyWeight.classList.add('border-red-500');
                    } else {
                        babyWeight.classList.remove('border-red-500');
                    }
                }
                
                // Show validation errors
                if (!isValid) {
                    e.preventDefault();
                    
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'delivery-error-notification mb-4 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg';
                    errorDiv.innerHTML = `
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Please fix the following errors:</h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <ul class="list-disc list-inside space-y-1">
                                        ${errorMessages.map(msg => `<li>${msg}</li>`).join('')}
                                    </ul>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    const modalBody = document.querySelector('#deliveryModal .px-6.py-6');
                    modalBody.insertBefore(errorDiv, modalBody.firstChild);
                    
                    // Scroll to error
                    errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    
                    // Auto-remove after 5 seconds
                    setTimeout(() => {
                        if (errorDiv) errorDiv.remove();
                    }, 5000);
                }
            });
            
            // Remove error styling on input
            deliveryDate.addEventListener('input', function() {
                this.classList.remove('border-red-500');
            });
            
            if (babyWeight) {
                babyWeight.addEventListener('input', function() {
                    this.classList.remove('border-red-500');
                });
            }
        }
        
        // Prevent modal close when clicking inside modal content
        const modalContent = document.querySelector('#deliveryModal .inline-block');
        if (modalContent) {
            modalContent.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    </script>
</x-app-layout>