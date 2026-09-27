<x-app-layout>

<div class="min-h-screen bg-[#FCFBF8]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        {{-- =========================================================
             HEADER
        ========================================================== --}}
        <x-app-header class="mb-7">
            <x-slot name="title">Patient Records</x-slot>

            <x-slot name="subtitle">
                Manage ongoing patient records, assignments, and maternal risk status.
            </x-slot>

            <x-slot name="actions">

                <a
                    href="{{ route('patients.trashed') }}"
                    class="btn btn-secondary"
                >
                    <svg
                        class="w-4 h-4"
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
                    href="{{ route('patients.create') }}"
                    class="inline-flex items-center gap-2 rounded-xl bg-[#55B85A] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#4aa04c] focus:outline-none focus:ring-2 focus:ring-[#55B85A]/30"
                >
                    <svg
                        class="w-4 h-4"
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

                    Add New Patient
                </a>

            </x-slot>
        </x-app-header>


        {{-- =========================================================
             SUCCESS MESSAGE
             One notification only. No success modal.
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
                    aria-label="Dismiss"
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
             STATISTICS
        ========================================================== --}}
        <div class="grid grid-cols-1 gap-4 mb-7 sm:grid-cols-3">

            {{-- Ongoing Patients --}}
            <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">

                <div class="flex items-start justify-between gap-4">

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Ongoing Patients
                        </p>

                        <p class="mt-2 text-2xl font-bold text-[#19355F]">
                            {{ $totalPatients }}
                        </p>

                        <p class="mt-1 text-xs text-gray-400">
                            Active pregnancy records
                        </p>
                    </div>

                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-50 text-[#19355F]">
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
                                d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.003A9.353 9.353 0 0110.5 20.25c-2.1 0-4.04-.69-5.604-1.857l-.003-.002a4.125 4.125 0 017.533-2.493M12 6.75a3 3 0 11-6 0 3 3 0 016 0zm8.25 2.25a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"
                            />
                        </svg>
                    </div>

                </div>

            </div>


            {{-- High Risk --}}
            <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">

                <div class="flex items-start justify-between gap-4">

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                            High-Risk Cases
                        </p>

                        <p class="mt-2 text-2xl font-bold text-red-600">
                            {{ $highRiskCount }}
                        </p>

                        <p class="mt-1 text-xs text-gray-400">
                            Latest assessment is high risk
                        </p>
                    </div>

                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-600">
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
                                d="M12 9v3.75m9.303 3.376c.866 1.5-.217 3.374-1.948 3.374H4.645c-1.73 0-2.813-1.874-1.948-3.374L10.052 3.38c.866-1.5 3.03-1.5 3.896 0l7.355 12.746zM12 15.75h.008v.008H12v-.008z"
                            />
                        </svg>
                    </div>

                </div>

            </div>


            {{-- My Patients --}}
            <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">

                <div class="flex items-start justify-between gap-4">

                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                            My Patients
                        </p>

                        <p class="mt-2 text-2xl font-bold text-[#19355F]">
                            {{ $myPatientsCount }}
                        </p>

                        <p class="mt-1 text-xs text-gray-400">
                            Assigned to your account
                        </p>
                    </div>

                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-50 text-[#19355F]">
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
                                d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"
                            />
                        </svg>
                    </div>

                </div>

            </div>

        </div>


        {{-- =========================================================
             FILTER
        ========================================================== --}}
        <div class="mb-4">

            <div class="inline-flex rounded-lg border border-gray-200 bg-white p-1">

                <a
                    href="{{ route('patients.index', ['filter' => 'all', 'search' => $search ?: null]) }}"
                    class="rounded-md px-4 py-2 text-sm font-medium transition
                    {{ $filter === 'all'
                        ? 'bg-slate-100 text-[#19355F] shadow-sm'
                        : 'text-gray-500 hover:bg-gray-50 hover:text-[#19355F]' }}"
                >
                    All Patients
                </a>

                <a
                    href="{{ route('patients.index', ['filter' => 'my', 'search' => $search ?: null]) }}"
                    class="rounded-md px-4 py-2 text-sm font-medium transition
                    {{ $filter === 'my'
                        ? 'bg-slate-100 text-[#19355F] shadow-sm'
                        : 'text-gray-500 hover:bg-gray-50 hover:text-[#19355F]' }}"
                >
                    My Patients
                </a>

            </div>

        </div>


        {{-- =========================================================
             PATIENT TABLE CARD
        ========================================================== --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

            {{-- Search --}}
<div class="border-b border-gray-100 px-5 py-4">

    <form
        id="patientSearchForm"
        method="GET"
        action="{{ route('patients.index') }}"
        class="flex items-center gap-4"
    >
        <input
            type="hidden"
            name="filter"
            value="{{ $filter }}"
        >

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
                id="patientSearchInput"
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
                    href="{{ route('patients.index', ['filter' => $filter]) }}"
                    class="absolute right-3 top-1/2 -translate-y-1/2 rounded p-0.5 text-gray-400 transition hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#19355F]/20"
                    title="Clear search"
                    aria-label="Clear patient search"
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

        <div class="shrink-0 whitespace-nowrap text-sm text-gray-400">
            <span class="font-medium text-gray-600">
                {{ number_format($patients->total()) }}
            </span>
            {{ Str::plural('patient', $patients->total()) }}
        </div>

    </form>

    @error('search')
        <p class="mt-2 text-sm text-red-600">
            {{ $message }}
        </p>
    @enderror

</div>


            {{-- TABLE --}}
            <div class="overflow-x-auto">

                <table class="w-full text-sm">

                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/80">

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Patient
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Age
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Contact
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                G / P
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                EDD
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                PhilHealth
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Assigned Staff
                            </th>

                            <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Actions
                            </th>

                        </tr>
                    </thead>


                    <tbody class="divide-y divide-gray-100">

                        @forelse ($patients as $patient)

                            @php
                                $firstName = trim((string) $patient->first_name);
                                $lastName = trim((string) $patient->last_name);

                                

                                $fullName = trim(
                                    $patient->first_name . ' ' .
                                    $patient->middle_name . ' ' .
                                    $patient->last_name
                                );
                            @endphp


                            <tr class="transition hover:bg-gray-50/60">

                                {{-- Patient --}}
                                <td class="px-5 py-4">

                                    <div class="min-w-0">

    <p class="max-w-[220px] truncate font-semibold text-gray-900">
        {{ $fullName }}
    </p>

    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-gray-400">

        <span class="font-medium text-[#19355F]/70">
            Patient ID: PT-{{ str_pad($patient->id, 4, '0', STR_PAD_LEFT) }}
        </span>

        <span class="text-gray-300">•</span>

        <span>
            {{ $patient->civil_status ?: '—' }}
        </span>

    </div>

</div>

                                </td>


                                {{-- Age --}}
                                <td class="whitespace-nowrap px-5 py-4 text-gray-700">
                                    {{ $patient->age }} yrs
                                </td>


                                {{-- Contact --}}
                                <td class="whitespace-nowrap px-5 py-4 text-gray-700">
                                    {{ $patient->contact_number ?: '—' }}
                                </td>


                                {{-- G / P --}}
                                <td class="whitespace-nowrap px-5 py-4">

                                    <span class="font-semibold text-gray-700">
                                        G{{ $patient->gravida ?? 0 }}
                                    </span>

                                    <span class="mx-1 text-gray-300">/</span>

                                    <span class="font-semibold text-[#239447]">
                                        P{{ $patient->para ?? 0 }}
                                    </span>

                                </td>


                                {{-- EDD --}}
                                <td class="whitespace-nowrap px-5 py-4 text-gray-700">
                                    {{ $patient->edd
                                        ? \Carbon\Carbon::parse($patient->edd)->format('M d, Y')
                                        : '—' }}
                                </td>


                                {{-- PhilHealth --}}
                                <td class="whitespace-nowrap px-5 py-4">

                                    @if ($patient->philhealth_member)

                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700">

                                            <svg
                                                class="h-3.5 w-3.5"
                                                fill="currentColor"
                                                viewBox="0 0 20 20"
                                            >
                                                <path
                                                    fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.707a1 1 0 00-1.414-1.414L9 10.172 7.707 8.879a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                    clip-rule="evenodd"
                                                />
                                            </svg>

                                            Member
                                        </span>

                                    @else

                                        <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500">
                                            None
                                        </span>

                                    @endif

                                </td>


                                {{-- Staff --}}
                                <td class="whitespace-nowrap px-5 py-4">

                                    @if ($patient->assignedStaff)

                                        <span class="text-gray-700">
                                            {{ $patient->assignedStaff->name }}
                                        </span>

                                    @else

                                        <span class="text-gray-400">
                                            —
                                        </span>

                                    @endif

                                </td>


                                {{-- Actions --}}
                                <td class="whitespace-nowrap px-5 py-4">

                                    <div class="flex items-center justify-center gap-1">

                                        {{-- View --}}
                                        <a
                                            href="{{ route('patients.show', $patient->id) }}"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-blue-600 transition hover:bg-blue-50"
                                            title="View patient"
                                            aria-label="View {{ $fullName }}"
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
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                                                />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        </a>


                                        {{-- Edit --}}
                                        <a
                                            href="{{ route('patients.edit', $patient->id) }}"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-teal-600 transition hover:bg-teal-50"
                                            title="Edit patient"
                                            aria-label="Edit {{ $fullName }}"
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
                                                    d="M16.862 3.487a2.25 2.25 0 113.182 3.182L8.25 18.463 4 20l1.537-4.25L16.862 3.487z"
                                                />
                                            </svg>
                                        </a>


                                        {{-- Archive/Delete --}}
                                        <button
                                            type="button"
                                            onclick="openDeleteModal(
                                                {{ $patient->id }},
                                                @js($fullName)
                                            )"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50"
                                            title="Archive patient"
                                            aria-label="Archive {{ $fullName }}"
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
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"
                                                />
                                            </svg>
                                        </button>

                                    </div>

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
                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                            />
                                        </svg>

                                    </div>


                                    @if ($search)

                                        <p class="mt-3 font-semibold text-gray-700">
                                            No patients found
                                        </p>

                                        <p class="mt-1 text-sm text-gray-400">
                                            No records matched “{{ $search }}”.
                                        </p>

                                        <a
                                            href="{{ route('patients.index', ['filter' => $filter]) }}"
                                            class="mt-3 inline-flex text-sm font-semibold text-[#19355F] hover:underline"
                                        >
                                            Clear search
                                        </a>

                                    @elseif ($filter === 'my')

                                        <p class="mt-3 font-semibold text-gray-700">
                                            No patients assigned to you
                                        </p>

                                        <p class="mt-1 text-sm text-gray-400">
                                            Your assigned patients will appear here.
                                        </p>

                                    @else

                                        <p class="mt-3 font-semibold text-gray-700">
                                            No ongoing patients
                                        </p>

                                        <p class="mt-1 text-sm text-gray-400">
                                            Add a patient to begin creating patient records.
                                        </p>

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
            @if ($patients->hasPages())

                <div class="flex flex-col gap-4 border-t border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">

                    <p class="text-sm text-gray-500">
                        Showing
                        <span class="font-medium text-gray-700">
                            {{ $patients->firstItem() }}
                        </span>
                        –
                        <span class="font-medium text-gray-700">
                            {{ $patients->lastItem() }}
                        </span>
                        of
                        <span class="font-medium text-gray-700">
                            {{ $patients->total() }}
                        </span>
                        patients
                    </p>


                    <nav
                        class="flex items-center gap-1"
                        aria-label="Patient pagination"
                    >

                        {{-- Previous --}}
                        @if ($patients->onFirstPage())

                            <span class="inline-flex h-9 items-center rounded-lg border border-gray-200 px-3 text-sm font-medium text-gray-300">
                                Previous
                            </span>

                        @else

                            <a
                                href="{{ $patients->previousPageUrl() }}"
                                class="inline-flex h-9 items-center rounded-lg border border-gray-200 bg-white px-3 text-sm font-medium text-gray-600 transition hover:bg-gray-50"
                            >
                                Previous
                            </a>

                        @endif


                        {{-- Page numbers --}}
                        @foreach ($patients->getUrlRange(
                            max(1, $patients->currentPage() - 2),
                            min($patients->lastPage(), $patients->currentPage() + 2)
                        ) as $page => $url)

                            @if ($page == $patients->currentPage())

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
                        @if ($patients->hasMorePages())

                            <a
                                href="{{ $patients->nextPageUrl() }}"
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

            @elseif ($patients->total() > 0)

                <div class="border-t border-gray-100 px-5 py-4">
                    <p class="text-sm text-gray-500">
                        Showing all
                        <span class="font-medium text-gray-700">
                            {{ $patients->total() }}
                        </span>
                        {{ Str::plural('patient', $patients->total()) }}
                    </p>
                </div>

            @endif

        </div>

    </div>
</div>


{{-- =============================================================
     ARCHIVE CONFIRMATION
============================================================== --}}
<div
    id="deleteModal"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 px-4 backdrop-blur-sm"
>

    <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">

        <div class="flex items-start gap-4">

            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600">

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
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"
                    />
                </svg>

            </div>


            <div>

                <h3 class="text-base font-semibold text-gray-900">
                    Archive Patient?
                </h3>

                <p class="mt-1 text-sm leading-6 text-gray-500">
                    <span id="deletePatientName" class="font-medium text-gray-700"></span>
                    will be moved to Archived Patients. The record can be restored later.
                </p>

            </div>

        </div>


        <form
            id="deleteForm"
            method="POST"
            class="mt-6 flex justify-end gap-3"
        >
            @csrf
            @method('DELETE')

            <button
                type="button"
                onclick="closeDeleteModal()"
                class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50"
            >
                Cancel
            </button>

            <button
                type="submit"
                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700"
            >
                Archive Patient
            </button>

        </form>

    </div>

</div>


<script>
    function openDeleteModal(patientId, patientName) {
        const modal = document.getElementById('deleteModal');
        const form = document.getElementById('deleteForm');
        const name = document.getElementById('deletePatientName');

        form.action = @js(url('/patients')) + '/' + patientId;
        name.textContent = patientName;

        modal.classList.remove('hidden');
        modal.classList.add('flex');

        document.body.style.overflow = 'hidden';
    }


    function closeDeleteModal() {
        const modal = document.getElementById('deleteModal');

        modal.classList.add('hidden');
        modal.classList.remove('flex');

        document.body.style.overflow = '';
    }


    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeDeleteModal();
        }
    });


    document.getElementById('deleteModal')?.addEventListener('click', function (event) {
        if (event.target === this) {
            closeDeleteModal();
        }
    });
   const patientSearchInput = document.getElementById('patientSearchInput');
const patientSearchForm = document.getElementById('patientSearchForm');

if (patientSearchInput && patientSearchForm) {
    let searchTimer;

    patientSearchInput.addEventListener('input', function () {
        clearTimeout(searchTimer);

        searchTimer = setTimeout(() => {
            patientSearchForm.requestSubmit();
        }, 350);
    });
}
</script>

</x-app-layout>