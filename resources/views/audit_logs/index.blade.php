<x-app-layout>
    <style>
        .audit-logs-theme {
            --audit-primary: #55B85A;
            --audit-primary-hover: #4aa04c;
            --audit-soft: #F6F4EE;
            --audit-border: #E7E9E5;
            --audit-text: #19355F;
            --audit-muted: #657083;
        }

        .audit-logs-theme .audit-filter,
        .audit-logs-theme .audit-card {
            border-color: var(--audit-border);
        }

        .audit-logs-theme .audit-filter:focus,
        .audit-logs-theme .audit-select:focus {
            border-color: var(--audit-primary);
            --tw-ring-color: var(--audit-primary);
        }

        .audit-logs-theme .audit-card-header,
        .audit-logs-theme .audit-table-head {
            background-color: var(--audit-soft);
            border-color: var(--audit-border);
        }

        .audit-logs-theme .audit-row {
            border-color: var(--audit-border);
        }

        .audit-logs-theme .audit-row:hover {
            background-color: var(--audit-soft);
        }

        .audit-logs-theme .audit-avatar {
            background-color: #EDF5EC;
            color: #367E4B;
        }

        .audit-logs-theme .audit-name,
        .audit-logs-theme .audit-description {
            color: var(--audit-text);
        }

        .audit-logs-theme .audit-muted {
            color: var(--audit-muted);
        }
        .audit-logs-theme { container-type: inline-size; min-width: 0; max-width: 100%; overflow-wrap: anywhere; }
        .audit-logs-theme * { box-sizing: border-box; }
        .audit-logs-theme .flex > * { min-width: 0; }
        .audit-logs-theme form.audit-filter { flex-wrap: wrap; }
        .audit-logs-theme form.audit-filter input { flex: 1 1 220px; min-width: 0; max-width: 100%; }
        .audit-logs-theme form.audit-filter select { min-width: 0; max-width: 100%; }
        .audit-logs-theme table { table-layout: fixed; }
        .audit-logs-theme th:nth-child(4) { width: 30%; }
        .audit-logs-theme td { overflow-wrap: anywhere; }
        .audit-logs-theme .status-badge { max-width: 100%; white-space: normal; overflow-wrap: anywhere; }
        .audit-logs-theme .audit-avatar { flex-shrink: 0; }
        .audit-logs-theme nav, .audit-logs-theme nav > .flex { min-width: 0; max-width: 100%; flex-wrap: wrap; gap: 12px; }
        @container (max-width: 850px) {
            .audit-logs-theme table, .audit-logs-theme tbody, .audit-logs-theme tr { display: block; width: 100%; }
            .audit-logs-theme thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
            .audit-logs-theme .audit-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 16px; padding: 16px; }
            .audit-logs-theme .audit-row td { display: block; min-width: 0; padding: 0; }
            .audit-logs-theme .audit-row td::before { content: attr(data-label); display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: var(--audit-muted); }
            .audit-logs-theme .audit-row td:first-child, .audit-logs-theme .audit-row td:nth-child(4), .audit-logs-theme .audit-row td:last-child { grid-column: 1 / -1; }
            .audit-logs-theme .audit-avatar { display: inline-flex; margin-right: 8px; vertical-align: middle; }
            .audit-logs-theme form.audit-filter { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .audit-logs-theme form.audit-filter input, .audit-logs-theme form.audit-filter button { grid-column: 1 / -1; }
            .audit-logs-theme form.audit-filter input, .audit-logs-theme form.audit-filter select { width: 100%; min-height: 44px; }
            .audit-logs-theme form.audit-filter button { min-height: 44px; }
        }
        @media (max-width: 639px) {
            .audit-logs-theme form.audit-filter { grid-template-columns: minmax(0, 1fr); }
        }
    </style>

    <div class="audit-logs-theme min-h-screen bg-[#FCFBF8] p-4 md:p-8">
        <div class="mx-auto max-w-6xl">

        

        <!-- HEADER -->
        <x-app-header
            title="Audit Logs"
            subtitle="Track all system activities and user actions"
            class="mb-6"
        />

        <!-- FILTERS (UNCHANGED DESIGN, RESPONSIVE LANG) -->
        <form method="GET" class="audit-filter flex flex-col gap-3 rounded-2xl border bg-white p-4 shadow-sm md:flex-row md:items-center mb-6">

            <input type="text" name="search" value="{{ request('search') }}"
                placeholder="Search description..."
                class="audit-filter w-full rounded-lg border px-4 py-2 text-sm md:w-auto focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A]">

            <select name="action" class="audit-select w-full rounded-lg border px-4 py-2 text-sm md:w-auto focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A]">
                <option value="">All Actions</option>
                <option value="CREATE" {{ request('action') == 'CREATE' ? 'selected' : '' }}>Create</option>
                <option value="UPDATE" {{ request('action') == 'UPDATE' ? 'selected' : '' }}>Update</option>
                <option value="DELETE" {{ request('action') == 'DELETE' ? 'selected' : '' }}>Delete</option>
            </select>

            <select name="module" class="audit-select w-full rounded-lg border px-4 py-2 text-sm md:w-auto focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A]">
                <option value="">All Modules</option>
                <option value="STAFF" {{ request('module') == 'STAFF' ? 'selected' : '' }}>Staff</option>
                <option value="PATIENT" {{ request('module') == 'PATIENT' ? 'selected' : '' }}>Patient</option>
            </select>

            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#55B85A] px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-[#4aa04c] focus:outline-none focus:ring-2 focus:ring-[#55B85A] focus:ring-offset-2">
                Apply Filters
            </button>
        </form>

        <!-- TABLE CARD -->
        <div class="audit-card overflow-hidden rounded-2xl border bg-white shadow-sm">

            <div class="audit-card-header border-b px-4 py-4 md:px-6">
                <h2 class="text-lg font-semibold text-gray-800">Activity Records</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="audit-table-head border-b">
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
                            <tr class="audit-row border-b transition">

                                <td data-label="User" class="px-4 md:px-6 py-4 flex items-center gap-3">
                                    <div class="audit-avatar flex h-8 w-8 items-center justify-center rounded-full font-bold">
                                        {{ strtoupper(substr($log->user->name ?? 'A', 0, 1)) }}
                                    </div>
                                    <span class="audit-name">{{ $log->user->name ?? 'Unknown' }}</span>
                                </td>

                                <td data-label="Action" class="px-4 md:px-6 py-4">
                                    <x-status-badge :variant="$log->action == 'CREATE' ? 'success' : ($log->action == 'UPDATE' ? 'info' : 'danger')">
                                        {{ $log->action }}
                                    </x-status-badge>
                                </td>

                                <td data-label="Module" class="px-4 md:px-6 py-4">
                                    <x-status-badge variant="neutral">
                                        {{ $log->module }}
                                    </x-status-badge>
                                </td>

                                <td data-label="Description" class="audit-description px-4 py-4 md:px-6">
                                    {{ $log->description }}
                                </td>

                                <td data-label="Date" class="audit-muted px-4 py-4 md:px-6">
                                    {{ $log->created_at->format('M d, Y h:i A') }}
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="audit-muted py-10 text-center">
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
    </div>
</x-app-layout>
