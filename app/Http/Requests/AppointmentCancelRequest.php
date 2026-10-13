<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DEPLA Phase 3B.2B - Cancel a confirmed appointment.
 * A meaningful cancellation reason is mandatory. The cancellation never
 * touches reviewed_by/reviewed_at (the original review provenance); the
 * actor is captured in the audit log.
 */
class AppointmentCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role, ['admin', 'staff'], true);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'A cancellation reason is required.',
            'cancellation_reason.min' => 'The cancellation reason must be at least 3 characters.',
            'cancellation_reason.max' => 'The cancellation reason may not exceed 1000 characters.',
        ];
    }
}
