<x-app-layout>
    <div class="min-h-screen bg-[#FCFBF8]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            {{-- Header --}}
            <x-app-header class="mb-8">
                <x-slot name="title">Prenatal Visits</x-slot>
                <x-slot name="subtitle">Record of all prenatal check-ups and risk assessments</x-slot>
                <x-slot name="actions">
                    <a href="{{ route('prenatal-visits.create') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#55B85A] text-white text-sm font-medium hover:bg-[#4aa04c] transition shadow-sm">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Prenatal Visit
                    </a>
                </x-slot>
            </x-app-header>

            <x-flash type="success" :message="session('success')" class="mb-6" />

            {{-- Stats Cards --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Total Visits</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $visits->count() }}</p>
                </div>

                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">High Risk</p>
                    <p class="text-2xl font-bold text-red-600">{{ $visits->where('risk_level', 'HIGH')->count() }}</p>
                </div>

                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">Low Risk</p>
                    <p class="text-2xl font-bold text-green-600">{{ $visits->where('risk_level', 'LOW')->count() }}</p>
                </div>

                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                    <p class="text-xs text-gray-500 font-medium uppercase tracking-wide mb-1">This Month</p>
                    <p class="text-2xl font-bold text-gray-900">{{ $visits->where('visit_date', '>=', now()->startOfMonth())->count() }}</p>
                </div>
            </div>

            {{-- Search & Filter Bar --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" id="searchInput" placeholder="Search by patient name..."
                            class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition" />
                    </div>
                    <div class="sm:w-48">
                        <select id="riskFilter" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#55B85A] focus:border-[#55B85A] transition">
                            <option value="">All Risks</option>
                            <option value="HIGH">High Risk</option>
                            <option value="LOW">Low Risk</option>
                            <option value="ASSESSMENT INCOMPLETE">Assessment Incomplete</option>
                            <option value="PENDING">Pending</option>
                        </select>
                    </div>
                    <span class="text-sm text-gray-400 self-center" id="resultCount">{{ $visits->count() }} records</span>
                </div>

                {{-- Visits Table --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" id="visitsTable">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100">
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Patient</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Visit Date</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">BP</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Weight</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">GA</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Risk</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap">Next Visit</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50" id="visitsTableBody">
                            @forelse($visits as $visit)
                                <tr class="hover:bg-gray-50/40 transition visit-row" data-name="{{ strtolower($visit->patient->first_name . ' ' . $visit->patient->last_name) }}" data-risk="{{ $visit->risk_level }}">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-[#FCFBF8] border border-gray-200 flex items-center justify-center text-xs font-bold flex-shrink-0 text-gray-600">
                                                {{ strtoupper(substr($visit->patient->first_name, 0, 1)) }}{{ strtoupper(substr($visit->patient->last_name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-900">{{ $visit->patient->first_name }} {{ $visit->patient->last_name }}</p>
                                                <p class="text-xs text-gray-400">Age: {{ $visit->patient->age }} | G{{ $visit->patient->gravida }} P{{ $visit->patient->para }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700 whitespace-nowrap">{{ \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') }}</td>
                                    <td class="px-6 py-4 text-gray-700 font-mono whitespace-nowrap">{{ $visit->bp_sys }}/{{ $visit->bp_dia }}</td>
                                    <td class="px-6 py-4 text-gray-700 whitespace-nowrap">{{ $visit->weight }} kg</td>
                                    <td class="px-6 py-4 text-gray-700 whitespace-nowrap">{{ $visit->gestational_age !== null ? $visit->gestational_age . ' weeks' : '—' }}</td>
                                    <td class="px-6 py-4">
                                        <x-status-badge :variant="$visit->risk_level == 'HIGH' ? 'danger' : ($visit->risk_level == 'LOW' ? 'success' : ($visit->risk_level == 'ASSESSMENT INCOMPLETE' ? 'warning' : 'info'))" class="status-badge-wrap">
                                            {{ $visit->risk_level ?? 'LOW' }}
                                        </x-status-badge>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700 whitespace-nowrap">{{ $visit->next_visit_date ? \Carbon\Carbon::parse($visit->next_visit_date)->format('M d, Y') : '—' }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <x-action-buttons
                                            :viewRoute="route('patients.show', ['patient' => $visit->patient_id, 'from' => 'prenatal-visits'])"
                                            :editRoute="route('prenatal-visits.edit', $visit->id)"
                                            :deleteRoute="route('prenatal-visits.destroy', $visit->id)" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-16 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            <p class="text-gray-500 font-medium">No prenatal visits recorded</p>
                                            <a href="{{ route('prenatal-visits.create') }}" class="text-[#55B85A] text-sm hover:underline">Add your first visit</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Empty search state --}}
                <div id="noResults" class="hidden px-6 py-12 text-center">
                    <p class="text-gray-500">No visits match your search.</p>
                </div>
            </div>

        </div>
    </div>

    {{-- Delete Confirmation Modal --}}
    <div id="deleteConfirmationModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4 py-6 sm:px-6">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full mx-4 p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Delete Visit</h3>
                    <p class="text-sm text-gray-500">Are you sure you want to delete this prenatal visit? This action cannot be undone.</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" id="cancelDeleteVisitBtn" class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">Cancel</button>
                <button type="button" id="confirmDeleteVisitBtn" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 transition font-medium">Delete</button>
            </div>
        </div>
    </div>

    {{-- Success/Flash Modals --}}
    <div id="deleteSuccessModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4 py-6 sm:px-6">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full mx-4 p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Deleted</h3>
                    <p class="text-sm text-gray-500">Prenatal visit has been deleted.</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" id="closeDeleteSuccessBtn" class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">OK</button>
            </div>
        </div>
    </div>

    <div id="flashSuccessModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4 py-6 sm:px-6">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full mx-4 p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div>
                    <h3 id="flashSuccessTitle" class="font-semibold text-gray-900">Updated</h3>
                    <p id="flashSuccessMessage" class="text-sm text-gray-500">Prenatal visit updated successfully.</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" id="closeFlashSuccessBtn" class="px-4 py-2 border border-gray-200 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">OK</button>
            </div>
        </div>
    </div>

    <div id="deleteSuccessFlag" data-success="{{ session('delete_success') ? 'true' : 'false' }}" class="hidden"></div>
    <div id="flashSuccessFlag" data-title="{{ session('success') ? (\Illuminate\Support\Str::contains(strtolower(session('success')), 'updated') ? 'Updated' : 'Success') : '' }}" data-message="{{ session('success') ? e(session('success')) : '' }}" class="hidden"></div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const riskFilter = document.getElementById('riskFilter');
            const resultCount = document.getElementById('resultCount');
            const noResults = document.getElementById('noResults');
            const tableBody = document.getElementById('visitsTableBody');

            function filterTable() {
                const searchTerm = searchInput?.value.toLowerCase() || '';
                const riskValue = riskFilter?.value || '';

                const rows = document.querySelectorAll('.visit-row');
                let visible = 0;

                rows.forEach(row => {
                    const name = row.dataset.name || '';
                    const risk = row.dataset.risk || '';

                    const matchesSearch = name.includes(searchTerm);
                    const matchesRisk = !riskValue || risk === riskValue;

                    if (matchesSearch && matchesRisk) {
                        row.style.display = '';
                        visible++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                resultCount.textContent = visible + ' record' + (visible !== 1 ? 's' : '');
                noResults.classList.toggle('hidden', visible > 0 || rows.length === 0);
            }

            searchInput?.addEventListener('input', filterTable);
            riskFilter?.addEventListener('change', filterTable);

            // Delete modal handling
            const deleteButtons = document.querySelectorAll('.delete-visit-btn');
            let pendingDeleteForm = null;

            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    pendingDeleteForm = this.closest('.delete-form');
                    openDeleteConfirmationModal();
                });
            });

            document.getElementById('confirmDeleteVisitBtn')?.addEventListener('click', function() {
                if (pendingDeleteForm) {
                    closeDeleteConfirmationModal();
                    pendingDeleteForm.submit();
                }
            });

            document.getElementById('cancelDeleteVisitBtn')?.addEventListener('click', closeDeleteConfirmationModal);

            document.getElementById('closeDeleteSuccessBtn')?.addEventListener('click', closeDeleteSuccessModal);
            document.getElementById('closeFlashSuccessBtn')?.addEventListener('click', closeFlashSuccessModal);

            document.getElementById('deleteConfirmationModal')?.addEventListener('click', function(e) {
                if (e.target === this) closeDeleteConfirmationModal();
            });

            document.getElementById('flashSuccessModal')?.addEventListener('click', function(e) {
                if (e.target === this) closeFlashSuccessModal();
            });

            function openDeleteConfirmationModal() {
                const modal = document.getElementById('deleteConfirmationModal');
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }
            }

            function closeDeleteConfirmationModal() {
                const modal = document.getElementById('deleteConfirmationModal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
                pendingDeleteForm = null;
            }

            function closeDeleteSuccessModal() {
                const modal = document.getElementById('deleteSuccessModal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            }

            function closeFlashSuccessModal() {
                const modal = document.getElementById('flashSuccessModal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            }

            // Show success modals on page load
            const deleteSuccessFlag = document.getElementById('deleteSuccessFlag');
            const flashSuccessFlag = document.getElementById('flashSuccessFlag');

            if (deleteSuccessFlag?.dataset.success === 'true') {
                openDeleteSuccessModal();
            } else if (flashSuccessFlag?.dataset.message) {
                const titleEl = document.getElementById('flashSuccessTitle');
                const messageEl = document.getElementById('flashSuccessMessage');
                if (titleEl) titleEl.textContent = flashSuccessFlag.dataset.title;
                if (messageEl) messageEl.textContent = flashSuccessFlag.dataset.message;
                openFlashSuccessModal();
            }

            function openDeleteSuccessModal() {
                const modal = document.getElementById('deleteSuccessModal');
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }
            }

            function openFlashSuccessModal() {
                const modal = document.getElementById('flashSuccessModal');
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }
            }
        });
    </script>
</x-app-layout>