<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\ClinicAvailabilityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator as BaseValidator;

/**
 * DEPLA Phase 3 - Admin closure creation (one of three form modes).
 *
 * The form submits a `mode` plus mode-scoped fields; the controller
 * normalizes every mode into one or more canonical closure rows through
 * ClinicAvailabilityService (windowsFromSlotStarts + validateClosureInput),
 * so this request owns only the SHAPE. Cross-field rules (366-day range,
 * partial-window alignment, all_day time nullability) are re-validated by
 * the service at the write boundary - defence in depth, never duplicated
 * logic.
 *
 * Authorization is the second independent boundary on top of the 'auth' +
 * 'clinic' group and AdminMiddleware (three-layer pattern).
 */
class AdminClinicAvailabilityStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && ! $user->trashed()
            && $user->role === 'admin';
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', 'in:single_day,date_range,slots'],
            'date' => ['nullable', 'string', 'date_format:Y-m-d'],
            'start_date' => ['nullable', 'string', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'string', 'date_format:Y-m-d'],
            'slot_starts' => ['nullable', 'array', 'min:1', 'max:18'],
            'slot_starts.*' => ['required', 'string', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:255'],
            'confirm_affected' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(BaseValidator $validator): void
    {
        $validator->after(function (BaseValidator $validator) {
            $mode = (string) $this->input('mode');

            if (($mode === 'single_day' || $mode === 'slots') && ! $validator->errors()->has('date')) {
                if (! is_string($this->input('date')) || trim($this->input('date')) === '') {
                    $validator->errors()->add('date', $mode === 'slots'
                        ? 'Choose the date for the selected slots.'
                        : 'Choose the date to close.');
                }
            }

            if ($mode === 'date_range') {
                if ((! is_string($this->input('start_date')) || trim($this->input('start_date')) === '')
                    && ! $validator->errors()->has('start_date')) {
                    $validator->errors()->add('start_date', 'Choose the range start date.');
                }

                if ((! is_string($this->input('end_date')) || trim($this->input('end_date')) === '')
                    && ! $validator->errors()->has('end_date')) {
                    $validator->errors()->add('end_date', 'Choose the range end date.');
                }

                // 366-day inclusive maximum (approved rule) - the same
                // boundary validateClosureInput enforces at the write
                // boundary; surfaced early for field-level feedback.
                $start = $this->input('start_date');
                $end = $this->input('end_date');

                if (is_string($start) && is_string($end)
                    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) === 1
                    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) === 1
                    && $end >= $start
                    && ! $validator->errors()->hasAny(['start_date', 'end_date'])) {
                    $span = (int) \Carbon\Carbon::createFromFormat('Y-m-d', $start)
                        ->startOfDay()
                        ->diffInDays(
                            \Carbon\Carbon::createFromFormat('Y-m-d', $end)->startOfDay()
                        ) + 1;

                    if ($span > ClinicAvailabilityService::MAX_CLOSURE_RANGE_DAYS) {
                        $validator->errors()->add('end_date', sprintf(
                            'A closure may not span more than %d calendar days (inclusive).',
                            ClinicAvailabilityService::MAX_CLOSURE_RANGE_DAYS
                        ));
                    }
                }
            }

            if ($mode === 'slots') {
                if (! $validator->errors()->has('slot_starts')) {
                    $starts = $this->input('slot_starts');

                    if (! is_array($starts) || $starts === []) {
                        $validator->errors()->add('slot_starts', 'Select at least one time slot to close.');
                    } else {
                        // Grid alignment (early field feedback; the
                        // service assertSlotStart repeats it per window).
                        foreach (array_values($starts) as $index => $slotStart) {
                            $issue = ClinicAvailabilityService::slotStartIssue(
                                is_string($slotStart) ? $slotStart : null
                            );

                            if ($issue !== null) {
                                $validator->errors()->add("slot_starts.{$index}", $issue);
                            }
                        }
                    }
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
            'mode.required' => 'Choose what to close.',
            'mode.in' => 'Choose a valid closure type.',
            'date.date_format' => 'The date must be a valid date (YYYY-MM-DD).',
            'start_date.date_format' => 'The start date must be a valid date (YYYY-MM-DD).',
            'end_date.date_format' => 'The end date must be a valid date (YYYY-MM-DD).',
            'end_date.after_or_equal' => 'The end date cannot precede the start date.',
            'slot_starts.array' => 'Select at least one time slot to close.',
            'slot_starts.min' => 'Select at least one time slot to close.',
            'slot_starts.max' => 'A single day has at most 18 slots.',
            'slot_starts.*.date_format' => 'Each selected slot must be a valid time (HH:MM).',
            'reason.max' => 'The reason may not be longer than 255 characters.',
        ];
    }
}
