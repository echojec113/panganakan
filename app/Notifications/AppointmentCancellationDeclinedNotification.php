<?php

namespace App\Notifications;

use App\Models\AppointmentChangeRequest;
use Illuminate\Notifications\Notification;

/**
 * DEPLA Phase 3D.2C - Admin/Staff DECLINED a patient cancellation
 * request: the confirmed online appointment stays confirmed with its
 * schedule intact.
 *
 * Sent once per change request (event_key =
 * appointment-cancellation-declined:{requestId}) to the patient's
 * linked, active Patient Portal account after the decline transaction
 * commits. This is the SINGLE patient notification for the decline
 * path - the generic appointment status-changed notification is
 * deliberately NOT also sent (the appointment's status did not change).
 * The decline EXPLANATION (review_note) is deliberately NOT in the
 * payload - it stays in the authorized My Appointments page where only
 * the owning patient can see it (Phase 3B.4 convention: reasons never
 * travel through the shared notification wording).
 */
class AppointmentCancellationDeclinedNotification extends Notification
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
            'type' => 'APPOINTMENT_CANCELLATION_DECLINED',
            'title' => 'Cancellation Request Declined',
            'message' => 'Your cancellation request was not approved. Your ' . $appointment->type_label
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
