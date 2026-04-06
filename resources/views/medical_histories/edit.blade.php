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
            // Get the form and submit it
            const form = document.querySelector('form');
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