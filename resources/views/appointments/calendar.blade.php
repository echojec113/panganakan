{{-- DEPLA Phase 3B.2A - Appointments Calendar (Admin + Staff, READ-ONLY).
     Google-Calendar-inspired Month / Year / Day browser over the
     appointments table. No write actions of any kind: chips and rows only
     open a read-only details panel. Patient identity resolves through the
     Appointment->patient relationship; archived patient records degrade to
     a safe "Patient record unavailable" state with no profile link. --}}

<x-app-layout>
    @php
        // Period navigation URLs keep the current type/status filters so
        // switching month/year/day or stepping prev/next never drops them.
        $baseUrl = fn (array $overrides) => route('appointments.index', array_filter(array_merge([
            'view' => $view,
            'date' => $date->toDateString(),
            'type' => $typeFilter,
            'status' => $statusFilter,
        ], $overrides), fn ($v) => $v !== null && $v !== ''));

        $dayUrl = fn (string $d) => $baseUrl(['view' => 'day', 'date' => $d]);
        $monthUrl = fn (string $d) => $baseUrl(['view' => 'month', 'date' => $d]);
        $monthViewUrl = $baseUrl(['view' => 'month']);
        $yearViewUrl = $baseUrl(['view' => 'year']);
        $dayViewUrl = $baseUrl(['view' => 'day']);
        $todayUrl = $baseUrl(['date' => \Illuminate\Support\Carbon::now('Asia/Manila')->toDateString()]);

        $previousDate = match ($view) {
            'year' => $date->copy()->subYearNoOverflow()->toDateString(),
            'day' => $date->copy()->subDay()->toDateString(),
            default => $date->copy()->subMonthNoOverflow()->toDateString(),
        };
        $nextDate = match ($view) {
            'year' => $date->copy()->addYearNoOverflow()->toDateString(),
            'day' => $date->copy()->addDay()->toDateString(),
            default => $date->copy()->addMonthNoOverflow()->toDateString(),
        };
        $previousUrl = $baseUrl(['date' => $previousDate]);
        $nextUrl = $baseUrl(['date' => $nextDate]);
    @endphp

    <style>
        .cal-card { background: #fff; border: 1px solid #E7E9E5; border-radius: 14px; }
        .cal-toolbar-btn { display:inline-flex; align-items:center; gap:.35rem; border-radius:.6rem; padding:.4rem .75rem; font-size:.8rem; font-weight:600; color:#19355F; background:#fff; border:1px solid #E7E9E5; transition:background .15s; }
        .cal-toolbar-btn:hover { background:#EDF5EC; }
        .cal-view-btn { border-radius:.6rem; padding:.4rem .8rem; font-size:.8rem; font-weight:600; color:#657083; background:#fff; border:1px solid #E7E9E5; }
        .cal-view-btn.active { background:#55B85A; border-color:#55B85A; color:#fff; }
        .cal-grid { display:grid; grid-template-columns:repeat(7, minmax(0,1fr)); }
        .cal-dow { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:#657083; text-align:center; padding:.5rem .25rem; }
        .cal-cell { min-height:6.5rem; border-top:1px solid #E7E9E5; border-left:1px solid #E7E9E5; padding:.35rem; display:flex; flex-direction:column; gap:.25rem; }
        .cal-cell:nth-child(7n+1) { border-left:none; }
        .cal-cell.outside { background:#FCFBF8; }
        .cal-daynum { display:inline-flex; align-items:center; justify-content:center; width:1.6rem; height:1.6rem; border-radius:999px; font-size:.78rem; font-weight:600; color:#19355F; }
        .cal-daynum:hover { background:#EDF5EC; }
        .cal-daynum.today { background:#55B85A; color:#fff; }
        .cal-event { display:flex; align-items:center; gap:.3rem; width:100%; text-align:left; border-radius:.45rem; padding:.18rem .35rem; font-size:.7rem; font-weight:600; line-height:1.25; border:1px solid transparent; cursor:pointer; }
        .cal-event:focus-visible { outline:2px solid #55B85A; outline-offset:1px; }
        .cal-event.pending { background:#FEF3C7; color:#92400E; border-color:#FDE68A; }
        .cal-event.confirmed { background:#EDF5EC; color:#367E4B; border-color:#CBE5CF; }
        .cal-event.completed { background:#F1F5F9; color:#475569; border-color:#E2E8F0; }
        .cal-event.cancelled, .cal-event.declined { background:#F8FAFC; color:#94A3B8; border-color:#E2E8F0; text-decoration:line-through; }
        .cal-more { font-size:.68rem; font-weight:600; color:#367E4B; padding:.1rem .35rem; }
        .cal-more:hover { text-decoration:underline; }
        .cal-legend-dot { width:.6rem; height:.6rem; border-radius:999px; display:inline-block; }
        /* Alpine cloak: hide x-cloak elements until Alpine initializes and
           applies x-show. Without this rule the DECLINE/CANCEL/COMPLETE/
           CONFIRM forms and their submit buttons render visible (with an
           empty :action, since selected is null) for the first paint. */
        [x-cloak] { display: none !important; }
        @media (max-width: 640px) {
            .cal-cell { min-height:4.25rem; padding:.2rem; }
            .cal-event { font-size:.6rem; padding:.12rem .2rem; }
            .cal-dow { font-size:.55rem; }
        }
    </style>

    <div class="min-h-screen bg-[#FCFBF8]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <x-app-header class="mb-6">
                <x-slot name="title">Appointments</x-slot>
                <x-slot name="subtitle">Manage clinic appointment requests — confirm, decline, cancel, complete, or record a walk-in for an existing patient.</x-slot>
                <x-slot name="actions">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button"
                                x-data
                                x-on:click="$dispatch('open-modal', 'walk-in-appointment')"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-[#55B85A] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#4aa04c] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#367E4B]">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                            Add Walk-in Appointment
                        </button>
                        <a href="{{ $previousUrl }}" class="cal-toolbar-btn" aria-label="Previous period">&lsaquo; Prev</a>
                        <a href="{{ $todayUrl }}" class="cal-toolbar-btn">Today</a>
                        <a href="{{ $nextUrl }}" class="cal-toolbar-btn" aria-label="Next period">Next &rsaquo;</a>
                        <div class="flex items-center gap-1 rounded-lg border border-[#E7E9E5] bg-white p-1" role="group" aria-label="Calendar view">
                            <a href="{{ $monthViewUrl }}" class="cal-view-btn {{ $view === 'month' ? 'active' : '' }}">Month</a>
                            <a href="{{ $yearViewUrl }}" class="cal-view-btn {{ $view === 'year' ? 'active' : '' }}">Year</a>
                            <a href="{{ $dayViewUrl }}" class="cal-view-btn {{ $view === 'day' ? 'active' : '' }}">Day</a>
                        </div>
                    </div>
                </x-slot>
            </x-app-header>

            {{-- Success / error feedback after a management action. The
                 redirect back() preserves the calendar view/date/filters. --}}
            @if (session('success'))
                <x-flash type="success" :message="session('success')" class="mb-4" />
            @endif
            @if ($errors->any())
                <x-flash type="error" class="mb-4">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-flash>
            @endif

            {{-- Filters (GET only - browsing never mutates data) --}}
            <form method="GET" action="{{ route('appointments.index') }}" class="cal-card mb-5 flex flex-wrap items-end gap-3 p-4">
                <input type="hidden" name="view" value="{{ $view }}">
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">

                <div>
                    <label for="filter-type" class="block text-xs font-semibold uppercase tracking-wider text-[#657083] mb-1">Service Type</label>
                    <select id="filter-type" name="type" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[#55B85A] focus:ring-[#55B85A]">
                        <option value="all" {{ $typeFilter === 'all' ? 'selected' : '' }}>All Types</option>
                        @foreach ($typeLabels as $typeValue => $typeLabel)
                            <option value="{{ $typeValue }}" {{ $typeFilter === $typeValue ? 'selected' : '' }}>{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filter-status" class="block text-xs font-semibold uppercase tracking-wider text-[#657083] mb-1">Status</label>
                    <select id="filter-status" name="status" class="rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[#55B85A] focus:ring-[#55B85A]">
                        <option value="all" {{ $statusFilter === 'all' ? 'selected' : '' }}>All Statuses</option>
                        <option value="active" {{ $statusFilter === 'active' ? 'selected' : '' }}>Active (Pending, Confirmed, Completed)</option>
                        @foreach ($statusLabels as $statusValue => $statusLabel)
                            <option value="{{ $statusValue }}" {{ $statusFilter === $statusValue ? 'selected' : '' }}>{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="submit" class="rounded-lg bg-[#55B85A] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#4aa04c]">Apply Filters</button>
            </form>

            {{-- Period title + legend --}}
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-bold text-slate-900">
                    @if ($view === 'year')
                        {{ $yearLabel }}
                    @elseif ($view === 'day')
                        {{ $dayLabel }}
                    @else
                        {{ $monthLabel }}
                    @endif
                </h2>

                <div class="flex flex-wrap items-center gap-4 text-xs font-medium text-[#657083]">
                    <span class="inline-flex items-center gap-1.5"><span class="cal-legend-dot" style="background:#FDE68A;"></span> Pending</span>
                    <span class="inline-flex items-center gap-1.5"><span class="cal-legend-dot" style="background:#55B85A;"></span> Confirmed</span>
                    <span class="inline-flex items-center gap-1.5"><span class="cal-legend-dot" style="background:#CBD5E1;"></span> Completed</span>
                    <span class="inline-flex items-center gap-1.5"><span class="cal-legend-dot" style="background:#F1F5F9; border:1px solid #E2E8F0;"></span> Cancelled / Declined</span>
                    <span class="inline-flex items-center gap-1.5"><span class="cal-legend-dot" style="background:#FB923C;"></span> Cancellation request pending</span>
                    <span class="inline-flex items-center gap-1.5"><span class="cal-legend-dot" style="background:#6366F1;"></span> Reschedule request pending</span>
                </div>
            </div>

            <div x-data="appointmentDetailsPanel()"
                 x-init="
                     @if ($errors->hasAny(['patient_id', 'appointment_type', 'appointment_date', 'appointment_time', 'walk_in']))
                         $nextTick(() => $dispatch('open-modal', 'walk-in-appointment'));
                     @endif
                     @if ($errors->hasAny(['confirmed_date', 'confirmed_time', 'appointment', 'change_request']) && session('errorAppointmentId'))
                         $nextTick(() => open({{ (int) session('errorAppointmentId') }}));
                     @endif
                 ">

                {{-- Full read-only details for every visible appointment,
                     embedded server-side so browsing makes no extra request
                     and no appointment-id endpoint exists to manipulate. --}}
                <script type="application/json" id="appointmentDetailsData">{!! json_encode($details ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>

                @if (! $hasAnyEvent)
                    <div class="cal-card mb-5 border-dashed bg-gray-50 p-10 text-center">
                        <p class="text-sm text-gray-500">No appointments scheduled for this period.</p>
                    </div>
                @endif

                {{-- ══════════ MONTH VIEW ══════════ --}}
                @if ($view === 'month')
                    <div class="cal-card overflow-hidden">
                        <div class="cal-grid border-b border-[#E7E9E5] bg-[#FCFBF8]">
                            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)
                                <div class="cal-dow">{{ $dayName }}</div>
                            @endforeach
                        </div>
                        <div class="cal-grid">
                            @foreach ($weeks as $week)
                                @foreach ($week as $day)
                                    <div class="cal-cell {{ $day['isCurrentMonth'] ? '' : 'outside' }}">
                                        <a href="{{ $dayUrl($day['date']) }}"
                                           class="cal-daynum {{ $day['isToday'] ? 'today' : '' }}"
                                           aria-label="Open day view for {{ $day['date'] }}">
                                            {{ $day['dayNumber'] }}
                                        </a>

                                        @foreach ($day['events'] as $event)
                                            @php
                                                // Type-aware pending change-request marker (Phase 3D.3B Step 5):
                                                // the appointment's own STATUS styling never changes - the "!"
                                                // only signals that a patient request awaits review.
                                                $pendingChangeLabel = match ($event['pendingChangeRequestType'] ?? null) {
                                                    'cancellation' => 'Cancellation request pending review',
                                                    'reschedule' => 'Reschedule request pending review',
                                                    default => null,
                                                };
                                            @endphp
                                            <button type="button"
                                                    class="cal-event {{ $event['status'] }}"
                                                    title="{{ $event['patientName'] }} — {{ $event['typeLabel'] }}{{ $event['time'] ? ' at ' . $event['time'] : ' (no time set)' }}{{ $pendingChangeLabel !== null ? ' — ' . $pendingChangeLabel : '' }}"
                                                    data-appointment-id="{{ $event['id'] }}"
                                                    x-on:click="open({{ $event['id'] }})">
                                                <span aria-hidden="true">•</span>
                                                <span class="truncate">{{ $event['patientLastName'] }}{{ $event['time'] ? ' · ' . $event['time'] : '' }}</span>
                                                @if (! empty($event['hasPendingChangeRequest']) && $pendingChangeLabel !== null)
                                                    <span class="ml-0.5 shrink-0 font-bold text-[#9A3412]"
                                                          style="color:#9A3412;"
                                                          title="{{ $pendingChangeLabel }}"
                                                          aria-label="{{ $pendingChangeLabel }}">!</span>
                                                @endif
                                            </button>
                                        @endforeach

                                        @if ($day['hiddenEventCount'] > 0)
                                            <a href="{{ $dayUrl($day['date']) }}" class="cal-more">
                                                +{{ $day['hiddenEventCount'] }} more
                                            </a>
                                        @endif
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- ══════════ YEAR VIEW ══════════ --}}
                @if ($view === 'year')
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        @foreach ($months as $month)
                            <a href="{{ $monthUrl($date->copy()->month($month['month'])->startOfMonth()->toDateString()) }}"
                               class="cal-card block p-5 transition hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#55B85A]">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-bold text-[#19355F]">{{ $month['name'] }}</span>
                                    <span class="flex items-center gap-2">
                                        @if (($month['pendingChangeRequestCount'] ?? 0) > 0)
                                            <span class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-orange-100 px-1 text-[10px] font-bold text-[#9A3412]"
                                                  title="{{ $month['pendingChangeRequestCount'] }} change request(s) pending review"
                                                  aria-label="{{ $month['pendingChangeRequestCount'] }} change request(s) pending review">!</span>
                                        @endif
                                        @if ($month['pendingCount'] > 0)
                                            <span class="cal-legend-dot" style="background:#FDE68A;" title="{{ $month['pendingCount'] }} pending"></span>
                                        @endif
                                    </span>
                                </div>
                                <p class="mt-2 text-2xl font-bold {{ $month['count'] > 0 ? 'text-[#367E4B]' : 'text-gray-300' }}">
                                    {{ $month['count'] }}
                                </p>
                                <p class="mt-1 text-xs text-[#657083]">
                                    {{ $month['count'] === 1 ? 'appointment' : 'appointments' }}
                                    @if ($month['pendingCount'] > 0)
                                        · {{ $month['pendingCount'] }} pending
                                    @endif
                                    @if (($month['pendingChangeRequestCount'] ?? 0) > 0)
                                        · {{ $month['pendingChangeRequestCount'] }} change {{ Str::plural('request', $month['pendingChangeRequestCount']) }} pending review
                                    @endif
                                </p>
                            </a>
                        @endforeach
                    </div>
                @endif

                {{-- ══════════ DAY VIEW ══════════ --}}
                @if ($view === 'day')
                    <div class="cal-card divide-y divide-[#E7E9E5]">
                        @foreach ($events as $event)
                            <div class="flex flex-wrap items-center gap-3 px-5 py-4">
                                <span class="w-24 shrink-0 text-sm font-semibold {{ $event['time'] ? 'text-[#19355F]' : 'text-[#657083]' }}">
                                    {{ $event['time'] ?? 'No time set' }}
                                </span>

                                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold {{ $event['status'] === 'pending' ? 'bg-amber-100 text-amber-800' : ($event['status'] === 'confirmed' ? 'bg-[#EDF5EC] text-[#367E4B]' : 'bg-slate-100 text-slate-600') }}">
                                    {{ $event['typeLabel'] }}
                                </span>

                                @if (($event['pendingChangeRequestType'] ?? null) === 'cancellation')
                                    <span class="inline-flex items-center rounded-full bg-orange-100 px-3 py-1 text-xs font-bold text-orange-800"
                                          title="A patient cancellation request is awaiting review">
                                        Cancellation Request
                                    </span>
                                @elseif (($event['pendingChangeRequestType'] ?? null) === 'reschedule')
                                    <span class="inline-flex items-center rounded-full bg-indigo-100 px-3 py-1 text-xs font-bold text-indigo-800"
                                          title="A patient reschedule request is awaiting review">
                                        Reschedule Request
                                    </span>
                                @endif

                                <span class="min-w-0 flex-1 text-sm font-medium text-slate-900">
                                    {{ $event['patientName'] }}
                                </span>

                                <x-status-badge :variant="match($event['status']) {
                                    'confirmed' => 'success',
                                    'pending' => 'warning',
                                    default => 'neutral',
                                }">{{ $event['statusLabel'] }}</x-status-badge>

                                <button type="button"
                                        class="rounded-lg border border-[#E7E9E5] px-3 py-1.5 text-xs font-semibold text-[#367E4B] hover:bg-[#EDF5EC] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#55B85A]"
                                        data-appointment-id="{{ $event['id'] }}"
                                        x-on:click="open({{ $event['id'] }})">
                                    Details
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- ══════════ DETAILS + MANAGEMENT ACTIONS MODAL ══════════ --}}
                <x-modal name="appointment-details" focusable max-width="lg">
                    <div class="p-6" x-show="selected" x-cloak>
                        <div class="mb-4 flex items-start justify-between gap-4">
                            <h3 class="text-lg font-bold text-slate-900">Appointment Details</h3>
                            <span class="rounded-full px-3 py-1 text-xs font-bold"
                                  :class="statusBadgeClass(selected?.status)"
                                  x-text="selected?.statusLabel"></span>
                        </div>

                        <div class="rounded-xl border border-[#E7E9E5] bg-[#FCFBF8] p-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-[#657083]">Patient</p>
                            <p class="mt-1 text-sm font-bold text-[#19355F]" x-text="selected?.patientFullName"></p>
                            <p class="mt-0.5 text-sm text-[#657083]">
                                Contact: <span class="font-medium text-slate-700" x-text="selected?.patientContact"></span>
                            </p>
                            <a x-show="selected?.profileUrl"
                               :href="selected?.profileUrl"
                               class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-[#55B85A] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#4aa04c] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#367E4B]">
                                View Patient Profile
                            </a>
                            <p x-show="!selected?.profileUrl" class="mt-2 text-xs font-medium text-amber-700">
                                Patient record unavailable — profile link is hidden.
                            </p>
                        </div>

                        <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wider text-[#657083]">Service</dt>
                                <dd class="mt-0.5 font-medium text-slate-800" x-text="selected?.typeLabel"></dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wider text-[#657083]">Booking Source</dt>
                                <dd class="mt-0.5 font-medium text-slate-800" x-text="selected?.sourceLabel"></dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wider text-[#657083]">Requested</dt>
                                <dd class="mt-0.5 font-medium text-slate-800">
                                    <span x-text="selected?.requestedDate"></span>
                                    <span class="text-[#657083]" x-text="' · ' + (selected?.requestedTimeLabel ?? '')"></span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wider text-[#657083]">Confirmed</dt>
                                {{-- One placeholder when neither field is set; date + time when both
                                     exist; date alone when only the date is set. --}}
                                <dd class="mt-0.5 font-medium text-slate-800" x-text="(() => {
                                    const d = selected?.confirmedDateLabel;
                                    const t = selected?.confirmedTimeLabel;
                                    if (!d && !t) return 'Not yet confirmed';
                                    return [d, t].filter(Boolean).join(' · ');
                                })()"></dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wider text-[#657083]">Reviewed By</dt>
                                <dd class="mt-0.5 font-medium text-slate-800" x-text="selected?.reviewerName"></dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wider text-[#657083]">Reviewed Date</dt>
                                <dd class="mt-0.5 font-medium text-slate-800" x-text="selected?.reviewedAtLabel"></dd>
                            </div>
                        </dl>

                        <div x-show="selected?.declineReason" class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm">
                            <p class="text-xs font-semibold uppercase tracking-wider text-amber-800">Decline Reason</p>
                            <p class="mt-1 text-amber-900" x-text="selected?.declineReason"></p>
                        </div>

                        <div x-show="selected?.cancellationReason" class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-700">Cancellation Reason</p>
                            <p class="mt-1 text-gray-800" x-text="selected?.cancellationReason"></p>
                        </div>

                        {{-- ══════════ PENDING CANCELLATION REQUEST (Phase 3D.2C) ══════════
                             Shown only while a patient-submitted cancellation
                             request is still pending review. The confirmed
                             schedule above stays active until staff act. --}}
                        <div x-show="selected?.cancellationRequest" class="mt-4 rounded-xl border border-orange-200 bg-orange-50 p-4 text-sm">
                            <p class="text-xs font-semibold uppercase tracking-wider text-orange-800">Pending Cancellation Request</p>
                            <p class="mt-1 text-xs text-orange-700">Submitted <span x-text="selected?.cancellationRequest?.requestedAtLabel"></span></p>
                            <p class="mt-1 text-orange-900" x-text="selected?.cancellationRequest?.reason"></p>
                            <p class="mt-2 text-xs text-orange-700">
                                The confirmed schedule stays active until this request is reviewed. Approving cancels the appointment with the patient's reason; declining keeps it confirmed.
                            </p>
                        </div>

                        {{-- ══════════ PENDING RESCHEDULE REQUEST (Phase 3D.3B Step 5) ══════════
                             Shown only while a patient-submitted reschedule
                             request is still pending review. The current
                             confirmed schedule and the proposed schedule are
                             displayed separately; a null time (date-only
                             delivery, K5) renders the date alone - the panel
                             never invents a clock time. --}}
                        <div x-show="selected?.rescheduleRequest" class="mt-4 rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm">
                            <p class="text-xs font-semibold uppercase tracking-wider text-indigo-800">Pending Reschedule Request</p>
                            <p class="mt-1 text-xs text-indigo-700">
                                Request type: Reschedule · Submitted <span x-text="selected?.rescheduleRequest?.requestedAtLabel"></span>
                            </p>

                            <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <div class="rounded-lg border border-indigo-100 bg-white/70 p-2">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-700">Current Confirmed Schedule</p>
                                    <p class="mt-0.5 font-semibold text-indigo-900" x-text="(() => {
                                        const d = selected?.rescheduleRequest?.currentDateLabel;
                                        const t = selected?.rescheduleRequest?.currentTimeLabel;
                                        if (!d) return 'Not provided';
                                        return [d, t].filter(Boolean).join(' · ');
                                    })()"></p>
                                </div>
                                <div class="rounded-lg border border-indigo-100 bg-white/70 p-2">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-indigo-700">Proposed Schedule</p>
                                    <p class="mt-0.5 font-semibold text-indigo-900" x-text="(() => {
                                        const d = selected?.rescheduleRequest?.proposedDateLabel;
                                        const t = selected?.rescheduleRequest?.proposedTimeLabel;
                                        if (!d) return 'Not provided';
                                        return [d, t].filter(Boolean).join(' · ');
                                    })()"></p>
                                </div>
                            </div>

                            <p x-show="selected?.rescheduleRequest?.reason" class="mt-2 text-indigo-900">
                                Patient's reason: <span x-text="selected?.rescheduleRequest?.reason"></span>
                            </p>
                            <p class="mt-2 text-xs text-indigo-700">
                                The confirmed schedule stays active until this request is reviewed. Approving moves the appointment to the proposed schedule above (the status stays Confirmed); declining keeps the original confirmed schedule.
                            </p>
                        </div>

                        {{-- ══════════ MANAGEMENT ACTIONS (Phase 3B.2B) ══════════
                             Buttons follow the CURRENT server state; the
                             server re-validates every transition, so a stale
                             modal can never perform an illegal action. --}}
                        <div class="mt-5 border-t border-[#E7E9E5] pt-4">
                            {{-- Pending: Confirm / Decline --}}
                            <div x-show="!action && selected?.status === 'pending'" class="flex flex-wrap gap-2">
                                <button type="button"
                                        x-on:click="action = 'confirm'"
                                        class="rounded-lg bg-[#55B85A] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#4aa04c] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#367E4B]">
                                    Confirm
                                </button>
                                <button type="button"
                                        x-on:click="action = 'decline'"
                                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-600">
                                    Decline
                                </button>
                            </div>

                            {{-- Confirmed: Cancel / Mark Completed --}}
                            <div x-show="!action && selected?.status === 'confirmed'" class="flex flex-wrap gap-2">
                                <button type="button"
                                        x-on:click="action = 'cancel'"
                                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-red-600">
                                    Cancel
                                </button>
                                <button type="button"
                                        x-on:click="action = 'complete'"
                                        class="rounded-lg bg-[#55B85A] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#4aa04c] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#367E4B]">
                                    Mark Completed
                                </button>
                            </div>

                            {{-- Confirmed + pending patient cancellation request
                                 (Phase 3D.2C): review actions. Direct Cancel /
                                 Complete above stay available - the service
                                 auto-resolves the pending request in the same
                                 transaction if staff use them instead. --}}
                            <div x-show="!action && selected?.status === 'confirmed' && selected?.cancellationRequest" class="mt-2 flex flex-wrap gap-2">
                                <button type="button"
                                        x-on:click="action = 'approveRequest'"
                                        class="rounded-lg bg-[#9A3412] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#7C2D12] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#9A3412]">
                                    Approve Cancellation
                                </button>
                                <button type="button"
                                        x-on:click="action = 'declineRequest'"
                                        class="rounded-lg border border-[#9A3412] px-4 py-2 text-sm font-semibold text-[#9A3412] transition hover:bg-orange-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#9A3412]">
                                    Decline Cancellation Request
                                </button>
                            </div>

                            {{-- Confirmed + pending patient reschedule request
                                 (Phase 3D.3B Step 5): review actions. Direct
                                 Cancel / Complete above stay available - the
                                 service auto-resolves the pending request in
                                 the same transaction if staff use them
                                 instead. Never both change-request rows: the
                                 one-pending invariant guarantees at most one. --}}
                            <div x-show="!action && selected?.status === 'confirmed' && selected?.rescheduleRequest" class="mt-2 flex flex-wrap gap-2">
                                <button type="button"
                                        x-on:click="action = 'approveReschedule'"
                                        class="rounded-lg bg-[#4F46E5] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#4338CA] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#4F46E5]">
                                    Approve Reschedule
                                </button>
                                <button type="button"
                                        x-on:click="action = 'declineReschedule'"
                                        class="rounded-lg border border-[#4F46E5] px-4 py-2 text-sm font-semibold text-[#4F46E5] transition hover:bg-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#4F46E5]">
                                    Decline Reschedule Request
                                </button>
                            </div>

                            {{-- Terminal states: read-only --}}
                            <p x-show="!action && ['completed', 'cancelled', 'declined'].includes(selected?.status)"
                               class="text-xs font-medium text-[#657083]">
                                No further actions — this appointment is <span x-text="selected?.statusLabel"></span>.
                            </p>

                            {{-- CONFIRM --}}
                            <form x-show="action === 'confirm'" x-cloak
                                  method="POST" :action="selected?.confirmUrl"
                                x-on:submit="if (guardConfirmSchedule()) { submitting = true; } else { $event.preventDefault(); }"
                                  class="mt-1 space-y-3">
                                @csrf
                                <input type="hidden" name="_method" value="PATCH">

                                @error('appointment')
                                    <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-medium text-red-800">{{ $message }}</div>
                                @enderror

                                <div class="rounded-xl border border-[#E7E9E5] bg-[#FCFBF8] p-3 text-sm">
                                    <p class="font-semibold text-[#19355F]" x-text="selected?.patientFullName"></p>
                                    <p class="text-[#657083]">
                                        <span x-text="selected?.typeLabel"></span>
                                        · Requested: <span x-text="selected?.requestedDate"></span>
                                        <span x-text="' · ' + (selected?.requestedTimeLabel ?? '')"></span>
                                    </p>
                                </div>

                                <div>
                                    <label for="confirm-date" class="block text-xs font-semibold uppercase tracking-wider text-[#657083] mb-1">Confirmed Date</label>
                                    <input id="confirm-date" type="date" name="confirmed_date" required
                                           min="{{ $clinicToday }}"
                                           :value="{{ json_encode(old('confirmed_date')) }} ?? selected?.requestedDateIso ?? ''"
                                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[#55B85A] focus:ring-[#55B85A]">
                                    @error('confirmed_date')
                                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-1 text-xs text-[#657083]">Today ({{ $clinicToday }}) or a future date — Philippine time. Past schedules cannot be confirmed.</p>
                                </div>

                                {{-- Confirmed Time: ONE input carries name="confirmed_time" per
                                     appointment type. Alpine x-show only toggles visibility (CSS)
                                     and does NOT remove a control from the form, so both inputs
                                     below would otherwise be submitted and PHP would keep the LAST
                                     one - the hidden delivery input's empty value. That empty value
                                     overwrote the visible checkup/counseling time, turning it into
                                     null and tripping the "time is required" error (Bug: visible
                                     04:56 PM but server saw no time). x-bind:disabled removes the
                                     INACTIVE input from the submission entirely, so only the
                                     visible/active time is sent. Both inputs still render so
                                     Alpine can switch between them; the inactive one is disabled,
                                     never submitted, and never carries the field name into PHP. --}}
                                <div x-show="selected?.type !== 'delivery_request'">
                                    <label for="confirm-time" class="block text-xs font-semibold uppercase tracking-wider text-[#657083] mb-1">Confirmed Time</label>
                                    <input id="confirm-time" type="time" name="confirmed_time" required step="60"
                                           x-bind:disabled="selected?.type === 'delivery_request'"
                                           :value="{{ json_encode(old('confirmed_time')) }} ?? selected?.requestedTime ?? ''"
                                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[#55B85A] focus:ring-[#55B85A]">
                                    <p class="mt-1 text-xs text-[#657083]">Monday–Sunday, 8:00 AM to before 5:00 PM Philippine time (provisional clinic hours). Today's earlier times are blocked. No capacity limit — overlapping times are allowed.</p>
                                    @error('confirmed_time')
                                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div x-show="selected?.type === 'delivery_request'">
                                    <label for="confirm-time-delivery" class="block text-xs font-semibold uppercase tracking-wider text-[#657083] mb-1">Confirmed Time (optional)</label>
                                    <input id="confirm-time-delivery" type="time" name="confirmed_time" step="60"
                                           x-bind:disabled="selected?.type !== 'delivery_request'"
                                           :value="{{ json_encode(old('confirmed_time')) }}"
                                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[#55B85A] focus:ring-[#55B85A]">
                                    <p class="mt-1 text-xs text-[#657083]">Delivery requests are accepted 24/7 and may be date-only. Confirming is scheduling approval only — it does not reserve a bed or create an admission.</p>
                                    @error('confirmed_time')
                                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <p x-show="scheduleError" x-text="scheduleError"
                                   class="rounded-lg border border-red-200 bg-red-50 p-2 text-xs font-medium text-red-700"></p>

                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" :disabled="submitting"
                                            class="rounded-lg bg-[#55B85A] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#4aa04c] disabled:opacity-60"
                                            x-text="submitting ? 'Confirming…' : 'Confirm Appointment'"></button>
                                    <button type="button" x-on:click="action = null"
                                            class="rounded-lg border border-[#E7E9E5] px-4 py-2 text-sm font-semibold text-[#19355F] hover:bg-[#EDF5EC]">
                                        Back
                                    </button>
                                </div>
                            </form>

                            {{-- DECLINE --}}
                            <form x-show="action === 'decline'" x-cloak
                                  method="POST" :action="selected?.declineUrl"
                                  x-on:submit="submitting = true" class="mt-1 space-y-3">
                                @csrf
                                <input type="hidden" name="_method" value="PATCH">

                                <div class="rounded-xl border border-red-100 bg-red-50 p-3 text-sm">
                                    <p class="font-semibold text-red-900" x-text="selected?.patientFullName"></p>
                                    <p class="text-red-700">
                                        <span x-text="selected?.typeLabel"></span>
                                        · Requested: <span x-text="selected?.requestedDate"></span>
                                        <span x-text="' · ' + (selected?.requestedTimeLabel ?? '')"></span>
                                    </p>
                                    <p class="mt-1 text-xs text-red-700">The request is kept in history and remains visible through status filters.</p>
                                </div>

                                <div>
                                    <label for="decline-reason" class="block text-xs font-semibold uppercase tracking-wider text-[#657083] mb-1">Decline Reason (required)</label>
                                    <textarea id="decline-reason" name="decline_reason" rows="3" required minlength="3" maxlength="1000"
                                              placeholder="Explain why this request is being declined…"
                                              class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[#55B85A] focus:ring-[#55B85A]"></textarea>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" :disabled="submitting"
                                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 disabled:opacity-60"
                                            x-text="submitting ? 'Declining…' : 'Decline Appointment'"></button>
                                    <button type="button" x-on:click="action = null"
                                            class="rounded-lg border border-[#E7E9E5] px-4 py-2 text-sm font-semibold text-[#19355F] hover:bg-[#EDF5EC]">
                                        Back
                                    </button>
                                </div>
                            </form>

                            {{-- CANCEL --}}
                            <form x-show="action === 'cancel'" x-cloak
                                  method="POST" :action="selected?.cancelUrl"
                                  x-on:submit="submitting = true" class="mt-1 space-y-3">
                                @csrf
                                <input type="hidden" name="_method" value="PATCH">

                                <div class="rounded-xl border border-red-100 bg-red-50 p-3 text-sm">
                                    <p class="font-semibold text-red-900" x-text="selected?.patientFullName"></p>
                                    <p class="text-red-700">
                                        <span x-text="selected?.typeLabel"></span>
                                        · Confirmed: <span x-text="selected?.confirmedDateLabel"></span>
                                        <span x-text="' · ' + (selected?.confirmedTimeLabel ?? '')"></span>
                                    </p>
                                    <p class="mt-1 text-xs text-red-700">Cancelling keeps the original requested and confirmed schedules, and keeps the original reviewer on record.</p>
                                </div>

                                <div>
                                    <label for="cancel-reason" class="block text-xs font-semibold uppercase tracking-wider text-[#657083] mb-1">Cancellation Reason (required)</label>
                                    <textarea id="cancel-reason" name="cancellation_reason" rows="3" required minlength="3" maxlength="1000"
                                              placeholder="Explain why this appointment is being cancelled…"
                                              class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[#55B85A] focus:ring-[#55B85A]"></textarea>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" :disabled="submitting"
                                            class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 disabled:opacity-60"
                                            x-text="submitting ? 'Cancelling…' : 'Cancel Appointment'"></button>
                                    <button type="button" x-on:click="action = null"
                                            class="rounded-lg border border-[#E7E9E5] px-4 py-2 text-sm font-semibold text-[#19355F] hover:bg-[#EDF5EC]">
                                        Back
                                    </button>
                                </div>
                            </form>

                            {{-- COMPLETE --}}
                            <form x-show="action === 'complete'" x-cloak
                                  method="POST" :action="selected?.completeUrl"
                                  x-on:submit="submitting = true" class="mt-1 space-y-3">
                                @csrf
                                <input type="hidden" name="_method" value="PATCH">

                                <div class="rounded-xl border border-[#CBE5CF] bg-[#EDF5EC] p-3 text-sm">
                                    <p class="font-semibold text-[#367E4B]" x-text="selected?.patientFullName"></p>
                                    <p class="text-[#367E4B]">
                                        <span x-text="selected?.typeLabel"></span>
                                        · Confirmed: <span x-text="selected?.confirmedDateLabel"></span>
                                        <span x-text="' · ' + (selected?.confirmedTimeLabel ?? '')"></span>
                                    </p>
                                    <p class="mt-1 text-xs text-[#367E4B]">Completing records that the administrative appointment workflow finished. It does not create a visit, delivery, or admission record.</p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" :disabled="submitting"
                                            class="rounded-lg bg-[#55B85A] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#4aa04c] disabled:opacity-60"
                                            x-text="submitting ? 'Saving…' : 'Mark as Completed'"></button>
                                    <button type="button" x-on:click="action = null"
                                            class="rounded-lg border border-[#E7E9E5] px-4 py-2 text-sm font-semibold text-[#19355F] hover:bg-[#EDF5EC]">
                                        Back
                                    </button>
                                </div>
                            </form>

                            {{-- APPROVE CANCELLATION REQUEST (Phase 3D.2C) --}}
                            <form x-show="action === 'approveRequest'" x-cloak
                                  method="POST" :action="selected?.cancellationRequest?.approveUrl"
                                  x-on:submit="submitting = true" class="mt-1 space-y-3">
                                @csrf
                                <input type="hidden" name="_method" value="PATCH">

                                @error('change_request')
                                    <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-medium text-red-800">{{ $message }}</div>
                                @enderror

                                <div class="rounded-xl border border-orange-200 bg-orange-50 p-3 text-sm">
                                    <p class="font-semibold text-orange-900" x-text="selected?.patientFullName"></p>
                                    <p class="text-orange-800">
                                        <span x-text="selected?.typeLabel"></span>
                                        · Confirmed: <span x-text="selected?.confirmedDateLabel"></span>
                                        <span x-text="' · ' + (selected?.confirmedTimeLabel ?? '')"></span>
                                    </p>
                                    <p class="mt-1 text-xs text-orange-800">Patient's cancellation reason: <span x-text="selected?.cancellationRequest?.reason"></span></p>
                                    <p class="mt-1 text-xs text-orange-700">Approving cancels the appointment with the patient's reason and notifies the patient once. The original reviewer stays on record.</p>
                                </div>

                                <div>
                                    <label for="approve-request-note" class="block text-xs font-semibold uppercase tracking-wider text-[#657083] mb-1">Staff Note (optional)</label>
                                <textarea id="approve-request-note" name="review_note" rows="2" minlength="3" maxlength="1000"
                                          placeholder="Optional internal note for this approval…"
                                          class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[#55B85A] focus:ring-[#55B85A]">{{ old('review_note') }}</textarea>
                                    @error('review_note')
                                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" :disabled="submitting"
                                            class="rounded-lg bg-[#9A3412] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#7C2D12] disabled:opacity-60"
                                            x-text="submitting ? 'Approving…' : 'Approve & Cancel Appointment'"></button>
                                    <button type="button" x-on:click="action = null"
                                            class="rounded-lg border border-[#E7E9E5] px-4 py-2 text-sm font-semibold text-[#19355F] hover:bg-[#EDF5EC]">
                                        Back
                                    </button>
                                </div>
                            </form>

                            {{-- DECLINE CANCELLATION REQUEST (Phase 3D.2C) --}}
                            <form x-show="action === 'declineRequest'" x-cloak
                                  method="POST" :action="selected?.cancellationRequest?.declineUrl"
                                  x-on:submit="submitting = true" class="mt-1 space-y-3">
                                @csrf
                                <input type="hidden" name="_method" value="PATCH">

                                @error('change_request')
                                    <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-medium text-red-800">{{ $message }}</div>
                                @enderror

                                <div class="rounded-xl border border-[#E7E9E5] bg-[#FCFBF8] p-3 text-sm">
                                    <p class="font-semibold text-[#19355F]" x-text="selected?.patientFullName"></p>
                                    <p class="text-[#657083]">
                                        <span x-text="selected?.typeLabel"></span>
                                        · Confirmed: <span x-text="selected?.confirmedDateLabel"></span>
                                        <span x-text="' · ' + (selected?.confirmedTimeLabel ?? '')"></span>
                                    </p>
                                    <p class="mt-1 text-xs text-[#657083]">Patient's cancellation reason: <span x-text="selected?.cancellationRequest?.reason"></span></p>
                                    <p class="mt-1 text-xs text-[#657083]">Declining keeps the appointment confirmed with its schedule intact. The patient sees your explanation on their My Appointments card.</p>
                                </div>

                                <div>
                                    <label for="decline-request-note" class="block text-xs font-semibold uppercase tracking-wider text-[#657083] mb-1">Explanation (required)</label>
                                    <textarea id="decline-request-note" name="review_note" rows="3" required minlength="3" maxlength="1000"
                                              placeholder="Explain why this cancellation request is being declined…"
                                              class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[#55B85A] focus:ring-[#55B85A]">{{ old('review_note') }}</textarea>
                                    @error('review_note')
                                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" :disabled="submitting"
                                            class="rounded-lg bg-[#9A3412] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#7C2D12] disabled:opacity-60"
                                            x-text="submitting ? 'Declining…' : 'Decline Request'"></button>
                                    <button type="button" x-on:click="action = null"
                                            class="rounded-lg border border-[#E7E9E5] px-4 py-2 text-sm font-semibold text-[#19355F] hover:bg-[#EDF5EC]">
                                        Back
                                    </button>
                                </div>
                            </form>

                            {{-- APPROVE RESCHEDULE REQUEST (Phase 3D.3B Step 5) --}}
                            <form x-show="action === 'approveReschedule'" x-cloak
                                  method="POST" :action="selected?.rescheduleRequest?.approveUrl"
                                  x-on:submit="submitting = true" class="mt-1 space-y-3">
                                @csrf
                                <input type="hidden" name="_method" value="PATCH">

                                @error('change_request')
                                    <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-medium text-red-800">{{ $message }}</div>
                                @enderror

                                <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-3 text-sm">
                                    <p class="font-semibold text-indigo-900" x-text="selected?.patientFullName"></p>
                                    <p class="text-indigo-800">
                                        <span x-text="selected?.typeLabel"></span>
                                        · Request type: Reschedule
                                    </p>
                                    <p class="mt-1 text-xs text-indigo-700">
                                        Current:
                                        <span class="font-semibold" x-text="(() => {
                                            const d = selected?.rescheduleRequest?.currentDateLabel;
                                            const t = selected?.rescheduleRequest?.currentTimeLabel;
                                            if (!d) return 'Not provided';
                                            return [d, t].filter(Boolean).join(' · ');
                                        })()"></span>
                                        → Proposed:
                                        <span class="font-semibold" x-text="(() => {
                                            const d = selected?.rescheduleRequest?.proposedDateLabel;
                                            const t = selected?.rescheduleRequest?.proposedTimeLabel;
                                            if (!d) return 'Not provided';
                                            return [d, t].filter(Boolean).join(' · ');
                                        })()"></span>
                                    </p>
                                    <p x-show="selected?.rescheduleRequest?.reason" class="mt-1 text-xs text-indigo-700">
                                        Patient's reason: <span x-text="selected?.rescheduleRequest?.reason"></span>
                                    </p>
                                    <p class="mt-1 text-xs text-indigo-700">Approving moves this appointment to the proposed schedule above. The status stays Confirmed, the original reviewer stays on record, and the patient is notified once.</p>
                                </div>

                                <div>
                                    <label for="approve-reschedule-note" class="block text-xs font-semibold uppercase tracking-wider text-[#657083] mb-1">Staff Note (optional)</label>
                                    <textarea id="approve-reschedule-note" name="review_note" rows="2" minlength="3" maxlength="1000"
                                              placeholder="Optional internal note for this approval…"
                                              class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[#55B85A] focus:ring-[#55B85A]">{{ old('review_note') }}</textarea>
                                    @error('review_note')
                                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" :disabled="submitting"
                                            class="rounded-lg bg-[#4F46E5] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#4338CA] disabled:opacity-60"
                                            x-text="submitting ? 'Approving…' : 'Approve & Move to Proposed Schedule'"></button>
                                    <button type="button" x-on:click="action = null"
                                            class="rounded-lg border border-[#E7E9E5] px-4 py-2 text-sm font-semibold text-[#19355F] hover:bg-[#EDF5EC]">
                                        Back
                                    </button>
                                </div>
                            </form>

                            {{-- DECLINE RESCHEDULE REQUEST (Phase 3D.3B Step 5) --}}
                            <form x-show="action === 'declineReschedule'" x-cloak
                                  method="POST" :action="selected?.rescheduleRequest?.declineUrl"
                                  x-on:submit="submitting = true" class="mt-1 space-y-3">
                                @csrf
                                <input type="hidden" name="_method" value="PATCH">

                                @error('change_request')
                                    <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-medium text-red-800">{{ $message }}</div>
                                @enderror

                                <div class="rounded-xl border border-[#E7E9E5] bg-[#FCFBF8] p-3 text-sm">
                                    <p class="font-semibold text-[#19355F]" x-text="selected?.patientFullName"></p>
                                    <p class="text-[#657083]">
                                        <span x-text="selected?.typeLabel"></span>
                                        · Request type: Reschedule
                                    </p>
                                    <p class="mt-1 text-xs text-[#657083]">
                                        Proposed schedule:
                                        <span class="font-semibold" x-text="(() => {
                                            const d = selected?.rescheduleRequest?.proposedDateLabel;
                                            const t = selected?.rescheduleRequest?.proposedTimeLabel;
                                            if (!d) return 'Not provided';
                                            return [d, t].filter(Boolean).join(' · ');
                                        })()"></span>
                                    </p>
                                    <p x-show="selected?.rescheduleRequest?.reason" class="mt-1 text-xs text-[#657083]">
                                        Patient's reason: <span x-text="selected?.rescheduleRequest?.reason"></span>
                                    </p>
                                    <p class="mt-1 text-xs text-[#657083]">Declining keeps the original confirmed appointment schedule unchanged. The patient sees your explanation on their My Appointments card.</p>
                                </div>

                                <div>
                                    <label for="decline-reschedule-note" class="block text-xs font-semibold uppercase tracking-wider text-[#657083] mb-1">Explanation (required)</label>
                                    <textarea id="decline-reschedule-note" name="review_note" rows="3" required minlength="3" maxlength="1000"
                                              placeholder="Explain why this reschedule request is being declined…"
                                              class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[#55B85A] focus:ring-[#55B85A]">{{ old('review_note') }}</textarea>
                                    @error('review_note')
                                        <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <button type="submit" :disabled="submitting"
                                            class="rounded-lg bg-[#4F46E5] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#4338CA] disabled:opacity-60"
                                            x-text="submitting ? 'Declining…' : 'Decline Reschedule Request'"></button>
                                    <button type="button" x-on:click="action = null"
                                            class="rounded-lg border border-[#E7E9E5] px-4 py-2 text-sm font-semibold text-[#19355F] hover:bg-[#EDF5EC]">
                                        Back
                                    </button>
                                </div>
                            </form>
                        </div>

                        <p class="mt-5 text-xs text-[#657083]">
                            Every action is validated server-side and recorded in the audit log. Refreshing the calendar always shows the latest status.
                        </p>
                    </div>
                </x-modal>

                {{-- ══════════ WALK-IN APPOINTMENT MODAL ══════════ --}}
                <x-modal name="walk-in-appointment" focusable max-width="lg">
                    <form method="POST" action="{{ route('appointments.walk-in.store') }}"
                          x-on:submit="if (guardWalkInSchedule()) { submitting = true; } else { $event.preventDefault(); }"
                          class="p-6">
                        @csrf

                        <div class="mb-4 flex items-start justify-between gap-4">
                            <h3 class="text-lg font-bold text-slate-900">Add Walk-in Appointment</h3>
                            <span class="rounded-full bg-[#EDF5EC] px-3 py-1 text-xs font-bold text-[#367E4B]">Walk-in</span>
                        </div>

                        @error('walk_in')
                            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm font-medium text-red-800">{{ $message }}</div>
                        @enderror

                        {{-- Patient selection (existing patients only) --}}
                        <div class="rounded-xl border border-[#E7E9E5] bg-[#FCFBF8] p-4">
                            <label class="block text-xs font-semibold uppercase tracking-wider text-[#657083] mb-1">Patient (required)</label>

                            <div x-show="!walkIn.patientId">
                                <input type="text"
                                       x-model="walkIn.query"
                                       x-on:input.debounce.300ms="searchPatients()"
                                       placeholder="Search by first name, last name, or patient ID…"
                                       autocomplete="off"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[#55B85A] focus:ring-[#55B85A]">
                                <p x-show="walkIn.searching" class="mt-1 text-xs text-[#657083]">Searching…</p>

                                <ul x-show="walkIn.results.length > 0" class="mt-2 max-h-48 overflow-y-auto rounded-lg border border-[#E7E9E5] bg-white divide-y divide-[#E7E9E5]">
                                    <template x-for="patient in walkIn.results" :key="patient.id">
                                        <li>
                                            <button type="button" x-on:click="selectPatient(patient)"
                                                    class="w-full px-3 py-2 text-left text-sm hover:bg-[#EDF5EC] focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#55B85A]">
                                                <span class="font-semibold text-[#19355F]" x-text="patient.full_name"></span>
                                                <span class="ml-2 text-xs text-[#657083]">
                                                    <span x-text="patient.age ? patient.age + ' yrs' : ''"></span>
                                                    <span x-text="patient.contact_number ? ' · ' + patient.contact_number : ''"></span>
                                                </span>
                                            </button>
                                        </li>
                                    </template>
                                </ul>
                                <p x-show="!walkIn.searching && walkIn.query.length > 0 && walkIn.results.length === 0"
                                   class="mt-2 text-xs text-[#657083]">No matching patients found.</p>
                            </div>

                            <div x-show="walkIn.patientId" class="flex items-start justify-between gap-3">
                                <input type="hidden" name="patient_id" :value="walkIn.patientId">
                                <p class="text-sm font-semibold text-[#19355F]" x-text="walkIn.patientLabel"></p>
                                <button type="button" x-on:click="clearPatient()"
                                        class="text-xs font-semibold text-[#367E4B] hover:underline">Change</button>
                            </div>

                            @error('patient_id')
                                <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label for="walkin-type" class="block text-xs font-semibold uppercase tracking-wider text-[#657083] mb-1">Appointment Type</label>
                                <select id="walkin-type" name="appointment_type" x-model="walkIn.type" required
                                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[#55B85A] focus:ring-[#55B85A]">
                                    @foreach ($typeLabels as $typeValue => $typeLabel)
                                        <option value="{{ $typeValue }}">{{ $typeLabel }}</option>
                                    @endforeach
                                </select>
                                @error('appointment_type')
                                    <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="walkin-date" class="block text-xs font-semibold uppercase tracking-wider text-[#657083] mb-1">Appointment Date</label>
                                <input id="walkin-date" type="date" name="appointment_date" required
                                       min="{{ $clinicToday }}"
                                       x-model="walkIn.date"
                                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[#55B85A] focus:ring-[#55B85A]">
                                @error('appointment_date')
                                    <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-3">
                            <label for="walkin-time" class="block text-xs font-semibold uppercase tracking-wider text-[#657083] mb-1">
                                Appointment Time <span x-show="walkIn.type !== 'delivery_request'" class="text-red-600">(required)</span>
                            </label>
                            <input id="walkin-time" type="time" name="appointment_time" step="60"
                                   x-model="walkIn.time"
                                   x-bind:required="walkIn.type !== 'delivery_request'"
                                   class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-[#55B85A] focus:ring-[#55B85A]">
                            <p class="mt-1 text-xs text-[#657083]">
                                Checkup &amp; counseling: Monday–Sunday, 8:00 AM to before 5:00 PM Philippine time (provisional). Delivery requests: any time, 24/7, date-only allowed — confirming never reserves a bed. Today ({{ $clinicToday }}) or a future date only — today's earlier times are blocked (Philippine time).
                            </p>
                            @error('appointment_time')
                                <p class="mt-1 text-xs font-medium text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <p x-show="scheduleError" x-text="scheduleError"
                           class="mt-3 rounded-lg border border-red-200 bg-red-50 p-2 text-xs font-medium text-red-700"></p>

                        <p class="mt-3 text-xs text-[#657083]">
                            Walk-in appointments are recorded as <strong>Confirmed</strong> immediately (source: Walk-in) with your staff account as the reviewer. Existing patients only — this never creates a new patient record or patient account.
                        </p>

                        <div class="mt-5 flex flex-wrap gap-2">
                            <button type="submit" :disabled="submitting || !walkIn.patientId"
                                    class="rounded-lg bg-[#55B85A] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#4aa04c] disabled:opacity-60"
                                    x-text="submitting ? 'Creating…' : 'Create Walk-in Appointment'"></button>
                            <button type="button" x-on:click="$dispatch('close-modal', 'walk-in-appointment')"
                                    class="rounded-lg border border-[#E7E9E5] px-4 py-2 text-sm font-semibold text-[#19355F] hover:bg-[#EDF5EC]">
                                Cancel
                            </button>
                        </div>
                    </form>
                </x-modal>

                {{-- Inline script (not @push - the layout has no scripts
                     stack). Classic script executes during HTML parse, so
                     the factory is a global function before Alpine.start()
                     runs from the deferred Vite module. --}}
                <script>
                    // Manila anchors rendered at page load for the frontend's
                    // past-schedule hints. Server validation (FormRequest +
                    // AppointmentManagementService at the write boundary)
                    // remains authoritative - this only prevents a pointless
                    // round-trip and shows an immediate readable message.
                    // NOTE: inside <script>, Blade's escaped echo would
                    // HTML-escape the JSON quotes to &quot; (which the
                    // browser does NOT decode in script text) and break the
                    // whole component - JSON is emitted unescaped with HEX
                    // flags, matching the details payload above.
                    const CLINIC_TODAY = {!! json_encode($clinicToday, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};
                    const CLINIC_NOW = {!! json_encode($clinicNow, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};

                    function appointmentDetailsPanel() {
                        return {
                            selected: null,
                            action: null,
                            submitting: false,
                            scheduleError: '',
                            walkIn: {
                                patientId: null,
                                patientLabel: '',
                                query: '',
                                results: [],
                                searching: false,
                                type: {!! json_encode(old('appointment_type', 'checkup'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
                                date: {!! json_encode(old('appointment_date', ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
                                time: {!! json_encode(old('appointment_time', ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!},
                            },
                            open(id) {
                                const all = JSON.parse(document.getElementById('appointmentDetailsData').textContent || '{}');
                                this.selected = all[String(id)] || null;
                                this.action = null;
                                this.scheduleError = '';
                                if (this.selected) {
                                    this.$dispatch('open-modal', 'appointment-details');
                                }
                            },
                            // Shared past-schedule guard (mirrors the server
                            // rule). Returns false and sets a readable
                            // scheduleError when the picked schedule has
                            // already passed; never mutates the input.
                            checkSchedule(date, time) {
                                this.scheduleError = '';
                                if (!date) {
                                    return true; // required= owns empty dates.
                                }
                                if (date < CLINIC_TODAY) {
                                    this.scheduleError = 'That date has already passed. Choose today (' + CLINIC_TODAY + ') or a later date (Philippine time).';
                                    return false;
                                }
                                if (time && date === CLINIC_TODAY && time < CLINIC_NOW) {
                                    this.scheduleError = 'That time has already passed today. Choose ' + CLINIC_NOW + ' or later (Philippine time).';
                                    return false;
                                }
                                return true;
                            },
                            guardWalkInSchedule() {
                                return this.checkSchedule(this.walkIn.date, this.walkIn.time);
                            },
                            guardConfirmSchedule() {
                                const dateEl = document.getElementById('confirm-date');
                                const timeEl = document.getElementById(this.selected?.type === 'delivery_request' ? 'confirm-time-delivery' : 'confirm-time');
                                return this.checkSchedule(dateEl ? dateEl.value : '', timeEl ? timeEl.value : '');
                            },
                            statusBadgeClass(status) {
                                return {
                                    pending: 'bg-amber-100 text-amber-800',
                                    confirmed: 'bg-[#EDF5EC] text-[#367E4B]',
                                    completed: 'bg-slate-100 text-slate-600',
                                    cancelled: 'bg-gray-100 text-gray-500',
                                    declined: 'bg-gray-100 text-gray-500',
                                }[status] || 'bg-slate-100 text-slate-600';
                            },
                            async searchPatients() {
                                const query = this.walkIn.query.trim();
                                if (query.length < 1) {
                                    this.walkIn.results = [];
                                    return;
                                }
                                this.walkIn.searching = true;
                                try {
                                    const response = await fetch({!! json_encode(route('appointments.patient-search'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!} + '?q=' + encodeURIComponent(query), {
                                        headers: { 'Accept': 'application/json' },
                                    });
                                    const data = await response.json();
                                    this.walkIn.results = data.patients || [];
                                } catch (e) {
                                    this.walkIn.results = [];
                                }
                                this.walkIn.searching = false;
                            },
                            selectPatient(patient) {
                                this.walkIn.patientId = patient.id;
                                this.walkIn.patientLabel = patient.full_name
                                    + (patient.age ? ' · ' + patient.age + ' yrs' : '')
                                    + (patient.contact_number ? ' · ' + patient.contact_number : '');
                                this.walkIn.results = [];
                                this.walkIn.query = '';
                            },
                            clearPatient() {
                                this.walkIn.patientId = null;
                                this.walkIn.patientLabel = '';
                            },
                        };
                    }
                </script>
            </div>
        </div>
    </div>
</x-app-layout>
