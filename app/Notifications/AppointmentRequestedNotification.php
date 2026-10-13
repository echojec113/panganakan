<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Notifications\Notification;

/**
 * DEPLA Phase 3B.4 - a new ONLINE appointment request reached the clinic.
 *
 * Sent once per appointment (event_key = appointment-requested:{id}) to
 * every active Admin/Staff account after the online-request transaction
 * commits. Walk-in appointments never trigger this class. The payload
 * carries only the appointment type and requested date (no patient name,
 * contact number or clinical data) so the shared clinic tray stays
 * minimal; the day-view deep link identifies the appointment in the
 * calendar without putting an ID in the URL query string.
 */
class AppointmentRequestedNotification extends Notification
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
            'type' => 'APPOINTMENT_REQUESTED',
            'title' => 'New Appointment Request',
            'message' => 'New appointment request: ' . $this->appointment->type_label
                . ' appointment submitted for ' . $this->appointment->requested_date->format('F j, Y') . '.',
            'action_label' => 'View request',
            'destination' => [
                'route' => 'appointments.index',
                'parameters' => [
                    'view' => 'day',
                    'date' => $this->appointment->requested_date->toDateString(),
                    'status' => 'all',
                ],
            ],
            'event_key' => $this->eventKey,
        ];
    }
}
