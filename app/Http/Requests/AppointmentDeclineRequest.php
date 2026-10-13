<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DEPLA Phase 3B.2B - Decline a pending appointment request.
 * A meaningful reason is mandatory (min 3 chars, capped at 1000).
 */
class AppointmentDeclineRequest extends FormRequest
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
            'decline_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decline_reason.required' => 'A decline reason is required.',
            'decline_reason.min' => 'The decline reason must be at least 3 characters.',
            'decline_reason.max' => 'The decline reason may not exceed 1000 characters.',
        ];
    }
}
