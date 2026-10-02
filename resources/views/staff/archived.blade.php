<x-app-layout>
    <div class="min-h-screen bg-[#FCFBF8]">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <x-app-header class="mb-8">
                <x-slot name="title">Archived Staff</x-slot>
                <x-slot name="subtitle">Review archived staff accounts and restore them to Manage Staff.</x-slot>
                <x-slot name="actions">
                    <a href="{{ route('staff.index') }}" class="btn btn-secondary">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to Manage Staff
                    </a>
                    <div class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm">
                        <span class="text-[#55B85A]">{{ $staffs->count() }}</span>
                        <span>archived staff</span>
                    </div>
                </x-slot>
            </x-app-header>

            <x-flash type="success" :message="session('success')" class="mb-6" />

            <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm">
                <div class="flex flex-col gap-4 border-b border-gray-100 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="font-semibold text-gray-900">Archived Staff Accounts</h2>
                        <p class="mt-1 text-sm text-gray-500">Restore an account to return it to Manage Staff and allow it to sign in again.</p>
                    </div>
                    <div class="text-sm text-gray-500">
                        {{ $staffs->count() }} archived account{{ $staffs->count() === 1 ? '' : 's' }}
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50">
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Archived Date</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @forelse ($staffs as $staff)
                                <tr class="hover:bg-gray-50/40">
                                    <td class="px-6 py-4 font-medium text-gray-900">{{ $staff->name }}</td>
                                    <td class="px-6 py-4 text-gray-700">{{ $staff->email }}</td>
                                    <td class="px-6 py-4 capitalize text-gray-700">{{ $staff->role }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-gray-700">{{ $staff->deleted_at?->format('M d, Y h:i A') ?? '—' }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end">
                                            <x-action-buttons :restoreRoute="route('staff.restore', $staff->id)" />
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
                                            <p class="font-medium text-gray-500">No archived staff accounts found.</p>
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

    <div id="restoreStaffModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4 py-6 backdrop-blur-sm">
        <div class="mx-4 w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
            <div class="mb-4 flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-100">
                    <svg class="h-5 w-5 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Restore Staff?</h3>
                    <p class="text-sm text-gray-500">This account will return to Manage Staff and be eligible to sign in again.</p>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeRestoreStaffModal()" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50">Cancel</button>
                <button type="button" id="confirmRestoreStaffButton" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-green-700">Restore</button>
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
