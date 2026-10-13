<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DEPLA Phase 3D.2C - Admin/Staff APPROVAL of a patient cancellation
 * request. The optional staff note (3-1000 chars when present) is stored
 * on the change request's review_note; the appointment itself is
 * cancelled by AppointmentManagementService::approveCancellationRequest
 * using the PATIENT's original reason, never re-typed by the reviewer.
 */
class AppointmentChangeRequestApproveRequest extends FormRequest
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
            'review_note' => ['nullable', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'review_note.min' => 'The staff note must be at least 3 characters when provided.',
            'review_note.max' => 'The staff note may not exceed 1000 characters.',
        ];
    }
}
