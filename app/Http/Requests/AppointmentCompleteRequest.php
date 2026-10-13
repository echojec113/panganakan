<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DEPLA Phase 3B.2B - Mark a confirmed appointment as completed.
 * No payload fields: completion is an explicit administrative action with
 * no automatic triggers and no clinical side effects.
 */
class AppointmentCompleteRequest extends FormRequest
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
        return [];
    }
}
