<?php

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\PrenatalVisit;
use App\Models\User;
use App\Models\UserPregnancyLink;
use App\Services\PregnancyContinuationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * DEPLA Phase 3H.3 - transactional staff-managed pregnancy continuation.
 *
 * Linked DELIVERED episodes hand their login to the new ONGOING episode
 * (pointer transfer + verified history links + structured audit, one
 * transaction); unlinked episodes continue unlinked. Portal behavior,
 * duplicate-ONGOING safeguards and rollback atomicity are covered on
 * isolated SQLite :memory: only. No patient-facing switching exists.
 */
beforeEach(function () {
    $this->withoutVite();
});

function phase3h3Staff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

function phase3h3Delivered(array $overrides = []): Patient
{
    return Patient::create(array_merge([
        'first_name' => 'Carla',
        'last_name' => 'Santos',
        'birthdate' => today()->subYears(30)->toDateString(),
        'age' => 30,
        'address' => 'Santa Maria, Bulacan',
        'contact_number' => '09181234567',
        'civil_status' => 'Married',
        'gravida' => 2,
        'para' => 1,
        'previous_cs' => 0,
        'miscarriage' => 0,
        'status' => 'DELIVERED',
        'delivery_date' => today()->subDays(5)->toDateString(),
    ], $overrides));
}

/**
 * Link the episode to a fresh patient-role login with BOTH the Phase 2A
 * pointer and the Phase 3H.2 verified link - the real post-3H.2 state.
 */
function phase3h3LinkAccount(Patient $patient, ?User $verifier = null): User
{
    $account = User::factory()->create(['role' => 'patient']);

    $patient->user_id = $account->id;
    $patient->save();

    $link = new UserPregnancyLink();
    $link->user_id = $account->id;
    $link->patient_id = $patient->id;
    $link->verified_by = $verifier?->id;
    $link->verified_at = now();
    $link->save();

    return $account;
}

function phase3h3Payload(array $overrides = []): array
{
    return array_merge([
        'lmp' => today()->subWeeks(10)->toDateString(),
        'edd' => today()->subWeeks(10)->addDays(280)->toDateString(),
        'address' => 'Purok 2, Barangay San Jose, San Jose del Monte',
        'contact_number' => '09181234567',
    ], $overrides);
}

function phase3h3AppointmentFor(Patient $patient): Appointment
{
    $appointment = new Appointment();
    $appointment->patient_id = $patient->id;
    $appointment->appointment_type = Appointment::TYPE_CHECKUP;
    $appointment->requested_date = today()->addDays(3)->toDateString();
    $appointment->source = Appointment::SOURCE_WALK_IN;
    $appointment->status = Appointment::STATUS_PENDING;
    $appointment->save();

    return $appointment;
}

/*
|--------------------------------------------------------------------------
| Linked continuation
|--------------------------------------------------------------------------
*/

it('creates one ONGOING episode from a linked DELIVERED episode and transfers the login pointer', function () {
    $staff = phase3h3Staff();
    $old = phase3h3Delivered();
    $account = phase3h3LinkAccount($old);

    $response = $this->actingAs($staff)
        ->post(route('patients.start-new-pregnancy', $old->id), phase3h3Payload());

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $new = Patient::where('status', 'ONGOING')->sole();

    expect($new->id)->not->toBe($old->id);
    expect($new->gravida)->toBe(3);
    expect($new->para)->toBe(1);
    expect($new->address)->toBe('Purok 2, Barangay San Jose, San Jose del Monte');

    // Old episode stays DELIVERED, keeps its clinical identity, loses ONLY
    // the current pointer.
    $old->refresh();
    expect($old->status)->toBe('DELIVERED');
    expect($old->delivery_date)->not->toBeNull();
    expect($old->user_id)->toBeNull();

    // New episode holds the same login.
    expect($new->user_id)->toBe($account->id);
    expect(Patient::count())->toBe(2);

    // Structured audit: actor, new episode in patient_id, ID-based
    // description with old/new/user IDs and the continuity statement.
    $log = AuditLog::sole();
    expect($log->user_id)->toBe($staff->id);
    expect($log->patient_id)->toBe($new->id);
    expect($log->action)->toBe('CREATE');
    expect($log->module)->toBe('PATIENT');
    expect($log->description)->toContain('patient ID ' . $new->id);
    expect($log->description)->toContain('patient ID ' . $old->id);
    expect($log->description)->toContain('user ID ' . $account->id);
    expect($log->description)->toContain('transferred to the new episode');
    expect($log->description)->not->toContain('Carla');
});

