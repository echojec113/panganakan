<?php

use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Services\AppointmentReminderService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;



function appointmentReminderPatient(?string $email = 'patient@example.test'): Patient
{
    return Patient::forceCreate([
        'first_name' => 'Test',
        'last_name' => 'Patient',
        'age' => 28,
        'email' => $email,
    ]);
}

function appointmentReminderAppointment(Patient $patient, array $overrides = []): Appointment
{
    $schedule = Carbon::now(AppointmentReminderService::TIMEZONE)->addDays(7)->setTime(10, 0);

    return Appointment::forceCreate(array_merge([
        'patient_id' => $patient->id,
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'source' => Appointment::SOURCE_ONLINE,
        'requested_date' => $schedule->toDateString(),
        'requested_time' => '10:00:00',
        'confirmed_date' => $schedule->toDateString(),
        'confirmed_time' => '10:00:00',
        'status' => Appointment::STATUS_CONFIRMED,
    ], $overrides));
}

afterEach(function () {
    Carbon::setTestNow();
});

it('sends each reminder at its lead time using the confirmed schedule', function (string $type, int $leadMinutes) {
    $schedule = Carbon::parse('2026-11-05 10:00:00', AppointmentReminderService::TIMEZONE);
    Carbon::setTestNow($schedule->copy()->subMinutes($leadMinutes));
    Mail::fake();

    $appointment = appointmentReminderAppointment(appointmentReminderPatient(), [
        'requested_date' => '2026-11-06',
        'requested_time' => '15:00:00',
        'confirmed_date' => $schedule->toDateString(),
        'confirmed_time' => '10:00:00',
    ]);

    $this->artisan('appointments:send-reminders')->assertSuccessful();

    Mail::assertSent(AppointmentReminderMail::class, fn ($mail) =>
        $mail->appointment->is($appointment) && $mail->reminderType === $type
    );
    expect(DB::table('appointment_reminders')->where('reminder_type', $type)->value('status'))->toBe('sent');
})->with([
    'one week' => ['one_week', 7 * 24 * 60],
    'three days' => ['three_days', 3 * 24 * 60],
    'two hours' => ['two_hours', 120],
]);

it('tracks reminder types independently and prevents duplicate deliveries', function () {
    $schedule = Carbon::parse('2026-11-05 10:00:00', AppointmentReminderService::TIMEZONE);
    Carbon::setTestNow($schedule->copy()->subDays(7));
    Mail::fake();
    $appointment = appointmentReminderAppointment(appointmentReminderPatient(), [
        'confirmed_date' => $schedule->toDateString(),
    ]);

    $this->artisan('appointments:send-reminders')->assertSuccessful();
    $this->artisan('appointments:send-reminders')->assertSuccessful();

    Carbon::setTestNow($schedule->copy()->subDays(3));
    $this->artisan('appointments:send-reminders')->assertSuccessful();
    $this->artisan('appointments:send-reminders')->assertSuccessful();

    Mail::assertSent(AppointmentReminderMail::class, 2);
    expect(DB::table('appointment_reminders')->where('appointment_id', $appointment->id)->where('status', 'sent')->count())->toBe(2);
});

it('excludes prenatal-linked, non-confirmed, and incomplete appointments', function () {
    $schedule = Carbon::parse('2026-11-05 10:00:00', AppointmentReminderService::TIMEZONE);
    Carbon::setTestNow($schedule->copy()->subDays(7));
    Mail::fake();
    $patient = appointmentReminderPatient();
    $visit = PrenatalVisit::forceCreate(['patient_id' => $patient->id, 'visit_date' => '2026-10-01']);

    foreach ([
        ['status' => Appointment::STATUS_PENDING],
        ['status' => Appointment::STATUS_CANCELLED],
        ['status' => Appointment::STATUS_DECLINED],
        ['status' => Appointment::STATUS_COMPLETED],
        ['prenatal_visit_id' => $visit->id],
        ['confirmed_time' => null],
    ] as $overrides) {
        appointmentReminderAppointment($patient, array_merge(['confirmed_date' => $schedule->toDateString()], $overrides));
    }

    $this->artisan('appointments:send-reminders')->assertSuccessful();

    Mail::assertNothingSent();
});

it('safely skips a missing or invalid patient email', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-29 10:00:00', AppointmentReminderService::TIMEZONE));
    Mail::fake();
    appointmentReminderAppointment(appointmentReminderPatient('invalid-address'));
    appointmentReminderAppointment(appointmentReminderPatient(null));

    $this->artisan('appointments:send-reminders')->assertSuccessful();

    Mail::assertNothingSent();
    expect(DB::table('appointment_reminders')->count())->toBe(0);
});

it('skips an appointment whose patient has been archived', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-29 10:00:00', AppointmentReminderService::TIMEZONE));
    Mail::fake();
    $patient = appointmentReminderPatient();
    $patient->delete();
    appointmentReminderAppointment($patient);

    $this->artisan('appointments:send-reminders')->assertSuccessful();

    Mail::assertNothingSent();
});

