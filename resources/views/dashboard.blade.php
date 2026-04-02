<x-app-layout>
    <div class="flex min-h-screen bg-gray-50">

        {{-- ==================== SIDEBAR - FIXED LAYOUT ==================== --}}
<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-white shadow-lg transform transition-transform duration-300 ease-in-out lg:translate-x-0 -translate-x-full border-r border-gray-200 flex flex-col">
    <div class="p-6 border-b border-gray-200 flex-shrink-0">
        <div class="flex items-center justify-between">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                <span class="text-3xl">🤰</span>
                <div>
                    <h1 class="text-xl font-bold text-blue-600">Depla</h1>
                    <p class="text-xs text-gray-500">Maternity Clinic</p>
                </div>
            </a>
            <button id="closeSidebar" class="lg:hidden text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    </div>
    
    {{-- Scrollable Navigation Area --}}
    <nav class="flex-1 overflow-y-auto p-4 space-y-1">
        @php
            $navigationItems = [
                ['icon' => '📊', 'label' => 'Dashboard', 'route' => 'dashboard'],
                ['icon' => '👥', 'label' => 'Patients', 'route' => 'patients.index'],
                ['icon' => '🩺', 'label' => 'Prenatal Visits', 'route' => 'prenatal-visits.index'],
                ['icon' => '⚠️', 'label' => 'Risk Monitoring', 'route' => 'risk.monitoring'],
                ['icon' => '📈', 'label' => 'Reports', 'route' => 'dashboard'],
                ['icon' => '📦', 'label' => 'Delivered Patients', 'route' => 'patients.delivered'],
            ];

            if (auth()->user()->role === 'admin') {
                $navigationItems[] = ['icon' => '🛠️', 'label' => 'Manage Staff', 'route' => 'staff.index'];
                $navigationItems[] = ['icon' => '📜', 'label' => 'Audit Logs', 'route' => 'audit-logs.index'];
            }
        @endphp
        @foreach($navigationItems as $item)
            @php
                $isActive = request()->routeIs($item['route']);
            @endphp
            <a href="{{ route($item['route']) }}"
               class="flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-200
                   {{ $isActive 
                       ? 'bg-blue-50 text-blue-600 border-l-4 border-blue-600 font-semibold' 
                       : 'text-gray-700 hover:bg-gray-50' }}">
                <span class="text-lg w-6">{{ $item['icon'] }}</span>
                <span class="text-sm font-medium">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    {{-- Status Card - Now part of normal flow, not absolutely positioned --}}
    <div class="p-4 border-t border-gray-200 flex-shrink-0">
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-4 border border-blue-100">
            <p class="text-xs text-gray-600 font-semibold mb-3">System Status</p>
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 bg-green-500 rounded-full animate-pulse"></span>
                    <span class="text-xs text-gray-600">Services Active</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 bg-blue-500 rounded-full"></span>
                    <span class="text-xs text-gray-600">Database Connected</span>
                </div>
            </div>
        </div>
    </div>
