<x-app-layout>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">View All Records</h1>
            <p class="mt-1 text-sm text-gray-500">View a patient's complete pregnancy history.</p>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <form method="GET" action="{{ route('view-all-records.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="w-full sm:w-64">
                    <label for="status" class="mb-1 block text-sm font-medium text-gray-700">Status</label>
                    <select id="status" name="status" onchange="this.form.submit()" class="w-full rounded-lg border-gray-300 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500">
                        <option value="ONGOING" @selected($status === 'ONGOING')>Ongoing</option>
                        <option value="DELIVERED" @selected($status === 'DELIVERED')>Delivered</option>
                        <option value="REFERRED" @selected($status === 'REFERRED')>Referred</option>
                    </select>
                </div>
                <noscript>
                    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Apply</button>
                </noscript>
            </form>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Patient Name</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($patients as $patient)
                            <tr>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                                    {{ $patient->first_name }} {{ $patient->middle_name ? $patient->middle_name . ' ' : '' }}{{ $patient->last_name }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('view-all-records.history', $patient) }}" class="inline-flex rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                        View History
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-6 py-10 text-center text-sm text-gray-500">No patient records match this status.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
