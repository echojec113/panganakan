<x-app-layout>
    @include('patients.partials.responsive-styles')
    <div class="patient-module">
    <div class="space-y-6">

        {{-- =========================
             PAGE HEADER
        ========================== --}}
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">
                Patient Records
            </h1>

            <p class="mt-1 text-sm text-gray-500">
                Review patient records and pregnancy history.
            </p>
        </div>


        {{-- Connected record summary --}}
        <section aria-label="Patient record summary" class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="flex flex-col lg:flex-row lg:items-center">
                <div class="px-5 py-4 sm:px-6 lg:w-1/3 lg:shrink-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total Patient Records</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900">{{ $totalPatientRecords }}</p>
                    <p class="mt-1 text-xs text-gray-500">All non-deleted patient records</p>
                </div>
                <div class="min-w-0 flex-1 border-t border-gray-100 px-5 py-4 sm:px-6 lg:border-l lg:border-t-0">
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Record Status</h2>
                    <dl class="grid grid-cols-1 divide-y divide-gray-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                        <div class="pb-3 sm:pb-0 sm:pr-4">
                            <dt class="text-sm text-gray-600">Ongoing</dt>
                            <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $ongoingPatientRecords }}</dd>
                        </div>
                        <div class="py-3 sm:px-4 sm:py-0">
                            <dt class="text-sm text-gray-600">Delivered</dt>
                            <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $deliveredPatientRecords }}</dd>
                        </div>
                        <div class="pt-3 sm:pl-4 sm:pt-0">
                            <dt class="text-sm text-gray-600">Historical Referred</dt>
                            <dd class="mt-1 text-xl font-semibold text-gray-900">{{ $referredPatientRecords }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        {{-- =========================
             PATIENT DIRECTORY
        ========================== --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

            {{-- Search / Filters --}}
            <div class="border-b border-gray-100 px-5 py-5 sm:px-6">

                <form
                    id="view-all-records-search"
                    method="GET"
                    action="{{ route('view-all-records.index') }}"
                    class="flex flex-col gap-3 sm:flex-row sm:items-end"
                >
                    {{-- Search --}}
                    <div class="min-w-0 flex-1">
                        <label for="search" class="mb-1.5 block text-xs font-medium text-gray-600">
                            Search Records
                        </label>

                        <div class="relative">
                            <input
                                type="search"
                                id="search"
                                name="search"
                                value="{{ $search }}"
                                placeholder="Search by patient name or Patient ID..."
                                class="h-11 w-full rounded-lg border border-gray-300 pl-4 pr-12 text-sm text-gray-900
                                       placeholder:text-gray-400
                                       focus:border-green-500 focus:ring-green-500
                                       [&::-webkit-search-cancel-button]:appearance-none
                                       [&::-webkit-search-decoration]:appearance-none"
                            >

                            <button
                                type="submit"
                                class="absolute inset-y-0 right-0 flex items-center px-3
                                       text-gray-400 hover:text-green-600
                                       focus:outline-none focus-visible:ring-2 focus-visible:ring-green-500 rounded-r-lg"
                                aria-label="Search"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="m21 21-4.35-4.35
                                           m1.35-5.4
                                           a6.75 6.75 0 11-13.5 0
                                           6.75 6.75 0 0113.5 0z"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Status --}}
                    <div class="w-full sm:w-48 sm:shrink-0">
                        <label for="status" class="mb-1.5 block text-xs font-medium text-gray-600">
                            Status
                        </label>

                        <select
                            id="status"
                            name="status"
                            onchange="this.form.submit()"
                            class="h-11 w-full rounded-lg border border-gray-300 pl-3 pr-10 text-sm text-gray-700
                                   focus:border-green-500 focus:ring-green-500"
                        >
                            <option value="" @selected($status === '')>
                                All Status
                            </option>

                            <option value="ONGOING" @selected($status === 'ONGOING')>
                                Ongoing
                            </option>

                            <option value="DELIVERED" @selected($status === 'DELIVERED')>
                                Delivered
                            </option>

                            <option value="REFERRED" @selected($status === 'REFERRED')>
                                Referred
                            </option>
                        </select>
                    </div>
                </form>

            </div>


            {{-- Table Header and action legend --}}
            <div class="flex flex-col gap-2 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <h2 class="text-sm font-semibold text-gray-900">Patient Records</h2>
                <div class="space-y-1 text-xs text-gray-500 sm:text-right">
                    <p>{{ $patients->total() }} {{ Str::plural('record', $patients->total()) }}</p>
                    <p class="flex items-center gap-1.5 sm:justify-end">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12z" />
                            <circle cx="12" cy="12" r="2.75" stroke-width="1.8" />
                        </svg>
                        <span>View patient record and pregnancy history</span>
                    </p>
                </div>
            </div>

            {{-- =========================
                 DESKTOP TABLE
            ========================== --}}
            <div class="hidden overflow-x-auto md:block">

                <table role="table" class="patient-data-table patient-list-table min-w-full divide-y divide-gray-100">

                    <thead role="rowgroup" class="bg-gray-50">
                        <tr role="row">
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Patient
                            </th>

                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Status
                            </th>

                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Latest Activity
                            </th>

                            <th scope="col" class="px-6 py-3 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Action
                            </th>
                        </tr>
                    </thead>


                    <tbody role="rowgroup" class="divide-y divide-gray-100 bg-white">

                        @forelse($patients as $patient)

                            @php
                                $statusLabel = match($patient->directory_status) {
                                    'ONGOING' => 'Ongoing',
                                    'DELIVERED' => 'Delivered',
                                    'REFERRED' => 'Historical Referred',
                                    default => 'Unknown',
                                };

                                $statusColor = match($patient->directory_status) {
                                    'ONGOING' => 'bg-green-50 text-green-700',
                                    'DELIVERED' => 'bg-blue-50 text-blue-700',
                                    'REFERRED' => 'bg-slate-100 text-slate-700',
                                    default => 'bg-gray-100 text-gray-700',
                                };
                            @endphp

                            <tr role="row" class="transition hover:bg-gray-50/70">

                                {{-- Patient --}}
                                <td role="cell" class="px-6 py-4">
                                    <div class="text-sm font-semibold text-gray-900">
                                        {{ $patient->first_name }}
                                        {{ $patient->middle_name ? $patient->middle_name . ' ' : '' }}
                                        {{ $patient->last_name }}
                                    </div>

                                    <div class="mt-1 text-xs text-gray-500">
                                        Patient ID:
                                        PT-{{ str_pad((string) $patient->id, 4, '0', STR_PAD_LEFT) }}
                                    </div>
                                </td>


                                {{-- Status --}}
                                <td role="cell" class="px-6 py-4">
                                    <div class="flex flex-wrap items-center gap-2">

    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusColor }}">
        {{ $statusLabel }}
    </span>

    @if($patient->has_pending_referral)
        <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
            Pending Referral
        </span>
    @endif

</div>
                                </td>


                                {{-- Latest Activity --}}
                                <td role="cell" class="px-6 py-4 text-sm text-gray-600">
                                    {{ $patient->directory_activity?->format('M d, Y') ?? 'Not recorded' }}
                                </td>


                                {{-- Action --}}
                                <td role="cell" class="px-6 py-4 text-center">
                                    <a
                                        href="{{ route('view-all-records.history', $patient) }}"
                                        title="View patient history"
                                        aria-label="View patient history for {{ $patient->first_name }} {{ $patient->last_name }}"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg
                                               text-gray-500 transition
                                               hover:bg-green-50 hover:text-green-700
                                               focus:outline-none focus:ring-2 focus:ring-green-500"
                                    >
                                        <svg
                                            class="h-5 w-5"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="1.8"
                                                d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12
                                                   18 18.75 12 18.75 2.25 12 2.25 12z"
                                            />

                                            <circle
                                                cx="12"
                                                cy="12"
                                                r="2.75"
                                                stroke-width="1.8"
                                            />
                                        </svg>
                                    </a>
                                </td>

                            </tr>

                        @empty

                            <tr role="row">
                                <td role="cell"
                                    colspan="4"
                                    class="px-6 py-12 text-center"
                                >
                                    <p class="text-sm font-medium text-gray-700">
                                        No patient records found
                                    </p>

                                    <p class="mt-1 text-xs text-gray-500">
                                        Try adjusting the search or status filter.
                                    </p>
                                </td>
                            </tr>

                        @endforelse

                    </tbody>
                </table>

            </div>


            {{-- =========================
                 MOBILE CARDS
            ========================== --}}
            <div class="divide-y divide-gray-100 md:hidden">

                @forelse($patients as $patient)

                    @php
                        $statusLabel = match($patient->directory_status) {
                            'ONGOING' => 'Ongoing',
                            'DELIVERED' => 'Delivered',
                            'REFERRED' => 'Historical Referred',
                            default => 'Unknown',
                        };

                        $statusColor = match($patient->directory_status) {
                            'ONGOING' => 'bg-green-50 text-green-700',
                            'DELIVERED' => 'bg-blue-50 text-blue-700',
                            'REFERRED' => 'bg-slate-100 text-slate-700',
                            default => 'bg-gray-100 text-gray-700',
                        };
                    @endphp

                    <div class="p-5">

                        <div class="flex items-start justify-between gap-4">

                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-900">
                                    {{ $patient->first_name }}
                                    {{ $patient->middle_name ? $patient->middle_name . ' ' : '' }}
                                    {{ $patient->last_name }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    Patient ID:
                                    PT-{{ str_pad((string) $patient->id, 4, '0', STR_PAD_LEFT) }}
                                </p>
                            </div>

                            <div class="flex shrink-0 flex-col items-end gap-2">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $statusColor }}">{{ $statusLabel }}</span>
                                @if($patient->has_pending_referral)
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">Pending Referral</span>
                                @endif
                            </div>

                        </div>


                        <div class="mt-4 flex items-end justify-between gap-4">

                            <div>
                                <p class="text-xs font-medium text-gray-500">
                                    Latest Activity
                                </p>

                                <p class="mt-1 text-sm text-gray-700">
                                    {{ $patient->directory_activity?->format('M d, Y') ?? 'Not recorded' }}
                                </p>
                            </div>


                            <a
                                href="{{ route('view-all-records.history', $patient) }}"
                                title="View patient history"
                                aria-label="View patient history for {{ $patient->first_name }} {{ $patient->last_name }}"
                                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg
                                       text-gray-500 transition
                                       hover:bg-green-50 hover:text-green-700
                                       focus:outline-none focus:ring-2 focus:ring-green-500"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.8"
                                        d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12
                                           18 18.75 12 18.75 2.25 12 2.25 12z"
                                    />

                                    <circle
                                        cx="12"
                                        cy="12"
                                        r="2.75"
                                        stroke-width="1.8"
                                    />
                                </svg>
                            </a>

                        </div>

                    </div>

                @empty

                    <div class="px-5 py-12 text-center">
                        <p class="text-sm font-medium text-gray-700">
                            No patient records found
                        </p>

                        <p class="mt-1 text-xs text-gray-500">
                            Try adjusting the search or status filter.
                        </p>
                    </div>

                @endforelse

            </div>


            
{{-- =========================
     PAGINATION
========================== --}}
@if($patients->total() > 0)
    <div class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">

        <p class="text-sm text-gray-500">
            Showing
            {{ $patients->firstItem() ?? 0 }}–{{ $patients->lastItem() ?? 0 }}
            of {{ $patients->total() }}
            {{ Str::plural('record', $patients->total()) }}
        </p>

        @if($patients->hasPages())
            <nav class="flex items-center gap-1" aria-label="Pagination">

                {{-- Previous --}}
                @if($patients->onFirstPage())
                    <span class="inline-flex h-9 items-center rounded-lg border border-gray-200 px-3
                                 text-sm text-gray-300">
                        Previous
                    </span>
                @else
                    <a
                        href="{{ $patients->previousPageUrl() }}"
                        class="inline-flex h-9 items-center rounded-lg border border-gray-200 px-3
                               text-sm text-gray-600 transition hover:bg-gray-50"
                    >
                        Previous
                    </a>
                @endif


                {{-- Page Numbers --}}
                @foreach(range(1, $patients->lastPage()) as $page)

                    @if($page === $patients->currentPage())
                        <span
                            class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg
                                   bg-slate-800 px-3 text-sm font-semibold text-white"
                        >
                            {{ $page }}
                        </span>
                    @else
                        <a
                            href="{{ $patients->url($page) }}"
                            class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg
                                   border border-gray-200 px-3 text-sm text-gray-600
                                   transition hover:bg-gray-50"
                        >
                            {{ $page }}
                        </a>
                    @endif

                @endforeach


                {{-- Next --}}
                @if($patients->hasMorePages())
                    <a
                        href="{{ $patients->nextPageUrl() }}"
                        class="inline-flex h-9 items-center rounded-lg border border-gray-200 px-3
                               text-sm text-gray-600 transition hover:bg-gray-50"
                    >
                        Next
                    </a>
                @else
                    <span class="inline-flex h-9 items-center rounded-lg border border-gray-200 px-3
                                 text-sm text-gray-300">
                        Next
                    </span>
                @endif

            </nav>
        @endif

    </div>
@endif
        </div>

    </div>
    <script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('view-all-records-search');
    const searchInput = form?.querySelector('input[name="search"]');

    if (!form || !searchInput) return;

    let searchTimer;
    let lastSubmittedValue = searchInput.value.trim();

    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);

        searchTimer = setTimeout(function () {
            const currentValue = searchInput.value.trim();

            if (currentValue === lastSubmittedValue) return;

            lastSubmittedValue = currentValue;
            form.submit();
        }, 350);
    });
});
</script>
    </div>
    
</x-app-layout>