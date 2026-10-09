<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangeInitialPasswordRequest;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Phase 2C - mandatory first-login password change.
 *
 * Reachable only by authenticated patient-role accounts (the FormRequest
 * authorizes role + flag; edit() enforces the same two conditions on GET).
 * The password write, the flag clearing and the audit entry share one
 * transaction, so a patient is never left with a new password and a stuck
 * flag, or a cleared flag and no audit trail.
 */
class PatientInitialPasswordController extends Controller
{
    /**
     * Show the mandatory change form.
     *
     * Non-patients are refused with 403 (patient-only endpoint). A patient
     * whose flag is already cleared is forwarded to the portal unchanged -
     * a second tab or a refresh after a successful change never re-enters
     * the flow.
     */
    public function edit(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user === null || $user->role !== 'patient') {
            abort(403);
        }

        if (! $user->must_change_password) {
            return redirect()->route('patient.portal.coming-soon');
        }

        return view('patient.change-initial-password');
    }

    /**
     * Apply the new password, clear the flag and audit the change atomically.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(ChangeInitialPasswordRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($user, $validated): void {
            $user->forceFill([
                // Same pattern as PasswordController: Hash::make() plus the
                // model's 'hashed' cast (which keeps an already-hashed value).
                'password' => Hash::make($validated['password']),
                'must_change_password' => false,
            ])->save();

            // Mandatory audit, inside the transaction: the row records the
            // patient account and the linked patients row, never the old or
            // the new password, hash, or any session credential.
            AuditLog::create([
                'user_id' => $user->id,
                'patient_id' => $this->linkedPatientId($user),
                'action' => 'PATIENT_INITIAL_PASSWORD_CHANGED',
                'module' => 'PATIENT',
                'description' => sprintf(
                    'Patient login account (user ID %d) completed the mandatory initial password change.',
                    $user->id
                ),
            ]);
        });

        // Session fixation defence: rotate the session id only after the
        // transaction has committed, so a rolled-back change can never leave
        // the patient holding a new password under an old session id.
        $request->session()->regenerate();

        return redirect()
            ->route('patient.portal.coming-soon')
            ->with('status', 'Your password has been changed. You can now continue to the Patient Portal.');
    }

    /**
     * The patients row this account belongs to, for audit attribution.
     * Archived records still resolve (withTrashed) so the audit entry keeps
     * pointing at the right patient; NULL only for an unlinked account.
     */
    private function linkedPatientId(User $user): ?int
    {
        return Patient::withTrashed()
            ->where('user_id', $user->id)
            ->value('id');
    }
}
