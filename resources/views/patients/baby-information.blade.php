<x-app-layout>
    @php
        $riskLevel = $latestVisit?->risk_level ?: 'N/A';
        $riskClass = $riskLevel === 'HIGH' ? 'bg-red-100 text-red-700' : ($riskLevel === 'LOW' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700');
    @endphp

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('patients.delivered.history', $pregnancy->id) }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-50 text-blue-700 hover:bg-blue-100">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <h1 class="text-2xl font-semibold text-gray-900">Baby Information</h1>
            </div>
            <a href="{{ route('patients.delivered.history', $pregnancy->id) }}" class="text-gray-400 hover:text-gray-600">&times;</a>
        </div>

        <div class="p-6">
            <div class="mb-6 flex items-center justify-between gap-4">
                <span class="inline-flex rounded-md bg-pink-100 px-3 py-1 text-sm font-bold text-pink-700">Pregnancy Record</span>
                <span class="inline-flex rounded-md bg-green-100 px-3 py-1 text-xs font-bold text-green-700">Delivered</span>
            </div>

            <div class="mb-6 grid grid-cols-2 gap-4 border-b border-gray-100 pb-6 md:grid-cols-5">
                <div>
                    <div class="text-xs text-gray-500">Delivery Date</div>
                    <div class="mt-1 font-semibold text-gray-900">{{ $pregnancy->delivery_date ? \Carbon\Carbon::parse($pregnancy->delivery_date)->format('M d, Y') : 'N/A' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Delivery Type</div>
                    <div class="mt-1 font-semibold text-gray-900">{{ $pregnancy->delivery_type ?? 'Normal Delivery' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Babies</div>
                    <div class="mt-1 font-semibold text-gray-900">{{ $pregnancy->babies->count() }} {{ $pregnancy->babies->count() > 1 ? '(Multiple)' : '' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Risk Level</div>
                    <span class="mt-1 inline-flex rounded-md px-2 py-1 text-xs font-semibold {{ $riskClass }}">{{ Str::title(strtolower($riskLevel)) }}</span>
                </div>
                <div>
                    <div class="text-xs text-gray-500">Delivery Status</div>
                    <div class="mt-1 font-semibold text-gray-900">Delivered</div>
                </div>
            </div>

            <div class="space-y-6">
                @forelse($pregnancy->babies as $baby)
                    <section>
                        <h2 class="mb-3 text-base font-bold text-pink-600">Baby {{ $loop->iteration }}</h2>
                        <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <div class="text-xs font-semibold text-blue-600">Full Name</div>
                                    <div class="mt-1 font-semibold text-gray-900">{{ $baby->full_name ?: 'N/A' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-semibold text-blue-600">Sex</div>
                                    <div class="mt-1 font-semibold text-gray-900">{{ $baby->sex ?: 'N/A' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-semibold text-blue-600">Date of Birth</div>
                                    <div class="mt-1 font-semibold text-gray-900">{{ $baby->date_of_birth ? \Carbon\Carbon::parse($baby->date_of_birth)->format('M d, Y') : 'N/A' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-semibold text-blue-600">Time of Birth</div>
                                    <div class="mt-1 font-semibold text-gray-900">{{ $baby->time_of_birth ? \Carbon\Carbon::parse($baby->time_of_birth)->format('h:i A') : 'N/A' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-semibold text-blue-600">Birth Weight</div>
                                    <div class="mt-1 font-semibold text-gray-900">{{ $baby->birth_weight ? $baby->birth_weight . ' kg' : 'N/A' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-semibold text-blue-600">Birth Length</div>
                                    <div class="mt-1 font-semibold text-gray-900">{{ $baby->birth_length ? $baby->birth_length . ' cm' : 'N/A' }}</div>
                                </div>
                            </div>
                        </div>
                    </section>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 p-8 text-center text-sm text-gray-500">No baby records found for this pregnancy.</div>
                @endforelse
            </div>

            <div class="mt-6 rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-700">
                You can print individual baby information or all babies from the Print button in Pregnancy History.
            </div>
        </div>
    </div>
</x-app-layout>
