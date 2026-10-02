<x-app-layout>
    <div class="min-h-screen bg-[#FCFBF8]">
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
                    <table class="w-full min-w-[1200px] text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Patient</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Visit Date</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">BP</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Weight</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">GA</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Assessment</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Next Visit</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Archived Date</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse ($visits as $visit)
                                <tr class="hover:bg-gray-50/40">
                                    <td class="px-4 py-4">
                                        <span class="font-medium text-gray-900">{{ $visit->patient->first_name }} {{ $visit->patient->last_name }}</span>
                                        <span class="mt-1 block text-xs text-gray-400">Patient #{{ $visit->patient_id }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-gray-700">
                                        {{ $visit->visit_date?->format('M d, Y') ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-gray-700">
                                        {{ $visit->bp_sys !== null && $visit->bp_dia !== null ? $visit->bp_sys . '/' . $visit->bp_dia . ' mmHg' : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-gray-700">
                                        {{ \App\Support\WeightFormatter::formatKg($visit->weight) !== null
                                            ? \App\Support\WeightFormatter::formatKg($visit->weight) . ' kg'
                                            : '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-gray-700">
                                        {{ $visit->gestational_age !== null ? number_format((float) $visit->gestational_age, 1, '.', '') . ' weeks' : '—' }}
                                    </td>
                                    <td class="max-w-xs px-4 py-4 text-gray-700">
                                        <span class="line-clamp-2">{{ $visit->assessment ?: '—' }}</span>
                                        @if ($visit->risk_level)
                                            <span class="mt-1 block text-xs text-gray-400">{{ $visit->risk_level }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-gray-700">
                                        {{ $visit->next_visit_date?->format('M d, Y') ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-gray-700">
                                        {{ $visit->deleted_at?->format('M d, Y h:i A') ?? '—' }}
                                    </td>
                                    <td class="px-4 py-4 text-right">
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

    <div id="restoreVisitModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4 py-6 backdrop-blur-sm">
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
