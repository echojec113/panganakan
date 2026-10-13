<?php

namespace App\Notifications;

use App\Models\AppointmentChangeRequest;
use Illuminate\Notifications\Notification;

/**
 * DEPLA Phase 3D.2B - a PATIENT submitted a cancellation REQUEST against
 * a CONFIRMED online appointment (approval-gated: the appointment stays
 * confirmed until Admin/Staff review in a later step).
 *
 * Sent once per change request (event_key = appointment-cancel-requested:
 * {requestId}) to every active Admin/Staff account after the request
 * transaction commits. The payload carries the appointment type and the
 * confirmed schedule date only - no patient name, contact number,
 * clinical data and no patient-submitted reason (the reason is reviewed
 * inside the authorized calendar detail panel, never in the shared
 * notification tray). The day-view deep link identifies the appointment
 * without putting an ID in the URL query string.
 */
class AppointmentCancellationRequestedNotification extends Notification
{
    public function __construct(public AppointmentChangeRequest $request, public string $eventKey)
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
        $appointment = $this->request->appointment;
        $scheduleDate = $appointment->confirmed_date ?? $appointment->requested_date;

        return [
            'type' => 'APPOINTMENT_CANCELLATION_REQUESTED',
            'title' => 'Cancellation Request Submitted',
            'message' => 'A cancellation request is awaiting review: ' . $appointment->type_label
                . ' appointment confirmed for ' . $scheduleDate->format('F j, Y') . '.',
            'action_label' => 'View day',
            'destination' => [
                'route' => 'appointments.index',
                'parameters' => [
                    'view' => 'day',
                    'date' => $scheduleDate->toDateString(),
                    'status' => 'all',
                ],
            ],
            'event_key' => $this->eventKey,
        ];
    }
}
