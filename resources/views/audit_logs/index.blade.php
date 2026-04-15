<x-app-layout>
    <div class="p-4 md:p-8 max-w-6xl mx-auto">

        

        <!-- HEADER -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                📜 Audit Logs
            </h1>
            <p class="text-sm text-gray-500">
                Track all system activities and user actions
            </p>
        </div>

        <!-- FILTERS (UNCHANGED DESIGN, RESPONSIVE LANG) -->
        <form method="GET" class="bg-white p-4 rounded-2xl shadow border flex flex-col md:flex-row md:items-center gap-3 mb-6">

            <input type="text" name="search" value="{{ request('search') }}"
                placeholder="Search description..."
                class="border rounded-lg px-4 py-2 text-sm w-full md:w-auto">

            <select name="action" class="border rounded-lg px-4 py-2 text-sm w-full md:w-auto">
                <option value="">All Actions</option>
                <option value="CREATE" {{ request('action') == 'CREATE' ? 'selected' : '' }}>Create</option>
                <option value="UPDATE" {{ request('action') == 'UPDATE' ? 'selected' : '' }}>Update</option>
                <option value="DELETE" {{ request('action') == 'DELETE' ? 'selected' : '' }}>Delete</option>
            </select>

            <select name="module" class="border rounded-lg px-4 py-2 text-sm w-full md:w-auto">
                <option value="">All Modules</option>
                <option value="STAFF" {{ request('module') == 'STAFF' ? 'selected' : '' }}>Staff</option>
                <option value="PATIENT" {{ request('module') == 'PATIENT' ? 'selected' : '' }}>Patient</option>
            </select>

            <button type="submit"
                class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-xl shadow w-full md:w-auto">
                Apply Filters
            </button>
        </form>

        <!-- TABLE CARD -->
        <div class="bg-white rounded-2xl shadow border overflow-hidden">

            <div class="px-4 md:px-6 py-4 border-b bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-800">Activity Records</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 md:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">User</th>
                            <th class="px-4 md:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Action</th>
                            <th class="px-4 md:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Module</th>
                            <th class="px-4 md:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Description</th>
                            <th class="px-4 md:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y">
                        @forelse($logs as $log)
                            <tr class="hover:bg-gray-50">

                                <td class="px-4 md:px-6 py-4 flex items-center gap-3">
                                    <div class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 text-blue-600 font-bold">
                                        {{ strtoupper(substr($log->user->name ?? 'A', 0, 1)) }}
                                    </div>
                                    {{ $log->user->name ?? 'Unknown' }}
                                </td>

                                <td class="px-4 md:px-6 py-4">
                                    <span class="px-3 py-1 text-xs rounded-full
                                        @if($log->action == 'CREATE') bg-green-100 text-green-700
                                        @elseif($log->action == 'UPDATE') bg-blue-100 text-blue-700
                                        @elseif($log->action == 'DELETE') bg-red-100 text-red-700
                                        @endif">
                                        {{ $log->action }}
                                    </span>
                                </td>

                                <td class="px-4 md:px-6 py-4">
                                    <span class="px-3 py-1 text-xs rounded-full bg-gray-100 text-gray-700">
                                        {{ $log->module }}
                                    </span>
                                </td>

                                <td class="px-4 md:px-6 py-4 text-gray-700">
                                    {{ $log->description }}
                                </td>

                                <td class="px-4 md:px-6 py-4 text-gray-500">
                                    {{ $log->created_at->format('M d, Y h:i A') }}
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-10 text-gray-500">
                                    No audit logs found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                </table>
            </div>

        </div>

        <!-- PAGINATION -->
        <div class="mt-6">
            {{ $logs->links() }}
        </div>

    </div>
</x-app-layout>