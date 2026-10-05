<x-app-layout>
    <style>
        .prenatal-responsive { container-type: inline-size; min-width: 0; overflow-wrap: anywhere; }
        .prenatal-responsive *, .prenatal-responsive *::before, .prenatal-responsive *::after { box-sizing: border-box; }
        .prenatal-responsive .grid > *, .prenatal-responsive .flex > * { min-width: 0; }
        .prenatal-responsive .app-page-header-actions { flex-wrap: wrap; }
        .prenatal-dialog > div { min-width: 0; max-height: calc(100dvh - 3rem); overflow-y: auto; margin-inline: 0; overflow-wrap: anywhere; }
        .prenatal-dialog { padding-block: 1.5rem; }
        .prenatal-dialog button { white-space: normal; }

        .prenatal-responsive .prenatal-summary { grid-template-columns: repeat(auto-fit, minmax(min(100%, 210px), 1fr)); }
        .prenatal-table {
    width: 100%;
    table-layout: auto;
}

.prenatal-table th,
.prenatal-table td {
    padding-inline: .75rem;
    vertical-align: middle;
}

.prenatal-table th {
    white-space: normal;
}

.prenatal-table td {
    overflow-wrap: break-word;
}

.prenatal-table td:first-child {
    min-width: 150px;
}

.prenatal-table td:nth-child(2) {
    min-width: 105px;
    white-space: nowrap;
}

.prenatal-table td:nth-child(3) {
    min-width: 100px;
    white-space: nowrap;
}

.prenatal-table td:nth-child(4) {
    min-width: 80px;
    white-space: nowrap;
}

.prenatal-table td:nth-child(5) {
    min-width: 105px;
}

.prenatal-table td:nth-child(6) {
    min-width: 105px;
}

.prenatal-table td:nth-child(7) {
    min-width: 110px;
}

.prenatal-table td:last-child {
    min-width: 90px;
    white-space: nowrap;
}

.prenatal-table td > .flex {
    flex-wrap: wrap;
}
.prenatal-table td:last-child > .flex {
    flex-wrap: nowrap;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
}
        /* Compact card layout when the table no longer has enough room */
@container (max-width: 1000px) {
    .prenatal-table,
    .prenatal-table tbody {
        display: block;
        width: 100%;
    }

    .prenatal-table thead {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip-path: inset(50%);
    }

    .prenatal-table tbody {
        padding: 1rem;
    }

    .prenatal-table tbody tr {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        column-gap: 1.5rem;
        row-gap: 1rem;
        padding: 1rem;
        margin-bottom: 1rem;
        border: 1px solid #e5e7eb;
        border-radius: .75rem;
    }

    .prenatal-table td {
        display: block;
        min-width: 0 !important;
        max-width: none;
        padding: 0;
        border: 0;
        text-align: left;
        white-space: normal;
    }

    .prenatal-table td::before {
        content: attr(data-label);
        display: block;
        margin-bottom: .25rem;
        font-size: .75rem;
        font-weight: 600;
        color: #657083;
    }

    /* Patient information gets the full first row */
    .prenatal-table td:first-child {
        grid-column: 1 / -1;
    }

    .prenatal-table td:first-child > div {
        min-width: 0;
    }

    .prenatal-table td:first-child p {
        max-width: 100%;
        white-space: normal;
        overflow: visible;
        text-overflow: clip;
    }

    /* Actions get the full final row */
    .prenatal-table td:last-child {
        grid-column: 1 / -1;
    }

    .prenatal-table td:last-child > .flex {
        justify-content: flex-start;
        flex-wrap: nowrap;
        gap: .75rem;
    }

    .prenatal-table td[colspan] {
        grid-column: 1 / -1;
    }

    .prenatal-table td[colspan]::before {
        content: none;
    }
}