it('uses the new confirmed schedule after rescheduling and ignores the old schedule key', function () {
    $oldSchedule = Carbon::parse('2026-11-05 10:00:00', AppointmentReminderService::TIMEZONE);
    Carbon::setTestNow($oldSchedule->copy()->subDays(7));
    Mail::fake();
    $appointment = appointmentReminderAppointment(appointmentReminderPatient(), [
        'confirmed_date' => $oldSchedule->toDateString(),
    ]);
    DB::table('appointment_reminders')->insert([
        'appointment_id' => $appointment->id,
        'reminder_type' => 'one_week',
        'confirmed_date' => $oldSchedule->toDateString(),
        'confirmed_time' => '10:00:00',
        'status' => 'sent',
        'sent_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $newSchedule = Carbon::parse('2026-11-12 10:00:00', AppointmentReminderService::TIMEZONE);
    $appointment->forceFill(['confirmed_date' => $newSchedule->toDateString()])->save();
    Carbon::setTestNow($newSchedule->copy()->subDays(7));
    $this->artisan('appointments:send-reminders')->assertSuccessful();

    Mail::assertSent(AppointmentReminderMail::class, 1);
    expect(DB::table('appointment_reminders')->where('appointment_id', $appointment->id)->where('status', 'sent')->count())->toBe(2);
});

it('records an expired reminder window as missed instead of sending it late', function () {
    $schedule = Carbon::parse('2026-11-05 10:00:00', AppointmentReminderService::TIMEZONE);
    Carbon::setTestNow($schedule->copy()->subDays(7)->addMinutes(AppointmentReminderService::GRACE_MINUTES + 1));
    Mail::fake();
    $appointment = appointmentReminderAppointment(appointmentReminderPatient(), [
        'confirmed_date' => $schedule->toDateString(),
    ]);

    $this->artisan('appointments:send-reminders')->assertSuccessful();

    Mail::assertNothingSent();
    expect(DB::table('appointment_reminders')->where('appointment_id', $appointment->id)->where('reminder_type', 'one_week')->value('status'))->toBe('missed');
});

it('does not mark a failed mail delivery as sent', function () {
    $schedule = Carbon::parse('2026-11-05 10:00:00', AppointmentReminderService::TIMEZONE);
    Carbon::setTestNow($schedule->copy()->subDays(7));
    $appointment = appointmentReminderAppointment(appointmentReminderPatient(), [
        'confirmed_date' => $schedule->toDateString(),
    ]);
    Mail::shouldReceive('to')->once()->with('patient@example.test')->andReturn(new class
    {
        public function send($mail): void
        {
            throw new RuntimeException('Transport unavailable');
        }
    });

    $this->artisan('appointments:send-reminders')->assertSuccessful();

    expect(DB::table('appointment_reminders')->where('appointment_id', $appointment->id)->count())->toBe(0);
});

it('does not send a missed week reminder after late confirmation but still sends a due three-day reminder', function () {
    $schedule = Carbon::parse('2026-11-05 10:00:00', AppointmentReminderService::TIMEZONE);
    Carbon::setTestNow($schedule->copy()->subDays(3));
    Mail::fake();
    $appointment = appointmentReminderAppointment(appointmentReminderPatient(), [
        'confirmed_date' => $schedule->toDateString(),
    ]);

    $this->artisan('appointments:send-reminders')->assertSuccessful();

    Mail::assertSent(AppointmentReminderMail::class, 1);
    expect(DB::table('appointment_reminders')->where('appointment_id', $appointment->id)->where('reminder_type', 'one_week')->value('status'))->toBe('missed')
        ->and(DB::table('appointment_reminders')->where('appointment_id', $appointment->id)->where('reminder_type', 'three_days')->value('status'))->toBe('sent');
});

it('never sends a two-hour reminder after the appointment has started', function () {
    $schedule = Carbon::parse('2026-11-05 10:00:00', AppointmentReminderService::TIMEZONE);
    Carbon::setTestNow($schedule->copy()->addMinute());
    Mail::fake();
    $appointment = appointmentReminderAppointment(appointmentReminderPatient(), [
        'confirmed_date' => $schedule->toDateString(),
        'confirmed_time' => '10:00:00',
    ]);

    $this->artisan('appointments:send-reminders')->assertSuccessful();

    Mail::assertNothingSent();
    expect(DB::table('appointment_reminders')->where('appointment_id', $appointment->id)->where('reminder_type', 'two_hours')->value('status'))->toBe('missed');
});

it('preserves the existing scheduler entries and checks appointment reminders every minute', function () {
    $commands = collect(Schedule::events())->pluck('command')->all();

    expect(collect($commands)->contains(fn ($command) => str_contains($command, 'app:send-prenatal-reminders')))->toBeTrue()
        ->and(collect($commands)->contains(fn ($command) => str_contains($command, 'notifications:check-clinical-alerts')))->toBeTrue()
        ->and(collect($commands)->contains(fn ($command) => str_contains($command, 'appointments:send-reminders')))->toBeTrue();
});
