<x-app-layout>
    <style>
        .prenatal-responsive { container-type: inline-size; min-width: 0; overflow-wrap: anywhere; }
        .prenatal-responsive *, .prenatal-responsive *::before, .prenatal-responsive *::after { box-sizing: border-box; }
        .prenatal-responsive .grid > *, .prenatal-responsive .flex > * { min-width: 0; }
        .prenatal-responsive .app-page-header-actions { flex-wrap: wrap; }
        .prenatal-dialog > div { min-width: 0; max-height: calc(100dvh - 3rem); overflow-y: auto; margin-inline: 0; overflow-wrap: anywhere; }
        .prenatal-dialog { padding-block: 1.5rem; }
        .prenatal-dialog button { white-space: normal; }

        .prenatal-responsive #prenatalForm input:not([type="hidden"]), .prenatal-responsive #prenatalForm select, .prenatal-responsive #prenatalForm textarea { min-width: 0; max-width: 100%; }
        .prenatal-responsive #prenatalForm .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .prenatal-responsive #prenatalForm summary { flex-wrap: wrap; gap: .5rem; }
        .prenatal-responsive #prenatalForm svg { flex-shrink: 0; }
        .prenatal-responsive #prenatalForm [class~="ml-auto"] { margin-left: 0; }
        .prenatal-responsive #prenatalForm .flex:has(> [class~="ml-auto"]) { flex-wrap: wrap; }
        @container (min-width: 950px) {
            .prenatal-responsive #prenatalForm [class~="lg:grid-cols-3"] { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @container (max-width: 600px) {
            .prenatal-responsive #prenatalForm .grid { grid-template-columns: minmax(0, 1fr); }
        }
    </style>
    <div class="prenatal-responsive min-h-screen bg-[#FCFBF8]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            <x-app-header class="mb-6 sm:mb-8">
                <x-slot name="title">Add Prenatal Visit</x-slot>
                <x-slot name="subtitle">Record a new prenatal check-up with risk assessment.</x-slot>
                <x-slot name="actions">
                    <a href="{{ route('prenatal-visits.index') }}" class="btn btn-secondary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to Prenatal Visits
                    </a>
                </x-slot>
            </x-app-header>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <form action="{{ route('prenatal-visits.store') }}" method="POST" id="prenatalForm" class="p-4 sm:p-6">
                @csrf

                <!-- Error Summary -->
                <x-error-summary :errors="$errors" title="Please fix the following errors:" class="mb-6" />

                <!-- CDSS Input Guide -->
                <details class="mb-6 rounded-lg border border-gray-100 bg-gray-50 overflow-hidden">
                    <summary class="px-4 py-3 flex items-center justify-between cursor-pointer list-none select-none">
                        <span class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            CDSS Input Guide
                        </span>
                        <span class="text-xs text-gray-500">What triggers each risk factor</span>
                    </summary>
                    <div class="px-4 pb-4 pt-1">
                        <ul class="space-y-1.5 text-xs text-gray-600">
                            <li><span class="font-semibold">Maternal age</span> — under 19 flags a teenage-pregnancy factor; 35 or older with a first pregnancy (gravida 1, para 0) flags an advanced maternal age factor.</li>
                            <li><span class="font-semibold">Blood Pressure</span> — confirms elevated readings; a severe reading (<span class="font-mono">≥160/110</span>) triggers an urgent clinical review and a repeat reading.</li>
                            <li><span class="font-semibold">Diabetes (confirmed)</span> — flagged during this visit is treated as a current-condition factor.</li>
                            <li><span class="font-semibold">Anemia (confirmed)</span> — flagged during this visit is treated as a current-condition factor.</li>
                            <li><span class="font-semibold">Obstetric history</span> — previous Cesarean section and prior recurrent miscarriage contribute to the rule-based assessment.</li>
                            <li><span class="font-semibold">Ultrasound findings</span> — abnormal presentation (breech, transverse, oblique), low/high amniotic fluid, or a weak/abnormal/absent fetal heartbeat contribute to the rule-based assessment.</li>
                        </ul>
                    </div>
                </details>

                <!-- Patient Selection -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Patient <span class="text-red-500">*</span>
                    </label>
                    @if($lockedPatient ?? null)
                        <div class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-lg bg-gray-50">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                            <span class="text-sm text-gray-700 font-medium">{{ $lockedPatient->first_name }} {{ $lockedPatient->last_name }} (Age: {{ $lockedPatient->age }}) - {{ $lockedPatient->status }}</span>
                            <span class="text-xs text-gray-500 ml-auto">Locked from profile</span>
                        </div>
                        <input type="hidden" name="patient_id" value="{{ $lockedPatient->id }}" data-lmp="{{ $lockedPatient->lmp?->toDateString() }}">
                    @else
                        <select name="patient_id" id="patient_id" required
                            class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition bg-white">
                            <option value="">Select Patient</option>
                            @foreach($patients as $patient)
                                <option value="{{ $patient->id }}"
                                    data-age="{{ $patient->age }}"
                                    data-gravida="{{ $patient->gravida }}"
                                    data-para="{{ $patient->para }}"
                                    data-previous-cs="{{ $patient->previous_cs }}"
                                    data-miscarriage="{{ $patient->miscarriage }}"
                                    data-lmp="{{ $patient->lmp?->toDateString() }}"
                                    {{ old('patient_id') == $patient->id ? 'selected' : '' }}>
                                    {{ $patient->first_name }} {{ $patient->last_name }} (Age: {{ $patient->age }}) - {{ $patient->status }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Select the patient for this prenatal visit</p>
                    @endif
                    @if($sourcePreview ?? null)
                    <div id="source-preview" class="mt-3 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-900"
                        data-preselected-patient="{{ $selectedPatient ?? '' }}">
                        <p class="font-semibold text-blue-900">Source preview (preselected patient)</p>
                        {{ $sourcePreview }}
                        <p class="mt-1 text-[11px] text-blue-700/80">This preview reflects the patient selected before the form opened. Changing the patient on this page re-runs the preview when the form is loaded again; the assessment itself is always computed from the submitted patient.</p>
                    </div>
                    @endif
                </div>

                <!-- Visit Date -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Visit Date <span class="text-red-500">*</span>
                    </label>
                    <input type="date" name="visit_date" id="visit_date"
                        value="{{ old('visit_date', date('Y-m-d')) }}"
                        max="{{ date('Y-m-d') }}" required
                        class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                    <p class="text-xs text-gray-500 mt-1">Cannot be in the future</p>
                </div>

                <!-- Vital Signs Section -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-100">
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="text-lg font-semibold text-gray-800">Vital Signs</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                BP Systolic (mmHg) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="bp_sys" id="bp_sys"
                                value="{{ old('bp_sys') }}"
                                min="60" max="480" required
                                placeholder="e.g., 120"
                                class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                            <p class="text-xs text-gray-500 mt-1">Normal: 90-120 | Range: 60-480</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                BP Diastolic (mmHg) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="bp_dia" id="bp_dia"
                                value="{{ old('bp_dia') }}"
                                min="40" max="350" required
                                placeholder="e.g., 80"
                                class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                            <p class="text-xs text-gray-500 mt-1">Normal: 60-80 | Range: 40-350</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Weight (kg) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="weight" id="weight"
                                value="{{ old('weight') }}"
                                step="0.1" min="30" max="250" required
                                placeholder="e.g., 65.5"
                                class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                            <p class="text-xs text-gray-500 mt-1">Range: 30-250 kg</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Temperature (°C)
                            </label>
                            <input type="number" name="temperature" id="temperature"
                                value="{{ old('temperature') }}"
                                step="0.1" min="35" max="40"
                                placeholder="e.g., 36.5"
                                class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                            <p class="text-xs text-gray-500 mt-1">Normal: 36.5-37.5°C | Range: 35-40°C</p>
                        </div>
                    </div>

                    <!-- Repeat BP Verification (shown when initial BP is elevated) -->
                    <div id="repeatBpSection" class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg hidden">
                        <div class="flex items-center gap-2 mb-3">
                            <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <h4 class="text-sm font-semibold text-amber-800">BP Verification</h4>
                        </div>
                        <p class="text-xs text-amber-700 mb-3">Initial BP is elevated. Record a repeat measurement according to the clinic's approved protocol to verify.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Repeat BP Systolic (mmHg)</label>
                                <input type="number" name="repeat_bp_sys" id="repeat_bp_sys"
                                    value="{{ old('repeat_bp_sys') }}"
                                    min="60" max="480" step="1"
                                    placeholder="e.g., 130"
                                    class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                                <p class="text-xs text-gray-500 mt-1">Range: 60-480 mmHg</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Repeat BP Diastolic (mmHg)</label>
                                <input type="number" name="repeat_bp_dia" id="repeat_bp_dia"
                                    value="{{ old('repeat_bp_dia') }}"
                                    min="40" max="350" step="1"
                                    placeholder="e.g., 85"
                                    class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                                <p class="text-xs text-gray-500 mt-1">Range: 40-350 mmHg</p>
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Verification Status</label>
                            <select name="bp_verification_status" id="bp_verification_status"
                                class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                                <option value="">Select status</option>
                                <option value="UNABLE_TO_REPEAT" {{ old('bp_verification_status') == 'UNABLE_TO_REPEAT' ? 'selected' : '' }}>Unable to Repeat</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Verification status is derived automatically from the repeat measurement.</p>
                        </div>
                        <div class="mt-3">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Verification Note</label>
                            <textarea name="bp_verification_note" rows="2"
                                placeholder="Optional note about the verification..."
                                class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">{{ old('bp_verification_note') }}</textarea>
                        </div>
                    </div>

                </div>

                <!-- Pregnancy Monitoring -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-100">
                        <svg class="w-5 h-5 text-pink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                        <h3 class="text-lg font-semibold text-gray-800">Pregnancy Monitoring</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Gestational Age (weeks) <span class="text-red-500">*</span>
                            </label>
                            <input type="number" name="gestational_age" id="gestational_age"
                                value="{{ old('gestational_age', $expectedGestationalAge !== null ? number_format($expectedGestationalAge, 1, '.', '') : '') }}"
                                data-ga-reference="visit_date"
                                data-ga-lmp="{{ $lockedPatient?->lmp?->toDateString() }}"
                                data-ga-old-input="{{ $hasOldGestationalAge ? 'true' : 'false' }}"
                                data-ga-initial-expected="{{ $expectedGestationalAge !== null ? number_format($expectedGestationalAge, 1, '.', '') : '' }}"
                                step="0.1" min="4" max="42" required
                                placeholder="e.g., 28"
                                class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                            <p class="text-xs text-gray-500 mt-1" id="ga_hint">{{ $gestationalAgeHint }}</p>
                            <div class="mt-2">
                                <span id="trimester_indicator" class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-100 px-3 py-1.5 text-sm font-semibold text-gray-800 whitespace-nowrap">—</span>
                            </div>
                        </div>
                        <div data-trimester-visible="2,3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Fundic Height (cm)
                            </label>
                            <input type="text" name="fundic_height"
                                value="{{ old('fundic_height') }}"
                                placeholder="e.g., 28"
                                class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                        </div>
                        <div data-trimester-visible="2,3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Fetal Heart Tone
                            </label>
                            <select name="fetal_heart_tone" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                                <option value="">Select</option>
                                <option value="Regular 120-160" {{ old('fetal_heart_tone') == 'Regular 120-160' ? 'selected' : '' }}>Regular (120-160 bpm)</option>
                                <option value="Tachycardia >160" {{ old('fetal_heart_tone') == 'Tachycardia >160' ? 'selected' : '' }}>Tachycardia (>160 bpm)</option>
                                <option value="Bradycardia <120" {{ old('fetal_heart_tone') == 'Bradycardia <120' ? 'selected' : '' }}>Bradycardia (<120 bpm)</option>
                                <option value="Irregular" {{ old('fetal_heart_tone') == 'Irregular' ? 'selected' : '' }}>Irregular</option>
                            </select>
                        </div>
                        <div data-trimester-visible="2,3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Fetal Movement
                            </label>
                            <select name="fetal_movement" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                                <option value="">Select</option>
                                <option value="Active" {{ old('fetal_movement') == 'Active' ? 'selected' : '' }}>Active</option>
                                <option value="Normal" {{ old('fetal_movement') == 'Normal' ? 'selected' : '' }}>Normal</option>
                                <option value="Decreased" {{ old('fetal_movement') == 'Decreased' ? 'selected' : '' }}>Decreased</option>
                                <option value="Absent" {{ old('fetal_movement') == 'Absent' ? 'selected' : '' }}>Absent</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Risk Factors -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-100">
                        <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <h3 class="text-lg font-semibold text-gray-800">Risk Factors</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Hypertension</label>
                            <select name="hypertension" id="hypertension" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                                <option value="0" {{ old('hypertension') == '0' ? 'selected' : '' }}>No</option>
                                <option value="1" {{ old('hypertension') == '1' ? 'selected' : '' }}>Yes</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Diabetes</label>
                            <select name="diabetes" id="diabetes" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                                <option value="0" {{ old('diabetes') == '0' ? 'selected' : '' }}>No</option>
                                <option value="1" {{ old('diabetes') == '1' ? 'selected' : '' }}>Yes</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Anemia</label>
                            <select name="anemia" id="anemia" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                                <option value="0" {{ old('anemia') == '0' ? 'selected' : '' }}>No</option>
                                <option value="1" {{ old('anemia') == '1' ? 'selected' : '' }}>Yes</option>
                            </select>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-3">
                        Diabetes and anemia are assessed for this visit. When marked Yes, the existing Medical History background record will also be updated. A No value does not automatically remove a previously recorded condition.
                    </p>
                </div>

                <!-- Clinical Examination -->
                <div class="mb-6" data-trimester-visible="3">
                    <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-100">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        <h3 class="text-lg font-semibold text-gray-800">Clinical Examination</h3>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div data-trimester-visible="3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Presenting Part</label>
                            <select name="presenting_part" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                                <option value="">Select</option>
                                <option value="Cephalic" {{ old('presenting_part') == 'Cephalic' ? 'selected' : '' }}>Cephalic (Head down)</option>
                                <option value="Breech" {{ old('presenting_part') == 'Breech' ? 'selected' : '' }}>Breech</option>
                                <option value="Transverse" {{ old('presenting_part') == 'Transverse' ? 'selected' : '' }}>Transverse</option>
                                <option value="Oblique" {{ old('presenting_part') == 'Oblique' ? 'selected' : '' }}>Oblique</option>
                            </select>
                        </div>
                        <div data-trimester-visible="3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Uterine Activity</label>
                            <select name="uterine_activity" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                                <option value="">Select</option>
                                <option value="Normal" {{ old('uterine_activity') == 'Normal' ? 'selected' : '' }}>Normal</option>
                                <option value="Hypertonic" {{ old('uterine_activity') == 'Hypertonic' ? 'selected' : '' }}>Hypertonic</option>
                                <option value="Hypotonic" {{ old('uterine_activity') == 'Hypotonic' ? 'selected' : '' }}>Hypotonic</option>
                                <option value="Contracting" {{ old('uterine_activity') == 'Contracting' ? 'selected' : '' }}>Contracting</option>
                            </select>
                        </div>
                        <div data-trimester-visible="3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Cervical Dilation (cm)</label>
                            <input type="number" name="cervical_dilation"
                                value="{{ old('cervical_dilation') }}"
                                step="0.5" min="0" max="10"
                                placeholder="e.g., 0"
                                class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                        </div>
                        <div data-trimester-visible="3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Bag of Water</label>
                            <select name="bag_of_water" class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                                <option value="">Select</option>
                                <option value="Intact" {{ old('bag_of_water') == 'Intact' ? 'selected' : '' }}>Intact</option>
                                <option value="Ruptured" {{ old('bag_of_water') == 'Ruptured' ? 'selected' : '' }}>Ruptured</option>
                                <option value="Leaking" {{ old('bag_of_water') == 'Leaking' ? 'selected' : '' }}>Leaking</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Assessment & Plan -->
                <div class="mb-6">
                    <div class="flex items-center gap-2 mb-4 pb-2 border-b border-gray-100">
                        <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <h3 class="text-lg font-semibold text-gray-800">Assessment & Plan</h3>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Treatment Plan</label>
                            <textarea name="treatment_plan" rows="3"
                                placeholder="Describe the treatment plan, medications prescribed, referrals, etc."
                                class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">{{ old('treatment_plan') }}</textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Next Visit Date</label>
                            <input type="date" name="next_visit_date" id="next_visit_date"
                                value="{{ old('next_visit_date') }}"
                                data-min-date="{{ today()->toDateString() }}"
                                class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                            <p class="text-xs text-gray-500 mt-1">Leave empty for system-generated recommendation (3 days for HIGH risk, 30 days for LOW risk)</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Additional Notes</label>
                            <textarea name="notes" rows="2"
                                placeholder="Any additional observations or notes..."
                                class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex flex-col sm:flex-row justify-end gap-3 pt-6 border-t border-gray-100">
                <a href="{{ route('prenatal-visits.index') }}" class="w-full sm:w-auto order-2 sm:order-1 px-6 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition text-center">
                        Cancel
                    </a>
                    <button type="submit" id="submitBtn" class="w-full sm:w-auto order-1 sm:order-2 px-6 py-2 bg-[#55B85A] text-white rounded-lg text-sm font-medium hover:bg-[#4aa04c] transition shadow-sm">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                        </svg>
                        Save Prenatal Visit
                    </button>
                </div>
            </form>

            <div id="validationModal" class="prenatal-dialog fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4 py-4 sm:px-6 sm:py-6 overflow-y-auto">
                <div class="bg-white rounded-xl shadow-xl max-w-sm w-full mx-4 my-4 sm:my-auto p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-semibold text-gray-900 truncate">Validation Error</h3>
                            <p class="text-sm text-gray-500">All required prenatal visit details must be filled.</p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" id="validationOkBtn" class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">OK</button>
                    </div>
                </div>
            </div>

            <div id="confirmSaveModal" class="prenatal-dialog fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4 py-4 sm:px-6 sm:py-6 overflow-y-auto">
                <div class="bg-white rounded-xl shadow-xl max-w-sm w-full mx-4 my-4 sm:my-auto p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-[#55B85A] flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-semibold text-gray-900 truncate">Confirm Save</h3>
                            <p class="text-sm text-gray-500">Are you sure you want to save this prenatal visit?</p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <button type="button" id="cancelConfirmSaveBtn" class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">Cancel</button>
                        <button type="button" id="confirmSaveBtn" class="px-4 py-2 bg-[#55B85A] text-white rounded-lg text-sm hover:bg-[#4aa04c] transition font-medium">Save</button>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>

    @include('components.gestational-age-autofill')
    @include('components.trimester-visibility')

    <!-- JavaScript Validation -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get form elements
            const form = document.getElementById('prenatalForm');
            const bpSys = document.getElementById('bp_sys');
            const bpDia = document.getElementById('bp_dia');
            const weight = document.getElementById('weight');
            const temperature = document.getElementById('temperature');
            const patientSelect = document.getElementById('patient_id');
            const visitDate = document.getElementById('visit_date');
            const nextVisitDate = document.getElementById('next_visit_date');

            // Helper function to show error
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

            let confirmedSubmit = false;

            function hideError(element) {
                element.classList.remove('border-red-500');
                const errorDiv = element.parentElement.querySelector('.error-message');
                if (errorDiv) {
                    errorDiv.remove();
                }
            }

            function validateNextVisitDate() {
                if (!nextVisitDate || !/^\d{4}-\d{2}-\d{2}$/.test(nextVisitDate.value)) {
                    if (nextVisitDate) hideError(nextVisitDate);
                    return true;
                }

                if (nextVisitDate.value < nextVisitDate.dataset.minDate) {
                    showError(nextVisitDate, 'Next visit date must be today or in the future');
                    return false;
                }

                hideError(nextVisitDate);
                return true;
            }

            function openValidationModal() {
                const modal = document.getElementById('validationModal');
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }
            }

            function closeValidationModal() {
                const modal = document.getElementById('validationModal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            }

            function openConfirmSaveModal() {
                const modal = document.getElementById('confirmSaveModal');
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }
            }

            function closeConfirmSaveModal() {
                const modal = document.getElementById('confirmSaveModal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            }

            // BP Range Validation
            function validateBPRange(input, min, max, fieldName) {
                const val = parseFloat(input.value);
                if (isNaN(val)) {
                    hideError(input);
                    return true;
                }
                if (val > max) {
                    input.value = max;
                    showError(input, fieldName + ' must not exceed ' + max + ' mmHg');
                    return false;
                }
                if (val < min) {
                    showError(input, fieldName + ' must be at least ' + min + ' mmHg');
                    return false;
                }
                hideError(input);
                return true;
            }

            // BP Logic Validation
            function validateBP() {
                const sys = parseFloat(bpSys.value);
                const dia = parseFloat(bpDia.value);

                if (sys && dia) {
                    if (sys <= dia) {
                        showError(bpSys, 'Systolic BP must be greater than Diastolic BP');
                        showError(bpDia, 'Systolic BP must be greater than Diastolic BP');
                        return false;
                    } else {
                        hideError(bpSys);
                        hideError(bpDia);
                    }

                    // Severe hypertension warning
                    if (sys >= 160 || dia >= 110) {
                        showWarning('Severe hypertension detected! Immediate medical evaluation required.', 'bp_warning');
                    } else if (sys >= 140 || dia >= 90) {
                        showWarning('Elevated blood pressure. Monitor closely.', 'bp_warning');
                    } else {
                        clearWarning('bp_warning');
                    }

                    // Show/hide repeat BP section
                    const repeatSection = document.getElementById('repeatBpSection');
                    if (repeatSection) {
                        if ((sys >= 140 || dia >= 90) && sys && dia) {
                            repeatSection.classList.remove('hidden');
                        } else {
                            repeatSection.classList.add('hidden');
                        }
                    }
                }
                return true;
            }

            // Show warning message
            function showWarning(message, id) {
                let warningDiv = document.getElementById(id);
                if (!warningDiv) {
                    warningDiv = document.createElement('div');
                    warningDiv.id = id;
                    warningDiv.className = 'mt-2 p-2 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 text-xs rounded';
                    bpSys.parentElement.parentElement.appendChild(warningDiv);
                }
                warningDiv.innerHTML = message;
            }

            function clearWarning(id) {
                const warning = document.getElementById(id);
                if (warning) warning.remove();
            }

            // Weight validation
            function validateWeight() {
                const wt = parseFloat(weight.value);
                if (wt && (wt < 30 || wt > 250)) {
                    showError(weight, 'Weight must be between 30kg and 250kg');
                    return false;
                }
                hideError(weight);
                return true;
            }

            // Temperature validation
            function validateTemperature() {
                const temp = parseFloat(temperature.value);
                if (temp && (temp < 35 || temp > 40)) {
                    showError(temperature, 'Temperature must be between 35°C and 40°C');
                    return false;
                }
                if (temp && temp >= 38) {
                    showWarning('Fever detected! Monitor for infection.', 'temp_warning');
                } else {
                    clearWarning('temp_warning');
                }
                hideError(temperature);
                return true;
            }

            // Gestational Age vs LMP validation
            function validateGA() {
                return window.validateGestationalAge();
            }

            // Auto-calculate next visit based on risk
            function autoCalculateNextVisit() {
                // This would be triggered after form submission or risk calculation
                // For now, just a placeholder
            }

            // Add event listeners
            bpSys?.addEventListener('input', function() {
                validateBPRange(this, 60, 480, 'Systolic blood pressure');
                validateBP();
            });
            bpDia?.addEventListener('input', function() {
                validateBPRange(this, 40, 350, 'Diastolic blood pressure');
                validateBP();
            });
            weight?.addEventListener('input', validateWeight);
            temperature?.addEventListener('input', validateTemperature);
            nextVisitDate?.addEventListener('input', validateNextVisitDate);
            nextVisitDate?.addEventListener('change', validateNextVisitDate);
            validateNextVisitDate();
            // Form submission validation
            form?.addEventListener('submit', function(e) {
                let isValid = true;

                if (!validateBP()) isValid = false;
                if (!validateWeight()) isValid = false;
                if (!validateTemperature()) isValid = false;
                if (!validateGA()) isValid = false;
                if (!validateNextVisitDate()) isValid = false;

                // Check if patient is selected
                const patientInput = patientSelect || form.querySelector('input[name="patient_id"]');
                if (!patientInput?.value) {
                    showError(patientInput, 'Please select a patient');
                    isValid = false;
                } else {
                    hideError(patientInput);
                }

                // Check if visit date is valid
                if (!visitDate.value) {
                    showError(visitDate, 'Visit date is required');
                    isValid = false;
                } else {
                    hideError(visitDate);
                }

                if (!isValid) {
                    e.preventDefault();
                    // Scroll to first error
                    const firstError = document.querySelector('.border-red-500');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    openValidationModal();
                    return;
                }

                if (!confirmedSubmit) {
                    e.preventDefault();
                    openConfirmSaveModal();
                    return;
                }

                confirmedSubmit = false;
            });

            document.getElementById('validationOkBtn')?.addEventListener('click', function() {
                closeValidationModal();
            });

            document.getElementById('cancelConfirmSaveBtn')?.addEventListener('click', function() {
                closeConfirmSaveModal();
            });

            document.getElementById('confirmSaveBtn')?.addEventListener('click', function() {
                if (!validateNextVisitDate()) {
                    closeConfirmSaveModal();
                    return;
                }

                closeConfirmSaveModal();
                confirmedSubmit = true;
                form.submit();
            });

            function validateRepeatBP() {
                const sys = parseFloat(document.getElementById('repeat_bp_sys')?.value);
                const dia = parseFloat(document.getElementById('repeat_bp_dia')?.value);
                if (sys && dia && sys <= dia) {
                    const el = document.getElementById('repeat_bp_sys');
                    let errorDiv = el.parentElement.querySelector('.error-message');
                    if (!errorDiv) {
                        errorDiv = document.createElement('p');
                        errorDiv.className = 'error-message text-red-500 text-xs mt-1';
                        el.parentElement.appendChild(errorDiv);
                    }
                    errorDiv.textContent = 'Repeat systolic BP must be greater than diastolic BP';
                    return false;
                }
                return true;
            }

            document.getElementById('repeat_bp_sys')?.addEventListener('input', function() {
                validateBPRange(this, 60, 480, 'Systolic blood pressure');
                validateRepeatBP();
            });
            document.getElementById('repeat_bp_dia')?.addEventListener('input', function() {
                validateBPRange(this, 40, 350, 'Diastolic blood pressure');
                validateRepeatBP();
            });

            // UI-only guard: the source preview is server-generated for the
            // preselected patient only. If the user changes the patient in this
            // form, hide the preview rather than show a stale one. No client-side
            // risk calculation is performed.
            (function () {
                const preview = document.getElementById('source-preview');
                const select = document.getElementById('patient_id');
                if (!preview || !select) {
                    return;
                }
                const preselected = String(preview.dataset.preselectedPatient || '');
                select.addEventListener('change', function () {
                    if (String(this.value) !== preselected) {
                        preview.style.display = 'none';
                    } else {
                        preview.style.display = '';
                    }
                });
            })();
        });
    </script>
</x-app-layout>
