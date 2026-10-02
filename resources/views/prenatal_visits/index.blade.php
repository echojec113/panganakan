<x-app-layout>

    <div class="min-h-screen bg-[#FCFBF8]">
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
            <div class="mb-7 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">

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
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4"
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
                        <div class="w-full sm:w-auto sm:min-w-[180px] shrink-0">
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
                        <div class="shrink-0 whitespace-nowrap text-sm text-gray-400 sm:order-last">
                            <span class="font-medium text-gray-600">
                                {{ number_format($visits->total()) }}
                            </span>

                            {{ Str::plural('visit', $visits->total()) }}
                        </div>

                    </form>

</div>


                {{-- =====================================================
                     VISITS TABLE (Desktop) / CARDS (Mobile/Tablet)
                 ====================================================== --}}

                {{-- Mobile/Tablet Cards: hidden on lg (1024px+) --}}
                <div class="lg:hidden">
                    <div class="divide-y divide-gray-100">

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

                            <div class="p-4 bg-white transition hover:bg-gray-50/60">
                                {{-- Patient Header --}}
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-900 truncate">{{ $fullName }}</p>
                                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-gray-500">
                                            <span class="font-medium text-[#19355F]/70">{{ $patientId }}</span>
                                            <span class="text-gray-300">•</span>
                                            <span>Age {{ $patient->age }}</span>
                                            <span class="text-gray-300">•</span>
                                            <span>G{{ $patient->gravida ?? 0 }} P{{ $patient->para ?? 0 }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        @if ($riskLevel === 'HIGH')
                                            <span class="inline-flex rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-600 whitespace-nowrap">HIGH</span>
                                        @elseif ($riskLevel === 'LOW')
                                            <span class="inline-flex rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700 whitespace-nowrap">LOW</span>
                                        @elseif ($riskLevel === 'ASSESSMENT INCOMPLETE')
                                            <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 whitespace-nowrap">INCOMPLETE</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500 whitespace-nowrap">{{ $riskLevel }}</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Details Grid --}}
                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div>
                                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Visit Date</p>
                                        <p class="text-gray-700">{{ $visit->visit_date ? \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') : '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Blood Pressure</p>
                                        <p class="font-medium {{ !is_null($visit->bp_sys) && !is_null($visit->bp_dia) && ($visit->bp_sys >= 140 || $visit->bp_dia >= 90) ? 'text-red-600' : 'text-gray-700' }}">
                                            {{ !is_null($visit->bp_sys) && !is_null($visit->bp_dia) ? $visit->bp_sys . '/' . $visit->bp_dia : '—' }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Weight</p>
                                        <p class="text-gray-700">{{ !is_null($visit->weight) ? rtrim(rtrim(number_format((float) $visit->weight, 2, '.', ''), '0'), '.') . ' kg' : '—' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Gestational Age</p>
                                        <p class="text-gray-700">{{ !is_null($visit->gestational_age) ? $visit->gestational_age . ' weeks' : '—' }}</p>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <p class="text-xs font-medium text-gray-500 uppercase tracking-wider">Next Visit</p>
                                        @if ($nextVisit)
                                            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                                                <p class="text-gray-700">{{ $nextVisit->format('M d, Y') }}</p>
                                                @if ($isOverdue)
                                                    <span class="inline-flex rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-600">Overdue</span>
                                                @elseif ($isDueToday)
                                                    <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-600">Due today</span>
                                                @endif
                                            </div>
                                        @else
                                            <p class="text-gray-400">—</p>
                                        @endif
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="mt-4 pt-3 border-t border-gray-100 flex justify-end">
                                    <x-action-buttons
                                        :viewRoute="route('patients.show', [
                                            'patient' => $visit->patient_id,
                                            'from' => 'prenatal-visits'
                                        ])"
                                        :editRoute="route('prenatal-visits.edit', $visit->id)"
                                        :deleteRoute="route('prenatal-visits.destroy', $visit->id)"
                                    />
                                </div>
                            </div>

                        @empty

                            {{-- Empty State for Cards --}}
                            <div class="px-6 py-14 text-center">
                                <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>

                                @if ($search)
                                    <p class="mt-3 font-semibold text-gray-700">No visits found</p>
                                    <p class="mt-1 text-sm text-gray-400">No prenatal visits matched “{{ $search }}”.</p>
                                    <a href="{{ route('prenatal-visits.index', ['risk' => $risk ?: null]) }}" class="mt-3 inline-flex text-sm font-semibold text-[#19355F] hover:underline">Clear search</a>
                                @elseif ($risk)
                                    <p class="mt-3 font-semibold text-gray-700">No matching assessments</p>
                                    <p class="mt-1 text-sm text-gray-400">No prenatal visits match the selected assessment.</p>
                                    <a href="{{ route('prenatal-visits.index') }}" class="mt-3 inline-flex text-sm font-semibold text-[#19355F] hover:underline">Clear filter</a>
                                @else
                                    <p class="mt-3 font-semibold text-gray-700">No prenatal visits recorded</p>
                                    <p class="mt-1 text-sm text-gray-400">Prenatal visit records will appear here.</p>
                                    <a href="{{ route('prenatal-visits.create') }}" class="mt-3 inline-flex text-sm font-semibold text-[#55B85A] hover:underline">Add first visit</a>
                                @endif
                            </div>

                        @endforelse
                    </div>
                </div>

                {{-- Desktop Table: hidden below lg (1024px) --}}
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/80">
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Patient</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Visit Date</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Blood Pressure</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Weight</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Gestational Age</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Assessment</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Next Visit</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($visits as $visit)
                                @php
                                    $patient = $visit->patient;
                                    $fullName = trim($patient->first_name . ' ' . $patient->middle_name . ' ' . $patient->last_name);
                                    $patientId = 'PT-' . str_pad($patient->id, 4, '0', STR_PAD_LEFT);
                                    $nextVisit = $visit->next_visit_date ? \Carbon\Carbon::parse($visit->next_visit_date)->startOfDay() : null;
                                    $today = today();
                                    $isOverdue = $nextVisit && $nextVisit->lt($today);
                                    $isDueToday = $nextVisit && $nextVisit->isSameDay($today);
                                    $riskLevel = strtoupper($visit->risk_level ?? 'PENDING');
                                @endphp

                                <tr class="transition hover:bg-gray-50/60">
                                    {{-- Patient --}}
                                    <td class="px-5 py-4">
                                        <div class="min-w-[180px]">
                                            <p class="max-w-[220px] truncate font-semibold text-gray-900">{{ $fullName }}</p>
                                            <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-gray-400">
                                                <span class="font-medium text-[#19355F]/70">{{ $patientId }}</span>
                                                <span class="text-gray-300">•</span>
                                                <span>Age {{ $patient->age }}</span>
                                                <span class="text-gray-300">•</span>
                                                <span>G{{ $patient->gravida ?? 0 }} P{{ $patient->para ?? 0 }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    {{-- Visit Date --}}
                                    <td class="whitespace-nowrap px-5 py-4 text-gray-700">
                                        {{ $visit->visit_date ? \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') : '—' }}
                                    </td>

                                    {{-- Blood Pressure --}}
                                    <td class="whitespace-nowrap px-5 py-4">
                                        @if (!is_null($visit->bp_sys) && !is_null($visit->bp_dia))
                                            <span class="font-medium {{ $visit->bp_sys >= 140 || $visit->bp_dia >= 90 ? 'text-red-600' : 'text-gray-700' }}">
                                                {{ $visit->bp_sys }}/{{ $visit->bp_dia }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>

                                    {{-- Weight --}}
                                    <td class="whitespace-nowrap px-5 py-4 text-gray-700">
                                        {{ !is_null($visit->weight) ? rtrim(rtrim(number_format((float) $visit->weight, 2, '.', ''), '0'), '.') . ' kg' : '—' }}
                                    </td>

                                    {{-- Gestational Age --}}
                                    <td class="whitespace-nowrap px-5 py-4 text-gray-700">
                                        {{ !is_null($visit->gestational_age) ? $visit->gestational_age . ' weeks' : '—' }}
                                    </td>

                                    {{-- Assessment --}}
                                    <td class="whitespace-nowrap px-5 py-4">
                                        @if ($riskLevel === 'HIGH')
                                            <span class="inline-flex rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-600">HIGH</span>
                                        @elseif ($riskLevel === 'LOW')
                                            <span class="inline-flex rounded-full bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-700">LOW</span>
                                        @elseif ($riskLevel === 'ASSESSMENT INCOMPLETE')
                                            <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">INCOMPLETE</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500">{{ $riskLevel }}</span>
                                        @endif
                                    </td>

                                    {{-- Next Visit --}}
                                    <td class="whitespace-nowrap px-5 py-4">
                                        @if ($nextVisit)
                                            <div>
                                                <p class="text-gray-700">{{ $nextVisit->format('M d, Y') }}</p>
                                                @if ($isOverdue)
                                                    <p class="mt-0.5 text-xs font-semibold text-red-600">Overdue</p>
                                                @elseif ($isDueToday)
                                                    <p class="mt-0.5 text-xs font-semibold text-amber-600">Due today</p>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>

                                    {{-- Actions --}}
                                    <td class="whitespace-nowrap px-5 py-4 text-right">
                                        <x-action-buttons
                                            :viewRoute="route('patients.show', ['patient' => $visit->patient_id, 'from' => 'prenatal-visits'])"
                                            :editRoute="route('prenatal-visits.edit', $visit->id)"
                                            :deleteRoute="route('prenatal-visits.destroy', $visit->id)"
                                        />
                                    </td>
                                </tr>

                            @empty
                                {{-- Empty state for table is handled by cards above, but keep a minimal fallback --}}
                                <tr>
                                    <td colspan="8" class="px-6 py-14 text-center">
                                        <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
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

                    <div class="flex flex-col gap-3 border-t border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                        {{-- Page Info --}}
                        <p class="text-sm text-gray-500 whitespace-nowrap sm:whitespace-normal">
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
                            {{ Str::plural('visit', $visits->total()) }}
                        </p>

                        {{-- Pagination Controls --}}
                        <nav
                            class="flex items-center gap-1 flex-wrap justify-center sm:justify-end"
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
                            {{ Str::plural('visit', $visits->total()) }}
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

</x-app-layout>