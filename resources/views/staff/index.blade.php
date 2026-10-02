<x-app-layout>
    <style>
        .manage-staff-theme {
            --staff-soft: #F6F4EE;
            --staff-border: #E7E9E5;
            --staff-text: #19355F;
            --staff-muted: #657083;
        }
        .manage-staff-theme .staff-header { background: #FCFBF8; border-color: var(--staff-border); }
        .manage-staff-theme .staff-card,
        .manage-staff-theme .staff-modal { border-color: var(--staff-border); }
        .manage-staff-theme .staff-card-header,
        .manage-staff-theme .staff-table-head { background: var(--staff-soft); border-color: var(--staff-border); }
        .manage-staff-theme .staff-row:hover,
        .manage-staff-theme .staff-cancel:hover { background: var(--staff-soft); }
        .manage-staff-theme .staff-name,
        .manage-staff-theme .staff-modal-title { color: var(--staff-text); }
        .manage-staff-theme .staff-muted { color: var(--staff-muted); }
        .staff-modal .staff-delete { background: #dc2626; }
        .staff-modal .staff-delete:hover { background: #b91c1c; }
    </style>

    <div class="manage-staff-theme min-h-screen bg-[#FCFBF8]">

        <div class="flex-1 flex flex-col">

            {{-- HEADER --}}
            <div class="staff-header border-b px-4 py-6 sm:px-6 lg:px-8">
                <x-app-header>
                    <x-slot name="title">Manage Staff</x-slot>
                    <x-slot name="subtitle">Create and manage clinic staff accounts</x-slot>
                    <x-slot name="actions">
                        <a href="{{ route('staff.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-[#55B85A] px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-[#4aa04c] focus:outline-none focus:ring-2 focus:ring-[#55B85A] focus:ring-offset-2">+ Add Staff</a>
                        <a href="{{ route('staff.archived') }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-[#55B85A] focus:ring-offset-2">Archived</a>
                    </x-slot>
                </x-app-header>
            </div>

            {{-- CONTENT --}}
            <div class="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">

                <x-flash type="success" :message="session('success')" class="mb-6" />
                <x-flash type="error" :message="session('error')" class="mb-6" />

                {{-- STAFF CARD --}}
                <div class="staff-card overflow-hidden rounded-2xl border bg-white shadow-sm">

                    <div class="staff-card-header border-b px-6 py-4">
                        <h2 class="text-lg font-semibold text-gray-800">Staff List</h2>
                    </div>

                    {{-- TABLE --}}
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm table-auto">
                            
                            <thead class="staff-table-head border-b">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase w-1/3">
                                        Name
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase w-1/3">
                                        Email
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase w-1/3">
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-100">
                                @forelse($staffs as $staff)
                                    <tr class="staff-row transition">

                                        {{-- NAME --}}
                                        <td class="staff-name px-6 py-4 font-medium align-middle">
                                            {{ $staff->name }}
                                        </td>

                                        {{-- EMAIL --}}
                                        <td class="staff-muted px-6 py-4 align-middle">
                                            {{ $staff->email }}
                                        </td>

                                        {{-- ACTIONS --}}
                                        <td class="px-6 py-4 align-middle">
                                            <div class="flex justify-end gap-1">
                                                <x-action-buttons 
                                                    :editRoute="route('staff.edit', $staff)" />
                                                <button type="button" onclick="confirmResetStaff(this)"
                                                    data-reset-url="{{ route('staff.reset-account', $staff) }}"
                                                    data-staff-name="{{ $staff->name }}"
                                                    data-staff-email="{{ $staff->email }}"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#6B4EFF] transition-all duration-150 hover:bg-purple-50 hover:text-[#5139D8]"
                                                    title="Reset Account" aria-label="Reset account for {{ $staff->name }}">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.75a3 3 0 0 0-4.16 4.33l-5.4 5.4a1.5 1.5 0 0 0 2.12 2.12l5.4-5.4a3 3 0 0 0 4.33-4.16l-2.12 2.12-2.12-2.12 1.95-2.29Z" />
                                                    </svg>
                                                </button>
                                                <button type="button" onclick="confirmArchiveStaff(this)"
                                                    data-archive-url="{{ route('staff.destroy', $staff) }}"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-700 transition-all duration-150 hover:bg-red-50 hover:text-red-900"
                                                    title="Archive" aria-label="Archive staff account">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>

                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-10 text-gray-500">
                                            No staff found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>

                        </table>
                    </div>

                </div>

            </div>
        </div>
    </div>

    <div id="archiveStaffModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4 py-6 backdrop-blur-sm">
        <div class="staff-modal mx-4 w-full max-w-md rounded-xl border bg-white p-6 shadow-xl">
            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" />
                    </svg>
                </div>
                <div>
                    <h2 class="staff-modal-title text-base font-semibold">Archive Staff?</h2>
                    <p class="staff-muted mt-1 text-sm leading-6">
                        Are you sure you want to archive this staff account? The staff member will no longer be able to log in and will be moved to Archived Staff. You can restore the account later.
                    </p>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeArchiveStaffModal()" class="staff-cancel rounded-lg border px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                    Cancel
                </button>
                <form id="archiveStaffForm" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" id="confirmArchiveStaffButton" disabled class="staff-delete inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-60">
                        Archive
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="resetStaffModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4 py-6 backdrop-blur-sm"
        role="dialog" aria-modal="true" aria-labelledby="resetStaffModalTitle" aria-describedby="resetStaffModalMessage">
        <div class="staff-modal mx-4 w-full max-w-md rounded-xl border bg-white p-6 shadow-xl">
            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-purple-50 text-[#6B4EFF]">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 6.75a3 3 0 0 0-4.16 4.33l-5.4 5.4a1.5 1.5 0 0 0 2.12 2.12l5.4-5.4a3 3 0 0 0 4.33-4.16l-2.12 2.12-2.12-2.12 1.95-2.29Z" />
                    </svg>
                </div>
                <div>
                    <h2 id="resetStaffModalTitle" class="staff-modal-title text-base font-semibold">Reset Staff Account?</h2>
                    <p id="resetStaffModalMessage" class="staff-muted mt-1 text-sm leading-6">
                        Send a password reset link to <span id="resetStaffName" class="font-semibold text-gray-800"></span>
                        at <span id="resetStaffEmail" class="font-semibold text-gray-800"></span>?
                        The staff member will create their own new password using the secure reset link. Their profile and records will not be changed.
                    </p>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeResetStaffModal()" class="staff-cancel rounded-lg border px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                    Cancel
                </button>
                <form id="resetStaffForm" method="POST" class="inline">
                    @csrf
                    <button type="submit" id="sendResetStaffButton" disabled class="inline-flex items-center justify-center rounded-lg bg-[#6B4EFF] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#5139D8] disabled:cursor-not-allowed disabled:opacity-60">
                        Send Reset Link
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const archiveStaffForm = document.getElementById('archiveStaffForm');
        const confirmArchiveStaffButton = document.getElementById('confirmArchiveStaffButton');
        const resetStaffForm = document.getElementById('resetStaffForm');
        const sendResetStaffButton = document.getElementById('sendResetStaffButton');

        function confirmArchiveStaff(button) {
            const archiveUrl = button.dataset.archiveUrl;
            if (!archiveUrl) {
                return;
            }

            archiveStaffForm.action = archiveUrl;
            confirmArchiveStaffButton.disabled = false;
            const modal = document.getElementById('archiveStaffModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closeArchiveStaffModal() {
            const modal = document.getElementById('archiveStaffModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            archiveStaffForm.removeAttribute('action');
            confirmArchiveStaffButton.disabled = true;
            document.body.style.overflow = '';
        }

        function confirmResetStaff(button) {
            const resetUrl = button.dataset.resetUrl;
            if (!resetUrl) {
                return;
            }

            resetStaffForm.action = resetUrl;
            sendResetStaffButton.disabled = false;
            document.getElementById('resetStaffName').textContent = button.dataset.staffName || '';
            document.getElementById('resetStaffEmail').textContent = button.dataset.staffEmail || '';

            const modal = document.getElementById('resetStaffModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closeResetStaffModal() {
            const modal = document.getElementById('resetStaffModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            resetStaffForm.removeAttribute('action');
            sendResetStaffButton.disabled = true;
            document.getElementById('resetStaffName').textContent = '';
            document.getElementById('resetStaffEmail').textContent = '';
            document.body.style.overflow = '';
        }

        document.getElementById('archiveStaffModal')?.addEventListener('click', function (event) {
            if (event.target === this) {
                closeArchiveStaffModal();
            }
        });

        document.getElementById('resetStaffModal')?.addEventListener('click', function (event) {
            if (event.target === this) {
                closeResetStaffModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !document.getElementById('resetStaffModal').classList.contains('hidden')) {
                closeResetStaffModal();
            } else if (event.key === 'Escape') {
                closeArchiveStaffModal();
            }
        });
    </script>

</x-app-layout>