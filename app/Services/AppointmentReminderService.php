<?php

namespace App\Services;

use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AppointmentReminderService
{
    public const TIMEZONE = AppointmentManagementService::CLINIC_TIMEZONE;

    /** A scheduler delay beyond this grace period is recorded as missed. */
    public const GRACE_MINUTES = 5;

    /** @var array<string, int> */
    private const LEAD_MINUTES = [
        'one_week' => 7 * 24 * 60,
        'three_days' => 3 * 24 * 60,
        'two_hours' => 2 * 60,
    ];

    public function process(Appointment $appointment): void
    {
        // Re-fetch immediately before delivery so cancellations, reschedules,
        // soft deletes, and prenatal links are revalidated at send time.
        $current = Appointment::query()
            ->with('patient')
            ->whereKey($appointment->id)
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->whereNull('prenatal_visit_id')
            ->whereNotNull('confirmed_date')
            ->whereNotNull('confirmed_time')
            ->first();

        if (! $current) {
            return;
        }

        $patient = $current->patient;
        $scheduledAt = Carbon::parse(
            $current->confirmed_date->toDateString() . ' ' . $current->confirmed_time,
            self::TIMEZONE
        );
        $now = Carbon::now(self::TIMEZONE);

        foreach (self::LEAD_MINUTES as $type => $leadMinutes) {
            $targetAt = $scheduledAt->copy()->subMinutes($leadMinutes);
            if ($now->lt($targetAt)) {
                continue;
            }

            $expiresAt = $targetAt->copy()->addMinutes(self::GRACE_MINUTES);
            if ($now->gt($expiresAt)) {
                $this->recordMissed($current, $type);
                continue;
            }

            if (! $patient || ! filter_var($patient->email, FILTER_VALIDATE_EMAIL)) {
                Log::notice('Appointment reminder skipped: patient email unavailable.', [
                    'appointment_id' => $current->id,
                    'reminder_type' => $type,
                ]);

                continue;
            }

            // The two-hour reminder must never be delivered after the visit
            // has started, even if the scheduler is running late.
            if ($type === 'two_hours' && $now->gte($scheduledAt)) {
                $this->recordMissed($current, $type);
                continue;
            }

            $this->sendOnce($current, $type);
        }
    }

    private function sendOnce(Appointment $appointment, string $type): void
    {
        $key = $this->key($appointment, $type);
        $now = Carbon::now(self::TIMEZONE);

        // The unique schedule/type key claims the reminder before delivery.
        // A failed send removes the claim so a later scheduler tick can retry.
        $claimed = DB::table('appointment_reminders')->insertOrIgnore([
            ...$key,
            'status' => 'sending',
            'attempted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($claimed === 0) {
            return;
        }

        try {
            // Re-read after taking the claim, immediately before the mail
            // call, to reject a schedule/status change that raced this tick.
            $current = Appointment::query()
                ->with('patient')
                ->whereKey($appointment->id)
                ->where('confirmed_date', $key['confirmed_date'])
                ->where('confirmed_time', $key['confirmed_time'])
                ->where('status', Appointment::STATUS_CONFIRMED)
                ->whereNull('prenatal_visit_id')
                ->first();

            if (! $current || ! $current->patient || ! filter_var($current->patient->email, FILTER_VALIDATE_EMAIL)) {
                DB::table('appointment_reminders')->where($key)->delete();

                return;
            }

            Mail::to($current->patient->email)->send(new AppointmentReminderMail($current, $type));

            DB::table('appointment_reminders')->where($key)->update([
                'status' => 'sent',
                'sent_at' => Carbon::now(self::TIMEZONE),
                'updated_at' => Carbon::now(self::TIMEZONE),
            ]);

            Log::info('Appointment reminder sent.', [
                'appointment_id' => $appointment->id,
                'reminder_type' => $type,
            ]);
        } catch (Throwable $exception) {
            DB::table('appointment_reminders')->where($key)->delete();

            Log::error('Appointment reminder delivery failed.', [
                'appointment_id' => $appointment->id,
                'reminder_type' => $type,
                'exception' => $exception::class,
            ]);
        }
    }

    private function recordMissed(Appointment $appointment, string $type): void
    {
        $now = Carbon::now(self::TIMEZONE);
        $inserted = DB::table('appointment_reminders')->insertOrIgnore([
            ...$this->key($appointment, $type),
            'status' => 'missed',
            'attempted_at' => $now,
            'sent_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted > 0) {
            Log::warning('Appointment reminder window missed.', [
                'appointment_id' => $appointment->id,
                'reminder_type' => $type,
            ]);
        }
    }

    /** @return array{appointment_id:int, reminder_type:string, confirmed_date:string, confirmed_time:string} */
    private function key(Appointment $appointment, string $type): array
    {
        return [
            'appointment_id' => $appointment->id,
            'reminder_type' => $type,
            'confirmed_date' => $appointment->confirmed_date->toDateString(),
            'confirmed_time' => $appointment->confirmed_time,
        ];
    }
}
