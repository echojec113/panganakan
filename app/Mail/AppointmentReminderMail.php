<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Appointment $appointment,
        public string $reminderType,
    ) {
    }

    public function build(): self
    {
        $label = match ($this->reminderType) {
            'one_week' => 'One Week',
            'three_days' => 'Three Day',
            'two_hours' => 'Two Hour',
            default => 'Appointment',
        };

        return $this->subject($label . ' Appointment Reminder')
            ->view('emails.appointment_reminder');
    }
}
