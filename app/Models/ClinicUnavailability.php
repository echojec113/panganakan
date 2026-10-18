<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DEPLA Phase 1 (appointment scheduling upgrade) - admin-declared clinic
 * unavailability period.
 *
 * One row = one closure: a full-day date, a continuous inclusive date range
 * (all_day=true), or a partial time window on a single date (all_day=false,
 * start_time inclusive / end_time EXCLUSIVE, 30-minute grid aligned).
 * Overlapping rows are allowed; occupancy is their UNION (computed by
 * ClinicAvailabilityService, never by the schema).
 *
 * The `reason` column is ADMIN-INTERNAL by approved policy. This model
 * deliberately defines no accessor, serialize() override or patient-facing
 * scope for it - no patient-facing serialization path may include it, and
 * the future slotsForDate() payload must never expose it.
 *
 * Cross-field invariants (end_date >= start_date, 366-day max range,
 * all_day=false => single date + aligned window, all_day=true => NULL
 * times) are validated in ClinicAvailabilityService::validateClosureInput()
 * - application-level by design (portable across MySQL and SQLite).
 */
class ClinicUnavailability extends Model
{
    /**
     * Server-owned columns are written only by ClinicAvailabilityService
     * through explicit property assignment inside its transaction (same
     * mass-assignment hardening doctrine as Appointment). The content
     * fields listed here are the ones a future FormRequest may supply.
     */
    protected $fillable = [
        'start_date',
        'end_date',
        'all_day',
        'start_time',
        'end_time',
        'reason',
        'created_by',
    ];

    /**
     * Dates are casts so every consumer compares Y-m-d through Carbon
     * (patients/prenatal_visits convention). Time columns stay strings:
     * plain clock values (canonical H:i), no date to anchor.
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'all_day' => 'boolean',
    ];

    /** The admin/staff member who declared the closure (SET NULL on removal). */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Date coverage: every closure whose inclusive range touches $date. */
    public function scopeCoveringDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);
    }

    /**
     * Range overlap: every closure whose inclusive range touches
     * [start, end]. whereDate() (like scopeCoveringDate above) keeps the
     * comparison portable - a raw string comparison breaks on SQLite,
     * where the date cast serializes as 'Y-m-d 00:00:00' and
     * 'Y-m-d 00:00:00' <= 'Y-m-d' is lexicographically false. MySQL DATE
     * columns store bare 'Y-m-d', so whereDate() is a no-op there.
     */
    public function scopeOverlappingRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate);
    }

    /** True when the inclusive range covers the given Y-m-d date. */
    public function coversDate(string $date): bool
    {
        return $date >= $this->start_date->toDateString()
            && $date <= $this->end_date->toDateString();
    }

    /** Partial time window as [start H:i, end H:i) or null for full-day. */
    public function timeWindow(): ?array
    {
        if ($this->all_day) {
            return null;
        }

        return [$this->start_time, $this->end_time];
    }

    /** Human label for admin UI lists: "All day" or "10:00–11:00 AM". */
    public function getWindowLabelAttribute(): string
    {
        if ($this->all_day) {
            return 'All day';
        }

        return sprintf('%s–%s', $this->start_time, $this->end_time);
    }
}
