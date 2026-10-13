<?php

namespace App\Notifications;

use App\Models\AppointmentChangeRequest;
use Illuminate\Notifications\Notification;

/**
 * DEPLA Phase 3D.3B Step 3 - Admin/Staff APPROVED a patient reschedule
 * request: the appointment now follows the patient's exact proposed
 * schedule (delivery requests land date-only with no time).
 *
 * Sent once per change request (event_key =
 * appointment-reschedule-approved:{requestId}) to the patient's linked,
 * active Patient Portal account after the approveRescheduleRequest()
 * transaction commits. This is the SINGLE patient notification for the
 * approval path - the generic appointment status-changed notification
 * is deliberately NOT also sent (the appointment's STATUS never
 * changed, only its schedule), so the patient never receives two
 * messages for one approval. The message names the NEWLY CONFIRMED
 * schedule (date, plus time when the type has one); the staff note is
 * deliberately NOT in the payload - details stay in the authorized My
 * Appointments page (Phase 3B.4 convention).
 */
class AppointmentRescheduleApprovedNotification extends Notification
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
        $newDate = $appointment->confirmed_date ?? $appointment->requested_date;
        $newTime = $appointment->confirmed_time;

        return [
            'type' => 'APPOINTMENT_RESCHEDULE_APPROVED',
            'title' => 'Reschedule Request Approved',
            'message' => 'Your reschedule request was approved. Your ' . $appointment->type_label
                . ' appointment is now confirmed for ' . $newDate->format('F j, Y')
                . ($newTime !== null ? ' at ' . $newTime : '') . '.',
            'action_label' => 'View My Appointments',
            'destination' => [
                'route' => 'patient.appointments.index',
                'parameters' => [],
            ],
            'event_key' => $this->eventKey,
        ];
    }
}
