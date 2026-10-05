<x-app-layout>
    <style>
        .deactivated-staff { container-type: inline-size; min-width: 0; overflow-wrap: anywhere; }
        .deactivated-staff table { table-layout: fixed; }
        .deactivated-staff td { overflow-wrap: anywhere; }
        .deactivated-staff .flex > * { min-width: 0; }
        .reactivate-button { min-height: 44px; white-space: normal; }
        #restoreStaffModal { overflow-y: auto; }
        #restoreStaffModal > div { margin-inline: 0; max-height: calc(100dvh - 3rem); overflow-y: auto; overflow-wrap: anywhere; }
        #restoreStaffModal button { min-height: 44px; }
        @container (max-width: 900px) {
        .deactivated-staff table, .deactivated-staff tbody, .deactivated-staff tr, .deactivated-staff td { display: block; width: 100%; }
        .deactivated-staff thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
        .deactivated-staff .staff-row { padding: 12px 16px; }
        .deactivated-staff .staff-row td { padding: 8px 0; text-align: left; }
        .deactivated-staff .staff-row td::before { content: attr(data-label); display: block; margin-bottom: 4px; font-size: 12px; font-weight: 600; color: #657083; }
        .deactivated-staff td .flex { justify-content: flex-start; }
        }
        @media (max-width: 639px) {
        #restoreStaffModal .mt-6 { flex-direction: column; }
        #restoreStaffModal button { width: 100%; }
        }
    </style>
    <div class="deactivated-staff min-h-screen bg-[#FCFBF8]">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <x-app-header class="mb-8">
                <x-slot name="title">Deactivated Staff</x-slot>
                <x-slot name="subtitle">Deactivated accounts retain their records but cannot sign in.</x-slot>
                <x-slot name="actions">
                    <a href="{{ route('staff.index') }}" class="btn btn-secondary">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to Manage Staff
                    </a>
                    <div class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm">
                        <span class="text-[#55B85A]">{{ $staffs->count() }}</span>
                        <span>deactivated staff</span>
                    </div>
                </x-slot>
            </x-app-header>

            <x-flash type="success" :message="session('success')" class="mb-6" />

            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-semibold text-gray-900">Deactivated Staff Accounts</h2>
                        <p class="mt-1 text-sm text-gray-500">Reactivate an account to allow sign-in again using its existing credentials.</p>
                    </div>
                    <div class="text-sm text-gray-500">
                        {{ $staffs->count() }} deactivated account{{ $staffs->count() === 1 ? '' : 's' }}
                    </div>
                </div>

                <div>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50">
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Deactivated Date</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse ($staffs as $staff)
                                <tr class="staff-row hover:bg-gray-50/40">
                                    <td data-label="Name" class="px-6 py-4 font-medium text-gray-900">{{ $staff->name }}</td>
                                    <td data-label="Email" class="px-6 py-4 text-gray-700">{{ $staff->email }}</td>
                                    <td data-label="Role" class="px-6 py-4 capitalize text-gray-700">{{ $staff->role }}</td>
                                    <td data-label="Deactivated Date" class="px-6 py-4 text-gray-700">{{ $staff->deleted_at?->format('M d, Y h:i A') ?? '—' }}</td>
                                    <td data-label="Action" class="px-6 py-4 text-right">
                                        <div class="flex justify-end">
                                            <form method="POST" action="{{ route('staff.restore', $staff->id) }}">
                                                @csrf
                                                <button type="button" onclick="confirmRestore(this)" class="reactivate-button rounded-lg border border-green-600 px-4 py-2 text-sm font-semibold text-green-700 hover:bg-green-50">Reactivate Account</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-16 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <svg class="h-12 w-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2m16 0v-2a4 4 0 00-3-3.87M14 3.13a4 4 0 010 7.75M14 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                            </svg>
                                            <p class="font-medium text-gray-500">No deactivated staff accounts found.</p>
                                            <a href="{{ route('staff.index') }}" class="text-sm text-[#55B85A] hover:underline">Back to Manage Staff</a>
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

    <div id="restoreStaffModal" role="dialog" aria-modal="true" aria-labelledby="reactivateStaffTitle" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4 py-6 backdrop-blur-sm">
        <div class="mx-4 w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
            <div class="mb-4 flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-100">
                    <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div>
                    <h3 id="reactivateStaffTitle" class="font-semibold text-gray-900">Reactivate Staff Account</h3>
                    <p class="text-sm text-gray-500">This account can sign in again. Existing profile information, records, and password will be preserved.</p>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeRestoreStaffModal()" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Cancel</button>
                <button type="button" id="confirmRestoreStaffButton" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-green-700">Reactivate Account</button>
            </div>
        </div>
    </div>

    <script>
        let pendingRestoreStaffForm = null;

        function confirmRestore(button) {
            pendingRestoreStaffForm = button.closest('form');
            const modal = document.getElementById('restoreStaffModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeRestoreStaffModal() {
            const modal = document.getElementById('restoreStaffModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            pendingRestoreStaffForm = null;
        }

        document.getElementById('confirmRestoreStaffButton')?.addEventListener('click', function () {
            if (pendingRestoreStaffForm) {
                pendingRestoreStaffForm.submit();
            }
        });

        document.getElementById('restoreStaffModal')?.addEventListener('click', function (event) {
            if (event.target === this) {
                closeRestoreStaffModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeRestoreStaffModal();
            }
        });
    </script>
</x-app-layout>
