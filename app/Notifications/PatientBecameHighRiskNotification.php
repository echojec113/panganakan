<?php

namespace App\Notifications;

use App\Models\Patient;
use Illuminate\Notifications\Notification;

class PatientBecameHighRiskNotification extends Notification
{
    /**
     * @param  string  $eventKey  Stable logical identity of this transition
     *                            (became-high-risk:{patient}:{assessment}) used
     *                            for duplicate prevention.
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

        return [
            'type' => 'PATIENT_HIGH_RISK',
            'title' => 'Patient Became High Risk',
            'message' => ($name !== '' ? $name : 'Patient #' . $patient->id)
                . ' has been classified as HIGH risk. Please review the patient\'s latest assessment and clinical findings.',
            'action_label' => 'View patient',
            'destination' => [
                'route' => 'patients.show',
                'parameters' => ['patient' => $patient->id],
            ],
            'event_key' => $this->eventKey,
        ];
    }
}
