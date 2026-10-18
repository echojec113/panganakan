{{-- DEPLA Phase 3B.3/3D.2B/3D.3B — one appointment card for the patient
     portal. Reused by the Pending, Upcoming Confirmed and History
     sections of patient/appointments/index.blade.php so the three
     sections can never drift in the fields they show. Phase 3D.2B adds
     the patient cancellation actions; Phase 3D.3B Step 4 adds the
     rescheduling UI: a "Request Reschedule" disclosure on eligible
     CONFIRMED online cards, a type-aware pending badge (cancellation
     vs reschedule wording), and the approved/declined reschedule
     outcome banners. Eligibility (online source, pending or confirmed
     status, before the scheduled start cutoff, no other pending change
     request) is computed SERVER-SIDE here from the same public cutoff
     helper the service enforces under lock — walk-ins, terminal
     statuses, past-schedule rows and ineligible cards stay fully
     read-only. The reschedule form posts only proposed_date/
     proposed_time/reason plus a display-state marker (hidden
     `form=reschedule`); patient_id, source, status and ownership are
     never posted (the controller re-resolves them through the own
     patient relation). No staff-only audit details (reviewer name,
     internal ids) are ever rendered. $appointment is an
     App\Models\Appointment (upcoming/history sections eager-load
     pendingChangeRequest + latestCancellationRequest +
     latestRescheduleRequest). --}}
