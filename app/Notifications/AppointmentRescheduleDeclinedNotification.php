<?php

namespace App\Notifications;

use App\Models\AppointmentChangeRequest;
use Illuminate\Notifications\Notification;

/**
 * DEPLA Phase 3D.3B Step 3 - Admin/Staff DECLINED a patient reschedule
 * request: the confirmed online appointment keeps its ORIGINAL
 * confirmed schedule.
 *
 * Sent once per change request (event_key =
 * appointment-reschedule-declined:{requestId}) to the patient's linked,
 * active Patient Portal account after the declineRescheduleRequest()
 * transaction commits. This is the SINGLE patient notification for the
 * decline path - the generic appointment status-changed notification is
 * deliberately NOT also sent (the appointment's status did not change).
 * The decline EXPLANATION (review_note) is deliberately NOT in the
 * payload - it will be displayed only on the patient's appointment card
 * in the later UI step (Phase 3B.4 convention: reasons never travel
 * through the shared notification wording).
 */
class AppointmentRescheduleDeclinedNotification extends Notification
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
            'type' => 'APPOINTMENT_RESCHEDULE_DECLINED',
            'title' => 'Reschedule Request Declined',
            'message' => 'Your reschedule request was not approved. Your ' . $appointment->type_label
                . ' appointment for ' . $scheduleDate->format('F j, Y') . ' remains confirmed.',
            'action_label' => 'View My Appointments',
            'destination' => [
                'route' => 'patient.appointments.index',
                'parameters' => [],
            ],
            'event_key' => $this->eventKey,
        ];
    }
}
