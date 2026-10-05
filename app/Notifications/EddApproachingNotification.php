<?php

namespace App\Notifications;

use App\Models\Patient;
use Illuminate\Notifications\Notification;

class EddApproachingNotification extends Notification
{
    /**
     * @param  string  $eventKey  Stable logical identity of this pregnancy
     *                            event (edd-approaching:{patient}) used for
     *                            duplicate prevention - one alert per
     *                            pregnancy.
     */
    public function __construct(public Patient $patient, public string $eventKey)
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
        $patient = $this->patient;
        $name = trim($patient->first_name . ' ' . $patient->last_name);
        $formattedEdd = $patient->edd
            ? $patient->edd->format('M d, Y')
            : 'Not recorded';

        return [
            'type' => 'EDD_APPROACHING',
            'title' => 'EDD Approaching',
            'message' => ($name !== '' ? $name : 'Patient #' . $patient->id)
                . '\'s expected delivery date is approaching (' . $formattedEdd . ').'
                . ' Please review the patient\'s pregnancy status and follow-up plan.',
            'action_label' => 'View patient',
            'destination' => [
                'route' => 'patients.show',
                'parameters' => ['patient' => $patient->id],
            ],
            'event_key' => $this->eventKey,
        ];
    }
}
