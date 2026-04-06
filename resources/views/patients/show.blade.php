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
                        <button type="button" onclick="startDownloadProcess()" data-download-url="{{ route('patients.download', $patient->id) }}" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-medium text-sm shadow-sm inline-flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Download Patient Record
                        </button>
                        
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

        </div>

        <!-- Main 2-Column Layout -->
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- LEFT COLUMN (70%) -->
            <div class="lg:w-2/3 space-y-8">
                @if($patient->status === 'DELIVERED' && $patient->babies->count() > 0)
                <!-- Baby Information Section -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="border-b border-gray-100 px-6 py-4 bg-gray-50">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                </svg>
                                <h2 class="text-lg font-semibold text-gray-800">Baby Information</h2>
                            </div>
                            <span class="text-sm text-gray-500">{{ $patient->babies->count() }} {{ Str::plural('baby', $patient->babies->count()) }}</span>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="space-y-6">
                            @foreach($patient->babies as $index => $baby)
                            <div class="baby-card bg-gradient-to-r from-pink-50 to-purple-50 rounded-xl p-6 border border-pink-100" data-baby-id="{{ $baby->id }}">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-pink-100 rounded-full flex items-center justify-center">
                                            <svg class="w-5 h-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-800 baby-name-display">{{ $baby->full_name }}</h3>
                                            <p class="text-sm text-gray-600">Baby {{ $index + 1 }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        @if($baby->sex)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                            @if($baby->sex === 'Male') bg-blue-100 text-blue-800
                                            @elseif($baby->sex === 'Female') bg-pink-100 text-pink-800
                                            @else bg-gray-100 text-gray-800 @endif">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                @if($baby->sex === 'Male')
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                @elseif($baby->sex === 'Female')
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                                @endif
                                            </svg>
                                            {{ $baby->sex }}
                                        </span>
                                        @endif
                                        <button type="button" onclick="toggleBabyEdit({{ $baby->id }})" class="inline-flex items-center gap-2 px-3 py-1.5 text-sm bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition edit-baby-btn">
                                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                            Edit
                                        </button>
                                    </div>
                                </div>

                                <!-- Display Mode -->
                                <div class="baby-display-mode">
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                        <div class="bg-white rounded-lg p-4 border border-gray-200">
                                            <div class="flex items-center space-x-2 mb-2">
                                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                                </svg>
                                                <span class="text-sm font-medium text-gray-700">Date of Birth</span>
                                            </div>
                                            <p class="text-lg font-semibold text-gray-900">
                                                {{ $baby->date_of_birth ? \Carbon\Carbon::parse($baby->date_of_birth)->format('M d, Y') : 'N/A' }}
                                            </p>
                                        </div>

                                        <div class="bg-white rounded-lg p-4 border border-gray-200">
                                            <div class="flex items-center space-x-2 mb-2">
                                                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                <span class="text-sm font-medium text-gray-700">Time of Birth</span>
                                            </div>
                                            <p class="text-lg font-semibold text-gray-900">
                                                {{ $baby->time_of_birth ? \Carbon\Carbon::parse($baby->time_of_birth)->format('g:i A') : 'N/A' }}
                                            </p>
                                        </div>

                                        <div class="bg-white rounded-lg p-4 border border-gray-200">
                                            <div class="flex items-center space-x-2 mb-2">
                                                <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path>
                                                </svg>
                                                <span class="text-sm font-medium text-gray-700">Birth Weight</span>
                                            </div>
                                            <p class="text-lg font-semibold text-gray-900">
                                                {{ $baby->birth_weight ? $baby->birth_weight . ' kg' : 'N/A' }}
                                            </p>
                                        </div>

                                        <div class="bg-white rounded-lg p-4 border border-gray-200">
                                            <div class="flex items-center space-x-2 mb-2">
                                                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2m0 0V1a1 1 0 011-1h2a1 1 0 011 1v3M7 4H5a1 1 0 00-1 1v16a1 1 0 001 1h14a1 1 0 001-1V5a1 1 0 00-1-1h-2M7 4h10M9 9h6m-6 4h6m-6 4h6"></path>
                                                </svg>
                                                <span class="text-sm font-medium text-gray-700">Birth Length</span>
                                            </div>
                                            <p class="text-lg font-semibold text-gray-900">
                                                {{ $baby->birth_length ? $baby->birth_length . ' cm' : 'N/A' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit Mode -->
                                <div class="baby-edit-mode hidden">
                                    <form class="baby-edit-form" data-baby-id="{{ $baby->id }}">
                                        @csrf
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                                <input type="text" name="first_name" value="{{ $baby->first_name }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                                                <input type="text" name="middle_name" value="{{ $baby->middle_name }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                                <input type="text" name="last_name" value="{{ $baby->last_name }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Sex</label>
                                                <select name="sex" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500">
                                                    <option value="">Select Sex</option>
                                                    <option value="Male" {{ $baby->sex === 'Male' ? 'selected' : '' }}>Male</option>
                                                    <option value="Female" {{ $baby->sex === 'Female' ? 'selected' : '' }}>Female</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth <span class="text-red-500">*</span></label>
                                                <input type="date" name="date_of_birth" value="{{ $baby->date_of_birth }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Time of Birth <span class="text-red-500">*</span></label>
                                                <input type="time" name="time_of_birth" value="{{ $baby->time_of_birth }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Birth Weight (kg)</label>
                                                <input type="number" name="birth_weight" value="{{ $baby->birth_weight }}" step="0.01" min="0" max="10" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500">
                                            </div>
                                            <div class="md:col-span-2 lg:col-span-1">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Birth Length (cm)</label>
                                                <input type="number" name="birth_length" value="{{ $baby->birth_length }}" step="0.1" min="0" max="100" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500 focus:border-pink-500">
                                            </div>
                                        </div>

                                        <div class="flex justify-end space-x-3 mt-6">
                                            <button type="button" onclick="cancelBabyEdit({{ $baby->id }})" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition">
                                                Cancel
                                            </button>
                                            <button type="submit" class="px-4 py-2 bg-pink-600 text-white rounded-lg hover:bg-pink-700 transition">
                                                Save Changes
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

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
                                    <div class="flex items-center space-x-2 p-2 bg-green-50 rounded-lg border border-green-100">
                                        <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        <span class="text-sm text-gray-700">{{ $label }}</span>
                                    </div>
                                @else
                                    <div class="flex items-center space-x-2 p-2 bg-red-50 rounded-lg border border-red-100 opacity-60">
                                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
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

    <!-- Validation Error Modal -->
    <div id="downloadValidationModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4 py-6 sm:px-6">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Validation Error</h3>
                    <p class="text-sm text-gray-500">Patient data is incomplete. Please complete required information before downloading.</p>
                </div>
            </div>
            <div id="downloadValidationList" class="text-sm text-gray-700 mb-4"></div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeValidationModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">OK</button>
            </div>
        </div>
    </div>

    <!-- Select File Format Modal -->
    <div id="downloadFormatModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4 py-6 sm:px-6">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Select File Format</h3>
                    <p class="text-sm text-gray-500">Please choose the file format for download.</p>
                </div>
            </div>
            <div class="space-y-3">
                <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-blue-300 transition">
                    <input type="radio" name="download_format" value="pdf" checked class="h-4 w-4 text-blue-600" />
                    <span class="text-sm text-gray-700">Download as PDF</span>
                </label>
                <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-blue-300 transition">
                    <input type="radio" name="download_format" value="csv" class="h-4 w-4 text-blue-600" />
                    <span class="text-sm text-gray-700">Download as CSV</span>
                </label>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeFormatModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">Cancel</button>
                <button type="button" onclick="openDownloadConfirmModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition font-medium">Continue</button>
            </div>
        </div>
    </div>

    <!-- Confirm Download Modal -->
    <div id="downloadConfirmModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4 py-6 sm:px-6">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Confirm Download</h3>
                    <p id="downloadConfirmText" class="text-sm text-gray-500">Are you sure you want to download this patient record as PDF?</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeDownloadConfirmModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">Cancel</button>
                <button type="button" onclick="submitPatientDownload()" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 transition font-medium">Download</button>
            </div>
        </div>
    </div>

    <!-- Download Success Modal -->
    <div id="downloadSuccessModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4 py-6 sm:px-6">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Downloaded</h3>
                    <p class="text-sm text-gray-500">Patient record has been successfully downloaded.</p>
                </div>
            </div>
            <div class="flex justify-end mt-6">
                <button type="button" onclick="closeDownloadSuccessModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">OK</button>
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

                    <!-- Baby Information Section -->
                    <div class="border-t border-gray-200 pt-4">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-base font-semibold text-gray-800">Baby Information</h4>
                            <button type="button" onclick="addAnotherBaby()" class="inline-flex items-center gap-2 px-3 py-1.5 text-sm bg-blue-50 text-blue-700 border border-blue-200 rounded-lg hover:bg-blue-100 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Add Another Baby
                            </button>
                        </div>

                        <div id="babiesContainer">
                            <!-- Baby 1 (Default) -->
                            <div class="baby-entry bg-gray-50 p-4 rounded-lg mb-4" data-baby-index="0">
                                <div class="flex items-center justify-between mb-3">
                                    <h5 class="font-medium text-gray-700">Baby 1</h5>
                                    <button type="button" onclick="removeBaby(this)" class="text-red-500 hover:text-red-700 text-sm opacity-0" style="display: none;">
                                        Remove
                                    </button>
                                </div>

                                <!-- Name Fields -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">First Name</label>
                                        <input type="text" name="babies[0][first_name]" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Middle Name</label>
                                        <input type="text" name="babies[0][middle_name]" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Last Name</label>
                                        <input type="text" name="babies[0][last_name]" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                                    </div>
                                </div>

                                <!-- Details Row -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Sex</label>
                                        <select name="babies[0][sex]" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                                            <option value="">Select Sex</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Date of Birth <span class="text-red-500">*</span></label>
                                            <input type="date" name="babies[0][date_of_birth]" required class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Time of Birth <span class="text-red-500">*</span></label>
                                            <input type="time" name="babies[0][time_of_birth]" required class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                                        </div>
                                    </div>
                                </div>

                                <!-- Measurements Row -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Birth Weight (kg)</label>
                                        <input type="number" name="babies[0][birth_weight]" step="0.01" min="0" max="10" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Birth Length (cm)</label>
                                        <input type="number" name="babies[0][birth_length]" step="0.1" min="0" max="100" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                                    </div>
                                </div>
                            </div>
                        </div>
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
                
                // Validate baby information
                const babyEntries = document.querySelectorAll('.baby-entry');
                babyEntries.forEach((baby, index) => {
                    const babyNumber = index + 1;
                    
                    // Check required date of birth
                    const dateOfBirth = baby.querySelector(`input[name="babies[${index}][date_of_birth]"]`);
                    if (dateOfBirth && !dateOfBirth.value) {
                        errorMessages.push(`Baby ${babyNumber}: Date of birth is required`);
                        isValid = false;
                        dateOfBirth.classList.add('border-red-500');
                    } else if (dateOfBirth) {
                        dateOfBirth.classList.remove('border-red-500');
                    }
                    
                    // Check required time of birth
                    const timeOfBirth = baby.querySelector(`input[name="babies[${index}][time_of_birth]"]`);
                    if (timeOfBirth && !timeOfBirth.value) {
                        errorMessages.push(`Baby ${babyNumber}: Time of birth is required`);
                        isValid = false;
                        timeOfBirth.classList.add('border-red-500');
                    } else if (timeOfBirth) {
                        timeOfBirth.classList.remove('border-red-500');
                    }
                    
                    // Validate birth weight if provided
                    const birthWeight = baby.querySelector(`input[name="babies[${index}][birth_weight]"]`);
                    if (birthWeight && birthWeight.value) {
                        const weight = parseFloat(birthWeight.value);
                        if (isNaN(weight) || weight < 0.5 || weight > 10.0) {
                            errorMessages.push(`Baby ${babyNumber}: Birth weight must be between 0.5 kg and 10.0 kg`);
                            isValid = false;
                            birthWeight.classList.add('border-red-500');
                        } else {
                            birthWeight.classList.remove('border-red-500');
                        }
                    }
                    
                    // Validate birth length if provided
                    const birthLength = baby.querySelector(`input[name="babies[${index}][birth_length]"]`);
                    if (birthLength && birthLength.value) {
                        const length = parseFloat(birthLength.value);
                        if (isNaN(length) || length < 20 || length > 100) {
                            errorMessages.push(`Baby ${babyNumber}: Birth length must be between 20 cm and 100 cm`);
                            isValid = false;
                            birthLength.classList.add('border-red-500');
                        } else {
                            birthLength.classList.remove('border-red-500');
                        }
                    }
                });
                
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

        @php
            $patientExportData = [
                'id' => $patient->id,
                'first_name' => $patient->first_name,
                'middle_name' => $patient->middle_name,
                'last_name' => $patient->last_name,
                'birthdate' => $patient->birthdate,
                'age' => $patient->age,
                'address' => $patient->address,
                'contact_number' => $patient->contact_number,
                'civil_status' => $patient->civil_status,
                'philhealth_member' => $patient->philhealth_member,
                'philhealth_number' => $patient->philhealth_number,
                'gravida' => $patient->gravida,
                'para' => $patient->para,
                'lmp' => $patient->lmp,
                'edd' => $patient->edd,
                'status' => $patient->status,
                'delivery_date' => $patient->delivery_date,
                'has_medical_history' => $patient->medicalHistory ? true : false,
            ];
        @endphp
        const patientExportData = @json($patientExportData);
        const downloadUrl = '{{ route('patients.download', $patient->id) }}';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        let selectedDownloadFormat = 'pdf';

        function startDownloadProcess() {
            const missing = getPatientDownloadMissingFields();
            if (missing.length > 0) {
                openValidationModal(missing);
                return;
            }
            openFormatModal();
        }

        function getPatientDownloadMissingFields() {
            const missing = [];
            if (!patientExportData.first_name) missing.push('First name');
            if (!patientExportData.last_name) missing.push('Last name');
            if (!patientExportData.birthdate) missing.push('Birthdate');
            if (!patientExportData.age) missing.push('Age');
            if (!patientExportData.address) missing.push('Address');
            if (!patientExportData.contact_number) missing.push('Contact number');
            if (!patientExportData.civil_status) missing.push('Civil status');
            if (patientExportData.philhealth_member === null) missing.push('PhilHealth membership status');
            if (patientExportData.philhealth_member && !patientExportData.philhealth_number) missing.push('PhilHealth number');
            if (patientExportData.gravida === null) missing.push('Gravida');
            if (patientExportData.para === null) missing.push('Para');
            if (!patientExportData.lmp) missing.push('LMP');
            if (!patientExportData.edd) missing.push('EDD');
            if (!patientExportData.has_medical_history) missing.push('Medical history');
            if (patientExportData.status !== 'ONGOING' && !patientExportData.delivery_date) missing.push('Delivery date');
            return missing;
        }

        function openValidationModal(missingFields) {
            const modal = document.getElementById('downloadValidationModal');
            const list = document.getElementById('downloadValidationList');
            list.innerHTML = '<ul class="list-disc list-inside space-y-1">' + missingFields.map(field => `<li>${field}</li>`).join('') + '</ul>';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeValidationModal() {
            const modal = document.getElementById('downloadValidationModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function openFormatModal() {
            const modal = document.getElementById('downloadFormatModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeFormatModal() {
            const modal = document.getElementById('downloadFormatModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function openDownloadConfirmModal() {
            const format = document.querySelector('input[name="download_format"]:checked').value;
            selectedDownloadFormat = format;
            const confirmText = document.getElementById('downloadConfirmText');
            confirmText.textContent = `Are you sure you want to download this patient record as ${format.toUpperCase()}?`;
            closeFormatModal();
            const modal = document.getElementById('downloadConfirmModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeDownloadConfirmModal() {
            const modal = document.getElementById('downloadConfirmModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        async function submitPatientDownload() {
            closeDownloadConfirmModal();
            try {
                const response = await fetch(downloadUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/octet-stream',
                    },
                    body: JSON.stringify({ format: selectedDownloadFormat }),
                });

                if (!response.ok) {
                    const body = await response.json();
                    openValidationModal(body.missing ?? []);
                    return;
                }

                const blob = await response.blob();
                const disposition = response.headers.get('Content-Disposition');
                const filename = getFilenameFromDisposition(disposition) || `patient-${patientExportData.id}-record.${selectedDownloadFormat}`;
                const url = window.URL.createObjectURL(blob);
                const anchor = document.createElement('a');
                anchor.href = url;
                anchor.download = filename;
                document.body.appendChild(anchor);
                anchor.click();
                anchor.remove();
                window.URL.revokeObjectURL(url);
                openDownloadSuccessModal();
            } catch (error) {
                console.error('Download failed', error);
                openValidationModal(['Unexpected error. Please try again.']);
            }
        }

        function getFilenameFromDisposition(disposition) {
            if (!disposition) return null;
            const match = /filename="?([^";]+)"?/.exec(disposition);
            return match ? match[1] : null;
        }

        function openDownloadSuccessModal() {
            const modal = document.getElementById('downloadSuccessModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeDownloadSuccessModal() {
            const modal = document.getElementById('downloadSuccessModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Baby management functions
        let babyIndex = 1;

        function addAnotherBaby() {
            const container = document.getElementById('babiesContainer');
            const babyEntry = document.createElement('div');
            babyEntry.className = 'baby-entry bg-gray-50 p-4 rounded-lg mb-4';
            babyEntry.setAttribute('data-baby-index', babyIndex);

            babyEntry.innerHTML = `
                <div class="flex items-center justify-between mb-3">
                    <h5 class="font-medium text-gray-700">Baby ${babyIndex + 1}</h5>
                    <button type="button" onclick="removeBaby(this)" class="text-red-500 hover:text-red-700 text-sm">
                        Remove
                    </button>
                </div>

                <!-- Name Fields -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">First Name</label>
                        <input type="text" name="babies[${babyIndex}][first_name]" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Middle Name</label>
                        <input type="text" name="babies[${babyIndex}][middle_name]" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Last Name</label>
                        <input type="text" name="babies[${babyIndex}][last_name]" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                    </div>
                </div>

                <!-- Details Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Sex</label>
                        <select name="babies[${babyIndex}][sex]" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                            <option value="">Select Sex</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Date of Birth <span class="text-red-500">*</span></label>
                            <input type="date" name="babies[${babyIndex}][date_of_birth]" required class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Time of Birth <span class="text-red-500">*</span></label>
                            <input type="time" name="babies[${babyIndex}][time_of_birth]" required class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                        </div>
                    </div>
                </div>

                <!-- Measurements Row -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Birth Weight (kg)</label>
                        <input type="number" name="babies[${babyIndex}][birth_weight]" step="0.01" min="0" max="10" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Birth Length (cm)</label>
                        <input type="number" name="babies[${babyIndex}][birth_length]" step="0.1" min="0" max="100" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-green-500 focus:border-green-500">
                    </div>
                </div>
            `;

            container.appendChild(babyEntry);
            babyIndex++;

            // Show remove button on first baby if more than one baby
            updateRemoveButtons();
        }

        function removeBaby(button) {
            const babyEntry = button.closest('.baby-entry');
            babyEntry.remove();
            babyIndex--;

            // Renumber remaining babies
            const babies = document.querySelectorAll('.baby-entry');
            babies.forEach((baby, index) => {
                const title = baby.querySelector('h5');
                title.textContent = `Baby ${index + 1}`;

                // Update input names
                const inputs = baby.querySelectorAll('input, select');
                inputs.forEach(input => {
                    const name = input.name;
                    if (name) {
                        const newName = name.replace(/babies\[\d+\]/, `babies[${index}]`);
                        input.name = newName;
                    }
                });
            });

            updateRemoveButtons();
        }

        function updateRemoveButtons() {
            const babies = document.querySelectorAll('.baby-entry');
            const removeButtons = document.querySelectorAll('.baby-entry button[onclick*="removeBaby"]');

            if (babies.length > 1) {
                removeButtons.forEach(button => {
                    button.style.display = 'block';
                    button.style.opacity = '1';
                });
            } else {
                removeButtons.forEach(button => {
                    button.style.display = 'none';
                    button.style.opacity = '0';
                });
            }
        }

        // Baby editing functions
        function toggleBabyEdit(babyId) {
            const babyCard = document.querySelector(`.baby-card[data-baby-id="${babyId}"]`);
            const displayMode = babyCard.querySelector('.baby-display-mode');
            const editMode = babyCard.querySelector('.baby-edit-mode');
            const editBtn = babyCard.querySelector('.edit-baby-btn');

            if (displayMode.classList.contains('hidden')) {
                // Currently in edit mode, switch to display
                displayMode.classList.remove('hidden');
                editMode.classList.add('hidden');
                editBtn.innerHTML = `
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit
                `;
            } else {
                // Currently in display mode, switch to edit
                displayMode.classList.add('hidden');
                editMode.classList.remove('hidden');
                editBtn.innerHTML = `
                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    Cancel
                `;
            }
        }

        function cancelBabyEdit(babyId) {
            toggleBabyEdit(babyId);
        }

        // Handle baby edit form submission
        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('submit', function(e) {
                if (e.target.classList.contains('baby-edit-form')) {
                    e.preventDefault();
                    const form = e.target;
                    const babyId = form.getAttribute('data-baby-id');
                    const formData = new FormData(form);

                    // Validate required fields
                    const dateOfBirth = formData.get('date_of_birth');
                    const timeOfBirth = formData.get('time_of_birth');

                    if (!dateOfBirth || !timeOfBirth) {
                        showBabyMessage('Validation Error', 'Baby\'s date and time of birth are required.', 'error');
                        return;
                    }

                    // Submit the form
                    fetch(`{{ route('patients.update-baby', ':id') }}`.replace(':id', babyId), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update the display with new data
                            updateBabyDisplay(babyId, data.baby);
                            toggleBabyEdit(babyId);
                            showBabyMessage('Success', 'Baby information has been successfully updated.', 'success');
                        } else {
                            showBabyMessage('Error', data.message || 'Failed to update baby information.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showBabyMessage('Error', 'An unexpected error occurred. Please try again.', 'error');
                    });
                }
            });
        });

        function updateBabyDisplay(babyId, babyData) {
            const babyCard = document.querySelector(`.baby-card[data-baby-id="${babyId}"]`);
            const babyNameDisplay = babyCard.querySelector('.baby-name-display');

            // Update name
            const fullName = [babyData.first_name, babyData.middle_name, babyData.last_name].filter(Boolean).join(' ');
            babyNameDisplay.textContent = fullName;

            // Update sex badge
            let sexBadge = babyCard.querySelector('.sex-badge');
            if (!sexBadge) {
                const headerDiv = babyCard.querySelector('.flex.items-center.justify-between');
                sexBadge = document.createElement('span');
                sexBadge.className = 'sex-badge inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ml-2';
                headerDiv.appendChild(sexBadge);
            }

            if (babyData.sex) {
                sexBadge.className = `sex-badge inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                    ${babyData.sex === 'Male' ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800'}`;
                sexBadge.innerHTML = `
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        ${babyData.sex === 'Male'
                            ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>'
                            : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>'
                        }
                    </svg>
                    ${babyData.sex}
                `;
            } else {
                sexBadge.style.display = 'none';
            }

            // Update date of birth
            const dobElement = babyCard.querySelector('.baby-display-mode .grid > div:nth-child(1) p');
            dobElement.textContent = babyData.date_of_birth
                ? new Date(babyData.date_of_birth).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
                : 'N/A';

            // Update time of birth
            const tobElement = babyCard.querySelector('.baby-display-mode .grid > div:nth-child(2) p');
            tobElement.textContent = babyData.time_of_birth
                ? new Date('1970-01-01T' + babyData.time_of_birth).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })
                : 'N/A';

            // Update birth weight
            const weightElement = babyCard.querySelector('.baby-display-mode .grid > div:nth-child(3) p');
            weightElement.textContent = babyData.birth_weight ? babyData.birth_weight + ' kg' : 'N/A';

            // Update birth length
            const lengthElement = babyCard.querySelector('.baby-display-mode .grid > div:nth-child(4) p');
            lengthElement.textContent = babyData.birth_length ? babyData.birth_length + ' cm' : 'N/A';
        }

        function showBabyMessage(title, message, type) {
            // Remove existing message
            const existingMessage = document.querySelector('.baby-message-notification');
            if (existingMessage) {
                existingMessage.remove();
            }

            // Create new message
            const messageDiv = document.createElement('div');
            messageDiv.className = `baby-message-notification fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg ${
                type === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'
            }`;
            messageDiv.innerHTML = `
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 ${type === 'success' ? 'text-green-400' : 'text-red-400'}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            ${type === 'success'
                                ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
                                : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
                            }
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium ${type === 'success' ? 'text-green-800' : 'text-red-800'}">${title}</h3>
                        <div class="mt-2 text-sm ${type === 'success' ? 'text-green-700' : 'text-red-700'}">
                            ${message}
                        </div>
                    </div>
                    <div class="ml-auto pl-3">
                        <button onclick="this.parentElement.parentElement.remove()" class="inline-flex rounded-md p-1.5 focus:outline-none focus:ring-2 focus:ring-gray-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(messageDiv);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (messageDiv.parentElement) {
                    messageDiv.remove();
                }
            }, 5000);
        }
    </script>
</x-app-layout>