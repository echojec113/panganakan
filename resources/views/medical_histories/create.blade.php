<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <x-app-header class="mb-6">
            <x-slot name="title">Add Medical History</x-slot>
            <x-slot name="subtitle">Capture medical risk factors and history for this patient.</x-slot>
            <x-slot name="actions">
                <a href="{{ route('patients.show', $patient_id) }}" class="btn btn-secondary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back to Patient Profile
                </a>
            </x-slot>
        </x-app-header>

        <x-flash type="info" :message="session('info')" class="mb-6" />
        <x-flash type="error" :message="session('error')" class="mb-6" />

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-800">Medical History Details</h2>
                <p class="text-sm text-gray-500 mt-1">Select the conditions that apply to the patient.</p>
            </div>

            <form method="POST" action="{{ route('medical-histories.store') }}" id="medical-history-form" class="px-6 py-6 space-y-6">
                @csrf
                <input type="hidden" name="patient_id" value="{{ $patient_id }}">

                <div class="rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    <strong>Diabetes</strong> and <strong>Anemia</strong> are also assessed during prenatal visits and may affect that visit's CDSS result. This Medical History record stores pregnancy-level background information and is not directly submitted to the risk engine.
                </div>

                @php
                    $groups = [
                        'Conditions Also Assessed During Prenatal Visits' => [
                            'fields' => [
                                'diabetes' => 'Diabetes',
                                'anemia' => 'Anemia',
                            ],
                            'note' => 'Confirmed during prenatal visits and updated in this background record.',
                        ],
                        'Chronic & Background Conditions' => [
                            'fields' => [
                                'epilepsy' => 'Epilepsy',
                                'hypertension' => 'Hypertension',
                                'asthma' => 'Asthma',
                                'thyroid_disease' => 'Thyroid Disease',
                                'heart_disease' => 'Heart Disease',
                                'liver_disease' => 'Liver Disease',
                                'mental_health_condition' => 'Mental Health Condition',
                            ],
                            'note' => 'Recorded for the health record only.',
                        ],
                        'Lifestyle, History & Physical Findings' => [
                            'fields' => [
                                'smoking' => 'Smoking',
                                'allergies' => 'Allergies',
                                'drug_intake' => 'Drug Intake',
                                'std_history' => 'STD History',
                                'breast_mass' => 'Breast Mass',
                            ],
                            'note' => 'Recorded for the health record only.',
                        ],
                        'Legacy Historical or Recurring Concerns' => [
                            'fields' => [
                                'severe_headache' => 'Severe Headache',
                                'visual_disturbance' => 'Visual Disturbance',
                                'chest_pain' => 'Chest Pain',
                                'shortness_breath' => 'Shortness of Breath',
                            ],
                            'note' => 'These fields store previously reported or recurring concerns. They do not confirm that a symptom is present during the current prenatal visit and are not evaluated by the current CDSS.',
                        ],
                    ];
                @endphp

                @foreach($groups as $groupName => $group)
                    <section>
                        <div class="mb-3 flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-1">
                            <h3 class="text-sm font-semibold text-gray-800">{{ $groupName }}</h3>
                            <span class="text-xs text-gray-500">{{ $group['note'] }}</span>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($group['fields'] as $name => $label)
                                <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-2xl cursor-pointer hover:border-blue-300 transition">
                                    <input type="checkbox" name="{{ $name }}" value="1" {{ old($name) ? 'checked' : '' }} class="h-5 w-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500" />
                                    <span class="text-sm text-gray-700">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                {{-- Other Section --}}
                <section class="border-t border-gray-100 pt-6">
                    <div class="mb-3 flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-1">
                        <h3 class="text-sm font-semibold text-gray-800">Other Conditions</h3>
                        <span class="text-xs text-gray-500">Recorded for the health record only.</span>
                    </div>
                    <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-2xl cursor-pointer hover:border-blue-300 transition max-w-md">
                        <input type="checkbox" name="other" value="1" {{ old('other') ? 'checked' : '' }} class="h-5 w-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500" onchange="document.getElementById('otherSpecifyGroup').classList.toggle('hidden', !this.checked)" />
                        <span class="text-sm text-gray-700">Other</span>
                    </label>
                    <div id="otherSpecifyGroup" class="{{ old('other') ? '' : 'hidden' }} mt-3 max-w-md">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Please specify:</label>
                        <input type="text" name="other_specify" value="{{ old('other_specify') }}" placeholder="Enter condition name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                    </div>
                </section>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <a href="{{ route('patients.show', $patient_id) }}" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-100 transition">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center justify-center px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition">
                        Save Medical History
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
