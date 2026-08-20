<?php

namespace App\Notifications;

use App\Models\PrenatalVisit;
use Illuminate\Notifications\Notification;

class PendingRepeatBloodPressureNotification extends Notification
{
    public function __construct(public PrenatalVisit $visit)
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
        $patient = $this->visit->patient;

        return [
            'type' => 'BP_PENDING_REPEAT',
            'title' => 'Repeat BP Required',
            'message' => trim(
                $patient->first_name . ' ' . $patient->last_name
            ) . ' is due for a repeat blood pressure measurement to verify the recorded finding.',
            'action_label' => 'View patient',
            'destination' => [
                'route' => 'patients.show',
                'parameters' => ['patient' => $patient->id],
            ],
        ];
    }
}