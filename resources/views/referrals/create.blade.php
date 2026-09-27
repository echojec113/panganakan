<x-app-layout>
    @php
        $linked = $linkedVisit && is_array($snapshot);

        $observedContextLabels = [
            'ultrasound_inputs.amniotic_fluid' => 'Amniotic fluid',
            'ultrasound_inputs.presentation' => 'Fetal presentation',
        ];

        $patientName = trim(
            $patient->first_name . ' ' .
            ($patient->middle_name ?? '') . ' ' .
            $patient->last_name
        );
    @endphp

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

        {{-- =====================================================
            PAGE HEADER
        ====================================================== --}}
        <x-app-header
            title="Create Referral"
            subtitle="Refer {{ $patient->first_name }} {{ $patient->last_name }} to a hospital or OB-GYN specialist."
            class="mb-6"
        >
            <x-slot name="actions">
                <a href="{{ route('referrals.select-patient') }}"
                   class="btn btn-secondary">
                    Back to Referrals
                </a>
            </x-slot>
        </x-app-header>

        <x-error-summary
            :errors="$errors"
            title="Please review the highlighted issues."
            class="mb-5"
        />


        {{-- =====================================================
            PATIENT SUMMARY
        ====================================================== --}}
        <div class="mb-5 overflow-hidden rounded-xl border border-[#E7E9E5] bg-white">
            <div class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">

                <div class="min-w-0">
                    <div class="mb-1 flex flex-wrap items-center gap-2">

                        <span class="text-[11px] font-semibold uppercase tracking-wider text-[#657083]">
                            Patient
                        </span>

                        <span class="inline-flex items-center rounded-full bg-[#EDF5EC] px-2.5 py-1 text-[11px] font-semibold text-[#367E4B]">
                            Ongoing Pregnancy
                        </span>
                    </div>

                    <p class="truncate text-base font-semibold text-[#19355F]">
                        {{ $patientName }}
                    </p>

                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-[#657083]">

                        <span>
                            Record #{{ $patient->id }}
                        </span>

                        <span class="text-[#CBD0D6]">•</span>

                        <span>
                            Birthdate:
                            {{ $patient->birthdate?->format('M d, Y') ?? 'Not recorded' }}
                        </span>

                        <span class="text-[#CBD0D6]">•</span>

                        <span>
                            EDD:
                            {{ $patient->edd?->format('M d, Y') ?? 'Not recorded' }}
                        </span>

                    </div>
                </div>


                @if($linked)
                    <div class="flex shrink-0 items-center gap-2 rounded-lg border border-red-100 bg-red-50 px-3 py-2">
                        <span class="h-2 w-2 rounded-full bg-red-500"></span>

                        <span class="text-xs font-semibold text-red-700">
                            High-Risk Assessment
                        </span>
                    </div>
                @else
                    <x-status-badge variant="neutral">
                        Manual Referral
                    </x-status-badge>
                @endif

            </div>
        </div>


        {{-- =====================================================
            MAIN CONTENT
        ====================================================== --}}
        <div class="{{ $linked
            ? 'grid grid-cols-1 gap-5 lg:grid-cols-5'
            : 'max-w-4xl mx-auto' }}">

            {{-- =================================================
                REFERRAL FORM
            ================================================== --}}
            <div class="{{ $linked ? 'lg:col-span-3' : '' }}">

                <div class="overflow-hidden rounded-xl border border-[#E7E9E5] bg-white">

                    {{-- FORM HEADER --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#E7E9E5] bg-[#FCFBF8] px-5 py-4">

                        <div>
                            <h2 class="text-[15px] font-semibold text-[#19355F]">
                                Referral Details
                            </h2>

                            <p class="mt-0.5 text-xs text-[#657083]">
                                Enter the receiving facility and referral information.
                            </p>
                        </div>

                        @if($linked)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-[11px] font-semibold text-red-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                                Assessment Linked
                            </span>
                        @else
                            <x-status-badge variant="neutral">
                                Manual Referral
                            </x-status-badge>
                        @endif

                    </div>


                    <form
                        id="referral-form"
                        action="{{ route('referrals.store') }}"
                        method="POST"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="patient_id"
                            value="{{ $patient->id }}"
                        >

                        @if($linkedVisit)
                            <input
                                type="hidden"
                                name="prenatal_visit_id"
                                value="{{ $linkedVisit->id }}"
                            >
                        @endif


                        <div class="p-5 sm:p-6">

                            {{-- =====================================
                                FACILITY + DOCTOR
                            ====================================== --}}
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                                <div>
                                    <label
                                        for="referred_to"
                                        class="mb-1.5 block text-sm font-medium text-[#19355F]"
                                    >
                                        Facility
                                        <span class="text-red-500">*</span>
                                    </label>

                                    <input
                                        id="referred_to"
                                        type="text"
                                        name="referred_to"
                                        value="{{ old('referred_to') }}"
                                        placeholder="e.g., Provincial Hospital"
                                        required
                                        class="w-full rounded-lg border border-[#D7DCE2] bg-white px-3 py-2.5 text-sm text-[#19355F]
                                               placeholder:text-[#9AA3AF]
                                               focus:border-[#55B85A]
                                               focus:ring-2
                                               focus:ring-[#55B85A]/20"
                                    >
                                </div>


                                <div>
                                    <label
                                        for="doctor_name"
                                        class="mb-1.5 block text-sm font-medium text-[#19355F]"
                                    >
                                        Doctor / Specialist

                                        <span class="font-normal text-[#8A94A3]">
                                            (Optional)
                                        </span>
                                    </label>

                                    <input
                                        id="doctor_name"
                                        type="text"
                                        name="doctor_name"
                                        value="{{ old('doctor_name') }}"
                                        placeholder="Receiving doctor's name"
                                        class="w-full rounded-lg border border-[#D7DCE2] bg-white px-3 py-2.5 text-sm text-[#19355F]
                                               placeholder:text-[#9AA3AF]
                                               focus:border-[#55B85A]
                                               focus:ring-2
                                               focus:ring-[#55B85A]/20"
                                    >
                                </div>

                            </div>


                            {{-- =====================================
                                REASON
                            ====================================== --}}
                            <div class="mt-5">

                                <label
                                    for="reason"
                                    class="mb-1.5 block text-sm font-medium text-[#19355F]"
                                >
                                    Reason for Referral
                                    <span class="text-red-500">*</span>
                                </label>

                                <textarea
                                    id="reason"
                                    name="reason"
                                    rows="4"
                                    required
                                    placeholder="Describe the clinical reason for referring this patient..."
                                    class="w-full resize-y rounded-lg border border-[#D7DCE2] bg-white px-3 py-2.5 text-sm leading-6 text-[#19355F]
                                           placeholder:text-[#9AA3AF]
                                           focus:border-[#55B85A]
                                           focus:ring-2
                                           focus:ring-[#55B85A]/20"
                                >{{ old('reason', $reasonPrefill ?? '') }}</textarea>

                                @if($linked && !empty($reasonPrefill))
                                    <p class="mt-1.5 text-[11px] text-[#657083]">
                                        Pre-filled from the linked assessment. Editing this
                                        field will not change the original assessment.
                                    </p>
                                @endif

                            </div>


                            {{-- =====================================
                                NOTES
                            ====================================== --}}
                            <div class="mt-5">

                                <label
                                    for="notes"
                                    class="mb-1.5 block text-sm font-medium text-[#19355F]"
                                >
                                    Additional Notes

                                    <span class="font-normal text-[#8A94A3]">
                                        (Optional)
                                    </span>
                                </label>

                                <textarea
                                    id="notes"
                                    name="notes"
                                    rows="3"
                                    placeholder="Add relevant instructions or remarks..."
                                    class="w-full resize-y rounded-lg border border-[#D7DCE2] bg-white px-3 py-2.5 text-sm leading-6 text-[#19355F]
                                           placeholder:text-[#9AA3AF]
                                           focus:border-[#55B85A]
                                           focus:ring-2
                                           focus:ring-[#55B85A]/20"
                                >{{ old('notes') }}</textarea>

                            </div>


                            {{-- =====================================
                                DATE
                            ====================================== --}}
                            <div class="mt-5 max-w-sm">

                                <label
                                    for="date_referred"
                                    class="mb-1.5 block text-sm font-medium text-[#19355F]"
                                >
                                    Referral Date
                                    <span class="text-red-500">*</span>
                                </label>

                                <input
                                    id="date_referred"
                                    type="date"
                                    name="date_referred"
                                    value="{{ old(
                                        'date_referred',
                                        \Carbon\Carbon::today()->toDateString()
                                    ) }}"
                                    required
                                    class="w-full rounded-lg border border-[#D7DCE2] bg-white px-3 py-2.5 text-sm text-[#19355F]
                                           focus:border-[#55B85A]
                                           focus:ring-2
                                           focus:ring-[#55B85A]/20"
                                >

                            </div>

                        </div>


                        {{-- =========================================
                            FORM ACTIONS
                        ========================================== --}}
                        <div class="flex flex-col-reverse gap-2 border-t border-[#E7E9E5] bg-[#FCFBF8] px-5 py-4 sm:flex-row sm:items-center sm:justify-end">

                            <a
                                href="{{ route('patients.show', $patient->id) }}"
                                class="btn btn-secondary sm:min-w-[110px]"
                            >
                                Cancel
                            </a>

                            {{--
                                IMPORTANT:
                                type="button" prevents immediate submission.
                                JS opens the confirmation modal instead.
                            --}}
                            <button
                                id="open-referral-confirmation"
                                type="button"
                                class="btn btn-primary sm:min-w-[145px]"
                            >
                                Save Referral
                            </button>

                        </div>

                    </form>

                </div>
            </div>


            {{-- =================================================
                ASSESSMENT BEING REFERRED
            ================================================== --}}
            @if($linked)

                <div class="lg:col-span-2">

                    <div class="overflow-hidden rounded-xl border border-[#E7E9E5] bg-white">

                        {{-- HEADER --}}
                        <div class="border-b border-[#E7E9E5] bg-[#FCFBF8] px-5 py-4">

                            <div class="flex items-start justify-between gap-3">

                                <div>
                                    <h2 class="text-[15px] font-semibold text-[#19355F]">
                                        Assessment Being Referred
                                    </h2>

                                    <p class="mt-0.5 text-xs leading-5 text-[#657083]">
                                        Read-only clinical evidence from the selected prenatal visit.
                                    </p>
                                </div>

                                <svg
                                    class="mt-0.5 h-5 w-5 shrink-0 text-[#55B85A]"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <path d="M9 15l2 2 4-4"/>
                                </svg>

                            </div>

                        </div>


                        <div class="p-5">

                            {{-- URGENT --}}
                            @if(($snapshot['urgency'] ?? null) === 'URGENT_CLINICAL_REVIEW')

                                <div class="mb-4 flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-red-700">

                                    <svg
                                        class="h-4 w-4 shrink-0"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                        <line x1="12" y1="9" x2="12" y2="13"/>
                                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                                    </svg>

                                    <span class="text-xs font-bold">
                                        Urgent Clinical Review
                                    </span>

                                </div>

                            @endif


                            {{-- SUMMARY --}}
                            <dl class="grid grid-cols-2 gap-x-4 gap-y-4">

                                <div>
                                    <dt class="text-[10px] font-semibold uppercase tracking-wider text-[#8A94A3]">
                                        Visit Date
                                    </dt>

                                    <dd class="mt-1 text-sm font-semibold text-[#19355F]">
                                        {{ ($snapshot['visit_date'] ?? null)
                                            ? \Carbon\Carbon::parse(
                                                $snapshot['visit_date']
                                            )->format('M d, Y')
                                            : '—' }}
                                    </dd>
                                </div>


                                <div>
                                    <dt class="text-[10px] font-semibold uppercase tracking-wider text-[#8A94A3]">
                                        Risk Level
                                    </dt>

                                    <dd class="mt-1">

                                        <x-status-badge
                                            :variant="
                                                ($snapshot['risk_level'] ?? null) === 'HIGH'
                                                    ? 'danger'
                                                    : (
                                                        ($snapshot['risk_level'] ?? null) === 'LOW'
                                                            ? 'success'
                                                            : 'neutral'
                                                    )
                                            "
                                        >
                                            {{ $snapshot['risk_level'] ?? '—' }}
                                        </x-status-badge>

                                    </dd>
                                </div>


                                <div>
                                    <dt class="text-[10px] font-semibold uppercase tracking-wider text-[#8A94A3]">
                                        Assessment Date
                                    </dt>

                                    <dd class="mt-1 text-sm text-[#19355F]">
                                        {{ ($snapshot['assessment_date'] ?? null) ?: '—' }}
                                    </dd>
                                </div>


                                <div>
                                    <dt class="text-[10px] font-semibold uppercase tracking-wider text-[#8A94A3]">
                                        Decision Source
                                    </dt>

                                    <dd class="mt-1 text-sm leading-5 text-[#19355F]">

                                        @php
                                            $ds = $snapshot['decision_source'] ?? null;

                                            $dsLabel = match ($ds) {
                                                'RULE_BASED' =>
                                                    'Rule-Based Clinical Assessment',

                                                'MACHINE_LEARNING' =>
                                                    'Machine Learning Assessment',

                                                'COMPLETENESS' =>
                                                    'Required Records Check',

                                                'MACHINE_LEARNING_INVALID' =>
                                                    'ML Assessment Unavailable',

                                                default =>
                                                    $ds ?: 'Legacy',
                                            };
                                        @endphp

                                        {{ $dsLabel }}

                                    </dd>
                                </div>

                            </dl>


                            {{-- CLINICAL ASSESSMENT --}}
                            @if(!empty($snapshot['assessment']))

                                <div class="mt-5 border-t border-[#E7E9E5] pt-4">

                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-[#8A94A3]">
                                        Clinical Assessment
                                    </p>

                                    <p class="mt-2 text-sm leading-6 text-[#19355F]">
                                        {{ $snapshot['assessment'] }}
                                    </p>

                                </div>

                            @endif


                            {{-- RECOMMENDATION --}}
                            @if(!empty($snapshot['recommendation']))

                                <div class="mt-4 rounded-lg border-l-[3px] border-red-400 bg-red-50/60 px-3.5 py-3">

                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-red-600">
                                        Recommendation
                                    </p>

                                    <p class="mt-1.5 text-sm leading-6 text-[#19355F]">
                                        {{ $snapshot['recommendation'] }}
                                    </p>

                                </div>

                            @endif


                            {{-- CLINICAL FACTORS --}}
                            @if(!empty($snapshot['factor_evidence']))

                                <div class="mt-5 border-t border-[#E7E9E5] pt-4">

                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-[#8A94A3]">
                                        Clinical Factors
                                    </p>

                                    <div class="mt-2.5 space-y-2">

                                        @foreach($snapshot['factor_evidence'] as $factor)

                                            @if(!is_array($factor))
                                                @continue
                                            @endif

                                            <div class="flex items-start justify-between gap-3 rounded-lg border border-amber-100 bg-amber-50/60 px-3 py-2.5">

                                                <p class="text-sm leading-5 text-[#19355F]">
                                                    {{ $factor['label']
                                                        ?? $factor['code']
                                                        ?? 'Factor' }}
                                                </p>

                                                @if(!empty($factor['code']))

                                                    <span class="shrink-0 rounded bg-white/70 px-1.5 py-0.5 font-mono text-[10px] text-[#8A94A3]">
                                                        {{ $factor['code'] }}
                                                    </span>

                                                @endif

                                            </div>

                                        @endforeach

                                    </div>
                                </div>

                            @endif


                            {{-- CLINICAL INTERACTIONS --}}
                            @if(!empty($snapshot['interaction_evidence']))

                                <div class="mt-5 border-t border-[#E7E9E5] pt-4">

                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-[#8A94A3]">
                                        Clinical Interactions
                                    </p>

                                    <div class="mt-2.5 space-y-2">

                                        @foreach(
                                            $snapshot['interaction_evidence']
                                            as $interaction
                                        )

                                            @if(!is_array($interaction))
                                                @continue
                                            @endif


                                            @php
                                                $contextLines = collect();

                                                foreach (
                                                    ($interaction['observed_context'] ?? [])
                                                    as $path => $value
                                                ) {
                                                    if (
                                                        isset($observedContextLabels[$path]) &&
                                                        $value !== null &&
                                                        trim((string) $value) !== ''
                                                    ) {
                                                        $contextLines->push(
                                                            $observedContextLabels[$path]
                                                            . ': '
                                                            . $value
                                                        );
                                                    }
                                                }
                                            @endphp


                                            <div class="rounded-lg border border-blue-100 bg-blue-50/50 px-3 py-2.5">

                                                <p class="text-sm font-medium leading-5 text-[#19355F]">
                                                    {{ $interaction['label']
                                                        ?? $interaction['code']
                                                        ?? 'Interaction' }}
                                                </p>

                                                @if($contextLines->isNotEmpty())

                                                    <p class="mt-1 text-xs leading-5 text-[#657083]">
                                                        {{ $contextLines->implode(' · ') }}
                                                    </p>

                                                @endif

                                            </div>

                                        @endforeach

                                    </div>
                                </div>

                            @endif


                            {{-- BLOOD PRESSURE --}}
                            @if(
                                !empty($snapshot['bp_assessment']) &&
                                is_array($snapshot['bp_assessment'])
                            )

                                @php
                                    $urgentBp =
                                        ($snapshot['bp_assessment']['reason_code'] ?? null)
                                        === 'BP-URG';
                                @endphp


                                <div
                                    class="mt-5 rounded-lg border px-3.5 py-3
                                    {{ $urgentBp
                                        ? 'border-red-200 bg-red-50'
                                        : 'border-[#E7E9E5] bg-[#F6F4EE]' }}"
                                >

                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-[#8A94A3]">
                                        Blood Pressure Finding
                                    </p>

                                    @if($urgentBp)

                                        <p class="mt-1.5 text-sm font-semibold text-red-700">
                                            Urgent blood-pressure finding captured in this assessment.
                                        </p>

                                    @else

                                        <p class="mt-1.5 text-sm text-[#19355F]">
                                            Blood-pressure finding captured in this assessment.
                                        </p>

                                    @endif

                                </div>

                            @endif

                        </div>


                        {{-- READ ONLY FOOTER --}}
                        <div class="border-t border-[#E7E9E5] bg-[#F6F4EE] px-5 py-3">

                            <div class="flex items-center gap-2 text-[11px] leading-4 text-[#657083]">

                                <svg
                                    class="h-3.5 w-3.5 shrink-0"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <rect x="3" y="11" width="18" height="10" rx="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>

                                <span>
                                    Assessment evidence is read-only and will
                                    be preserved with the referral.
                                </span>

                            </div>

                        </div>

                    </div>
                </div>

            @endif

        </div>
    </div>



    {{-- =========================================================
        SAVE REFERRAL CONFIRMATION MODAL
    ========================================================== --}}
    <div
        id="referral-confirmation-modal"
        class="fixed inset-0 z-[100] hidden items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="referral-confirmation-title"
    >

        {{-- BACKDROP --}}
        <div
            id="referral-modal-backdrop"
            class="absolute inset-0 bg-black/45"
        ></div>


        {{-- MODAL --}}
        <div class="relative z-10 w-full max-w-md overflow-hidden rounded-2xl border border-[#E7E9E5] bg-white shadow-2xl">

            {{-- HEADER --}}
            <div class="flex items-start gap-3 border-b border-[#E7E9E5] px-5 py-5">

                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-50">

                    <svg
                        class="h-5 w-5 text-blue-600"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <path d="M9 15l2 2 4-4"/>
                    </svg>

                </div>


                <div class="min-w-0">

                    <h2
                        id="referral-confirmation-title"
                        class="text-lg font-semibold text-[#19355F]"
                    >
                        Confirm Referral
                    </h2>

                    <p class="mt-0.5 text-sm font-medium text-blue-600">
                        {{ $patientName }}
                    </p>

                </div>

            </div>


            {{-- BODY --}}
            <div class="px-5 py-5">

                <p class="text-sm leading-6 text-[#4F5D70]">
                    Are you sure you want to save this referral for
                    <span class="font-semibold text-[#19355F]">
                        {{ $patientName }}
                    </span>?
                    Please confirm that the referral information is correct
                    before continuing.
                </p>


                {{-- QUICK SUMMARY --}}
                <div class="mt-4 rounded-xl border border-[#E7E9E5] bg-[#FCFBF8]">

                    <div class="flex items-start justify-between gap-4 border-b border-[#E7E9E5] px-4 py-3">

                        <span class="text-xs text-[#657083]">
                            Facility
                        </span>

                        <span
                            id="confirm-facility"
                            class="max-w-[65%] text-right text-xs font-semibold text-[#19355F]"
                        >
                            —
                        </span>

                    </div>


                    <div class="flex items-start justify-between gap-4 border-b border-[#E7E9E5] px-4 py-3">

                        <span class="text-xs text-[#657083]">
                            Doctor / Specialist
                        </span>

                        <span
                            id="confirm-doctor"
                            class="max-w-[65%] text-right text-xs font-semibold text-[#19355F]"
                        >
                            Not specified
                        </span>

                    </div>


                    <div class="flex items-start justify-between gap-4 px-4 py-3">

                        <span class="text-xs text-[#657083]">
                            Referral Date
                        </span>

                        <span
                            id="confirm-date"
                            class="max-w-[65%] text-right text-xs font-semibold text-[#19355F]"
                        >
                            —
                        </span>

                    </div>

                </div>

            </div>


            {{-- ACTIONS --}}
            <div class="flex items-center justify-between gap-3 border-t border-[#E7E9E5] px-5 py-4">

                <button
                    id="cancel-referral-confirmation"
                    type="button"
                    class="btn btn-secondary"
                >
                    Cancel
                </button>

                <button
                    id="confirm-referral-submit"
                    type="button"
                    class="btn btn-primary"
                >
                    Confirm & Save Referral
                </button>

            </div>

        </div>

    </div>



    {{-- =========================================================
        MODAL SCRIPT
    ========================================================== --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const form = document.getElementById('referral-form');

            const openButton =
                document.getElementById('open-referral-confirmation');

            const modal =
                document.getElementById('referral-confirmation-modal');

            const backdrop =
                document.getElementById('referral-modal-backdrop');

            const cancelButton =
                document.getElementById('cancel-referral-confirmation');

            const confirmButton =
                document.getElementById('confirm-referral-submit');


            const facilityInput =
                document.getElementById('referred_to');

            const doctorInput =
                document.getElementById('doctor_name');

            const dateInput =
                document.getElementById('date_referred');


            const facilityPreview =
                document.getElementById('confirm-facility');

            const doctorPreview =
                document.getElementById('confirm-doctor');

            const datePreview =
                document.getElementById('confirm-date');


            function formatDate(value) {

                if (!value) {
                    return '—';
                }

                const parts = value.split('-');

                if (parts.length !== 3) {
                    return value;
                }

                const year = Number(parts[0]);
                const month = Number(parts[1]) - 1;
                const day = Number(parts[2]);

                const date = new Date(year, month, day);

                return date.toLocaleDateString('en-US', {
                    month: 'short',
                    day: '2-digit',
                    year: 'numeric'
                });
            }


            function openModal() {

                /*
                 * Run the browser's required-field validation FIRST.
                 * The confirmation should only appear when the form
                 * contains valid required values.
                 */
                if (!form.reportValidity()) {
                    return;
                }


                facilityPreview.textContent =
                    facilityInput.value.trim() || '—';

                doctorPreview.textContent =
                    doctorInput.value.trim() || 'Not specified';

                datePreview.textContent =
                    formatDate(dateInput.value);


                modal.classList.remove('hidden');
                modal.classList.add('flex');

                document.body.style.overflow = 'hidden';

                cancelButton.focus();
            }


            function closeModal() {

                modal.classList.add('hidden');
                modal.classList.remove('flex');

                document.body.style.overflow = '';

                openButton.focus();
            }


            openButton.addEventListener('click', openModal);

            cancelButton.addEventListener('click', closeModal);

            backdrop.addEventListener('click', closeModal);


            /*
             * Only this button actually submits the referral.
             */
            confirmButton.addEventListener('click', function () {

                confirmButton.disabled = true;

                confirmButton.textContent = 'Saving...';

                form.submit();
            });


            /*
             * ESC closes the confirmation dialog.
             */
            document.addEventListener('keydown', function (event) {

                if (
                    event.key === 'Escape' &&
                    !modal.classList.contains('hidden')
                ) {
                    closeModal();
                }

            });


            /*
             * Prevent pressing Enter in an input from bypassing
             * the confirmation modal.
             */
            form.addEventListener('submit', function (event) {

                event.preventDefault();

                openModal();
            });

        });
    </script>

</x-app-layout>