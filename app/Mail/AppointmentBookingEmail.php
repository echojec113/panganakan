<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentBookingEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Appointment $appointment)
    {
    }

    public function build(): self
    {
        $subject = $this->appointment->status === Appointment::STATUS_CONFIRMED
            ? 'Appointment Confirmed'
            : 'Appointment Request Received';

        return $this->subject($subject)->view('emails.appointment_booking');
    }
}
