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
        .manage-staff-theme .staff-delete { background: #dc2626; }
        .manage-staff-theme .staff-delete:hover { background: #b91c1c; }
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
                    </x-slot>
                </x-app-header>
            </div>

            {{-- CONTENT --}}
            <div class="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">

                <x-flash type="success" :message="session('success')" class="mb-6" />

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
                                            <div class="flex justify-end">
                                                <x-action-buttons 
                                                    :editRoute="route('staff.edit', $staff)"
                                                    :deleteRoute="route('staff.destroy', $staff)" />
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

    {{-- DELETE MODAL --}}
    <div id="deleteModal" class="fixed inset-0 backdrop-blur-md bg-black/20 hidden flex items-center justify-center z-50">
        <div class="staff-modal w-80 rounded-2xl border bg-white p-6 shadow-xl">

            <div class="flex items-center gap-3 mb-3">
                <div class="bg-red-100 text-red-600 p-2 rounded-full">❗</div>
                <h2 class="staff-modal-title text-lg font-semibold">Delete Staff</h2>
            </div>

            <p class="staff-muted mb-6 text-sm">
                This will permanently delete this staff.
            </p>

            <div class="flex justify-end gap-3">
                <button onclick="closeDeleteModal()"
                    class="staff-cancel rounded-lg border px-4 py-2 text-gray-700">
                    Cancel
                </button>

                <button id="confirmDeleteBtn"
                    class="staff-delete rounded-lg px-4 py-2 text-white">
                    Delete
                </button>
            </div>
        </div>
    </div>

    {{-- SCRIPT --}}
    <script>
        let selectedForm = null;

        document.querySelectorAll('.deleteForm').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                selectedForm = form;
                document.getElementById('deleteModal').classList.remove('hidden');
            });
        });

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (selectedForm) {
                selectedForm.submit();
            }
        });
    </script>

</x-app-layout>