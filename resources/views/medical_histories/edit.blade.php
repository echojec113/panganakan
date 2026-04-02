<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900">Edit Medical History</h1>
                <p class="text-sm text-gray-500 mt-1">Update the patient's medical conditions and risk history.</p>
            </div>
            <a href="{{ route('patients.show', $history->patient_id) }}" class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-100 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Patient Profile
            </a>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-800">Medical History Details</h2>
                <p class="text-sm text-gray-500 mt-1">Toggle the conditions that apply to this patient.</p>
            </div>

            <form action="{{ route('medical-histories.update', $history->id) }}" method="POST" class="px-6 py-6 space-y-6">
                @csrf
                @method('PUT')

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @php
                        $fields = [
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
                            'std_history' => 'STD History',
                        ];
                    @endphp

                    @foreach($fields as $name => $label)
                        <label class="flex items-center gap-3 p-4 border border-gray-200 rounded-2xl cursor-pointer hover:border-blue-300 transition">
                            <input type="checkbox" name="{{ $name }}" value="1" {{ $history->$name ? 'checked' : '' }} class="h-5 w-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500" />
                            <span class="text-sm text-gray-700">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <a href="{{ route('patients.show', $history->patient_id) }}" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-100 transition">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center justify-center px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition">
                        Update Medical History
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>