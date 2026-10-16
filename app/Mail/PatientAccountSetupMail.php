<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * DEPLA Phase 1.5 — first-time Patient Portal password setup email.
 *
 * Carries ONLY the namespaced setup link. No plaintext password (the patient
 * chooses their own), no password hash, no clinical or pregnancy data, no
 * sensitive information in the subject. The URL is built with route(), so
 * the configured APP_URL (HTTPS in production) is always used - nothing is
 * hardcoded. Sent synchronously through the configured Laravel mailer
 * (Brevo-compatible), exactly like the existing DEPLA mails.
 */
class PatientAccountSetupMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    public $setupUrl;

    public function __construct(User $user, string $token)
    {
        $this->user = $user;

        // Guest route; query-string email lets the setup endpoints resolve
        // the account exactly like the ordinary reset links do.
        $this->setupUrl = route('patient.setup.show', [
            'token' => $token,
            'email' => $user->email,
        ]);
    }

    public function build()
    {
        return $this->subject('DEPLA Family Care - Patient Portal Account Setup')
            ->view('emails.patient_account_setup');
    }
}
