<?php

namespace App\Services;

use App\Mail\PatientAccountSetupMail;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Sends first-time setup links after account provisioning has committed. */
class PatientAccountSetupEmailService
{
    public function __construct(private readonly PatientSetupTokenService $setupTokens)
    {
    }

    /**
     * @return bool Whether the synchronous mail transport accepted the email.
     */
    public function send(User $user, User $actor, bool $auditResend = false): bool
    {
        try {
            $token = $this->setupTokens->create($user);
            Mail::to($user->email)->send(new PatientAccountSetupMail($user, $token));
        } catch (Throwable $exception) {
            // Provider exception messages can contain credentials or link data.
            Log::error('PATIENT SETUP EMAIL FAILED', [
                'user_id' => $user->id,
                'actor_id' => $actor->id,
                'exception' => $exception::class,
            ]);

            try {
                AuditLog::create([
                    'user_id' => $actor->id,
                    'patient_id' => $user->patientAccount()->value('id'),
                    'action' => 'PATIENT_SETUP_EMAIL_FAILED',
                    'module' => 'PATIENT',
                    'description' => sprintf(
                        'Patient portal setup email for user ID %d could not be sent (%s). The account remains pending setup.',
                        $user->id,
                        $exception::class
                    ),
                ]);
            } catch (Throwable $auditException) {
                Log::error('PATIENT SETUP EMAIL FAILURE AUDIT FAILED', [
                    'user_id' => $user->id,
                    'actor_id' => $actor->id,
                    'exception' => $auditException::class,
                ]);
            }

            return false;
        }

        if ($auditResend) {
            AuditLog::create([
                'user_id' => $actor->id,
                'patient_id' => $user->patientAccount()->value('id'),
                'action' => 'PATIENT_SETUP_EMAIL_RESENT',
                'module' => 'PATIENT',
                'description' => sprintf(
                    'Patient portal setup email resent for user ID %d. The previous setup link was invalidated.',
                    $user->id
                ),
            ]);
        }

        return true;
    }
}
