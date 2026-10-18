<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * DEPLA Phase 3 - Admin closure removal (DELETE). Authorization-only
 * request: the DELETE carries no content fields. Third independent
 * boundary on top of the 'auth' + 'clinic' group and AdminMiddleware
 * (same three-layer pattern as the rest of the Admin modules).
 */
class AdminClinicAvailabilityDestroyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && ! $user->trashed()
            && $user->role === 'admin';
    }
}
