{{-- DEPLA Phase 3E.2 — My Medical Records (patient portal, read-only),
     extended by Phase 3E.3 with Ultrasound Records, Birth Plan, Delivery
     Records and Baby Information.
     Sections: Pregnancy Overview, Prenatal Visit History, Medical History,
     Ultrasound Records, Birth Plan, Delivery Records, Baby Information.
     Scope is the pregnancy row linked to THIS login — resolved by the
     controller from `patientAccount`, never from an ID in the URL — so no
     other patient's and no previous pregnancy's data can appear here.
     The view receives only plain presentation arrays built from explicit
     column allow-lists; no Eloquent model and no internal field name is
     reachable. Ultrasound reports, storage paths, staff remarks, birth
     plan notes and outcome confirmation provenance are never passed down.
     Strictly read-only: no edit, add, delete or staff action, and no form
     other than the layout's logout. --}}
@extends('patient.layout')

@section('content')
    <div class="mb-6">
        <a href="{{ route('patient.portal.coming-soon') }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold transition-colors hover:underline"
           style="color: var(--depla-dark-green);">
            &larr; Back to Dashboard
        </a>
    </div>

    <header class="mb-6">
        <h1 class="portal-heading text-2xl sm:text-3xl" style="color: var(--depla-navy);">
            My Medical Records
        </h1>
        <p class="mt-2 text-sm leading-6" style="color: var(--body-text);">
            Records for your currently linked pregnancy. This page is read-only.
        </p>
    </header>

    {{-- ═══ 1. Pregnancy Overview ═══ --}}
    <section class="mb-8 overflow-hidden rounded-2xl border bg-white"
             style="border-color: var(--light-border);"
             aria-labelledby="mr-overview-heading">
        <div class="border-b px-6 py-4" style="border-color: var(--light-border); background: #F6F4EE;">
            <h2 id="mr-overview-heading" class="text-sm font-bold uppercase tracking-wider" style="color: var(--depla-navy);">
                Pregnancy Overview
            </h2>
        </div>

        <dl class="divide-y" style="divide-color: var(--light-border);">
            <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:gap-6">
                <dt class="w-64 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                    Pregnancy Status
                </dt>
                <dd class="text-sm font-medium" style="color: var(--depla-navy);">
                    {{ $overview['status'] ?? 'Not recorded' }}
                </dd>
            </div>

            <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:gap-6">
                <dt class="w-64 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                    Last Menstrual Period (LMP)
                </dt>
                <dd class="text-sm font-medium" style="color: var(--depla-navy);">
                    {{ $overview['lmp'] ?? 'Not recorded' }}
                </dd>
            </div>

            <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:gap-6">
                <dt class="w-64 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                    Estimated Due Date (EDD)
                </dt>
                <dd class="text-sm font-medium" style="color: var(--depla-navy);">
                    {{ $overview['edd'] ?? 'Not recorded' }}
                </dd>
            </div>

            <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:gap-6">
                <dt class="w-64 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                    Gravida
                </dt>
                <dd class="text-sm font-medium" style="color: var(--depla-navy);">
                    {{ $overview['gravida'] ?? 'Not recorded' }}
                </dd>
            </div>

            <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:gap-6">
                <dt class="w-64 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                    Para
                </dt>
                <dd class="text-sm font-medium" style="color: var(--depla-navy);">
                    {{ $overview['para'] ?? 'Not recorded' }}
                </dd>
            </div>

            <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:gap-6">
                <dt class="w-64 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                    Previous Cesarean Section History
                </dt>
                <dd class="text-sm font-medium" style="color: var(--depla-navy);">
                    {{ $overview['previous_cs'] ?? 'Not recorded' }}
                </dd>
            </div>

            <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:gap-6">
                <dt class="w-64 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                    Miscarriage History
                </dt>
                <dd class="text-sm font-medium" style="color: var(--depla-navy);">
                    {{ $overview['miscarriage'] ?? 'Not recorded' }}
                </dd>
            </div>
        </dl>
    </section>

    {{-- ═══ 2. Prenatal Visit History ═══ --}}
    <section class="mb-8" aria-labelledby="mr-visits-heading">
        <h2 id="mr-visits-heading" class="mb-3 text-sm font-bold uppercase tracking-wider" style="color: var(--depla-navy);">
            Prenatal Visit History
        </h2>

        @if (count($visits) === 0)
            <p class="rounded-2xl border bg-white px-5 py-6 text-sm"
               style="border-color: var(--light-border); color: var(--body-text);">
                No prenatal visits are recorded for this pregnancy yet.
            </p>
        @else
            <div class="space-y-4">
                @foreach ($visits as $visit)
                    <article class="rounded-2xl border bg-white px-5 py-5"
                             style="border-color: var(--light-border);"
                             aria-label="Prenatal visit {{ $visit['visit_date'] ?? '' }}">
                        <p class="mb-4 text-xs font-bold uppercase tracking-wider" style="color: var(--depla-dark-green);">
                            {{ $visit['visit_date'] ?? 'Not recorded' }}
                        </p>

                        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Visit Date</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">{{ $visit['visit_date'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Gestational Age</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">
                                    {{ $visit['gestational_age'] !== null ? number_format((float) $visit['gestational_age'], 1, '.', '') . ' weeks' : 'Not recorded' }}
                                </dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Blood Pressure</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">
                                    {{ $visit['bp_sys'] !== null && $visit['bp_dia'] !== null ? $visit['bp_sys'] . '/' . $visit['bp_dia'] . ' mmHg' : 'Not recorded' }}
                                </dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Weight</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">
                                    {{ $visit['weight'] !== null ? \App\Support\WeightFormatter::formatKg($visit['weight']) . ' kg' : 'Not recorded' }}
                                </dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Temperature</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">
                                    {{ $visit['temperature'] !== null ? number_format((float) $visit['temperature'], 1, '.', '') . ' °C' : 'Not recorded' }}
                                </dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Fundal Height</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">{{ $visit['fundic_height'] ?: 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Fetal Heart Tone</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">{{ $visit['fetal_heart_tone'] ?: 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Fetal Movement</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">{{ $visit['fetal_movement'] ?: 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Next Visit Date</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">{{ $visit['next_visit_date'] ?? 'Not recorded' }}</dd>
                            </div>
                        </dl>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ═══ 3. Medical History ═══ --}}
    <section class="mb-8" aria-labelledby="mr-history-heading">
        <h2 id="mr-history-heading" class="mb-3 text-sm font-bold uppercase tracking-wider" style="color: var(--depla-navy);">
            Medical History
        </h2>

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
                        <div class="border-b px-6 py-4" style="border-color: var(--light-border); background: #F6F4EE;">
                            <h3 class="text-sm font-bold" style="color: var(--depla-navy);">{{ $groupLabel }}</h3>
                        </div>

                        <ul class="grid grid-cols-1 gap-3 px-6 py-5 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($rows as $row)
                                <li class="flex items-center justify-between gap-3 rounded-xl border px-4 py-3"
                                    style="border-color: var(--light-border);">
                                    <span class="text-sm" style="color: var(--depla-navy);">{{ $row['label'] }}</span>
                                    <span class="rounded-full px-3 py-1 text-xs font-bold"
                                          style="{{ $row['value'] === 'Yes' ? 'background: #EDF5EC; color: var(--depla-dark-green);' : 'background: #F6F4EE; color: #657083;' }}">
                                        {{ $row['value'] }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach

                @if ($medicalHistory['other'] !== null)
                    <div class="rounded-2xl border bg-white px-6 py-5"
                         style="border-color: var(--light-border);">
                        <p class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                            Other Recorded Condition
                        </p>
                        <p class="mt-1.5 text-sm font-medium" style="color: var(--depla-navy);">
                            {{ $medicalHistory['other'] }}
                        </p>
                    </div>
                @endif
            </div>
        @endif
    </section>

    {{-- ═══ 4. Ultrasound Records (Phase 3E.3) ═══ --}}
    <section class="mb-8" aria-labelledby="mr-ultrasounds-heading">
        <h2 id="mr-ultrasounds-heading" class="mb-3 text-sm font-bold uppercase tracking-wider" style="color: var(--depla-navy);">
            Ultrasound Records
        </h2>

        @if (count($ultrasounds) === 0)
            <p class="rounded-2xl border bg-white px-5 py-6 text-sm"
               style="border-color: var(--light-border); color: var(--body-text);">
                No ultrasound records are available for this pregnancy yet.
            </p>
        @else
            <div class="space-y-4">
                @foreach ($ultrasounds as $scan)
                    <article class="rounded-2xl border bg-white px-5 py-5"
                             style="border-color: var(--light-border);"
                             aria-label="Ultrasound scan {{ $scan['scan_date'] ?? '' }}">
                        <p class="mb-4 text-xs font-bold uppercase tracking-wider" style="color: var(--depla-dark-green);">
                            {{ $scan['scan_date'] ?? 'Not recorded' }}
                        </p>

                        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Scan Date</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">{{ $scan['scan_date'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Gestational Age from Scan</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">{{ $scan['gestational_age_scan'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Estimated Fetal Weight</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">{{ $scan['estimated_fetal_weight'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Fetal Heartbeat</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">{{ $scan['fetal_heartbeat'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Fetal Movement</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">{{ $scan['fetal_movement'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Presentation</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">{{ $scan['presentation'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Amniotic Fluid</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">{{ $scan['amniotic_fluid'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Placenta Position</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">{{ $scan['placenta_position'] ?? 'Not recorded' }}</dd>
                            </div>
                        </dl>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ═══ 5. Birth Plan (Phase 3E.3) ═══ --}}
    <section class="mb-8" aria-labelledby="mr-birth-plan-heading">
        <h2 id="mr-birth-plan-heading" class="mb-3 text-sm font-bold uppercase tracking-wider" style="color: var(--depla-navy);">
            Birth Plan
        </h2>

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
                    <li class="flex flex-col gap-2 px-6 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-6">
                        <span class="text-sm" style="color: var(--depla-navy);">{{ $answer['label'] }}</span>

                        @if ($answer['kind'] === 'yesno')
                            <span class="shrink-0 self-start rounded-full px-3 py-1 text-xs font-bold sm:self-auto"
                                  style="{{ $answer['value'] === 'Yes' ? 'background: #EDF5EC; color: var(--depla-dark-green);' : 'background: #F6F4EE; color: #657083;' }}">
                                {{ $answer['value'] }}
                            </span>
                        @else
                            <span class="text-sm font-medium sm:text-right" style="color: var(--depla-dark-green);">{{ $answer['value'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- ═══ 6. Delivery Records (Phase 3E.3) ═══ --}}
    <section class="mb-8" aria-labelledby="mr-delivery-heading">
        <h2 id="mr-delivery-heading" class="mb-3 text-sm font-bold uppercase tracking-wider" style="color: var(--depla-navy);">
            Delivery Records
        </h2>

        @if ($delivery === null)
            <p class="rounded-2xl border bg-white px-5 py-6 text-sm"
               style="border-color: var(--light-border); color: var(--body-text);">
                No delivery record is available for this pregnancy.
            </p>
        @else
            <dl class="divide-y overflow-hidden rounded-2xl border bg-white"
                style="border-color: var(--light-border); divide-color: var(--light-border);">
                <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:gap-6">
                    <dt class="w-64 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                        Pregnancy Outcome
                    </dt>
                    <dd class="text-sm font-medium" style="color: var(--depla-navy);">
                        {{ $delivery['outcome'] ?? 'Not recorded' }}
                    </dd>
                </div>

                <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:gap-6">
                    <dt class="w-64 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                        Delivery Date
                    </dt>
                    <dd class="text-sm font-medium" style="color: var(--depla-navy);">
                        {{ $delivery['delivery_date'] ?? 'Not recorded' }}
                    </dd>
                </div>

                <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:gap-6">
                    <dt class="w-64 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                        Delivery Location
                    </dt>
                    <dd class="text-sm font-medium" style="color: var(--depla-navy);">
                        {{ $delivery['delivery_location'] ?? 'Not recorded' }}
                    </dd>
                </div>
            </dl>
        @endif
    </section>

    {{-- ═══ 7. Baby Information (Phase 3E.3) ═══ --}}
    <section aria-labelledby="mr-babies-heading">
        <h2 id="mr-babies-heading" class="mb-3 text-sm font-bold uppercase tracking-wider" style="color: var(--depla-navy);">
            Baby Information
        </h2>

        @if (count($babies) === 0)
            <p class="rounded-2xl border bg-white px-5 py-6 text-sm"
               style="border-color: var(--light-border); color: var(--body-text);">
                No baby information is available for this pregnancy yet.
            </p>
        @else
            <div class="space-y-4">
                @foreach ($babies as $index => $baby)
                    <article class="rounded-2xl border bg-white px-5 py-5"
                             style="border-color: var(--light-border);"
                             aria-label="Baby {{ $index + 1 }}">
                        <p class="mb-4 text-xs font-bold uppercase tracking-wider" style="color: var(--depla-dark-green);">
                            Baby {{ $index + 1 }}
                        </p>

                        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Full Name</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">{{ $baby['name'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Sex</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">{{ $baby['sex'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Date of Birth</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">{{ $baby['date_of_birth'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Time of Birth</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">{{ $baby['time_of_birth'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Birth Weight</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">{{ $baby['birth_weight'] ?? 'Not recorded' }}</dd>
                            </div>

                            <div class="flex flex-col gap-0.5">
                                <dt class="text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">Birth Length</dt>
                                <dd class="text-sm font-medium" style="color: var(--depla-navy);">{{ $baby['birth_length'] ?? 'Not recorded' }}</dd>
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

        {{-- Read-only page: the only form anywhere near this view is the
             shared layout's POST logout. --}}
        <span class="text-xs" style="color: var(--body-text);">
            Use <span class="font-semibold">Log Out</span> in the header to end your session.
        </span>
    </div>
@endsection
