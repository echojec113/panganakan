<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
        <!-- Back Button -->
        <div class="mb-4 sm:mb-6">
            <a href="{{ route('prenatal-visits.index') }}" class="inline-flex items-center text-gray-600 hover:text-blue-600 transition group">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-1 sm:mr-2 group-hover:-translate-x-1 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Prenatal Visits
            </a>
        </div>

        <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
            <!-- Header -->
            <div class="bg-gradient-to-r from-amber-50 to-yellow-50 px-4 sm:px-6 py-4 sm:py-5 border-b border-gray-200">
                <h2 class="text-xl sm:text-2xl font-bold text-gray-800">Edit Prenatal Visit</h2>
                <p class="text-xs sm:text-sm text-gray-600 mt-1">Update prenatal visit #{{ $visit->id }} - {{ $visit->patient->first_name }} {{ $visit->patient->last_name }}</p>
            </div>

            <form action="{{ route('prenatal-visits.update', $visit->id) }}" method="POST" id="prenatalForm" class="p-4 sm:p-6">
                @csrf
                @method('PUT')

                <!-- Error Summary -->
                @if($errors->any())
                    <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-lg">
                        <div class="flex items-start gap-2">
                            <svg class="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <p class="font-semibold text-red-700 text-sm">Please fix the following errors:</p>
                                <ul class="list-disc list-inside text-sm text-red-600 mt-1">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Patient Selection (Disabled on Edit) -->
                <div class="mb-5 sm:mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Patient <span class="text-red-500">*</span>
                    </label>
                    <select name="patient_id" id="patient_id" required 
                        class="w-full px-3 sm:px-4 py-2.5 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed"
                        disabled>
                        <option value="{{ $visit->patient_id }}">
                            {{ $visit->patient->first_name }} {{ $visit->patient->last_name }} 
                            (Age: {{ $visit->patient->age }}) - {{ $visit->patient->status }}
                        </option>
                    </select>
                    <input type="hidden" name="patient_id" value="{{ $visit->patient_id }}">
                    <p class="text-xs text-gray-500 mt-1">Patient cannot be changed after creation</p>
                </div>

                <!-- Visit Date -->
                <div class="mb-5 sm:mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Visit Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="visit_date" id="visit_date" 
                        value="{{ old('visit_date', $visit->visit_date) }}" 
                        max="{{ date('Y-m-d') }}" required
                        class="w-full px-3 sm:px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                    <p class="text-xs text-gray-500 mt-1">Cannot be in the future</p>
                </div>

                <!-- Vital Signs Section -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-200">
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="text-base sm:text-lg font-semibold text-gray-800">Vital Signs</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                BP Systolic (mmHg) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="bp_sys" id="bp_sys" 
                                value="{{ old('bp_sys', $visit->bp_sys) }}" 
                                min="60" max="200" required
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                BP Diastolic (mmHg) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="bp_dia" id="bp_dia" 
                                value="{{ old('bp_dia', $visit->bp_dia) }}" 
                                min="40" max="130" required
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Weight (kg) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="weight" id="weight" 
                                value="{{ old('weight', $visit->weight) }}" 
                                step="0.1" min="30" max="150" required
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Temperature (°C)
                            </label>
                            <input type="number" name="temperature" id="temperature" 
                                value="{{ old('temperature', $visit->temperature) }}" 
                                step="0.1" min="35" max="40"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Pregnancy Monitoring -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-200">
                        <svg class="w-5 h-5 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                        <h3 class="text-base sm:text-lg font-semibold text-gray-800">Pregnancy Monitoring</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Gestational Age (weeks) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="gestational_age" id="gestational_age" 
                                value="{{ old('gestational_age', $visit->gestational_age) }}" 
                                step="0.5" min="4" max="42" required
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1" id="ga_hint">Based on LMP: Will auto-validate</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Fundic Height (cm)
                            </label>
                            <input type="text" name="fundic_height" 
                                value="{{ old('fundic_height', $visit->fundic_height) }}" 
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Fetal Heart Tone
                            </label>
                            <select name="fetal_heart_tone" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select</option>
                                <option value="Regular 120-160" {{ old('fetal_heart_tone', $visit->fetal_heart_tone) == 'Regular 120-160' ? 'selected' : '' }}>Regular (120-160 bpm)</option>
                                <option value="Tachycardia >160" {{ old('fetal_heart_tone', $visit->fetal_heart_tone) == 'Tachycardia >160' ? 'selected' : '' }}>Tachycardia (>160 bpm)</option>
                                <option value="Bradycardia <120" {{ old('fetal_heart_tone', $visit->fetal_heart_tone) == 'Bradycardia <120' ? 'selected' : '' }}>Bradycardia (<120 bpm)</option>
                                <option value="Irregular" {{ old('fetal_heart_tone', $visit->fetal_heart_tone) == 'Irregular' ? 'selected' : '' }}>Irregular</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Fetal Movement
                            </label>
                            <select name="fetal_movement" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select</option>
                                <option value="Active" {{ old('fetal_movement', $visit->fetal_movement) == 'Active' ? 'selected' : '' }}>Active</option>
                                <option value="Normal" {{ old('fetal_movement', $visit->fetal_movement) == 'Normal' ? 'selected' : '' }}>Normal</option>
                                <option value="Decreased" {{ old('fetal_movement', $visit->fetal_movement) == 'Decreased' ? 'selected' : '' }}>Decreased</option>
                                <option value="Absent" {{ old('fetal_movement', $visit->fetal_movement) == 'Absent' ? 'selected' : '' }}>Absent</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Risk Factors -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-200">
                        <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <h3 class="text-base sm:text-lg font-semibold text-gray-800">Risk Factors</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Hypertension</label>
                            <select name="hypertension" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="0" {{ old('hypertension', $visit->hypertension) == '0' ? 'selected' : '' }}>No</option>
                                <option value="1" {{ old('hypertension', $visit->hypertension) == '1' ? 'selected' : '' }}>Yes</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Diabetes</label>
                            <select name="diabetes" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="0" {{ old('diabetes', $visit->diabetes) == '0' ? 'selected' : '' }}>No</option>
                                <option value="1" {{ old('diabetes', $visit->diabetes) == '1' ? 'selected' : '' }}>Yes</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Anemia</label>
                            <select name="anemia" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="0" {{ old('anemia', $visit->anemia) == '0' ? 'selected' : '' }}>No</option>
                                <option value="1" {{ old('anemia', $visit->anemia) == '1' ? 'selected' : '' }}>Yes</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Clinical Examination -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-200">
                        <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        <h3 class="text-base sm:text-lg font-semibold text-gray-800">Clinical Examination</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Presenting Part</label>
                            <select name="presenting_part" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select</option>
                                <option value="Cephalic" {{ old('presenting_part', $visit->presenting_part) == 'Cephalic' ? 'selected' : '' }}>Cephalic (Head down)</option>
                                <option value="Breech" {{ old('presenting_part', $visit->presenting_part) == 'Breech' ? 'selected' : '' }}>Breech</option>
                                <option value="Transverse" {{ old('presenting_part', $visit->presenting_part) == 'Transverse' ? 'selected' : '' }}>Transverse</option>
                                <option value="Oblique" {{ old('presenting_part', $visit->presenting_part) == 'Oblique' ? 'selected' : '' }}>Oblique</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Uterine Activity</label>
                            <select name="uterine_activity" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select</option>
                                <option value="Normal" {{ old('uterine_activity', $visit->uterine_activity) == 'Normal' ? 'selected' : '' }}>Normal</option>
                                <option value="Hypertonic" {{ old('uterine_activity', $visit->uterine_activity) == 'Hypertonic' ? 'selected' : '' }}>Hypertonic</option>
                                <option value="Hypotonic" {{ old('uterine_activity', $visit->uterine_activity) == 'Hypotonic' ? 'selected' : '' }}>Hypotonic</option>
                                <option value="Contracting" {{ old('uterine_activity', $visit->uterine_activity) == 'Contracting' ? 'selected' : '' }}>Contracting</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Cervical Dilation (cm)</label>
                            <input type="number" name="cervical_dilation" 
                                value="{{ old('cervical_dilation', $visit->cervical_dilation) }}" 
                                step="0.5" min="0" max="10"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Bag of Water</label>
                            <select name="bag_of_water" class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="">Select</option>
                                <option value="Intact" {{ old('bag_of_water', $visit->bag_of_water) == 'Intact' ? 'selected' : '' }}>Intact</option>
                                <option value="Ruptured" {{ old('bag_of_water', $visit->bag_of_water) == 'Ruptured' ? 'selected' : '' }}>Ruptured</option>
                                <option value="Leaking" {{ old('bag_of_water', $visit->bag_of_water) == 'Leaking' ? 'selected' : '' }}>Leaking</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Assessment & Plan -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-200">
                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h3 class="text-base sm:text-lg font-semibold text-gray-800">Assessment & Plan</h3>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Treatment Plan</label>
                            <textarea name="treatment_plan" rows="3" 
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">{{ old('treatment_plan', $visit->treatment_plan) }}</textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Next Visit Date</label>
                            <input type="date" name="next_visit_date" id="next_visit_date" 
                                value="{{ old('next_visit_date', $visit->next_visit_date) }}" 
                                min="{{ date('Y-m-d') }}"
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Current risk level: <strong class="{{ $visit->risk_level == 'HIGH' ? 'text-red-600' : 'text-green-600' }}">{{ $visit->risk_level }}</strong></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Additional Notes</label>
                            <textarea name="notes" rows="2" 
                                class="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">{{ old('notes', $visit->notes) }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Current Risk Display -->
@if($visit->risk_reasons)
<div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
    <h4 class="text-sm font-semibold text-gray-700 mb-2">Current Risk Assessment</h4>
    <div class="flex items-center gap-2 mb-2">
        <span class="px-2 py-1 rounded-full text-xs font-semibold {{ $visit->risk_level == 'HIGH' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
            {{ $visit->risk_level }} RISK
        </span>
    </div>
    
    @if($visit->risk_reasons)
    <p class="text-xs text-gray-600 mt-1">
        <strong>Risk factors:</strong> 
        @php
            // Check if risk_reasons is already an array or needs to be decoded
            $riskReasons = is_string($visit->risk_reasons) ? json_decode($visit->risk_reasons, true) : $visit->risk_reasons;
        @endphp
        @if(is_array($riskReasons) && count($riskReasons) > 0)
            @foreach($riskReasons as $reason)
                <span class="inline-block px-2 py-0.5 bg-gray-200 rounded text-xs mr-1 mb-1">{{ $reason }}</span>
            @endforeach
        @else
            <span class="text-gray-500">No specific risk factors listed</span>
        @endif
    </p>
    @endif
    
    <p class="text-xs text-gray-500 mt-2">Note: Risk assessment will be re-evaluated upon update</p>
</div>
@endif

                <!-- Submit Buttons -->
                <div class="flex flex-col sm:flex-row justify-end gap-3 pt-4 border-t border-gray-200">
                    <a href="{{ route('prenatal-visits.index') }}" class="order-2 sm:order-1 px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition text-center">
                        Cancel
                    </a>
                    <button type="submit" class="order-1 sm:order-2 px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition shadow-sm font-medium">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Update Prenatal Visit
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Update Confirmation Modal --}}
    <div id="updateConfirmationModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4 py-6 sm:px-6">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full mx-4 p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m-2 10a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Confirm Update</h3>
                    <p class="text-sm text-gray-500">Do you want to save changes to this prenatal visit?</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" id="cancelUpdateBtn" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">Cancel</button>
                <button type="button" id="confirmUpdateBtn" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition font-medium">Save changes</button>
            </div>
        </div>
    </div>

    <div id="patientData" data-json='@json($visit->patient)' class="hidden"></div>
    <script>
        // Same JavaScript validation as create form
        document.addEventListener('DOMContentLoaded', function() {
            const bpSys = document.getElementById('bp_sys');
            const bpDia = document.getElementById('bp_dia');
            const weight = document.getElementById('weight');
            const temperature = document.getElementById('temperature');
            const gestationalAge = document.getElementById('gestational_age');
            const patientDataEl = document.getElementById('patientData');
            const patientData = patientDataEl ? JSON.parse(patientDataEl.dataset.json || '{}') : {};
            const visitDate = document.getElementById('visit_date');
            const gaHint = document.getElementById('ga_hint');

            function showError(element, message) {
                element.classList.add('border-red-500');
                let errorDiv = element.parentElement.querySelector('.error-message');
                if (!errorDiv) {
                    errorDiv = document.createElement('p');
                    errorDiv.className = 'error-message text-red-500 text-xs mt-1';
                    element.parentElement.appendChild(errorDiv);
                }
                errorDiv.textContent = message;
            }

            function hideError(element) {
                element.classList.remove('border-red-500');
                const errorDiv = element.parentElement.querySelector('.error-message');
                if (errorDiv) errorDiv.remove();
            }

            function validateBP() {
                const sys = parseFloat(bpSys.value);
                const dia = parseFloat(bpDia.value);
                if (sys && dia && sys <= dia) {
                    showError(bpSys, 'Systolic BP must be greater than Diastolic BP');
                    showError(bpDia, 'Systolic BP must be greater than Diastolic BP');
                    return false;
                }
                hideError(bpSys);
                hideError(bpDia);
                return true;
            }

            function validateGA() {
                const lmp = patientData?.lmp;
                const ga = parseFloat(gestationalAge.value);
                const visitDateValue = visitDate.value;

                if (lmp && ga && visitDateValue) {
                    const lmpDate = new Date(lmp);
                    const visitDateObj = new Date(visitDateValue);
                    const diffDays = (visitDateObj - lmpDate) / (1000 * 60 * 60 * 24);
                    const expectedWeeks = diffDays / 7;
                    
                    if (Math.abs(expectedWeeks - ga) > 3) {
                        showError(gestationalAge, `Based on LMP (${lmp}), expected GA is ${expectedWeeks.toFixed(1)} weeks`);
                        gaHint.innerHTML = `⚠️ Expected GA: ${expectedWeeks.toFixed(1)} weeks based on LMP`;
                        gaHint.classList.add('text-orange-600');
                        return false;
                    } else {
                        hideError(gestationalAge);
                        gaHint.innerHTML = `✅ Based on LMP, expected GA: ${expectedWeeks.toFixed(1)} weeks`;
                        gaHint.classList.remove('text-orange-600');
                        gaHint.classList.add('text-green-600');
                        return true;
                    }
                }
                return true;
            }

            bpSys?.addEventListener('input', validateBP);
            bpDia?.addEventListener('input', validateBP);
            gestationalAge?.addEventListener('input', validateGA);
            visitDate?.addEventListener('change', validateGA);

            const prenatalForm = document.getElementById('prenatalForm');
            const updateConfirmationModal = document.getElementById('updateConfirmationModal');
            const confirmUpdateBtn = document.getElementById('confirmUpdateBtn');
            const cancelUpdateBtn = document.getElementById('cancelUpdateBtn');
            let updateSubmitConfirmed = false;

            function openUpdateConfirmationModal() {
                if (updateConfirmationModal) {
                    updateConfirmationModal.classList.remove('hidden');
                    updateConfirmationModal.classList.add('flex');
                }
            }

            function closeUpdateConfirmationModal() {
                if (updateConfirmationModal) {
                    updateConfirmationModal.classList.add('hidden');
                    updateConfirmationModal.classList.remove('flex');
                }
            }

            prenatalForm?.addEventListener('submit', function(event) {
                if (updateSubmitConfirmed) {
                    return;
                }

                const validBP = validateBP();
                const validGA = validateGA();
                if (!validBP || !validGA) {
                    return;
                }

                event.preventDefault();
                openUpdateConfirmationModal();
            });

            confirmUpdateBtn?.addEventListener('click', function() {
                updateSubmitConfirmed = true;
                closeUpdateConfirmationModal();
                prenatalForm?.submit();
            });

            cancelUpdateBtn?.addEventListener('click', function() {
                closeUpdateConfirmationModal();
            });
        });
    </script>
</x-app-layout>