@php
    use App\Services\AppointmentManagementService;

    $typeLabels = [
        \App\Models\Appointment::TYPE_CHECKUP => 'Checkup',
        \App\Models\Appointment::TYPE_COUNSELING => 'Counseling',
        \App\Models\Appointment::TYPE_DELIVERY_REQUEST => 'Delivery Request',
    ];

    $sourceLabels = [
        \App\Models\Appointment::SOURCE_ONLINE => 'Online',
        \App\Models\Appointment::SOURCE_WALK_IN => 'Walk-in',
    ];

    $statusMeta = [
        \App\Models\Appointment::STATUS_PENDING => ['label' => 'Pending', 'bg' => '#FEF3C7', 'fg' => '#92400E'],
        \App\Models\Appointment::STATUS_CONFIRMED => ['label' => 'Confirmed', 'bg' => '#D1FAE5', 'fg' => '#065F46'],
        \App\Models\Appointment::STATUS_COMPLETED => ['label' => 'Completed', 'bg' => '#E5E7EB', 'fg' => '#374151'],
        \App\Models\Appointment::STATUS_CANCELLED => ['label' => 'Cancelled', 'bg' => '#F3F4F6', 'fg' => '#6B7280'],
        \App\Models\Appointment::STATUS_DECLINED => ['label' => 'Declined', 'bg' => '#FEE2E2', 'fg' => '#991B1B'],
    ];

    $typeLabel = $typeLabels[$appointment->appointment_type] ?? $appointment->appointment_type;
    $sourceLabel = $sourceLabels[$appointment->source] ?? $appointment->source;
    $status = $statusMeta[$appointment->status] ?? ['label' => $appointment->status, 'bg' => '#F3F4F6', 'fg' => '#374151'];

    $formatTime = fn ($time) => $time !== null ? substr((string) $time, 0, 5) : null;
    $requestedTime = $formatTime($appointment->requested_time);
    $confirmedTime = $formatTime($appointment->confirmed_time);

    // ── Phase 3D.2B cancellation eligibility (server-side) ──────────
    $isOnline = $appointment->source === \App\Models\Appointment::SOURCE_ONLINE;
    $isPending = $appointment->status === \App\Models\Appointment::STATUS_PENDING;
    $isConfirmed = $appointment->status === \App\Models\Appointment::STATUS_CONFIRMED;

    // The schedule the patient can currently see is the cutoff anchor:
    // confirmed schedule for confirmed rows, requested schedule otherwise.
    $cutoffDate = ($isConfirmed ? $appointment->confirmed_date : null) ?? $appointment->requested_date;
    $cutoffTime = $isConfirmed
        ? ($appointment->confirmed_time ?? $appointment->requested_time)
        : $appointment->requested_time;

    $cutoffIssue = $cutoffDate !== null
        ? AppointmentManagementService::scheduledStartPassedIssue($cutoffDate->toDateString(), $cutoffTime)
        : null;

    $pendingChangeRequest = $appointment->relationLoaded('pendingChangeRequest')
        ? $appointment->pendingChangeRequest
        : null;

    // Phase 3D.2C - latest CANCELLATION-typed request regardless of
    // review status (eager-loaded by the controller); used to show the
    // REVIEW OUTCOME: approved (appointment now cancelled with the
    // patient's reason) or declined (appointment stays confirmed, the
    // clinic's explanation shown). Pending is handled by the badge below.
    $latestCancellationRequest = $appointment->relationLoaded('latestCancellationRequest')
        ? $appointment->latestCancellationRequest
        : null;

    // Phase 3D.3B Step 4 - latest RESCHEDULE-typed request regardless
    // of review status (eager-loaded by the controller): approved
    // banners announce the NEW confirmed schedule, declined banners
    // show the clinic explanation while the original schedule stays.
    $latestRescheduleRequest = $appointment->relationLoaded('latestRescheduleRequest')
        ? $appointment->latestRescheduleRequest
        : null;

    $canDirectCancel = $isOnline && $isPending && $cutoffIssue === null;
    $canRequestCancellation = $isOnline && $isConfirmed && $cutoffIssue === null && $pendingChangeRequest === null;
    // Rescheduling mirrors cancellation eligibility exactly: own card
    // (the controller only ever renders the patient's own rows), online
    // source, confirmed status, before the cutoff and no other pending
    // change request (the service re-checks ALL of these under lock).
    $canRequestReschedule = $isOnline && $isConfirmed && $cutoffIssue === null && $pendingChangeRequest === null;
    $hasPendingChangeRequest = $pendingChangeRequest !== null;

    // Delivery proposals are date-only (approved decision K5): the
    // time field is not rendered at all. Checkup/counseling require a
    // time inside clinic hours (08:00 up to but excluding 17:00).
    $isDelivery = $appointment->appointment_type === \App\Models\Appointment::TYPE_DELIVERY_REQUEST;

    // Phase 3D.3B Step 4 - validation/DomainException return state for
    // the RESCHEDULE form: the hidden `form=reschedule` marker flashed
    // through old input distinguishes a reschedule resubmission from a
    // cancellation one, so old input, field errors and the reopened
    // disclosure land on the RIGHT form (both forms post a field named
    // `reason`, and this page can render several cards at once).
    $rescheduleReturn = old('form') === 'reschedule';

    // A reschedule DomainException is scoped to the card it targeted
    // via the flashed errorAppointmentId, so the shared error never
    // appears on unrelated cards.
    $hasRescheduleCardError = $errors->has('appointment')
        && (string) session('errorAppointmentId') === (string) $appointment->id;

    // Phase 3D.4B (P2) - CANCELLATION error/old-input scoping.
    //
    // Both cancellation handlers (pending direct cancel and confirmed
    // approval-gated request) now flash, on EITHER failure path
    // (FormRequest validation and service DomainException), the
    // TARGETED appointment id plus a form marker, because the shared
    // portal page renders many cards and BOTH cancellation forms post
    // a field named `reason`. The marker ('cancel' vs
    // 'cancellation-request') keeps the two forms of one card from
    // painting each other's failures, and the id keeps one patient's
    // failure off every other card.
    $cancelErrorAppointmentId = (string) session('errorAppointmentId');
    $cancelErrorForm = (string) session('errorForm');

    $hasCancellationCardError = $errors->has('reason')
        && $cancelErrorAppointmentId === (string) $appointment->id
        && in_array($cancelErrorForm, ['cancel', 'cancellation-request'], true);

    // Which single cancellation form may repaint the typed reason.
    // A reschedule failure never sets these (it flashes only
    // errorAppointmentId + the 'appointment' error key), so the two
    // error families stay disjoint.
    $directCancelReturn = $hasCancellationCardError && $cancelErrorForm === 'cancel';
    $cancelRequestReturn = $hasCancellationCardError && $cancelErrorForm === 'cancellation-request';

    // Does the form this refusal belongs to actually render below?
    // Mirror of the @if chain in the actions section.
    $directCancelFormRendered = ! $hasPendingChangeRequest && $canDirectCancel;
    $cancelRequestFormRendered = ! $hasPendingChangeRequest && $canRequestCancellation;

    // A refusal must never be SILENT: when the form it belongs to no
    // longer renders (status moved on, cutoff passed, a request is
    // already pending, or the patient posted the endpoint for the other
    // status), paint the same message once at card level instead.
    $hasCancellationCardFallbackError = $hasCancellationCardError
        && (($directCancelReturn && ! $directCancelFormRendered)
            || ($cancelRequestReturn && ! $cancelRequestFormRendered));

    // Manila "today" for the proposed-date min attribute (the server
    // refuses past proposals in Asia/Manila regardless).
    $clinicToday = \Carbon\Carbon::now(AppointmentManagementService::CLINIC_TIMEZONE)->toDateString();
