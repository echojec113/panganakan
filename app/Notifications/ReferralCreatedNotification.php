<?php

namespace App\Notifications;

use App\Models\Referral;
use Illuminate\Notifications\Notification;

class ReferralCreatedNotification extends Notification
{
    public function __construct(public Referral $referral)
    {
    }

    /**
     * Database-backed notifications only. No mail, no realtime channels.
     */
    public function via($notifiable): array
    {
        return ['database'];
    }

    /**
     * Structured payload stored in the notifications.data JSON column.
     *
     * @return array<string, mixed>
     */
    public function toDatabase($notifiable): array
    {
        $patient = $this->referral->patient;
        $name = trim(($patient->first_name ?? '') . ' ' . ($patient->last_name ?? ''));

        return [
            'type' => 'REFERRAL_CREATED',
            'title' => 'New Referral',
            'message' => 'A referral for ' . ($name !== '' ? $name : 'Patient #' . $this->referral->patient_id)
                . ' to ' . ($this->referral->referred_to ?? 'a facility') . ' was created.',
            'action_label' => 'View referral',
            'destination' => [
                'route' => 'referrals.show',
                'parameters' => ['id' => $this->referral->id],
            ],
        ];
    }
}