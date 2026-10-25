<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Notifications\Notification;

/**
 * DEPLA FCFS Phase 2 - a patient booked an ONLINE CHECKUP that was
 * confirmed immediately (no staff review).
 *
 * Sent once per appointment (event_key = appointment-booked:{id}) to every
 * active Admin/Staff account after the booking transaction commits. The
 * payload carries only the appointment type and confirmed schedule (no
 * patient name, contact number or clinical data), like the request alert.
 */
class AppointmentBookedNotification extends Notification
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
     * @return array<string, mixed>
     */
    public function toDatabase($notifiable): array
    {
        $date = $this->appointment->confirmed_date ?? $this->appointment->requested_date;
        $clock = $this->formatClock($this->appointment->confirmed_time);

        return [
            'type' => 'APPOINTMENT_BOOKED',
            'title' => 'New Confirmed Appointment',
            'message' => 'New online ' . $this->appointment->type_label
                . ' appointment automatically confirmed for ' . $date->format('F j, Y')
                . ($clock !== null ? ' at ' . $clock : '') . '.',
            'action_label' => 'View appointment',
            'destination' => [
                'route' => 'appointments.index',
                'parameters' => [
                    'view' => 'day',
                    'date' => $date->toDateString(),
                    'status' => 'all',
                ],
            ],
            'event_key' => $this->eventKey,
        ];
    }

    private function formatClock(?string $time): ?string
    {
        if ($time === null || trim($time) === '') {
            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $time));
        $period = $hour >= 12 ? 'PM' : 'AM';
        $hour12 = $hour % 12 === 0 ? 12 : $hour % 12;

        return sprintf('%d:%02d %s', $hour12, $minute, $period);
    }
}
