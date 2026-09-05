<?php

namespace App\Notifications;

use App\Models\PrenatalVisit;
use Illuminate\Notifications\Notification;

class UrgentBloodPressureNotification extends Notification
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
     * Structured payload stored in the notifications.data JSON column. The
     * destination keeps a route name + parameters so the link is always built
     * at render time with the application's current route/URL configuration.
     *
     * @return array<string, mixed>
     */
    public function toDatabase($notifiable): array
    {
        $patient = $this->visit->patient;

        return [
            'type' => 'URGENT_BP',
            'title' => 'Urgent BP Review',
            'message' => trim(
                $patient->first_name . ' ' . $patient->last_name
            ) . ' has an urgent blood pressure finding (BP-URG) that requires immediate clinical review.',
            'action_label' => 'View patient',
            'destination' => [
                'route' => 'patients.show',
                'parameters' => ['patient' => $patient->id],
            ],
        ];
    }
}