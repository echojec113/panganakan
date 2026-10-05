<x-app-layout>
    <div class="min-h-screen bg-[#FCFBF8]">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            {{-- Page Header --}}
            <x-app-header
                title="Create Referral"
                subtitle="Select a high-risk patient for referral."
                class="mb-8"
            >
                <x-slot name="actions">
                    <a href="{{ route('referrals.index') }}" class="btn btn-secondary">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to Referrals
                    </a>
                </x-slot>
            </x-app-header>

            <x-flash type="success" :message="session('success')" class="mb-6" />
            <x-flash type="error" :message="session('error')" class="mb-6" />

            {{-- Validation Errors --}}
            <x-error-summary :errors="$errors" class="mb-6" />


            {{-- Main Card --}}
            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">

                {{-- Search --}}
                <form
                    method="GET"
                    action="{{ route('referrals.select-patient') }}"
                    class="flex flex-col gap-3 border-b border-gray-100 px-4 py-4 sm:flex-row sm:items-center sm:px-6"
                >
                    <label for="referral-patient-search" class="sr-only">
                        Search patients by name
                    </label>

                    <div class="relative min-w-0 flex-1">
                        <svg
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>

                        <input
                            id="referral-patient-search"
                            type="search"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Search by patient name..."
                            class="w-full rounded-lg border border-gray-200 bg-white py-2.5 pl-9 pr-3 text-sm text-gray-700 transition placeholder:text-gray-400 focus:border-[#55B85A] focus:outline-none focus:ring-2 focus:ring-[#55B85A]"
                        >
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" class="btn bg-[#55B85A] text-white transition hover:bg-[#4aa04c] focus:outline-none focus:ring-2 focus:ring-[#55B85A]/30">
                            Search
                        </button>

                        @if($search !== '')
                            <a
                                href="{{ route('referrals.select-patient') }}"
                                class="whitespace-nowrap rounded-lg border border-red-200 px-3 py-2 text-sm text-red-600 transition hover:bg-red-50"
                            >
                                Clear
                            </a>
                        @endif
                    </div>
                </form>


                {{-- Patient List --}}
                <ul class="divide-y divide-gray-100">

                    @forelse($patients as $patient)

                        @php
                            $latestAssessment = $patient->latestPrenatalAssessment;

                            $canLinkAssessment = $latestAssessment
                                && $latestAssessment->risk_level === 'HIGH'
                                && is_array($latestAssessment->assessment_metadata)
                                && count($latestAssessment->assessment_metadata) > 0;

                            $referralParameters = [
                                'id' => $patient->id
                            ];

                            if ($canLinkAssessment) {
                                $referralParameters['prenatal_visit_id']
                                    = $latestAssessment->id;
                            }

                            $patientName = trim(
                                $patient->first_name . ' ' .
                                ($patient->middle_name ? $patient->middle_name . ' ' : '') .
                                $patient->last_name
                            );
                        @endphp


                        <li
                            class="flex flex-col gap-4 px-4 py-5
                                   sm:flex-row sm:items-center sm:justify-between sm:px-6"
                        >

                            {{-- Patient Information --}}
                            <div class="min-w-0">

                                <p class="text-base font-semibold text-gray-900">
                                    {{ $patientName }}
                                </p>

                                {{-- Basic Patient Details --}}
                                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-500">

                                    <span>
                                        Birthdate:
                                        {{ $patient->birthdate?->format('M d, Y') ?? 'Not recorded' }}
                                    </span>

                                    <span class="hidden text-gray-300 sm:inline">
                                        &middot;
                                    </span>

                                    <span>
                                        EDD:
                                        {{ $patient->edd?->format('M d, Y') ?? 'Not recorded' }}
                                    </span>

                                </div>

                                {{-- Latest Assessment --}}
                                @if($latestAssessment)
                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-gray-600">
                                        <span>
                                            Latest assessment:
                                            <span class="font-medium text-gray-700">
                                                {{ $latestAssessment->visit_date?->format('M d, Y') ?? 'Not recorded' }}
                                            </span>
                                        </span>

                                        @if($latestAssessment->risk_level === 'HIGH')
                                            <x-status-badge variant="danger">
                                                HIGH
                                            </x-status-badge>
                                        @endif
                                    </div>
                                @endif

                            </div>


                            {{-- Create Referral --}}
                            <button
                                type="button"
                                class="open-referral-modal btn shrink-0 bg-[#55B85A] text-white transition hover:bg-[#4aa04c] focus:outline-none focus:ring-2 focus:ring-[#55B85A]/30"
                                data-patient="{{ $patientName }}"
                                data-birthdate="{{ $patient->birthdate?->format('M d, Y') ?? 'Not recorded' }}"
                                data-edd="{{ $patient->edd?->format('M d, Y') ?? 'Not recorded' }}"
                                data-assessment="{{ $latestAssessment?->visit_date?->format('M d, Y') ?? 'Not recorded' }}"
                                data-url="{{ route('referrals.create', $referralParameters) }}"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Create Referral
                            </button>

                        </li>


                    @empty

                        {{-- Empty State --}}
                        <li class="px-4 py-14 text-center sm:px-6">

                            <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>

                            @if($search !== '')

                                <p class="mt-3 font-semibold text-gray-700">
                                    No patients found
                                </p>

                                <p class="mt-1 text-sm text-gray-400">
                                    No high-risk patients match your search.
                                </p>

                                <a
                                    href="{{ route('referrals.select-patient') }}"
                                    class="mt-3 inline-flex text-sm font-semibold text-[#19355F] hover:underline"
                                >
                                    Clear search
                                </a>

                            @else

                                <p class="mt-3 font-semibold text-gray-700">
                                    No patients available for referral
                                </p>

                                <p class="mt-1 text-sm text-gray-400">
                                    There are currently no high-risk patients available.
                                </p>

                            @endif

                        </li>

                    @endforelse

                </ul>


                {{-- Pagination --}}
                @if($patients->total() > 0)

                    <div class="border-t border-gray-100 bg-gray-50 px-4 py-4 sm:px-6">

                        <div
                            class="flex flex-col gap-4
                                   sm:flex-row sm:items-center sm:justify-between"
                        >

                            {{-- Result Count --}}
                            <p class="text-sm text-gray-500">
                                Showing

                                <span class="font-medium text-gray-700">
                                    {{ $patients->firstItem() }}
                                </span>

                                to

                                <span class="font-medium text-gray-700">
                                    {{ $patients->lastItem() }}
                                </span>

                                of

                                <span class="font-medium text-gray-700">
                                    {{ $patients->total() }}
                                </span>

                                patients
                            </p>


                            {{-- Laravel Pagination --}}
                            @if($patients->hasPages())
                                <div>
                                    {{ $patients->links() }}
                                </div>
                            @endif

                        </div>

                    </div>

                @endif

            </div>
        </div>


        {{-- =========================================================
             CONFIRM PATIENT MODAL
        ========================================================== --}}
        <div
            id="referral-confirmation-modal"
            class="fixed inset-0 z-50 hidden items-center justify-center px-4 py-6 backdrop-blur-sm"
            role="dialog"
            aria-modal="true"
            aria-labelledby="referral-modal-title"
        >

            {{-- Backdrop --}}
            <div
                id="referral-modal-backdrop"
                class="absolute inset-0 bg-black/40"
            ></div>


            {{-- Modal --}}
            <div
                class="relative w-full max-w-md overflow-hidden
                       rounded-xl bg-white p-6 shadow-xl"
            >

                {{-- Header --}}
                <div class="mb-4">
                    <h2
                        id="referral-modal-title"
                        class="text-lg font-semibold text-gray-900"
                    >
                        Confirm Patient
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        Review the patient before proceeding with the referral.
                    </p>
                </div>


                {{-- Patient Details --}}
                <dl class="space-y-4">

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            Patient
                        </dt>

                        <dd
                            id="modal-patient-name"
                            class="mt-1 text-sm font-semibold text-gray-900"
                        ></dd>
                    </div>


                    <div class="grid grid-cols-2 gap-4">

                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                                Birthdate
                            </dt>

                            <dd
                                id="modal-birthdate"
                                class="mt-1 text-sm text-gray-700"
                            ></dd>
                        </div>


                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                                EDD
                            </dt>

                            <dd
                                id="modal-edd"
                                class="mt-1 text-sm text-gray-700"
                            ></dd>
                        </div>

                    </div>


                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            Latest Assessment
                        </dt>

                        <dd class="mt-1 flex items-center gap-2">

                            <span
                                id="modal-assessment"
                                class="text-sm text-gray-700"
                            ></span>

                            <x-status-badge variant="danger">
                                HIGH
                            </x-status-badge>

                        </dd>
                    </div>

                </dl>


                {{-- Actions --}}
                <div
                    class="mt-6 flex flex-col-reverse gap-2
                           sm:flex-row sm:justify-end"
                >

                    <button
                        id="cancel-referral"
                        type="button"
                        class="rounded-lg border border-gray-200 bg-white
                               px-4 py-2 text-sm font-semibold text-gray-600
                               transition hover:bg-gray-50"
                    >
                        Cancel
                    </button>


                    <a
                        id="confirm-referral"
                        href="#"
                        class="rounded-lg bg-[#55B85A] px-4 py-2
                               text-sm font-semibold text-white
                               transition hover:bg-[#4aa04c]
                               focus:outline-none focus:ring-2 focus:ring-[#55B85A]/30"
                    >
                        Continue
                    </a>

                </div>

            </div>
        </div>


        {{-- =========================================================
             MODAL SCRIPT
        ========================================================== --}}
        <script>
            document.addEventListener('DOMContentLoaded', function () {

                const modal = document.getElementById('referral-confirmation-modal');
                const backdrop = document.getElementById('referral-modal-backdrop');

                const patientName = document.getElementById('modal-patient-name');
                const birthdate = document.getElementById('modal-birthdate');
                const edd = document.getElementById('modal-edd');
                const assessment = document.getElementById('modal-assessment');

                const confirmButton = document.getElementById('confirm-referral');
                const cancelButton = document.getElementById('cancel-referral');

                const referralButtons =
                    document.querySelectorAll('.open-referral-modal');


                function openModal(button) {

                    patientName.textContent =
                        button.dataset.patient;

                    birthdate.textContent =
                        button.dataset.birthdate;

                    edd.textContent =
                        button.dataset.edd;

                    assessment.textContent =
                        button.dataset.assessment;

                    confirmButton.href =
                        button.dataset.url;

                    modal.classList.remove('hidden');
                    modal.classList.add('flex');

                    document.body.classList.add('overflow-hidden');

                    cancelButton.focus();
                }


                function closeModal() {

                    modal.classList.add('hidden');
                    modal.classList.remove('flex');

                    document.body.classList.remove('overflow-hidden');

                    confirmButton.href = '#';
                }


                referralButtons.forEach(function (button) {

                    button.addEventListener('click', function () {
                        openModal(button);
                    });

                });


                cancelButton.addEventListener('click', closeModal);

                backdrop.addEventListener('click', closeModal);


                document.addEventListener('keydown', function (event) {

                    if (
                        event.key === 'Escape' &&
                        !modal.classList.contains('hidden')
                    ) {
                        closeModal();
                    }

                });

            });
        </script>

    </div>
</x-app-layout>
