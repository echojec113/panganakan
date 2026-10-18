<?php

namespace App\Http\Requests;

use App\Models\Appointment;
use App\Services\AppointmentManagementService;
use App\Services\ClinicAvailabilityService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as BaseValidator;

/**
 * DEPLA Phase 3B.2B - Confirm a pending appointment.
 *
 * The appointment type is read from the ROUTED appointment (never from
 * request input), so the type-conditional schedule rules cannot be
 * forged. Service-level re-validation repeats the same rules for direct
 * callers (defence in depth, following the Phase 2B pattern).
 *
 * Past-schedule rule: the confirmed schedule may never be in the past
 * (Asia/Manila, compared at the write boundary by the service; repeated
 * here for early field-level feedback). On validation failure the
 * appointment id is flashed so the calendar can reopen the details modal
 * with the error visible.
 */
class AppointmentConfirmRequest extends FormRequest
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
        $appointment = $this->route('appointment');
        $type = $appointment instanceof Appointment ? $appointment->appointment_type : null;
        $requiresTime = $type !== null && $type !== Appointment::TYPE_DELIVERY_REQUEST;

        return [
            'confirmed_date' => ['required', 'string', 'date_format:Y-m-d'],
            'confirmed_time' => $requiresTime
                ? array_merge(['required', 'string', 'date_format:H:i'], self::clinicHoursRules())
                : ['nullable', 'string', 'date_format:H:i'],
        ];
    }

    public function withValidator(BaseValidator $validator): void
    {
        $validator->after(function (BaseValidator $validator) {
            // Phase 2: slot-grid alignment for timed types (early field
            // feedback - the service repeats assertSlotStart() at the
            // write boundary). The appointment type comes from the ROUTED
            // row so it cannot be forged. Delivery requests are date-only.
            $appointment = $this->route('appointment');
            $requiresTime = $appointment instanceof Appointment
                && $appointment->appointment_type !== Appointment::TYPE_DELIVERY_REQUEST;

            if ($requiresTime && ! $validator->errors()->has('confirmed_time')) {
                $slotIssue = ClinicAvailabilityService::slotStartIssue(
                    is_string($this->input('confirmed_time')) ? $this->input('confirmed_time') : null
                );

                if ($slotIssue !== null) {
                    $validator->errors()->add('confirmed_time', $slotIssue);
                }
            }

            // Past-schedule rule (defence in depth - the service repeats
            // this check at the write boundary for direct callers).
            if (! $validator->errors()->hasAny(['confirmed_date', 'confirmed_time'])) {
                $issue = AppointmentManagementService::pastScheduleIssue(
                    (string) $this->input('confirmed_date'),
                    $this->input('confirmed_time')
                );

                if ($issue !== null) {
                    $field = $issue['scope'] === 'time' ? 'confirmed_time' : 'confirmed_date';
                    $validator->errors()->add($field, $issue['message']);
                }
            }
        });
    }

    /**
     * Flash the routed appointment id on failure so the calendar page can
     * reopen the details modal with the validation error visible (the
     * walk-in modal already auto-reopens through the shared error bag).
     */
    protected function failedValidation(Validator $validator): void
    {
        $appointment = $this->route('appointment');

        if ($appointment instanceof Appointment) {
            $this->session()->flash('errorAppointmentId', $appointment->id);
        }

        parent::failedValidation($validator);
    }

    /**
     * Provisional clinic-hours rules: at or after 08:00 and STRICTLY
     * before 17:00 (Mon-Sun). Documented as provisional pending clinic
     * confirmation of whether 5 PM is a start time or the closing time.
     *
     * Returns an ARRAY of separate rules on purpose: a single piped
     * string ('after_or_equal:08:00|before:17:00') placed inside a rules
     * array is silently ignored by Laravel's rule parser (pipes are only
     * split at the top level of a string rule, not within array
     * elements), which made this validation dead code until now - the
     * service check remained the only enforcer. Callers must spread the
     * result into their rule arrays.
     *
     * @return array<int, string>
     */
    public static function clinicHoursRules(): array
    {
        return [
            'after_or_equal:' . AppointmentManagementService::CLINIC_OPEN_TIME,
            'before:' . AppointmentManagementService::CLINIC_LAST_START_TIME,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmed_date.required' => 'Choose the confirmed appointment date.',
            'confirmed_date.date_format' => 'The confirmed date must be a valid date (YYYY-MM-DD).',
            'confirmed_time.required' => 'An appointment time is required for checkup and counseling appointments.',
            'confirmed_time.date_format' => 'The confirmed time must be a valid time (HH:MM).',
            'confirmed_time.after_or_equal' => 'Checkup and counseling appointments must start at or after 8:00 AM (Philippine clinic hours, provisional).',
            'confirmed_time.before' => 'Checkup and counseling appointments must start strictly before 5:00 PM (Philippine clinic hours, provisional).',
        ];
    }
}
