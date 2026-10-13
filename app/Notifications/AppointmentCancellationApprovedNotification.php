<?php

namespace App\Notifications;

use App\Models\AppointmentChangeRequest;
use Illuminate\Notifications\Notification;

/**
 * DEPLA Phase 3D.2C - Admin/Staff APPROVED a patient cancellation
 * request: the confirmed online appointment is now cancelled.
 *
 * Sent once per change request (event_key =
 * appointment-cancellation-approved:{requestId}) to the patient's
 * linked, active Patient Portal account after the approval transaction
 * commits. This is the SINGLE patient notification for the approval
 * path - the generic appointment status-changed notification is
 * deliberately NOT also sent, so the patient never receives two
 * messages for one approval. The staff note and the patient's own
 * reason are deliberately NOT in the payload - details stay in the
 * authorized My Appointments page (Phase 3B.4 convention).
 */
class AppointmentCancellationApprovedNotification extends Notification
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
            'type' => 'APPOINTMENT_CANCELLATION_APPROVED',
            'title' => 'Cancellation Request Approved',
            'message' => 'Your cancellation request was approved. Your ' . $appointment->type_label
                . ' appointment for ' . $scheduleDate->format('F j, Y') . ' has been cancelled.',
            'action_label' => 'View My Appointments',
            'destination' => [
                'route' => 'patient.appointments.index',
                'parameters' => [],
            ],
            'event_key' => $this->eventKey,
        ];
    }
}
