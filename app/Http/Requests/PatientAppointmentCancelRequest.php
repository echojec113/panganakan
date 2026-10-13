<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * DEPLA Phase 3D.2B - Patient Portal cancellation reason (used by BOTH
 * the direct pending cancellation and the confirmed cancellation
 * request; the two flows are separated by route + service status guard,
 * never by a client-supplied field).
 *
 * SECURITY: this request NEVER validates or exposes a patient_id,
 * appointment status, source, confirmed schedule or reviewed field -
 * the appointment is resolved server-side from the authenticated
 * account's own patientAccount link (scoped query, foreign id = 404)
 * and every server-owned column is asserted inside
 * AppointmentManagementService under lockForUpdate. A forged reason is
 * the ONLY input this class accepts, matching the staff
 * AppointmentCancelRequest reason rules exactly (3-1000) so the two
 * surfaces cannot drift.
 *
 * Phase 3D.4B (P2): this request also CARD-SCOPES its own validation
 * failure (see failedValidation), because both cancellation flows share
 * the `reason` field name on a page that renders many cards.
 */
class PatientAppointmentCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->role === 'patient';
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'A cancellation reason is required.',
            'reason.string' => 'The cancellation reason must be text.',
            'reason.min' => 'The cancellation reason must be at least 3 characters.',
            'reason.max' => 'The cancellation reason may not be longer than 1000 characters.',
        ];
    }

    /**
     * DEPLA Phase 3D.4B (P2) - CARD-SCOPE + WHITELIST the failure.
     *
     * The portal page renders MANY appointment cards and both
     * cancellation forms post a field named `reason`, so a bare
     * `withErrors(['reason' => ...])` would repaint the refusal (and the
     * typed reason, through the flashed old input) onto every card.
     *
     * DEFENSE NOTES
     * - The scope is derived from the ROUTE (parameter + name), which the
     *   server fully controls; a client cannot aim a failure at somebody
     *   else's card and cannot forge the marker.
     * - parent::failedValidation() is REPLACED (not wrapped) because the
     *   framework's default redirect calls withInput() on the whole
     *   request, which parks every submitted key except the three
     *   password fields in the session. Only the reason this form
     *   actually posts is re-flashed, so a forged POST appending a
     *   password, patient_id or status field can never enter the session.
     * - The redirect target and the error bag are identical to the
     *   framework default (getRedirectUrl() honours the `from()` URL,
     *   redirect()/redirectRoute properties and the previous URL).
     * - Nothing is flashed for an unexpected route name or a
     *   non-numeric id: the failure simply falls back to the previous
     *   unscoped behaviour.
     */
    protected function failedValidation(Validator $validator): void
    {
        $this->flashCancellationFailureScope();

        throw new HttpResponseException(
            redirect()
                ->to($this->getRedirectUrl())
                ->withInput(array_intersect_key($this->all(), ['reason' => true]))
                ->withErrors($validator->errors(), $this->errorBag)
        );
    }

    private function flashCancellationFailureScope(): void
    {
        $route = $this->route();

        $form = match ($route?->getName()) {
            'patient.appointments.cancel' => 'cancel',
            'patient.appointments.cancellationRequest' => 'cancellation-request',
            default => null,
        };

        if ($form === null) {
            return;
        }

        $appointmentId = $route?->parameter('appointmentId');

        if (! is_numeric($appointmentId)) {
            return;
        }

        session()->flash('errorAppointmentId', (int) $appointmentId);
        session()->flash('errorForm', $form);
    }
}
