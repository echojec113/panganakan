<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * DEPLA Phase 3 - Admin closure EDIT (one existing row).
 *
 * Only the row's own content fields are accepted; created_by is NEVER
 * read (the service keeps the original creator). Cross-field rules
 * (366-day range, partial-window alignment, all_day time nullability)
 * are re-validated by ClinicAvailabilityService::validateClosureInput
 * inside updateClosure's transaction.
 *
 * Authorization is the second independent boundary on top of the 'auth' +
 * 'clinic' group and AdminMiddleware.
 */
class AdminClinicAvailabilityUpdateRequest extends FormRequest
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
            'reason' => ['nullable', 'string', 'max:255'],
            'confirm_affected' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.required' => 'Choose the closure start date.',
            'start_date.date_format' => 'The start date must be a valid date (YYYY-MM-DD).',
            'end_date.required' => 'Choose the closure end date.',
            'end_date.date_format' => 'The end date must be a valid date (YYYY-MM-DD).',
            'end_date.after_or_equal' => 'The end date cannot precede the start date.',
            'all_day.required' => 'Choose whether the whole day (or range) is closed.',
            'all_day.boolean' => 'Choose whether the whole day (or range) is closed.',
            'start_time.date_format' => 'The start time must be a valid time (HH:MM).',
            'end_time.date_format' => 'The end time must be a valid time (HH:MM).',
            'reason.max' => 'The reason may not be longer than 255 characters.',
        ];
    }
}