it('resolves the same login to the new episode after continuation', function () {
    $staff = phase3h3Staff();
    $old = phase3h3Delivered();
    $account = phase3h3LinkAccount($old);

    $this->actingAs($staff)
        ->post(route('patients.start-new-pregnancy', $old->id), phase3h3Payload())
        ->assertRedirect();

    $new = Patient::where('status', 'ONGOING')->sole();

    expect($account->fresh()->patientAccount()->sole()->id)->toBe($new->id);
});

it('keeps the verified history link on the old episode', function () {
    $staff = phase3h3Staff();
    $old = phase3h3Delivered();
    $account = phase3h3LinkAccount($old);

    $this->actingAs($staff)
        ->post(route('patients.start-new-pregnancy', $old->id), phase3h3Payload());

    $oldLink = UserPregnancyLink::where('patient_id', $old->id)->sole();

    expect($oldLink->user_id)->toBe($account->id);
});

it('creates the verified history link for the new episode with staff provenance', function () {
    $staff = phase3h3Staff();
    $old = phase3h3Delivered();
    $account = phase3h3LinkAccount($old);

    $this->actingAs($staff)
        ->post(route('patients.start-new-pregnancy', $old->id), phase3h3Payload());

    $new = Patient::where('status', 'ONGOING')->sole();
    $newLink = UserPregnancyLink::where('patient_id', $new->id)->sole();

    expect($newLink->user_id)->toBe($account->id);
    expect($newLink->verified_by)->toBe($staff->id);
    expect($newLink->verified_at)->not->toBeNull();

    // Exactly two links total: old episode + new episode, same account.
    expect(UserPregnancyLink::count())->toBe(2);
    expect(UserPregnancyLink::where('user_id', $account->id)->count())->toBe(2);
});

it('keeps the login account credentials unchanged through continuation', function () {
    $staff = phase3h3Staff();
    $old = phase3h3Delivered();
    $account = phase3h3LinkAccount($old);

    $emailBefore = $account->email;
    $passwordHashBefore = $account->password;
    $roleBefore = $account->role;

    $this->actingAs($staff)
        ->post(route('patients.start-new-pregnancy', $old->id), phase3h3Payload());

    $account->refresh();

    expect($account->email)->toBe($emailBefore);
    expect($account->password)->toBe($passwordHashBefore);
    expect($account->role)->toBe($roleBefore);
    expect(User::count())->toBe(2); // staff actor + patient account only
});

/*
|--------------------------------------------------------------------------
| Clinical records and appointments
|--------------------------------------------------------------------------
*/

it('leaves the old episode medical records unchanged', function () {
    $staff = phase3h3Staff();
    $old = phase3h3Delivered();
    phase3h3LinkAccount($old);

    $visit = PrenatalVisit::create([
        'patient_id' => $old->id,
        'visit_date' => today()->subDays(20)->toDateString(),
        'risk_level' => 'LOW',
    ]);

    $before = (array) DB::table('patients')->where('id', $old->id)->first();

    $this->actingAs($staff)
        ->post(route('patients.start-new-pregnancy', $old->id), phase3h3Payload());

    $after = (array) DB::table('patients')->where('id', $old->id)->first();

    unset($before['user_id'], $before['updated_at']);
    unset($after['user_id'], $after['updated_at']);

    expect($after)->toBe($before);
    expect(PrenatalVisit::count())->toBe(1);
    expect($visit->fresh()->risk_level)->toBe('LOW');
    expect($old->fresh()->delivery_date)->not->toBeNull();
});

