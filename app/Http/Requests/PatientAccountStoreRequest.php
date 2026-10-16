<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * DEPLA Phase 2B — validation + authorization for provisioning a patient
 * login account.
 *
 * Authorization is intentionally a second, independent boundary on top of the
 * `auth` + `clinic` middleware group: it re-states the same rule so that the
 * endpoint can never become reachable by a guest, a patient-role user, an
 * unknown role or an archived clinic account even if the route group is ever
 * edited by mistake.
 */
class PatientAccountStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        // instanceof guard: an archived account rehydrated through a stale
        // session must never pass, and the flag is only ever read from the
        // real Eloquent model.
        return $user instanceof User
            && ! $user->trashed()
            && in_array($user->role, ['admin', 'staff'], true);
    }

    /**
     * Trim and lower-case the email before validation so case variants cannot
     * slip past the global uniqueness requirement (the unique index is
     * case-sensitive on the SQLite test database and case-insensitive on the
     * production MySQL collation).
     */
    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // DEPLA Phase 1.5: the email is the ONLY credential input. Any
        // password fields a stale/crafted form might still post are
        // deliberately NOT validated and never reach the service - the
        // patient chooses their own password through the emailed setup
        // link.
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                // DatabasePresenceVerifier reads the table directly, so
                // soft-deleted users are included - the rule therefore covers
                // active AND archived accounts.
                Rule::unique('users', 'email'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already used by an existing account, including deactivated accounts.',
            'email.max' => 'The email must not exceed 255 characters.',
        ];
    }
}
