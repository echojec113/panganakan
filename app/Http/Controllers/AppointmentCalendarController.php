<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentChangeRequest;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * DEPLA Phase 3B.2A - READ-ONLY Appointments Calendar (Admin + Staff).
 *
 * Strictly display-only: this controller never writes, never mutates and
 * never accepts appointment state changes. It only validates calendar
 * navigation/filter query parameters and prepares presentation data for
 * the Month / Year / Day views plus the read-only details panel.
 *
 * Timezone policy: the clinic's calendar is anchored to Asia/Manila
 * ("today", month/year navigation) WITHOUT changing the application's
 * global timezone configuration. Stored date columns are plain calendar
 * dates (Y-m-d) and are compared as strings - never converted through a
 * timezone. Clock times are stored as clinic-local wall times and are
 * displayed as recorded (truncated to H:i), never shifted.
 *
 * Display-date rule (each appointment appears on exactly ONE date):
 *   - pending / declined        -> requested_date (+ requested_time)
 *   - confirmed / completed     -> confirmed_date ?? requested_date
 *     (+ confirmed_time; a confirmed appointment with no confirmed_time
 *      renders as date-only - the schedule is never invented)
 *   - cancelled                 -> confirmed_date ?? requested_date
 *     (+ confirmed_time ?? requested_time)
 * Cancelled/Declined rows are hidden by the default "active" status
 * filter and stay reachable through the explicit status filter.
 */
class AppointmentCalendarController extends Controller
{
    /** Clinic timezone for today/navigation anchors. Not the app timezone. */
    private const CLINIC_TIMEZONE = 'Asia/Manila';

    /** Month cells show at most this many event chips before "+N more". */
    private const MAX_EVENTS_PER_DAY_CELL = 3;

    /** Optional narrow filter for the active (non-history) statuses. */
    private const ACTIVE_STATUSES = [
        Appointment::STATUS_PENDING,
        Appointment::STATUS_CONFIRMED,
        Appointment::STATUS_COMPLETED,
    ];

    /** Human labels shown on chips, day rows and the details panel. */
    private const TYPE_LABELS = [
        Appointment::TYPE_CHECKUP => 'Checkup',
        Appointment::TYPE_COUNSELING => 'Counseling',
        Appointment::TYPE_DELIVERY_REQUEST => 'Delivery Request',
    ];

    private const SOURCE_LABELS = [
        Appointment::SOURCE_ONLINE => 'Online',
        Appointment::SOURCE_WALK_IN => 'Walk-in',
    ];

    private const STATUS_LABELS = [
        Appointment::STATUS_PENDING => 'Pending',
        Appointment::STATUS_CONFIRMED => 'Confirmed',
        Appointment::STATUS_COMPLETED => 'Completed',
        Appointment::STATUS_CANCELLED => 'Cancelled',
        Appointment::STATUS_DECLINED => 'Declined',
    ];

    public function index(Request $request): View
    {
        $view = $this->resolveView($request);
        $typeFilter = $this->resolveTypeFilter($request);
        $statusFilter = $this->resolveStatusFilter($request);
        $date = $this->resolveDate($request);

        $shared = [
            'view' => $view,
            'typeFilter' => $typeFilter,
            'statusFilter' => $statusFilter,
            'date' => $date,
            'typeLabels' => self::TYPE_LABELS,
            'statusLabels' => self::STATUS_LABELS,
            'maxEventsPerDayCell' => self::MAX_EVENTS_PER_DAY_CELL,
            // Manila anchors for the frontend's past-schedule hints (min
            // attributes / Alpine guards). Server validation stays the
            // authoritative check at the write boundary.
            'clinicToday' => $this->clinicToday()->toDateString(),
            'clinicNow' => Carbon::now(self::CLINIC_TIMEZONE)->format('H:i'),
        ];

        $payload = match ($view) {
            'year' => $this->yearPayload($date, $typeFilter, $statusFilter),
            'day' => $this->dayPayload($date, $typeFilter, $statusFilter),
            default => $this->monthPayload($date, $typeFilter, $statusFilter),
        };

        return view('appointments.calendar', array_merge($shared, $payload));
    }

    /*
    |--------------------------------------------------------------------------
    | Query-parameter resolution (invalid input falls back to safe defaults)
    |--------------------------------------------------------------------------
    */

    private function resolveView(Request $request): string
    {
        $view = (string) $request->query('view', 'month');

        return in_array($view, ['month', 'year', 'day'], true) ? $view : 'month';
    }