it('keeps existing appointments on the old episode and scopes new appointments to the new episode', function () {
    $staff = phase3h3Staff();
    $old = phase3h3Delivered();
    phase3h3LinkAccount($old);

    $oldAppointment = phase3h3AppointmentFor($old);

    $this->actingAs($staff)
        ->post(route('patients.start-new-pregnancy', $old->id), phase3h3Payload());

    $new = Patient::where('status', 'ONGOING')->sole();

    expect($oldAppointment->fresh()->patient_id)->toBe($old->id);
    expect($old->fresh()->appointments()->count())->toBe(1);
    expect($new->appointments()->count())->toBe(0);

    $newAppointment = phase3h3AppointmentFor($new);

    expect($newAppointment->fresh()->patient_id)->toBe($new->id);
    expect($new->appointments()->count())->toBe(1);
    expect($old->fresh()->appointments()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Unlinked continuation
|--------------------------------------------------------------------------
*/

it('creates an unlinked new episode from an unlinked DELIVERED episode without inventing a login', function () {
    $staff = phase3h3Staff();
    $old = phase3h3Delivered(); // no user_id, no links

    $this->actingAs($staff)
        ->post(route('patients.start-new-pregnancy', $old->id), phase3h3Payload())
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $new = Patient::where('status', 'ONGOING')->sole();

    expect($new->user_id)->toBeNull();
    expect($old->fresh()->user_id)->toBeNull();
    expect(UserPregnancyLink::count())->toBe(0);

    // No patient-role account was invented.
    expect(User::where('role', 'patient')->count())->toBe(0);

    $log = AuditLog::sole();
    expect($log->patient_id)->toBe($new->id);
    expect($log->description)->toContain('No login account was linked');
    expect($log->description)->toContain('patient ID ' . $old->id);
});

it('refuses an unlinked episode that carries foreign verified-link evidence', function () {
    $staff = phase3h3Staff();
    $old = phase3h3Delivered(); // no pointer
    $foreign = User::factory()->create(['role' => 'patient']);

    $link = new UserPregnancyLink();
    $link->user_id = $foreign->id;
    $link->patient_id = $old->id;
    $link->verified_at = now();
    $link->save();

    $this->actingAs($staff)
        ->post(route('patients.start-new-pregnancy', $old->id), phase3h3Payload())
        ->assertSessionHasErrors('status');

    expect(session('errors')->first('status'))->toContain('conflicting account-link history');
    expect(Patient::where('status', 'ONGOING')->exists())->toBeFalse();
    expect(UserPregnancyLink::count())->toBe(1);
    expect(UserPregnancyLink::sole()->user_id)->toBe($foreign->id);
});

/*
|--------------------------------------------------------------------------
| Clinical and ownership refusals
|--------------------------------------------------------------------------
*/

it('rejects a non-DELIVERED episode', function () {
    $staff = phase3h3Staff();
    $ongoing = phase3h3Delivered(['status' => 'ONGOING', 'delivery_date' => null]);

    $this->actingAs($staff)
        ->post(route('patients.start-new-pregnancy', $ongoing->id), phase3h3Payload())
        ->assertSessionHasErrors('status');

    expect(session('errors')->first('status'))->toContain('delivered patient record');
    expect(Patient::count())->toBe(1);
});

it('rejects an archived old episode with the existing 404 behavior', function () {
    $staff = phase3h3Staff();
    $old = phase3h3Delivered();
    $old->delete();

    $this->actingAs($staff)
        ->post(route('patients.start-new-pregnancy', $old->id), phase3h3Payload())
        ->assertNotFound();

    expect(Patient::where('status', 'ONGOING')->exists())->toBeFalse();
});

it('refuses continuation when the linked login account is archived and changes nothing', function () {
    $staff = phase3h3Staff();
    $old = phase3h3Delivered();
    $account = phase3h3LinkAccount($old);
    $account->delete();

    $this->actingAs($staff)
        ->post(route('patients.start-new-pregnancy', $old->id), phase3h3Payload())
        ->assertSessionHasErrors('status');

    expect(session('errors')->first('status'))->toContain('archived');
    expect(Patient::where('status', 'ONGOING')->exists())->toBeFalse();
    expect($old->fresh()->user_id)->toBe($account->id);
    expect(UserPregnancyLink::count())->toBe(1);

    // The archived account was NOT restored or modified.
    expect($account->fresh()->trashed())->toBeTrue();
});

it('refuses continuation when another account holds verified-link evidence on the old episode', function () {
    $staff = phase3h3Staff();
    $old = phase3h3Delivered();
    $account = phase3h3LinkAccount($old);

    $intruder = User::factory()->create(['role' => 'patient']);

    // A second ownership row for the same episode (different account) is
    // constructible under the Phase 3H.2 pair-unique schema; the
    // continuation guard must refuse instead of reassigning or deleting
    // either row.
    $conflict = new UserPregnancyLink();
    $conflict->user_id = $intruder->id;
    $conflict->patient_id = $old->id;
    $conflict->verified_at = now();
    $conflict->save();

    $this->actingAs($staff)
        ->post(route('patients.start-new-pregnancy', $old->id), phase3h3Payload())
        ->assertRedirect();

    expect(session('errors')->first('status'))->toContain('conflicting account-link history');

    // No new episode, no pointer change, no link reassigned or deleted.
    expect(Patient::where('status', 'ONGOING')->exists())->toBeFalse();
    expect($old->fresh()->user_id)->toBe($account->id);
    expect(UserPregnancyLink::where('patient_id', $old->id)->count())->toBe(2)
        ->and(UserPregnancyLink::where('patient_id', $old->id)->where('user_id', $account->id)->count())->toBe(1)
        ->and(UserPregnancyLink::where('patient_id', $old->id)->where('user_id', $intruder->id)->count())->toBe(1);
});

it('rejects a duplicate ONGOING pregnancy for the same demographics', function () {
    $staff = phase3h3Staff();
    $old = phase3h3Delivered();

    Patient::create([
        'first_name' => $old->first_name,
        'last_name' => $old->last_name,
        'birthdate' => $old->birthdate,
        'age' => 30,
        'status' => 'ONGOING',
    ]);

    $this->actingAs($staff)
        ->post(route('patients.start-new-pregnancy', $old->id), phase3h3Payload())
        ->assertSessionHasErrors('status');

    expect(session('errors')->first('status'))->toContain('active ongoing pregnancy');
    expect(Patient::where('status', 'ONGOING')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Duplicate submissions and atomicity
|--------------------------------------------------------------------------
*/

it('refuses a repeated stale submission without creating a second episode', function () {
    $staff = phase3h3Staff();
    $old = phase3h3Delivered();
    $account = phase3h3LinkAccount($old);

    $service = app(PregnancyContinuationService::class);

    $new = $service->continueFromDelivered($old, $staff, phase3h3Payload());

    expect($new->id)->not->toBe($old->id);

    // Stale second submission: in-memory model still says DELIVERED with a
    // pointer, but the locked re-read sees the committed twin and refuses.
    expect(fn () => $service->continueFromDelivered($old, $staff, phase3h3Payload()))
        ->toThrow(DomainException::class, 'This patient already has an active ongoing pregnancy record.');

    expect(Patient::where('status', 'ONGOING')->count())->toBe(1);
    expect(Patient::count())->toBe(2);
    expect(UserPregnancyLink::count())->toBe(2);
    expect(AuditLog::count())->toBe(1);

    // HTTP duplicate submission is also refused by the same guard.
    $this->actingAs($staff)
        ->post(route('patients.start-new-pregnancy', $old->id), phase3h3Payload())
        ->assertSessionHasErrors('status');

    expect(Patient::where('status', 'ONGOING')->count())->toBe(1);
    expect($old->fresh()->user_id)->toBeNull();
    expect($new->fresh()->user_id)->toBe($account->id);
});

it('rolls back the entire continuation when verified-link creation fails', function () {
    $staff = phase3h3Staff();
    $old = phase3h3Delivered();
    $account = phase3h3LinkAccount($old);

    // Deterministic mid-transaction failure: the verified_by foreign key
    // rejects a non-existent actor at the NEW episode's link insert (after
    // episode creation and after the old episode's ensure-link step).
    $ghostActor = new User();
    $ghostActor->id = 424242;

    expect(fn () => app(PregnancyContinuationService::class)->continueFromDelivered(
        $old,
        $ghostActor,
        phase3h3Payload()
    ))->toThrow(QueryException::class);

    expect(Patient::count())->toBe(1);
    expect($old->fresh()->user_id)->toBe($account->id);
    expect(UserPregnancyLink::count())->toBe(1);
    expect(AuditLog::count())->toBe(0);
});

it('rolls back the entire continuation when audit logging fails', function () {
    $staff = phase3h3Staff();
    $old = phase3h3Delivered();
    $account = phase3h3LinkAccount($old);

    // Deterministic post-everything failure: the audit insert is the last
    // in-transaction write, so a failure here must undo the episode, both
    // links and the pointer transfer.
    AuditLog::creating(function (): void {
        throw new RuntimeException('audit failure injection');
    });

    expect(fn () => app(PregnancyContinuationService::class)->continueFromDelivered(
        $old,
        $staff,
        phase3h3Payload()
    ))->toThrow(RuntimeException::class, 'audit failure injection');

    expect(Patient::count())->toBe(1);
    expect($old->fresh()->user_id)->toBe($account->id);
    expect(UserPregnancyLink::count())->toBe(1);
    expect(AuditLog::count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Portal compatibility and switching prevention
|--------------------------------------------------------------------------
*/

it('keeps current portal authorization working against the new episode', function () {
    $staff = phase3h3Staff();
    $old = phase3h3Delivered();
    $account = phase3h3LinkAccount($old);

    $this->actingAs($staff)
        ->post(route('patients.start-new-pregnancy', $old->id), phase3h3Payload());

    $new = Patient::where('status', 'ONGOING')->sole();

    // The established session resolves the NEW episode with a 200. The
    // dashboard greets with the linked patient record's first name. (Guest
    // redirect behavior is unchanged and covered by the Phase 3A suite;
    // actingAs() pins the guard here, so no guest assertion runs after it.)
    $this->actingAs($account)
        ->get(route('patient.portal.coming-soon'))
        ->assertOk()
        ->assertSee('Welcome, ' . $old->first_name . '!', false);

    // Clinic roles keep the existing dashboard redirect.
    $this->actingAs(phase3h3Staff())->get(route('patient.portal.coming-soon'))
        ->assertRedirect(route('dashboard'));

    // The old episode is NOT portal-reachable through the pivot: the
    // account's pointer resolves the new episode only.
    expect($account->fresh()->patientAccount()->sole()->id)->toBe($new->id);
});

it('introduces no patient-controlled current-pregnancy switching', function () {
    $staff = phase3h3Staff();
    $old = phase3h3Delivered();
    $account = phase3h3LinkAccount($old);

    $this->actingAs($staff)
        ->post(route('patients.start-new-pregnancy', $old->id), phase3h3Payload());

    // Patient-role logins cannot reach the staff continuation endpoint.
    $this->actingAs($account)
        ->post(route('patients.start-new-pregnancy', $old->id), phase3h3Payload())
        ->assertForbidden();

    expect(Patient::where('status', 'ONGOING')->count())->toBe(1);

    // No patient-facing switching/history route exists anywhere.
    $switchRoutes = collect(Route::getRoutes())
        ->filter(function ($route): bool {
            $uri = $route->uri();

            return str_contains($uri, 'switch-pregnancy')
                || str_contains($uri, '/patient/pregnancies')
                || str_contains($uri, '/patient/history')
                || str_contains($uri, 'current-pregnancy');
        });

    expect($switchRoutes)->toHaveCount(0);

    // The pointer was never chosen from request input: the patient's
    // attempt above changed nothing.
    expect($old->fresh()->user_id)->toBeNull();
    expect($account->fresh()->patientAccount()->sole()->status)->toBe('ONGOING');
});
