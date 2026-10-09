<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatientAccountStoreRequest;
use App\Models\Patient;
use App\Services\PatientAccountCreationService;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * DEPLA Phase 2B — POST /patients/{patient}/account.
 *
 * Backend-only provisioning endpoint. There is deliberately no GET route, no
 * Blade form, no invitation/activation email and no automatic patient-to-user
 * matching. The service owns the entire write transaction; this controller
 * only resolves the target record, converts business refusals into safe
 * validation errors and issues the redirect.
 */
class PatientAccountController extends Controller
{
    public function __construct(
        private PatientAccountCreationService $patientAccountCreationService,
    ) {
    }

    public function store(PatientAccountStoreRequest $request, $patient)
    {
        $record = Patient::withTrashed()->find($patient);

        if (! $record) {
            abort(404);
        }

        $validated = $request->validated();

        try {
            $this->patientAccountCreationService->createForPatient(
                $record,
                $request->user(),
                $validated['email'],
                $validated['password']
            );
        } catch (DomainException $exception) {
            // Intentionally WITHOUT withInput(): flashing the raw request back
            // would put the submitted initial password into the session.
            return back()->withErrors(['status' => $exception->getMessage()]);
        } catch (UniqueConstraintViolationException) {
            return back()->withErrors([
                'email' => 'This email is already used by an existing account, including deactivated accounts.',
            ]);
        }

        return redirect()
            ->route('patients.show', $record->id)
            ->with('success', 'Patient login account created. The patient will be asked to change the password on first sign-in.');
    }
}