/* Phone layout */
@container (max-width: 600px) {
    #prenatalFilterForm {
        flex-direction: column;
        align-items: stretch;
    }

    #prenatalFilterForm > div {
        width: 100%;
        max-width: 100%;
    }

    #prenatalSearchInput {
        min-width: 0;
    }

    .prenatal-table tbody {
        padding: .75rem;
    }

    .prenatal-table tbody tr {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        column-gap: 1rem;
        row-gap: 1rem;
        padding: .875rem;
        margin-bottom: .75rem;
    }

    .prenatal-table td {
        font-size: .875rem;
    }

    .prenatal-table td::before {
        margin-bottom: .2rem;
        font-size: .7rem;
    }

    .prenatal-responsive nav {
        flex-wrap: wrap;
    }

    .prenatal-responsive nav,
    .prenatal-responsive nav + * {
        max-width: 100%;
    }
}


/* Extremely narrow screens only */
@container (max-width: 300px) {
    .prenatal-table tbody tr {
        grid-template-columns: minmax(0, 1fr);
    }

    .prenatal-table td:first-child,
    .prenatal-table td:last-child,
    .prenatal-table td[colspan] {
        grid-column: 1;
    }
}
    </style>

    <div class="prenatal-responsive min-h-screen bg-[#FCFBF8]">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">

            {{-- =========================================================
                 HEADER
            ========================================================== --}}
            <x-app-header class="mb-7">
                <x-slot name="title">Prenatal Visits</x-slot>

                <x-slot name="subtitle">
                    Monitor prenatal assessments, maternal risk, and follow-up schedules.
                </x-slot>

                <x-slot name="actions">
                    <a
                        href="{{ route('prenatal-visits.archived') }}"
                        class="btn btn-secondary"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12a2 2 0 002 2h8a2 2 0 002-2L19 8m-9 4v4m4-4v4"
                            />
                        </svg>
                        Archived
                    </a>
                    <a
                        href="{{ route('prenatal-visits.create') }}"
                        class="inline-flex items-center gap-2 rounded-xl bg-[#55B85A] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#4aa04c] focus:outline-none focus:ring-2 focus:ring-[#55B85A]/30"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 4v16m8-8H4"
                            />
                        </svg>

                        Add Prenatal Visit
                    </a>
                </x-slot>
            </x-app-header>


            {{-- =========================================================
                 SUCCESS MESSAGE
            ========================================================== --}}
            @if (session('success'))
                <div
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 4000)"
                    class="mb-6 flex items-center justify-between gap-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
                >
                    <div class="flex items-center gap-2">
                        <svg
                            class="h-5 w-5 shrink-0"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.5"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M5 13l4 4L19 7"
                            />
                        </svg>

                        <span class="font-medium">
                            {{ session('success') }}
                        </span>
                    </div>

                    <button
                        type="button"
                        @click="show = false"
                        class="text-green-600 transition hover:text-green-800"
                        aria-label="Dismiss notification"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
            @endif


            {{-- =========================================================
                 SUMMARY CARDS
            ========================================================== --}}
            <div class="prenatal-summary mb-7 grid gap-4">

                {{-- Total Visits --}}
                <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Total Visits
                    </p>

                    <p class="mt-2 text-2xl font-bold text-[#19355F]">
                        {{ number_format($totalVisits) }}
                    </p>

                    <p class="mt-1 text-xs text-gray-400">
                        Ongoing patient visit records
                    </p>
                </div>


                {{-- High Risk --}}
                <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        High-Risk Patients
                    </p>

                    <p class="mt-2 text-2xl font-bold text-red-600">
                        {{ number_format($highRiskPatients) }}
                    </p>

                    <p class="mt-1 text-xs text-gray-400">
                        Latest assessment is high risk
                    </p>
                </div>


                {{-- Follow-Ups Due --}}
                <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Follow-Ups Due
                    </p>

                    <p class="mt-2 text-2xl font-bold {{ $followUpsDue > 0 ? 'text-amber-600' : 'text-[#19355F]' }}">
                        {{ number_format($followUpsDue) }}
                    </p>

                    <p class="mt-1 text-xs text-gray-400">
                        Due today or overdue
                    </p>
                </div>


                {{-- This Month --}}
                <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Visits This Month
                    </p>

                    <p class="mt-2 text-2xl font-bold text-[#19355F]">
                        {{ number_format($visitsThisMonth) }}
                    </p>

                    <p class="mt-1 text-xs text-gray-400">
                        Recorded this month
                    </p>
                </div>

            </div>


            {{-- =========================================================
                 TABLE CARD
            ========================================================== --}}
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

                {{-- Search + Assessment Filter --}}
                <div class="border-b border-gray-100 px-5 py-4">

                    <form
                        id="prenatalFilterForm"
                        method="GET"
                        action="{{ route('prenatal-visits.index') }}"
                        class="flex flex-col gap-3 md:flex-row md:items-center"
                    >

                        {{-- Live Search --}}
                        <div class="relative min-w-0 flex-1">

                            <svg
                                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                />
                            </svg>

                            <input
                                id="prenatalSearchInput"
                                type="search"
                                name="search"
                                value="{{ $search }}"
                                maxlength="100"
                                autocomplete="off"
                                placeholder="Search by patient name or Patient ID..."
                                class="w-full rounded-lg border border-gray-200 bg-white py-2.5 pl-9 pr-10 text-sm text-gray-700 outline-none transition placeholder:text-gray-400 focus:border-[#19355F]/40 focus:ring-2 focus:ring-[#19355F]/10"
                            >

                            @if ($search)
                                <a
                                    href="{{ route('prenatal-visits.index', ['risk' => $risk ?: null]) }}"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 rounded p-0.5 text-gray-400 transition hover:text-gray-700"
                                    title="Clear search"
                                    aria-label="Clear search"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M6 18L18 6M6 6l12 12"
                                        />
                                    </svg>
                                </a>
                            @endif

                        </div>


                        {{-- Assessment Filter --}}
                        <div class="md:w-56">
                            <select
                                id="riskFilter"
                                name="risk"
                                class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-700 outline-none transition focus:border-[#19355F]/40 focus:ring-2 focus:ring-[#19355F]/10"
                            >
                                <option value="" @selected($risk === '')>
                                    All Assessments
                                </option>

                                <option value="HIGH" @selected($risk === 'HIGH')>
                                    High Risk
                                </option>

                                <option value="LOW" @selected($risk === 'LOW')>
                                    Low Risk
                                </option>

                                <option
                                    value="ASSESSMENT INCOMPLETE"
                                    @selected($risk === 'ASSESSMENT INCOMPLETE')
                                >
                                    Assessment Incomplete
                                </option>

                                <option value="PENDING" @selected($risk === 'PENDING')>
                                    Pending
                                </option>
                            </select>
                        </div>


                        {{-- Result Count --}}
                        <div class="shrink-0 whitespace-nowrap text-sm text-gray-400">
                            <span class="font-medium text-gray-600">
                                {{ number_format($visits->total()) }}
                            </span>

                            {{ Str::plural('patient', $visits->total()) }}
                        </div>

                    </form>

                </div>


                {{-- =====================================================
                     VISITS TABLE
                ====================================================== --}}
                <div class="overflow-x-auto">

                    <table class="prenatal-table w-full text-sm">

                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/80">

                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Patient
                                </th>

                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Visit Date
                                </th>

                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Blood Pressure</th>

                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Weight
                                </th>

                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Gestational Age</th>

                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Assessment
                                </th>

                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Next Visit
                                </th>

                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    Actions
                                </th>

                            </tr>
                        </thead>


                        <tbody class="divide-y divide-gray-100">

                            @forelse ($visits as $visit)

                                @php
                                    $patient = $visit->patient;

                                    $fullName = trim(
                                        $patient->first_name . ' ' .
                                        $patient->middle_name . ' ' .
                                        $patient->last_name
                                    );

                                    $patientId = 'PT-' . str_pad(
                                        $patient->id,
                                        4,
                                        '0',
                                        STR_PAD_LEFT
                                    );

                                    $nextVisit = $visit->next_visit_date
                                        ? \Carbon\Carbon::parse($visit->next_visit_date)->startOfDay()
                                        : null;

                                    $today = today();

                                    $isOverdue = $nextVisit && $nextVisit->lt($today);
                                    $isDueToday = $nextVisit && $nextVisit->isSameDay($today);

                                    $riskLevel = strtoupper(
                                        $visit->risk_level ?? 'PENDING'
                                    );
                                @endphp


                                <tr class="transition hover:bg-gray-50/60">

                                    {{-- Patient --}}
                                    <td data-label="Patient" class="px-5 py-4">

                                        <div class="min-w-[180px]">

                                            <p class="max-w-[220px] truncate font-semibold text-gray-900">
                                                {{ $fullName }}
                                            </p>

                                            <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-gray-400">

                                                <span class="font-medium text-[#19355F]/70">
                                                    {{ $patientId }}
                                                </span>

                                                <span class="text-gray-300">•</span>

                                                <span>
                                                    Age {{ $patient->age }}
                                                </span>

                                                <span class="text-gray-300">•</span>

                                                <span>
                                                    G{{ $patient->gravida ?? 0 }}
                                                    P{{ $patient->para ?? 0 }}
                                                </span>

                                            </div>

                                        </div>

                                    </td>


                                    {{-- Visit Date --}}
                                    <td data-label="Visit Date" class="whitespace-nowrap px-5 py-4 text-gray-700">
                                        {{ $visit->visit_date
                                            ? \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y')
                                            : '—' }}
                                    </td>


                                    {{-- BP --}}
                                    <td data-label="Blood Pressure" class="whitespace-nowrap px-5 py-4">

                                        @if (!is_null($visit->bp_sys) && !is_null($visit->bp_dia))

                                            <span
                                                class="font-medium
                                                {{ $visit->bp_sys >= 140 || $visit->bp_dia >= 90
                                                    ? 'text-red-600'
                                                    : 'text-gray-700' }}"
                                            >
                                                {{ $visit->bp_sys }}/{{ $visit->bp_dia }}
                                            </span>

                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif

                                    </td>


                                    {{-- Weight --}}
                                    <td data-label="Weight" class="whitespace-nowrap px-5 py-4 text-gray-700">
                                        {{ \App\Support\WeightFormatter::formatKg($visit->weight) !== null
                                            ? \App\Support\WeightFormatter::formatKg($visit->weight) . ' kg'
                                            : '—' }}
                                    </td>


                                    {{-- Gestational Age --}}
                                    <td data-label="Gestational Age" class="whitespace-nowrap px-5 py-4 text-gray-700">
                                        {{ !is_null($visit->gestational_age)
                                            ? $visit->gestational_age . ' weeks'
                                            : '—' }}
                                    </td>


                                    {{-- Assessment --}}
                                    <td data-label="Assessment" class="whitespace-nowrap px-5 py-4">

                                        @if ($riskLevel === 'HIGH')

                                            <span class="inline-flex rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-600">
                                                HIGH
                                            </span>

                                        @elseif ($riskLevel === 'LOW')

                                            <span class="inline-flex rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700">
                                                LOW
                                            </span>

                                        @elseif ($riskLevel === 'ASSESSMENT INCOMPLETE')

                                            <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                                INCOMPLETE
                                            </span>

                                        @else

                                            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500">
                                                {{ $riskLevel }}
                                            </span>

                                        @endif

                                    </td>


                                    {{-- Next Visit --}}
                                    <td data-label="Next Visit" class="whitespace-nowrap px-5 py-4">

                                        @if ($nextVisit)

                                            <div>
                                                <p class="text-gray-700">
                                                    {{ $nextVisit->format('M d, Y') }}
                                                </p>

                                                @if ($isOverdue)

                                                    <p class="mt-0.5 text-xs font-semibold text-red-600">
                                                        Overdue
                                                    </p>

                                                @elseif ($isDueToday)

                                                    <p class="mt-0.5 text-xs font-semibold text-amber-600">
                                                        Due today
                                                    </p>

                                                @endif
                                            </div>

                                        @else

                                            <span class="text-gray-400">
                                                —
                                            </span>

                                        @endif

                                    </td>


                                    {{-- Actions --}}
                                    <td data-label="Actions" class="whitespace-nowrap px-5 py-4 text-right">

                                        <x-action-buttons
                                            :viewRoute="route('patients.show', [
                                                'patient' => $visit->patient_id,
                                                'from' => 'prenatal-visits'
                                            ])"
                                            :archiveRoute="route('prenatal-visits.destroy', $visit->id)"
                                        />

                                    </td>

                                </tr>


                            @empty

                                <tr>
                                    <td
                                        colspan="8"
                                        class="px-6 py-14 text-center"
                                    >

                                        <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400">

                                            <svg
                                                class="h-5 w-5"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                                />
                                            </svg>

                                        </div>


                                        @if ($search)

                                            <p class="mt-3 font-semibold text-gray-700">
                                                No visits found
                                            </p>

                                            <p class="mt-1 text-sm text-gray-400">
                                                No prenatal visits matched “{{ $search }}”.
                                            </p>

                                            <a
                                                href="{{ route('prenatal-visits.index', ['risk' => $risk ?: null]) }}"
                                                class="mt-3 inline-flex text-sm font-semibold text-[#19355F] hover:underline"
                                            >
                                                Clear search
                                            </a>

                                        @elseif ($risk)

                                            <p class="mt-3 font-semibold text-gray-700">
                                                No matching assessments
                                            </p>

                                            <p class="mt-1 text-sm text-gray-400">
                                                No prenatal visits match the selected assessment.
                                            </p>

                                            <a
                                                href="{{ route('prenatal-visits.index') }}"
                                                class="mt-3 inline-flex text-sm font-semibold text-[#19355F] hover:underline"
                                            >
                                                Clear filter
                                            </a>

                                        @else

                                            <p class="mt-3 font-semibold text-gray-700">
                                                No prenatal visits recorded
                                            </p>

                                            <p class="mt-1 text-sm text-gray-400">
                                                Prenatal visit records will appear here.
                                            </p>

                                            <a
                                                href="{{ route('prenatal-visits.create') }}"
                                                class="mt-3 inline-flex text-sm font-semibold text-[#55B85A] hover:underline"
                                            >
                                                Add first visit
                                            </a>

                                        @endif

                                    </td>
                                </tr>

                            @endforelse

                        </tbody>

                    </table>

                </div>


                {{-- =====================================================
                     PAGINATION
                ====================================================== --}}
                @if ($visits->hasPages())

                    <div class="flex flex-col gap-4 border-t border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">

                        <p class="text-sm text-gray-500">
                            Showing
                            <span class="font-medium text-gray-700">
                                {{ $visits->firstItem() }}
                            </span>
                            –
                            <span class="font-medium text-gray-700">
                                {{ $visits->lastItem() }}
                            </span>
                            of
                            <span class="font-medium text-gray-700">
                                {{ $visits->total() }}
                            </span>
                            patients
                        </p>


                        <nav
                            class="flex flex-wrap items-center gap-1"
                            aria-label="Prenatal visit pagination"
                        >

                            {{-- Previous --}}
                            @if ($visits->onFirstPage())

                                <span class="inline-flex h-9 items-center rounded-lg border border-gray-200 px-3 text-sm font-medium text-gray-300">
                                    Previous
                                </span>

                            @else

                                <a
                                    href="{{ $visits->previousPageUrl() }}"
                                    class="inline-flex h-9 items-center rounded-lg border border-gray-200 bg-white px-3 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                                >
                                    Previous
                                </a>

                            @endif


                            {{-- Page Numbers --}}
                            @foreach (
                                $visits->getUrlRange(
                                    max(1, $visits->currentPage() - 2),
                                    min($visits->lastPage(), $visits->currentPage() + 2)
                                )
                                as $page => $url
                            )

                                @if ($page == $visits->currentPage())

                                    <span
                                        aria-current="page"
                                        class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg bg-[#19355F] px-3 text-sm font-semibold text-white"
                                    >
                                        {{ $page }}
                                    </span>

                                @else

                                    <a
                                        href="{{ $url }}"
                                        class="inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-gray-200 bg-white px-3 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                                    >
                                        {{ $page }}
                                    </a>

                                @endif

                            @endforeach


                            {{-- Next --}}
                            @if ($visits->hasMorePages())

                                <a
                                    href="{{ $visits->nextPageUrl() }}"
                                    class="inline-flex h-9 items-center rounded-lg border border-gray-200 bg-white px-3 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                                >
                                    Next
                                </a>

                            @else

                                <span class="inline-flex h-9 items-center rounded-lg border border-gray-200 px-3 text-sm font-medium text-gray-300">
                                    Next
                                </span>

                            @endif

                        </nav>

                    </div>

                @elseif ($visits->total() > 0)

                    <div class="border-t border-gray-100 px-5 py-4">

                        <p class="text-sm text-gray-500">
                            Showing all
                            <span class="font-medium text-gray-700">
                                {{ $visits->total() }}
                            </span>
                            {{ Str::plural('patient', $visits->total()) }}
                        </p>

                    </div>

                @endif

            </div>

        </div>
    </div>


    <script>
        const prenatalSearchInput =
            document.getElementById('prenatalSearchInput');

        const prenatalFilterForm =
            document.getElementById('prenatalFilterForm');

        const riskFilter =
            document.getElementById('riskFilter');


        /*
        |--------------------------------------------------------------------------
        | Live Search
        |--------------------------------------------------------------------------
        |
        | Wait 350ms after the user stops typing before submitting.
        | This prevents a request for every single keystroke.
        |
        */
        if (prenatalSearchInput && prenatalFilterForm) {
            let searchTimer;

            prenatalSearchInput.addEventListener('input', function () {
                clearTimeout(searchTimer);

                searchTimer = setTimeout(function () {
                    prenatalFilterForm.requestSubmit();
                }, 350);
            });
        }


        /*
        |--------------------------------------------------------------------------
        | Assessment Filter
        |--------------------------------------------------------------------------
        |
        | Filter immediately when the selected assessment changes.
        |
        */
        if (riskFilter && prenatalFilterForm) {
            riskFilter.addEventListener('change', function () {
                prenatalFilterForm.requestSubmit();
            });
        }
    </script>

    <div id="archiveVisitModal" class="prenatal-dialog fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
            <div class="flex items-start gap-4">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Archive Prenatal Visit?</h3>
                    <p class="mt-1 text-sm leading-6 text-gray-500">
                        This prenatal visit will be moved to Archived Prenatal Visits and can be restored later.
                    </p>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeArchiveVisitModal()" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50">
                    Cancel
                </button>
                <button type="button" id="confirmArchiveVisitButton" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700">
                    Archive Visit
                </button>
            </div>
        </div>
    </div>

    <script>
        let pendingArchiveVisitForm = null;

        function confirmArchiveVisit(button) {
            pendingArchiveVisitForm = button.closest('form');
            const modal = document.getElementById('archiveVisitModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        function closeArchiveVisitModal() {
            const modal = document.getElementById('archiveVisitModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            pendingArchiveVisitForm = null;
            document.body.style.overflow = '';
        }

        document.getElementById('confirmArchiveVisitButton')?.addEventListener('click', function () {
            if (pendingArchiveVisitForm) {
                pendingArchiveVisitForm.submit();
            }
        });

        document.getElementById('archiveVisitModal')?.addEventListener('click', function (event) {
            if (event.target === this) {
                closeArchiveVisitModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeArchiveVisitModal();
            }
        });
    </script>

</x-app-layout>