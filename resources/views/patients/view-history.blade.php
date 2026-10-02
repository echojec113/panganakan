<x-app-layout>
    @include('patients.partials.responsive-styles')
    <div class="patient-module">
    <div class="space-y-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('view-all-records.index') }}" class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-50 text-blue-700 hover:bg-blue-100" aria-label="Back to all records">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </a>
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Patient History</h1>
                <p class="mt-1 text-sm text-gray-500">All pregnancy records for this patient.</p>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
            <h2 class="text-xl font-bold text-gray-900">{{ $patient->first_name }} {{ $patient->middle_name ? $patient->middle_name . ' ' : '' }}{{ $patient->last_name }}</h2>
            <p class="mt-1 text-sm text-gray-500">Birthdate: {{ $patient->birthdate?->format('M d, Y') ?: 'Not recorded' }}</p>
        </div>

        <div class="space-y-4">
            @forelse($pregnancies as $pregnancy)
                <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Pregnancy {{ $loop->iteration }}</h2>
                            <p class="mt-1 text-sm text-gray-500">
                                {{ $pregnancy->delivery_date ? 'Delivery date: ' . $pregnancy->delivery_date->format('M d, Y') : 'Created: ' . $pregnancy->created_at->format('M d, Y') }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span @class([
                                'rounded-full px-3 py-1 text-xs font-bold',
                                'bg-green-100 text-green-700' => $pregnancy->status === 'DELIVERED',
                                'bg-blue-100 text-blue-700' => $pregnancy->status === 'ONGOING',
                                'bg-amber-100 text-amber-700' => $pregnancy->status === 'REFERRED',
                                'bg-gray-100 text-gray-700' => ! in_array($pregnancy->status, ['DELIVERED', 'ONGOING', 'REFERRED'], true),
                            ])>{{ $pregnancy->status }}</span>
                            <a href="{{ route('view-all-records.pregnancy', $pregnancy) }}" class="rounded-lg border border-blue-200 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">View</a>
                            <a href="{{ route('view-all-records.pregnancy.print', $pregnancy) }}" target="_blank" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Print</a>
                        </div>
                    </div>
                </section>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 p-10 text-center text-sm text-gray-500">No pregnancy records were found for this patient.</div>
            @endforelse
        </div>
    </div>
    </div>
</x-app-layout>
