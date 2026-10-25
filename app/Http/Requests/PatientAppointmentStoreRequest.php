<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Services\AppointmentManagementService;
use App\Services\ClinicAvailabilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as BaseValidator;

/**
 * DEPLA Phase 3B.3 - Patient Portal online appointment request submission.
 *
 * SECURITY: this request NEVER validates or exposes a patient_id, user_id,
 * status, source, confirmed_date, confirmed_time, reviewed_by or
 * reviewed_at field - the patient identity is resolved server-side from
 * the authenticated account (PatientAppointmentController::resolveOwnPatient),
 * and status/source/review metadata are assigned exclusively inside
 * AppointmentManagementService::createOnlineRequest. Forged values posted
 * alongside the real fields are ignored by construction (never read).
 *
 * Schedule rules mirror the Admin/Staff path through the SAME shared
 * building blocks: AppointmentConfirmRequest::clinicHoursRules() (split
 * array elements - a piped string inside an array is silently ignored by
 * Laravel's parser) and AppointmentManagementService::pastScheduleIssue()
 * (Asia/Manila, full local date+time compare). The service repeats both
 * at the write boundary for defence in depth.
 *
 * Type-conditional time rules: checkup and counseling REQUIRE a time
 * inside the provisional clinic hours; a delivery request may be
 * date-only (time nullable, 24/7) - matching the staff confirm/walk-in
 * requests exactly so the two paths can never drift.
 */
class PatientAppointmentStoreRequest extends FormRequest
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
        $requiresTime = in_array($this->input('appointment_type'), [
            Appointment::TYPE_CHECKUP,
            Appointment::TYPE_COUNSELING,
        ], true);

        return [
            'appointment_type' => ['required', 'string', Rule::in(Appointment::ROUTINE_BOOKABLE_TYPES)],
            'requested_date' => ['required', 'string', 'date_format:Y-m-d'],
            'requested_time' => $requiresTime
                ? array_merge(['required', 'string', 'date_format:H:i'], AppointmentConfirmRequest::clinicHoursRules())
                : ['nullable', 'string', 'date_format:H:i'],
        ];
    }

    public function withValidator(BaseValidator $validator): void
    {
        $validator->after(function (BaseValidator $validator) {
            // Phase 2: slot-grid alignment for timed types (early field
            // feedback - the service repeats assertSlotStart() at the
            // write boundary). Delivery requests are date-only: any
            // submitted time is ignored by the service, so skip.
            $requiresTime = in_array($this->input('appointment_type'), [
                Appointment::TYPE_CHECKUP,
                Appointment::TYPE_COUNSELING,
            ], true);

            if ($requiresTime && ! $validator->errors()->has('requested_time')) {
                $slotIssue = ClinicAvailabilityService::slotStartIssue(
                    is_string($this->input('requested_time')) ? $this->input('requested_time') : null
                );

                if ($slotIssue !== null) {
                    $validator->errors()->add('requested_time', $slotIssue);
                }
            }

            // Past-schedule rule (defence in depth - the service repeats
            // this check at the write boundary for direct callers).
            if (! $validator->errors()->hasAny(['requested_date', 'requested_time'])) {
                $issue = AppointmentManagementService::pastScheduleIssue(
                    (string) $this->input('requested_date'),
                    $this->input('requested_time')
                );

                if ($issue !== null) {
                    $field = $issue['scope'] === 'time' ? 'requested_time' : 'requested_date';
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
            'appointment_type.required' => 'Choose the appointment type.',
            'appointment_type.in' => 'Routine appointment booking currently supports checkups only.',
            'requested_date.required' => 'Choose your preferred appointment date.',
            'requested_date.date_format' => 'The preferred date must be a valid date (YYYY-MM-DD).',
            'requested_time.required' => 'A preferred time is required for checkup and counseling appointments.',
            'requested_time.date_format' => 'The preferred time must be a valid time (HH:MM).',
            'requested_time.after_or_equal' => 'Checkup and counseling appointments must start at or after 8:00 AM (Philippine clinic hours, provisional).',
            'requested_time.before' => 'Checkup and counseling appointments must start strictly before 5:00 PM (Philippine clinic hours, provisional).',
        ];
    }
}
