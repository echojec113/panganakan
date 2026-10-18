{{--
    DEPLA Phase 4 — shared 30-minute appointment slot picker.

    One Alpine component + markup block used by ALL four timed booking
    workflows (patient book, patient reschedule, staff confirm, staff
    walk-in). The endpoint response is the ONLY source of truth for the
    slot catalog — this component never generates or hardcodes a slot
    list; it fetches GET {url}?date=YYYY-MM-DD and renders the returned
    18 slots with their server-computed states (available / occupied /
    past / unavailable).

    Submits the selected start time through a hidden input carrying the
    caller's expected HH:MM field name (requested_time, proposed_time,
    confirmed_time or appointment_time) — the server FormRequests and
    ClinicAvailabilityService remain authoritative for every rule; a
    stale or forged client state is re-validated at the write boundary.

    Styling is SELF-CONTAINED (plain CSS below, DEPLA palette hexes)
    because the compiled Vite CSS build predates this component — no
    new Tailwind utility class is required for the picker to render.

    Props:
      time-name      backend field name of the hidden HH:MM input
      date-id        id of the form's date input (the picker watches it)
      url            availability endpoint (patient or staff slot route)
      initial        preselection (old input / requested staff time)
      picker-id      instance key for sync events + DOM hooks
      label          fieldset legend text
      required       show * and gate submit-readiness (default true)
      hide-expr      Alpine expression that hides the picker (delivery);
                     when truthy the hidden input is disabled so no
                     timed value is submitted
      preselect-notice  explain when the initial value is not selectable
--}}
@props([
    'timeName',
    'dateId',
    'url',
    'initial' => '',
    'pickerId' => '',
    'label' => 'Preferred Time',
    'required' => true,
    'hideExpr' => 'false',
    'preselectNotice' => false,
])

@php
    $uid = $pickerId !== '' ? $pickerId : 'sp-'.uniqid();
    $radioName = 'slot_ui_'.$uid;
    $config = [
        'url' => $url,
        'timeName' => $timeName,
        'dateId' => $dateId,
        'pickerId' => $uid,
        'required' => (bool) $required,
        'initial' => (string) $initial,
        'preselectNotice' => (bool) $preselectNotice,
    ];
@endphp

