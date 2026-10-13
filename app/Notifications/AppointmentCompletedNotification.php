<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Notifications\Notification;

/**
 * DEPLA Phase 3B.4 - the patient's confirmed appointment was completed.
 *
 * Sent once per appointment (event_key = appointment-completed:{id}) to the
 * patient's linked, active Patient Portal account after the complete
 * transaction commits.
 */
class AppointmentCompletedNotification extends Notification
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
            'type' => 'APPOINTMENT_COMPLETED',
            'title' => 'Appointment Completed',
            'message' => 'Your appointment has been marked completed.',
            'action_label' => 'View My Appointments',
            'destination' => [
                'route' => 'patient.appointments.index',
                'parameters' => [],
            ],
            'event_key' => $this->eventKey,
        ];
    }
}
