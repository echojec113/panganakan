<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Models\Patient;
use App\Services\AppointmentManagementService;
use App\Services\ClinicAvailabilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as BaseValidator;

/**
 * DEPLA Phase 3B.2B - Create a walk-in appointment for an EXISTING patient.
 *
 * source and status are NEVER read from the request: the service always
 * stores source = walk_in and status = confirmed with a validated
 * schedule. patient_id must reference a live (non-archived) patients row
 * (Rule::exists would also match soft-deleted rows, hence the explicit
 * withTrashed check). Checkup/counseling require a time inside the
 * provisional clinic hours; delivery requests may be date-only (24/7).
 */
class AppointmentWalkInStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role, ['admin', 'staff'], true);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $requiresTime = in_array($this->input('appointment_type'), [
            Appointment::TYPE_CHECKUP,
            Appointment::TYPE_COUNSELING,
        ], true);

        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'appointment_type' => ['required', 'string', Rule::in(Appointment::TYPES)],
            'appointment_date' => ['required', 'string', 'date_format:Y-m-d'],
            'appointment_time' => $requiresTime
                ? array_merge(['required', 'string', 'date_format:H:i'], AppointmentConfirmRequest::clinicHoursRules())
                : ['nullable', 'string', 'date_format:H:i'],
        ];
    }

    public function withValidator(BaseValidator $validator): void
    {
        $validator->after(function (BaseValidator $validator) {
            $patientId = $this->input('patient_id');

            if ($patientId !== null && ! $validator->errors()->has('patient_id')) {
                $patient = Patient::withTrashed()->find($patientId);

                if (! $patient) {
                    $validator->errors()->add('patient_id', 'The selected patient record does not exist.');
                } elseif ($patient->trashed()) {
                    $validator->errors()->add('patient_id', 'Archived patients cannot be given walk-in appointments.');
                }
            }

            // Phase 2: slot-grid alignment for timed types (early field
            // feedback - the service repeats assertSlotStart() at the
            // write boundary). Delivery requests are date-only.
            $requiresTime = in_array($this->input('appointment_type'), [
                Appointment::TYPE_CHECKUP,
                Appointment::TYPE_COUNSELING,
            ], true);

            if ($requiresTime && ! $validator->errors()->has('appointment_time')) {
                $slotIssue = ClinicAvailabilityService::slotStartIssue(
                    is_string($this->input('appointment_time')) ? $this->input('appointment_time') : null
                );

                if ($slotIssue !== null) {
                    $validator->errors()->add('appointment_time', $slotIssue);
                }
            }

            // Past-schedule rule (defence in depth - the service repeats
            // this check at the write boundary for direct callers).
            if (! $validator->errors()->hasAny(['appointment_date', 'appointment_time'])) {
                $issue = AppointmentManagementService::pastScheduleIssue(
                    (string) $this->input('appointment_date'),
                    $this->input('appointment_time')
                );

                if ($issue !== null) {
                    $field = $issue['scope'] === 'time' ? 'appointment_time' : 'appointment_date';
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
            'patient_id.required' => 'Select an existing patient.',
            'patient_id.integer' => 'Select an existing patient.',
            'patient_id.exists' => 'The selected patient record does not exist.',
            'appointment_type.required' => 'Choose the appointment type.',
            'appointment_type.in' => 'Choose a valid appointment type.',
            'appointment_date.required' => 'Choose the appointment date.',
            'appointment_date.date_format' => 'The appointment date must be a valid date (YYYY-MM-DD).',
            'appointment_time.required' => 'An appointment time is required for checkup and counseling appointments.',
            'appointment_time.date_format' => 'The appointment time must be a valid time (HH:MM).',
            'appointment_time.after_or_equal' => 'Checkup and counseling appointments must start at or after 8:00 AM (Philippine clinic hours, provisional).',
            'appointment_time.before' => 'Checkup and counseling appointments must start strictly before 5:00 PM (Philippine clinic hours, provisional).',
        ];
    }
}
