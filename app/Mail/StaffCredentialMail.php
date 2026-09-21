<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StaffCredentialMail extends Mailable
{
    use Queueable, SerializesModels;

    public $staff;

    public $plainTextPassword;

    public function __construct(User $staff, string $plainTextPassword)
    {
        $this->staff = $staff;
        $this->plainTextPassword = $plainTextPassword;
    }

    public function build()
    {
        return $this->subject('DEPLA Family Care - Staff Account Credentials')
            ->view('emails.staff_credentials');
    }
}
