<x-app-layout>
    <div class="min-h-screen bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            {{-- Header --}}
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Archived Patients</h1>
                    <p class="text-gray-500 mt-1 text-sm">Review deleted patient records and restore them back to the active list.</p>
                </div>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                    <a href="{{ route('patients.index') }}"
                        class="inline-flex items-center justify-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-100 transition font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back to Patients
                    </a>
                    <div class="inline-flex items-center justify-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 shadow-sm">
                        <span class="text-blue-600">{{ $patients->count() }}</span>
                        <span>archived patients</span>
                    </div>
                </div>
            </div>

            @if(session('success'))
                <div class="mb-6 flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm" role="alert">
                    <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex flex-col lg:flex-row lg:items-center gap-4">
                    <div class="relative flex-1">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input id="archiveSearch" type="text" placeholder="Search archived patients..."
                            class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition" />
                    </div>
                    <div class="text-sm text-gray-500">{{ $patients->count() }} archived record{{ $patients->count() === 1 ? '' : 's' }}</div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm" id="archiveTable">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100">
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Patient</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Deleted At</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Reason</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50" id="archiveTableBody">
                            @forelse($patients as $patient)
                                <tr class="hover:bg-blue-50/40 transition archive-row">
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col gap-1">
                                            <span class="font-medium text-gray-900">{{ $patient->first_name }} {{ $patient->last_name }}</span>
                                            <span class="text-xs text-gray-400">{{ $patient->civil_status ?? 'No status' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700">
                                        {{ $patient->deleted_at ? \Carbon\Carbon::parse($patient->deleted_at)->format('M d, Y h:i A') : '—' }}
                                    </td>
                                    <td class="px-6 py-4 text-gray-700">
                                        <span class="inline-flex items-center gap-2 px-2 py-1 bg-gray-100 text-gray-600 rounded-full text-xs font-medium">
                                            {{ $patient->archived_reason ?? 'Removed from active records' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
    <div class="flex justify-end">
        <x-action-buttons 
            :restoreRoute="route('patients.restore', $patient->id)" />
    </div>
</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-16 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            <p class="text-gray-500 font-medium">No archived patients found.</p>
                                            <a href="{{ route('patients.index') }}" class="text-blue-600 text-sm hover:underline">Back to active patients</a>
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

    <div id="restoreModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full mx-4 p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Restore Patient</h3>
                    <p class="text-sm text-gray-500">This will restore the patient from the archive.</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button onclick="closeRestoreModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">Cancel</button>
                <button id="confirmRestoreBtn" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 transition font-medium">Restore</button>
            </div>
        </div>
    </div>

    <script>
        const archiveSearch = document.getElementById('archiveSearch');
        const archiveRows = document.querySelectorAll('.archive-row');

        archiveSearch?.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            let visible = 0;

            archiveRows.forEach(row => {
                const name = row.querySelector('td:nth-child(1)')?.textContent.toLowerCase() || '';
                const reason = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '';
                const match = name.includes(query) || reason.includes(query);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
        });

        let pendingRestoreForm = null;
        function confirmRestore(btn) {
            pendingRestoreForm = btn.closest('form');
            const modal = document.getElementById('restoreModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
        function closeRestoreModal() {
            const modal = document.getElementById('restoreModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            pendingRestoreForm = null;
        }
        document.getElementById('confirmRestoreBtn')?.addEventListener('click', () => {
            if (pendingRestoreForm) pendingRestoreForm.submit();
        });
        document.getElementById('restoreModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeRestoreModal();
        });
    </script>
</x-app-layout>