<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentSlotHold;
use App\Models\AuditLog;
use App\Models\ClinicUnavailability;
use App\Models\User;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * DEPLA Phase 1 (appointment scheduling upgrade) - single source of truth
 * for the 30-minute slot grid, clinic closures, slot occupancy and the
 * slot-hold / closure domain operations.
 *
 * The class is a REUSABLE DOMAIN SERVICE: it owns no routes, no views and
 * no authorization (role gates stay at the eventual controller/FormRequest
 * boundaries), and it deliberately does NOT wire itself into
 * AppointmentManagementService - booking-path enforcement is Phase 2,
 * after this foundation is reviewed.
 *
 * APPROVED SCHEDULING RULES (Phase 1A)
 * ------------------------------------
 * - Clinic hours 08:00-17:00 Asia/Manila; 30-minute slots for checkup and
 *   counseling; 18 slots/day: starts 08:00 .. 16:30 (last slot ends 17:00).
 * - At most one confirmed/completed appointment per slot. Pending online
 *   requests do NOT reserve slots (multiple patients may request the same
 *   pending slot). Confirmation or immediate walk-in confirmation
 *   reserves; cancelled/declined release; completed RETAINS occupancy.
 * - Delivery requests stay DATE-ONLY: they never receive slot holds and
 *   never occupy slots (even legacy delivery rows that carry a time).
 *
 * OCCUPANCY (legacy-compatible, read side)
 * ----------------------------------------
 * A slot [start, end) is occupied when EITHER:
 *   1. an appointment_slot_holds row exists for (date, slot_start) - the
 *      authoritative barrier for NEW grid-aligned reservations, OR
 *   2. a confirmed/completed (non-delivery) appointment has a recorded
 *      confirmed_time falling inside [start, end) - the LEGACY-WINDOW
 *      query. Existing appointments may have non-grid times (e.g. 14:37)
 *      and several may legally share a window today; they are never
 *      rewritten and never backfilled with holds. The service reads them
 *      as-is: 14:37 occupies 14:30-15:00.
 *   Documented limitation: because legacy rows have no unique barrier,
 *   double-booking against a pure-legacy slot relies on the caller's
 *   transaction + this read-side query, not on a database constraint.
 *   Pending appointments never occupy (approved rule).
 *
 * CLOSURE SEMANTICS
 * -----------------
 * Overlapping closure rows are UNIONed into merged time windows per date
 * (full-day rows win outright). Slot state precedence: past > unavailable
 * (closed) > occupied > available.
 *
 * PATIENT-SAFE OUTPUT
 * -------------------
 * slotsForDate() returns only date/slot/state data - no patient names, no
 * appointment IDs, no closure reasons (reasons are admin-internal by
 * approved policy; no method here ever places one in a patient payload).
 *
 * CONCURRENCY POSTURE
 * -------------------
 * The unique indexes on appointment_slot_holds are the PRIMARY guarantee
 * for new reservations; lockForUpdate() alone is never claimed as
 * sufficient (documented in the migration). Hold helpers do NOT open
 * their own transaction: callers embed them in their existing
 * appointment-then-request lock discipline, with the hold write LAST
 * (consistent global lock order, deadlock-safe).
 */
class ClinicAvailabilityService
{
    /** Slot length for checkup/counseling (approved). */
    public const SLOT_DURATION_MINUTES = 30;

    /** Exactly 18 slots/day: 08:00 .. 16:30 (approved). */
    public const SLOT_COUNT = 18;

    /** Clinic day boundaries (shared with AppointmentManagementService). */
    public const CLINIC_OPEN_TIME = AppointmentManagementService::CLINIC_OPEN_TIME;

    /** Exclusive end of the clinic day (last slot ends here). */
    public const CLINIC_CLOSE_TIME = AppointmentManagementService::CLINIC_LAST_START_TIME;

    public const CLINIC_TIMEZONE = AppointmentManagementService::CLINIC_TIMEZONE;

    /** Maximum inclusive closure range in calendar days (approved). */
    public const MAX_CLOSURE_RANGE_DAYS = 366;

    /** Availability states (patient/staff slot payloads). */
    public const STATE_AVAILABLE = 'available';
    public const STATE_OCCUPIED = 'occupied';
    public const STATE_PAST = 'past';
    public const STATE_UNAVAILABLE = 'unavailable';

    /** Appointment statuses that occupy a slot (pending deliberately absent). */
    public const OCCUPYING_STATUSES = [
        Appointment::STATUS_CONFIRMED,
        Appointment::STATUS_COMPLETED,
    ];

    /*
    |--------------------------------------------------------------------------
    | Schema readiness (deployment boundary)
    |--------------------------------------------------------------------------
    */

    /**
     * Phase 2 wires this service into every appointment write path, so a
     * runtime whose database has NOT yet run the Phase 1 migrations would
     * otherwise crash with a raw "table not found" error mid-transaction.
     * This guard fails EARLY and CLEANLY (DomainException -> the existing
     * withErrors UX) with a rollout instruction instead. Result is
     * memoized per PHP process (two hasTable calls once per request that
     * touches scheduling - negligible at clinic scale; in normal
     * production the tables always exist and this never fires).
     *
     * @throws DomainException when the Phase 1 tables are missing
     */
    public function assertSchedulingSchemaReady(): void
    {
        static $checked = false;

        if ($checked) {
            return;
        }

        $missing = [];

        if (! \Illuminate\Support\Facades\Schema::hasTable('clinic_unavailabilities')) {
            $missing[] = 'clinic_unavailabilities';
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('appointment_slot_holds')) {
            $missing[] = 'appointment_slot_holds';
        }

        if ($missing !== []) {
            throw new DomainException(sprintf(
                'Appointment scheduling is not available yet: the database is missing the %s table(s). Run the pending migrations (php artisan migrate) before using appointment scheduling.',
                implode(' and ', $missing)
            ));
        }

        $checked = true;
    }

