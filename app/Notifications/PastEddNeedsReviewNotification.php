<?php

namespace App\Notifications;

use App\Models\Patient;
use Illuminate\Notifications\Notification;

class PastEddNeedsReviewNotification extends Notification
{
    /**
     * @param  string  $eventKey  Stable logical identity of this pregnancy
     *                            event (past-edd:{patient}) used for duplicate
     *                            prevention - one review alert per pregnancy.
     *                            This notification NEVER changes patient state;
     *                            it only asks a human to review.
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
            'type' => 'PAST_EDD',
            'title' => 'Past Expected Delivery Date',
            'message' => ($name !== '' ? $name : 'Patient #' . $patient->id)
                . ' is past the expected delivery date (' . $formattedEdd . ').'
                . ' Please review and update the patient\'s pregnancy status.',
            'action_label' => 'View patient',
            'destination' => [
                'route' => 'patients.show',
                'parameters' => ['patient' => $patient->id],
            ],
            'event_key' => $this->eventKey,
        ];
    }
}
