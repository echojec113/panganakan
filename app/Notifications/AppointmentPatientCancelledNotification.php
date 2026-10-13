<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Notifications\Notification;

/**
 * DEPLA Phase 3D.2B - a PENDING online appointment was cancelled
 * DIRECTLY by its owning patient (no approval step, no request row).
 *
 * Sent once per appointment (event_key = appointment-patient-cancelled:
 * {id}) to every active Admin/Staff account after the patient's cancel
 * transaction commits. The patient is never notified about their own
 * action (the success flash already confirms it). The payload mirrors
 * AppointmentRequestedNotification's privacy posture: appointment type
 * and requested date only - no patient name, contact number, clinical
 * data and no cancellation reason (the reason stays on the appointment
 * record and in the audit log, visible to authorized clinic users).
 */
class AppointmentPatientCancelledNotification extends Notification
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
            'type' => 'APPOINTMENT_PATIENT_CANCELLED',
            'title' => 'Appointment Cancelled by Patient',
            'message' => 'A pending appointment request was cancelled by the patient: ' . $this->appointment->type_label
                . ' appointment submitted for ' . $this->appointment->requested_date->format('F j, Y') . '.',
            'action_label' => 'View day',
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
