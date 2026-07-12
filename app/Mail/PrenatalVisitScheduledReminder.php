<?php

namespace App\Mail;

use App\Models\Patient;
use App\Models\PrenatalVisit;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PrenatalVisitScheduledReminder extends Mailable
{
    use Queueable, SerializesModels;

    public $patient;
    public $visit;
    public $type;

    public function __construct(Patient $patient, PrenatalVisit $visit, string $type)
    {
        $this->patient = $patient;
        $this->visit = $visit;
        $this->type = $type;
    }

    public function build()
    {
        $subject = $this->type === 'tomorrow'
            ? 'Prenatal Visit Reminder - Tomorrow'
            : 'Prenatal Visit Reminder - Today';

        return $this->subject($subject)
            ->view('emails.prenatal_visit_scheduled_reminder');
    }
}
