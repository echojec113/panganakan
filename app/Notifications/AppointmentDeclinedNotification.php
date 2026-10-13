<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Notifications\Notification;

/**
 * DEPLA Phase 3B.4 - the patient's appointment request was declined.
 *
 * Sent once per appointment (event_key = appointment-declined:{id}) to the
 * patient's linked, active Patient Portal account after the decline
 * transaction commits. The detailed decline reason is deliberately NOT
 * included here - it stays in the authorized My Appointments page so this
 * shared-table row carries no extra clinical or personal detail.
 */
class AppointmentDeclinedNotification extends Notification
{
    public function __construct(public Appointment $appointment, public string $eventKey)
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
        return [
            'type' => 'APPOINTMENT_DECLINED',
            'title' => 'Appointment Declined',
            'message' => 'Your appointment request was declined. Please check My Appointments for details.',
            'action_label' => 'View My Appointments',
            'destination' => [
                'route' => 'patient.appointments.index',
                'parameters' => [],
            ],
            'event_key' => $this->eventKey,
        ];
    }
}
