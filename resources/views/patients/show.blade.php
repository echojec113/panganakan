<x-app-layout>
    <style>
        .patient-profile-theme {
            --color-primary: #55B85A;
            --color-primary-hover: #4aa04c;
            --color-primary-soft: #EDF5EC;
            --color-card-bg: #ffffff;
            --color-border: #E7E9E5;
            --color-border-strong: #D1D5DB;
            --color-neutral-soft: #F6F4EE;
            --text-primary: #19355F;
            --text-muted: #657083;
        }

        .patient-profile-theme [class~="focus:ring-blue-500"] {
            --tw-ring-color: #55B85A;
        }

        .patient-profile-theme [class~="focus:border-blue-500"] {
            border-color: #55B85A;
        }

        #deliveryModal.delivery-modal-theme {
            --color-primary: #55B85A;
            --color-primary-hover: #4aa04c;
            --color-card-bg: #ffffff;
            --color-border: #E7E9E5;
            --color-border-strong: #D1D5DB;
            --color-neutral-soft: #F6F4EE;
            --text-primary: #19355F;
        }

        #deliveryModal.delivery-modal-theme [class~="bg-gray-500"] {
            background-color: rgba(25, 53, 95, 0.75);
        }

        #deliveryModal.delivery-modal-theme [class~="bg-gray-50"] {
            background-color: #F6F4EE;
        }

        #deliveryModal.delivery-modal-theme [class~="bg-blue-600"] {
            background-color: #55B85A;
        }

        #deliveryModal.delivery-modal-theme [class~="text-blue-700"] {
            color: #367E4B;
        }

        #deliveryModal.delivery-modal-theme [class~="bg-blue-50"] {
            background-color: #EDF5EC;
        }

        #deliveryModal.delivery-modal-theme [class~="border-blue-200"] {
            border-color: #D8EBD6;
        }

        #deliveryModal.delivery-modal-theme [class~="focus:ring-blue-500"] {
            --tw-ring-color: #55B85A;
        }

        #deliveryModal.delivery-modal-theme [class~="focus:border-blue-500"] {
            border-color: #55B85A;
        }

        #deliveryModal.delivery-modal-theme [class~="border-gray-200"],
        #deliveryModal.delivery-modal-theme [class~="border-gray-300"] {
            border-color: #E7E9E5;
        }

        #deliveryModal.delivery-modal-theme [class~="text-gray-900"],
        #deliveryModal.delivery-modal-theme [class~="text-gray-800"],
        #deliveryModal.delivery-modal-theme [class~="text-gray-700"] {
            color: #19355F;
        }

        #deliveryModal.delivery-modal-theme [class~="text-gray-600"],
        #deliveryModal.delivery-modal-theme [class~="text-gray-500"],
        #deliveryModal.delivery-modal-theme [class~="text-gray-400"] {
            color: #657083;
        }

        #deliveryModal.delivery-modal-theme [class~="bg-yellow-50"] {
            background-color: #FFFBEB;
        }
        /* Keep native section anchors below the global header and profile navigation. */
        .patient-profile-theme .patient-profile-nav {
            position: sticky;
            top: var(--profile-header-height, 56px);
            z-index: 40;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            contain: inline-size;
        }

        .patient-profile-theme .patient-profile-nav a {
            flex-shrink: 0;
            white-space: nowrap;
        }

        .patient-profile-theme :is(#overview, #prenatal-visits, #medical-history,
            #ultrasound, #birth-plan, #risk-assessment, #referral-follow-through) {
            scroll-margin-top: calc(var(--profile-header-height, 56px) + var(--profile-nav-height, 74px) + 16px);
        }

    </style>

    <div class="patient-profile-theme max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8" style="background-color: #FCFBF8;">
        @if(session('success'))
            <x-flash type="success" :message="session('success')" class="mb-6" />
        @endif

        @if(session('error'))
            <x-flash type="error" :message="session('error')" class="mb-6" />
        @endif

        <x-error-summary :errors="$errors" title="Please review the highlighted issues." class="mb-6" />

        @if($patient->status === 'DELIVERED')
            <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <h2 class="text-base font-semibold text-amber-900">Pregnancy Completed</h2>
                        <p class="mt-1 text-sm text-amber-800">This pregnancy has been completed and is kept as a historical record. Clinical records are read-only to preserve accuracy. To register another pregnancy for this mother, use Start New Pregnancy.</p>
                    </div>
                </div>
            </div>
        @endif

        @if($patient->status === 'REFERRED')
            <div class="mb-6 rounded-xl border border-slate-200 bg-slate-50 px-5 py-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 flex-shrink-0 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <h2 class="text-base font-semibold text-slate-800">Legacy Referred Record</h2>
                        <p class="mt-1 text-sm text-slate-600">This pregnancy was recorded under a legacy referred status and is kept as a read-only historical record.</p>
                    </div>
                </div>
            </div>
        @endif

                <!-- Patient Header -->
        <div class="panel mb-6">
            <div class="panel-body">
                <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">

                    {{-- Patient Identity --}}
                    <div class="min-w-0 flex-1">
                        <div class="mb-3 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-semibold tracking-wide text-gray-600">
                                Patient ID: PT-{{ str_pad($patient->id, 4, '0', STR_PAD_LEFT) }}
                            </span>

                            @if($patient->status === 'ONGOING')
                                <x-status-badge variant="info">Ongoing Pregnancy</x-status-badge>
                            @elseif($patient->status === 'DELIVERED')
                                <x-status-badge variant="success">Completed Pregnancy</x-status-badge>
                            @elseif($patient->status === 'REFERRED')
                                <x-status-badge variant="neutral">Historical Referred Record</x-status-badge>
                            @else
                                <x-status-badge variant="neutral">
                                    {{ ucwords(strtolower(str_replace('_', ' ', $patient->status))) }}
                                </x-status-badge>
                            @endif

                            @if($patient->hasActiveReferral())
                                <x-status-badge
                                    variant="warning"
                                    title="There is a referral awaiting follow-through for this patient."
                                >
                                    Pending Referral
                                </x-status-badge>
                            @endif
                        </div>

                        <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                            {{ $patient->first_name }}
                            {{ $patient->middle_name ? $patient->middle_name . ' ' : '' }}{{ $patient->last_name }}
                        </h1>

                        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-gray-600">
                            <span>{{ $patient->age }} years old</span>

                            @if($patient->civil_status)
                                <span class="text-gray-300" aria-hidden="true">&bull;</span>
                                <span>{{ $patient->civil_status }}</span>
                            @endif

                            @if($patient->contact_number)
                                <span class="text-gray-300" aria-hidden="true">&bull;</span>
                                <span>{{ $patient->contact_number }}</span>
                            @endif
                        </div>

                        @if($patient->address)
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600">
                                {{ $patient->address }}
                            </p>
                        @endif

                        <div class="mt-5 border-t border-gray-100 pt-4">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                Assigned Staff
                            </div>

                            <div class="mt-1 text-sm font-semibold text-gray-800">
                                @if($patient->assignedStaff)
                                    {{ $patient->assignedStaff->name }}
                                @else
                                    <span class="font-medium text-gray-500">Not Assigned</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Patient Actions --}}
                    <div class="flex w-full flex-col gap-2 xl:w-auto xl:min-w-[240px]">

                        @if($patient->status === 'ONGOING')

                            @if(auth()->user()->role !== 'admin')

                                {{-- Primary Action --}}
                                @if($canAddPrenatalVisit)
                                    <a
                                        href="{{ route('prenatal-visits.create', ['patient_id' => $patient->id]) }}"
                                        class="btn btn-primary w-full justify-center"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        Add Prenatal Visit
                                    </a>
                                @else
                                    <div class="group relative">
                                        <button
                                            type="button"
                                            disabled
                                            class="btn btn-secondary w-full cursor-not-allowed justify-center opacity-60"
                                        >
                                            Complete Required Records First
                                        </button>

                                        <div class="absolute right-0 z-50 mt-2 hidden w-80 rounded-lg border border-gray-200 bg-white p-4 shadow-lg group-hover:block">
                                            <p class="mb-3 text-sm font-semibold text-gray-800">
                                                Complete the following records before adding a prenatal visit:
                                            </p>

                                            <div class="space-y-3">

                                                {{-- Medical History --}}
                                                <div class="flex items-start justify-between gap-3">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-700">
                                                            Medical History
                                                        </div>
                                                        <div class="mt-0.5 text-xs {{ $hasMedicalHistory ? 'text-green-700' : 'text-red-600' }}">
                                                            {{ $hasMedicalHistory ? 'Complete' : 'Required' }}
                                                        </div>
                                                    </div>

                                                    @if(!$hasMedicalHistory)
                                                        <a
                                                            href="{{ route('medical-histories.create', ['patient_id' => $patient->id]) }}"
                                                            class="text-sm font-semibold text-blue-700 hover:text-blue-900"
                                                        >
                                                            Add
                                                        </a>
                                                    @endif
                                                </div>

                                                {{-- Ultrasound --}}
                                                <div class="flex items-start justify-between gap-3 border-t border-gray-100 pt-3">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-700">
                                                            Ultrasound Record
                                                        </div>
                                                        <div class="mt-0.5 text-xs {{ $hasUltrasound ? 'text-green-700' : 'text-red-600' }}">
                                                            {{ $hasUltrasound ? 'Complete' : 'Required' }}
                                                        </div>
                                                    </div>

                                                    @if(!$hasUltrasound)
                                                        <a
                                                            href="{{ route('ultrasound.create', $patient->id) }}"
                                                            class="text-sm font-semibold text-blue-700 hover:text-blue-900"
                                                        >
                                                            Add
                                                        </a>
                                                    @endif
                                                </div>

                                                {{-- Birth Plan --}}
                                                <div class="flex items-start justify-between gap-3 border-t border-gray-100 pt-3">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-700">
                                                            Birth Plan
                                                        </div>
                                                        <div class="mt-0.5 text-xs {{ $hasBirthPlan ? 'text-green-700' : 'text-red-600' }}">
                                                            {{ $hasBirthPlan ? 'Complete' : 'Required' }}
                                                        </div>
                                                    </div>

                                                    @if(!$hasBirthPlan)
                                                        <a
                                                            href="{{ route('birth-plans.create', ['patient_id' => $patient->id]) }}"
                                                            class="text-sm font-semibold text-blue-700 hover:text-blue-900"
                                                        >
                                                            Add
                                                        </a>
                                                    @endif
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                @endif

                                {{-- Secondary Actions --}}
                                <div class="grid grid-cols-2 gap-2">
                                    <a
                                        href="{{ route('patients.edit', $patient->id) }}"
                                        class="btn btn-secondary justify-center"
                                    >
                                        Edit Patient
                                    </a>

                                    <a
                                        href="{{ route('referrals.create', $patient->id) }}"
                                        class="btn btn-secondary justify-center"
                                    >
                                        Create Referral
                                    </a>
                                </div>

                                {{-- Consequential Action --}}
                                <button
                                    type="button"
                                    onclick="openDeliveryModal()"
                                    class="btn btn-secondary w-full justify-center"
                                >
                                    Mark Pregnancy as Delivered
                                </button>

                            @endif

                        @else

                            {{-- Historical / Completed Pregnancy --}}
                            <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                This pregnancy is maintained as a read-only historical record.
                            </div>

                            <a
                                href="{{ route('patients.delivered.history', $patient->id) }}"
                                class="btn btn-secondary w-full justify-center"
                            >
                                View Pregnancy History
                            </a>

                            @if(auth()->user()->role !== 'admin')
                                <form
                                    method="POST"
                                    action="{{ route('patients.start-new-pregnancy', $patient->id) }}"
                                >
                                    @csrf

                                    <button
                                        type="submit"
                                        onclick="return confirm('Start a new pregnancy record for this patient? The completed record will remain unchanged.')"
                                        class="btn btn-primary w-full justify-center"
                                    >
                                        Start New Pregnancy
                                    </button>
                                </form>
                            @endif

                        @endif

                        {{-- Download is available for both active and historical records --}}
                        <button
                            type="button"
                            onclick="startDownloadProcess()"
                            data-download-url="{{ route('patients.download', $patient->id) }}"
                            class="btn btn-secondary w-full justify-center"
                        >
                            Download Patient Record
                        </button>

                    </div>
                </div>
            </div>
        </div>

        {{-- =========================================================
     Phase 3: Attention Required
     Only rendered when the ongoing pregnancy has an
     actionable condition that staff should notice.
========================================================= --}}
@if($patient->status === 'ONGOING')
    @php
        $attentionItems = [];

        /*
        |--------------------------------------------------------------------------
        | Latest Risk Assessment
        |--------------------------------------------------------------------------
        */
        if ($latestAssessment?->risk_level === 'HIGH') {
            $attentionItems[] = [
                'tone' => 'danger',
                'title' => 'High-Risk Pregnancy',
                'message' => 'The latest prenatal assessment classified this pregnancy as high risk.',
                'action_label' => 'View Risk Assessment',
                'action_target' => '#risk-assessment',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Prenatal Follow-Up
        |--------------------------------------------------------------------------
        */
        if ($latestAssessment?->next_visit_date) {
            $nextVisitDate = \Carbon\Carbon::parse($latestAssessment->next_visit_date)->startOfDay();
            $today = \Carbon\Carbon::today();

            if ($nextVisitDate->lt($today)) {
                $attentionItems[] = [
                    'tone' => 'warning',
                    'title' => 'Prenatal Follow-Up Overdue',
                    'message' => 'The scheduled return visit was '
                        . $nextVisitDate->format('M d, Y')
                        . ' and is now overdue.',
                    'action_label' => 'View Prenatal Visits',
                    'action_target' => '#prenatal-visits',
                ];
            } elseif ($nextVisitDate->isSameDay($today)) {
                $attentionItems[] = [
                    'tone' => 'warning',
                    'title' => 'Prenatal Follow-Up Due Today',
                    'message' => 'The patient is scheduled to return for prenatal follow-up today.',
                    'action_label' => 'View Prenatal Visits',
                    'action_target' => '#prenatal-visits',
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Pending Referral
        |--------------------------------------------------------------------------
        */
        if ($patient->hasActiveReferral()) {
            $attentionItems[] = [
                'tone' => 'warning',
                'title' => 'Pending Referral',
                'message' => 'This patient has a referral awaiting follow-through.',
                'action_label' => 'View Referral',
                'action_target' => '#referral-follow-through',
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Pregnancy Outcome Follow-Up
        |--------------------------------------------------------------------------
        | Reuses the existing monitoring service result supplied by the
        | controller. No pregnancy-outcome rules are recreated here.
        */
        if ($monitoringEligible) {
            $attentionItems[] = [
                'tone' => 'warning',
                'title' => 'Pregnancy Outcome Follow-Up Due',
                'message' => 'Pregnancy outcome monitoring requires a follow-up observation for this patient.',
                'action_label' => 'View Pregnancy Outcome',
                'action_target' => $monitoringReturnUrl ?? route('pregnancy-outcomes.index'),
            ];
        }
    @endphp

    @if(count($attentionItems) > 0)
        <section class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            {{-- Section Header --}}
            <div class="flex flex-col gap-2 border-b border-gray-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">
                        Attention Required
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        Review the following items for this pregnancy.
                    </p>
                </div>

                <span class="inline-flex w-fit items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                    {{ count($attentionItems) }}
                    {{ count($attentionItems) === 1 ? 'item' : 'items' }}
                </span>
            </div>

            {{-- Attention Items --}}
            <div class="divide-y divide-gray-100">
                @foreach($attentionItems as $item)
                    @php
                        $isDanger = $item['tone'] === 'danger';

                        $indicatorClass = $isDanger
                            ? 'bg-red-500'
                            : 'bg-amber-400';

                        $titleClass = $isDanger
                            ? 'text-red-800'
                            : 'text-amber-800';

                        $actionClass = $isDanger
                            ? 'text-red-700 hover:text-red-900'
                            : 'text-amber-700 hover:text-amber-900';
                    @endphp

                    <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex min-w-0 gap-3">
                            <span
                                class="mt-2 h-2 w-2 flex-none rounded-full {{ $indicatorClass }}"
                                aria-hidden="true"
                            ></span>

                            <div>
                                <h3 class="text-sm font-semibold {{ $titleClass }}">
                                    {{ $item['title'] }}
                                </h3>

                                <p class="mt-1 text-sm leading-6 text-gray-600">
                                    {{ $item['message'] }}
                                </p>
                            </div>
                        </div>

                        <a
                            href="{{ $item['action_target'] }}"
                            class="ml-5 inline-flex flex-none items-center text-sm font-semibold {{ $actionClass }}"
                        >
                            {{ $item['action_label'] }}
                            <span class="ml-1" aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endif


        {{-- =========================================================
     Phase 4: Patient Profile Navigation
========================================================= --}}
<nav
    class="patient-profile-nav mb-6 overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm"
    aria-label="Patient profile sections"
>
    <div class="flex min-w-max items-center px-2 py-2">
        <a
            href="#overview"
            class="rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-900 transition hover:bg-gray-100"
        >
            Overview
        </a>

        <a
            href="#prenatal-visits"
            class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900"
        >
            Prenatal Visits
        </a>

        <a
            href="#medical-history"
            class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900"
        >
            Medical History
        </a>

        <a
            href="#ultrasound"
            class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900"
        >
            Ultrasound
        </a>

        <a
            href="#birth-plan"
            class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900"
        >
            Birth Plan
        </a>

        <a
            href="#risk-assessment"
            class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900"
        >
            Risk Assessment
        </a>

        <a
            href="#referral-follow-through"
            class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900"
        >
            Referrals
        </a>
    </div>
</nav>
<script>
    (() => {
        const nav = document.querySelector('.patient-profile-nav');
        const profile = nav.closest('.patient-profile-theme');
        const header = document.querySelector('.main-wrapper > .topbar');
        const updateOffsets = () => {
            profile.style.setProperty('--profile-header-height', (header?.getBoundingClientRect().height ?? 56) + 'px');
            profile.style.setProperty('--profile-nav-height', nav.getBoundingClientRect().height + 'px');
        };
        updateOffsets();
        if ('ResizeObserver' in window) {
            const observer = new ResizeObserver(updateOffsets);
            observer.observe(nav);
            if (header) observer.observe(header);
        } else {
            window.addEventListener('resize', updateOffsets);
        }
    })();
</script>
                                            
        {{-- Priority strip: Current Pregnancy + Basic Information --}}
        <div
    id="overview"
    class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6 scroll-mt-6"
>
                        @php
                /*
                 * Phase 2 — Pregnancy at a Glance
                 *
                 * Keep this presentation based on the existing patient and
                 * prenatal-visit records. No clinical decision logic is
                 * recalculated here.
                 */
                $latestPregnancyVisit = $patient->prenatalVisits->first();

                $statusVariant = match ($patient->status) {
                    'ONGOING' => 'info',
                    'DELIVERED' => 'success',
                    default => 'neutral',
                };

                $latestRiskLevel = $latestPregnancyVisit?->risk_level;

                $riskVariant = match ($latestRiskLevel) {
                    'HIGH' => 'danger',
                    'LOW' => 'success',
                    'ASSESSMENT INCOMPLETE' => 'warning',
                    default => 'neutral',
                };

                $riskLabel = match ($latestRiskLevel) {
                    'HIGH' => 'High Risk',
                    'LOW' => 'Low Risk',
                    'ASSESSMENT INCOMPLETE' => 'Assessment Incomplete',
                    default => 'Not Yet Assessed',
                };

                $formattedLastMenstrualPeriod = $patient->lmp
                    ? \Carbon\Carbon::parse($patient->lmp)->format('M d, Y')
                    : 'Not recorded';

                $formattedEstimatedDeliveryDate = $patient->edd
                    ? \Carbon\Carbon::parse($patient->edd)->format('M d, Y')
                    : 'Not recorded';

                $formattedLatestVisitDate = $latestPregnancyVisit?->visit_date
                    ? \Carbon\Carbon::parse($latestPregnancyVisit->visit_date)->format('M d, Y')
                    : 'No visit recorded';

                $formattedNextVisitDate = $latestPregnancyVisit?->next_visit_date
                    ? \Carbon\Carbon::parse($latestPregnancyVisit->next_visit_date)->format('M d, Y')
                    : 'Not scheduled';

                $nextVisitIsOverdue = $patient->status === 'ONGOING'
                    && $latestPregnancyVisit?->next_visit_date
                    && \Carbon\Carbon::parse($latestPregnancyVisit->next_visit_date)->startOfDay()->lt(now()->startOfDay());

                $nextVisitIsToday = $patient->status === 'ONGOING'
                    && $latestPregnancyVisit?->next_visit_date
                    && \Carbon\Carbon::parse($latestPregnancyVisit->next_visit_date)->isToday();
            @endphp

            {{-- Pregnancy at a Glance --}}
            <div class="panel overflow-hidden">
                <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900">
                                Pregnancy at a Glance
                            </h2>
                            <p class="mt-1 text-sm text-gray-500">
                                Current pregnancy status and latest clinical assessment.
                            </p>
                        </div>

                        <x-status-badge :variant="$statusVariant">
                            {{ $patient->status === 'ONGOING' ? 'Ongoing Pregnancy' : ucwords(strtolower($patient->status)) }}
                        </x-status-badge>
                    </div>
                </div>

                <div class="panel-body">
                    {{-- Primary clinical status --}}
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">

                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-4">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Latest Risk Assessment
                            </div>

                            <div class="mt-2">
                                <x-status-badge :variant="$riskVariant">
                                    {{ $riskLabel }}
                                </x-status-badge>
                            </div>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-4">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Gestational Age
                            </div>

                            <div class="mt-2 text-lg font-semibold text-gray-900">
                                @if($latestPregnancyVisit?->gestational_age)
                                    {{ $latestPregnancyVisit->gestational_age }} weeks
                                @else
                                    <span class="text-base font-medium text-gray-500">Not recorded</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Important pregnancy dates --}}
                    <div class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">

                        <div class="border-b border-gray-100 pb-3">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Estimated Date of Delivery
                            </div>
                            <div class="mt-1 text-sm font-semibold text-gray-900">
                                {{ $formattedEstimatedDeliveryDate }}
                            </div>
                        </div>

                        <div class="border-b border-gray-100 pb-3">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Last Menstrual Period
                            </div>
                            <div class="mt-1 text-sm font-semibold text-gray-900">
                                {{ $formattedLastMenstrualPeriod }}
                            </div>
                        </div>

                        <div class="border-b border-gray-100 pb-3">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Latest Prenatal Visit
                            </div>
                            <div class="mt-1 text-sm font-semibold text-gray-900">
                                {{ $formattedLatestVisitDate }}
                            </div>
                        </div>

                        <div class="border-b border-gray-100 pb-3">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Next Scheduled Visit
                            </div>

                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold text-gray-900">
                                    {{ $formattedNextVisitDate }}
                                </span>

                                @if($nextVisitIsOverdue)
                                    <x-status-badge variant="danger">Overdue</x-status-badge>
                                @elseif($nextVisitIsToday)
                                    <x-status-badge variant="warning">Due Today</x-status-badge>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Pregnancy History --}}
<div class="mt-5 border-t border-gray-100 pt-5">
    <div class="mb-4 text-xs font-semibold uppercase tracking-wide text-gray-500">
        Pregnancy History
    </div>

    <div class="grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-4">
        <div>
            <div class="text-xs text-gray-500">
                Gravida
            </div>
            <div class="mt-1 text-base font-semibold text-gray-900">
                {{ $patient->gravida ?? '—' }}
            </div>
        </div>

        <div>
            <div class="text-xs text-gray-500">
                Para
            </div>
            <div class="mt-1 text-base font-semibold text-gray-900">
                {{ $patient->para ?? '—' }}
            </div>
        </div>

        <div>
            <div class="text-xs leading-5 text-gray-500">
                Previous Cesarean Section
            </div>
            <div class="mt-1 text-base font-semibold text-gray-900">
                {{ $patient->previous_cs ? 'Yes' : 'No' }}
            </div>
        </div>

        <div>
            <div class="text-xs leading-5 text-gray-500">
                Previous Miscarriage
            </div>
            <div class="mt-1 text-base font-semibold text-gray-900">
                {{ $patient->miscarriage ? 'Yes' : 'No' }}
            </div>
        </div>
    </div>
</div>
                </div>
            </div>

        {{-- Latest Prenatal Visit --}}
@php
    $latestVisit = $latestPregnancyVisit;
@endphp

<div class="panel overflow-hidden">
    <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
        <div>
            <h2 class="text-base font-semibold text-gray-900">
                Latest Prenatal Visit
            </h2>

            <p class="mt-1 text-sm text-gray-500">
                Most recent recorded prenatal assessment.
            </p>
        </div>
    </div>

    @if($latestVisit)
        <div class="panel-body">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Visit Date
                    </div>

                    <div class="mt-1 text-base font-semibold text-gray-900">
                        {{ $latestVisit->visit_date
                            ? \Carbon\Carbon::parse($latestVisit->visit_date)->format('M d, Y')
                            : 'Not recorded' }}
                    </div>
                </div>

                @if($latestVisit->risk_level === 'HIGH')
                    <x-status-badge variant="danger">
                        High Risk
                    </x-status-badge>
                @elseif($latestVisit->risk_level === 'LOW')
                    <x-status-badge variant="success">
                        Low Risk
                    </x-status-badge>
                @elseif($latestVisit->risk_level === 'ASSESSMENT INCOMPLETE')
                    <x-status-badge variant="warning">
                        Assessment Incomplete
                    </x-status-badge>
                @endif
            </div>

            <dl class="grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-3">
                <div>
                    <dt class="text-xs text-gray-500">
                        Blood Pressure
                    </dt>
                    <dd class="mt-1 text-sm font-semibold text-gray-900">
                        @if($latestVisit->bp_sys !== null && $latestVisit->bp_dia !== null)
                            {{ $latestVisit->bp_sys }}/{{ $latestVisit->bp_dia }} mmHg
                        @else
                            —
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-xs text-gray-500">
                        Weight
                    </dt>
                    <dd class="mt-1 text-sm font-semibold text-gray-900">
                        {{ $latestVisit->weight !== null ? $latestVisit->weight . ' kg' : '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs text-gray-500">
                        Temperature
                    </dt>
                    <dd class="mt-1 text-sm font-semibold text-gray-900">
                        {{ $latestVisit->temperature !== null ? $latestVisit->temperature . '°C' : '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs text-gray-500">
                        Gestational Age
                    </dt>
                    <dd class="mt-1 text-sm font-semibold text-gray-900">
                        {{ $latestVisit->gestational_age !== null
                            ? $latestVisit->gestational_age . ' weeks'
                            : '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs text-gray-500">
                        Fetal Heart Tone
                    </dt>
                    <dd class="mt-1 text-sm font-semibold text-gray-900">
                        {{ $latestVisit->fetal_heart_tone ?: '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs text-gray-500">
                        Next Scheduled Visit
                    </dt>

                    <dd class="mt-1 flex flex-wrap items-center gap-2 text-sm font-semibold text-gray-900">
                        @if($latestVisit->next_visit_date)
                            <span>
                                {{ \Carbon\Carbon::parse($latestVisit->next_visit_date)->format('M d, Y') }}
                            </span>

                            @if($nextVisitIsOverdue)
                                <x-status-badge variant="danger">
                                    Overdue
                                </x-status-badge>
                            @elseif($nextVisitIsToday)
                                <x-status-badge variant="warning">
                                    Due Today
                                </x-status-badge>
                            @endif
                        @else
                            <span>Not scheduled</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>
    @else
        <div class="px-6 py-10 text-center">
            <p class="text-sm font-medium text-gray-700">
                No prenatal visits recorded yet.
            </p>

            @if($patient->status === 'ONGOING')
                <p class="mt-1 text-sm text-gray-500">
                    Add the patient's first prenatal visit to begin clinical assessment.
                </p>
            @else
                <p class="mt-1 text-sm text-gray-500">
                    No prenatal visit was recorded for this pregnancy.
                </p>
            @endif
        </div>
    @endif
</div>


        </div><!-- End Overview -->

        <!-- Patient Record Sections -->
<div class="space-y-6">
                @if($patient->status === 'DELIVERED' && $patient->babies->count() > 0)
                <!-- Baby Information Section -->
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">Baby Information</div>
                        <span class="text-sm text-gray-500">{{ $patient->babies->count() }} {{ Str::plural('baby', $patient->babies->count()) }}</span>
                    </div>
                    <div class="panel-body">
                        <div class="space-y-4">
                            @foreach($patient->babies as $index => $baby)
                            <div class="baby-card rounded-xl border border-gray-200 p-5" data-baby-id="{{ $baby->id }}">
                                <div class="flex items-center justify-between gap-3 flex-wrap">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-soft text-primary">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class="text-base font-semibold text-gray-800 baby-name-display">{{ $baby->full_name }}</h3>
                                            <p class="text-sm text-gray-500">Baby {{ $index + 1 }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if($baby->sex)
                                        <span class="sex-badge inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                            @if($baby->sex === 'Male') bg-blue-100 text-blue-800
                                            @elseif($baby->sex === 'Female') bg-pink-100 text-pink-800
                                            @else bg-gray-100 text-gray-800 @endif">
                                            {{ $baby->sex }}
                                        </span>
                                        @endif
                                        @if($patient->status === 'ONGOING' && auth()->user()->role !== 'admin')
                                        <button type="button" onclick="toggleBabyEdit({{ $baby->id }})" class="btn btn-secondary edit-baby-btn">
                                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                            Edit
                                        </button>
                                        @endif
                                    </div>
                                </div>

                                <!-- Display Mode -->
                                <div class="baby-display-mode">
                                    <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-4">
                                        <div class="stat-cell">
                                            <div class="stat-label">Date of Birth</div>
                                            <p class="stat-value">{{ $baby->date_of_birth ? \Carbon\Carbon::parse($baby->date_of_birth)->format('M d, Y') : 'N/A' }}</p>
                                        </div>
                                        <div class="stat-cell">
                                            <div class="stat-label">Time of Birth</div>
                                            <p class="stat-value">{{ $baby->time_of_birth ? \Carbon\Carbon::parse($baby->time_of_birth)->format('g:i A') : 'N/A' }}</p>
                                        </div>
                                        <div class="stat-cell">
                                            <div class="stat-label">Birth Weight</div>
                                            <p class="stat-value">{{ $baby->birth_weight ? $baby->birth_weight . ' kg' : 'N/A' }}</p>
                                        </div>
                                        <div class="stat-cell">
                                            <div class="stat-label">Birth Length</div>
                                            <p class="stat-value">{{ $baby->birth_length ? $baby->birth_length . ' cm' : 'N/A' }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit Mode -->
                                <div class="baby-edit-mode hidden">
                                    <form class="baby-edit-form mt-4 border-t border-gray-100 pt-4" data-baby-id="{{ $baby->id }}">
                                        @csrf
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                                                <input type="text" name="first_name" value="{{ $baby->first_name }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                                                <input type="text" name="middle_name" value="{{ $baby->middle_name }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                                                <input type="text" name="last_name" value="{{ $baby->last_name }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Sex</label>
                                                <select name="sex" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                    <option value="">Select Sex</option>
                                                    <option value="Male" {{ $baby->sex === 'Male' ? 'selected' : '' }}>Male</option>
                                                    <option value="Female" {{ $baby->sex === 'Female' ? 'selected' : '' }}>Female</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth <span class="text-red-500">*</span></label>
                                                <input type="date" name="date_of_birth" value="{{ $baby->date_of_birth }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Time of Birth <span class="text-red-500">*</span></label>
                                                <input type="time" name="time_of_birth" value="{{ $baby->time_of_birth }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Birth Weight (kg)</label>
                                                <input type="number" name="birth_weight" value="{{ $baby->birth_weight }}" step="0.01" min="0" max="10" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            </div>
                                            <div class="md:col-span-2 lg:col-span-1">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">Birth Length (cm)</label>
                                                <input type="number" name="birth_length" value="{{ $baby->birth_length }}" step="0.1" min="0" max="100" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                            </div>
                                        </div>

                                        <div class="flex justify-end space-x-3 mt-6">
                                            <button type="button" onclick="cancelBabyEdit({{ $baby->id }})" class="btn btn-secondary">
                                                Cancel
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                Save Changes
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                <!-- =========================================================
     Phase 6: Prenatal Visit History
========================================================= -->
<section id="prenatal-visits" class="panel scroll-mt-6">
    {{-- Section Header --}}
    <div class="panel-header">
        <div>
            <div class="panel-title">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                    ></path>
                </svg>

                Prenatal Visit History
            </div>

            <p class="mt-1 text-sm text-gray-500">
                Chronological record of prenatal visits and clinical assessments.
            </p>
        </div>

        <div class="flex items-center gap-3">
            @if($patient->status === 'DELIVERED')
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-600">
                    Read-only
                </span>
            @else
                <span class="text-sm text-gray-500">
                    {{ $patient->prenatalVisits->count() }}
                    {{ Str::plural('visit', $patient->prenatalVisits->count()) }}
                </span>
            @endif
        </div>
    </div>

    @if($patient->prenatalVisits->isNotEmpty())

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">

                {{-- Table Header --}}
                <thead class="bg-gray-50">
                    <tr>
                        <th class="th-cell">Visit Date</th>
                        <th class="th-cell">Blood Pressure</th>
                        <th class="th-cell">Weight</th>
                        <th class="th-cell">Gestational Age</th>
                        <th class="th-cell">Risk Assessment</th>
                        <th class="th-cell">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 bg-white">

                    @foreach($patient->prenatalVisits as $visit)
                        @php
                            $visitMissingRecords = \App\Support\ListNormalizer::normalize(
                                $visit->missing_records
                            );

                            $visitRuleReasons = \App\Support\ListNormalizer::normalize(
                                $visit->rule_reasons
                            );

                            $formattedVisitDate = $visit->visit_date
                                ? \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y')
                                : 'Not recorded';

                            $formattedNextVisitDate = $visit->next_visit_date
                                ? \Carbon\Carbon::parse($visit->next_visit_date)->format('M d, Y')
                                : 'Not scheduled';

                            $visitNextDate = $visit->next_visit_date
                                ? \Carbon\Carbon::parse($visit->next_visit_date)->startOfDay()
                                : null;

                            $visitIsOverdue = $patient->status === 'ONGOING'
                                && $visitNextDate
                                && $visitNextDate->lt(\Carbon\Carbon::today());

                            $visitIsDueToday = $patient->status === 'ONGOING'
                                && $visitNextDate
                                && $visitNextDate->isSameDay(\Carbon\Carbon::today());

                            $decisionSourceLabel = match ($visit->decision_source) {
                                'COMPLETENESS' => 'Completeness Check',
                                'RULE_BASED' => 'Rule-Based Clinical Assessment',
                                'MACHINE_LEARNING' => 'Machine Learning Assessment',
                                'MACHINE_LEARNING_INVALID' => 'Machine Learning Assessment Unavailable',
                                null => 'Legacy Assessment',
                                default => ucwords(
                                    strtolower(
                                        str_replace('_', ' ', $visit->decision_source)
                                    )
                                ),
                            };
                        @endphp

                        {{-- Main Visit Row --}}
                        <tr
                            class="cursor-pointer transition hover:bg-gray-50"
                            onclick="toggleVisitDetails({{ $visit->id }})"
                        >
                            <td class="td-cell">
                                <div class="font-semibold text-gray-900">
                                    {{ $formattedVisitDate }}
                                </div>

                                <div class="mt-1 text-xs text-gray-500">
                                    Click to view details
                                </div>
                            </td>

                            <td class="td-cell text-gray-900">
                                @if($visit->bp_sys !== null && $visit->bp_dia !== null)
                                    {{ $visit->bp_sys }}/{{ $visit->bp_dia }} mmHg
                                @else
                                    <span class="text-gray-400">Not recorded</span>
                                @endif
                            </td>

                            <td class="td-cell text-gray-900">
                                @if($visit->weight !== null)
                                    {{ $visit->weight }} kg
                                @else
                                    <span class="text-gray-400">Not recorded</span>
                                @endif
                            </td>

                            <td class="td-cell text-gray-900">
                                @if($visit->gestational_age !== null)
                                    {{ $visit->gestational_age }} weeks
                                @else
                                    <span class="text-gray-400">Not recorded</span>
                                @endif
                            </td>

                            <td class="td-cell">
                                @if($visit->risk_level === 'HIGH')
                                    <x-status-badge variant="danger">
                                        High Risk
                                    </x-status-badge>

                                @elseif($visit->risk_level === 'LOW')
                                    <x-status-badge variant="success">
                                        Low Risk
                                    </x-status-badge>

                                @elseif($visit->risk_level === 'ASSESSMENT INCOMPLETE')
                                    <x-status-badge variant="warning">
                                        Assessment Incomplete
                                    </x-status-badge>

                                @else
                                    <x-status-badge variant="neutral">
                                        Not Assessed
                                    </x-status-badge>
                                @endif
                            </td>

                            <td class="td-cell">
                                @if($patient->status === 'ONGOING')
                                    <div
                                        class="flex flex-wrap items-center gap-x-3 gap-y-2"
                                        onclick="event.stopPropagation()"
                                    >
                                        <a
                                            href="{{ route('prenatal-visits.edit', $visit->id) }}"
                                            class="text-sm font-medium text-blue-600 hover:text-blue-800"
                                        >
                                            Edit
                                        </a>

                                        <form
                                            action="{{ route('prenatal-visits.destroy', $visit->id) }}"
                                            method="POST"
                                            class="inline"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                onclick="return confirm('Delete this prenatal visit?')"
                                                class="text-sm font-medium text-red-600 hover:text-red-800"
                                            >
                                                Delete
                                            </button>
                                        </form>

                                        <a
                                            href="{{ route('prenatal-visits.print', $visit->id) }}"
                                            target="_blank"
                                            class="text-sm font-medium text-gray-600 hover:text-gray-900"
                                        >
                                            Print
                                        </a>
                                    </div>
                                @else
                                    <div
                                        class="flex flex-wrap items-center gap-3"
                                        onclick="event.stopPropagation()"
                                    >
                                        <span class="text-xs text-gray-500">
                                            Read-only
                                        </span>

                                        <a
                                            href="{{ route('prenatal-visits.print', $visit->id) }}"
                                            target="_blank"
                                            class="text-sm font-medium text-gray-600 hover:text-gray-900"
                                        >
                                            Print
                                        </a>
                                    </div>
                                @endif
                            </td>
                        </tr>

                        {{-- Expanded Visit Details --}}
                        <tr
                            id="visit-details-{{ $visit->id }}"
                            class="hidden bg-gray-50"
                        >
                            <td colspan="6" class="px-5 py-5">

                                <div class="rounded-xl border border-gray-200 bg-white">

                                    {{-- Detail Header --}}
                                    <div class="border-b border-gray-100 px-5 py-4">
                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                            <div>
                                                <h3 class="text-sm font-semibold text-gray-900">
                                                    Visit Details
                                                </h3>

                                                <p class="mt-1 text-xs text-gray-500">
                                                    Clinical findings and assessment recorded on
                                                    {{ $formattedVisitDate }}.
                                                </p>
                                            </div>

                                            @if($visit->urgency === 'URGENT_CLINICAL_REVIEW')
                                                <x-status-badge variant="danger">
                                                    Urgent Clinical Review
                                                </x-status-badge>

                                            @elseif($visit->urgency === 'PROMPT')
                                                <x-status-badge variant="warning">
                                                    Prompt Clinical Review
                                                </x-status-badge>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="space-y-6 p-5">

                                        {{-- Clinical Measurements --}}
                                        <div>
                                            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                Clinical Measurements
                                            </h4>

                                            <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">

                                                <div>
                                                    <dt class="text-xs text-gray-500">
                                                        Temperature
                                                    </dt>
                                                    <dd class="mt-1 text-sm font-semibold text-gray-900">
                                                        {{ $visit->temperature !== null
                                                            ? $visit->temperature . '°C'
                                                            : 'Not recorded' }}
                                                    </dd>
                                                </div>

                                                <div>
                                                    <dt class="text-xs text-gray-500">
                                                        Fundic Height
                                                    </dt>
                                                    <dd class="mt-1 text-sm font-semibold text-gray-900">
                                                        {{ $visit->fundic_height !== null
                                                            ? $visit->fundic_height . ' cm'
                                                            : 'Not recorded' }}
                                                    </dd>
                                                </div>

                                                <div>
                                                    <dt class="text-xs text-gray-500">
                                                        Fetal Heart Tone
                                                    </dt>
                                                    <dd class="mt-1 text-sm font-semibold text-gray-900">
                                                        {{ $visit->fetal_heart_tone ?: 'Not recorded' }}
                                                    </dd>
                                                </div>

                                                <div>
                                                    <dt class="text-xs text-gray-500">
                                                        Fetal Movement
                                                    </dt>
                                                    <dd class="mt-1 text-sm font-semibold text-gray-900">
                                                        {{ $visit->fetal_movement ?: 'Not recorded' }}
                                                    </dd>
                                                </div>

                                                <div>
                                                    <dt class="text-xs text-gray-500">
                                                        Presenting Part
                                                    </dt>
                                                    <dd class="mt-1 text-sm font-semibold text-gray-900">
                                                        {{ $visit->presenting_part ?: 'Not recorded' }}
                                                    </dd>
                                                </div>

                                                <div>
                                                    <dt class="text-xs text-gray-500">
                                                        Cervical Dilation
                                                    </dt>
                                                    <dd class="mt-1 text-sm font-semibold text-gray-900">
                                                        {{ $visit->cervical_dilation !== null
                                                            ? $visit->cervical_dilation . ' cm'
                                                            : 'Not recorded' }}
                                                    </dd>
                                                </div>

                                                @if($visit->repeat_bp_sys && $visit->repeat_bp_dia)
                                                    <div>
                                                        <dt class="text-xs text-gray-500">
                                                            Repeat Blood Pressure
                                                        </dt>
                                                        <dd class="mt-1 text-sm font-semibold text-gray-900">
                                                            {{ $visit->repeat_bp_sys }}/{{ $visit->repeat_bp_dia }} mmHg
                                                        </dd>
                                                    </div>
                                                @endif

                                                @if($visit->bp_verification_status)
                                                    <div>
                                                        <dt class="text-xs text-gray-500">
                                                            Blood Pressure Verification
                                                        </dt>
                                                        <dd class="mt-1 text-sm font-semibold text-gray-900">
                                                            {{
                                                                ucwords(
                                                                    strtolower(
                                                                        str_replace(
                                                                            '_',
                                                                            ' ',
                                                                            $visit->bp_verification_status
                                                                        )
                                                                    )
                                                                )
                                                            }}
                                                        </dd>
                                                    </div>
                                                @endif
                                            </dl>
                                        </div>

                                        {{-- Assessment Summary --}}
                                        <div class="border-t border-gray-100 pt-5">
                                            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                Assessment Summary
                                            </h4>

                                            <div class="mt-3 grid grid-cols-1 gap-4 lg:grid-cols-2">

                                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                        Decision Source
                                                    </div>

                                                    <div class="mt-2 text-sm font-semibold text-gray-900">
                                                        {{ $decisionSourceLabel }}
                                                    </div>
                                                </div>

                                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                        Machine Learning Prediction
                                                    </div>

                                                    <div class="mt-2 text-sm font-semibold text-gray-900">
                                                        @if($visit->ml_prediction !== null)
                                                            {{ $visit->ml_prediction }}

                                                            @if($visit->ml_valid !== null)
                                                                <span class="font-normal text-gray-500">
                                                                    —
                                                                    {{ $visit->ml_valid
                                                                        ? 'Valid evaluation'
                                                                        : 'Invalid evaluation' }}
                                                                </span>
                                                            @endif
                                                        @else
                                                            <span class="font-medium text-gray-500">
                                                                Not used
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>

                                                <div class="rounded-lg border border-gray-200 bg-white p-4 lg:col-span-2">
                                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                        Clinical Assessment
                                                    </div>

                                                    <p class="mt-2 text-sm leading-6 text-gray-700">
                                                        {{ $visit->assessment ?: 'No assessment recorded.' }}
                                                    </p>
                                                </div>

                                                <div class="rounded-lg border border-gray-200 bg-white p-4 lg:col-span-2">
                                                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                        Recommendation
                                                    </div>

                                                    <p class="mt-2 text-sm leading-6 text-gray-700">
                                                        {{ $visit->recommendation ?: 'No recommendation recorded.' }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Follow-Up --}}
                                        <div class="border-t border-gray-100 pt-5">
                                            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                Follow-Up
                                            </h4>

                                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                                <div>
                                                    <div class="text-xs text-gray-500">
                                                        Next Scheduled Visit
                                                    </div>

                                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                                        <span class="text-sm font-semibold text-gray-900">
                                                            {{ $formattedNextVisitDate }}
                                                        </span>

                                                        @if($visitIsOverdue)
                                                            <x-status-badge variant="danger">
                                                                Overdue
                                                            </x-status-badge>

                                                        @elseif($visitIsDueToday)
                                                            <x-status-badge variant="warning">
                                                                Due Today
                                                            </x-status-badge>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Assessment Evidence --}}
                                        @if(!empty($visitMissingRecords) || !empty($visitRuleReasons))
                                            <div class="border-t border-gray-100 pt-5">
                                                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                    Assessment Evidence
                                                </h4>

                                                <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">

                                                    {{-- Missing Records --}}
                                                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                                        <div class="text-sm font-semibold text-gray-800">
                                                            Missing Records
                                                        </div>

                                                        @if(!empty($visitMissingRecords))
                                                            <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-gray-600">
                                                                @foreach($visitMissingRecords as $record)
                                                                    <li>{{ $record }}</li>
                                                                @endforeach
                                                            </ul>
                                                        @else
                                                            <p class="mt-2 text-sm text-gray-500">
                                                                None
                                                            </p>
                                                        @endif
                                                    </div>

                                                    {{-- Triggered Rules --}}
                                                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                                        <div class="text-sm font-semibold text-gray-800">
                                                            Triggered Clinical Rules
                                                        </div>

                                                        @if(!empty($visitRuleReasons))
                                                            <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-gray-600">
                                                                @foreach($visitRuleReasons as $reason)
                                                                    <li>{{ $reason }}</li>
                                                                @endforeach
                                                            </ul>
                                                        @else
                                                            <p class="mt-2 text-sm text-gray-500">
                                                                None
                                                            </p>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                    </div>
                                </div>
                            </td>
                        </tr>

                    @endforeach
                </tbody>
            </table>
        </div>

    @else

        {{-- Empty State --}}
        <div class="px-6 py-10 text-center">
            <p class="text-sm font-semibold text-gray-700">
                No prenatal visits recorded yet.
            </p>

            @if($patient->status === 'ONGOING')
                <p class="mt-1 text-sm text-gray-500">
                    Add the patient's first prenatal visit to begin the clinical assessment history.
                </p>
            @else
                <p class="mt-1 text-sm text-gray-500">
                    No prenatal visits were recorded for this pregnancy.
                </p>
            @endif
        </div>

    @endif
</section>

               <!-- =========================================================
     Phase 7: Medical History
========================================================= -->
<section id="medical-history" class="panel scroll-mt-6">

    {{-- Header --}}
    <div class="panel-header">
        <div>
            <div class="panel-title">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                    ></path>
                </svg>

                Medical History
            </div>

            <p class="mt-1 text-sm text-gray-500">
                Pregnancy-level health history and relevant background conditions.
            </p>
        </div>

        @if($patient->status === 'ONGOING')
            @if($patient->medicalHistory)
                <a
                    href="{{ route('medical-histories.edit', $patient->medicalHistory->id) }}"
                    class="text-sm font-medium text-blue-600 hover:text-blue-800"
                >
                    Edit
                </a>
            @else
                <a
                    href="{{ route('medical-histories.create', ['patient_id' => $patient->id]) }}"
                    class="btn btn-primary"
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
                            d="M12 4v16m8-8H4"
                        />
                    </svg>

                    Add Medical History
                </a>
            @endif
        @else
            <span class="text-sm text-gray-500">
                Historical record
            </span>
        @endif
    </div>

    <div class="panel-body">

        @if($patient->medicalHistory)

            @php
                $currentConditions = [
                    'diabetes' => 'Diabetes',
                    'anemia' => 'Anemia',
                ];

                $backgroundConditions = [
                    'epilepsy' => 'Epilepsy',
                    'hypertension' => 'Hypertension',
                    'asthma' => 'Asthma',
                    'thyroid_disease' => 'Thyroid Disease',
                    'heart_disease' => 'Heart Disease',
                    'liver_disease' => 'Liver Disease',
                    'mental_health_condition' => 'Mental Health Condition',
                ];

                $lifestyleHistory = [
                    'smoking' => 'Smoking',
                    'allergies' => 'Allergies',
                    'drug_intake' => 'Drug Intake',
                    'std_history' => 'Sexually Transmitted Disease History',
                    'breast_mass' => 'Breast Mass',
                ];

                $historicalConcerns = [
                    'severe_headache' => 'Severe Headache',
                    'visual_disturbance' => 'Visual Disturbance',
                    'chest_pain' => 'Chest Pain',
                    'shortness_breath' => 'Shortness of Breath',
                ];

                $activeCurrentConditions = collect($currentConditions)
                    ->filter(fn ($label, $field) => (bool) $patient->medicalHistory->$field);

                $activeBackgroundConditions = collect($backgroundConditions)
                    ->filter(fn ($label, $field) => (bool) $patient->medicalHistory->$field);

                $activeLifestyleHistory = collect($lifestyleHistory)
                    ->filter(fn ($label, $field) => (bool) $patient->medicalHistory->$field);

                $activeHistoricalConcerns = collect($historicalConcerns)
                    ->filter(fn ($label, $field) => (bool) $patient->medicalHistory->$field);
            @endphp

            <div class="divide-y divide-gray-100">

                {{-- Current Conditions --}}
                <div class="pb-5">
                    <div class="mb-3">
                        <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Current Conditions
                        </h4>

                        <p class="mt-1 text-xs text-gray-400">
                            Conditions that may also be assessed during prenatal visits.
                        </p>
                    </div>

                    @if($activeCurrentConditions->isNotEmpty())
                        <div class="space-y-2">
                            @foreach($activeCurrentConditions as $label)
                                <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 px-4 py-3">
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ $label }}
                                    </span>

                                    <x-status-badge variant="info">
                                        Confirmed
                                    </x-status-badge>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500">
                            No current conditions recorded.
                        </p>
                    @endif
                </div>

                {{-- Background Conditions --}}
                <div class="py-5">
                    <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Background Conditions
                    </h4>

                    @if($activeBackgroundConditions->isNotEmpty())
                        <div class="flex flex-wrap gap-2">
                            @foreach($activeBackgroundConditions as $label)
                                <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-700">
                                    {{ $label }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500">
                            No background conditions recorded.
                        </p>
                    @endif
                </div>

                {{-- Lifestyle and Other History --}}
                <div class="py-5">
                    <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Lifestyle & Other History
                    </h4>

                    @if($activeLifestyleHistory->isNotEmpty())
                        <div class="flex flex-wrap gap-2">
                            @foreach($activeLifestyleHistory as $label)
                                <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-700">
                                    {{ $label }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500">
                            No relevant lifestyle or other history recorded.
                        </p>
                    @endif
                </div>

                {{-- Previous / Recurring Concerns --}}
                <div class="py-5">
                    <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Previous or Recurring Concerns
                    </h4>

                    @if($activeHistoricalConcerns->isNotEmpty())
                        <div class="flex flex-wrap gap-2">
                            @foreach($activeHistoricalConcerns as $label)
                                <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-700">
                                    {{ $label }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500">
                            No previous or recurring concerns recorded.
                        </p>
                    @endif
                </div>

                {{-- Other Specified History --}}
                @if($patient->medicalHistory->other_specify)
                    <div class="py-5">
                        <h4 class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Other Specified History
                        </h4>

                        <p class="text-sm font-medium text-gray-900">
                            {{ $patient->medicalHistory->other_specify }}
                        </p>
                    </div>
                @endif

                {{-- Clinical Note --}}
                <div class="pt-5">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Clinical Note
                        </div>

                        <p class="mt-2 text-sm leading-6 text-gray-600">
                            Diabetes and anemia may also be assessed during prenatal visits
                            and can contribute to that visit's clinical risk assessment.
                            This Medical History record stores pregnancy-level background
                            information.
                        </p>
                    </div>
                </div>

            </div>

        @else

            {{-- No Medical History Record --}}
            <div class="py-6 text-center">
                <p class="text-sm font-semibold text-gray-700">
                    No medical history recorded.
                </p>

                @if($patient->status === 'ONGOING')
                    <p class="mt-1 text-sm text-gray-500">
                        Add the patient's relevant health history and background conditions.
                    </p>
                @endif
            </div>

            @php
                $visitRecordedCondition = $patient->prenatalVisits->contains(function ($visit) {
                    return (bool) $visit->diabetes || (bool) $visit->anemia;
                });
            @endphp

            @if($visitRecordedCondition)
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                    <div class="text-sm font-semibold text-amber-900">
                        Medical history follow-up needed
                    </div>

                    <p class="mt-1 text-sm leading-6 text-amber-800">
                        Diabetes or anemia was recorded during a prenatal visit.
                        Complete the Medical History record to maintain the patient's
                        pregnancy-level background information.
                    </p>
                </div>
            @endif

        @endif

    </div>
</section>

                <!-- =========================================================
     Phase 8: Ultrasound Records
========================================================= -->
<section id="ultrasound" class="panel scroll-mt-6">

    {{-- Header --}}
    <div class="panel-header">
        <div>
            <div class="panel-title">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M3 9l3 6m0 0l3-6m0 0l3 6m0 0l3-6m3 3v6a2 2 0 01-2 2H5a2 2 0 01-2-2V9"
                    ></path>
                </svg>

                Ultrasound Records
            </div>

            <p class="mt-1 text-sm text-gray-500">
                Recorded ultrasound findings and available reports for this pregnancy.
            </p>
        </div>

        @if($patient->status === 'ONGOING')
            <a
                href="{{ route('ultrasound.create', $patient->id) }}"
                class="btn btn-primary"
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
                        d="M12 4v16m8-8H4"
                    />
                </svg>

                Add Ultrasound Record
            </a>
        @else
            <span class="text-sm text-gray-500">
                Historical record
            </span>
        @endif
    </div>

    @if($patient->ultrasounds->isNotEmpty())

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">

                <thead class="bg-gray-50">
                    <tr>
                        <th class="th-cell">
                            Scan Date
                        </th>

                        <th class="th-cell">
                            Fetal Heartbeat
                        </th>

                        <th class="th-cell">
                            Fetal Movement
                        </th>

                        <th class="th-cell">
                            Gestational Age
                        </th>

                        <th class="th-cell">
                            Report
                        </th>

                        <th class="th-cell">
                            Actions
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 bg-white">

                    @foreach($patient->ultrasounds as $u)

                        @php
                            $formattedScanDate = $u->scan_date
                                ? \Carbon\Carbon::parse($u->scan_date)->format('M d, Y')
                                : 'Not recorded';

                            $usHasImage = $u->report_image
                                && \Storage::disk('public')->exists($u->report_image);

                            $usHasPdf = $u->report_file
                                && \Storage::disk('public')->exists($u->report_file);

                            $usImageUrl = $usHasImage
                                ? route('ultrasound.file', [
                                    'id' => $u->id,
                                    'type' => 'image'
                                ])
                                : null;

                            $usPdfUrl = $usHasPdf
                                ? route('ultrasound.file', [
                                    'id' => $u->id,
                                    'type' => 'pdf'
                                ])
                                : null;
                        @endphp

                        <tr
    class="cursor-pointer transition hover:bg-gray-50"
    onclick="toggleUltrasoundDetails({{ $u->id }})"
>

                            {{-- Scan Date --}}
                            <td class="td-cell">
                                <span class="font-semibold text-gray-900">
                                    {{ $formattedScanDate }}
                                </span>
                            </td>

                            {{-- Fetal Heartbeat --}}
                            <td class="td-cell">
                                @if($u->fetal_heartbeat)
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ $u->fetal_heartbeat }}
                                    </span>
                                @else
                                    <span class="text-sm text-gray-400">
                                        Not recorded
                                    </span>
                                @endif
                            </td>

                            {{-- Fetal Movement --}}
                            <td class="td-cell">
                                @if($u->fetal_movement)
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ $u->fetal_movement }}
                                    </span>
                                @else
                                    <span class="text-sm text-gray-400">
                                        Not recorded
                                    </span>
                                @endif
                            </td>

                            {{-- Gestational Age --}}
                            <td class="td-cell">
                                @if(
                                    $u->gestational_age_scan !== null
                                    && $u->gestational_age_scan !== ''
                                )
                                    <span class="text-sm font-medium text-gray-900">
                                        {{ $u->gestational_age_scan }} weeks
                                    </span>
                                @else
                                    <span class="text-sm text-gray-400">
                                        Not recorded
                                    </span>
                                @endif
                            </td>

                            {{-- Report --}}
                            <td class="td-cell">
                                @if($usHasImage || $usHasPdf)

                                    <div class="flex items-center gap-3">

                                        @if($usHasImage)
                                            <button
                                                type="button"
                                                data-full="{{ $usImageUrl }}"
                                                class="us-lightbox-trigger shrink-0"
                                                aria-label="View ultrasound image"
                                            >
                                                <img
                                                    src="{{ $usImageUrl }}"
                                                    alt="Ultrasound image from {{ $formattedScanDate }}"
                                                    class="h-11 w-11 cursor-pointer rounded-lg border border-gray-200 object-cover transition hover:opacity-80"
                                                >
                                            </button>
                                        @endif

                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">

                                            @if($usHasImage)
                                                <a
                                                    href="#"
                                                    data-full="{{ $usImageUrl }}"
                                                    class="us-lightbox-trigger font-medium text-blue-600 hover:text-blue-800"
                                                >
                                                    View Image
                                                </a>
                                            @endif

                                            @if($usHasPdf)
                                                <a
                                                    href="{{ $usPdfUrl }}"
                                                    onclick="event.stopPropagation()"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="font-medium text-blue-600 hover:text-blue-800"
                                                >
                                                    View PDF
                                                </a>
                                            @endif

                                        </div>
                                    </div>

                                @else

                                    <span class="text-sm text-gray-400">
                                        No report attached
                                    </span>

                                @endif
                            </td>

                            {{-- Actions --}}
                            <td class="td-cell">
                                @if($patient->status === 'ONGOING')
                                    <div onclick="event.stopPropagation()">
    <a
        href="{{ route('ultrasound.edit', $u->id) }}"
        class="text-sm font-medium text-blue-600 hover:text-blue-800"
    >
        Edit
    </a>
</div>
                                @else
                                    <span class="text-xs text-gray-500">
                                        Read-only
                                    </span>
                                @endif
                            </td>

                        </tr>

                        <tr id="ultrasound-details-{{ $u->id }}" class="hidden bg-gray-50">
                            <td colspan="6" class="px-6 py-5">
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-900">Ultrasound Details</h3>

                                <div class="mt-4">
                                    <h4 class="text-sm font-semibold text-gray-700">Ultrasound Findings</h4>
                                    <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                                        <div>
                                            <dt class="text-xs text-gray-500">Scan Date</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ $formattedScanDate }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs text-gray-500">Gestational Age</dt>
                                            <dd class="mt-1 text-sm text-gray-900">{{ filled($u->gestational_age_scan) ? $u->gestational_age_scan . ' weeks' : 'Not recorded' }}</dd>
                                        </div>
                                        @foreach([
                                            'fetal_heartbeat' => 'Fetal Heartbeat',
                                            'fetal_movement' => 'Fetal Movement',
                                            'presentation' => 'Presentation',
                                            'amniotic_fluid' => 'Amniotic Fluid',
                                            'placenta_position' => 'Placenta Position',
                                            'estimated_fetal_weight' => 'Estimated Fetal Weight',
                                        ] as $usField => $usLabel)
                                            <div>
                                                <dt class="text-xs text-gray-500">{{ $usLabel }}</dt>
                                                <dd class="mt-1 text-sm text-gray-900">{{ filled($u->$usField) ? $u->$usField : 'Not recorded' }}</dd>
                                            </div>
                                        @endforeach
                                    </dl>
                                </div>

                                <div class="mt-5 border-t border-gray-200 pt-4">
                                    <h4 class="text-sm font-semibold text-gray-700">Remarks</h4>
                                    <p class="mt-2 whitespace-pre-line break-words text-sm text-gray-700">{{ filled($u->remarks) ? $u->remarks : 'No remarks recorded.' }}</p>
                                </div>

                                <div class="mt-5 border-t border-gray-200 pt-4">
                                    <h4 class="text-sm font-semibold text-gray-700">Attached Reports</h4>
                                    <div class="mt-2 flex flex-wrap items-center gap-3 text-sm">
                                        @if($usHasImage)
                                            <a href="#" data-full="{{ $usImageUrl }}" class="us-lightbox-trigger font-medium text-blue-600 hover:text-blue-800">View Image</a>
                                        @endif
                                        @if($usHasPdf)
                                            <a href="{{ $usPdfUrl }}" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation()" class="font-medium text-blue-600 hover:text-blue-800">View PDF</a>
                                        @endif
                                        @if(!$usHasImage && !$usHasPdf)
                                            <span class="text-gray-500">No report attached.</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                        </tr>

                    @endforeach

                </tbody>
            </table>
        </div>

    @else

        {{-- Empty State --}}
        <div class="px-6 py-10 text-center">
            <p class="text-sm font-semibold text-gray-700">
                No ultrasound records yet.
            </p>

            @if($patient->status === 'ONGOING')
                <p class="mt-1 text-sm text-gray-500">
                    Add an ultrasound record when scan findings become available.
                </p>
            @else
                <p class="mt-1 text-sm text-gray-500">
                    No ultrasound records were recorded for this pregnancy.
                </p>
            @endif
        </div>

    @endif

</section>
                <!-- Ultrasound image lightbox -->
                <div id="usLightbox" class="hidden fixed inset-0 z-[300] flex items-center justify-center bg-black/60 p-4">
                    <div class="relative max-w-3xl w-full bg-white rounded-xl shadow-xl overflow-hidden">
                        <button id="usLightboxClose" class="absolute top-2 right-2 w-9 h-9 flex items-center justify-center bg-gray-900/70 text-white rounded-full hover:bg-gray-900/90 text-xl leading-none" aria-label="Close">&times;</button>
                        <img id="usLightboxImg" src="" alt="Ultrasound image" class="w-full object-contain" style="max-height:80vh">
                    </div>
                </div>

                <script>
                function toggleUltrasoundDetails(id) {
                    const details = document.getElementById('ultrasound-details-' + id);
                    if (details) {
                        details.classList.toggle('hidden');
                    }
                }

                document.addEventListener('DOMContentLoaded', function () {
                    const lightbox = document.getElementById('usLightbox');
                    const lightboxImg = document.getElementById('usLightboxImg');
                    const lightboxClose = document.getElementById('usLightboxClose');

                    function openLightbox(src) {
                        lightboxImg.src = src;
                        lightbox.classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                    }

                    function closeLightbox() {
                        lightbox.classList.add('hidden');
                        lightboxImg.src = '';
                        document.body.style.overflow = '';
                    }

                    if (lightbox) {
                        document.querySelectorAll('.us-lightbox-trigger').forEach(function (el) {
                            el.addEventListener('click', function (e) {
                                e.preventDefault();
                                e.stopPropagation();
                                openLightbox(el.getAttribute('data-full'));
                            });
                        });

                        if (lightboxClose) {
                            lightboxClose.addEventListener('click', closeLightbox);
                        }

                        lightbox.addEventListener('click', function (e) {
                            if (e.target === lightbox) {
                                closeLightbox();
                            }
                        });

                        document.addEventListener('keydown', function (e) {
                            if (e.key === 'Escape') {
                                closeLightbox();
                            }
                        });
                    }
                });
                </script>

                <!-- =========================================================
     Phase 9: Birth Plan
========================================================= -->
<section id="birth-plan" class="panel scroll-mt-6">

    {{-- Header --}}
    <div class="panel-header">
        <div>
            <div class="panel-title">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
                    ></path>
                </svg>

                Birth Plan
            </div>

            <p class="mt-1 text-sm text-gray-500">
                Delivery preparation and support arrangements for this pregnancy.
            </p>
        </div>

        @if($patient->status === 'ONGOING')

            @if($patient->birthPlan)
                <a
                    href="{{ route('birth-plans.edit', $patient->birthPlan->id) }}"
                    class="btn btn-secondary"
                >
                    Edit Birth Plan
                </a>
            @else
                <a
                    href="{{ route('birth-plans.create', ['patient_id' => $patient->id]) }}"
                    class="btn btn-primary"
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
                            d="M12 4v16m8-8H4"
                        />
                    </svg>

                    Add Birth Plan
                </a>
            @endif

        @else
            <span class="text-sm text-gray-500">
                Historical record
            </span>
        @endif
    </div>


    @if($patient->birthPlan)

        <div class="panel-body">

            <dl class="grid grid-cols-1 gap-x-10 md:grid-cols-2">

                {{-- Planned Visits --}}
                <div class="border-b border-gray-100 py-4 first:pt-0">
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                        Planned Visits
                    </dt>

                    <dd class="mt-1.5 text-sm font-semibold text-gray-900">
                        {{ filled($patient->birthPlan->planned_visits)
                            ? $patient->birthPlan->planned_visits
                            : 'Not specified' }}
                    </dd>
                </div>


                {{-- Delivery Location --}}
                <div class="border-b border-gray-100 py-4 first:pt-0">
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                        Delivery Location
                    </dt>

                    <dd class="mt-1.5 text-sm font-semibold text-gray-900">
                        {{ filled($patient->birthPlan->delivery_location)
                            ? $patient->birthPlan->delivery_location
                            : 'Not specified' }}
                    </dd>
                </div>


                {{-- Transportation --}}
                <div class="border-b border-gray-100 py-4">
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                        Transportation
                    </dt>

                    <dd class="mt-1.5 text-sm font-semibold text-gray-900">
                        {{ filled($patient->birthPlan->transportation)
                            ? $patient->birthPlan->transportation
                            : 'Not specified' }}
                    </dd>
                </div>


                {{-- Payment Method --}}
                <div class="border-b border-gray-100 py-4">
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                        Payment Method
                    </dt>

                    <dd class="mt-1.5 text-sm font-semibold text-gray-900">
                        {{ filled($patient->birthPlan->payment_method)
                            ? $patient->birthPlan->payment_method
                            : 'Not specified' }}
                    </dd>
                </div>


                {{-- Birth Companion --}}
                <div class="py-4 md:pb-0">
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                        Birth Companion
                    </dt>

                    <dd class="mt-1.5 text-sm font-semibold text-gray-900">
                        {{ filled($patient->birthPlan->birth_companion)
                            ? $patient->birthPlan->birth_companion
                            : 'Not specified' }}
                    </dd>
                </div>


                {{-- Family Planning Method --}}
                <div class="py-4 md:pb-0">
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                        Family Planning Method
                    </dt>

                    <dd class="mt-1.5 text-sm font-semibold text-gray-900">
                        {{ filled($patient->birthPlan->family_planning_method)
                            ? $patient->birthPlan->family_planning_method
                            : 'Not specified' }}
                    </dd>
                </div>

            </dl>

        </div>

    @else

        {{-- Empty State --}}
        <div class="px-6 py-10 text-center">

            <p class="text-sm font-semibold text-gray-700">
                No birth plan recorded.
            </p>

            @if($patient->status === 'ONGOING')
                <p class="mt-1 text-sm text-gray-500">
                    Add a birth plan to document delivery preparation and support arrangements.
                </p>
            @else
                <p class="mt-1 text-sm text-gray-500">
                    No birth plan was recorded for this pregnancy.
                </p>
            @endif

        </div>

    @endif

</section>
           
            
                <!-- Risk Assessment Card -->
                <div id="risk-assessment" class="scroll-mt-6">
                @if($latestAssessment)
                @php
                    $ds = $latestAssessment->decision_source;
                    $rl = $latestAssessment->risk_level;
                    $bp = is_array($latestAssessment->bp_assessment) ? $latestAssessment->bp_assessment : [];
                    $bpCode = $bp['reason_code'] ?? null;
                    $urgency = $latestAssessment->urgency;

                    $latestMissingRecords = \App\Support\ListNormalizer::normalize($latestAssessment->missing_records);
                    $latestRuleReasons = \App\Support\ListNormalizer::normalize($latestAssessment->rule_reasons);
                    $latestRiskReasons = \App\Support\ListNormalizer::normalize($latestAssessment->risk_reasons);

                    $triggeredFactors = array_values(array_unique(array_merge($latestRuleReasons, $latestRiskReasons)));
                    if ($bpCode === 'BP-URG') {
                        $bpLabel = $bp['label'] ?? null;
                        if ($bpLabel && !in_array($bpLabel, $triggeredFactors, true)) {
                            array_unshift($triggeredFactors, $bpLabel);
                        }
                    }

                    $verificationLabels = [
                        'NOT_REQUIRED' => 'Not Required',
                        'PENDING_REPEAT' => 'Repeat Pending',
                        'REPEAT_COMPLETED' => 'Repeat Completed',
                        'UNABLE_TO_REPEAT' => 'Unable to Repeat',
                    ];
                    $repeatLabels = [
                        'NOT_RECORDED' => 'Not Recorded',
                        'NORMAL' => 'Normal Range',
                        'ELEVATED' => 'Elevated Range',
                        'SEVERE' => 'Severe Range',
                    ];
                    $urgencyLabels = [
                        'URGENT_CLINICAL_REVIEW' => 'Urgent Clinical Review',
                        'PROMPT' => 'Prompt Clinical Review',
                    ];

                    $verificationKey = $bp['verification_status'] ?? $latestAssessment->bp_verification_status;
                    $verificationDisplay = $verificationKey ? ($verificationLabels[$verificationKey] ?? $verificationKey) : 'Not Required';
                    $repeatDisplay = !empty($bp['repeat_interpretation']) ? ($repeatLabels[$bp['repeat_interpretation']] ?? $bp['repeat_interpretation']) : 'Not Recorded';
                    $urgencyDisplay = $urgency ? ($urgencyLabels[$urgency] ?? $urgency) : 'None';

                    $structuredFactors = \App\ValueObjects\ClinicalFactorEvidence::normalizeList($latestAssessment->factor_evidence);
                    $factorCategoryLabels = [
                        'MATERNAL_DEMOGRAPHICS' => 'Maternal Demographics',
                        'VITAL_SIGNS' => 'Vital Signs',
                        'CURRENT_CONDITION' => 'Current Conditions',
                        'OBSTETRIC_HISTORY' => 'Obstetric History',
                        'ULTRASOUND' => 'Ultrasound Findings',
                    ];
                    $groupedFactors = [];
                    foreach ($structuredFactors as $factor) {
                        $cat = $factor['category'] ?? 'OTHER';
                        $groupedFactors[$cat][] = $factor;
                    }

                    $assessmentMetadata = is_array($latestAssessment->assessment_metadata) ? $latestAssessment->assessment_metadata : [];
                    $structuredInteractions = \App\ValueObjects\ClinicalInteractionEvidence::normalizeList($assessmentMetadata['interaction_evidence'] ?? $latestAssessment->interaction_evidence ?? []);
                    $observedContextLabels = [
                        'ultrasound_inputs.amniotic_fluid' => 'Amniotic fluid',
                        'ultrasound_inputs.presentation' => 'Fetal presentation',
                    ];
                    $metadataContext = is_array($assessmentMetadata['context'] ?? null) ? $assessmentMetadata['context'] : [];
                    $metadataFlags = is_array($assessmentMetadata['data_quality_flags'] ?? null) ? $assessmentMetadata['data_quality_flags'] : [];
                    $metadataTrace = is_array($assessmentMetadata['decision_trace'] ?? null) ? $assessmentMetadata['decision_trace'] : [];
                    $metadataVersions = is_array($assessmentMetadata['versions'] ?? null) ? $assessmentMetadata['versions'] : [];
                    $flagSeverityStyles = [
                        'INFO' => 'bg-blue-100 text-blue-800',
                        'VERIFY' => 'bg-amber-100 text-amber-800',
                        'IMPORTANT' => 'bg-orange-100 text-orange-800',
                    ];
                    $traceStatusStyles = [
                        'COMPLETED' => 'bg-emerald-100 text-emerald-800',
                        'TRIGGERED' => 'bg-red-100 text-red-700',
                        'SKIPPED' => 'bg-gray-100 text-gray-500',
                        'BLOCKED' => 'bg-amber-100 text-amber-800',
                    ];
                @endphp
                <div class="panel">
                    {{-- =========================================================
     A–C. STAFF CLINICAL SUMMARY
========================================================= --}}

{{-- Header --}}
<div class="panel-header">
    <div>
        <div class="panel-title">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                ></path>
            </svg>

            Risk Assessment
        </div>

        <p class="mt-1 text-sm text-gray-500">
            Latest clinical risk assessment for this pregnancy.
        </p>
    </div>

    <div class="text-right">
        <span class="text-xs font-medium uppercase tracking-wide text-gray-400">
            Assessment Date
        </span>

        <p class="mt-1 text-sm font-medium text-gray-700">
            {{ $latestAssessment->visit_date
                ? \Carbon\Carbon::parse($latestAssessment->visit_date)->format('M d, Y')
                : 'Not recorded' }}
        </p>
    </div>
</div>


{{-- Risk Status --}}
<div class="
    px-6 py-4 border-b border-gray-100
    @if($rl === 'HIGH') bg-red-50
    @elseif($rl === 'LOW') bg-green-50
    @elseif($rl === 'ASSESSMENT INCOMPLETE') bg-amber-50
    @else bg-gray-50
    @endif
">
    <div class="flex flex-wrap items-center justify-between gap-3">

        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                Current Risk Classification
            </p>

            <div class="mt-2 flex flex-wrap items-center gap-2">

                @if($rl === 'HIGH')
                    <x-status-badge variant="danger">
                        High Risk
                    </x-status-badge>

                @elseif($rl === 'LOW')
                    <x-status-badge variant="success">
                        Low Risk
                    </x-status-badge>

                @elseif($rl === 'ASSESSMENT INCOMPLETE')
                    <x-status-badge variant="warning">
                        Assessment Incomplete
                    </x-status-badge>

                @else
                    <x-status-badge variant="neutral">
                        {{ $rl ?? 'No Assessment Available' }}
                    </x-status-badge>
                @endif


                @if($urgency === 'URGENT_CLINICAL_REVIEW')
                    <span class="inline-flex items-center rounded-full bg-red-600 px-3 py-1 text-xs font-bold text-white">
                        Urgent Clinical Review
                    </span>
                @endif

            </div>
        </div>


        {{-- Decision Source --}}
        <div class="sm:text-right">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                Decision Source
            </p>

            <div class="mt-2">

                @if($ds === 'RULE_BASED')
                    <span class="inline-flex items-center rounded-full bg-orange-100 px-3 py-1 text-xs font-semibold text-orange-800">
                        Rule-Based Clinical Assessment
                    </span>

                @elseif($ds === 'MACHINE_LEARNING')
                    <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-800">
                        Machine Learning Assessment
                    </span>

                @elseif($ds === 'COMPLETENESS')
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                        Required Records Check
                    </span>

                @elseif($ds === 'MACHINE_LEARNING_INVALID')
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">
                        Machine Learning Assessment Unavailable
                    </span>

                @elseif($ds === null)
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">
                        Legacy Assessment
                    </span>

                @else
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">
                        {{ $ds }}
                    </span>
                @endif

            </div>
        </div>

    </div>
</div>


{{-- Clinical Summary --}}
<div class="bg-white">
    <div class="px-6 py-6 space-y-5">

        {{-- Clinical Assessment --}}
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                Clinical Assessment
            </p>

            <p class="mt-2 text-sm leading-6 text-gray-800">
                {{ $latestAssessment->assessment ?: 'No clinical assessment recorded.' }}
            </p>
        </div>


        {{-- Recommendation --}}
        <div class="
            rounded-lg border-l-4 px-4 py-3
            @if($rl === 'HIGH')
                border-red-500 bg-red-50/60
            @elseif($rl === 'LOW')
                border-green-500 bg-green-50/60
            @else
                border-amber-500 bg-amber-50/60
            @endif
        ">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">
                Recommendation
            </p>

            <p class="mt-1.5 text-sm leading-6 text-gray-800">
                {{ $latestAssessment->recommendation ?: 'No recommendation recorded.' }}
            </p>
        </div>


        {{-- Follow-Up Information --}}
        <div class="border-t border-gray-100 pt-5">

            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                Follow-Up
            </p>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

                <div>
                    <p class="text-xs text-gray-500">
                        Next Scheduled Visit
                    </p>

                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <p class="text-sm font-semibold text-gray-900">
                            {{ $latestAssessment->next_visit_date
                                ? \Carbon\Carbon::parse($latestAssessment->next_visit_date)->format('M d, Y')
                                : 'Not scheduled' }}
                        </p>

                        @if(
                            $latestAssessment->next_visit_date &&
                            \Carbon\Carbon::parse($latestAssessment->next_visit_date)->isPast() &&
                            !\Carbon\Carbon::parse($latestAssessment->next_visit_date)->isToday()
                        )
                            <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-1 text-xs font-medium text-red-600">
                                Overdue
                            </span>
                        @elseif(
                            $latestAssessment->next_visit_date &&
                            \Carbon\Carbon::parse($latestAssessment->next_visit_date)->isToday()
                        )
                            <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">
                                Due Today
                            </span>
                        @endif
                    </div>
                </div>


                <div>
                    <p class="text-xs text-gray-500">
                        Assessment Date
                    </p>

                    <p class="mt-1 text-sm font-semibold text-gray-900">
                        {{ $latestAssessment->visit_date
                            ? \Carbon\Carbon::parse($latestAssessment->visit_date)->format('M d, Y')
                            : 'Not recorded' }}
                    </p>
                </div>

            </div>
        </div>


        

                            {{-- D. BLOOD PRESSURE CARD --}}
                            @if($bp)
                            <div class="rounded-xl border p-4 {{ $bpCode === 'BP-URG' ? 'border-red-300 bg-red-50' : ($bpCode === 'BP-H' ? 'border-amber-300 bg-amber-50' : 'border-gray-200 bg-white') }}">
                                <div class="flex items-center justify-between gap-2 mb-3">
                                    <span class="text-xs font-semibold uppercase tracking-wide {{ $bpCode === 'BP-URG' ? 'text-red-700' : ($bpCode === 'BP-H' ? 'text-amber-700' : 'text-gray-500') }}">Blood Pressure</span>
                                    @if(!empty($bp['label']))
                                    <span class="text-xs font-bold {{ $bpCode === 'BP-URG' ? 'text-red-700' : ($bpCode === 'BP-H' ? 'text-amber-700' : 'text-gray-600') }}">{{ $bp['label'] }}</span>
                                    @endif
                                </div>
                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 text-sm">
                                    <div>
                                        <dt class="text-gray-500">Initial BP</dt>
                                        <dd class="font-medium text-gray-900">{{ $latestAssessment->bp_sys }}/{{ $latestAssessment->bp_dia }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-500">Repeat BP</dt>
                                        <dd class="font-medium text-gray-900">{{ (!empty($latestAssessment->repeat_bp_sys) && !empty($latestAssessment->repeat_bp_dia)) ? $latestAssessment->repeat_bp_sys . '/' . $latestAssessment->repeat_bp_dia : 'Not recorded' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-500">Verification</dt>
                                        <dd class="font-medium text-gray-900">{{ $verificationDisplay }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-500">Repeat Interpretation</dt>
                                        <dd class="font-medium text-gray-900">{{ $repeatDisplay }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-500">Urgency</dt>
                                        <dd class="font-medium {{ $urgency === 'URGENT_CLINICAL_REVIEW' ? 'text-red-700' : 'text-gray-900' }}">{{ $urgencyDisplay }}</dd>
                                    </div>
                                </dl>
                                @if(!empty($bp['verification_note']))
                                <p class="mt-3 pt-2 border-t border-gray-200 text-xs text-gray-600"><span class="font-semibold">Verification Note:</span> {{ $bp['verification_note'] }}</p>
                                @endif
                            </div>
                            @endif

                            {{-- E. CLINICAL FACTORS IDENTIFIED --}}
                            @if($rl === 'HIGH' && !empty($structuredFactors))
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Clinical Factors Identified</span>
                                <div class="mt-3 space-y-3">
                                    @foreach($factorCategoryLabels as $cat => $catLabel)
                                        @if(!empty($groupedFactors[$cat]))
                                        <div>
                                            <p class="text-xs font-semibold text-gray-600">{{ $catLabel }}</p>
                                            <div class="mt-2 space-y-2">
                                                @foreach($groupedFactors[$cat] as $factor)
                                                <details class="group/factor rounded-lg border border-gray-200 bg-white px-3 py-2">
                                                    <summary class="flex items-center justify-between gap-2 cursor-pointer list-none">
                                                        <span class="flex flex-wrap items-center gap-x-2 min-w-0">
                                                            <span class="text-sm font-medium text-gray-800">{{ $factor['label'] ?? 'Clinical factor' }}</span>
                                                            <span class="text-xs text-gray-400 font-mono">{{ $factor['code'] ?? '' }}</span>
                                                            @if(($factor['category'] ?? null) === 'VITAL_SIGNS' && $bp)
                                                            <span class="text-xs text-gray-400">— see Blood Pressure details above</span>
                                                            @endif
                                                        </span>
                                                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0 group-open/factor:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                        </svg>
                                                    </summary>
                                                    <dl class="mt-2 pt-2 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5 text-sm">
                                                        <div>
                                                            <dt class="text-gray-500 text-xs">Observed value</dt>
                                                            <dd class="font-medium text-gray-800">{{ \App\ValueObjects\ClinicalFactorEvidence::displayObserved($factor['observed_value'] ?? null) }}</dd>
                                                        </div>
                                                        <div>
                                                            <dt class="text-gray-500 text-xs">Rule / threshold</dt>
                                                            <dd class="text-gray-700">{{ $factor['threshold_or_rule'] ?? '—' }}</dd>
                                                        </div>
                                                        @if(!empty($factor['explanation']))
                                                        <div class="sm:col-span-2">
                                                            <dt class="text-gray-500 text-xs">Explanation</dt>
                                                            <dd class="text-gray-700">{{ $factor['explanation'] }}</dd>
                                                        </div>
                                                        @endif
                                                        @if(!empty($factor['suggested_action']))
                                                        <div class="sm:col-span-2">
                                                            <dt class="text-gray-500 text-xs">Suggested action</dt>
                                                            <dd class="text-gray-700">{{ $factor['suggested_action'] }}</dd>
                                                        </div>
                                                        @endif
                                                    </dl>
                                                </details>
                                                @endforeach
                                            </div>
                                        </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                            @elseif($rl === 'HIGH')
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Triggered Clinical Rules</span>
                                @if(!empty($triggeredFactors))
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach($triggeredFactors as $factor)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium {{ $bpCode === 'BP-URG' && $factor === ($bp['label'] ?? null) ? 'bg-red-100 text-red-800' : 'bg-orange-100 text-orange-800' }}">{{ $factor }}</span>
                                    @endforeach
                                </div>
                                @else
                                <p class="mt-1 text-sm text-gray-600">No structured clinical factors recorded.</p>
                                @endif
                            </div>
                            @endif

                            {{-- E2. CLINICAL INTERACTIONS IDENTIFIED --}}
                            @if(!empty($structuredInteractions))
                            <div class="rounded-xl border border-violet-200 bg-violet-50 p-4">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 010 5.656M10.172 13.828a4 4 0 010-5.656M19 12a7 7 0 11-14 0 7 7 0 0114 0zM12 12h.01"></path>
                                    </svg>
                                    <span class="text-xs font-semibold uppercase tracking-wide text-violet-700">Clinical Interactions Identified</span>
                                </div>
                                <p class="mt-1 text-xs text-violet-700/70">Additional relationships between already-identified clinical findings. These support review and planning and do not change the final risk classification.</p>
                                <div class="mt-3 space-y-3">
                                    @foreach($structuredInteractions as $interaction)
                                    @php
                                        $contextLines = collect();
                                        foreach (($interaction['observed_context'] ?? []) as $path => $value) {
                                            if (isset($observedContextLabels[$path]) && $value !== null && trim((string) $value) !== '') {
                                                $contextLines->push($observedContextLabels[$path] . ': ' . $value);
                                            }
                                        }
                                    @endphp
                                    <details class="group/interaction rounded-lg border border-violet-200 bg-white px-3 py-2">
                                        <summary class="flex items-center justify-between gap-2 cursor-pointer list-none">
                                            <span class="flex flex-wrap items-center gap-x-2">
                                                <span class="text-sm font-medium text-gray-800">{{ $interaction['label'] ?? 'Clinical interaction' }}</span>
                                                <span class="text-xs text-violet-500 font-mono">{{ $interaction['code'] ?? '' }}</span>
                                            </span>
                                            <svg class="w-4 h-4 text-violet-400 flex-shrink-0 group-open/interaction:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </summary>
                                        <dl class="mt-2 pt-2 border-t border-violet-100 space-y-2 text-sm">
                                            @if(!empty($interaction['required_factor_codes']))
                                            <div>
                                                <dt class="text-gray-500 text-xs">Contributing factors</dt>
                                                <dd class="flex flex-wrap gap-1 mt-1">
                                                    @foreach($interaction['required_factor_codes'] as $code)
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-mono bg-violet-100 text-violet-700">{{ $code }}</span>
                                                    @endforeach
                                                </dd>
                                            </div>
                                            @endif
                                            @if($contextLines->isNotEmpty())
                                            <div>
                                                <dt class="text-gray-500 text-xs">Evaluated finding</dt>
                                                <dd class="mt-1">
                                                    @foreach($contextLines as $line)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-700 mr-1">{{ $line }}</span>
                                                    @endforeach
                                                </dd>
                                            </div>
                                            @endif
                                            @if(!empty($interaction['explanation']))
                                            <div>
                                                <dt class="text-gray-500 text-xs">Explanation</dt>
                                                <dd class="text-gray-700">{{ $interaction['explanation'] }}</dd>
                                            </div>
                                            @endif
                                            @if(!empty($interaction['suggested_action']))
                                            <div>
                                                <dt class="text-gray-500 text-xs">Suggested clinical follow-through</dt>
                                                <dd class="text-gray-700">{{ $interaction['suggested_action'] }}</dd>
                                            </div>
                                            @endif
                                        </dl>
                                    </details>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- F. REQUIRED RECORDS --}}
                            @if(!empty($latestMissingRecords))
                            <div class="rounded-xl border border-amber-300 bg-amber-50 p-4">
                                <span class="text-xs font-semibold uppercase tracking-wide text-amber-800">Required Records Still Missing</span>
                                <ul class="mt-2 space-y-1">
                                    @foreach($latestMissingRecords as $record)
                                    <li class="flex items-start space-x-2 text-sm text-amber-900">
                                        <span class="w-1.5 h-1.5 mt-1.5 bg-amber-500 rounded-full"></span>
                                        <span>{{ $record }}</span>
                                    </li>
                                    @endforeach
                                </ul>
                                <p class="mt-2 text-xs text-amber-700">Data-quality warning — the assessment may not reflect the full clinical picture.</p>
                            </div>
                            @endif

                            {{-- TECHNICAL ASSESSMENT DETAILS --}}
<details class="rounded-xl border border-gray-200 bg-gray-50 overflow-hidden">
    <summary class="cursor-pointer list-none px-4 py-4 hover:bg-gray-100 transition-colors">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">
                    Technical Assessment Details
                </h3>
                <p class="mt-1 text-xs text-gray-500">
                    View how the system processed and reached this assessment.
                </p>
            </div>

            <svg
                class="w-4 h-4 text-gray-400 shrink-0"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 9l-7 7-7-7"
                />
            </svg>
        </div>
    </summary>

    <div class="border-t border-gray-200 p-4 space-y-4">

                            {{-- G. MACHINE LEARNING DISPLAY --}}
                            @if($ds === 'MACHINE_LEARNING')
                            <div class="rounded-xl border border-blue-300 bg-blue-50 p-4">
                                <span class="text-xs font-semibold uppercase tracking-wide text-blue-700">Machine Learning Assessment</span>
                                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                                    <span class="text-gray-600">Prediction:</span>
                                    <span class="font-semibold {{ $latestAssessment->ml_prediction === 'HIGH' ? 'text-red-700' : 'text-green-700' }}">{{ $latestAssessment->ml_prediction ?? 'N/A' }}</span>
                                    <span class="text-gray-400">|</span>
                                    <span class="text-gray-600">Output Valid:</span>
                                    <span class="font-semibold {{ $latestAssessment->ml_valid ? 'text-green-700' : 'text-amber-700' }}">{{ $latestAssessment->ml_valid ? 'Yes' : 'No' }}</span>
                                </div>
                                <p class="mt-2 text-xs text-gray-600">The model prediction was used as the basis for this final assessment.</p>
                            </div>
                            @elseif($ds === 'MACHINE_LEARNING_INVALID')
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Machine Learning</span>
                                <p class="mt-1 text-sm text-gray-700">Machine learning output was unavailable or invalid.</p>
                            </div>
                            @else
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Machine Learning</span>
                                <p class="mt-1 text-sm text-gray-700">
                                    Machine learning was not used for the final decision.
                                    @if($ds === 'RULE_BASED') A deterministic clinical rule already determined the result.
                                    @elseif($ds === 'COMPLETENESS') Required records were incomplete.
                                    @else Legacy assessment without machine-learning metadata.
                                    @endif
                                </p>
                            </div>
                            @endif

                            {{-- H. DECISION FLOW --}}
                            <div class="rounded-xl border border-gray-200 bg-white p-4">
                                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Assessment Flow</span>
                                <dl class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5 text-sm">
                                    @if($bpCode === 'BP-URG')
                                        <div>
                                            <dt class="text-gray-500">Severe BP safety check</dt>
                                            <dd class="font-medium text-red-700">Triggered</dd>
                                        </div>
                                        <div>
                                            <dt class="text-gray-500">Required records check</dt>
                                            <dd class="font-medium {{ empty($latestMissingRecords) ? 'text-green-700' : 'text-amber-700' }}">{{ empty($latestMissingRecords) ? 'Completed' : 'Missing records noted' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-gray-500">Deterministic rules</dt>
                                            <dd class="font-medium text-red-700">HIGH</dd>
                                        </div>
                                        <div>
                                            <dt class="text-gray-500">Machine learning</dt>
                                            <dd class="text-gray-600">Skipped</dd>
                                        </div>
                                        <div>
                                            <dt class="text-gray-500">Final result</dt>
                                            <dd class="font-bold text-red-700">HIGH</dd>
                                        </div>
                                    @elseif($ds === 'RULE_BASED')
                                        <div>
                                            <dt class="text-gray-500">Required records check</dt>
                                            <dd class="font-medium text-green-700">Complete</dd>
                                        </div>
                                        <div>
                                            <dt class="text-gray-500">Deterministic rules</dt>
                                            <dd class="font-medium text-red-700">Triggered</dd>
                                        </div>
                                        <div>
                                            <dt class="text-gray-500">Machine learning</dt>
                                            <dd class="text-gray-600">Skipped</dd>
                                        </div>
                                        <div>
                                            <dt class="text-gray-500">Final result</dt>
                                            <dd class="font-bold {{ $rl === 'HIGH' ? 'text-red-700' : 'text-gray-700' }}">{{ $rl === 'HIGH' ? 'HIGH' : ($rl ?? '—') }}</dd>
                                        </div>
                                    @elseif($ds === 'COMPLETENESS')
                                        <div>
                                            <dt class="text-gray-500">Required records check</dt>
                                            <dd class="font-medium text-amber-700">Incomplete</dd>
                                        </div>
                                        <div>
                                            <dt class="text-gray-500">Deterministic rules</dt>
                                            <dd class="text-gray-600">Not finalized</dd>
                                        </div>
                                        <div>
                                            <dt class="text-gray-500">Machine learning</dt>
                                            <dd class="text-gray-600">Skipped</dd>
                                        </div>
                                        <div>
                                            <dt class="text-gray-500">Final result</dt>
                                            <dd class="font-bold text-amber-700">Assessment Incomplete</dd>
                                        </div>
                                    @elseif($ds === 'MACHINE_LEARNING')
                                        <div>
                                            <dt class="text-gray-500">Required records check</dt>
                                            <dd class="font-medium text-green-700">Complete</dd>
                                        </div>
                                        <div>
                                            <dt class="text-gray-500">Deterministic rules</dt>
                                            <dd class="text-gray-600">No HIGH rule</dd>
                                        </div>
                                        <div>
                                            <dt class="text-gray-500">Machine learning</dt>
                                            <dd class="font-medium text-blue-700">Valid prediction</dd>
                                        </div>
                                        <div>
                                            <dt class="text-gray-500">Final result</dt>
                                            <dd class="font-bold {{ $rl === 'HIGH' ? 'text-red-700' : 'text-green-700' }}">{{ $rl }}</dd>
                                        </div>
                                    @else
                                        <div class="sm:col-span-2">
                                            <dt class="text-gray-500">Assessment type</dt>
                                            <dd class="font-medium text-gray-700">Legacy assessment without a structured decision flow.</dd>
                                        </div>
                                    @endif
                                </dl>
                            </div>

                            {{-- I. ASSESSMENT CONTEXT USED --}}
                            @if(!empty($metadataContext))
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Assessment Context Used</span>
                                <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5 text-sm">
                                    <div>
                                        <dt class="text-gray-500">Ultrasound</dt>
                                        <dd class="font-medium text-gray-800">
                                            @if(!empty($metadataContext['ultrasound_date']))
                                                {{ \Carbon\Carbon::parse($metadataContext['ultrasound_date'])->format('M d, Y') }} <span class="text-xs text-gray-400">(record #{{ $metadataContext['ultrasound_id'] ?? '—' }})</span>
                                                @if(!empty($metadataContext['ultrasound_inputs']))
                                                <span class="mt-1 block text-[11px] text-gray-500">presentation: {{ $metadataContext['ultrasound_inputs']['presentation'] ?? '—' }} · fluid: {{ $metadataContext['ultrasound_inputs']['amniotic_fluid'] ?? '—' }} · heartbeat: {{ $metadataContext['ultrasound_inputs']['fetal_heartbeat'] ?? '—' }}</span>
                                                @endif
                                            @else
                                                <span class="text-gray-600">No ultrasound record</span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-500">Medical History</dt>
                                        <dd class="font-medium text-gray-800">{{ !empty($metadataContext['medical_history_exists']) ? 'Active record present' : 'No active record' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-500">Birth Plan</dt>
                                        <dd class="font-medium text-gray-800">{{ !empty($metadataContext['birth_plan_exists']) ? 'Active record present' : 'No active record' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-500">Assessment Date</dt>
                                        <dd class="font-medium text-gray-800">{{ $metadataContext['assessment_date'] ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-500">Patient Status</dt>
                                        <dd class="font-medium text-gray-800">{{ $metadataContext['patient_status'] ?? '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-500">Rules / Engine Versions</dt>
                                        <dd class="text-gray-700">rules v{{ $metadataVersions['clinical_rules'] ?? '—' }} · engine v{{ $metadataVersions['assessment_engine'] ?? '—' }} · ctx v{{ $metadataVersions['context'] ?? '—' }}</dd>
                                    </div>
                                </dl>
                            </div>
                            @endif

                            {{-- J. DATA REQUIRING VERIFICATION --}}
                            @if(!empty($metadataFlags))
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Data Requiring Verification</span>
                                <p class="mt-1 text-xs text-gray-500">Documentation items to verify. These do not change the clinical risk classification.</p>
                                <div class="mt-3 space-y-2">
                                    @foreach($metadataFlags as $flag)
                                    <details class="rounded-lg border border-gray-200 bg-white px-3 py-2">
                                        <summary class="flex items-center justify-between gap-2 cursor-pointer list-none">
                                            <span class="text-sm font-medium text-gray-800">{{ $flag['label'] ?? 'Verification item' }}</span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold {{ $flagSeverityStyles[$flag['severity'] ?? 'INFO'] ?? 'bg-gray-100 text-gray-700' }}">{{ $flag['severity'] ?? 'INFO' }}</span>
                                        </summary>
                                        <p class="mt-2 text-sm text-gray-700">{{ $flag['explanation'] ?? '' }}</p>
                                        @if(!empty($flag['suggested_verification']))
                                        <p class="mt-1 text-xs text-gray-600"><span class="font-semibold">Suggested verification:</span> {{ $flag['suggested_verification'] }}</p>
                                        @endif
                                        @if(!empty($flag['expected_condition']))
                                        <p class="mt-1 text-xs text-gray-500"><span class="font-semibold">Expected:</span> {{ $flag['expected_condition'] }}</p>
                                        @endif
                                    </details>
                                    @endforeach
                                </div>
                            </div>
                            @endif

                            {{-- K. ASSESSMENT DECISION PATH --}}
                            @if(!empty($metadataTrace))
                            <div class="rounded-xl border border-gray-200 bg-white p-4">
                                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Assessment Decision Path</span>
                                <ol class="mt-3 space-y-2">
                                    @foreach($metadataTrace as $step)
                                    <li class="flex items-start gap-3">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-100 text-indigo-800 shrink-0">{{ $step['step_code'] ?? 'STEP' }}</span>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-gray-800">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold {{ $traceStatusStyles[$step['status'] ?? ''] ?? 'bg-gray-100 text-gray-600' }}">{{ $step['status'] ?? '' }}</span>
                                            </p>
                                            <p class="mt-0.5 text-xs text-gray-600">{{ $step['summary'] ?? '' }}</p>
                                            @if(!empty($step['related_factor_codes']))
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                @foreach($step['related_factor_codes'] as $code)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono bg-gray-100 text-gray-600">{{ $code }}</span>
                                                @endforeach
                                            </div>
                                            @endif
                                            @if(!empty($step['related_interaction_codes']))
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                @foreach($step['related_interaction_codes'] as $code)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-mono bg-violet-100 text-violet-700">{{ $code }}</span>
                                                @endforeach
                                            </div>
                                            @endif
                                            @if(!empty($step['missing_records']))
                                            <p class="mt-1 text-[11px] text-amber-700"><span class="font-semibold">Missing:</span> {{ implode(', ', $step['missing_records']) }}</p>
                                            @endif
                                        </div>
                                    </li>
                                    @endforeach
                                </ol>
                            </div>
                            @endif

                            {{-- 16C: ASSESSMENT-LINKED REFERRAL ENTRY (HIGH + structured metadata only) --}}
                            @if(
                                $rl === 'HIGH'
                                && $patient->status === 'ONGOING'
                                && is_array($latestAssessment->assessment_metadata)
                                && count($latestAssessment->assessment_metadata) > 0
                            )
                            @php
                                $pendingForThisAssessment = $patient->referrals
                                    ->where('prenatal_visit_id', $latestAssessment->id)
                                    ->where('status', 'Pending')
                                    ->sortByDesc('id')
                                    ->first();
                            @endphp
                            @if($pendingForThisAssessment)
                            <a href="{{ route('referrals.show', $pendingForThisAssessment->id) }}"
                               class="inline-flex items-center justify-center gap-2 w-full px-4 py-3 bg-orange-100 text-orange-800 rounded-xl hover:bg-orange-200 transition font-semibold text-sm shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Pending Referral Exists
                            </a>
                            <p class="text-[11px] text-gray-500">A referral for this assessment is pending follow-through. The duplicate is protected server-side.</p>
                            @else
                            <a href="{{ route('referrals.create', ['id' => $patient->id, 'prenatal_visit_id' => $latestAssessment->id]) }}"
                               class="inline-flex items-center justify-center gap-2 w-full px-4 py-3 bg-red-600 text-white rounded-xl hover:bg-red-700 transition font-semibold text-sm shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Create Referral from this Assessment
                            </a>
                            <p class="text-[11px] text-gray-500">Links this assessment's evidence snapshot to the referral for traceability.</p>
                            @endif
                            @endif
                        </div>
                    </div>
                </div>
                @else
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                            Risk Assessment
                        </div>
                    </div>
                    <div class="px-5 py-4 border-b border-gray-100 bg-gray-50 flex items-center gap-3">
                        <x-status-badge variant="neutral">NO ASSESSMENT AVAILABLE</x-status-badge>
                    </div>
                    <div class="p-6 text-center">
                        <p class="text-gray-600">No prenatal risk assessment has been recorded for this patient.</p>
                        @if($patient->status === 'ONGOING')
                        <a href="{{ route('prenatal-visits.create', ['patient_id' => $patient->id]) }}" class="btn btn-primary mt-4">Add first visit</a>
                        @else
                        <p class="mt-2 text-sm text-gray-500">Historical pregnancy record.</p>
                        @endif
                    </div>
                </div>
                @endif

                </div><!-- End Risk Assessment anchor -->

                <!-- Referral Follow-through -->
@php
    $referrals = $patient->referrals
        ->sortByDesc(fn ($r) => [$r->referral_date?->timestamp ?? 0, $r->id]);

    $latestReferral = $referrals->first();

    $isAssessmentLinked = $latestReferral
        && $latestReferral->prenatal_visit_id
        && is_array($latestReferral->assessment_snapshot)
        && count($latestReferral->assessment_snapshot) > 0;

    $closedCount = $referrals->where('status', '!=', 'Pending')->count();
@endphp

<div id="referral-follow-through" class="panel scroll-mt-6">
    <div class="panel-header">
        <div>
            <div class="panel-title">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"
                    />
                </svg>

                Referral Follow-through
            </div>

            <p class="mt-1 text-sm text-gray-500">
                Referral status and follow-through for this pregnancy.
            </p>
        </div>

        <a
            href="{{ route('referrals.index') }}"
            class="text-sm font-medium text-blue-600 hover:text-blue-800"
        >
            Manage
        </a>
    </div>

    <div class="panel-body">
        @if($latestReferral)

            {{-- Current Referral --}}
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Current Referral
                    </p>

                    <h3 class="mt-1 text-base font-semibold text-gray-900">
                        {{ $latestReferral->referred_to ?: 'Referral destination not specified' }}
                    </h3>
                </div>

                <div class="shrink-0">
                    @if($latestReferral->status === 'Pending')
                        <x-status-badge variant="warning">
                            Pending Referral
                        </x-status-badge>
                    @elseif($latestReferral->status === 'Completed')
                        <x-status-badge variant="success">
                            Completed
                        </x-status-badge>
                    @elseif($latestReferral->status === 'Refused')
                        <x-status-badge variant="danger">
                            Refused
                        </x-status-badge>
                    @else
                        <x-status-badge variant="neutral">
                            Cancelled
                        </x-status-badge>
                    @endif
                </div>
            </div>

            {{-- Referral Information --}}
            <dl class="mt-5 grid grid-cols-1 gap-x-8 gap-y-5 border-t border-gray-100 pt-5 md:grid-cols-2">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                        Referred To
                    </dt>

                    <dd class="mt-1 text-sm font-medium text-gray-900">
                        {{ $latestReferral->referred_to ?: 'Not specified' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                        Referral Date
                    </dt>

                    <dd class="mt-1 text-sm font-medium text-gray-900">
                        {{ $latestReferral->referral_date?->format('M d, Y') ?? 'Not recorded' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                        Referral Source
                    </dt>

                    <dd class="mt-1">
                        @if($isAssessmentLinked)
                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                                Risk Assessment
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">
                                Manual Referral
                            </span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                        Follow-through
                    </dt>

                    <dd class="mt-1 text-sm font-medium text-gray-900">
                        @if($latestReferral->status === 'Completed')
                            Completed
                            @if($latestReferral->completed_at)
                                on {{ $latestReferral->completed_at->format('M d, Y') }}
                            @endif

                        @elseif($latestReferral->status === 'Refused')
                            Refused
                            @if($latestReferral->refusal_recorded_at)
                                on {{ $latestReferral->refusal_recorded_at->format('M d, Y') }}
                            @endif

                        @elseif($latestReferral->status === 'Pending')
                            Awaiting follow-through

                        @else
                            Cancelled
                        @endif
                    </dd>
                </div>
            </dl>

            {{-- Assessment Relationship --}}
            @if($isAssessmentLinked)
                <div class="mt-5 rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">
                        Linked to Clinical Assessment
                    </p>

                    <p class="mt-1 text-sm text-indigo-900">
                        This referral was created from a prenatal risk assessment and retains the assessment evidence snapshot for traceability.
                    </p>
                </div>
            @endif

            {{-- Action --}}
            <div class="mt-5 flex flex-col gap-3 border-t border-gray-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-xs text-gray-500">
                    {{ $referrals->count() }}
                    total referral{{ $referrals->count() === 1 ? '' : 's' }}

                    @if($closedCount > 0)
                        <span class="mx-1">·</span>
                        {{ $closedCount }} closed
                    @endif
                </div>

                <a
                    href="{{ route('referrals.show', $latestReferral->id) }}"
                    class="btn btn-secondary justify-center"
                >
                    View Referral
                </a>
            </div>

            {{-- Referral History --}}
            @if($referrals->count() > 1)
                <details class="mt-5 overflow-hidden rounded-lg border border-gray-200">
                    <summary class="cursor-pointer list-none px-4 py-3 hover:bg-gray-50">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-gray-900">
                                    Referral History
                                </p>

                                <p class="mt-0.5 text-xs text-gray-500">
                                    View previous referrals for this pregnancy.
                                </p>
                            </div>

                            <span class="text-xs font-medium text-gray-500">
                                {{ $referrals->count() - 1 }}
                                previous
                            </span>
                        </div>
                    </summary>

                    <div class="divide-y divide-gray-100 border-t border-gray-200">
                        @foreach($referrals->skip(1) as $referral)
                            <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">
                                        {{ $referral->referred_to ?: 'Referral destination not specified' }}
                                    </p>

                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ $referral->referral_date?->format('M d, Y') ?? 'Date not recorded' }}
                                        <span class="mx-1">·</span>
                                        {{ $referral->status }}
                                    </p>
                                </div>

                                <a
                                    href="{{ route('referrals.show', $referral->id) }}"
                                    class="text-sm font-medium text-blue-600 hover:text-blue-800"
                                >
                                    View Referral
                                </a>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif

        @else

            {{-- Empty State --}}
            <div class="py-6 text-center">
                <p class="text-sm font-medium text-gray-900">
                    No referral recorded
                </p>

                <p class="mt-1 text-sm text-gray-500">
                    No referral has been recorded for this pregnancy.
                </p>
            </div>

        @endif
    </div>
</div>
            </div>
        </div>
   

    <!-- Validation Error Modal -->
    <div id="downloadValidationModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4 py-6 sm:px-6">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Validation Error</h3>
                    <p class="text-sm text-gray-500">Patient data is incomplete. Please complete required information before downloading.</p>
                </div>
            </div>
            <div id="downloadValidationList" class="text-sm text-gray-700 mb-4"></div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeValidationModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">OK</button>
            </div>
        </div>
    </div>

    <!-- Select File Format Modal -->
    <div id="downloadFormatModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4 py-6 sm:px-6">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Select File Format</h3>
                    <p class="text-sm text-gray-500">Please choose the file format for download.</p>
                </div>
            </div>
            <div class="space-y-3">
                <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-blue-300 transition">
                    <input type="radio" name="download_format" value="pdf" checked class="h-4 w-4 text-blue-600" />
                    <span class="text-sm text-gray-700">Download as PDF</span>
                </label>
                <label class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-blue-300 transition">
                    <input type="radio" name="download_format" value="csv" class="h-4 w-4 text-blue-600" />
                    <span class="text-sm text-gray-700">Download as CSV</span>
                </label>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeFormatModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">Cancel</button>
                <button type="button" onclick="openDownloadConfirmModal()" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition font-medium">Continue</button>
            </div>
        </div>
    </div>

    <!-- Confirm Download Modal -->
    <div id="downloadConfirmModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4 py-6 sm:px-6">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Confirm Download</h3>
                    <p id="downloadConfirmText" class="text-sm text-gray-500">Are you sure you want to download this patient record as PDF?</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeDownloadConfirmModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">Cancel</button>
                <button type="button" onclick="submitPatientDownload()" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 transition font-medium">Download</button>
            </div>
        </div>
    </div>

    <!-- Download Success Modal -->
    <div id="downloadSuccessModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 backdrop-blur-sm px-4 py-6 sm:px-6">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900">Downloaded</h3>
                    <p class="text-sm text-gray-500">Patient record has been successfully downloaded.</p>
                </div>
            </div>
            <div class="flex justify-end mt-6">
                <button type="button" onclick="closeDownloadSuccessModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition font-medium">OK</button>
            </div>
        </div>
    </div>

    <!-- Simplified Delivery Modal -->
<div id="deliveryModal" class="delivery-modal-theme fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75" onclick="closeDeliveryModal()"></div>
        
        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form method="POST" action="{{ route('patients.deliver', $patient->id) }}">
                @csrf
                
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="bg-blue-600 rounded-full p-2">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Complete Delivery</h3>
                                <p class="text-sm text-gray-600">{{ $patient->first_name }} {{ $patient->last_name }}</p>
                            </div>
                        </div>
                        <button type="button" onclick="closeDeliveryModal()" class="text-gray-400 hover:text-gray-500">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="px-6 py-6 space-y-4">
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-lg">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    <strong>Important:</strong> This will mark the patient as delivered. Please confirm the delivery date.
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Delivery Date <span class="text-red-500">*</span>
                        </label>
                        <input type="date" 
                               name="delivery_date" 
                               value="{{ now()->format('Y-m-d') }}"
                               max="{{ now()->format('Y-m-d') }}"
                               required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Date of delivery</p>
                        @error('delivery_date')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Delivery Location <span class="text-red-500">*</span>
                        </label>
                        <select name="delivery_location" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="" disabled hidden>Select delivery location</option>
                            <option value="THIS_CLINIC" {{ old('delivery_location') === 'THIS_CLINIC' ? 'selected' : '' }}>This clinic</option>
                            <option value="ANOTHER_FACILITY" {{ old('delivery_location') === 'ANOTHER_FACILITY' ? 'selected' : '' }}>Another facility</option>
                            <option value="HOME" {{ old('delivery_location') === 'HOME' ? 'selected' : '' }}>Home</option>
                            <option value="OTHER" {{ old('delivery_location') === 'OTHER' ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('delivery_location')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Confirmation Source <span class="text-red-500">*</span>
                        </label>
                        <select name="confirmation_source" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="" disabled hidden>Select how the delivery was confirmed</option>
                            <option value="CLINIC_RECORD" {{ old('confirmation_source') === 'CLINIC_RECORD' ? 'selected' : '' }}>Clinic record</option>
                            <option value="PATIENT_REPORT" {{ old('confirmation_source') === 'PATIENT_REPORT' ? 'selected' : '' }}>Patient report</option>
                            <option value="OTHER_FACILITY_REPORT" {{ old('confirmation_source') === 'OTHER_FACILITY_REPORT' ? 'selected' : '' }}>Other facility report</option>
                            <option value="OTHER" {{ old('confirmation_source') === 'OTHER' ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('confirmation_source')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Outcome / Confirmation Notes (Optional)
                        </label>
                        <textarea name="outcome_notes"
                                  rows="3"
                                  placeholder="Optional note about how or where the delivery was confirmed."
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('outcome_notes') }}</textarea>
                        @error('outcome_notes')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Baby Information Section -->
                    <div class="border-t border-gray-200 pt-4">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-base font-semibold text-gray-800">Baby Information</h4>
                            <button type="button" onclick="addAnotherBaby()" class="inline-flex items-center gap-2 px-3 py-1.5 text-sm bg-blue-50 text-blue-700 border border-blue-200 rounded-lg hover:bg-blue-100 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                Add Another Baby
                            </button>
                        </div>

                        <div id="babiesContainer">
                            <!-- Baby 1 (Default) -->
                            <div class="baby-entry bg-gray-50 p-4 rounded-lg mb-4" data-baby-index="0">
                                <div class="flex items-center justify-between mb-3">
                                    <h5 class="font-medium text-gray-700">Baby 1</h5>
                                    <button type="button" onclick="removeBaby(this)" class="text-red-500 hover:text-red-700 text-sm opacity-0" style="display: none;">
                                        Remove
                                    </button>
                                </div>

                                <!-- Name Fields -->
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">First Name</label>
                                        <input type="text" name="babies[0][first_name]" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Middle Name</label>
                                        <input type="text" name="babies[0][middle_name]" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Last Name</label>
                                        <input type="text" name="babies[0][last_name]" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>

                                <!-- Details Row -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Sex</label>
                                        <select name="babies[0][sex]" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">Select Sex</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Date of Birth <span class="text-red-500">*</span></label>
                                            <input type="date" name="babies[0][date_of_birth]" required class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 mb-1">Time of Birth <span class="text-red-500">*</span></label>
                                            <input type="time" name="babies[0][time_of_birth]" required class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                    </div>
                                </div>

                                <!-- Measurements Row -->
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Birth Weight (kg)</label>
                                        <input type="number" name="babies[0][birth_weight]" step="0.01" min="0" max="10" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Birth Length (cm)</label>
                                        <input type="number" name="babies[0][birth_length]" step="0.1" min="0" max="100" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row justify-end gap-3">
                    <button type="button" onclick="closeDeliveryModal()" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Confirm Delivery
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script>
        // Toggle visit details
        function toggleVisitDetails(visitId) {
            const detailsRow = document.getElementById(`visit-details-${visitId}`);
            if (detailsRow) {
                detailsRow.classList.toggle('hidden');
            }
        }
        
        // Modal functions
        function openDeliveryModal() {
            const modal = document.getElementById('deliveryModal');
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        
        function closeDeliveryModal() {
            const modal = document.getElementById('deliveryModal');
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
        
        // Close modal on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const modal = document.getElementById('deliveryModal');
                if (!modal.classList.contains('hidden')) {
                    closeDeliveryModal();
                }
            }
        });
        
        // Prevent modal close when clicking inside modal content
        const modalContent = document.querySelector('#deliveryModal .inline-block');
        if (modalContent) {
            modalContent.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }

        @php
            $patientExportData = [
                'id' => $patient->id,
                'first_name' => $patient->first_name,
                'middle_name' => $patient->middle_name,
                'last_name' => $patient->last_name,
                'birthdate' => $patient->birthdate,
                'age' => $patient->age,
                'address' => $patient->address,
                'contact_number' => $patient->contact_number,
                'civil_status' => $patient->civil_status,
                'philhealth_member' => $patient->philhealth_member,
                'philhealth_number' => $patient->philhealth_number,
                'gravida' => $patient->gravida,
                'para' => $patient->para,
                'lmp' => $patient->lmp,
                'edd' => $patient->edd,
                'status' => $patient->status,
                'delivery_date' => $patient->delivery_date,
                'has_medical_history' => $patient->medicalHistory ? true : false,
            ];
        @endphp
        const patientExportData = @json($patientExportData);
        const downloadUrl = '{{ route('patients.download', $patient->id) }}';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        let selectedDownloadFormat = 'pdf';

        function startDownloadProcess() {
            const missing = getPatientDownloadMissingFields();
            if (missing.length > 0) {
                openValidationModal(missing);
                return;
            }
            openFormatModal();
        }

        function getPatientDownloadMissingFields() {
            const missing = [];
            if (!patientExportData.first_name) missing.push('First name');
            if (!patientExportData.last_name) missing.push('Last name');
            if (!patientExportData.birthdate) missing.push('Birthdate');
            if (!patientExportData.age) missing.push('Age');
            if (!patientExportData.address) missing.push('Address');
            if (!patientExportData.contact_number) missing.push('Contact number');
            if (!patientExportData.civil_status) missing.push('Civil status');
            if (patientExportData.philhealth_member === null) missing.push('PhilHealth membership status');
            if (patientExportData.philhealth_member && !patientExportData.philhealth_number) missing.push('PhilHealth number');
            if (patientExportData.gravida === null) missing.push('Gravida');
            if (patientExportData.para === null) missing.push('Para');
            if (!patientExportData.lmp) missing.push('LMP');
            if (!patientExportData.edd) missing.push('EDD');
            if (!patientExportData.has_medical_history) missing.push('Medical history');
            if (patientExportData.status !== 'ONGOING' && !patientExportData.delivery_date) missing.push('Delivery date');
            return missing;
        }

        function openValidationModal(missingFields) {
            const modal = document.getElementById('downloadValidationModal');
            const list = document.getElementById('downloadValidationList');
            list.innerHTML = '<ul class="list-disc list-inside space-y-1">' + missingFields.map(field => `<li>${field}</li>`).join('') + '</ul>';
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeValidationModal() {
            const modal = document.getElementById('downloadValidationModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function openFormatModal() {
            const modal = document.getElementById('downloadFormatModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeFormatModal() {
            const modal = document.getElementById('downloadFormatModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function openDownloadConfirmModal() {
            const format = document.querySelector('input[name="download_format"]:checked').value;
            selectedDownloadFormat = format;
            const confirmText = document.getElementById('downloadConfirmText');
            confirmText.textContent = `Are you sure you want to download this patient record as ${format.toUpperCase()}?`;
            closeFormatModal();
            const modal = document.getElementById('downloadConfirmModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeDownloadConfirmModal() {
            const modal = document.getElementById('downloadConfirmModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        async function submitPatientDownload() {
            closeDownloadConfirmModal();
            try {
                const response = await fetch(downloadUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/octet-stream',
                    },
                    body: JSON.stringify({ format: selectedDownloadFormat }),
                });

                if (!response.ok) {
                    const body = await response.json();
                    openValidationModal(body.missing ?? []);
                    return;
                }

                const blob = await response.blob();
                const disposition = response.headers.get('Content-Disposition');
                const filename = getFilenameFromDisposition(disposition) || `patient-${patientExportData.id}-record.${selectedDownloadFormat}`;
                const url = window.URL.createObjectURL(blob);
                const anchor = document.createElement('a');
                anchor.href = url;
                anchor.download = filename;
                document.body.appendChild(anchor);
                anchor.click();
                anchor.remove();
                window.URL.revokeObjectURL(url);
                openDownloadSuccessModal();
            } catch (error) {
                console.error('Download failed', error);
                openValidationModal(['Unexpected error. Please try again.']);
            }
        }

        function getFilenameFromDisposition(disposition) {
            if (!disposition) return null;
            const match = /filename="?([^";]+)"?/.exec(disposition);
            return match ? match[1] : null;
        }

        function openDownloadSuccessModal() {
            const modal = document.getElementById('downloadSuccessModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeDownloadSuccessModal() {
            const modal = document.getElementById('downloadSuccessModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Baby management functions
        let babyIndex = 1;

        function addAnotherBaby() {
            const container = document.getElementById('babiesContainer');
            const babyEntry = document.createElement('div');
            babyEntry.className = 'baby-entry bg-gray-50 p-4 rounded-lg mb-4';
            babyEntry.setAttribute('data-baby-index', babyIndex);

            babyEntry.innerHTML = `
                <div class="flex items-center justify-between mb-3">
                    <h5 class="font-medium text-gray-700">Baby ${babyIndex + 1}</h5>
                    <button type="button" onclick="removeBaby(this)" class="text-red-500 hover:text-red-700 text-sm">
                        Remove
                    </button>
                </div>

                <!-- Name Fields -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">First Name</label>
                        <input type="text" name="babies[${babyIndex}][first_name]" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Middle Name</label>
                        <input type="text" name="babies[${babyIndex}][middle_name]" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Last Name</label>
                        <input type="text" name="babies[${babyIndex}][last_name]" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <!-- Details Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Sex</label>
                        <select name="babies[${babyIndex}][sex]" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Select Sex</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Date of Birth <span class="text-red-500">*</span></label>
                            <input type="date" name="babies[${babyIndex}][date_of_birth]" required class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Time of Birth <span class="text-red-500">*</span></label>
                            <input type="time" name="babies[${babyIndex}][time_of_birth]" required class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                </div>

                <!-- Measurements Row -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Birth Weight (kg)</label>
                        <input type="number" name="babies[${babyIndex}][birth_weight]" step="0.01" min="0" max="10" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Birth Length (cm)</label>
                        <input type="number" name="babies[${babyIndex}][birth_length]" step="0.1" min="0" max="100" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
            `;

            container.appendChild(babyEntry);
            babyIndex++;

            // Show remove button on first baby if more than one baby
            updateRemoveButtons();
        }

        function removeBaby(button) {
            const babyEntry = button.closest('.baby-entry');
            babyEntry.remove();
            babyIndex--;

            // Renumber remaining babies
            const babies = document.querySelectorAll('.baby-entry');
            babies.forEach((baby, index) => {
                const title = baby.querySelector('h5');
                title.textContent = `Baby ${index + 1}`;

                // Update input names
                const inputs = baby.querySelectorAll('input, select');
                inputs.forEach(input => {
                    const name = input.name;
                    if (name) {
                        const newName = name.replace(/babies\[\d+\]/, `babies[${index}]`);
                        input.name = newName;
                    }
                });
            });

            updateRemoveButtons();
        }

        function updateRemoveButtons() {
            const babies = document.querySelectorAll('.baby-entry');
            const removeButtons = document.querySelectorAll('.baby-entry button[onclick*="removeBaby"]');

            if (babies.length > 1) {
                removeButtons.forEach(button => {
                    button.style.display = 'block';
                    button.style.opacity = '1';
                });
            } else {
                removeButtons.forEach(button => {
                    button.style.display = 'none';
                    button.style.opacity = '0';
                });
            }
        }

        // Baby editing functions
        function toggleBabyEdit(babyId) {
            const babyCard = document.querySelector(`.baby-card[data-baby-id="${babyId}"]`);
            const displayMode = babyCard.querySelector('.baby-display-mode');
            const editMode = babyCard.querySelector('.baby-edit-mode');
            const editBtn = babyCard.querySelector('.edit-baby-btn');

            if (displayMode.classList.contains('hidden')) {
                // Currently in edit mode, switch to display
                displayMode.classList.remove('hidden');
                editMode.classList.add('hidden');
                editBtn.innerHTML = `
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit
                `;
            } else {
                // Currently in display mode, switch to edit
                displayMode.classList.add('hidden');
                editMode.classList.remove('hidden');
                editBtn.innerHTML = `
                    <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    Cancel
                `;
            }
        }

        function cancelBabyEdit(babyId) {
            toggleBabyEdit(babyId);
        }

        // Handle baby edit form submission
        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('submit', function(e) {
                if (e.target.classList.contains('baby-edit-form')) {
                    e.preventDefault();
                    const form = e.target;
                    const babyId = form.getAttribute('data-baby-id');
                    const formData = new FormData(form);

                    // Validate required fields
                    const dateOfBirth = formData.get('date_of_birth');
                    const timeOfBirth = formData.get('time_of_birth');

                    if (!dateOfBirth || !timeOfBirth) {
                        showBabyMessage('Validation Error', 'Baby\'s date and time of birth are required.', 'error');
                        return;
                    }

                    // Submit the form
                    fetch(`{{ route('patients.update-baby', ':id') }}`.replace(':id', babyId), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update the display with new data
                            updateBabyDisplay(babyId, data.baby);
                            toggleBabyEdit(babyId);
                            showBabyMessage('Success', 'Baby information has been successfully updated.', 'success');
                        } else {
                            showBabyMessage('Error', data.message || 'Failed to update baby information.', 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showBabyMessage('Error', 'An unexpected error occurred. Please try again.', 'error');
                    });
                }
            });
        });

        function updateBabyDisplay(babyId, babyData) {
            const babyCard = document.querySelector(`.baby-card[data-baby-id="${babyId}"]`);
            const babyNameDisplay = babyCard.querySelector('.baby-name-display');

            // Update name
            const fullName = [babyData.first_name, babyData.middle_name, babyData.last_name].filter(Boolean).join(' ');
            babyNameDisplay.textContent = fullName;

            // Update sex badge
            let sexBadge = babyCard.querySelector('.sex-badge');
            if (!sexBadge) {
                const headerDiv = babyCard.querySelector('.flex.items-center.justify-between');
                sexBadge = document.createElement('span');
                sexBadge.className = 'sex-badge inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ml-2';
                headerDiv.appendChild(sexBadge);
            }

            if (babyData.sex) {
                sexBadge.className = `sex-badge inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                    ${babyData.sex === 'Male' ? 'bg-blue-100 text-blue-800' : 'bg-pink-100 text-pink-800'}`;
                sexBadge.innerHTML = `
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        ${babyData.sex === 'Male'
                            ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>'
                            : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>'
                        }
                    </svg>
                    ${babyData.sex}
                `;
            } else {
                sexBadge.style.display = 'none';
            }

            // Update date of birth
            const dobElement = babyCard.querySelector('.baby-display-mode .grid > div:nth-child(1) p');
            dobElement.textContent = babyData.date_of_birth
                ? new Date(babyData.date_of_birth).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
                : 'N/A';

            // Update time of birth
            const tobElement = babyCard.querySelector('.baby-display-mode .grid > div:nth-child(2) p');
            tobElement.textContent = babyData.time_of_birth
                ? new Date('1970-01-01T' + babyData.time_of_birth).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })
                : 'N/A';

            // Update birth weight
            const weightElement = babyCard.querySelector('.baby-display-mode .grid > div:nth-child(3) p');
            weightElement.textContent = babyData.birth_weight ? babyData.birth_weight + ' kg' : 'N/A';

            // Update birth length
            const lengthElement = babyCard.querySelector('.baby-display-mode .grid > div:nth-child(4) p');
            lengthElement.textContent = babyData.birth_length ? babyData.birth_length + ' cm' : 'N/A';
        }

        function showBabyMessage(title, message, type) {
            // Remove existing message
            const existingMessage = document.querySelector('.baby-message-notification');
            if (existingMessage) {
                existingMessage.remove();
            }

            // Create new message
            const messageDiv = document.createElement('div');
            messageDiv.className = `baby-message-notification fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg ${
                type === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'
            }`;
            messageDiv.innerHTML = `
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 ${type === 'success' ? 'text-green-400' : 'text-red-400'}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            ${type === 'success'
                                ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
                                : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>'
                            }
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium ${type === 'success' ? 'text-green-800' : 'text-red-800'}">${title}</h3>
                        <div class="mt-2 text-sm ${type === 'success' ? 'text-green-700' : 'text-red-700'}">
                            ${message}
                        </div>
                    </div>
                    <div class="ml-auto pl-3">
                        <button onclick="this.parentElement.parentElement.remove()" class="inline-flex rounded-md p-1.5 focus:outline-none focus:ring-2 focus:ring-gray-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(messageDiv);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (messageDiv.parentElement) {
                    messageDiv.remove();
                }
            }, 5000);
        }
    </script>

    @if($patient->status === 'ONGOING' && $monitoringEligible && auth()->user()->role !== 'admin')
        <x-outcome-confirm-modal />
    @endif
</x-app-layout>
