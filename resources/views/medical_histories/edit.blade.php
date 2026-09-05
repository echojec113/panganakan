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

        @if(session('info'))
            <div class="mb-6 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                {{ session('info') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ session('error') }}
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-800">Medical History Details</h2>
                <p class="text-sm text-gray-500 mt-1">Toggle the conditions that apply to this patient.</p>
            </div>

            @php
                $isRevalidation = old('_token') !== null;
            @endphp

            <form action="{{ route('medical-histories.update', $history->id) }}" method="POST" id="medical-history-form" class="px-6 py-6 space-y-6">
                @csrf
                @method('PUT')

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
                                    <input type="checkbox" name="{{ $name }}" value="1" {{ $isRevalidation ? (old($name) ? 'checked' : '') : ($history->$name ? 'checked' : '') }} class="h-5 w-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500" />
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
                        <input type="checkbox" name="other" value="1" {{ $isRevalidation ? (old('other') ? 'checked' : '') : ($history->other_specify ? 'checked' : '') }} class="h-5 w-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500" onchange="document.getElementById('otherSpecifyGroup').classList.toggle('hidden', !this.checked)" />
                        <span class="text-sm text-gray-700">Other</span>
                    </label>
                    <div id="otherSpecifyGroup" class="{{ $isRevalidation ? (old('other') ? '' : 'hidden') : ($history->other_specify ? '' : 'hidden') }} mt-3 max-w-md">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Please specify:</label>
                        <input type="text" name="other_specify" value="{{ old('other_specify', $history->other_specify) }}" placeholder="Enter condition name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition">
                    </div>
                </section>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <a href="{{ route('patients.show', $history->patient_id) }}" class="inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-100 transition">
                        Cancel
                    </a>
                    <button type="button" onclick="openUpdateModal()" class="inline-flex items-center justify-center px-5 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition">
                        Update Medical History
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Update Confirmation Modal --}}
    <div id="updateModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4 py-6 sm:px-6">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Update Medical History</h3>
                    <p class="text-sm text-gray-500">Are you sure you want to update this medical history?</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeUpdateModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">Cancel</button>
                <button type="button" onclick="submitUpdateForm()" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition font-medium">Update</button>
            </div>
        </div>
    </div>

    {{-- Success Modal --}}
    <div id="successModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4 py-6 sm:px-6">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Success</h3>
                    <p class="text-sm text-gray-500">Medical history has been successfully updated.</p>
                </div>
            </div>
            <div class="flex justify-end mt-6">
                <button type="button" onclick="closeSuccessModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">OK</button>
            </div>
        </div>
    </div>

    <script>
        let medicalHistoryForm = null;

        function openUpdateModal() {
            const modal = document.getElementById('updateModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeUpdateModal() {
            const modal = document.getElementById('updateModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function submitUpdateForm() {
            closeUpdateModal();
            const form = document.getElementById('medical-history-form');
            form.submit();
        }

        function closeSuccessModal() {
            const modal = document.getElementById('successModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Show success modal if there's a success message
        document.addEventListener('DOMContentLoaded', function() {
            @if(session('success'))
                const modal = document.getElementById('successModal');
                modal.classList.remove('hidden');
                modal.classList.add('flex');

                // Auto close after 3 seconds
                setTimeout(() => {
                    closeSuccessModal();
                }, 3000);
            @endif

            // Close modal on escape key
            document.getElementById('updateModal').addEventListener('click', function(e) {
                if (e.target === this) closeUpdateModal();
            });

            document.getElementById('successModal').addEventListener('click', function(e) {
                if (e.target === this) closeSuccessModal();
            });
        });
    </script>
</x-app-layout>