    /*
    |--------------------------------------------------------------------------
    | Slot catalog
    |--------------------------------------------------------------------------
    */

    /**
     * The 18 canonical slot START times (H:i, zero-padded): 08:00, 08:30,
     * ... 16:30.
     *
     * @return array<int, string>
     */
    public function slotStarts(): array
    {
        $starts = [];
        $cursor = Carbon::createFromFormat('H:i', self::CLINIC_OPEN_TIME, self::CLINIC_TIMEZONE);

        for ($i = 0; $i < self::SLOT_COUNT; $i++) {
            $starts[] = $cursor->format('H:i');
            $cursor = $cursor->copy()->addMinutes(self::SLOT_DURATION_MINUTES);
        }

        return $starts;
    }

    /** Exclusive end (H:i) of the slot starting at $slotStart. */
    public function slotEnd(string $slotStart): string
    {
        $normalized = $this->assertSlotStart($slotStart);

        return Carbon::createFromFormat('H:i', $normalized, self::CLINIC_TIMEZONE)
            ->addMinutes(self::SLOT_DURATION_MINUTES)
            ->format('H:i');
    }

    /**
     * Display label for a slot start, e.g. "8:00–8:30 AM", "4:30–5:00 PM".
     */
    public function slotLabel(string $slotStart): string
    {
        $normalized = $this->assertSlotStart($slotStart);

        $start = Carbon::createFromFormat('H:i', $normalized, self::CLINIC_TIMEZONE);
        $end = $start->copy()->addMinutes(self::SLOT_DURATION_MINUTES);

        // First meridiem is implied by the second when they match
        // ("8:00–8:30 AM"); across noon both appear ("11:30 AM–12:00 PM").
        if ($start->format('A') === $end->format('A')) {
            return sprintf('%s–%s', $start->format('g:i'), $end->format('g:i A'));
        }

        return sprintf('%s %s–%s %s', $start->format('g:i'), $start->format('A'), $end->format('g:i'), $end->format('A'));
    }

    /**
     * 12-hour display label for a single clock time (H:i), e.g. "8:00 AM".
     * Unlike slotLabel() this accepts ANY clinic-grid-aligned time,
     * including 17:00 (the exclusive end of the final slot), so it can
     * label both window edges.
     *
     * @throws DomainException when the time is not grid-aligned
     */
    public function timeLabel(string $time): string
    {
        $normalized = $this->normalizeTimeInput($time);

        if ($normalized === null) {
            throw new DomainException('Invalid time.');
        }

        $endMinutes = $this->toMinutes($normalized);

        if ($endMinutes % self::SLOT_DURATION_MINUTES !== 0
            || $endMinutes > $this->toMinutes(self::CLINIC_CLOSE_TIME)) {
            throw new DomainException('Time is outside the clinic slot grid.');
        }

        return Carbon::createFromFormat('H:i', $normalized, self::CLINIC_TIMEZONE)->format('g:i A');
    }

    /**
     * Display label for one closure window, e.g. "10:00 AM – 12:30 PM"
     * or "10:00 AM – 11:00 AM". Admin UI only (the reason/labels never
     * reach patient-facing endpoints).
     */
    public function timeWindowLabel(?string $startTime, ?string $endTime): string
    {
        if ($startTime === null || $endTime === null) {
            return '—';
        }

        return sprintf('%s – %s', $this->timeLabel($startTime), $this->timeLabel($endTime));
    }

    /*
    |--------------------------------------------------------------------------
    | Time normalization / alignment
    |--------------------------------------------------------------------------
    */

    /** Accept H:i or H:i:s input, return canonical H:i, or null when absent/invalid. */
    public function normalizeTimeInput(?string $time): ?string
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        $time = trim($time);

        if (preg_match('/^(\d{2}:\d{2})(:\d{2})?$/', $time, $parts) !== 1) {
            return null;
        }

