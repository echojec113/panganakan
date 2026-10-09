<?php

namespace App\Http\Middleware;

use App\Models\Patient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 2C - Patient Portal eligibility (deny by default).
 *
 * A patient-role login may only reach the portal while it is linked to a live
 * (non-archived) patients row. An account with no link, or whose patient
 * record has been soft-deleted (archived), is refused with 403 before any
 * portal content renders.
 *
 * Non-patient roles pass through untouched: clinic staff keep the existing
 * redirect to the dashboard and unknown roles are still refused by the route
 * itself, so this middleware changes nothing for Admin/Staff.
 */
class EnsurePatientPortalAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->role !== 'patient') {
            return $next($request);
        }

        // withTrashed(): an archived record must surface as an explicit 403,
        // not as a silent "no link found" that hides what happened.
        $linked = Patient::withTrashed()
            ->where('user_id', $user->id)
            ->first();

        if ($linked === null || $linked->trashed()) {
            // DEPLA Phase 2D (§12): patient-friendly wording only. The status
            // stays 403 and nothing else about the eligibility rule changed;
            // no account state, link or archived record is disclosed here.
            abort(
                403,
                'Your patient account is currently unavailable. Please contact the clinic for assistance.'
            );
        }

        return $next($request);
    }
}
