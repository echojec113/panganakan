<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * DEPLA Phase 3 - affected-appointments PREVIEW (read-only JSON GET).
 *
 * Validates one proposed closure window (the exact shape the service
 * affectedAppointments() expects). Never modifies anything; the backend
 * re-runs the same computation inside the write transaction at save
 * time, so a client-provided count is never trusted.
 *
 * `ignore` optionally excludes one closure row from the OVERLAP warning
 * count (edit mode must not warn about overlapping itself).
 */
class AdminClinicAvailabilityAffectedRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && ! $user->trashed()
            && $user->role === 'admin';
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'string', 'date_format:Y-m-d'],
            'end_date' => ['required', 'string', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'all_day' => ['required', 'boolean'],
            'start_time' => ['nullable', 'string', 'date_format:H:i'],
            'end_time' => ['nullable', 'string', 'date_format:H:i'],
            'ignore' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
