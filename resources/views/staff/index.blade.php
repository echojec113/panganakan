<x-app-layout>
    <div class="flex min-h-screen bg-gray-50">

        {{-- MAIN CONTENT --}}
        <div class="flex-1 flex flex-col">

            {{-- HEADER --}}
            <div class="bg-white border-b px-8 py-6 flex justify-between items-center shadow-sm">
                <div>
    <a href="{{ route('dashboard') }}" 
       class="flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 mb-1">
        <span>←</span>
        <span>Back to Dashboard</span>
    </a>

    <h1 class="text-2xl font-bold text-gray-900">Manage Staff</h1>
    <p class="text-sm text-gray-500">Create and manage clinic staff accounts</p>
</div>

                <a href="{{ route('staff.create') }}"
                   class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl shadow transition">
                    + Add Staff
                </a>
            </div>

            {{-- CONTENT --}}
            <div class="p-8">

                {{-- SUCCESS MESSAGE (KEEP SAME STYLE) --}}
                @if(session('success'))
                    <div class="mb-6 bg-green-100 text-green-700 px-4 py-3 rounded-lg">
                        {{ session('success') }}
                    </div>
                @endif

                {{-- STAFF CARD --}}
                <div class="bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden">

                    {{-- TABLE HEADER --}}
                    <div class="px-6 py-4 border-b bg-gray-50">
                        <h2 class="text-lg font-semibold text-gray-800">Staff List</h2>
                    </div>

                    {{-- TABLE --}}
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Actions</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y">
                                @forelse($staffs as $staff)
                                    <tr class="hover:bg-gray-50 transition">

                                        <td class="px-6 py-4 font-medium text-gray-900">
                                            {{ $staff->name }}
                                        </td>

                                        <td class="px-6 py-4 text-gray-600">
                                            {{ $staff->email }}
                                        </td>

                                        <td class="px-6 py-4 flex gap-2">

                                            {{-- EDIT --}}
                                            <a href="{{ route('staff.edit', $staff) }}"
                                               class="bg-yellow-400 hover:bg-yellow-500 text-white px-3 py-1 rounded-lg text-xs shadow">
                                                Edit
                                            </a>

                                            {{-- DELETE (NO MORE confirm()) --}}
                                            <form action="{{ route('staff.destroy', $staff) }}" method="POST" class="deleteForm">
                                                @csrf
                                                @method('DELETE')

                                                <button type="submit"
                                                    class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-lg text-xs shadow">
                                                    Delete
                                                </button>
                                            </form>

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

    {{-- 🔥 DELETE MODAL (ONLY ADDITION) --}}
    <div id="deleteModal" class="fixed inset-0 backdrop-blur-md bg-black/20 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-2xl shadow-xl p-6 w-80">

            <div class="flex items-center gap-3 mb-3">
                <div class="bg-red-100 text-red-600 p-2 rounded-full">
                    ❗
                </div>
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

    {{-- 🔥 SCRIPT (ONLY ADDITION) --}}
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