</aside>

        {{-- Overlay for mobile --}}
        <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden hidden transition-opacity duration-300"></div>

        {{-- ==================== MAIN CONTENT ==================== --}}
        <div class="flex-1 flex flex-col min-h-screen lg:ml-64">
            
            {{-- TOP NAVBAR - Responsive --}}
            <header class="bg-white sticky top-0 z-30 border-b border-gray-200 shadow-sm">
                <div class="flex items-center justify-between px-4 sm:px-6 lg:px-8 py-3 sm:py-4">
                    <div class="flex items-center gap-3">
                        <button id="openSidebar" class="lg:hidden text-gray-600 hover:text-blue-600 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>
                        <div>
                            <h2 class="text-xl sm:text-2xl font-bold text-gray-900">Dashboard</h2>
                            <p class="text-xs sm:text-sm text-gray-500 mt-0.5 sm:mt-1">Welcome back! Here's your clinic overview.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 sm:gap-6">
                        <div class="relative">
                            <button class="relative text-gray-600 hover:text-blue-600 transition text-xl sm:text-2xl">
                                🔔
                                <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center">
                                    {{ $upcomingAppointments }}
                                </span>
                            </button>
                        </div>
                        <div class="flex items-center gap-3 sm:gap-4 pl-3 sm:pl-6 border-l border-gray-200">
                            <div class="flex items-center gap-2 sm:gap-3">
                                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-gradient-to-r from-blue-600 to-blue-400 flex items-center justify-center text-white font-bold shadow-md text-sm sm:text-base">
                                    {{ strtoupper(auth()->user()?->name[0] ?? 'U') }}
                                </div>
                                <div class="hidden sm:block">
                                    <p class="text-sm font-semibold text-gray-900">
                                        {{ auth()->user()?->name ?? 'User' }}
                                    </p>
                                    <p class="text-xs text-gray-500">Healthcare Provider</p>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="text-red-500 hover:text-red-700 text-xs sm:text-sm font-semibold transition">
                                    Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            {{-- MAIN CONTENT - Responsive Grid --}}
            <main class="flex-1 p-4 sm:p-6 lg:p-8 space-y-6 sm:space-y-8 overflow-y-auto">
                
                {{-- KPI CARDS - Responsive Grid --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                    

                    {{-- Active Pregnancies --}}
                    <div class="bg-white rounded-xl sm:rounded-2xl shadow-md hover:shadow-lg transition-all duration-300 p-4 sm:p-6 border border-gray-100 group">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-xs sm:text-sm font-medium mb-1 sm:mb-2">Active Pregnancies</p>
                                <h3 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $activePregnancies }}</h3>
                                
                            </div>
                            <div class="h-12 w-12 sm:h-14 sm:w-14 rounded-2xl bg-green-100 flex items-center justify-center text-2xl sm:text-3xl shadow-inner group-hover:scale-110 transition">
                                🤰
                            </div>
                        </div>
                    </div>

                    {{-- High Risk --}}
                    <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-xl sm:rounded-2xl shadow-md hover:shadow-lg transition-all duration-300 p-4 sm:p-6 border border-red-200 group">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-red-700 text-xs sm:text-sm font-medium mb-1 sm:mb-2">High Risk</p>
                                <h3 class="text-2xl sm:text-3xl font-bold text-red-700">{{ $highRisk }}</h3>
                                <span class="inline-flex items-center gap-1 bg-red-200 text-red-800 px-2 py-0.5 sm:px-2.5 sm:py-1 rounded-full text-xs font-semibold mt-1 sm:mt-2">
                                    ⚠️ Priority
                                </span>
                            </div>
                            <div class="h-12 w-12 sm:h-14 sm:w-14 rounded-2xl bg-red-200 flex items-center justify-center text-2xl sm:text-3xl shadow-inner group-hover:scale-110 transition">
                                🚨
                            </div>
                        </div>
                    </div>

                    {{-- Upcoming Appointments --}}
                    <div class="bg-white rounded-xl sm:rounded-2xl shadow-md hover:shadow-lg transition-all duration-300 p-4 sm:p-6 border border-gray-100 group">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 text-xs sm:text-sm font-medium mb-1 sm:mb-2">Upcoming Visits</p>
                                <h3 class="text-2xl sm:text-3xl font-bold text-gray-900">{{ $upcomingAppointments }}</h3>
                                <p class="text-xs text-gray-400 mt-1 sm:mt-2">Scheduled</p>
                            </div>
                            <div class="h-12 w-12 sm:h-14 sm:w-14 rounded-2xl bg-purple-100 flex items-center justify-center text-2xl sm:text-3xl shadow-inner group-hover:scale-110 transition">
                                📅
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 2-COLUMN GRID - Responsive --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8">
                    
                    {{-- High Risk Patients Panel --}}
                    <div class="bg-white rounded-xl sm:rounded-2xl shadow-md overflow-hidden border border-gray-100">
                        <div class="bg-gradient-to-r from-red-50 to-red-100 px-4 sm:px-6 py-3 sm:py-4 border-b border-red-200">
                            <h2 class="text-base sm:text-lg font-bold text-red-900 flex items-center gap-2">
                                🚨 High-Risk Patients
                            </h2>
                            <p class="text-xs sm:text-sm text-red-700 mt-1">Requires immediate attention</p>
                        </div>
                        <div class="p-4 sm:p-6 space-y-3 max-h-96 overflow-y-auto">
                            @forelse($highRiskPatients as $visit)
                                <a href="{{ route('patients.show', $visit->patient) }}" 
                                   class="block p-3 sm:p-4 border border-red-200 rounded-xl bg-red-50 hover:bg-red-100 hover:shadow-md transition-all group">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3 flex-1 min-w-0">
                                            <div class="h-10 w-10 rounded-full bg-red-300 flex items-center justify-center text-red-700 font-bold flex-shrink-0 text-sm">
                                                {{ strtoupper(substr($visit->patient->first_name ?? '', 0, 1)) }}{{ strtoupper(substr($visit->patient->last_name ?? '', 0, 1)) }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-semibold text-gray-900 text-sm sm:text-base truncate">
                                                    {{ $visit->patient->first_name }} {{ $visit->patient->last_name }}
                                                </p>
                                                <p class="text-xs text-gray-600">GA: {{ $visit->gestational_age }} weeks</p>
                                            </div>
                                        </div>
                                        <span class="text-xl group-hover:translate-x-1 transition ml-2">→</span>
                                    </div>
                                </a>
                            @empty
                                <div class="text-center py-8 sm:py-12">
                                    <div class="text-5xl mb-3">✅</div>
                                    <p class="text-base sm:text-lg text-green-600 font-semibold">All Clear</p>
                                    <p class="text-xs sm:text-sm text-gray-500 mt-1">No high-risk patients at this time</p>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Charts Column --}}
                    <div class="lg:col-span-2 space-y-6 sm:space-y-8">
                        {{-- Risk Distribution Chart --}}
                        <div class="bg-white rounded-xl sm:rounded-2xl shadow-md p-4 sm:p-6 border border-gray-100">
                            <h2 class="text-base sm:text-lg font-bold text-gray-900 mb-4 sm:mb-6">Risk Distribution</h2>
                            <div class="relative h-48 sm:h-56 md:h-64 w-full">
                                <canvas id="riskChart"></canvas>
                            </div>
                            <div class="flex items-center justify-center gap-4 sm:gap-6 mt-4 sm:mt-6 pt-4 sm:pt-6 border-t border-gray-200">
                                <div class="text-center">
                                    <div class="flex items-center gap-2 justify-center mb-1">
                                        <span class="w-3 h-3 rounded-full bg-green-500"></span>
                                        <p class="text-xl sm:text-2xl font-bold text-green-600">{{ $lowRisk }}</p>
                                    </div>
                                    <p class="text-xs sm:text-sm text-gray-600">Low Risk</p>
                                </div>
                                <div class="h-8 border-l border-gray-300"></div>
                                <div class="text-center">
                                    <div class="flex items-center gap-2 justify-center mb-1">
                                        <span class="w-3 h-3 rounded-full bg-red-500"></span>
                                        <p class="text-xl sm:text-2xl font-bold text-red-600">{{ $highRisk }}</p>
                                    </div>
                                    <p class="text-xs sm:text-sm text-gray-600">High Risk</p>
                                </div>
                            </div>
                        </div>

                        {{-- Trend Chart --}}
                        <div class="bg-white rounded-xl sm:rounded-2xl shadow-md p-4 sm:p-6 border border-gray-100">
                            <h2 class="text-base sm:text-lg font-bold text-gray-900 mb-4 sm:mb-6">Monthly Visits Trend</h2>
                            <div class="relative h-48 sm:h-56 md:h-64 w-full">
                                <canvas id="trendChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- LOWER SECTION - Responsive --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8">
                    
                    {{-- Smart Recommendations --}}
                    <div class="bg-white rounded-xl sm:rounded-2xl shadow-md overflow-hidden border border-gray-100">
                        <div class="bg-gradient-to-r from-blue-50 to-blue-100 px-4 sm:px-6 py-3 sm:py-4 border-b border-blue-200">
                            <h2 class="text-base sm:text-lg font-bold text-blue-900 flex items-center gap-2">
                                💡 Smart Insights
                            </h2>
                            <p class="text-xs sm:text-sm text-blue-700 mt-1">AI-Generated Recommendations</p>
                        </div>
                        <div class="p-4 sm:p-6 space-y-3 max-h-96 overflow-y-auto">
                            @forelse($recommendations as $rec)
                                <div class="flex items-start gap-2 sm:gap-3 p-3 bg-blue-50 rounded-lg border-l-4 border-blue-500">
                                    <span class="text-base sm:text-lg flex-shrink-0">💬</span>
                                    <p class="text-xs sm:text-sm text-gray-700 leading-relaxed">{{ $rec }}</p>
                                </div>
                            @empty
                                <p class="text-center text-gray-500 py-6 text-sm">No recommendations</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Recent Prenatal Visits --}}
                    <div class="bg-white rounded-xl sm:rounded-2xl shadow-md overflow-hidden border border-gray-100 lg:col-span-2">
                        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-white">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div>
                                    <h2 class="text-base sm:text-lg font-bold text-gray-900">Recent Prenatal Visits</h2>
                                    <p class="text-xs sm:text-sm text-gray-600 mt-1">Latest check-ins</p>
                                </div>
                                <a href="{{ route('prenatal-visits.index') }}" class="text-sm text-blue-600 hover:text-blue-700 font-semibold self-start sm:self-auto">
                                    View All →
                                </a>
                            </div>
                        </div>
                        
                        {{-- Mobile Card View --}}
                        <div class="block lg:hidden divide-y divide-gray-100 max-h-96 overflow-y-auto">
                            @forelse($recentVisits as $visit)
                                <div class="p-4 hover:bg-gray-50 transition">
                                    <div class="flex items-center justify-between mb-2">
                                        <a href="{{ route('patients.show', $visit->patient) }}" class="font-medium text-gray-900 hover:text-blue-600">
                                            {{ $visit->patient->first_name }} {{ $visit->patient->last_name }}
                                        </a>
                                        <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold
                                            {{ $visit->risk_level == 'HIGH' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                            {{ $visit->risk_level ?? 'LOW' }}
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-xs text-gray-600">
                                        <div>
                                            <span class="font-medium">Date:</span> {{ \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') }}
                                        </div>
                                        <div>
                                            <span class="font-medium">BP:</span> {{ $visit->bp_sys }}/{{ $visit->bp_dia }}
                                        </div>
                                        <div>
                                            <span class="font-medium">GA:</span> {{ $visit->gestational_age ?? '-' }} wk
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div class="p-8 text-center text-gray-500">No prenatal visits recorded</div>
                            @endforelse
                        </div>
                        
                        {{-- Desktop Table View --}}
                        <div class="hidden lg:block overflow-x-auto max-h-96">
                            <table class="w-full text-sm">
                                <thead class="sticky top-0 bg-gray-50">
                                    <tr class="border-b border-gray-200">
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Patient</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Visit Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">BP</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">GA</th>
                                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Risk</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @forelse($recentVisits as $visit)
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="px-6 py-4">
                                                <a href="{{ route('patients.show', $visit->patient) }}" 
                                                   class="font-medium text-gray-900 hover:text-blue-600 transition">
                                                    {{ $visit->patient->first_name }} {{ $visit->patient->last_name }}
                                                </a>
                                            </td>
                                            <td class="px-6 py-4 text-gray-600 whitespace-nowrap">
                                                {{ \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') }}
                                            </td>
                                            <td class="px-6 py-4 font-mono text-xs text-gray-700 whitespace-nowrap">
                                                {{ $visit->bp_sys }}/{{ $visit->bp_dia }}
                                            </td>
                                            <td class="px-6 py-4 text-gray-600 whitespace-nowrap">
                                                {{ $visit->gestational_age ?? '-' }} wk
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold
                                                    {{ $visit->risk_level == 'HIGH' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                                    {{ $visit->risk_level ?? 'LOW' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                                No prenatal visits recorded
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Condition Alerts - Responsive --}}
                @if($hypertensionCount > 0 || $diabetesCount > 0 || $anemiaCount > 0)
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                        @if($hypertensionCount > 0)
                            <div class="bg-orange-50 border border-orange-200 rounded-xl sm:rounded-2xl p-4 sm:p-6 hover:shadow-md transition">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-2xl">🩸</span>
                                    <h3 class="font-bold text-orange-900 text-sm sm:text-base">Hypertension Alert</h3>
                                </div>
                                <p class="text-orange-700 text-xs sm:text-sm">{{ $hypertensionCount }} patients with elevated blood pressure</p>
                            </div>
                        @endif

                        @if($diabetesCount > 0)
                            <div class="bg-yellow-50 border border-yellow-200 rounded-xl sm:rounded-2xl p-4 sm:p-6 hover:shadow-md transition">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-2xl">🍬</span>
                                    <h3 class="font-bold text-yellow-900 text-sm sm:text-base">Diabetes Alert</h3>
                                </div>
                                <p class="text-yellow-700 text-xs sm:text-sm">{{ $diabetesCount }} patients with diabetes diagnosis</p>
                            </div>
                        @endif

                        @if($anemiaCount > 0)
                            <div class="bg-amber-50 border border-amber-200 rounded-xl sm:rounded-2xl p-4 sm:p-6 hover:shadow-md transition">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-2xl">🫀</span>
                                    <h3 class="font-bold text-amber-900 text-sm sm:text-base">Anemia Alert</h3>
                                </div>
                                <p class="text-amber-700 text-xs sm:text-sm">{{ $anemiaCount }} patients with anemia diagnosis</p>
                            </div>
                        @endif
                    </div>
                @endif

               

            </main>
        </div>
    </div>

    {{-- Mobile Sidebar Script --}}
    <script>
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const openBtn = document.getElementById('openSidebar');
        const closeBtn = document.getElementById('closeSidebar');

        function openSidebar() {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebar() {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
            document.body.style.overflow = '';
        }

        if (openBtn) openBtn.addEventListener('click', openSidebar);
        if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
        if (overlay) overlay.addEventListener('click', closeSidebar);

        // Close on window resize if open
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) {
                closeSidebar();
            }
        });
    </script>

    {{-- Charts Script --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Risk Distribution Doughnut Chart
            const riskCtx = document.getElementById('riskChart').getContext('2d');
            const highRisk = {{ $highRisk }};
            const lowRisk = {{ $lowRisk }};
            const totalRisk = highRisk + lowRisk;

            new Chart(riskCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Low Risk', 'High Risk'],
                    datasets: [{
                        data: [lowRisk, highRisk],
                        backgroundColor: ['#22c55e', '#ef4444'],
                        borderWidth: 0,
                        hoverOffset: 8,
                        cutout: '65%'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: { size: 14 },
                            bodyFont: { size: 12 },
                            callbacks: {
                                label: function(context) {
                                    const value = context.raw;
                                    const percentage = totalRisk > 0 ? ((value / totalRisk) * 100).toFixed(1) : 0;
                                    return `${value} patients (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });

            // Monthly Visits Trend Line Chart
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const trendLabels = {!! json_encode($trendLabels) !!}.map(month => monthNames[month - 1] || 'N/A');
            const trendData = {!! json_encode($trendData) !!};

            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [{
                        label: 'Visits',
                        data: trendData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.05)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: { font: { size: 12 } }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: { size: 14 }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0, 0, 0, 0.05)' },
                            ticks: { font: { size: 11 } }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 11 } }
                        }
                    }
                }
            });
        });
    </script>
</x-app-layout>