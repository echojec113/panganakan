<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DEPLA Phase 3D.2C - Admin/Staff DECLINE of a patient cancellation
 * request. A meaningful explanation (min 3 chars, capped at 1000) is
 * mandatory and is stored on the change request's review_note; the
 * patient portal card displays it. The appointment itself stays
 * confirmed with its schedule intact - declining never mutates the
 * appointment row.
 */
class AppointmentChangeRequestDeclineRequest extends FormRequest
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
            'review_note' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'review_note.required' => 'An explanation is required when declining a cancellation request.',
            'review_note.min' => 'The explanation must be at least 3 characters.',
            'review_note.max' => 'The explanation may not exceed 1000 characters.',
        ];
    }
}
