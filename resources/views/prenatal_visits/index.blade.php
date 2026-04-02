<x-app-layout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8">
        <div class="mb-4">
    <a href="{{ route('dashboard') }}" class="inline-flex items-center text-gray-600 hover:text-blue-600 transition group">
        <svg class="w-4 h-4 mr-1 group-hover:-translate-x-1 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
        </svg>
        Back to Dashboard
    </a>
</div>

        <!-- Header with Actions -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h2 class="text-2xl sm:text-3xl font-bold text-gray-800">Prenatal Visits</h2>
                <p class="text-sm text-gray-600 mt-1">Record of all prenatal check-ups and risk assessments</p>
            </div>
            <a href="{{ route('prenatal-visits.create') }}" class="inline-flex items-center justify-center px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition shadow-sm font-medium text-sm sm:text-base">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Add Prenatal Visit
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Visits</p>
                        <p class="text-2xl font-bold text-gray-800">{{ $visits->count() }}</p>
                    </div>
                    <div class="bg-blue-100 rounded-lg p-2">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">High Risk</p>
                        <p class="text-2xl font-bold text-red-600">{{ $visits->where('risk_level', 'HIGH')->count() }}</p>
                    </div>
                    <div class="bg-red-100 rounded-lg p-2">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Low Risk</p>
                        <p class="text-2xl font-bold text-green-600">{{ $visits->where('risk_level', 'LOW')->count() }}</p>
                    </div>
                    <div class="bg-green-100 rounded-lg p-2">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm p-4 border border-gray-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">This Month</p>
                        <p class="text-2xl font-bold text-gray-800">{{ $visits->where('visit_date', '>=', now()->startOfMonth())->count() }}</p>
                    </div>
                    <div class="bg-purple-100 rounded-lg p-2">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <input type="text" id="searchInput" placeholder="Search by patient name..." 
                            class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div class="sm:w-48">
                    <select id="riskFilter" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">All Risks</option>
                        <option value="HIGH">High Risk</option>
                        <option value="LOW">Low Risk</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Visits Table - Mobile Card View & Desktop Table View -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <!-- Mobile Card View -->
            <div class="block lg:hidden divide-y divide-gray-100">
                @forelse($visits as $visit)
                <div class="p-4 hover:bg-gray-50 transition visit-item" data-name="{{ strtolower($visit->patient->first_name . ' ' . $visit->patient->last_name) }}" data-risk="{{ $visit->risk_level }}">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <p class="font-semibold text-gray-900">
                                {{ $visit->patient->first_name }} {{ $visit->patient->last_name }}
                            </p>
                            <p class="text-xs text-gray-500 mt-0.5">ID: #{{ $visit->id }}</p>
                        </div>
                        <span class="px-2 py-1 rounded-full text-xs font-semibold
                            {{ $visit->risk_level == 'HIGH' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                            {{ $visit->risk_level ?? 'LOW' }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs text-gray-600 mb-3">
                        <div><span class="font-medium">Visit Date:</span> {{ \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') }}</div>
                        <div><span class="font-medium">BP:</span> {{ $visit->bp_sys }}/{{ $visit->bp_dia }}</div>
                        <div><span class="font-medium">Weight:</span> {{ $visit->weight }} kg</div>
                        <div><span class="font-medium">GA:</span> {{ $visit->gestational_age }} wks</div>
                    </div>
                    <div class="flex gap-3 pt-2 border-t border-gray-100">
                        <a href="{{ route('prenatal-visits.edit', $visit->id) }}" class="text-blue-600 text-sm hover:text-blue-800">Edit</a>
                        <form action="{{ route('prenatal-visits.destroy', $visit->id) }}" method="POST" class="inline delete-form">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="delete-visit-btn text-red-600 text-sm hover:text-red-800">Delete</button>
                        </form>
                        <a href="{{ route('patients.show', ['patient' => $visit->patient_id, 'from' => 'prenatal-visits']) }}" class="text-green-600 text-sm hover:text-green-800">View Patient</a>
                    </div>
                </div>
                @empty
                <div class="p-8 text-center text-gray-500">
                    <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p class="text-sm">No prenatal visits recorded</p>
                    <a href="{{ route('prenatal-visits.create') }}" class="mt-3 inline-block text-blue-600 text-sm hover:text-blue-800">Add your first visit →</a>
                </div>
                @endforelse
            </div>

            <!-- Desktop Table View -->
            <div class="hidden lg:block overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Visit Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">BP</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weight</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GA</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Risk</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Next Visit</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="visitsTableBody">
                        @foreach($visits as $visit)
                        <tr class="hover:bg-gray-50 transition visit-row" data-name="{{ strtolower($visit->patient->first_name . ' ' . $visit->patient->last_name) }}" data-risk="{{ $visit->risk_level }}">
                            <td class="px-6 py-4 text-sm text-gray-900">{{ $visit->id }}</td>
                            <td class="px-6 py-4">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $visit->patient->first_name }} {{ $visit->patient->last_name }}</p>
                                    <p class="text-xs text-gray-500">Age: {{ $visit->patient->age }} | G{{ $visit->patient->gravida }} P{{ $visit->patient->para }}</p>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') }}</td>
                            <td class="px-6 py-4 text-sm font-mono">{{ $visit->bp_sys }}/{{ $visit->bp_dia }}</td>
                            <td class="px-6 py-4 text-sm">{{ $visit->weight }} kg</td>
                            <td class="px-6 py-4 text-sm">{{ $visit->gestational_age }} wks</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold
                                    {{ $visit->risk_level == 'HIGH' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                    {{ $visit->risk_level ?? 'LOW' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm">{{ $visit->next_visit_date ? \Carbon\Carbon::parse($visit->next_visit_date)->format('M d, Y') : '-' }}</td>
                            <td class="px-6 py-4 text-sm">
                                <div class="flex space-x-3">
                                    <a href="{{ route('prenatal-visits.edit', $visit->id) }}" class="text-blue-600 hover:text-blue-800">Edit</a>
                                    <form action="{{ route('prenatal-visits.destroy', $visit->id) }}" method="POST" class="inline delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" class="delete-visit-btn text-red-600 hover:text-red-800">Delete</button>
                                    </form>
                                    <a href="{{ route('patients.show', ['patient' => $visit->patient_id, 'from' => 'prenatal-visits']) }}" class="text-green-600 hover:text-green-800">View</a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($visits->isEmpty())
            <div class="hidden lg:block text-center py-12 text-gray-500">
                <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p class="text-sm">No prenatal visits recorded</p>
                <a href="{{ route('prenatal-visits.create') }}" class="mt-3 inline-block text-blue-600 text-sm hover:text-blue-800">Add your first visit →</a>
            </div>
            @endif
        </div>

        <!-- Pagination (if using pagination) -->
        @if(method_exists($visits, 'links'))
        <div class="mt-6">
            {{ $visits->links() }}
        </div>
        @endif
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
                    <h3 class="font-semibold text-gray-900">Delete Confirmation</h3>
                    <p class="text-sm text-gray-500">Are you sure you want to delete this patient record?</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" id="cancelDeleteVisitBtn" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">Cancel</button>
                <button type="button" id="confirmDeleteVisitBtn" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 transition font-medium">Delete</button>
            </div>
        </div>
    </div>

    {{-- Success Modal --}}
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
                    <p class="text-sm text-gray-500">Patient record has been deleted.</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" id="closeDeleteSuccessBtn" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">OK</button>
            </div>
        </div>
    </div>
    <div id="deleteSuccessFlag" data-success="{{ session('delete_success') ? 'true' : 'false' }}" class="hidden"></div>
    <div id="flashSuccessFlag" data-title="{{ session('success') ? (\Illuminate\Support\Str::contains(strtolower(session('success')), 'updated') ? 'Updated' : 'Success') : '' }}" data-message="{{ session('success') ? e(session('success')) : '' }}" class="hidden"></div>

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
                <button type="button" id="closeFlashSuccessBtn" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">OK</button>
            </div>
        </div>
    </div>

    <script>
        // Search and Filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const riskFilter = document.getElementById('riskFilter');
            
            function filterTable() {
                const searchTerm = searchInput?.value.toLowerCase() || '';
                const riskValue = riskFilter?.value || '';
                
                // For desktop table rows
                const rows = document.querySelectorAll('.visit-row');
                rows.forEach(row => {
                    const name = row.dataset.name || '';
                    const risk = row.dataset.risk || '';
                    
                    const matchesSearch = name.includes(searchTerm);
                    const matchesRisk = !riskValue || risk === riskValue;
                    
                    if (matchesSearch && matchesRisk) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                // For mobile cards
                const cards = document.querySelectorAll('.visit-item');
                cards.forEach(card => {
                    const name = card.dataset.name || '';
                    const risk = card.dataset.risk || '';
                    
                    const matchesSearch = name.includes(searchTerm);
                    const matchesRisk = !riskValue || risk === riskValue;
                    
                    if (matchesSearch && matchesRisk) {
                        card.style.display = '';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }
            
            searchInput?.addEventListener('input', filterTable);
            riskFilter?.addEventListener('change', filterTable);

            const deleteButtons = document.querySelectorAll('.delete-visit-btn');
            let pendingDeleteForm = null;

            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    pendingDeleteForm = this.closest('.delete-form');
                    openDeleteConfirmationModal();
                });
            });

            document.getElementById('confirmDeleteVisitBtn').addEventListener('click', function() {
                if (pendingDeleteForm) {
                    closeDeleteConfirmationModal();
                    pendingDeleteForm.submit();
                }
            });

            document.getElementById('cancelDeleteVisitBtn')?.addEventListener('click', function() {
                closeDeleteConfirmationModal();
            });

            document.getElementById('closeDeleteSuccessBtn')?.addEventListener('click', function() {
                closeDeleteSuccessModal();
            });

            document.getElementById('deleteConfirmationModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDeleteConfirmationModal();
                }
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
            }

            function openDeleteSuccessModal() {
                const modal = document.getElementById('deleteSuccessModal');
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }
            }

            function closeDeleteSuccessModal() {
                const modal = document.getElementById('deleteSuccessModal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            }

            function openFlashSuccessModal() {
                const modal = document.getElementById('flashSuccessModal');
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }
            }

            function closeFlashSuccessModal() {
                const modal = document.getElementById('flashSuccessModal');
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            }

            const deleteSuccessFlag = document.getElementById('deleteSuccessFlag');
            const deleteSuccess = deleteSuccessFlag?.dataset.success === 'true';

            const flashSuccessFlag = document.getElementById('flashSuccessFlag');
            const flashSuccessMessage = flashSuccessFlag?.dataset.message || '';
            const flashSuccessTitle = flashSuccessFlag?.dataset.title || 'Success';

            if (deleteSuccess) {
                openDeleteSuccessModal();
            } else if (flashSuccessMessage) {
                const titleEl = document.getElementById('flashSuccessTitle');
                const messageEl = document.getElementById('flashSuccessMessage');
                if (titleEl) titleEl.textContent = flashSuccessTitle;
                if (messageEl) messageEl.textContent = flashSuccessMessage;
                openFlashSuccessModal();
            }

            document.getElementById('closeFlashSuccessBtn')?.addEventListener('click', closeFlashSuccessModal);
            document.getElementById('flashSuccessModal')?.addEventListener('click', function(e) {
                if (e.target === this) closeFlashSuccessModal();
            });
        });
    </script>
</x-app-layout>