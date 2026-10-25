<?php

namespace App\Services;

use App\Mail\AppointmentBookingEmail;
use App\Models\Appointment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AppointmentBookingEmailService
{
    /** Send the patient a receipt after the online booking transaction commits. */
    public function sendReceipt(Appointment $appointment): void
    {
        $current = Appointment::query()
            ->with('patient')
            ->whereKey($appointment->id)
            ->where('source', Appointment::SOURCE_ONLINE)
            ->whereNull('prenatal_visit_id')
            ->first();

        $email = $current?->patient?->email;
        if (! $current || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::notice('Appointment booking email skipped: patient email unavailable.', [
                'appointment_id' => $appointment->id,
            ]);

            return;
        }

        try {
            Mail::to($email)->send(new AppointmentBookingEmail($current));
        } catch (Throwable $exception) {
            // The appointment is already committed; mail transport failure
            // must not turn a successful booking into a failed web request.
            Log::error('Appointment booking email failed.', [
                'appointment_id' => $current->id,
                'exception' => $exception::class,
            ]);
        }
    }
}
