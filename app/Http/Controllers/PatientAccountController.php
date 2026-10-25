<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientAccountStoreRequest;
use App\Models\Patient;
use App\Models\User;
use App\Services\PatientAccountCreationService;
use App\Services\PatientAccountSetupEmailService;
use App\Services\PatientSetupTokenService;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * DEPLA Phase 1.5 — POST /patients/{patient}/account (+ resend).
 *
 * Email-only provisioning: the modal supplies the login email, the service
 * creates the account with an internal random password inside its existing
 * transaction, and only AFTER that transaction commits does this controller
 * generate the single-use setup token and send the setup email (an SMTP
 * call must never run inside an uncommitted database transaction).
 *
 * Email delivery failure never rolls back the committed account, the
 * ownership pointer, the pregnancy links or the Task B associations: the
 * account stays pending setup, a safe audit row and a staff-facing warning
 * are recorded, and the authorized Resend Setup Email action provides the
 * recovery path. No plaintext password, token or URL is ever logged or
 * flashed.
 */
class PatientAccountController extends Controller
{
    public function __construct(
        private PatientAccountCreationService $patientAccountCreationService,
        private PatientSetupTokenService $setupTokens,
        private PatientAccountSetupEmailService $setupEmail,
    ) {
    }

    public function store(PatientAccountStoreRequest $request, $patient): RedirectResponse
    {
        $record = Patient::withTrashed()->find($patient);

        if (! $record) {
            abort(404);
        }

        $validated = $request->validated();

        try {
            $user = $this->patientAccountCreationService->createForPatient(
                $record,
                $request->user(),
                $validated['email']
            );
        } catch (DomainException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        } catch (UniqueConstraintViolationException) {
            return back()->withErrors([
                'email' => 'This email is already used by an existing account, including deactivated accounts.',
            ]);
        }

        // The provisioning transaction has COMMITTED. Everything from here
        // is post-commit: a failure leaves the account intact and pending
        // setup, recoverable through the Resend Setup Email action.
        $sent = $this->setupEmail->send($user, $request->user());

        if (! $sent) {
            return redirect()
                ->route('patients.show', $record->id)
                ->with('warning', 'Patient login account created, but the setup email could not be sent. Use "Resend Setup Email" on this page to try again.');
        }

        return redirect()
            ->route('patients.show', $record->id)
            ->with('success', 'Patient login account created. A one-time setup email has been sent; the patient will choose their own password using the secure link (valid for 24 hours).');
    }

    /**
     * DEPLA Phase 1.5 — authorized Resend Setup Email for a still-pending
     * Patient Portal account. Generates a NEW token (invalidating the
     * previous one), respects the 60-second resend throttle, audits the
     * action and never creates a user, a pointer or a pregnancy link.
     */
    public function resendSetupEmail(Request $request, $patient): RedirectResponse
    {
        $record = Patient::find($patient);

        if (! $record) {
            abort(404);
        }

        $user = $record->loginAccount;

        // Independent boundary on top of the auth + clinic middleware group
        // (same doctrine as PatientAccountStoreRequest::authorize).
        $actor = $request->user();

        abort_unless(
            $actor instanceof User
                && ! $actor->trashed()
                && in_array($actor->role, ['admin', 'staff'], true),
            403
        );

        $back = redirect()->route('patients.show', $record->id);

        if (! $user || $user->trashed() || $user->role !== 'patient' || ! (bool) $user->must_change_password) {
            return $back->with('error', 'No pending setup email exists for this patient account.');
        }

        if ($this->setupTokens->recentlyCreated($user)) {
            return $back->with('error', 'A setup email was requested recently. Please wait a moment before trying again.');
        }

        $sent = $this->setupEmail->send($user, $actor, auditResend: true);

        if (! $sent) {
            return $back->with('warning', 'The setup email could not be sent. Please try again shortly.');
        }

        return $back->with('success', 'A new setup email has been sent. The previous setup link is no longer valid.');
    }

}
