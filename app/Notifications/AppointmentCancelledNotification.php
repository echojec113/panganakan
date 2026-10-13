<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Notifications\Notification;

/**
 * DEPLA Phase 3B.4 - the patient's confirmed appointment was cancelled.
 *
 * Sent once per appointment (event_key = appointment-cancelled:{id}) to the
 * patient's linked, active Patient Portal account after the cancel
 * transaction commits. The detailed cancellation reason is deliberately NOT
 * included here - it stays in the authorized My Appointments page.
 */
class AppointmentCancelledNotification extends Notification
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
            'type' => 'APPOINTMENT_CANCELLED',
            'title' => 'Appointment Cancelled',
            'message' => 'Your appointment has been cancelled. Please check My Appointments for details.',
            'action_label' => 'View My Appointments',
            'destination' => [
                'route' => 'patient.appointments.index',
                'parameters' => [],
            ],
            'event_key' => $this->eventKey,
        ];
    }
}
