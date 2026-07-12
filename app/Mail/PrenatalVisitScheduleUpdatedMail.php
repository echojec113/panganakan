<?php

namespace App\Mail;

use App\Models\Patient;
use App\Models\PrenatalVisit;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PrenatalVisitScheduleUpdatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $patient;
    public $visit;

    public function __construct(Patient $patient, PrenatalVisit $visit)
    {
        $this->patient = $patient;
        $this->visit = $visit;
    }

    public function build()
    {
       return $this->subject('Prenatal Follow-up Schedule Updated')
        ->view('emails.prenatal_visit_schedule_updated');
    }
}