@endphp

<article class="rounded-2xl border bg-white p-5"
         style="border-color: var(--light-border);"
         aria-label="{{ $typeLabel }} appointment, {{ $status['label'] }}">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-base font-bold" style="color: var(--depla-navy);">
                {{ $typeLabel }}
            </p>
            <p class="mt-0.5 text-xs font-medium" style="color: var(--body-text);">
                Booked via {{ $sourceLabel }}
                &middot; Requested {{ $appointment->created_at?->copy()->timezone(AppointmentManagementService::CLINIC_TIMEZONE)->format('M j, Y') ?? 'Not recorded' }}
            </p>
        </div>
        <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide"
              style="background: {{ $status['bg'] }}; color: {{ $status['fg'] }};">
            {{ $status['label'] }}
        </span>
    </div>

    {{-- Pending emphasis: the requested schedule is what the patient chose;
         the clinic has not approved a schedule yet. --}}
    @if ($appointment->status === \App\Models\Appointment::STATUS_PENDING)
        <p class="mt-3 rounded-xl px-3 py-2 text-sm font-medium"
           style="background: #FEF3C7; color: #92400E;">
            Awaiting clinic confirmation
        </p>
    @endif

    <dl class="mt-3 space-y-1.5 text-sm" style="color: var(--body-text);">
        <div class="flex flex-wrap items-baseline gap-x-2">
            <dt class="font-semibold" style="color: var(--depla-navy);">Preferred schedule:</dt>
            <dd>
                {{ $appointment->requested_date?->format('l, F j, Y') ?? 'Not recorded' }}
                @if ($requestedTime !== null)
                    at {{ $requestedTime }}
                @else
                    (date only)
                @endif
            </dd>
        </div>

        @if ($appointment->confirmed_date !== null || $appointment->status === \App\Models\Appointment::STATUS_CONFIRMED)
            <div class="flex flex-wrap items-baseline gap-x-2">
                <dt class="font-semibold" style="color: var(--depla-dark-green);">Clinic-confirmed schedule:</dt>
                <dd style="color: var(--depla-dark-green);">
                    {{ $appointment->confirmed_date?->format('l, F j, Y') ?? 'Not provided' }}
                    @if ($confirmedTime !== null)
                        at {{ $confirmedTime }}
                    @elseif ($appointment->confirmed_date !== null)
                        (date only)
                    @endif
                </dd>
            </div>
        @endif
    </dl>

    @if ($appointment->status === \App\Models\Appointment::STATUS_DECLINED && $appointment->decline_reason)
        <p class="mt-3 rounded-xl px-3 py-2 text-sm"
           style="background: #FEE2E2; color: #991B1B;">
            <span class="font-semibold">Decline reason:</span> {{ $appointment->decline_reason }}
        </p>
    @endif

    @if ($appointment->status === \App\Models\Appointment::STATUS_CANCELLED && $appointment->cancellation_reason)
        <p class="mt-3 rounded-xl px-3 py-2 text-sm"
           style="background: #F3F4F6; color: #374151;">
            <span class="font-semibold">Cancellation reason:</span> {{ $appointment->cancellation_reason }}
        </p>
    @endif

    {{-- ═══ Phase 3D.2C — cancellation-request review OUTCOME ═══ --}}
    @if ($latestCancellationRequest !== null
        && $latestCancellationRequest->status === \App\Models\AppointmentChangeRequest::STATUS_DECLINED
        && ! $hasPendingChangeRequest)
        <p class="mt-3 rounded-xl px-3 py-2 text-sm"
           style="background: #FEE2E2; color: #991B1B;">
            <span class="font-semibold">Your cancellation request was declined.</span>
            @if ($latestCancellationRequest->review_note)
                {{ $latestCancellationRequest->review_note }}
            @endif
            Your appointment stays confirmed.
        </p>
    @elseif ($latestCancellationRequest !== null
        && $latestCancellationRequest->status === \App\Models\AppointmentChangeRequest::STATUS_APPROVED
        && $appointment->status === \App\Models\Appointment::STATUS_CANCELLED)
        <p class="mt-3 rounded-xl px-3 py-2 text-sm"
           style="background: #F3F4F6; color: #374151;">
            <span class="font-semibold">Your cancellation request was approved.</span>
            This appointment was cancelled as you requested.
        </p>
    @endif

    {{-- ═══ Phase 3D.3B Step 4 — reschedule-request review OUTCOME ═══ --}}
    @if ($latestRescheduleRequest !== null
        && ! $hasPendingChangeRequest
        && $appointment->status === \App\Models\Appointment::STATUS_CONFIRMED)
        @if ($latestRescheduleRequest->status === \App\Models\AppointmentChangeRequest::STATUS_APPROVED)
            @php
                $approvedDate = $appointment->confirmed_date ?? $appointment->requested_date;
                $approvedTime = $appointment->confirmed_time !== null
                    ? substr((string) $appointment->confirmed_time, 0, 5)
                    : null;
            @endphp
            <p class="mt-3 rounded-xl px-3 py-2 text-sm"
               style="background: #D1FAE5; color: #065F46;"
               role="status">
                <span class="font-semibold">Your reschedule request was approved.</span>
                Your appointment is now confirmed for
                {{ $approvedDate?->format('l, F j, Y') ?? 'the newly approved schedule' }}@if ($approvedTime !== null) at {{ $approvedTime }}@else (date only)@endif.
            </p>
        @elseif ($latestRescheduleRequest->status === \App\Models\AppointmentChangeRequest::STATUS_DECLINED)
            <p class="mt-3 rounded-xl px-3 py-2 text-sm"
               style="background: #FEE2E2; color: #991B1B;">
                <span class="font-semibold">Your reschedule request was declined.</span>
                @if ($latestRescheduleRequest->review_note)
                    {{ $latestRescheduleRequest->review_note }}
                @endif
                Your appointment stays confirmed at its original schedule.
            </p>
        @endif
    @endif

    {{-- Scoped reschedule refusal (DomainException - e.g. a second
         pending request, or a proposal identical to the current
         schedule). Only the card the submission targeted renders it. --}}
    @if ($hasRescheduleCardError)
        <p class="mt-3 rounded-xl px-3 py-2 text-sm font-medium"
           style="background: #FEE2E2; color: #991B1B;"
           role="alert">
            {{ $errors->first('appointment') }}
        </p>
    @endif

    {{-- Scoped cancellation refusal that can no longer render inside its
         own form (Phase 3D.4B P2 fallback). When the form IS on screen
         the message is printed next to the field instead, never both. --}}
    @if ($hasCancellationCardFallbackError)
        <p class="mt-3 rounded-xl px-3 py-2 text-sm font-medium"
           style="background: #FEE2E2; color: #991B1B;"
           role="alert">
            {{ $errors->first('reason') }}
        </p>
    @endif

    {{-- ═══ Phase 3D.2B/3D.3B — patient change-request actions ═══ --}}

    {{-- A change request is awaiting clinic review: the confirmed
         appointment stays active and no further actions are offered
         (neither cancellation nor reschedule forms render). --}}
    @if ($hasPendingChangeRequest)
        @if ($pendingChangeRequest->type === \App\Models\AppointmentChangeRequest::TYPE_RESCHEDULE)
            <p class="mt-3 rounded-xl px-3 py-2 text-sm font-medium"
               style="background: #FEF3C7; color: #92400E;">
                Reschedule Request Pending — awaiting clinic review. Your appointment stays confirmed at its current schedule until the clinic responds.
            </p>
            <p class="mt-2 rounded-xl px-3 py-2 text-sm"
               style="background: #F9FAFB; color: #374151;">
                <span class="font-semibold">Your proposed schedule:</span>
                {{ $pendingChangeRequest->proposed_date?->format('l, F j, Y') ?? 'Not recorded' }}
                @if ($pendingChangeRequest->proposed_time !== null)
                    at {{ substr((string) $pendingChangeRequest->proposed_time, 0, 5) }}
                @else
                    (date only)
                @endif
            </p>
            @if ($pendingChangeRequest->reason)
                <p class="mt-2 rounded-xl px-3 py-2 text-sm"
                   style="background: #F9FAFB; color: #374151;">
                    <span class="font-semibold">Your reason:</span> {{ $pendingChangeRequest->reason }}
                </p>
            @endif
        @else
            <p class="mt-3 rounded-xl px-3 py-2 text-sm font-medium"
               style="background: #FEF3C7; color: #92400E;">
                Cancellation request pending — awaiting clinic review. Your appointment stays confirmed until the clinic responds.
            </p>
            @if ($pendingChangeRequest->reason)
                <p class="mt-2 rounded-xl px-3 py-2 text-sm"
                   style="background: #F9FAFB; color: #374151;">
                    <span class="font-semibold">Your cancellation reason:</span> {{ $pendingChangeRequest->reason }}
                </p>
            @endif
        @endif
    @elseif ($canDirectCancel)
        {{-- PENDING online appointment: direct cancellation (no approval).
             Pure-HTML disclosure — no JavaScript dependency. --}}
        <details class="mt-3 rounded-xl border" style="border-color: var(--light-border);">
            <summary class="cursor-pointer rounded-xl px-3 py-2 text-sm font-semibold"
                     style="color: #991B1B;">
                Cancel Appointment
            </summary>
            <form method="POST" action="{{ route('patient.appointments.cancel', ['appointmentId' => $appointment->id]) }}"
                  class="border-t px-3 py-3" style="border-color: var(--light-border);">
                @csrf
                <label for="cancel-reason-{{ $appointment->id }}" class="block text-sm font-semibold" style="color: var(--depla-navy);">
                    Reason for cancellation <span aria-hidden="true">*</span>
                </label>
                <textarea id="cancel-reason-{{ $appointment->id }}" name="reason" rows="3" required minlength="3" maxlength="1000"
                          class="mt-1.5 w-full rounded-xl border px-3 py-2 text-sm"
                          style="border-color: var(--light-border);"
                          placeholder="Briefly tell the clinic why you are cancelling.">{{ $directCancelReturn ? old('reason') : '' }}</textarea>
                @if ($directCancelReturn)
                    @error('reason')
                        <p class="mt-1.5 text-xs font-medium" style="color: #991B1B;" role="alert">{{ $message }}</p>
                    @enderror
                @endif
                <button type="submit"
                        class="mt-3 inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                        style="background: #991B1B;">
                    Confirm Cancellation
                </button>
            </form>
        </details>
    @elseif ($canRequestCancellation || $canRequestReschedule)
        {{-- CONFIRMED online appointment: approval-gated cancellation
             request and/or reschedule request. Both may be offered on
             the same card - the one-pending service rule guarantees
             only ONE of them can actually be submitted. --}}
        @if ($canRequestCancellation)
            <details class="mt-3 rounded-xl border" style="border-color: var(--light-border);">
                <summary class="cursor-pointer rounded-xl px-3 py-2 text-sm font-semibold"
                         style="color: #991B1B;">
                    Request Cancellation
                </summary>
                <form method="POST" action="{{ route('patient.appointments.cancellationRequest', ['appointmentId' => $appointment->id]) }}"
                      class="border-t px-3 py-3" style="border-color: var(--light-border);">
                    @csrf
                    <p class="mb-2 text-xs leading-5" style="color: var(--body-text);">
                        Your appointment stays confirmed with its clinic-approved schedule until the clinic reviews this request.
                    </p>
                    <label for="cancel-request-reason-{{ $appointment->id }}" class="block text-sm font-semibold" style="color: var(--depla-navy);">
                        Reason for cancellation <span aria-hidden="true">*</span>
                    </label>
                    <textarea id="cancel-request-reason-{{ $appointment->id }}" name="reason" rows="3" required minlength="3" maxlength="1000"
                              class="mt-1.5 w-full rounded-xl border px-3 py-2 text-sm"
                              style="border-color: var(--light-border);"
                              placeholder="Briefly tell the clinic why you are requesting cancellation.">{{ $cancelRequestReturn ? old('reason') : '' }}</textarea>
                    @if ($cancelRequestReturn)
                        @error('reason')
                            <p class="mt-1.5 text-xs font-medium" style="color: #991B1B;" role="alert">{{ $message }}</p>
                        @enderror
                    @endif
                    <button type="submit"
                            class="mt-3 inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                            style="background: #991B1B;">
                        Submit Cancellation Request
                    </button>
                </form>
            </details>
        @endif

        @if ($canRequestReschedule)
            {{-- Phase 3D.3B Step 4 — approval-gated reschedule request.
                 Pure-HTML disclosure (no JavaScript); reopened after a
                 failed reschedule submission via the flashed form
                 marker. Posts ONLY proposed_date / proposed_time /
                 reason (+ the display-state marker) - the appointment
                 identity comes from the route, never from a hidden
                 ownership field. --}}
            <details class="mt-3 rounded-xl border" style="border-color: var(--light-border);" {{ $rescheduleReturn ? 'open' : '' }}>
                <summary class="cursor-pointer rounded-xl px-3 py-2 text-sm font-semibold"
                         style="color: var(--depla-dark-green);">
                    Request Reschedule
                </summary>
                <form method="POST" action="{{ route('patient.appointments.rescheduleRequest', ['appointmentId' => $appointment->id]) }}"
                      x-data="{ slotReady: false, submitting: false }"
                      x-on:slot-picker-state="slotReady = ($event.detail.id === 'reschedule-{{ $appointment->id }}' && $event.detail.ready)"
                      @submit="if (!slotReady) { $event.preventDefault(); return; } submitting = true"
                      class="border-t px-3 py-3" style="border-color: var(--light-border);">
                    @csrf
                    <input type="hidden" name="form" value="reschedule">
                    <p class="mb-2 text-xs leading-5" style="color: var(--body-text);">
                        Your current appointment will remain confirmed until the clinic approves your reschedule request.
                    </p>

                    <label for="reschedule-date-{{ $appointment->id }}" class="block text-sm font-semibold" style="color: var(--depla-navy);">
                        Proposed Date <span aria-hidden="true">*</span>
                    </label>
                    <input type="date"
                           id="reschedule-date-{{ $appointment->id }}"
                           name="proposed_date"
                           value="{{ old('proposed_date') }}"
                           min="{{ $clinicToday }}"
                           required
                           class="mt-1.5 h-11 w-full rounded-xl border px-3 text-sm focus:outline-none focus:ring-2"
                           style="border-color: #D1D5DB; color: var(--depla-navy);"
                           aria-describedby="reschedule-date-hint-{{ $appointment->id }} @error('proposed_date') reschedule-date-error-{{ $appointment->id }} @enderror">
                    <p id="reschedule-date-hint-{{ $appointment->id }}" class="mt-1.5 text-xs" style="color: var(--body-text);">
                        Today or a later date (Philippine time). Available times update automatically when you change the date.
                    </p>
                    @error('proposed_date')
                        <p id="reschedule-date-error-{{ $appointment->id }}" class="mt-1.5 text-xs font-medium" style="color: #991B1B;" role="alert">{{ $message }}</p>
                    @enderror

                    @if (! $isDelivery)
                        {{-- Phase 4 — shared 30-minute slot picker replaces the
                             free-form time input. Delivery reschedules stay
                             date-only (no picker rendered). --}}
                        <div class="mt-3">
                            <x-slot-picker
                                time-name="proposed_time"
                                :date-id="'reschedule-date-'.$appointment->id"
                                :url="route('patient.appointments.slots')"
                                :initial="old('proposed_time')"
                                :picker-id="'reschedule-'.$appointment->id"
                                label="Proposed Time"
                                :required="true"
                            />
                            <p class="mt-1.5 text-xs" style="color: var(--body-text);">
                                Choose one 30-minute clinic slot. Booked, past and clinic-closed times cannot be selected.
                            </p>
                        </div>
                    @endif

                    <label for="reschedule-reason-{{ $appointment->id }}" class="mt-3 block text-sm font-semibold" style="color: var(--depla-navy);">
                        Reason <span class="text-xs font-normal" style="color: var(--body-text);">(optional, 3&ndash;1000 characters)</span>
                    </label>
                    <textarea id="reschedule-reason-{{ $appointment->id }}" name="reason" rows="3" minlength="3" maxlength="1000"
                              class="mt-1.5 w-full rounded-xl border px-3 py-2 text-sm"
                              style="border-color: var(--light-border);"
                              placeholder="Briefly tell the clinic why you need a new schedule.">{{ $rescheduleReturn ? old('reason') : '' }}</textarea>
                    @if ($rescheduleReturn)
                        @error('reason')
                            <p class="mt-1.5 text-xs font-medium" style="color: #991B1B;" role="alert">{{ $message }}</p>
                        @enderror
                    @endif

                    <button type="submit"
                            :disabled="submitting || !slotReady"
                            class="mt-3 inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
                            style="background: var(--depla-dark-green);">
                        Submit Reschedule Request
                    </button>
                </form>
            </details>
        @endif
    @endif
</article>