@once
    <style>
        [x-cloak] { display: none !important; }

        .sp-fieldset {
            border: 1px solid #E7E9E5;
            border-radius: 0.75rem;
            padding: 0.75rem;
            margin: 0;
        }

        .sp-legend {
            display: block;
            padding: 0 0.25rem;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #19355F;
        }

        .sp-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        @media (min-width: 640px) {
            .sp-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }

        .sp-chip {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 2px;
            border: 1px solid #D1D5DB;
            border-radius: 0.5rem;
            background: #fff;
            padding: 0.5rem 0.25rem;
            text-align: center;
            color: #19355F;
            cursor: pointer;
            transition: border-color 0.12s, background-color 0.12s, box-shadow 0.12s;
            min-height: 44px;
        }

        .sp-chip:hover { border-color: #55B85A; background: #F6F4EE; }

        .sp-chip.is-selected {
            border-color: #55B85A;
            background: #EDF5EC;
            box-shadow: 0 0 0 2px #55B85A;
        }

        .sp-chip.is-disabled {
            cursor: not-allowed;
            border-color: #E5E7EB;
            background: #F9FAFB;
            color: #9CA3AF;
            opacity: 0.85;
        }

        .sp-chip.is-disabled:hover { border-color: #E5E7EB; background: #F9FAFB; }

        /* Visible keyboard focus — never color alone. */
        .sp-chip:focus-within {
            outline: 2px solid #367E4B;
            outline-offset: 2px;
            z-index: 1;
        }

        /* Visually hidden radio (self-contained: the compiled Tailwind
           build may not ship sr-only for this new file). */
        .sp-radio {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .sp-time { font-size: 0.75rem; font-weight: 600; line-height: 1.15; }

        .sp-badge {
            font-size: 0.625rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #6B7280;
        }

        .sp-selected-tag {
            font-size: 0.625rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #367E4B;
        }

        .sp-status { margin-top: 0.25rem; }
        .sp-status > * + * { margin-top: 0.5rem; }

        .sp-msg { font-size: 0.75rem; color: #657083; }

        .sp-error-box {
            border: 1px solid #FECACA;
            border-radius: 0.5rem;
            background: #FEF2F2;
            padding: 0.5rem;
            font-size: 0.75rem;
        }

        .sp-warn-box {
            border: 1px solid #FDE68A;
            border-radius: 0.5rem;
            background: #FFFBEB;
            padding: 0.5rem;
            font-size: 0.75rem;
            font-weight: 500;
            color: #92400E;
        }

        .sp-retry {
            margin-top: 0.375rem;
            border: 1px solid #FCA5A5;
            border-radius: 0.5rem;
            background: #fff;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #B91C1C;
            cursor: pointer;
            transition: background-color 0.12s;
            min-height: 32px;
        }

        .sp-retry:hover { background: #FEE2E2; }

        .sp-retry:focus-visible { outline: 2px solid #B91C1C; outline-offset: 2px; }

        .sp-error-text { margin-top: 0.375rem; font-size: 0.75rem; font-weight: 500; color: #DC2626; }
    </style>

    {{-- Classic inline script: executes during HTML parse, BEFORE the
         deferred Vite module runs Alpine.start() — the same convention
         appointments/calendar.blade.php already uses for its factory. --}}
    <script>
        /*
         * Shared slot-picker factory (Phase 4). ONE implementation for
         * all four forms. The 18-slot catalog, labels and states come
         * from the availability endpoint response — never generated
         * here. Server validation stays authoritative.
         */
        function slotPicker(config) {
            return {
                url: config.url,
                timeName: config.timeName,
                dateId: config.dateId,
                pickerId: config.pickerId,
                required: config.required !== false,
                preselectNotice: !!config.preselectNotice,

                date: '',
                selected: '',
                slots: [],
                fullyUnavailable: false,
                loading: false,
                error: '',
                slotsLoaded: false,
                // Monotonic fetch token: an out-of-order (stale) response
                // whose seq no longer matches is DROPPED and can never
                // overwrite newer results (Part C).
                requestSeq: 0,
                // Preselection that is validated AFTER the fetch
                // (old() value or the patient-requested staff time).
                pendingInitial: config.initial || '',
                notice: '',

                get ready() {
                    return !this.loading
                        && !this.error
                        && this.slotsLoaded
                        && !this.fullyUnavailable
                        && (!this.required || this.selected !== '');
                },

                get availableCount() {
                    return this.slots.filter(function (s) { return s.state === 'available'; }).length;
                },

                init() {
                    var self = this;
                    var dateEl = document.getElementById(this.dateId);

                    if (dateEl) {
                        this.date = dateEl.value || '';
                        ['change', 'input'].forEach(function (evt) {
                            dateEl.addEventListener(evt, function () {
                                // Part C: date changed — clear the invalid
                                // previous selection and refetch; the seq
                                // guard drops any in-flight older response.
                                self.date = dateEl.value || '';
                                self.refresh();
                            });
                        });
                    }

                    if (this.date) {
                        this.refresh();
                    } else {
                        this.emitState();
                    }
                },

                // Programmatic sync (staff confirm modal): set the date
                // and a candidate time, then refetch. The candidate is
                // restored only when the endpoint reports it available —
                // a legacy non-grid time never matches and is never
                // silently rounded.
                sync(date, time) {
                    this.pendingInitial = time || '';
                    this.selected = '';
                    this.notice = '';
                    var dateEl = document.getElementById(this.dateId);
                    this.date = date || (dateEl ? dateEl.value : '') || '';
                    this.refresh();
                },

                async refresh() {
                    var seq = ++this.requestSeq;
                    this.loading = true;
                    this.error = '';
                    this.notice = '';

                    if (!this.date) {
                        // Cleared/empty date: drop everything; an old
                        // selection can never survive an unknown date.
                        this.slots = [];
                        this.slotsLoaded = false;
                        this.fullyUnavailable = false;
                        this.selected = '';
                        this.pendingInitial = '';
                        this.loading = false;
                        this.emitState();
                        return;
                    }

                    try {
                        var response = await fetch(
                            this.url + '?date=' + encodeURIComponent(this.date),
                            { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }
                        );

                        if (seq !== this.requestSeq) {
                            return; // stale response — a newer fetch owns the state
                        }

                        if (!response.ok) {
                            throw new Error('slots-http-' + response.status);
                        }

                        var data = await response.json();

                        if (seq !== this.requestSeq) {
                            return; // stale response — drop it
                        }

                        this.slots = Array.isArray(data.slots) ? data.slots : [];
                        this.fullyUnavailable = !!data.fully_unavailable;
                        this.slotsLoaded = true;

                        var selected = this.selected;
                        var stillAvailable = this.slots.some(function (s) {
                            return s.start === selected && s.state === 'available';
                        });

                        // Restore the previous/preselected value ONLY if
                        // it is a returned AVAILABLE slot (Part C rule 5).
                        if (this.selected && !stillAvailable) {
                            this.selected = '';
                        }

                        if (!this.selected && this.pendingInitial) {
                            var candidate = this.pendingInitial;
                            var matches = this.slots.some(function (s) {
                                return s.start === candidate && s.state === 'available';
                            });

                            if (matches) {
                                this.selected = candidate;
                            } else if (this.preselectNotice) {
                                // Staff confirm: the patient-requested time
                                // is occupied, past, closed or non-grid.
                                // Never rounded — staff must pick another.
                                this.notice = 'The patient-requested time is not available. Please choose another available slot below.';
                            }

                            this.pendingInitial = '';
                        }
                    } catch (e) {
                        if (seq !== this.requestSeq) {
                            return;
                        }

                        this.slots = [];
                        this.slotsLoaded = false;
                        this.fullyUnavailable = false;
                        this.selected = '';
                        // Mandated copy (Part C): never allow submission of
                        // an unverified timed slot after a failed fetch.
                        this.error = 'Unable to load appointment availability. Please try again.';
                    } finally {
                        if (seq === this.requestSeq) {
                            this.loading = false;
                            this.emitState();
                        }
                    }
                },

                retry() {
                    this.refresh();
                },

                emitState() {
                    // Bubbles to the enclosing form so submit buttons can
                    // disable themselves while availability is unresolved.
                    this.$dispatch('slot-picker-state', {
                        id: this.pickerId,
                        ready: this.ready,
                    });
                },

                stateBadge(state) {
                    return {
                        occupied: 'Booked',
                        past: 'Past',
                        unavailable: 'Clinic Unavailable',
                    }[state] || '';
                },

                stateDescription(state) {
                    return {
                        available: 'Available',
                        occupied: 'Booked — this slot already has a confirmed appointment',
                        past: 'Past — this time has already passed',
                        unavailable: 'Clinic Unavailable — the clinic is closed for this time',
                    }[state] || '';
                },
            };
        }
    </script>
@endonce

<div data-slot-picker
     data-picker-id="{{ $uid }}"
     data-slot-time="{{ $uid }}"
     x-data="slotPicker({{ \Illuminate\Support\Js::from($config) }})"
     x-init="init()"
     x-on:depla-slot-sync.window="if ($event.detail && $event.detail.id === pickerId) { sync($event.detail.date, $event.detail.time); }"
     x-cloak>

    <fieldset class="sp-fieldset" :aria-busy="loading ? 'true' : 'false'">
        <legend class="sp-legend">
            {{ $label }}
            @if ($required)
                <span class="text-red-600" aria-hidden="true">*</span>
            @endif
        </legend>

        {{-- Hidden HH:MM carrier — the ONLY field the backend reads.
             Disabled while availability is unresolved (or when a parent
             expression hides the picker, e.g. delivery requests) so an
             unverified time can never submit (Part C). --}}
        <input type="hidden"
               :name="timeName"
               :value="selected"
               :disabled="!ready || ({{ $hideExpr }})"
               data-slot-carrier>

        {{-- ═══ Status region (loading / error+retry / full-day / notice) --}}
        <div class="sp-status" role="status" aria-live="polite">
            <p x-show="loading" class="sp-msg">Loading available times&hellip;</p>

            <div x-show="!loading && error" class="sp-error-box">
                <p class="font-medium" style="color: #B91C1C;" x-text="error"></p>
                <button type="button"
                        x-on:click="retry()"
                        class="sp-retry"
                        data-slot-retry>
                    Retry
                </button>
            </div>

            <p x-show="!loading && !error && fullyUnavailable"
               class="sp-warn-box"
               data-slot-full-day-message>
                The clinic is unavailable for appointments on the selected date. Please choose another available date.
            </p>

            <p x-show="!loading && !error && !fullyUnavailable && slotsLoaded && availableCount === 0"
               class="sp-warn-box">
                No available time slots remain for this date. Please choose another date.
            </p>

            <p x-show="!loading && !error && notice"
               class="sp-warn-box"
               x-text="notice"
               data-slot-preselect-notice></p>
        </div>

        {{-- ═══ The slot radio grid (server catalog as source of truth) --}}
        <div x-show="!loading && !error && !fullyUnavailable && slotsLoaded"
             class="sp-grid"
             role="radiogroup"
             aria-label="{{ $label }}">
            <template x-for="slot in slots" :key="slot.start">
                <label class="sp-chip"
                       x-bind:class="slot.state !== 'available'
                           ? 'is-disabled'
                           : (selected === slot.start ? 'is-selected' : '')"
                       x-bind:data-state="slot.state"
                       x-bind:data-slot-start="slot.start">
                    <input type="radio"
                           name="{{ $radioName }}"
                           x-bind:value="slot.start"
                           x-model="selected"
                           x-bind:disabled="slot.state !== 'available'"
                           x-bind:aria-label="slot.label + ' — ' + stateDescription(slot.state)"
                           class="sp-radio">
                    <span class="sp-time" x-text="slot.label"></span>
                    <span x-show="slot.state !== 'available'"
                          x-text="stateBadge(slot.state)"
                          class="sp-badge"></span>
                    <span x-show="slot.state === 'available' && selected === slot.start"
                          class="sp-selected-tag">Selected</span>
                </label>
            </template>
        </div>
    </fieldset>

    {{-- Server validation feedback for this field (FormRequest /
         DomainException messages) stays exactly where it was — the
         picker never suppresses or rewrites backend errors. --}}
    @error($timeName)
        <p class="sp-error-text" role="alert" data-slot-field-error>{{ $message }}</p>
    @enderror
</div>
