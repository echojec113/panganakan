<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\AppointmentReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Send scheduled email reminders for confirmed appointments';

    public function handle(AppointmentReminderService $reminders): int
    {
        $timezone = AppointmentReminderService::TIMEZONE;
        $today = Carbon::now($timezone)->toDateString();
        $lastDate = Carbon::now($timezone)->addDays(7)->toDateString();

        $appointments = Appointment::query()
            ->with('patient')
            ->where('status', Appointment::STATUS_CONFIRMED)
            ->whereNull('prenatal_visit_id')
            ->whereNotNull('confirmed_date')
            ->whereNotNull('confirmed_time')
            ->whereBetween('confirmed_date', [$today, $lastDate])
            ->orderBy('id')
            ->get();

        foreach ($appointments as $appointment) {
            $reminders->process($appointment);
        }

        $this->info('Appointment reminders processed: ' . $appointments->count());

        return self::SUCCESS;
    }
}
