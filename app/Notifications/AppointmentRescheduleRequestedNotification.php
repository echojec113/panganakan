<?php

namespace App\Notifications;

use App\Models\AppointmentChangeRequest;
use Illuminate\Notifications\Notification;

/**
 * DEPLA Phase 3D.3B Step 3 - a PATIENT submitted a reschedule REQUEST
 * against a CONFIRMED online appointment (approval-gated: the current
 * confirmed schedule stays until Admin/Staff review applies the exact
 * proposed one).
 *
 * Sent once per change request (event_key = appointment-reschedule-
 * requested:{requestId}) to every active Admin/Staff account after the
 * requestAppointmentReschedule() transaction commits. The payload
 * carries only the neutral notice text - no patient name, no contact
 * details, no patient-submitted reason and no reviewer notes (privacy-
 * minimized: the day-view deep link opens the appointment inside the
 * authorized clinic calendar, where context lives). The link targets
 * the appointment's CURRENT confirmed schedule day, the day the
 * appointment currently sits on the calendar.
 */
class AppointmentRescheduleRequestedNotification extends Notification
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
            'type' => 'APPOINTMENT_RESCHEDULE_REQUESTED',
            'title' => 'Reschedule Request Submitted',
            'message' => 'A patient has requested to reschedule an appointment.',
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
