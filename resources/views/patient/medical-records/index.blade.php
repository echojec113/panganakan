{{-- DEPLA Phase 3E.2 — My Medical Records (patient portal, read-only),
     extended by Phase 3E.3 with Ultrasound Records, Birth Plan, Delivery
     Records and Baby Information, redesigned by Phase 3E.5 into a
     tabbed layout, and reused by Phase 3H.4 for verified historical
     episodes (Pregnancy History → View Records).
     Categories (7 tabs, one visible panel at a time): Pregnancy Overview,
     Prenatal Visit History, Medical History, Ultrasound Records, Birth
     Plan, Delivery Records, Baby Information.
     Scope is the episode the CONTROLLER resolved: the current pregnancy
     via `patientAccount` (this route never reads an ID from the URL), or
     - on the Phase 3H.4 historical route only - one STRICTLY verified
     episode resolved from user_pregnancy_links membership before this
     template renders. The optional $backRoute / $backLabel / $historical
     view variables are set ONLY by the controller (fixed route names from
     an application whitelist), never from request input, and default to
     the original Phase 3E.2 "Back to Dashboard" current-pregnancy wording.
     The view receives only plain presentation arrays built from explicit
     column allow-lists; no Eloquent model and no internal field name is
     reachable. Ultrasound reports, storage paths, staff remarks, birth
     plan notes and outcome confirmation provenance are never passed down.
     Strictly read-only: no edit, add, delete or staff action, and no form
     other than the layout's logout. Tab switching is a few lines of
     dependency-free inline JavaScript; every panel stays in the DOM in
     section order and clinical data is rendered exactly once. --}}
@extends('patient.layout')

