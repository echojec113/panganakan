<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Password;

/**
 * Phase 2C - the mandatory first-login password change form.
 *
 * Submission is limited to a patient-role account that still carries
 * must_change_password = true. Everything else is refused before any rule
 * runs, so a flagged patient can never be validation-blocked out of the form
 * they are required to complete.
 */
class ChangeInitialPasswordRequest extends FormRequest
{
    /**
     * Patient role AND the mandatory-change flag must both hold. A false
     * result is interpreted by failedAuthorization() below instead of the
     * default 403.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->role === 'patient'
            && (bool) $user->must_change_password;
    }

    /**
     * Authorization failed - distinguish the two legitimate reasons:
     *
     * - A patient whose flag is already cleared has nothing to change, so
     *   they are forwarded to the portal. This runs BEFORE validation, which
     *   guarantees "no state change" for every payload, valid or not.
     * - Any other caller (guest, staff, admin, unknown role) is a genuine
     *   access violation and keeps the framework default of 403.
     */
    protected function failedAuthorization(): void
    {
        $user = $this->user();

        if ($user !== null && $user->role === 'patient') {
            throw new HttpResponseException(
                redirect()->route('patient.portal.coming-soon')
            );
        }

        abort(403);
    }

    /**
     * The current password must still be valid, and the new one must differ
     * from it. Password::defaults() is the project-wide policy (min 8), the
     * same rule used by ProfileUpdateRequest and PasswordController, so the
     * clinical/staff password policy can never drift from the patient one.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'different:current_password',
                Password::defaults(),
            ],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Enter your current password.',
            'current_password.current_password' => 'The current password you entered is incorrect.',

            'password.required' => 'Choose a new password.',
            'password.confirmed' => 'The new password confirmation does not match.',
            'password.different' => 'The new password must be different from your current password.',

            'password_confirmation.required' => 'Confirm your new password.',
        ];
    }
}
