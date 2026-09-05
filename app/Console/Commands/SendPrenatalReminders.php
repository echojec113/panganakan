<?php

namespace App\Console\Commands;

use App\Mail\PrenatalVisitScheduledReminder;
use App\Models\PrenatalVisit;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPrenatalReminders extends Command
{
    protected $signature = 'app:send-prenatal-reminders';

    protected $description = 'Send automated prenatal visit reminders for tomorrow and today';

    public function handle(): int
    {
        $today = Carbon::today();
        $tomorrow = Carbon::today()->addDay();

        $this->info('Processing prenatal reminders for: ' . $today->toDateString());

        // ======================
        // TOMORROW REMINDERS
        // ======================
        $tomorrowVisits = PrenatalVisit::whereDate('next_visit_date', $tomorrow->toDateString())
            ->whereNull('reminder_tomorrow_sent_at')
            ->whereHas('patient', function ($query) {
                $query->whereNotNull('email');
            })
            ->get();

        $this->info('Tomorrow reminders to send: ' . $tomorrowVisits->count());

        foreach ($tomorrowVisits as $visit) {
            $this->sendReminder($visit, 'tomorrow');
        }

        // ======================
        // TODAY REMINDERS
        // ======================
        $todayVisits = PrenatalVisit::whereDate('next_visit_date', $today->toDateString())
            ->whereNull('reminder_today_sent_at')
            ->whereHas('patient', function ($query) {
                $query->whereNotNull('email');
            })
            ->get();

        $this->info('Today reminders to send: ' . $todayVisits->count());

        foreach ($todayVisits as $visit) {
            $this->sendReminder($visit, 'today');
        }

        $this->info('Prenatal reminders processed successfully.');

        return Command::SUCCESS;
    }

    private function sendReminder(PrenatalVisit $visit, string $type): void
    {
        $patient = $visit->patient;

        if (!$patient || empty($patient->email)) {
            Log::warning("SCHEDULED REMINDER SKIPPED: Patient has no email. Visit ID: {$visit->id}");
            return;
        }

        $column = $type === 'tomorrow' ? 'reminder_tomorrow_sent_at' : 'reminder_today_sent_at';

        try {
            Mail::to($patient->email)
                ->send(new PrenatalVisitScheduledReminder($patient, $visit, $type));

            $visit->update([$column => now()]);

            Log::info("SCHEDULED REMINDER SENT: {$type} reminder for visit ID {$visit->id}, patient {$patient->email}");

        } catch (\Exception $e) {
            Log::error("SCHEDULED REMINDER FAILED: {$type} reminder for visit ID {$visit->id}: {$e->getMessage()}");
        }
    }
}
