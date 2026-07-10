<x-app-layout>
    <div class="space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('patients.delivered') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-50 text-blue-700 hover:bg-blue-100">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <h1 class="text-2xl font-semibold text-gray-900">Pregnancy History</h1>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-pink-50 text-pink-600">
                        <svg class="h-9 w-9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">{{ $patient->first_name }} {{ $patient->middle_name ? $patient->middle_name . ' ' : '' }}{{ $patient->last_name }}</h2>
                        <div class="mt-2 flex flex-wrap gap-2 text-sm text-gray-600">
                            <span>{{ $patient->age ?: 'N/A' }} years</span>
                            <span>&bull;</span>
                            <span>{{ $patient->contact_number ?: 'No contact number' }}</span>
                            <span>&bull;</span>
                            <span>Blood Type: {{ $patient->blood_type ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:min-w-[460px]">
                    <div class="rounded-xl border border-gray-100 bg-white p-4 text-center shadow-sm">
                        <div class="text-xs font-semibold text-blue-700">Gravida</div>
                        <div class="mt-1 text-xl font-bold text-gray-900">{{ $patient->gravida ?? 'N/A' }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-white p-4 text-center shadow-sm">
                        <div class="text-xs font-semibold text-blue-700">Para</div>
                        <div class="mt-1 text-xl font-bold text-gray-900">{{ $patient->para ?? 'N/A' }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-white p-4 text-center shadow-sm">
                        <div class="text-xs font-semibold text-blue-700">Total Babies</div>
                        <div class="mt-1 text-xl font-bold text-gray-900">{{ $totalBabies }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-100 bg-white p-4 text-center shadow-sm">
                        <div class="text-xs font-semibold text-blue-700">PhilHealth</div>
                        <div class="mt-1 text-sm font-bold {{ $patient->philhealth_member ? 'text-green-700' : 'text-gray-500' }}">{{ $patient->philhealth_member ? 'Yes' : 'No' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">Completed Pregnancies ({{ $pregnancies->count() }})</h2>
                    <p class="mt-1 text-sm text-gray-500">List of all completed pregnancies for this patient. Most recent first.</p>
                </div>

                <div class="group relative w-full sm:w-64">
                    <button type="button" class="flex w-full items-center justify-between rounded-lg border border-blue-300 bg-white px-4 py-2.5 text-sm font-semibold text-blue-700 hover:bg-blue-50">
                        <span class="inline-flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                            </svg>
                            Print
                        </span>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div class="invisible absolute right-0 z-20 mt-2 w-full rounded-xl border border-gray-100 bg-white py-2 opacity-0 shadow-lg transition group-hover:visible group-hover:opacity-100">
                        @foreach($pregnancies->flatMap->babies->values() as $baby)
                            <a href="{{ route('patients.delivered.print-babies', ['id' => $patient->id, 'baby_id' => $baby->id]) }}" target="_blank" class="block px-4 py-2 text-sm text-gray-700 hover:bg-blue-50">
                                Print Baby {{ $loop->iteration }}
                            </a>
                        @endforeach
                        <a href="{{ route('patients.delivered.print-babies', ['id' => $patient->id, 'all' => 1]) }}" target="_blank" class="block border-t border-gray-100 px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-blue-50">
                            Print All Babies ({{ $totalBabies }})
                        </a>
                    </div>
                </div>
            </div>

            <div class="relative space-y-5 before:absolute before:left-3 before:top-2 before:h-[calc(100%-1rem)] before:w-px before:bg-pink-200">
                @foreach($pregnancies as $pregnancy)
                    @php
                        $latestVisit = $pregnancy->prenatalVisits->sortByDesc('visit_date')->first();
                        $riskLevel = $latestVisit?->risk_level ?: 'N/A';
                        $riskClass = $riskLevel === 'HIGH' ? 'bg-red-100 text-red-700' : ($riskLevel === 'LOW' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700');
                    @endphp
                    <div class="relative pl-10">
                        <div class="absolute left-0 top-6 flex h-7 w-7 items-center justify-center rounded-full {{ $loop->first ? 'bg-pink-500' : 'bg-blue-400' }} text-xs font-bold text-white">{{ $loop->iteration }}</div>
                        <a href="{{ route('patients.delivered.babies', $pregnancy->id) }}" class="block rounded-xl border {{ $loop->first ? 'border-pink-100 bg-pink-50' : 'border-blue-100 bg-blue-50' }} p-5 transition hover:shadow-md">
                            <div class="mb-4 inline-flex rounded-md {{ $loop->first ? 'bg-pink-100 text-pink-700' : 'bg-blue-100 text-blue-700' }} px-3 py-1 text-xs font-bold">
                                Pregnancy #{{ $pregnancies->count() - $loop->index }}{{ $loop->first ? ' (Most Recent)' : '' }}
                            </div>
                            <div class="grid grid-cols-2 gap-4 text-sm md:grid-cols-6">
                                <div>
                                    <div class="text-xs text-gray-500">Delivery Date</div>
                                    <div class="font-semibold text-gray-900">{{ $pregnancy->delivery_date ? \Carbon\Carbon::parse($pregnancy->delivery_date)->format('M d, Y') : 'N/A' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Delivery Type</div>
                                    <div class="font-semibold text-gray-900">{{ $pregnancy->delivery_type ?? 'Normal Delivery' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Babies</div>
                                    <div class="font-semibold text-gray-900">{{ $pregnancy->babies->count() }} {{ $pregnancy->babies->count() > 1 ? '(Multiple)' : '' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Risk Level</div>
                                    <span class="inline-flex rounded-md px-2 py-1 text-xs font-semibold {{ $riskClass }}">{{ Str::title(strtolower($riskLevel)) }}</span>
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500">Status</div>
                                    <span class="inline-flex rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">Delivered</span>
                                </div>
                                <div class="text-blue-700 font-semibold">View Babies</div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 flex items-center gap-2 text-sm text-gray-500">
                <svg class="h-4 w-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Pregnancies are sorted from most recent to oldest.
            </div>
        </div>
    </div>
</x-app-layout>
