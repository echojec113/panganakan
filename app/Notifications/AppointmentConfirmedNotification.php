<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Notifications\Notification;

/**
 * DEPLA Phase 3B.4 - the patient's appointment was confirmed.
 *
 * Sent once per appointment (event_key = appointment-confirmed:{id}) to
 * the patient's linked, active Patient Portal account after the confirm
 * transaction commits. The message uses the actual confirmed schedule in
 * Philippine 12-hour display; a date-only delivery request omits the time
 * clause. No other patient data is included - the recipient already owns
 * the record.
 */
class AppointmentConfirmedNotification extends Notification
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
        $date = ($this->appointment->confirmed_date ?? $this->appointment->requested_date)
            ->format('F j, Y');
        $clock = $this->formatClock($this->appointment->confirmed_time);

        return [
            'type' => 'APPOINTMENT_CONFIRMED',
            'title' => 'Appointment Confirmed',
            'message' => 'Your ' . $this->appointment->type_label
                . ' appointment has been confirmed for ' . $date
                . ($clock !== null ? ' at ' . $clock : '') . '.',
            'action_label' => 'View My Appointments',
            'destination' => [
                'route' => 'patient.appointments.index',
                'parameters' => [],
            ],
            'event_key' => $this->eventKey,
        ];
    }

    /**
     * The stored confirmed_time is a plain 24-hour HH:MM string; render it
     * in the Philippine 12-hour display convention used across DEPLA.
     */
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
