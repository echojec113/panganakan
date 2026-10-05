<x-app-layout>
    <style>
        .referral-archive-responsive { container-type: inline-size; min-width: 0; overflow-wrap: anywhere; }
        .referral-archive-responsive *, .referral-archive-responsive *::before, .referral-archive-responsive *::after { box-sizing: border-box; }
        .referral-archive-responsive .grid > *, .referral-archive-responsive .flex > * { min-width: 0; }
        .referral-archive-responsive .app-page-header-actions { flex-wrap: wrap; }
        .referral-archive-dialog > div { min-width: 0; max-height: calc(100dvh - 3rem); overflow-y: auto; margin-inline: 0; overflow-wrap: anywhere; }
        .referral-archive-dialog { padding-block: 1.5rem; }
        .referral-archive-dialog button { white-space: normal; }

        .referral-archive-table { table-layout: fixed; }
        .referral-archive-table th, .referral-archive-table td { white-space: normal; overflow-wrap: anywhere; padding-inline: .75rem; }
        .referral-archive-table td > .flex { flex-wrap: wrap; }
        @container (max-width: 1100px) {
            .referral-archive-table, .referral-archive-table tbody { display: block; width: 100%; }
            .referral-archive-table thead { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip-path: inset(50%); }
            .referral-archive-table tbody { padding: 1rem; }
            .referral-archive-table tbody tr { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); border: 1px solid #e5e7eb; border-radius: .75rem; margin-bottom: 1rem; }
            .referral-archive-table td { display: block; min-width: 0; max-width: none; text-align: left; padding: .75rem; border: 0; }
            .referral-archive-table td::before { content: attr(data-label); display: block; margin-bottom: .25rem; font-size: .75rem; font-weight: 600; color: #657083; }
            .referral-archive-table td:first-child, .referral-archive-table td:last-child { grid-column: 1 / -1; }
            .referral-archive-table td:last-child .flex { justify-content: flex-start; }
            .referral-archive-table td[colspan]::before { content: none; }
        }
        @container (max-width: 360px) {
            .referral-archive-table tbody tr { grid-template-columns: minmax(0, 1fr); }
        }
    </style>
    <div class="referral-archive-responsive min-h-screen bg-[#FCFBF8]">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <x-app-header class="mb-8">
                <x-slot name="title">Archived Referrals</x-slot>
                <x-slot name="subtitle">Review archived referral records and restore them to the active list.</x-slot>
                <x-slot name="actions">
                    <a href="{{ route('referrals.index') }}" class="btn btn-secondary">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to Referrals
                    </a>
                    <div class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm">
                        <span class="text-[#55B85A]">{{ $referrals->count() }}</span>
                        <span>archived referrals</span>
                    </div>
                </x-slot>
            </x-app-header>

            <x-flash type="success" :message="session('success')" class="mb-6" />

            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-semibold text-gray-900">Archived Referral Records</h2>
                        <p class="mt-1 text-sm text-gray-500">Archived referrals are hidden from the active Referrals list until restored.</p>
                    </div>
                    <div class="text-sm text-gray-500">
                        {{ $referrals->count() }} archived record{{ $referrals->count() === 1 ? '' : 's' }}
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="referral-archive-table w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Patient</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Destination</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Referral Date</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Reason</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Archived Date</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse ($referrals as $referral)
                                <tr class="hover:bg-gray-50/40">
                                    <td data-label="Patient" class="px-4 py-4">
                                        <span class="font-medium text-gray-900">{{ $referral->patient->first_name }} {{ $referral->patient->last_name }}</span>
                                        <span class="mt-1 block text-xs text-gray-400">Patient #{{ $referral->patient_id }}</span>
                                    </td>
                                    <td data-label="Destination" class="px-4 py-4 text-gray-700">
                                        {{ $referral->referred_to }}
                                    </td>
                                    <td data-label="Referral Date" class="whitespace-nowrap px-4 py-4 text-gray-700">
                                        {{ $referral->referral_date?->format('M d, Y') ?? '—' }}
                                    </td>
                                    <td data-label="Reason" class="px-4 py-4 text-gray-700">
                                        {{ \Str::limit($referral->reason, 80) }}
                                    </td>
                                    <td data-label="Status" class="whitespace-nowrap px-4 py-4 text-gray-700">
                                        {{ $referral->status }}
                                    </td>
                                    <td data-label="Archived Date" class="whitespace-nowrap px-4 py-4 text-gray-700">
                                        {{ $referral->deleted_at?->format('M d, Y h:i A') ?? '—' }}
                                    </td>
                                    <td data-label="Action" class="px-4 py-4 text-right">
                                        <div class="flex justify-end">
                                            <form action="{{ route('referrals.restore', $referral->id) }}" method="POST">
                                                @csrf
                                                <button type="button" onclick="confirmRestoreReferral(this)"
                                                    class="inline-flex items-center justify-center w-11 h-11 sm:w-8 sm:h-8 rounded-lg text-green-600 hover:bg-green-50 hover:text-green-800 transition-all duration-150"
                                                    title="Restore" aria-label="Restore referral">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-3-6.708M12 3v3H9" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-16 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <svg class="h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <p class="font-medium text-gray-500">No archived referrals found.</p>
                                            <a href="{{ route('referrals.index') }}" class="text-sm text-[#55B85A] hover:underline">Back to active referrals</a>
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

    <div id="restoreReferralModal" class="referral-archive-dialog fixed inset-0 z-50 hidden items-center justify-center bg-black/40 py-6 backdrop-blur-sm px-4">
        <div class="mx-4 w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
            <div class="mb-4 flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-100">
                    <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Restore Referral?</h3>
                    <p class="text-sm text-gray-500">This referral will return to the active Referrals list.</p>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeRestoreReferralModal()" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Cancel</button>
                <button type="button" id="confirmRestoreReferralButton" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-green-700">Restore Referral</button>
            </div>
        </div>
    </div>

    <script>
        let pendingRestoreReferralForm = null;

        function confirmRestoreReferral(button) {
            pendingRestoreReferralForm = button.closest('form');
            const modal = document.getElementById('restoreReferralModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeRestoreReferralModal() {
            const modal = document.getElementById('restoreReferralModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            pendingRestoreReferralForm = null;
        }

        document.getElementById('confirmRestoreReferralButton')?.addEventListener('click', function () {
            if (pendingRestoreReferralForm) {
                pendingRestoreReferralForm.submit();
            }
        });

        document.getElementById('restoreReferralModal')?.addEventListener('click', function (event) {
            if (event.target === this) {
                closeRestoreReferralModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeRestoreReferralModal();
            }
        });
    </script>
</x-app-layout>
