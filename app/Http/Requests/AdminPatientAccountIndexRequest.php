<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * DEPLA Phase 2 Sprint 1 — list controls for the centralized Admin
 * Patient Account Management page.
 *
 * Authorization is a second, independent boundary on top of the
 * 'auth' + 'clinic' group and AdminMiddleware: the endpoint can never
 * become reachable by Staff, Patient, unknown or archived accounts even
 * if the route group is ever edited by mistake (Staff must not access
 * the centralized Admin module at all).
 */
class AdminPatientAccountIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && ! $user->trashed()
            && $user->role === 'admin';
    }

    /**
     * Normalize the list controls before validation so an empty search box
     * and an absent status behave exactly like their explicit defaults.
     */
    protected function prepareForValidation(): void
    {
        $search = $this->input('search');
        $status = $this->input('status');

        $merge = [];

        if (is_string($search)) {
            $merge['search'] = trim($search);
        }

        if ($status === null || $status === '') {
            $merge['status'] = 'all';
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * URL-supplied list controls are validated server-side instead of
     * being trusted: the search term is length-bounded and the status is
     * constrained to the four documented values (an unknown value would
     * otherwise silently degrade to the unfiltered list through the
     * controller's `default` match arm).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['all', 'active', 'pending', 'deactivated'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
