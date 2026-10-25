<?php

use App\Mail\PatientAccountSetupMail;
use App\Models\Patient;
use App\Models\User;
use App\Services\PatientAccountCreationService;
use Illuminate\Support\Facades\Mail;

function automaticPortalStaff(): User
{
    return User::factory()->create(['role' => 'staff']);
}

function automaticPortalRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Ana',
        'middle_name' => '',
        'last_name' => 'Reyes',
        'birthdate' => '1995-01-01',
        'age' => 31,
        'address_line' => '12 Clinic Road',
        'barangay' => 'Poblacion',
        'city_municipality' => 'Santa Maria',
        'contact_number' => '09171234567',
        'email' => 'ana.registration@example.test',
        'civil_status' => 'Single',
        'philhealth_member' => '0',
        'philhealth_number' => '',
        'gravida' => 1,
        'para' => 0,
        'previous_cs' => '0',
        'miscarriage' => 0,
        'lmp' => today()->subWeeks(20)->toDateString(),
        'edd' => today()->subWeeks(20)->addDays(280)->toDateString(),
    ], $overrides);
}

beforeEach(function () {
    $this->withoutVite();
});

it('requires a valid email on the Add Patient form and registration endpoint', function () {
    $staff = automaticPortalStaff();

    $this->actingAs($staff)->get(route('patients.create'))
        ->assertOk()
        ->assertSee('name="email"', false)
        ->assertSee('required autocomplete="email"', false);

    $this->post(route('patients.store'), automaticPortalRegistrationPayload(['email' => '']))
        ->assertSessionHasErrors('email');

    expect(Patient::count())->toBe(0)
        ->and(User::where('role', 'patient')->count())->toBe(0);
});

it('normalizes registration email and automatically provisions a portal account and setup email', function () {
    Mail::fake();
    $staff = automaticPortalStaff();

    $response = $this->actingAs($staff)->post(route('patients.store'), automaticPortalRegistrationPayload([
        'email' => '  Ana.Registration@Example.Test  ',
    ]));

    $response->assertRedirect(route('patients.index'))
        ->assertSessionHas('success');

    $patient = Patient::sole();
    $account = User::where('role', 'patient')->sole();

    expect($patient->email)->toBe('ana.registration@example.test')
        ->and($patient->user_id)->toBe($account->id)
        ->and($account->email)->toBe('ana.registration@example.test')
        ->and($account->must_change_password)->toBeTrue();

    Mail::assertSent(PatientAccountSetupMail::class, function (PatientAccountSetupMail $mail): bool {
        return $mail->hasTo('ana.registration@example.test')
            && str_contains($mail->setupUrl, '/patient-setup/');
    });
});

it('preserves a patient when the email belongs to another portal account', function () {
    Mail::fake();
    $staff = automaticPortalStaff();
    $existing = User::factory()->create([
        'role' => 'patient',
        'email' => 'ana.registration@example.test',
    ]);
    $existing->forceFill(['must_change_password' => false])->save();

    $this->actingAs($staff)->post(route('patients.store'), automaticPortalRegistrationPayload())
        ->assertRedirect(route('patients.index'))
        ->assertSessionHas('warning');

    $patient = Patient::sole();
    expect($patient->user_id)->toBeNull()
        ->and($existing->fresh()->must_change_password)->toBeFalse()
        ->and(User::where('role', 'patient')->count())->toBe(1);

    Mail::assertNothingSent();
});

it('does not create a duplicate account when the registration form is submitted again', function () {
    Mail::fake();
    $staff = automaticPortalStaff();
    $payload = automaticPortalRegistrationPayload();

    $this->actingAs($staff)->post(route('patients.store'), $payload)->assertSessionHas('success');
    $this->post(route('patients.store'), $payload)->assertSessionHas('warning');

    expect(Patient::count())->toBe(2)
        ->and(User::where('role', 'patient')->count())->toBe(1)
        ->and(Patient::whereNotNull('user_id')->count())->toBe(1);

    Mail::assertSent(PatientAccountSetupMail::class, 1);
});

it('keeps patient and pending account when email delivery fails and allows a later resend', function () {
    $staff = automaticPortalStaff();
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('transport down'));

    $this->actingAs($staff)->post(route('patients.store'), automaticPortalRegistrationPayload())
        ->assertRedirect(route('patients.index'))
        ->assertSessionHas('warning');

    $patient = Patient::sole();
    $account = User::where('role', 'patient')->sole();
    expect($patient->user_id)->toBe($account->id)
        ->and($account->must_change_password)->toBeTrue();

    $this->travel(61)->seconds();
    Mail::fake();

    $this->post(route('patients.account.resend-setup-email', $patient->id))
        ->assertRedirect(route('patients.show', $patient->id))
        ->assertSessionHas('success');

    Mail::assertSent(PatientAccountSetupMail::class, 1);
    expect(User::where('role', 'patient')->count())->toBe(1);
});

it('keeps the patient record if unexpected account provisioning failure occurs', function () {
    $staff = automaticPortalStaff();
    $this->mock(PatientAccountCreationService::class)
        ->shouldReceive('createForPatient')
        ->once()
        ->andThrow(new RuntimeException('internal details must not reach the user'));

    $this->actingAs($staff)->post(route('patients.store'), automaticPortalRegistrationPayload())
        ->assertRedirect(route('patients.index'))
        ->assertSessionHas('warning')
        ->assertSessionMissing('success');

    expect(Patient::count())->toBe(1)
        ->and(User::where('role', 'patient')->count())->toBe(0);
});
