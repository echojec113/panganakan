<x-app-layout>
    <div class="p-4 md:p-8 max-w-6xl mx-auto">

        

        <!-- HEADER -->
        <x-app-header
            title="Audit Logs"
            subtitle="Track all system activities and user actions"
            class="mb-6"
        />

        <!-- FILTERS (UNCHANGED DESIGN, RESPONSIVE LANG) -->
        <form method="GET" class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex flex-col md:flex-row md:items-center gap-3 mb-6">

            <input type="text" name="search" value="{{ request('search') }}"
                placeholder="Search description..."
                class="border rounded-lg px-4 py-2 text-sm w-full md:w-auto focus:ring-2 focus:ring-blue-500 focus:border-blue-500">

            <select name="action" class="border rounded-lg px-4 py-2 text-sm w-full md:w-auto focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">All Actions</option>
                <option value="CREATE" {{ request('action') == 'CREATE' ? 'selected' : '' }}>Create</option>
                <option value="UPDATE" {{ request('action') == 'UPDATE' ? 'selected' : '' }}>Update</option>
                <option value="DELETE" {{ request('action') == 'DELETE' ? 'selected' : '' }}>Delete</option>
            </select>

            <select name="module" class="border rounded-lg px-4 py-2 text-sm w-full md:w-auto focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">All Modules</option>
                <option value="STAFF" {{ request('module') == 'STAFF' ? 'selected' : '' }}>Staff</option>
                <option value="PATIENT" {{ request('module') == 'PATIENT' ? 'selected' : '' }}>Patient</option>
            </select>

            <button type="submit" class="btn btn-primary">
                Apply Filters
            </button>
        </form>

        <!-- TABLE CARD -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

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
                                    <x-status-badge :variant="$log->action == 'CREATE' ? 'success' : ($log->action == 'UPDATE' ? 'info' : 'danger')">
                                        {{ $log->action }}
                                    </x-status-badge>
                                </td>

                                <td class="px-4 md:px-6 py-4">
                                    <x-status-badge variant="neutral">
                                        {{ $log->module }}
                                    </x-status-badge>
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