@section('content')
    <style>
        #mr-tabs [role="tab"] {
            background: transparent;
            color: var(--depla-navy);
            outline-color: var(--depla-green);
        }

        #mr-tabs [role="tab"][aria-selected="true"] {
            background: var(--depla-dark-green);
            color: #FFFFFF;
        }

        #mr-tabs [role="tab"][aria-selected="false"]:hover {
            background: #EDF5EC;
        }

        #mr-tabs [role="tab"]:focus-visible {
            outline: 2px solid var(--depla-green);
            outline-offset: 2px;
        }

        [role="tabpanel"][hidden] {
            display: none !important;
        }

        [role="tabpanel"]:focus-visible {
            outline: 2px solid var(--depla-green);
            outline-offset: 4px;
        }
    </style>

    <div class="mb-5">
        {{-- Phase 3H.4: the back-link destination is a fixed, controller-
             supplied ROUTE NAME from an application whitelist - never an
             arbitrary user-provided URL. Defaults preserve the original
             Phase 3E.2 "Back to Dashboard" behavior for the current-
             pregnancy page. --}}
        @php
            $allowedBackRoutes = ['patient.portal.coming-soon', 'patient.pregnancies.index'];
            $backRouteName = $backRoute ?? 'patient.portal.coming-soon';
            if (! in_array($backRouteName, $allowedBackRoutes, true)) {
                $backRouteName = 'patient.portal.coming-soon';
            }
        @endphp
        <a href="{{ route($backRouteName) }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold transition-colors hover:underline"
           style="color: var(--depla-dark-green);">
            &larr; {{ $backLabel ?? 'Back to Dashboard' }}
        </a>
    </div>

    {{-- ═══ Page header ═══ --}}
    <header class="mb-5 overflow-hidden rounded-2xl border bg-white"
            style="border-color: var(--light-border);">
        <div class="flex flex-col gap-3 px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6 sm:py-6">
            <div class="min-w-0">
                <h1 class="portal-heading text-2xl leading-tight sm:text-3xl" style="color: var(--depla-navy);">
                    My Medical Records
                </h1>
                <p class="mt-1.5 max-w-2xl text-sm leading-6" style="color: var(--body-text);">
                    @if (! empty($historical))
                        You are viewing a previous pregnancy linked to your account. These records are read-only.
                    @else
                        Records for your currently linked pregnancy. This page is read-only.
                    @endif
                </p>
            </div>

            <span class="inline-flex shrink-0 self-start items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-bold uppercase tracking-wider sm:self-start"
                  style="background: #EDF5EC; color: var(--depla-dark-green);">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
                Read-only view
            </span>
        </div>

        <p class="border-t px-5 py-3 text-xs leading-5 sm:px-6"
           style="border-color: var(--light-border); color: var(--body-text);">
            Nothing on this page can be changed from your account. Use
            <span class="font-semibold">Log Out</span> in the header to end your session.
        </p>
    </header>

    {{-- ═══ Category navigation (7 tabs, one panel at a time) ═══ --}}
    <div class="mb-5 overflow-x-auto pb-1">
        <div id="mr-tabs" role="tablist" aria-label="Medical records categories"
             class="flex min-w-max gap-1 rounded-2xl border bg-white p-1.5"
             style="border-color: var(--light-border);">
            <button type="button" role="tab" id="mr-tab-overview"
                    aria-controls="mr-panel-overview" aria-selected="true" tabindex="0"
                    class="mr-tab shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-xs font-semibold transition-colors sm:text-sm">
                Pregnancy Overview
            </button>

            <button type="button" role="tab" id="mr-tab-visits"
                    aria-controls="mr-panel-visits" aria-selected="false" tabindex="-1"
                    class="mr-tab shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-xs font-semibold transition-colors sm:text-sm">
                Prenatal Visit History
            </button>

            <button type="button" role="tab" id="mr-tab-history"
                    aria-controls="mr-panel-history" aria-selected="false" tabindex="-1"
                    class="mr-tab shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-xs font-semibold transition-colors sm:text-sm">
                Medical History
            </button>

            <button type="button" role="tab" id="mr-tab-ultrasounds"
                    aria-controls="mr-panel-ultrasounds" aria-selected="false" tabindex="-1"
                    class="mr-tab shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-xs font-semibold transition-colors sm:text-sm">
                Ultrasound Records
            </button>

            <button type="button" role="tab" id="mr-tab-birth-plan"
                    aria-controls="mr-panel-birth-plan" aria-selected="false" tabindex="-1"
                    class="mr-tab shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-xs font-semibold transition-colors sm:text-sm">
                Birth Plan
            </button>

            <button type="button" role="tab" id="mr-tab-delivery"
                    aria-controls="mr-panel-delivery" aria-selected="false" tabindex="-1"
                    class="mr-tab shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-xs font-semibold transition-colors sm:text-sm">
                Delivery Records
            </button>

            <button type="button" role="tab" id="mr-tab-babies"
                    aria-controls="mr-panel-babies" aria-selected="false" tabindex="-1"
                    class="mr-tab shrink-0 whitespace-nowrap rounded-xl px-3.5 py-2 text-xs font-semibold transition-colors sm:text-sm">
                Baby Information
            </button>
        </div>
    </div>

    {{-- ═══ 1. Pregnancy Overview ═══ --}}
    <section role="tabpanel" id="mr-panel-overview" aria-labelledby="mr-tab-overview" tabindex="0"
             aria-label="Pregnancy Overview">
        <div class="overflow-hidden rounded-2xl border bg-white"
             style="border-color: var(--light-border);">
            <dl class="divide-y" style="divide-color: var(--light-border);">
                <div class="flex flex-col gap-1 px-5 py-3.5 sm:flex-row sm:items-baseline sm:gap-6 sm:px-6">
                    <dt class="w-64 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                        Pregnancy Status
                    </dt>
                    <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">
                        {{ $overview['status'] ?? 'Not recorded' }}
                    </dd>
                </div>

                <div class="flex flex-col gap-1 px-5 py-3.5 sm:flex-row sm:items-baseline sm:gap-6 sm:px-6">
                    <dt class="w-64 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                        Last Menstrual Period (LMP)
                    </dt>
                    <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">
                        {{ $overview['lmp'] ?? 'Not recorded' }}
                    </dd>
                </div>

                <div class="flex flex-col gap-1 px-5 py-3.5 sm:flex-row sm:items-baseline sm:gap-6 sm:px-6">
                    <dt class="w-64 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                        Estimated Due Date (EDD)
                    </dt>
                    <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">
                        {{ $overview['edd'] ?? 'Not recorded' }}
                    </dd>
                </div>

                <div class="flex flex-col gap-1 px-5 py-3.5 sm:flex-row sm:items-baseline sm:gap-6 sm:px-6">
                    <dt class="w-64 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                        Gravida
                    </dt>
                    <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">
                        {{ $overview['gravida'] ?? 'Not recorded' }}
                    </dd>
                </div>

                <div class="flex flex-col gap-1 px-5 py-3.5 sm:flex-row sm:items-baseline sm:gap-6 sm:px-6">
                    <dt class="w-64 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                        Para
                    </dt>
                    <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">
                        {{ $overview['para'] ?? 'Not recorded' }}
                    </dd>
                </div>

                <div class="flex flex-col gap-1 px-5 py-3.5 sm:flex-row sm:items-baseline sm:gap-6 sm:px-6">
                    <dt class="w-64 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                        Previous Cesarean Section History
                    </dt>
                    <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">
                        {{ $overview['previous_cs'] ?? 'Not recorded' }}
                    </dd>
                </div>

                <div class="flex flex-col gap-1 px-5 py-3.5 sm:flex-row sm:items-baseline sm:gap-6 sm:px-6">
                    <dt class="w-64 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                        Miscarriage History
                    </dt>
                    <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">
                        {{ $overview['miscarriage'] ?? 'Not recorded' }}
                    </dd>
                </div>
            </dl>
        </div>
    </section>

    {{-- ═══ 2. Prenatal Visit History ═══ --}}
    <section role="tabpanel" id="mr-panel-visits" aria-labelledby="mr-tab-visits" tabindex="0"
             aria-label="Prenatal Visit History" hidden>
        @if (count($visits) === 0)
            <p class="rounded-2xl border bg-white px-5 py-6 text-sm"
               style="border-color: var(--light-border); color: var(--body-text);">
                No prenatal visits are recorded for this pregnancy yet.
            </p>
        @else
            <div class="space-y-4">
                @foreach ($visits as $visit)
                    <article class="overflow-hidden rounded-2xl border bg-white px-5 py-5"
                             style="border-color: var(--light-border);"
                             aria-label="Prenatal visit {{ $visit['visit_date'] ?? '' }}">
                        <p class="mb-4 text-xs font-bold uppercase tracking-wider" style="color: var(--depla-dark-green);">
                            {{ $visit['visit_date'] ?? 'Not recorded' }}
                        </p>

                        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Visit Date</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">{{ $visit['visit_date'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Gestational Age</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">
                                    {{ $visit['gestational_age'] !== null ? number_format((float) $visit['gestational_age'], 1, '.', '') . ' weeks' : 'Not recorded' }}
                                </dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Blood Pressure</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">
                                    {{ $visit['bp_sys'] !== null && $visit['bp_dia'] !== null ? $visit['bp_sys'] . '/' . $visit['bp_dia'] . ' mmHg' : 'Not recorded' }}
                                </dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Weight</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">
                                    {{ $visit['weight'] !== null ? \App\Support\WeightFormatter::formatKg($visit['weight']) . ' kg' : 'Not recorded' }}
                                </dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Temperature</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">
                                    {{ $visit['temperature'] !== null ? number_format((float) $visit['temperature'], 1, '.', '') . ' °C' : 'Not recorded' }}
                                </dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Fundal Height</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">{{ $visit['fundic_height'] ?: 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Fetal Heart Tone</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">{{ $visit['fetal_heart_tone'] ?: 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Fetal Movement</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">{{ $visit['fetal_movement'] ?: 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Next Visit Date</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">{{ $visit['next_visit_date'] ?? 'Not recorded' }}</dd>
                            </div>
                        </dl>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ═══ 3. Medical History ═══ --}}
    <section role="tabpanel" id="mr-panel-history" aria-labelledby="mr-tab-history" tabindex="0"
             aria-label="Medical History" hidden>
        @if ($medicalHistory === null)
            <p class="rounded-2xl border bg-white px-5 py-6 text-sm"
               style="border-color: var(--light-border); color: var(--body-text);">
                No medical history has been recorded for this pregnancy yet.
            </p>
        @else
            <div class="space-y-5">
                @foreach ($medicalHistory['groups'] as $groupLabel => $rows)
                    <div class="overflow-hidden rounded-2xl border bg-white"
                         style="border-color: var(--light-border);">
                        <div class="border-b px-5 py-3.5 sm:px-6" style="border-color: var(--light-border); background: #F6F4EE;">
                            <h3 class="text-sm font-bold" style="color: var(--depla-navy);">{{ $groupLabel }}</h3>
                        </div>

                        <ul class="grid grid-cols-1 gap-3 px-5 py-5 sm:grid-cols-2 sm:px-6 lg:grid-cols-3">
                            @foreach ($rows as $row)
                                <li class="flex items-center justify-between gap-3 rounded-xl border px-4 py-3"
                                    style="border-color: var(--light-border);">
                                    <span class="text-sm break-words" style="color: var(--depla-navy);">{{ $row['label'] }}</span>
                                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold"
                                          style="{{ $row['value'] === 'Yes' ? 'background: #EDF5EC; color: var(--depla-dark-green);' : 'background: #F6F4EE; color: #657083;' }}">
                                        {{ $row['value'] }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach

                @if ($medicalHistory['other'] !== null)
                    <div class="rounded-2xl border bg-white px-5 py-5 sm:px-6"
                         style="border-color: var(--light-border);">
                        <p class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                            Other Recorded Condition
                        </p>
                        <p class="mt-1.5 text-sm font-medium break-words" style="color: var(--depla-navy);">
                            {{ $medicalHistory['other'] }}
                        </p>
                    </div>
                @endif
            </div>
        @endif
    </section>

    {{-- ═══ 4. Ultrasound Records (Phase 3E.3) ═══ --}}
    <section role="tabpanel" id="mr-panel-ultrasounds" aria-labelledby="mr-tab-ultrasounds" tabindex="0"
             aria-label="Ultrasound Records" hidden>
        @if (count($ultrasounds) === 0)
            <p class="rounded-2xl border bg-white px-5 py-6 text-sm"
               style="border-color: var(--light-border); color: var(--body-text);">
                No ultrasound records are available for this pregnancy yet.
            </p>
        @else
            <div class="space-y-4">
                @foreach ($ultrasounds as $scan)
                    <article class="overflow-hidden rounded-2xl border bg-white px-5 py-5"
                             style="border-color: var(--light-border);"
                             aria-label="Ultrasound scan {{ $scan['scan_date'] ?? '' }}">
                        <p class="mb-4 text-xs font-bold uppercase tracking-wider" style="color: var(--depla-dark-green);">
                            {{ $scan['scan_date'] ?? 'Not recorded' }}
                        </p>

                        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Scan Date</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">{{ $scan['scan_date'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Gestational Age from Scan</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">{{ $scan['gestational_age_scan'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Estimated Fetal Weight</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">{{ $scan['estimated_fetal_weight'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Fetal Heartbeat</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">{{ $scan['fetal_heartbeat'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Fetal Movement</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">{{ $scan['fetal_movement'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Presentation</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">{{ $scan['presentation'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Amniotic Fluid</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">{{ $scan['amniotic_fluid'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Placenta Position</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">{{ $scan['placenta_position'] ?? 'Not recorded' }}</dd>
                            </div>
                        </dl>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ═══ 5. Birth Plan (Phase 3E.3) ═══ --}}
    <section role="tabpanel" id="mr-panel-birth-plan" aria-labelledby="mr-tab-birth-plan" tabindex="0"
             aria-label="Birth Plan" hidden>
        @if ($birthPlan === null)
            <p class="rounded-2xl border bg-white px-5 py-6 text-sm"
               style="border-color: var(--light-border); color: var(--body-text);">
                No birth plan has been recorded for this pregnancy yet.
            </p>
        @elseif (count($birthPlan) === 0)
            <p class="rounded-2xl border bg-white px-5 py-6 text-sm"
               style="border-color: var(--light-border); color: var(--body-text);">
                A birth plan exists for this pregnancy, but none of its answers are recorded yet.
            </p>
        @else
            <ul class="divide-y overflow-hidden rounded-2xl border bg-white"
                style="border-color: var(--light-border); divide-color: var(--light-border);">
                @foreach ($birthPlan as $answer)
                    <li class="flex flex-col gap-2 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:px-6">
                        <span class="text-sm break-words" style="color: var(--depla-navy);">{{ $answer['label'] }}</span>

                        @if ($answer['kind'] === 'yesno')
                            <span class="shrink-0 self-start rounded-full px-3 py-1 text-xs font-bold sm:self-auto"
                                  style="{{ $answer['value'] === 'Yes' ? 'background: #EDF5EC; color: var(--depla-dark-green);' : 'background: #F6F4EE; color: #657083;' }}">
                                {{ $answer['value'] }}
                            </span>
                        @else
                            <span class="text-sm font-medium break-words sm:text-right" style="color: var(--depla-dark-green);">{{ $answer['value'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- ═══ 6. Delivery Records (Phase 3E.3) ═══ --}}
    <section role="tabpanel" id="mr-panel-delivery" aria-labelledby="mr-tab-delivery" tabindex="0"
             aria-label="Delivery Records" hidden>
        @if ($delivery === null)
            <p class="rounded-2xl border bg-white px-5 py-6 text-sm"
               style="border-color: var(--light-border); color: var(--body-text);">
                No delivery record is available for this pregnancy.
            </p>
        @else
            <dl class="divide-y overflow-hidden rounded-2xl border bg-white"
                style="border-color: var(--light-border); divide-color: var(--light-border);">
                <div class="flex flex-col gap-1 px-5 py-3.5 sm:flex-row sm:items-baseline sm:gap-6 sm:px-6">
                    <dt class="w-64 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                        Pregnancy Outcome
                    </dt>
                    <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">
                        {{ $delivery['outcome'] ?? 'Not recorded' }}
                    </dd>
                </div>

                <div class="flex flex-col gap-1 px-5 py-3.5 sm:flex-row sm:items-baseline sm:gap-6 sm:px-6">
                    <dt class="w-64 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                        Delivery Date
                    </dt>
                    <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">
                        {{ $delivery['delivery_date'] ?? 'Not recorded' }}
                    </dd>
                </div>

                <div class="flex flex-col gap-1 px-5 py-3.5 sm:flex-row sm:items-baseline sm:gap-6 sm:px-6">
                    <dt class="w-64 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                        Delivery Location
                    </dt>
                    <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">
                        {{ $delivery['delivery_location'] ?? 'Not recorded' }}
                    </dd>
                </div>
            </dl>
        @endif
    </section>

    {{-- ═══ 7. Baby Information (Phase 3E.3) ═══ --}}
    <section role="tabpanel" id="mr-panel-babies" aria-labelledby="mr-tab-babies" tabindex="0"
             aria-label="Baby Information" hidden>
        @if (count($babies) === 0)
            <p class="rounded-2xl border bg-white px-5 py-6 text-sm"
               style="border-color: var(--light-border); color: var(--body-text);">
                No baby information is available for this pregnancy yet.
            </p>
        @else
            <div class="space-y-4">
                @foreach ($babies as $index => $baby)
                    <article class="overflow-hidden rounded-2xl border bg-white px-5 py-5"
                             style="border-color: var(--light-border);"
                             aria-label="Baby {{ $index + 1 }}">
                        <p class="mb-4 text-xs font-bold uppercase tracking-wider" style="color: var(--depla-dark-green);">
                            Baby {{ $index + 1 }}
                        </p>

                        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Full Name</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">{{ $baby['name'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Sex</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">{{ $baby['sex'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Date of Birth</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">{{ $baby['date_of_birth'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Time of Birth</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">{{ $baby['time_of_birth'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Birth Weight</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">{{ $baby['birth_weight'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Birth Length</dt>
                                <dd class="text-sm font-medium break-words" style="color: var(--depla-navy);">{{ $baby['birth_length'] ?? 'Not recorded' }}</dd>
                            </div>
                        </dl>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <div class="mt-8 flex flex-wrap items-center gap-3">
        <a href="{{ route('patient.portal.coming-soon') }}"
           class="rounded-xl px-5 py-2.5 text-sm font-semibold text-white transition hover:opacity-90"
           style="background: var(--depla-dark-green);">
            Back to Dashboard
        </a>
    </div>

    {{-- Dependency-free tab switching. Every panel stays in the DOM (hidden
         with the native `hidden` attribute), so with JavaScript disabled the
         page still renders its first category correctly. --}}
    <script>
        (function () {
            var list = document.getElementById('mr-tabs');
            if (!list) {
                return;
            }

            var tabs = Array.prototype.slice.call(list.querySelectorAll('[role="tab"]'));

            function selectTab(next) {
                tabs.forEach(function (tab) {
                    var isActive = tab === next;
                    tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    tab.setAttribute('tabindex', isActive ? '0' : '-1');

                    var panel = document.getElementById(tab.getAttribute('aria-controls'));
                    if (!panel) {
                        return;
                    }
                    if (isActive) {
                        panel.removeAttribute('hidden');
                    } else {
                        panel.setAttribute('hidden', 'hidden');
                    }
                });
            }

            tabs.forEach(function (tab, index) {
                tab.addEventListener('click', function () {
                    selectTab(tab);
                });

                tab.addEventListener('keydown', function (event) {
                    var next = null;

                    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                        next = tabs[(index + 1) % tabs.length];
                    } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                        next = tabs[(index - 1 + tabs.length) % tabs.length];
                    } else if (event.key === 'Home') {
                        next = tabs[0];
                    } else if (event.key === 'End') {
                        next = tabs[tabs.length - 1];
                    }

                    if (next) {
                        event.preventDefault();
                        selectTab(next);
                        next.focus();
                    }
                });
            });
        })();
    </script>
@endsection