        return $parts[1];
    }

    /** True when $time is a valid slot start on the approved grid. */
    public function isSlotAligned(string $time): bool
    {
        $normalized = $this->normalizeTimeInput($time);

        if ($normalized === null) {
            return false;
        }

        $minutes = $this->toMinutes($normalized);
        $open = $this->toMinutes(self::CLINIC_OPEN_TIME);
        $close = $this->toMinutes(self::CLINIC_CLOSE_TIME);

        return $minutes % self::SLOT_DURATION_MINUTES === 0
            && $minutes >= $open
            && $minutes + self::SLOT_DURATION_MINUTES <= $close;
    }

    /**
     * Normalize + assert a slot start; DomainException when the value is
     * not a valid grid start (format, alignment or clinic window).
     */
    public function assertSlotStart(string $time): string
    {
        $normalized = $this->normalizeTimeInput($time);

        if ($normalized === null) {
            throw new DomainException('The time is not a valid clock time (expected HH:MM).');
        }

        if (! $this->isSlotAligned($normalized)) {
            throw new DomainException(sprintf(
                'The time %s is not a valid appointment slot. Slots start on the half hour from %s up to %s (Philippine clinic hours).',
                $normalized,
                self::CLINIC_OPEN_TIME,
                '16:30'
            ));
        }

        return $normalized;
    }

    /** Assert a Y-m-d calendar date; DomainException when invalid. */
    public function assertDate(string $date): string
    {
        $date = trim($date);

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) !== 1
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            throw new DomainException('The date is not a valid calendar date (expected YYYY-MM-DD).');
        }

        return $date;
    }

    /*
    |--------------------------------------------------------------------------
    | Past detection (Asia/Manila)
    |--------------------------------------------------------------------------
    */

    /**
     * True when the slot's START moment has already passed in Asia/Manila.
     * Matches the existing past-schedule rule (AppointmentManagementService
     * pastScheduleIssue): a start at or after the current minute is still
     * allowed - only strictly-earlier starts are past.
     */
    public function isSlotPast(string $date, string $slotStart, ?Carbon $now = null): bool
    {
        $normalized = $this->assertSlotStart($slotStart);
        $date = $this->assertDate($date);

        $now = $now ?? Carbon::now(self::CLINIC_TIMEZONE);
        $schedule = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $normalized, self::CLINIC_TIMEZONE);

        if (! $schedule instanceof Carbon) {
            return false;
        }

        return $schedule->lt($now->copy()->startOfMinute());
    }

    /*
    |--------------------------------------------------------------------------
    | Closures (read side)
    |--------------------------------------------------------------------------
    */

    /** Every closure row whose inclusive range touches the date. */
    public function closuresForDate(string $date): Collection
    {
        $date = $this->assertDate($date);

        return ClinicUnavailability::query()->coveringDate($date)->get();
    }

    /** Every closure row whose inclusive range touches [start, end]. */
    public function overlappingClosures(string $startDate, string $endDate, ?int $ignoreId = null): Collection
    {
        return ClinicUnavailability::query()
            ->overlappingRange($startDate, $endDate)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->get();
    }

    /**
     * Union-merged concrete time windows [start H:i, end H:i) covering the
     * date. Full-day closures are NOT expanded here - check
     * isDateFullyClosed() first (a full-day row short-circuits to a fully
     * closed day). Overlapping and adjacent partial windows merge
     * (10:00-12:00 + 11:00-13:00 => 10:00-13:00).
     *
     * @return array<int, array{start: string, end: string}>
     */
    public function unavailableWindows(string $date): array
    {
        if ($this->isDateFullyClosed($date)) {
            return [];
        }

        $windows = [];

        foreach ($this->closuresForDate($date) as $closure) {
            if ($closure->all_day) {
                continue; // handled by isDateFullyClosed()
            }

            $start = $this->normalizeTimeInput((string) $closure->start_time);
            $end = $this->normalizeTimeInput((string) $closure->end_time);

            if ($start === null || $end === null || $end <= $start) {
                continue; // malformed legacy row: ignore rather than crash reads
            }

            $windows[] = ['start' => $start, 'end' => $end];
        }

        return $this->mergeWindows($windows);
    }

    /**
     * True when the whole clinic day is closed: an all_day closure covers
     * the date, OR the union of partial windows covers 08:00-17:00.
     */
    public function isDateFullyClosed(string $date): bool
    {
        $date = $this->assertDate($date);

        $hasAllDay = ClinicUnavailability::query()
            ->coveringDate($date)
            ->where('all_day', true)
            ->exists();

        if ($hasAllDay) {
            return true;
        }

        $merged = $this->mergeWindows($this->rawPartialWindows($date));

        foreach ($merged as $window) {
            if ($this->toMinutes($window['start']) <= $this->toMinutes(self::CLINIC_OPEN_TIME)
                && $this->toMinutes($window['end']) >= $this->toMinutes(self::CLINIC_CLOSE_TIME)) {
                return true;
            }
        }

        return false;
    }

    /** True when any closure (full-day or intersecting window) blocks the slot. */
    public function isSlotClosed(string $date, string $slotStart): bool
    {
        $normalized = $this->assertSlotStart($slotStart);
        $end = $this->slotEnd($normalized);

        if ($this->isDateFullyClosed($date)) {
            return true;
        }

        foreach ($this->unavailableWindows($date) as $window) {
            if ($window['start'] < $end && $window['end'] > $normalized) {
                return true;
            }
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Occupancy (read side)
    |--------------------------------------------------------------------------
    */

    /**
     * Confirmed/completed non-delivery appointments whose recorded
     * confirmed_time falls inside [slotStart, slotEnd) on the date -
     * covers legacy non-grid times (14:37 occupies 14:30-15:00). Pending
     * and soft-deleted rows are excluded; delivery requests are excluded
     * (date-only scheduling model). $ignoreAppointmentId excludes ONE
     * appointment (the row being rescheduled) so its own current slot
     * never blocks its own move.
     */
    public function slotOccupyingAppointments(string $date, string $slotStart, ?int $ignoreAppointmentId = null): Collection
    {
        $normalized = $this->assertSlotStart($slotStart);
        $end = $this->slotEnd($normalized);

        return $this->occupyingAppointmentsForDate($date)
            ->filter(function (Appointment $appointment) use ($normalized, $end, $ignoreAppointmentId) {
                if ($ignoreAppointmentId !== null && $appointment->id === $ignoreAppointmentId) {
                    return false;
                }

                $time = $this->normalizeTimeInput((string) $appointment->confirmed_time);

                return $time !== null && $time >= $normalized && $time < $end;
            })
            ->values();
    }

    /** True when a hold row OR a legacy-window appointment occupies the slot. */
    public function isSlotOccupied(string $date, string $slotStart, ?int $ignoreAppointmentId = null): bool
    {
        $normalized = $this->assertSlotStart($slotStart);
        $date = $this->assertDate($date);

        $hasHold = AppointmentSlotHold::query()
            ->where('hold_date', $date)
            ->where('slot_start', $normalized)
            ->when(
                $ignoreAppointmentId !== null,
                fn ($query) => $query->where('appointment_id', '!=', $ignoreAppointmentId)
            )
            ->exists();

        if ($hasHold) {
            return true;
        }

        return $this->slotOccupyingAppointments($date, $normalized, $ignoreAppointmentId)->isNotEmpty();
    }

    /** The hold row for an appointment, or null. */
    public function holdFor(Appointment $appointment): ?AppointmentSlotHold
    {
        return AppointmentSlotHold::query()
            ->where('appointment_id', $appointment->id)
            ->first();
    }

    public function hasHold(Appointment $appointment): bool
    {
        return AppointmentSlotHold::query()
            ->where('appointment_id', $appointment->id)
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Availability states / patient-safe payload
    |--------------------------------------------------------------------------
    */

    /**
     * State precedence: past > unavailable (closed) > occupied > available.
     * A past slot reports 'past' even when also closed or occupied - the
     * explanation that matters to the user is that it can no longer be
     * selected.
     */
    public function slotState(string $date, string $slotStart, ?Carbon $now = null): string
    {
        $normalized = $this->assertSlotStart($slotStart);

        if ($this->isSlotPast($date, $normalized, $now)) {
            return self::STATE_PAST;
        }

        if ($this->isSlotClosed($date, $normalized)) {
            return self::STATE_UNAVAILABLE;
        }

        if ($this->isSlotOccupied($date, $normalized)) {
            return self::STATE_OCCUPIED;
        }

        return self::STATE_AVAILABLE;
    }

    /**
     * Patient-safe availability payload for one date: date marker, clinic
     * day flag, full-day closure flag and per-slot {start, end, label,
     * state}. NEVER includes patient names, appointment IDs or closure
     * reasons (approved policy - this is the contract Phase 2 UI consumes).
     *
     * @return array{date: string, clinic_today: bool, fully_unavailable: bool, slots: array<int, array{start: string, end: string, label: string, state: string}>}
     */
    public function slotsForDate(string $date, ?Carbon $now = null): array
    {
        $this->assertSchedulingSchemaReady();

        $date = $this->assertDate($date);
        $now = $now ?? Carbon::now(self::CLINIC_TIMEZONE);

        $fullyClosed = $this->isDateFullyClosed($date);
        $windows = $fullyClosed ? [] : $this->unavailableWindows($date);
        $holdStarts = AppointmentSlotHold::query()
            ->where('hold_date', $date)
            ->pluck('slot_start')
            ->map(fn ($time) => $this->normalizeTimeInput((string) $time))
            ->filter()
            ->all();
        $occupiedTimes = $this->occupyingAppointmentsForDate($date)
            ->map(fn (Appointment $appointment) => $this->normalizeTimeInput((string) $appointment->confirmed_time))
            ->filter()
            ->all();

        $slots = [];

        foreach ($this->slotStarts() as $start) {
            $end = Carbon::createFromFormat('H:i', $start, self::CLINIC_TIMEZONE)
                ->addMinutes(self::SLOT_DURATION_MINUTES)
                ->format('H:i');

            if ($this->isSlotPast($date, $start, $now)) {
                $state = self::STATE_PAST;
            } elseif ($fullyClosed) {
                $state = self::STATE_UNAVAILABLE;
            } elseif ($this->windowListBlocks($windows, $start, $end)) {
                $state = self::STATE_UNAVAILABLE;
            } elseif (in_array($start, $holdStarts, true)
                || $this->timesFallInSlot($occupiedTimes, $start, $end)) {
                $state = self::STATE_OCCUPIED;
            } else {
                $state = self::STATE_AVAILABLE;
            }

            $slots[] = [
                'start' => $start,
                'end' => $end,
                'label' => $this->slotLabel($start),
                'state' => $state,
            ];
        }

        return [
            'date' => $date,
            'clinic_today' => $date === $now->toDateString(),
            'fully_unavailable' => $fullyClosed,
            'slots' => $slots,
        ];
    }

    /**
     * Phase 3: convert a list of selected slot START times into valid
     * closure windows (contiguous runs become ONE window; non-contiguous
     * selections become SEPARATE windows). Each input must be a valid
     * grid start (assertSlotStart); duplicates are ignored; the result
     * is sorted. This is the single source of truth for the Admin
     * multi-slot form - the controller never reimplements the grouping.
     *
     * Example: [10:00, 10:30, 12:00] => [10:00-11:00, 12:00-12:30].
     *
     * @param  array<int, string>  $slotStarts
     * @return array<int, array{start: string, end: string}>
     *
     * @throws DomainException when a value is not a valid grid start
     */
    public function windowsFromSlotStarts(array $slotStarts): array
    {
        $normalized = [];

        foreach ($slotStarts as $slotStart) {
            $start = $this->assertSlotStart((string) $slotStart);
            $normalized[$start] = $start; // dedupe by value, preserve uniqueness
        }

        $starts = array_values($normalized);
        sort($starts);

        if ($starts === []) {
            return [];
        }

        $windows = [];
        $runStart = $starts[0];
        $runEnd = $this->slotEnd($starts[0]);

        foreach (array_slice($starts, 1) as $start) {
            if ($start === $runEnd) {
                $runEnd = $this->slotEnd($start);
                continue;
            }

            $windows[] = ['start' => $runStart, 'end' => $runEnd];
            $runStart = $start;
            $runEnd = $this->slotEnd($start);
        }

        $windows[] = ['start' => $runStart, 'end' => $runEnd];

        return $windows;
    }

    /*
    |--------------------------------------------------------------------------
    | Closure validation
    |--------------------------------------------------------------------------
    */

    /**
     * Shared field rules for the future FormRequest + validateClosureInput().
     */
    public static function closureValidationRules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'all_day' => ['required', 'boolean'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * FormRequest-level slot-grid issue (defence in depth - the service
     * repeats the same rule at the write boundary). Returns null when the
     * value is absent/invalidly formatted (format rules own that error) or
     * when it is a valid grid start; otherwise a field message.
     */
    public static function slotStartIssue(?string $time): ?string
    {
        $service = new self();
        $normalized = $service->normalizeTimeInput($time);

        if ($normalized === null) {
            return null;
        }

        if (! $service->isSlotAligned($normalized)) {
            return 'Choose a valid appointment slot start (8:00 AM, 8:30 AM, ..., 4:30 PM).';
        }

        return null;
    }

    /**
     * Validate + normalize closure input (approved cross-field rules).
     * Returns canonical array ready for model assignment:
     *   start_date, end_date (Y-m-d), all_day (bool),
     *   start_time/end_time (?H:i), reason (?trimmed string).
     *
     * @throws ValidationException with field-scoped messages
     */
    public function validateClosureInput(array $data): array
    {
        $data['start_time'] = $this->normalizeTimeInput($data['start_time'] ?? null);
        $data['end_time'] = $this->normalizeTimeInput($data['end_time'] ?? null);

        if (($data['start_time'] ?? null) === null) {
            $data['start_time'] = null;
        }

        if (($data['end_time'] ?? null) === null) {
            $data['end_time'] = null;
        }

        if (array_key_exists('all_day', $data) && $data['all_day'] !== null && $data['all_day'] !== '') {
            $data['all_day'] = filter_var($data['all_day'], FILTER_VALIDATE_BOOLEAN);
        }

        $validator = Validator::make($data, self::closureValidationRules(), [
            'start_date.date_format' => 'The start date must be a valid date in YYYY-MM-DD format.',
            'end_date.date_format' => 'The end date must be a valid date in YYYY-MM-DD format.',
            'end_date.after_or_equal' => 'The end date cannot precede the start date.',
            'start_time.date_format' => 'The start time must be a valid time in HH:MM format.',
            'end_time.date_format' => 'The end time must be a valid time in HH:MM format.',
            'reason.max' => 'The reason may not be longer than 255 characters.',
        ]);

        $validated = $validator->validate();

        $startDate = $this->assertDate((string) $validated['start_date']);
        $endDate = $this->assertDate((string) $validated['end_date']);
        $allDay = (bool) $validated['all_day'];
        $startTime = $validated['start_time'] ?? null;
        $endTime = $validated['end_time'] ?? null;
        $reason = isset($validated['reason']) && $validated['reason'] !== null && trim((string) $validated['reason']) !== ''
            ? trim((string) $validated['reason'])
            : null;

        $errors = [];

        $spanDays = (int) Carbon::createFromFormat('Y-m-d', $startDate, self::CLINIC_TIMEZONE)
            ->startOfDay()
            ->diffInDays(
                Carbon::createFromFormat('Y-m-d', $endDate, self::CLINIC_TIMEZONE)->startOfDay()
            ) + 1;

        if ($spanDays > self::MAX_CLOSURE_RANGE_DAYS) {
            $errors['end_date'] = sprintf(
                'A closure may not span more than %d calendar days (inclusive).',
                self::MAX_CLOSURE_RANGE_DAYS
            );
        }

        if ($allDay) {
            if ($startTime !== null || $endTime !== null) {
                $errors['start_time'] = 'A full-day closure must not include start or end times. Set all_day to false for a partial time window.';
            }
        } else {
            if ($startDate !== $endDate) {
                $errors['end_date'] = 'A partial time-window closure must start and end on the same date. Use a full-day (all_day) closure for multi-day ranges.';
            }

            if ($startTime === null) {
                $errors['start_time'] = 'A partial time-window closure requires a start time.';
            } elseif (! $this->isSlotAligned($startTime)) {
                $errors['start_time'] = sprintf(
                    'The start time must be a valid slot start on the 30-minute grid (%s, %s, ... 16:30).',
                    self::CLINIC_OPEN_TIME,
                    '08:30'
                );
            }

            if ($endTime === null) {
                $errors['end_time'] = 'A partial time-window closure requires an end time (exclusive).';
            } else {
                $endMinutes = $this->toMinutes($endTime);

                if ($endMinutes % self::SLOT_DURATION_MINUTES !== 0
                    || $endMinutes > $this->toMinutes(self::CLINIC_CLOSE_TIME)) {
                    $errors['end_time'] = sprintf(
                        'The end time must fall on the 30-minute grid no later than %s (exclusive clinic close).',
                        self::CLINIC_CLOSE_TIME
                    );
                }
            }

            if ($startTime !== null && $endTime !== null && ! isset($errors['start_time'], $errors['end_time'])) {
                if ($endTime <= $startTime) {
                    $errors['end_time'] = 'The end time must be later than the start time (end times are exclusive).';
                } elseif (($this->toMinutes($endTime) - $this->toMinutes($startTime)) % self::SLOT_DURATION_MINUTES !== 0) {
                    $errors['end_time'] = 'The closure window must be a whole number of 30-minute slots.';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'all_day' => $allDay,
            'start_time' => $allDay ? null : $startTime,
            'end_time' => $allDay ? null : $endTime,
            'reason' => $reason,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Affected-appointment preview
    |--------------------------------------------------------------------------
    */

    /**
     * Appointments a proposed closure would touch, split into the two
     * approved buckets. Effective schedule = confirmed_* ?? requested_*.
     * Full-day/range closures match on effective DATE only; time-window
     * closures additionally require an effective time inside the window.
     * NOTHING is modified - the counts only drive the required explicit
     * admin confirmation and the preview UI.
     *
     * $forUpdate (Phase 3): when true, the appointment rows in the range
     * are locked FOR UPDATE for the remainder of the caller's
     * transaction. The closure write paths pass true so a staff
     * confirmation racing an admin closure is SERIALIZED: the confirm
     * path locks the appointment row first (lockAppointment), so either
     * the confirmation commits before the closure's re-check sees it
     * (counted as an affected existing appointment) or the closure
     * commits first and the confirmation's assertClinicAvailable
     * refuses the now-closed slot. On SQLite lockForUpdate is a no-op
     * (single-writer transactions serialize anyway); the walk-in
     * INSERT-phantom race is documented in the Phase 3 report. The
     * preview endpoint calls this with $forUpdate = false (read-only).
     *
     * @return array{confirmed: Collection, pending: Collection, confirmed_count: int, completed_count: int, pending_count: int}
     */
    public function affectedAppointments(array $closure, bool $forUpdate = false): array
    {
        $closure = $this->validateClosureInput($closure);

        // Effective date via COALESCE. substr(..., 1, 10) keeps the
        // comparison portable when a driver serializes the date cast as
        // 'Y-m-d 00:00:00' (SQLite) instead of bare 'Y-m-d' (MySQL DATE).
        $appointments = Appointment::query()
            ->whereIn('status', array_merge(self::OCCUPYING_STATUSES, [Appointment::STATUS_PENDING]))
            ->whereRaw('substr(COALESCE(confirmed_date, requested_date), 1, 10) BETWEEN ? AND ?', [
                $closure['start_date'],
                $closure['end_date'],
            ])
            ->when($forUpdate, fn ($query) => $query->lockForUpdate())
            ->get();

        if (! $closure['all_day']) {
            // Effective time compared in PHP through the same
            // normalizeTimeInput the occupancy path uses (H:i vs H:i:s
            // reads stay consistent; no raw engine time comparison).
            $appointments = $appointments
                ->filter(function (Appointment $appointment) use ($closure) {
                    $time = $this->normalizeTimeInput(
                        (string) ($appointment->confirmed_time ?? $appointment->requested_time)
                    );

                    return $time !== null
                        && $time >= $closure['start_time']
                        && $time < $closure['end_time'];
                })
                ->values();
        }

        $confirmed = $appointments
            ->filter(fn (Appointment $appointment) => in_array($appointment->status, self::OCCUPYING_STATUSES, true))
            ->values();
        $pending = $appointments
            ->filter(fn (Appointment $appointment) => $appointment->status === Appointment::STATUS_PENDING)
            ->values();

        // Phase 3: completed appointments are reported separately so the
        // Admin preview can distinguish them; confirmed_count stays the
        // combined occupying count (the confirm-affected gate and all
        // existing consumers keep their meaning).
        $completedCount = $confirmed
            ->filter(fn (Appointment $appointment) => $appointment->status === Appointment::STATUS_COMPLETED)
            ->count();

        return [
            'confirmed' => $confirmed,
            'pending' => $pending,
            'confirmed_count' => $confirmed->count(),
            'completed_count' => $completedCount,
            'pending_count' => $pending->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Closure domain operations (transaction-compatible; audit in-transaction)
    |--------------------------------------------------------------------------
    */

    /**
     * Create one closure row. When the proposal touches existing
     * confirmed/completed appointments, $confirmAffected must be true
     * (approved: explicit admin confirmation; existing appointments are
     * NEVER auto-cancelled/rescheduled - they are left untouched).
     *
     * @throws DomainException when affected appointments exist and unconfirmed
     * @throws ValidationException on invalid input
     */
    public function createClosure(array $data, User $actor, bool $confirmAffected = false): ClinicUnavailability
    {
        $normalized = $this->validateClosureInput($data);
        $this->assertAffectedConfirmedAccepted($normalized, $confirmAffected);

        return DB::transaction(function () use ($normalized, $actor) {
            $closure = new ClinicUnavailability();
            $closure->start_date = $normalized['start_date'];
            $closure->end_date = $normalized['end_date'];
            $closure->all_day = $normalized['all_day'];
            $closure->start_time = $normalized['start_time'];
            $closure->end_time = $normalized['end_time'];
            $closure->reason = $normalized['reason'];
            $closure->created_by = $actor->id;
            $closure->save();

            $this->auditClosure($actor, $closure, 'CLINIC_UNAVAILABILITY_CREATED', sprintf(
                'Clinic unavailability #%d created: %s.',
                $closure->id,
                $this->describeClosureWindow($normalized)
            ));

            return $closure;
        });
    }

    /**
     * Update one closure row (partial $data; omitted keys keep current
     * values). Re-validates the merged result and re-requires the
     * affected-appointment confirmation.
     */
    public function updateClosure(ClinicUnavailability $closure, array $data, User $actor, bool $confirmAffected = false): ClinicUnavailability
    {
        $merged = $data + [
            'start_date' => $closure->start_date->toDateString(),
            'end_date' => $closure->end_date->toDateString(),
            'all_day' => $closure->all_day,
            'start_time' => $closure->start_time,
            'end_time' => $closure->end_time,
            'reason' => $closure->reason,
        ];

        $normalized = $this->validateClosureInput($merged);
        $this->assertAffectedConfirmedAccepted($normalized, $confirmAffected);

        return DB::transaction(function () use ($closure, $normalized, $actor) {
            $previous = $this->describeClosureWindow([
                'start_date' => $closure->start_date->toDateString(),
                'end_date' => $closure->end_date->toDateString(),
                'all_day' => $closure->all_day,
                'start_time' => $closure->start_time,
                'end_time' => $closure->end_time,
                'reason' => $closure->reason,
            ]);

            $closure->start_date = $normalized['start_date'];
            $closure->end_date = $normalized['end_date'];
            $closure->all_day = $normalized['all_day'];
            $closure->start_time = $normalized['start_time'];
            $closure->end_time = $normalized['end_time'];
            $closure->reason = $normalized['reason'];
            $closure->save();

            $this->auditClosure($actor, $closure, 'CLINIC_UNAVAILABILITY_UPDATED', sprintf(
                'Clinic unavailability #%d updated. Previous: %s. New: %s.',
                $closure->id,
                $previous,
                $this->describeClosureWindow($normalized)
            ));

            return $closure;
        });
    }

    /**
     * Remove (reopen) one closure row. Hard delete per approved policy;
     * audit_logs retain the history.
     */
    public function removeClosure(ClinicUnavailability $closure, User $actor): void
    {
        DB::transaction(function () use ($closure, $actor) {
            $description = $this->describeClosureWindow([
                'start_date' => $closure->start_date->toDateString(),
                'end_date' => $closure->end_date->toDateString(),
                'all_day' => $closure->all_day,
                'start_time' => $closure->start_time,
                'end_time' => $closure->end_time,
                'reason' => $closure->reason,
            ]);

            $this->auditClosure($actor, $closure, 'CLINIC_UNAVAILABILITY_REMOVED', sprintf(
                'Clinic unavailability #%d removed (reopened). Previous: %s.',
                $closure->id,
                $description
            ));

            $closure->delete();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Slot-hold domain operations (caller owns the transaction)
    |--------------------------------------------------------------------------
    */

    /**
     * Reserve the slot for a CONFIRMED/COMPLETED timed appointment. The
     * UNIQUE (hold_date, slot_start) index is the primary barrier: a
     * concurrent winner surfaces as a unique-constraint violation,
     * translated (after a precise conflict re-check) to a DomainException.
     *
     * Callers must already run inside their transaction, with the
     * appointment/change-request lock discipline observed FIRST and this
     * write LAST (global lock order).
     *
     * @throws DomainException on delivery/pending type-status, bad slot, or slot race
     * @throws QueryException on unexpected integrity failures
     */
    public function reserveHold(Appointment $appointment, string $date, string $slotStart): AppointmentSlotHold
    {
        $this->assertSchedulingSchemaReady();
        $this->assertAppointmentUsesSlots($appointment);

        $normalized = $this->assertSlotStart($slotStart);
        $date = $this->assertDate($date);

        $hold = new AppointmentSlotHold();
        $hold->hold_date = $date;
        $hold->slot_start = $normalized;
        $hold->appointment_id = $appointment->id;

        try {
            $hold->save();
        } catch (QueryException $exception) {
            $this->rethrowHoldConflict($exception, $date, $normalized, $appointment->id);
        }

        return $hold;
    }

    /**
     * Move an appointment's EXISTING hold to another slot (reschedule
     * approval path). UPDATE in place - never a second row (UNIQUE
     * appointment_id).
     *
     * @throws DomainException when no hold exists or the target slot was just taken
     */
    public function moveHold(Appointment $appointment, string $newDate, string $newSlotStart): AppointmentSlotHold
    {
        $this->assertSchedulingSchemaReady();
        $this->assertAppointmentUsesSlots($appointment);

        $normalized = $this->assertSlotStart($newSlotStart);
        $date = $this->assertDate($newDate);

        $hold = $this->holdFor($appointment);

        if ($hold === null) {
            throw new DomainException('This appointment does not currently reserve a slot hold, so it cannot be moved.');
        }

        $hold->hold_date = $date;
        $hold->slot_start = $normalized;

        try {
            $hold->save();
        } catch (QueryException $exception) {
            $this->rethrowHoldConflict($exception, $date, $normalized, $appointment->id);
        }

        return $hold;
    }

    /**
     * Release the appointment's hold (cancel/decline path). Idempotent:
     * an appointment without a hold is not an error (pending rows never
     * had one). Completed appointments simply never call this - they
     * retain occupancy.
     *
     * @return bool true when a hold row was removed
     */
    public function releaseHold(Appointment $appointment): bool
    {
        $this->assertSchedulingSchemaReady();

        return AppointmentSlotHold::query()
            ->where('appointment_id', $appointment->id)
            ->delete() > 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /** Confirmed/completed non-delivery appointments recorded on the date. */
    private function occupyingAppointmentsForDate(string $date): Collection
    {
        return Appointment::query()
            ->whereIn('status', self::OCCUPYING_STATUSES)
            ->where('appointment_type', '!=', Appointment::TYPE_DELIVERY_REQUEST)
            ->whereNotNull('confirmed_time')
            ->whereDate('confirmed_date', $date)
            ->get();
    }

    /** Raw (unmerged) partial windows for the date. */
    private function rawPartialWindows(string $date): array
    {
        $windows = [];

        foreach ($this->closuresForDate($date) as $closure) {
            if ($closure->all_day) {
                continue;
            }

            $start = $this->normalizeTimeInput((string) $closure->start_time);
            $end = $this->normalizeTimeInput((string) $closure->end_time);

            if ($start === null || $end === null || $end <= $start) {
                continue;
            }

            $windows[] = ['start' => $start, 'end' => $end];
        }

        return $windows;
    }

    /**
     * Union-merge windows by minute ranges; adjacent windows join
     * (next.start <= current.end).
     *
     * @param array<int, array{start: string, end: string}> $windows
     * @return array<int, array{start: string, end: string}>
     */
    private function mergeWindows(array $windows): array
    {
        if ($windows === []) {
            return [];
        }

        usort($windows, fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        $merged = [];
        $current = $windows[0];

        for ($i = 1, $count = count($windows); $i < $count; $i++) {
            $next = $windows[$i];

            if ($this->toMinutes($next['start']) <= $this->toMinutes($current['end'])) {
                if ($this->toMinutes($next['end']) > $this->toMinutes($current['end'])) {
                    $current['end'] = $next['end'];
                }

                continue;
            }

            $merged[] = $current;
            $current = $next;
        }

        $merged[] = $current;

        return $merged;
    }

    /**
     * @param array<int, array{start: string, end: string}> $windows
     */
    private function windowListBlocks(array $windows, string $slotStart, string $slotEnd): bool
    {
        foreach ($windows as $window) {
            if ($window['start'] < $slotEnd && $window['end'] > $slotStart) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, string> $times */
    private function timesFallInSlot(array $times, string $slotStart, string $slotEnd): bool
    {
        foreach ($times as $time) {
            if ($time >= $slotStart && $time < $slotEnd) {
                return true;
            }
        }

        return false;
    }

    /** 'HH:MM' -> minutes since midnight. */
    private function toMinutes(string $time): int
    {
        return ((int) substr($time, 0, 2)) * 60 + (int) substr($time, 3, 2);
    }

    /** Holds exist only for confirmed/completed timed (non-delivery) appointments. */
    private function assertAppointmentUsesSlots(Appointment $appointment): void
    {
        if ($appointment->appointment_type === Appointment::TYPE_DELIVERY_REQUEST) {
            throw new DomainException('Delivery requests are date-only and never reserve 30-minute slot holds.');
        }

        if (! in_array($appointment->status, self::OCCUPYING_STATUSES, true)) {
            throw new DomainException('Slot holds apply only to confirmed or completed appointments. Pending requests do not reserve slots.');
        }
    }

    /**
     * Unique-constraint violations from hold INSERT/UPDATE are translated
     * to DomainException ONLY when the conflicting row actually exists
     * (precise, driver-agnostic via SQLSTATE 23000/23505 + row re-check -
     * never fragile error-message matching). Other integrity failures
     * (e.g. foreign keys) are rethrown untouched.
     *
     * @throws DomainException
     * @throws QueryException
     */
    private function rethrowHoldConflict(QueryException $exception, string $date, string $slotStart, int $appointmentId): void
    {
        if (! $this->isUniqueConstraintViolation($exception)) {
            throw $exception;
        }

        $slotTaken = AppointmentSlotHold::query()
            ->where('hold_date', $date)
            ->where('slot_start', $slotStart)
            ->exists();
        $appointmentTaken = AppointmentSlotHold::query()
            ->where('appointment_id', $appointmentId)
            ->exists();

        if ($slotTaken || $appointmentTaken) {
            throw new DomainException('That appointment slot was just reserved by another booking. Please choose a different available time.');
        }

        throw $exception;
    }

    /** SQLSTATE class 23 (integrity) on both MySQL (23000) and SQLite (23505/23000). */
    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        if ($exception instanceof \Illuminate\Database\UniqueConstraintViolationException) {
            return true;
        }

        if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
            return true;
        }

        $previous = $exception->getPrevious();

        return $previous instanceof \PDOException
            && in_array((string) $previous->getCode(), ['23000', '23505'], true);
    }

    /**
     * Approved policy gate: existing confirmed/completed appointments in
     * the proposal require an explicit confirmation flag. Called from the
     * closure write paths INSIDE their transaction, so the affected query
     * runs with $forUpdate = true (row locks serialize a racing staff
     * confirmation - see affectedAppointments()).
     */
    private function assertAffectedConfirmedAccepted(array $normalized, bool $confirmAffected): void
    {
        $affected = $this->affectedAppointments($normalized, forUpdate: true);

        if ($affected['confirmed_count'] > 0 && ! $confirmAffected) {
            throw new DomainException(sprintf(
                'This closure affects %d existing confirmed or completed appointment(s). Review them, then confirm the closure again. Existing appointments are never cancelled or rescheduled automatically.',
                $affected['confirmed_count']
            ));
        }
    }

    /** Audit row in the same transaction as the closure change (established convention). */
    private function auditClosure(User $actor, ClinicUnavailability $closure, string $action, string $description): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'patient_id' => null,
            'action' => $action,
            'module' => 'CLINIC_UNAVAILABILITY',
            'description' => $description,
        ]);
    }

    private function describeClosureWindow(array $closure): string
    {
        $dates = $closure['start_date'] === $closure['end_date']
            ? $closure['start_date']
            : sprintf('%s to %s (inclusive)', $closure['start_date'], $closure['end_date']);

        if ($closure['all_day']) {
            return sprintf('%s, all day', $dates);
        }

        return sprintf('%s, %s–%s (end exclusive)', $dates, $closure['start_time'], $closure['end_time']);
    }
}
