<x-app-layout>
    <div class="flex min-h-screen bg-gray-50">

        <div class="flex-1 flex flex-col">

            {{-- HEADER --}}
            <div class="bg-white border-b px-8 py-6 shadow-sm">
                <x-app-header>
                    <x-slot name="title">Manage Staff</x-slot>
                    <x-slot name="subtitle">Create and manage clinic staff accounts</x-slot>
                    <x-slot name="actions">
                        <a href="{{ route('staff.create') }}" class="btn btn-primary">+ Add Staff</a>
                    </x-slot>
                </x-app-header>
            </div>

            {{-- CONTENT --}}
            <div class="p-8">

                <x-flash type="success" :message="session('success')" class="mb-6" />

                {{-- STAFF CARD --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

                    <div class="px-6 py-4 border-b bg-gray-50">
                        <h2 class="text-lg font-semibold text-gray-800">Staff List</h2>
                    </div>

                    {{-- TABLE --}}
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm table-auto">
                            
                            <thead class="bg-gray-50 border-b">
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
                                    <tr class="hover:bg-gray-50 transition">

                                        {{-- NAME --}}
                                        <td class="px-6 py-4 font-medium text-gray-900 align-middle">
                                            {{ $staff->name }}
                                        </td>

                                        {{-- EMAIL --}}
                                        <td class="px-6 py-4 text-gray-600 align-middle">
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
        <div class="bg-white rounded-2xl shadow-xl p-6 w-80">

            <div class="flex items-center gap-3 mb-3">
                <div class="bg-red-100 text-red-600 p-2 rounded-full">❗</div>
                <h2 class="text-lg font-semibold">Delete Staff</h2>
            </div>

            <p class="text-sm text-gray-500 mb-6">
                This will permanently delete this staff.
            </p>

            <div class="flex justify-end gap-3">
                <button onclick="closeDeleteModal()"
                    class="px-4 py-2 rounded-lg border text-gray-700 hover:bg-gray-100">
                    Cancel
                </button>

                <button id="confirmDeleteBtn"
                    class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">
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