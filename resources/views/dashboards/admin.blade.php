<x-app-layout>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-blue-50">

        {{-- ==================== HEADER ==================== --}}
        <div class="bg-white border-b border-blue-100 sticky top-16 z-20 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">📊 Business Analytics</h1>
                        <p class="text-gray-600 text-sm mt-1">Clinic Performance & Insights</p>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="alert('Export coming soon')" class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-medium transition">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                            Export Report
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ==================== MAIN CONTENT ==================== --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-8">

            {{-- KPI CARDS - Business View --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                
                <!-- Total Patients -->
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl shadow-sm border border-blue-200 p-6 hover:shadow-lg transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-700 text-sm font-semibold">Total Patients</p>
                            <h3 class="text-4xl font-bold text-blue-900 mt-3">{{ $totalPatients }}</h3>
                            <p class="text-xs text-blue-600 mt-2">Active in system</p>
                        </div>
                        <div class="h-14 w-14 rounded-2xl bg-blue-200 flex items-center justify-center text-2xl">
                            👥
                        </div>
                    </div>
                </div>

                <!-- Active Cases -->
                <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl shadow-sm border border-green-200 p-6 hover:shadow-lg transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-700 text-sm font-semibold">Active Cases</p>
                            <h3 class="text-4xl font-bold text-green-900 mt-3">{{ $activePregnancies }}</h3>
                            <p class="text-xs text-green-600 mt-2">Ongoing pregnancies</p>
                        </div>
                        <div class="h-14 w-14 rounded-2xl bg-green-200 flex items-center justify-center text-2xl">
                            🤰
                        </div>
                    </div>
                </div>

                <!-- High-Risk Cases -->
                <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-xl shadow-sm border border-red-200 p-6 hover:shadow-lg transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-red-700 text-sm font-semibold">High-Risk Cases</p>
                            <h3 class="text-4xl font-bold text-red-900 mt-3">{{ $highRisk }}</h3>
                            <p class="text-xs text-red-600 mt-2">Requiring priority care</p>
                        </div>
                        <div class="h-14 w-14 rounded-2xl bg-red-200 flex items-center justify-center text-2xl">
                            🚨
                        </div>
                    </div>
                </div>

                <!-- Monthly Visits -->
                <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl shadow-sm border border-purple-200 p-6 hover:shadow-lg transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-700 text-sm font-semibold">Visits This Month</p>
                            <h3 class="text-4xl font-bold text-purple-900 mt-3">{{ $visitsThisMonth }}</h3>
                            <p class="text-xs text-purple-600 mt-2">
                                <span class="font-bold {{ $visitGrowthPercent >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $visitGrowthPercent >= 0 ? '+' : '' }}{{ $visitGrowthPercent }}%
                                </span>
                                vs last month
                            </p>
                        </div>
                        <div class="h-14 w-14 rounded-2xl bg-purple-200 flex items-center justify-center text-2xl">
                            📅
                        </div>
                    </div>
                </div>
            </div>

            {{-- GROWTH METRICS --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">📈 Growth Metrics</h3>
                    <div class="space-y-4">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-medium text-gray-700">Visit Growth</p>
                                <span class="inline-flex px-3 py-1 rounded-full text-sm font-bold {{ $visitGrowthPercent >= 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $visitGrowthPercent >= 0 ? '+' : '' }}{{ $visitGrowthPercent }}%
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min(abs($visitGrowthPercent), 100) }}%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-medium text-gray-700">Patient Growth</p>
                                <span class="inline-flex px-3 py-1 rounded-full text-sm font-bold {{ $patientGrowthPercent >= 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                    {{ $patientGrowthPercent >= 0 ? '+' : '' }}{{ $patientGrowthPercent }}%
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-600 h-2 rounded-full" style="width: {{ min(abs($patientGrowthPercent), 100) }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- CONDITION BREAKDOWN --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">🏥 Common Conditions</h3>
                    <div class="space-y-3">
                        @foreach($conditions as $cond)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <span class="text-xl">{{ $cond['icon'] }}</span>
                                    <p class="text-sm font-medium text-gray-700">{{ $cond['name'] }}</p>
                                </div>
                                <span class="inline-flex items-center justify-center h-8 w-8 rounded-lg bg-blue-100 text-blue-600 font-bold text-sm">
                                    {{ $cond['count'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- BUSINESS INSIGHTS --}}
                <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl shadow-sm border border-amber-200 p-6">
                    <h3 class="text-lg font-bold text-amber-900 mb-4">💡 Business Insights</h3>
                    <div class="space-y-3">
                        @foreach($insights as $insight)
                            <div class="flex gap-3 p-3 bg-white rounded-lg border border-amber-100">
                                <span class="text-lg flex-shrink-0">💬</span>
                                <p class="text-sm text-gray-700 leading-relaxed">{{ $insight }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- CHARTS SECTION --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                {{-- RISK DISTRIBUTION CHART --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-6">Risk Distribution</h3>
                    <div class="relative h-64 w-full">
                        <canvas id="riskChart"></canvas>
                    </div>
                    <div class="flex items-center justify-center gap-8 mt-6 pt-6 border-t border-gray-200">
                        <div class="text-center">
                            <div class="inline-flex items-center gap-2 mb-2">
                                <span class="w-3 h-3 rounded-full bg-green-500"></span>
                                <p class="text-2xl font-bold text-green-600">{{ $lowRisk }}</p>
                            </div>
                            <p class="text-sm text-gray-600">Low Risk</p>
                        </div>
                        <div class="h-8 border-l border-gray-300"></div>
                        <div class="text-center">
                            <div class="inline-flex items-center gap-2 mb-2">
                                <span class="w-3 h-3 rounded-full bg-red-500"></span>
                                <p class="text-2xl font-bold text-red-600">{{ $highRisk }}</p>
                            </div>
                            <p class="text-sm text-gray-600">High Risk</p>
                        </div>
                    </div>
                </div>

                {{-- MONTHLY TREND CHART --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-6">Monthly Visits Trend</h3>
                    <div class="relative h-64 w-full">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>

            {{-- HIGH RISK PATIENTS & STATISTICS --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                {{-- HIGH RISK PATIENTS --}}
                <div class="lg:col-span-1 bg-white rounded-xl shadow-sm border border-red-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-red-50 to-red-100 px-6 py-4 border-b border-red-200">
                        <h2 class="text-lg font-bold text-red-900">🚨 High-Risk Patients</h2>
                        <p class="text-sm text-red-700 mt-1">Priority monitoring</p>
                    </div>
                    <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                        @forelse($highRiskPatients as $visit)
                            <a href="{{ route('patients.show', $visit->patient) }}" class="block p-4 hover:bg-red-50 transition group">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-gray-900 truncate">{{ $visit->patient->first_name }} {{ $visit->patient->last_name }}</p>
                                        <p class="text-xs text-gray-500 mt-1">{{ $visit->visit_date->format('M d, Y') }}</p>
                                        <p class="text-xs text-red-600 font-bold mt-1">GA: {{ $visit->gestational_age ?? '-' }} weeks</p>
                                    </div>
                                    <span class="text-lg group-hover:translate-x-1 transition flex-shrink-0">→</span>
                                </div>
                            </a>
                        @empty
                            <div class="p-8 text-center text-gray-500">
                                <p class="text-4xl mb-2">✅</p>
                                <p class="text-sm">All Clear</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- STATISTICS CARDS --}}
                <div class="lg:col-span-2 space-y-4">
                    
                    {{-- CONDITIONS ALERT --}}
                    @if($hypertensionCount > 0 || $diabetesCount > 0 || $anemiaCount > 0)
                        <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border border-yellow-200 rounded-xl p-6">
                            <h3 class="text-lg font-bold text-yellow-900 mb-4">⚠️ Condition Alerts</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                @if($hypertensionCount > 0)
                                    <div class="bg-white rounded-lg p-4 border border-yellow-100">
                                        <p class="text-orange-600 font-bold text-2xl">{{ $hypertensionCount }}</p>
                                        <p class="text-sm text-gray-700 mt-1">🩸 Hypertension</p>
                                    </div>
                                @endif
                                @if($diabetesCount > 0)
                                    <div class="bg-white rounded-lg p-4 border border-yellow-100">
                                        <p class="text-yellow-600 font-bold text-2xl">{{ $diabetesCount }}</p>
                                        <p class="text-sm text-gray-700 mt-1">🍬 Diabetes</p>
                                    </div>
                                @endif
                                @if($anemiaCount > 0)
                                    <div class="bg-white rounded-lg p-4 border border-yellow-100">
                                        <p class="text-amber-600 font-bold text-2xl">{{ $anemiaCount }}</p>
                                        <p class="text-sm text-gray-700 mt-1">🫀 Anemia</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- PERFORMANCE SUMMARY --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <p class="text-gray-600 text-sm font-medium">Total Visits</p>
                            <h3 class="text-3xl font-bold text-gray-900 mt-2">{{ $visitsThisMonth + 150 }}</h3>
                            <p class="text-xs text-gray-500 mt-2">All time</p>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <p class="text-gray-600 text-sm font-medium">Avg Risk %</p>
                            @php
                                $totalRiskCases = $highRisk + $lowRisk;
                                $riskPercent = $totalRiskCases > 0 ? round(($highRisk / $totalRiskCases) * 100) : 0;
                            @endphp
                            <h3 class="text-3xl font-bold text-gray-900 mt-2">{{ $riskPercent }}%</h3>
                            <p class="text-xs text-gray-500 mt-2">High-risk rate</p>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                            <p class="text-gray-600 text-sm font-medium">Upcoming</p>
                            <h3 class="text-3xl font-bold text-gray-900 mt-2">{{ $upcomingAppointments }}</h3>
                            <p class="text-xs text-gray-500 mt-2">Scheduled visits</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- CHARTS LIBRARY & SCRIPTS --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Risk Distribution Chart
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

            // Trend Chart
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const trendLabels = {!! json_encode($trendLabels) !!}.map(month => monthNames[month - 1] || 'N/A');
            const trendData = {!! json_encode($trendData) !!};

            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [{
                        label: 'Monthly Visits',
                        data: trendData,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.05)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
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
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0, 0, 0, 0.05)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        });
    </script>
</x-app-layout>
