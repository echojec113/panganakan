<?php

use App\Models\Appointment;
use App\Models\ClinicUnavailability;
use App\Models\Patient;
use App\Models\User;
use App\Services\ClinicAvailabilityService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| Phase 1 - Slot grid, closures (read side), availability states
|--------------------------------------------------------------------------
| SQLite :memory: only (phpunit.xml forces). Covers the 18-slot catalog,
| alignment, Manila past detection, closure validation + union semantics,
| closure removal/reopening, and the patient-safe availability payload.
*/

function casService(): ClinicAvailabilityService
{
    return app(ClinicAvailabilityService::class);
}

function casPatient(string $first = 'Casa'): Patient
{
    return Patient::create([
        'first_name' => $first,
        'last_name' => 'Santos',
        'age' => 28,
        'address' => 'Test address',
        'contact_number' => '09171234567',
        'email' => strtolower($first) . '@casslot.test',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function casAppointment(Patient $patient, array $overrides = []): Appointment
{
    return Appointment::forceCreate(array_merge([
        'patient_id' => $patient->id,
        'appointment_type' => Appointment::TYPE_CHECKUP,
        'source' => Appointment::SOURCE_ONLINE,
        'requested_date' => '2026-11-05',
        'requested_time' => '09:00',
        'confirmed_date' => '2026-11-05',
        'confirmed_time' => '09:00',
        'status' => Appointment::STATUS_CONFIRMED,
    ], $overrides));
}

function casAdmin(): User
{
    return User::factory()->create(['role' => 'admin', 'name' => 'Closure Admin']);
}

afterEach(function () {
    Carbon::setTestNow();
});

/*
|--------------------------------------------------------------------------
| 1-4. Slot catalog: 18 slots, first/last, labels, alignment
|--------------------------------------------------------------------------
*/

it('generates exactly 18 daily slots from 08:00 through 16:30', function () {
    $starts = casService()->slotStarts();

    expect($starts)->toHaveCount(18)
        ->and($starts[0])->toBe('08:00')
        ->and($starts[17])->toBe('16:30');
});

it('computes slot ends on the 30-minute grid with the last slot ending at 17:00', function () {
    expect(casService()->slotEnd('08:00'))->toBe('08:30')
        ->and(casService()->slotEnd('16:30'))->toBe('17:00');
});

it('formats slot labels for display', function () {
    expect(casService()->slotLabel('08:00'))->toBe('8:00–8:30 AM')
        ->and(casService()->slotLabel('16:30'))->toBe('4:30–5:00 PM')
        ->and(casService()->slotLabel('11:30'))->toBe('11:30 AM–12:00 PM');
});

it('accepts only grid-aligned slot starts inside clinic hours', function () {
    $service = casService();

    expect($service->isSlotAligned('08:00'))->toBeTrue()
        ->and($service->isSlotAligned('16:30'))->toBeTrue()
        ->and($service->isSlotAligned('08:15'))->toBeFalse()
        ->and($service->isSlotAligned('07:30'))->toBeFalse()
        ->and($service->isSlotAligned('17:00'))->toBeFalse();

    expect(fn () => $service->assertSlotStart('09:15'))->toThrow(DomainException::class);
    expect(fn () => $service->assertSlotStart('17:00'))->toThrow(DomainException::class);
});

/*
|--------------------------------------------------------------------------
| 5. Timezone-aware past-slot detection (Asia/Manila)
|--------------------------------------------------------------------------
*/

it('detects past slots in Asia/Manila, allowing the current minute', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 09:00:00', 'Asia/Manila'));

    $service = casService();

    // Equal minute is still allowed (matches pastScheduleIssue rule).
    expect($service->isSlotPast('2026-11-05', '09:00'))->toBeFalse()
        ->and($service->isSlotPast('2026-11-05', '09:30'))->toBeFalse();

    Carbon::setTestNow(Carbon::parse('2026-11-05 09:01:00', 'Asia/Manila'));

    expect($service->isSlotPast('2026-11-05', '09:00'))->toBeTrue()
        ->and($service->isSlotPast('2026-11-05', '09:30'))->toBeFalse()
        ->and($service->isSlotPast('2026-11-04', '16:30'))->toBeTrue()
        ->and($service->isSlotPast('2026-11-06', '08:00'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| 6-10. Closures: single-day, multi-day, 366-day max, partial window, union
|--------------------------------------------------------------------------
*/

it('validates a single-day full-day closure and blocks every slot on that date', function () {
    $closure = casService()->createClosure([
        'start_date' => '2026-11-05',
        'end_date' => '2026-11-05',
        'all_day' => true,
    ], casAdmin());

    expect($closure->all_day)->toBeTrue()
        ->and(casService()->isDateFullyClosed('2026-11-05'))->toBeTrue()
        ->and(casService()->isDateFullyClosed('2026-11-06'))->toBeFalse();

    $payload = casService()->slotsForDate('2026-11-05', Carbon::parse('2026-11-01 08:00:00', 'Asia/Manila'));

    expect($payload['fully_unavailable'])->toBeTrue();

    foreach ($payload['slots'] as $slot) {
        expect($slot['state'])->toBe(ClinicAvailabilityService::STATE_UNAVAILABLE);
    }
});

it('applies an inclusive multi-day closure to the first, middle and last dates only', function () {
    casService()->createClosure([
        'start_date' => '2026-11-10',
        'end_date' => '2026-11-12',
        'all_day' => true,
    ], casAdmin());

    expect(casService()->isDateFullyClosed('2026-11-10'))->toBeTrue()
        ->and(casService()->isDateFullyClosed('2026-11-11'))->toBeTrue()
        ->and(casService()->isDateFullyClosed('2026-11-12'))->toBeTrue()
        ->and(casService()->isDateFullyClosed('2026-11-09'))->toBeFalse()
        ->and(casService()->isDateFullyClosed('2026-11-13'))->toBeFalse();
});

it('allows a 366-day closure and refuses a 367-day closure', function () {
    $service = casService();

    $start = Carbon::parse('2026-01-01', 'Asia/Manila');
    $within = $start->copy()->addDays(365)->toDateString();   // 366 inclusive days
    $beyond = $start->copy()->addDays(366)->toDateString();   // 367 inclusive days

    $closure = $service->createClosure([
        'start_date' => '2026-01-01',
        'end_date' => $within,
        'all_day' => true,
    ], casAdmin());

    expect($closure->end_date->toDateString())->toBe($within);

    expect(fn () => $service->validateClosureInput([
        'start_date' => '2026-01-01',
        'end_date' => $beyond,
        'all_day' => true,
    ]))->toThrow(ValidationException::class);
});

it('blocks exactly the slots inside a partial time-window closure', function () {
    casService()->createClosure([
        'start_date' => '2026-11-05',
        'end_date' => '2026-11-05',
        'all_day' => false,
        'start_time' => '10:00',
        'end_time' => '11:00',
    ], casAdmin());

    expect(casService()->isSlotClosed('2026-11-05', '10:00'))->toBeTrue()
        ->and(casService()->isSlotClosed('2026-11-05', '10:30'))->toBeTrue()
        ->and(casService()->isSlotClosed('2026-11-05', '09:30'))->toBeFalse()
        ->and(casService()->isSlotClosed('2026-11-05', '11:00'))->toBeFalse()
        ->and(casService()->isDateFullyClosed('2026-11-05'))->toBeFalse();
});

it('unions overlapping closure windows into merged ranges', function () {
    $admin = casAdmin();

    casService()->createClosure([
        'start_date' => '2026-11-05',
        'end_date' => '2026-11-05',
        'all_day' => false,
        'start_time' => '09:00',
        'end_time' => '11:00',
    ], $admin);

    casService()->createClosure([
        'start_date' => '2026-11-05',
        'end_date' => '2026-11-05',
        'all_day' => false,
        'start_time' => '10:00',
        'end_time' => '12:00',
    ], $admin);

    $windows = casService()->unavailableWindows('2026-11-05');

    expect($windows)->toHaveCount(1)
        ->and($windows[0]['start'])->toBe('09:00')
        ->and($windows[0]['end'])->toBe('12:00')
        ->and(casService()->isSlotClosed('2026-11-05', '11:30'))->toBeTrue()
        ->and(casService()->isSlotClosed('2026-11-05', '12:00'))->toBeFalse();
});

it('treats adjacent partial windows that span the whole day as fully closed', function () {
    $admin = casAdmin();

    casService()->createClosure([
        'start_date' => '2026-11-05',
        'end_date' => '2026-11-05',
        'all_day' => false,
        'start_time' => '08:00',
        'end_time' => '12:00',
    ], $admin);

    casService()->createClosure([
        'start_date' => '2026-11-05',
        'end_date' => '2026-11-05',
        'all_day' => false,
        'start_time' => '12:00',
        'end_time' => '17:00',
    ], $admin);

    expect(casService()->isDateFullyClosed('2026-11-05'))->toBeTrue();
});

it('reopens a date when its closure is removed', function () {
    $service = casService();

    $closure = $service->createClosure([
        'start_date' => '2026-11-05',
        'end_date' => '2026-11-05',
        'all_day' => true,
    ], casAdmin());

    expect($service->isDateFullyClosed('2026-11-05'))->toBeTrue();

    $service->removeClosure($closure, casAdmin());

    expect($service->isDateFullyClosed('2026-11-05'))->toBeFalse()
        ->and(ClinicUnavailability::find($closure->id))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| 11. Closure cross-field validation rules
|--------------------------------------------------------------------------
*/

it('refuses invalid closure shapes with field-scoped validation errors', function () {
    $service = casService();

    // Partial window spanning multiple dates.
    expect(fn () => $service->validateClosureInput([
        'start_date' => '2026-11-05',
        'end_date' => '2026-11-06',
        'all_day' => false,
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]))->toThrow(ValidationException::class);

    // Full-day closure with stray times.
    expect(fn () => $service->validateClosureInput([
        'start_date' => '2026-11-05',
        'end_date' => '2026-11-05',
        'all_day' => true,
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]))->toThrow(ValidationException::class);

    // Misaligned window start.
    expect(fn () => $service->validateClosureInput([
        'start_date' => '2026-11-05',
        'end_date' => '2026-11-05',
        'all_day' => false,
        'start_time' => '10:15',
        'end_time' => '11:00',
    ]))->toThrow(ValidationException::class);

    // Duration not a whole number of slots.
    expect(fn () => $service->validateClosureInput([
        'start_date' => '2026-11-05',
        'end_date' => '2026-11-05',
        'all_day' => false,
        'start_time' => '10:00',
        'end_time' => '10:45',
    ]))->toThrow(ValidationException::class);

    // End not later than start.
    expect(fn () => $service->validateClosureInput([
        'start_date' => '2026-11-05',
        'end_date' => '2026-11-05',
        'all_day' => false,
        'start_time' => '11:00',
        'end_time' => '10:00',
    ]))->toThrow(ValidationException::class);

    // end_date before start_date.
    expect(fn () => $service->validateClosureInput([
        'start_date' => '2026-11-06',
        'end_date' => '2026-11-05',
        'all_day' => true,
    ]))->toThrow(ValidationException::class);
});

/*
|--------------------------------------------------------------------------
| 12. Patient-safe availability payload
|--------------------------------------------------------------------------
*/

it('returns a patient-safe availability payload with no reasons, names or ids', function () {
    $patient = casPatient('Exposed');
    casAppointment($patient, ['confirmed_time' => '14:37']);

    casService()->createClosure([
        'start_date' => '2026-11-05',
        'end_date' => '2026-11-05',
        'all_day' => false,
        'start_time' => '09:00',
        'end_time' => '10:00',
        'reason' => 'Internal reason: private staff note',
    ], casAdmin());

    $payload = casService()->slotsForDate('2026-11-05', Carbon::parse('2026-11-01 08:00:00', 'Asia/Manila'));
    $json = json_encode($payload);

    expect($payload['date'])->toBe('2026-11-05')
        ->and($payload['clinic_today'])->toBeFalse()
        ->and($payload['fully_unavailable'])->toBeFalse()
        ->and($payload['slots'])->toHaveCount(18);

    foreach ($payload['slots'] as $slot) {
        expect(array_keys($slot))->toBe(['start', 'end', 'label', 'state']);
    }

    $byStart = collect($payload['slots'])->keyBy('start');

    expect($byStart['09:00']['state'])->toBe(ClinicAvailabilityService::STATE_UNAVAILABLE)
        ->and($byStart['09:30']['state'])->toBe(ClinicAvailabilityService::STATE_UNAVAILABLE)
        ->and($byStart['10:00']['state'])->toBe(ClinicAvailabilityService::STATE_AVAILABLE)
        ->and($byStart['14:30']['state'])->toBe(ClinicAvailabilityService::STATE_OCCUPIED)
        ->and($byStart['14:00']['state'])->toBe(ClinicAvailabilityService::STATE_AVAILABLE);

    expect($json)->not->toContain('Internal reason')
        ->and($json)->not->toContain('Exposed')
        ->and($json)->not->toContain('patient_id');
});

/*
|--------------------------------------------------------------------------
| 13. State precedence: past wins over closed/occupied
|--------------------------------------------------------------------------
*/

it('reports past slots as past even when the date is closed', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-05 15:00:00', 'Asia/Manila'));

    casService()->createClosure([
        'start_date' => '2026-11-05',
        'end_date' => '2026-11-05',
        'all_day' => true,
    ], casAdmin());

    expect(casService()->slotState('2026-11-05', '14:30'))->toBe(ClinicAvailabilityService::STATE_PAST)
        ->and(casService()->slotState('2026-11-05', '15:00'))->toBe(ClinicAvailabilityService::STATE_UNAVAILABLE);
});
