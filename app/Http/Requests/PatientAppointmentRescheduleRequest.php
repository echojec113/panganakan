<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Services\AppointmentManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator as BaseValidator;

/**
 * DEPLA Phase 3D.3B Step 1 - Patient Portal reschedule request submission
 * against the patient's OWN CONFIRMED ONLINE appointment.
 *
 * SECURITY: this request NEVER validates or exposes a patient_id,
 * appointment status/source, confirmed schedule or reviewed field. The
 * appointment type used for the conditional time rules is resolved
 * SERVER-SIDE from the authenticated account's own patientAccount link
 * (same ownership chain the controller repeats; an unresolvable or
 * foreign appointment leaves the time rule lenient so the controller's
 * own-relation firstOrFail owns the 404). The patient identity, the
 * type=reschedule vocabulary and every server-owned column are assigned
 * exclusively inside AppointmentManagementService::
 * requestAppointmentReschedule under lockForUpdate.
 *
 * Schedule rules mirror the create/walk-in/confirm surfaces through the
 * SAME shared building blocks: AppointmentConfirmRequest::
 * clinicHoursRules() (split array elements - a piped string inside an
 * array is silently ignored by Laravel's parser) and
 * AppointmentManagementService::pastScheduleIssue() (Asia/Manila, full
 * local date+time compare). The service repeats both at the write
 * boundary for defence in depth, plus the one-pending, cutoff,
 * identical-to-current and active-duplicate collision guards, which
 * stay service-authoritative (in-transaction) and are deliberately NOT
 * duplicated here.
 *
 * Type-conditional time rules: checkup and counseling REQUIRE a proposed
 * time inside the provisional clinic hours; a delivery proposal is
 * date-only (time nullable and IGNORED - approved decision K5 - the
 * service stores NULL regardless of any submitted value). The reason is
 * OPTIONAL (approved rule 8): null/blank stores NULL; when supplied it
 * must be 3-1000 characters.
 */
class PatientAppointmentRescheduleRequest extends FormRequest
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
        $appointment = $this->targetAppointment();

        $proposedTimeRules = match (true) {
            // Unresolvable/foreign appointment: keep lenient; the
            // controller's own-relation firstOrFail owns the 404.
            $appointment === null => ['nullable'],
            // Delivery proposals are date-only (K5); any submitted
            // value is ignored by the service.
            $appointment->appointment_type === Appointment::TYPE_DELIVERY_REQUEST => ['nullable'],
            default => array_merge(
                ['required', 'string', 'date_format:H:i'],
                AppointmentConfirmRequest::clinicHoursRules()
            ),
        };

        return [
            'proposed_date' => ['required', 'string', 'date_format:Y-m-d'],
            'proposed_time' => $proposedTimeRules,
            'reason' => ['nullable', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function withValidator(BaseValidator $validator): void
    {
        $validator->after(function (BaseValidator $validator) {
            // Past-schedule rule on the PROPOSED schedule (defence in
            // depth - the service repeats this at the write boundary
            // for direct callers). A delivery proposal is date-only
            // (K5): any submitted time is ignored there, so it must be
            // ignored here too or the form could refuse something the
            // service accepts.
            if (! $validator->errors()->hasAny(['proposed_date', 'proposed_time'])) {
                $appointment = $this->targetAppointment();
                $effectiveTime = $appointment !== null
                    && $appointment->appointment_type === Appointment::TYPE_DELIVERY_REQUEST
                        ? null
                        : $this->input('proposed_time');

                $issue = AppointmentManagementService::pastScheduleIssue(
                    (string) $this->input('proposed_date'),
                    is_string($effectiveTime) ? $effectiveTime : null
                );

                if ($issue !== null) {
                    $field = $issue['scope'] === 'time' ? 'proposed_time' : 'proposed_date';
                    $validator->errors()->add($field, $issue['message']);
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proposed_date.required' => 'Choose your preferred rescheduled date.',
            'proposed_date.date_format' => 'The proposed date must be a valid date (YYYY-MM-DD).',
            'proposed_time.required' => 'A proposed time is required for checkup and counseling appointments.',
            'proposed_time.date_format' => 'The proposed time must be a valid time (HH:MM).',
            'proposed_time.after_or_equal' => 'Checkup and counseling appointments must start at or after 8:00 AM (Philippine clinic hours, provisional).',
            'proposed_time.before' => 'Checkup and counseling appointments must start strictly before 5:00 PM (Philippine clinic hours, provisional).',
            'reason.min' => 'The reschedule reason must be at least 3 characters when provided.',
            'reason.max' => 'The reschedule reason may not exceed 1000 characters.',
        ];
    }

    /**
     * Resolve the appointment this request targets through the
     * authenticated account's OWN patientAccount link - the exact
     * ownership chain PatientAppointmentController::resolveOwnPatient
     * uses. Returns null for clinic roles, unlinked accounts and
     * foreign/missing ids (the controller owns the 403/404).
     */
    private function targetAppointment(): ?Appointment
    {
        $user = $this->user();

        if ($user === null || $user->role !== 'patient') {
            return null;
        }

        $patient = $user->patientAccount;

        if ($patient === null) {
            return null;
        }

        return $patient->appointments()->find($this->route('appointmentId'));
    }
}