    private function resolveTypeFilter(Request $request): string
    {
        $type = (string) $request->query('type', 'all');

        return $type === 'all' || in_array($type, Appointment::TYPES, true) ? $type : 'all';
    }

    private function resolveStatusFilter(Request $request): string
    {
        // Default to All Statuses when the parameter is absent; an invalid
        // value also falls back to All rather than an arbitrary subset.
        $status = (string) $request->query('status', 'all');

        return in_array($status, array_merge(['active', 'all'], Appointment::STATUSES), true)
            ? $status
            : 'all';
    }

    /**
     * The visible calendar date as a plain calendar anchor (never a
     * timezone-converted instant). Invalid/missing input falls back to
     * today in the clinic's timezone.
     */
    private function resolveDate(Request $request): Carbon
    {
        $raw = (string) $request->query('date', '');

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $parts) === 1
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return Carbon::create((int) $parts[1], (int) $parts[2], (int) $parts[3]);
        }

        return $this->clinicToday();
    }

    private function clinicToday(): Carbon
    {
        return Carbon::now(self::CLINIC_TIMEZONE)->startOfDay();
    }

    /*
    |--------------------------------------------------------------------------
    | View payloads
    |--------------------------------------------------------------------------
    */

    private function monthPayload(Carbon $date, string $typeFilter, string $statusFilter): array
    {
        $gridStart = $date->copy()->startOfMonth()->startOfWeek()->toDateString();
        $gridEnd = $date->copy()->endOfMonth()->endOfWeek()->toDateString();

        $eventsByDate = $this->eventsGroupedByEffectiveDate($gridStart, $gridEnd, $typeFilter, $statusFilter);

        $today = $this->clinicToday()->toDateString();
        $weeks = [];
        $cursor = Carbon::createFromFormat('Y-m-d', $gridStart)->startOfDay();

        while ($cursor->toDateString() <= $gridEnd) {
            $week = [];

            for ($i = 0; $i < 7; $i++) {
                $dayString = $cursor->toDateString();
                $events = $eventsByDate[$dayString] ?? [];

                $week[] = [
                    'date' => $dayString,
                    'dayNumber' => (int) $cursor->format('j'),
                    'isCurrentMonth' => $cursor->format('Y-m') === $date->format('Y-m'),
                    'isToday' => $dayString === $today,
                    'events' => array_slice($events, 0, self::MAX_EVENTS_PER_DAY_CELL),
                    'hiddenEventCount' => max(0, count($events) - self::MAX_EVENTS_PER_DAY_CELL),
                    'totalEventCount' => count($events),
                ];

                $cursor->addDay();
            }

            $weeks[] = $week;
        }

        $visibleEventCount = array_sum(array_map('count', $eventsByDate));

        return [
            'monthLabel' => $date->format('F Y'),
            'weeks' => $weeks,
            'details' => $this->detailsMap($eventsByDate),
            'visibleEventCount' => $visibleEventCount,
            'hasAnyEvent' => $visibleEventCount > 0,
        ];
    }

    private function yearPayload(Carbon $date, string $typeFilter, string $statusFilter): array
    {
        $year = (int) $date->format('Y');
        $yearStart = sprintf('%04d-01-01', $year);
        $yearEnd = sprintf('%04d-12-31', $year);

        // Lightweight projection: no relations, four columns, one query.
        // Aggregation happens in PHP so the SQL stays portable across
        // SQLite/MySQL - a single clinic's yearly volume is trivial.
        $rows = $this->baseQuery($typeFilter, $statusFilter)
            ->select(['id', 'status', 'requested_date', 'confirmed_date'])
            ->where(function ($query) use ($yearStart, $yearEnd) {
                $query->whereDate('requested_date', '>=', $yearStart)
                    ->whereDate('requested_date', '<=', $yearEnd)
                    ->orWhere(function ($or) use ($yearStart, $yearEnd) {
                        $or->whereDate('confirmed_date', '>=', $yearStart)
                            ->whereDate('confirmed_date', '<=', $yearEnd);
                    });
            })
            ->get();

        // Phase 3D.3B Step 5: ONE batched read (same pattern as month/day,
        // never a per-row query) so each month card can carry a pending
        // change-request marker for either request type.
        $pendingChangeIds = $rows->isEmpty()
            ? collect()
            : AppointmentChangeRequest::query()
                ->whereIn('appointment_id', $rows->pluck('id'))
                ->where('status', AppointmentChangeRequest::STATUS_PENDING)
                ->pluck('appointment_id')
                ->flip();

        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $months[] = [
                'name' => Carbon::create($year, $month, 1)->format('F'),
                'month' => $month,
                'count' => 0,
                'pendingCount' => 0,
                'pendingChangeRequestCount' => 0,
            ];
        }

        $yearTotal = 0;

        foreach ($rows as $row) {
            $effective = $this->effectiveDate($row);

            if ($effective === null || ! str_starts_with($effective, (string) $year)) {
                continue;
            }

            $monthIndex = (int) substr($effective, 5, 2) - 1;
            $months[$monthIndex]['count']++;
            $yearTotal++;

            if ($row->status === Appointment::STATUS_PENDING) {
                $months[$monthIndex]['pendingCount']++;
            }

            if ($pendingChangeIds->has($row->id)) {
                $months[$monthIndex]['pendingChangeRequestCount']++;
            }
        }

        return [
            'yearLabel' => (string) $year,
            'months' => $months,
            'visibleEventCount' => $yearTotal,
            'hasAnyEvent' => $yearTotal > 0,
        ];
    }

    private function dayPayload(Carbon $date, string $typeFilter, string $statusFilter): array
    {
        $dayString = $date->toDateString();

        $eventsByDate = $this->eventsGroupedByEffectiveDate($dayString, $dayString, $typeFilter, $statusFilter);
        $events = $eventsByDate[$dayString] ?? [];

        // Full details for the read-only panel (also embedded for month view chips).
        $details = $this->detailsMap($eventsByDate);

        return [
            'dayLabel' => $date->format('l, F j, Y'),
            'events' => $events,
            'details' => $details,
            'visibleEventCount' => count($events),
            'hasAnyEvent' => count($events) > 0,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Shared query + display-date mapping
    |--------------------------------------------------------------------------
    */

    private function baseQuery(string $typeFilter, string $statusFilter)
    {
        return Appointment::query()
            ->with([
                'patient:id,first_name,middle_name,last_name,contact_number',
                'reviewer:id,name',
            ])
            ->when($typeFilter !== 'all', fn ($query) => $query->where('appointment_type', $typeFilter))
            ->when(
                $statusFilter === 'active',
                fn ($query) => $query->whereIn('status', self::ACTIVE_STATUSES)
            )
            ->when(
                $statusFilter !== 'active' && $statusFilter !== 'all',
                fn ($query) => $query->where('status', $statusFilter)
            );
    }

    /**
     * Load only the rows whose REQUESTED or CONFIRMED date falls inside the
     * range, then keep each appointment exactly once - on its effective
     * display date - so a moved confirmation never duplicates or vanishes.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function eventsGroupedByEffectiveDate(string $start, string $end, string $typeFilter, string $statusFilter): array
    {
        $rows = $this->baseQuery($typeFilter, $statusFilter)
            ->where(function ($query) use ($start, $end) {
                // whereDate() (SQL date()) is required over whereBetween:
                // Eloquent's date cast persists 'Y-m-d 00:00:00' strings,
                // which a plain string BETWEEN with equal day bounds (the
                // day view) would miss on SQLite text columns. Portable
                // across SQLite and MySQL; clinic-scale volumes make the
                // unindexed date() comparison acceptable.
                $query->where(function ($or) use ($start, $end) {
                    $or->whereDate('requested_date', '>=', $start)
                        ->whereDate('requested_date', '<=', $end);
                })->orWhere(function ($or) use ($start, $end) {
                    $or->whereDate('confirmed_date', '>=', $start)
                        ->whereDate('confirmed_date', '<=', $end);
                });
            })
            ->get();

        // Phase 3D.2C/3D.3B Step 5: one extra batched read so month/day
        // chips can flag appointments holding a PENDING patient change
        // request (amber "!" marker) without N+1 queries. The type comes
        // back too (cancellation vs reschedule) so chips, day badges and
        // the details modal can label the review correctly. The details
        // modal then carries the full request block for the review forms.
        $pendingRequestTypes = $rows->isEmpty()
            ? collect()
            : AppointmentChangeRequest::query()
                ->whereIn('appointment_id', $rows->pluck('id'))
                ->where('status', AppointmentChangeRequest::STATUS_PENDING)
                ->get(['appointment_id', 'type'])
                ->keyBy('appointment_id')
                ->map(fn (AppointmentChangeRequest $request) => $request->type);

        $byDate = [];

        foreach ($rows as $appointment) {
            $effective = $this->effectiveDate($appointment);

            if ($effective === null || $effective < $start || $effective > $end) {
                continue;
            }

            $byDate[$effective][] = $this->eventPayload(
                $appointment,
                $pendingRequestTypes->get($appointment->id)
            );
        }

        foreach ($byDate as $date => $events) {
            usort($byDate[$date], function (array $a, array $b): int {
                // Date-only (all-day style) events first, then by clock time.
                if ($a['time'] === null && $b['time'] !== null) {
                    return -1;
                }
                if ($a['time'] !== null && $b['time'] === null) {
                    return 1;
                }
                if ($a['time'] !== null && $b['time'] !== null && $a['time'] !== $b['time']) {
                    return strcmp($a['time'], $b['time']);
                }

                return $a['id'] <=> $b['id'];
            });
        }

        ksort($byDate);

        return $byDate;
    }

    /** Single display date per appointment (see class docblock). */
    private function effectiveDate(Appointment $appointment): ?string
    {
        if (in_array($appointment->status, [Appointment::STATUS_PENDING, Appointment::STATUS_DECLINED], true)) {
            return $appointment->requested_date->toDateString();
        }

        $date = $appointment->confirmed_date ?? $appointment->requested_date;

        return $date?->toDateString();
    }

    /** Single display time per appointment; null = date-only, never invented. */
    private function effectiveTime(Appointment $appointment): ?string
    {
        $raw = match ($appointment->status) {
            Appointment::STATUS_PENDING, Appointment::STATUS_DECLINED => $appointment->requested_time,
            Appointment::STATUS_CANCELLED => $appointment->confirmed_time ?? $appointment->requested_time,
            default => $appointment->confirmed_time,
        };

        return $raw !== null ? substr((string) $raw, 0, 5) : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Presentation payloads (chips + full read-only details)
    |--------------------------------------------------------------------------
    */

    private function eventPayload(Appointment $appointment, ?string $pendingChangeRequestType = null): array
    {
        $patient = $appointment->patient;

        return [
            'id' => $appointment->id,
            'status' => $appointment->status,
            'statusLabel' => self::STATUS_LABELS[$appointment->status] ?? $appointment->status,
            'type' => $appointment->appointment_type,
            'typeLabel' => self::TYPE_LABELS[$appointment->appointment_type] ?? $appointment->appointment_type,
            'time' => $this->effectiveTime($appointment),
            'patientLastName' => $patient?->last_name ?? 'Unavailable',
            'patientName' => $patient ? $this->patientFullName($patient) : 'Patient record unavailable',
            // Phase 3D.3B Step 5 chip flags: ANY pending patient change
            // request marks the chip, with the type for type-aware titles
            // and day-view badges. hasPendingCancellationRequest keeps its
            // exact Phase 3D.2C semantics (cancellation only) so existing
            // consumers and tests stay byte-compatible.
            'hasPendingChangeRequest' => $pendingChangeRequestType !== null,
            'pendingChangeRequestType' => $pendingChangeRequestType,
            'hasPendingCancellationRequest' => $pendingChangeRequestType === AppointmentChangeRequest::TYPE_CANCELLATION,
        ];
    }

    /**
     * Full read-only details for the panel. Every optional field degrades to
     * an explicit placeholder - never fabricated data.
     *
     * @return array<int, array<string, mixed>>
     */
    private function detailsMap(array $eventsByDate): array
    {
        $details = [];

        foreach ($eventsByDate as $events) {
            foreach ($events as $event) {
                if (! isset($details[$event['id']])) {
                    $details[$event['id']] = $event;
                }
            }
        }

        $ids = array_keys($details);

        if ($ids === []) {
            return [];
        }

        // Re-fetch with the same scoped query so details never bypass the
        // active type/status filters, then merge the full panel fields.
        $appointments = $this->baseQuery('all', 'all')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        // Phase 3D.2C/3D.3B Step 5: one batched read of PENDING change
        // requests (either type - at most ONE pending row per appointment
        // is guaranteed by the service) so the details panel can render
        // the right review block (patient reason/schedule + Approve/
        // Decline form URLs) without N+1 queries. Only pending requests
        // are exposed - finished reviews surface on the portal card, not
        // here, keeping the staff panel action-focused.
        $pendingRequests = AppointmentChangeRequest::query()
            ->whereIn('appointment_id', $ids)
            ->where('status', AppointmentChangeRequest::STATUS_PENDING)
            ->get()
            ->keyBy('appointment_id');

        foreach ($details as $id => $event) {
            $appointment = $appointments->get($id);

            if (! $appointment) {
                continue;
            }

            $patient = $appointment->patient;
            $pendingRequest = $pendingRequests->get($id);
            $isReschedule = $pendingRequest !== null
                && $pendingRequest->type === AppointmentChangeRequest::TYPE_RESCHEDULE;

            $details[$id] = array_merge($event, [
                'patientFullName' => $patient ? $this->patientFullName($patient) : 'Patient record unavailable',
                'patientContact' => $patient?->contact_number ?: 'Not provided',
                'profileUrl' => $patient ? route('patients.show', $patient->id) : null,
                'sourceLabel' => self::SOURCE_LABELS[$appointment->source] ?? $appointment->source,
                'requestedDate' => $appointment->requested_date?->format('M j, Y') ?? 'Not provided',
                'requestedDateIso' => $appointment->requested_date?->format('Y-m-d'),
                'requestedTime' => $appointment->requested_time
                    ? substr((string) $appointment->requested_time, 0, 5)
                    : null,
                'requestedTimeLabel' => $appointment->requested_time
                    ? substr((string) $appointment->requested_time, 0, 5)
                    : 'Not provided',
                'confirmedDateLabel' => $appointment->confirmed_date
                    ? $appointment->confirmed_date->format('M j, Y')
                    : null,
                'confirmedTimeLabel' => $appointment->confirmed_time
                    ? substr((string) $appointment->confirmed_time, 0, 5)
                    : ($appointment->confirmed_date ? 'Not provided' : null),
                'reviewerName' => $appointment->reviewer?->name ?: 'Not yet reviewed',
                'reviewedAtLabel' => $appointment->reviewed_at
                    ? $appointment->reviewed_at->copy()->timezone(self::CLINIC_TIMEZONE)->format('M j, Y')
                    : 'Not yet reviewed',
                'declineReason' => $appointment->decline_reason ?: null,
                'cancellationReason' => $appointment->cancellation_reason ?: null,
                // Phase 3B.2B mutation endpoints. The URL is server-built
                // from the appointment id - the client never constructs an
                // id it was not already shown, and the forms carry CSRF.
                'confirmUrl' => route('appointments.confirm', $appointment),
                'declineUrl' => route('appointments.decline', $appointment),
                'cancelUrl' => route('appointments.cancel', $appointment),
                'completeUrl' => route('appointments.complete', $appointment),
                // Phase 3D.2C - pending patient CANCELLATION request block
                // (null when none or when the pending request is a
                // reschedule). The panel renders the patient's reason and
                // the server-built Approve/Decline review URLs; the forms
                // carry CSRF and the service revalidates everything under
                // lock at write time.
                'cancellationRequest' => ($pendingRequest && ! $isReschedule) ? [
                    'id' => $pendingRequest->id,
                    'reason' => $pendingRequest->reason,
                    'requestedAtLabel' => $pendingRequest->created_at
                        ->copy()
                        ->timezone(self::CLINIC_TIMEZONE)
                        ->format('M j, Y g:i A'),
                    'approveUrl' => route('appointment-change-requests.approve', $pendingRequest),
                    'declineUrl' => route('appointment-change-requests.decline', $pendingRequest),
                ] : null,
                // Phase 3D.3B Step 5 - pending patient RESCHEDULE request
                // block (null when none). The current confirmed schedule
                // is reported separately from the proposed one; a time
                // label is null whenever none is stored (date-only
                // delivery per K5 - the panel never invents a time).
                'rescheduleRequest' => $isReschedule ? [
                    'id' => $pendingRequest->id,
                    'currentDateLabel' => $appointment->confirmed_date
                        ? $appointment->confirmed_date->format('M j, Y')
                        : null,
                    'currentTimeLabel' => $appointment->confirmed_time
                        ? substr((string) $appointment->confirmed_time, 0, 5)
                        : null,
                    'proposedDateLabel' => $pendingRequest->proposed_date
                        ? $pendingRequest->proposed_date->format('M j, Y')
                        : null,
                    'proposedTimeLabel' => $pendingRequest->proposed_time
                        ? substr((string) $pendingRequest->proposed_time, 0, 5)
                        : null,
                    'reason' => $pendingRequest->reason,
                    'requestedAtLabel' => $pendingRequest->created_at
                        ->copy()
                        ->timezone(self::CLINIC_TIMEZONE)
                        ->format('M j, Y g:i A'),
                    'approveUrl' => route('appointment-change-requests.approve', $pendingRequest),
                    'declineUrl' => route('appointment-change-requests.decline', $pendingRequest),
                ] : null,
            ]);
        }

        return $details;
    }

    private function patientFullName(object $patient): string
    {
        return trim(implode(' ', array_filter([
            $patient->first_name,
            $patient->middle_name,
            $patient->last_name,
        ])));
    }
}
