<x-app-layout>
    <style>
        .prenatal-responsive { container-type: inline-size; min-width: 0; overflow-wrap: anywhere; }
        .prenatal-responsive *, .prenatal-responsive *::before, .prenatal-responsive *::after { box-sizing: border-box; }
        .prenatal-responsive .grid > *, .prenatal-responsive .flex > * { min-width: 0; }
        .prenatal-responsive .app-page-header-actions { flex-wrap: wrap; }
        .prenatal-dialog > div { min-width: 0; max-height: calc(100dvh - 3rem); overflow-y: auto; margin-inline: 0; overflow-wrap: anywhere; }
        .prenatal-dialog { padding-block: 1.5rem; }
        .prenatal-dialog button { white-space: normal; }

        .prenatal-responsive .prenatal-summary { grid-template-columns: repeat(auto-fit, minmax(min(100%, 210px), 1fr)); }
        .prenatal-table { table-layout: fixed; }
        .prenatal-table th, .prenatal-table td { white-space: normal; overflow-wrap: anywhere; padding-inline: .75rem; }
        .prenatal-table td > .flex { flex-wrap: wrap; }
        @container (max-width: 1100px) {
            .prenatal-table, .prenatal-table tbody { display: block; width: 100%; }
            .prenatal-table thead { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip-path: inset(50%); }
            .prenatal-table tbody { padding: 1rem; }
            .prenatal-table tbody tr { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); border: 1px solid #e5e7eb; border-radius: .75rem; margin-bottom: 1rem; }
            .prenatal-table td { display: block; min-width: 0; max-width: none; text-align: left; padding: .75rem; border: 0; }
            .prenatal-table td::before { content: attr(data-label); display: block; margin-bottom: .25rem; font-size: .75rem; font-weight: 600; color: #657083; }
            .prenatal-table td:first-child, .prenatal-table td:last-child { grid-column: 1 / -1; }
            .prenatal-table td:last-child .flex { justify-content: flex-start; }
            .prenatal-table td[colspan]::before { content: none; }
        }
        @container (max-width: 600px) {
            #prenatalFilterForm { flex-direction: column; align-items: stretch; }
            #prenatalFilterForm > div { width: 100%; }
            .prenatal-responsive nav { flex-wrap: wrap; }
            .prenatal-responsive nav, .prenatal-responsive nav + * { max-width: 100%; }
        }
        @container (max-width: 360px) {
            .prenatal-table tbody tr { grid-template-columns: minmax(0, 1fr); }
        }
    </style>
    <div class="prenatal-responsive min-h-screen bg-[#FCFBF8]">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <x-app-header class="mb-8">
                <x-slot name="title">Archived Prenatal Visits</x-slot>
                <x-slot name="subtitle">Review archived prenatal visits for active patient records and restore them to the active list.</x-slot>
                <x-slot name="actions">
                    <a href="{{ route('prenatal-visits.index') }}" class="btn btn-secondary">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to Prenatal Visits
                    </a>
                    <div class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm">
                        <span class="text-[#55B85A]">{{ $visits->count() }}</span>
                        <span>archived visits</span>
                    </div>
                </x-slot>
            </x-app-header>

            <x-flash type="success" :message="session('success')" class="mb-6" />

            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-semibold text-gray-900">Archived Visit Records</h2>
                        <p class="mt-1 text-sm text-gray-500">Only visits archived independently from an active patient are shown.</p>
                    </div>
                    <div class="text-sm text-gray-500">
                        {{ $visits->count() }} archived record{{ $visits->count() === 1 ? '' : 's' }}
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="prenatal-table w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Patient</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Visit Date</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Blood Pressure</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Weight</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Gestational Age</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Assessment</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Next Visit</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Archived Date</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse ($visits as $visit)
                                <tr class="hover:bg-gray-50/40">
                                    <td data-label="Patient" class="px-4 py-4">
                                        <span class="font-medium text-gray-900">{{ $visit->patient->first_name }} {{ $visit->patient->last_name }}</span>
                                        <span class="mt-1 block text-xs text-gray-400">Patient #{{ $visit->patient_id }}</span>
                                    </td>
                                    <td data-label="Visit Date" class="whitespace-nowrap px-4 py-4 text-gray-700">
                                        {{ $visit->visit_date?->format('M d, Y') ?? '—' }}
                                    </td>
                                    <td data-label="Blood Pressure" class="whitespace-nowrap px-4 py-4 text-gray-700">
                                        {{ $visit->bp_sys !== null && $visit->bp_dia !== null ? $visit->bp_sys . '/' . $visit->bp_dia . ' mmHg' : '—' }}
                                    </td>
                                    <td data-label="Weight" class="whitespace-nowrap px-4 py-4 text-gray-700">
                                        {{ \App\Support\WeightFormatter::formatKg($visit->weight) !== null
                                            ? \App\Support\WeightFormatter::formatKg($visit->weight) . ' kg'
                                            : '—' }}
                                    </td>
                                    <td data-label="Gestational Age" class="whitespace-nowrap px-4 py-4 text-gray-700">
                                        {{ $visit->gestational_age !== null ? number_format((float) $visit->gestational_age, 1, '.', '') . ' weeks' : '—' }}
                                    </td>
                                    <td data-label="Assessment" class="max-w-xs px-4 py-4 text-gray-700">
                                        <span class="break-words">{{ $visit->assessment ?: '—' }}</span>
                                        @if ($visit->risk_level)
                                            <span class="mt-1 block text-xs text-gray-400">{{ $visit->risk_level }}</span>
                                        @endif
                                    </td>
                                    <td data-label="Next Visit" class="whitespace-nowrap px-4 py-4 text-gray-700">
                                        {{ $visit->next_visit_date?->format('M d, Y') ?? '—' }}
                                    </td>
                                    <td data-label="Archived Date" class="whitespace-nowrap px-4 py-4 text-gray-700">
                                        {{ $visit->deleted_at?->format('M d, Y h:i A') ?? '—' }}
                                    </td>
                                    <td data-label="Action" class="px-4 py-4 text-right">
                                        <div class="flex justify-end">
                                            <x-action-buttons :restoreRoute="route('prenatal-visits.restore', $visit->id)" />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-16 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <svg class="h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <p class="font-medium text-gray-500">No archived prenatal visits found.</p>
                                            <a href="{{ route('prenatal-visits.index') }}" class="text-sm text-[#55B85A] hover:underline">Back to active prenatal visits</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="restoreVisitModal" class="prenatal-dialog fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4 py-6 backdrop-blur-sm">
        <div class="mx-4 w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
            <div class="mb-4 flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-100">
                    <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Restore Prenatal Visit?</h3>
                    <p class="text-sm text-gray-500">This visit will return to the active Prenatal Visits list.</p>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeRestoreVisitModal()" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Cancel</button>
                <button type="button" id="confirmRestoreVisitButton" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-green-700">Restore Visit</button>
            </div>
        </div>
    </div>

    <script>
        let pendingRestoreVisitForm = null;

        function confirmRestore(button) {
            pendingRestoreVisitForm = button.closest('form');
            const modal = document.getElementById('restoreVisitModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeRestoreVisitModal() {
            const modal = document.getElementById('restoreVisitModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            pendingRestoreVisitForm = null;
        }

        document.getElementById('confirmRestoreVisitButton')?.addEventListener('click', function () {
            if (pendingRestoreVisitForm) {
                pendingRestoreVisitForm.submit();
            }
        });

        document.getElementById('restoreVisitModal')?.addEventListener('click', function (event) {
            if (event.target === this) {
                closeRestoreVisitModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeRestoreVisitModal();
            }
        });
    </script>
</x-app-layout>
