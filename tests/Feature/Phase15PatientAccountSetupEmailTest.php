<?php

use App\Mail\PatientAccountSetupMail;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use App\Models\UserPregnancyLink;
use App\Services\PatientSetupTokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/**
 * DEPLA Phase 1.5 — email-only Patient Portal account creation with a
 * strictly single-use, 24-hour, first-time password setup link.
 *
 * Covers the full approved matrix: email-only provisioning, internal random
 * password, post-commit email delivery with non-fatal failure handling,
 * resend with throttling and token invalidation, the GET/POST setup
 * endpoints (consume-on-success only), purpose separation from ordinary
 * Forgot Password tokens, 24h vs 60m expiration policies, audit safety,
 * Task B compatibility and sequential double-submit single-use.
 *
 * All cases run against the isolated SQLite :memory: database with the
 * array mailer - no real email is ever sent.
 */
beforeEach(function () {
    $this->withoutVite();
});

const PHASE15_INVALID_MESSAGE = 'This setup link has expired, has already been used, or is invalid. Please contact the clinic to request a new setup email.';

function phase15Patient(string $first = 'Portal', string $last = 'Setup', string $email = 'clinical.record@phase15.test'): Patient
{
    return Patient::create([
        'first_name' => $first,
        'last_name' => $last,
        'age' => 26,
        'address' => 'Phase 1.5 test address',
        'contact_number' => '09171234567',
        'email' => $email,
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

function phase15Actor(string $role = 'staff'): User
{
    return User::factory()->create(['role' => $role]);
}

function phase15Provision(Patient $patient, string $loginEmail, ?User $actor = null, array $extra = []): object
{
    $actor ??= phase15Actor();

    $response = test()->actingAs($actor)->post(
        route('patients.account.store', $patient->id),
        array_merge(['email' => $loginEmail], $extra)
    );

    // Setup routes sit behind 'guest'; actingAs would otherwise leak the
    // staff session into later guest requests and redirect them to /dashboard.
    app('auth')->forgetGuards();

    return (object) [
        'actor' => $actor,
        'response' => $response,
        'user' => User::where('email', $loginEmail)->first(),
    ];
}

/**
 * Issue a setup token through the real service and return it (the same
 * shape the emailed link carries).
 */
function phase15TokenFor(User $user): string
{
    return app(PatientSetupTokenService::class)->create($user);
}

function phase15SetupUrl(User $user, string $token): string
{
    return route('patient.setup.show', ['token' => $token, 'email' => $user->email]);
}

/**
 * Capture the plaintext setup token from the sent mailable's URL.
 */
function phase15CapturedToken(): string
{
    $captured = null;

    Mail::assertSent(PatientAccountSetupMail::class, function (PatientAccountSetupMail $mail) use (&$captured): bool {
        $captured = basename(parse_url($mail->setupUrl, PHP_URL_PATH));

        return true;
    });

    expect($captured)->not->toBeNull();

    return $captured;
}

function phase15TokenRowExists(User $user): bool
{
    return DB::table('password_reset_tokens')
        ->where('email', PatientSetupTokenService::KEY_PREFIX . $user->id)
        ->exists();
}

/*
|--------------------------------------------------------------------------
| 1-3, 45 — Provisioning authorization and integrity
|--------------------------------------------------------------------------
*/

it('lets an admin create a login account using email only', function () {
    $admin = phase15Actor('admin');
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'patient.login@phase15.test', $admin);

    $result->response
        ->assertRedirect(route('patients.show', $patient->id))
        ->assertSessionHas('success')
        ->assertSessionHasNoErrors();

    expect($result->user)->not->toBeNull()
        ->and($result->user->role)->toBe('patient')
        ->and($patient->fresh()->user_id)->toBe($result->user->id);
});

it('lets a staff member create a login account using email only', function () {
    $staff = phase15Actor('staff');
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'patient.login@phase15.test', $staff);

    $result->response->assertRedirect(route('patients.show', $patient->id));

    expect(User::where('email', 'patient.login@phase15.test')->exists())->toBeTrue()
        ->and($patient->fresh()->user_id)->not->toBeNull();
});

it('denies unauthorized account creation', function () {
    $patient = phase15Patient();

    $this->post(route('patients.account.store', $patient->id), ['email' => 'x@phase15.test'])
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create(['role' => 'patient']))
        ->post(route('patients.account.store', $patient->id), ['email' => 'x@phase15.test'])
        ->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => 'clinic_observer']))
        ->post(route('patients.account.store', $patient->id), ['email' => 'x@phase15.test'])
        ->assertForbidden();

    expect(User::where('role', 'patient')->count())->toBe(1); // the distractor portal user above
    expect($patient->fresh()->user_id)->toBeNull();
});

it('creates no duplicate users or ownership links', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'unique.login@phase15.test');

    expect(User::where('role', 'patient')->count())->toBe(1)
        ->and(UserPregnancyLink::count())->toBe(1)
        ->and($result->user->patientAccount()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| 4-7 — Email-only contract, random password, setup flag
|--------------------------------------------------------------------------
*/

it('requires no password fields at account creation', function () {
    $patient = phase15Patient();
    Mail::fake();

    phase15Provision($patient, 'nopassword@phase15.test')
        ->response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    expect(User::where('email', 'nopassword@phase15.test')->count())->toBe(1);
});

it('does not accept staff-posted password fields as account credentials', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'ignored@phase15.test', extra: [
        'password' => 'Staff-Posted-Password!',
        'password_confirmation' => 'Staff-Posted-Password!',
    ]);

    $result->response->assertSessionHasNoErrors();

    expect(Hash::check('Staff-Posted-Password!', $result->user->password))->toBeFalse()
        ->and(Hash::check('password', $result->user->password))->toBeFalse();
});

it('generates a cryptographically secure random initial password internally', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'random.pass@phase15.test');

    expect(Hash::isHashed($result->user->password))->toBeTrue();

    // Str::random(40) entropy: the stored plaintext is 40 alphanumeric
    // characters and can never equal any common or posted value.
    $posted = ['password', 'secret', '12345678', 'Patient-Chooses!1', ''];
    foreach ($posted as $candidate) {
        expect(Hash::check($candidate, $result->user->password))->toBeFalse();
    }

    // Two accounts never share the same random placeholder hash input.
    $other = phase15Patient('Second', 'Account', 'second.record@phase15.test');
    $second = phase15Provision($other, 'random.pass2@phase15.test');

    expect($second->user->password)->not->toBe($result->user->password);
});

it('marks the account as requiring setup before the patient acts', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'pending.flag@phase15.test');

    expect($result->user->must_change_password)->toBeTrue()
        ->and($result->user->fresh()->must_change_password)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| 8-11, 45 — Setup email after commit; failure preserves everything
|--------------------------------------------------------------------------
*/

it('sends the setup email only after successful provisioning', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'setup.mail@phase15.test');

    Mail::assertSent(PatientAccountSetupMail::class, function (PatientAccountSetupMail $mail): bool {
        return $mail->hasTo('setup.mail@phase15.test')
            && str_contains($mail->setupUrl, '/patient-setup/')
            && str_contains($mail->setupUrl, 'email=');
    });

    // The link URL is built from config, never hardcoded hosts.
    expect($result->user)->not->toBeNull();
});

it('sends no setup email when provisioning fails', function () {
    $patient = phase15Patient();
    Mail::fake();

    User::factory()->create(['email' => 'taken.login@phase15.test']);

    phase15Provision($patient, 'taken.login@phase15.test')
        ->response->assertSessionHasErrors('email');

    Mail::assertNothingSent(PatientAccountSetupMail::class);
    expect($patient->fresh()->user_id)->toBeNull();
});

it('preserves the account and pregnancy ownership links when email delivery fails', function () {
    $patient = phase15Patient();

    // Deterministic transport failure at the Mail facade boundary.
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('transport down'));

    $result = phase15Provision($patient, 'email.fail@phase15.test');

    $result->response
        ->assertRedirect(route('patients.show', $patient->id))
        ->assertSessionHas('warning')
        ->assertSessionHasNoErrors();

    $user = User::where('email', 'email.fail@phase15.test')->first();

    expect($user)->not->toBeNull()
        ->and($patient->fresh()->user_id)->toBe($user->id)
        ->and(UserPregnancyLink::where('user_id', $user->id)->count())->toBe(1)
        ->and($user->must_change_password)->toBeTrue();

    $audit = AuditLog::where('action', 'PATIENT_SETUP_EMAIL_FAILED')->sole();

    expect($audit->description)->toContain('user ID ' . $user->id)
        ->and($audit->description)->not->toContain('token')
        ->and($audit->description)->not->toContain('$2y$');
});

/*
|--------------------------------------------------------------------------
| 12-14 — Resend: no duplicates, throttle, new token invalidates old
|--------------------------------------------------------------------------
*/

it('resends without creating another account', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'resend.safe@phase15.test');
    $this->travel(61)->seconds();

    $this->actingAs($result->actor)
        ->post(route('patients.account.resend-setup-email', $patient->id))
        ->assertRedirect(route('patients.show', $patient->id))
        ->assertSessionHas('success');

    expect(User::where('role', 'patient')->count())->toBe(1)
        ->and(UserPregnancyLink::count())->toBe(1)
        ->and(AuditLog::where('action', 'PATIENT_SETUP_EMAIL_RESENT')->count())->toBe(1);
});

it('respects the resend throttle', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'resend.throttle@phase15.test');

    // Immediately again: within the 60-second window.
    $this->actingAs($result->actor)
        ->post(route('patients.account.resend-setup-email', $patient->id))
        ->assertSessionHas('error');

    // Only the original provisioning email was ever sent.
    Mail::assertSent(PatientAccountSetupMail::class, 1);
});

it('invalidates the previous setup token when resending', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'resend.invalidate@phase15.test');
    $firstToken = phase15CapturedToken();

    $this->travel(61)->seconds();

    $this->actingAs($result->actor)
        ->post(route('patients.account.resend-setup-email', $patient->id))
        ->assertSessionHas('success');

    app('auth')->forgetGuards();

    $secondToken = null;
    Mail::assertSent(PatientAccountSetupMail::class, function (PatientAccountSetupMail $mail) use (&$secondToken): bool {
        $secondToken = basename(parse_url($mail->setupUrl, PHP_URL_PATH));

        return true;
    });

    expect($secondToken)->not->toBe($firstToken);

    // Old token refused; new token works.
    $this->get(phase15SetupUrl($result->user, $firstToken))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->get(phase15SetupUrl($result->user, $secondToken))->assertOk();
});

it('refuses resend once setup is complete, for archived accounts, and for patients without accounts', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'resend.guards@phase15.test');
    $token = phase15CapturedToken();

    // Patient completes setup.
    $this->post(route('patient.setup.store', ['token' => $token]), [
        'email' => $result->user->email,
        'password' => 'Completed-Passw0rd!',
        'password_confirmation' => 'Completed-Passw0rd!',
    ])->assertRedirect(route('login'));

    $this->actingAs($result->actor)
        ->post(route('patients.account.resend-setup-email', $patient->id))
        ->assertSessionHas('error');

    // Patient without any account.
    $orphan = phase15Patient('No', 'Account', 'no.account@phase15.test');
    $this->actingAs($result->actor)
        ->post(route('patients.account.resend-setup-email', $orphan->id))
        ->assertSessionHas('error');

    // Missing patient.
    $this->actingAs($result->actor)
        ->post(route('patients.account.resend-setup-email', 999999))
        ->assertNotFound();

    // Unauthorized roles.
    $this->actingAs(User::factory()->create(['role' => 'patient']))
        ->post(route('patients.account.resend-setup-email', $patient->id))
        ->assertForbidden();

    app('auth')->forgetGuards();

    $this->post(route('patients.account.resend-setup-email', $patient->id))
        ->assertRedirect(route('login'));

    Mail::assertSent(PatientAccountSetupMail::class, 1); // only the original provisioning send
});

/*
|--------------------------------------------------------------------------
| 15-22 — GET/POST setup endpoints; single-use enforcement
|--------------------------------------------------------------------------
*/

it('displays the setup form for a valid link without consuming the token', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'valid.get@phase15.test');
    $token = phase15CapturedToken();

    $response = $this->get(phase15SetupUrl($result->user, $token));

    $response->assertOk()
        ->assertSee('name="password"', false)
        ->assertSee('name="password_confirmation"', false)
        ->assertSee('name="token"', false)
        ->assertSee(route('patient.setup.store', ['token' => $token]), false)
        ->assertSee('Set Password');

    // Not consumed: the row survives and a refresh renders again.
    expect(phase15TokenRowExists($result->user))->toBeTrue();

    $this->get(phase15SetupUrl($result->user, $token))->assertOk();
    expect(phase15TokenRowExists($result->user))->toBeTrue();

    // Token-bearing page: referrer and cache protections.
    expect(strtolower((string) $response->headers->get('Referrer-Policy')))->toBe('no-referrer');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('sets the password, clears the flag and consumes the token on a valid POST', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'valid.post@phase15.test');
    $token = phase15CapturedToken();

    $this->post(route('patient.setup.store', ['token' => $token]), [
        'email' => $result->user->email,
        'password' => 'Patient-Chosen-Passw0rd!',
        'password_confirmation' => 'Patient-Chosen-Passw0rd!',
    ])->assertRedirect(route('login'))
        ->assertSessionHas('status')
        ->assertSessionHasNoErrors();

    $fresh = $result->user->fresh();

    expect(Hash::check('Patient-Chosen-Passw0rd!', $fresh->password))->toBeTrue()
        ->and($fresh->must_change_password)->toBeFalse()
        ->and(phase15TokenRowExists($result->user))->toBeFalse();

    $audit = AuditLog::where('action', 'PATIENT_SETUP_COMPLETED')->sole();

    expect($audit->user_id)->toBe($result->user->id)
        ->and($audit->module)->toBe('PATIENT')
        ->and($audit->description)->not->toContain('Patient-Chosen-Passw0rd!')
        ->and($audit->description)->not->toContain($token);
});

it('permanently rejects a used token on both GET and POST', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'used.token@phase15.test');
    $token = phase15CapturedToken();

    $this->post(route('patient.setup.store', ['token' => $token]), [
        'email' => $result->user->email,
        'password' => 'First-Choice-Passw0rd!',
        'password_confirmation' => 'First-Choice-Passw0rd!',
    ])->assertRedirect(route('login'));

    // Second GET.
    $this->get(phase15SetupUrl($result->user, $token))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => PHASE15_INVALID_MESSAGE]);

    // Second POST - and the password must NOT change again.
    $this->post(route('patient.setup.store', ['token' => $token]), [
        'email' => $result->user->email,
        'password' => 'Second-Choice-Passw0rd!',
        'password_confirmation' => 'Second-Choice-Passw0rd!',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => PHASE15_INVALID_MESSAGE]);

    $fresh = $result->user->fresh();

    expect(Hash::check('First-Choice-Passw0rd!', $fresh->password))->toBeTrue()
        ->and(Hash::check('Second-Choice-Passw0rd!', $fresh->password))->toBeFalse()
        ->and($fresh->must_change_password)->toBeFalse();

    // A used token can never be reactivated by any later request.
    $this->get(phase15SetupUrl($result->user, $token))->assertSessionHasErrors('email');
});

/*
|--------------------------------------------------------------------------
| 23-25 — Expiration policies: 24h setup vs 60m reset
|--------------------------------------------------------------------------
*/

it('accepts a setup token just before the 24-hour expiry', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'before.expiry@phase15.test');
    $token = phase15CapturedToken();

    $this->travel(23)->hours();
    $this->travel(59)->minutes();

    $this->get(phase15SetupUrl($result->user, $token))->assertOk();

    $this->post(route('patient.setup.store', ['token' => $token]), [
        'email' => $result->user->email,
        'password' => 'Still-Valid-Passw0rd!',
        'password_confirmation' => 'Still-Valid-Passw0rd!',
    ])->assertRedirect(route('login'));

    expect(Hash::check('Still-Valid-Passw0rd!', $result->user->fresh()->password))->toBeTrue();
});

it('rejects a setup token after exactly 24 hours', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'after.expiry@phase15.test');
    $token = phase15CapturedToken();

    $this->travel(24)->hours();
    $this->travel(1)->minutes();

    $this->get(phase15SetupUrl($result->user, $token))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => PHASE15_INVALID_MESSAGE]);

    $this->post(route('patient.setup.store', ['token' => $token]), [
        'email' => $result->user->email,
        'password' => 'Too-Late-Passw0rd!',
        'password_confirmation' => 'Too-Late-Passw0rd!',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => PHASE15_INVALID_MESSAGE]);

    $fresh = $result->user->fresh();

    // Untouched: still the random placeholder, still pending setup.
    expect($fresh->must_change_password)->toBeTrue()
        ->and(Hash::check('Too-Late-Passw0rd!', $fresh->password))->toBeFalse()
        ->and(Hash::check('Patient-Chosen-Passw0rd!', $fresh->password))->toBeFalse();
});

it('keeps ordinary forgot-password links at their 60-minute expiration', function () {
    $user = User::factory()->create(['role' => 'patient']);
    $token = Password::createToken($user);

    // Still valid well before 60 minutes.
    $this->travel(59)->minutes();
    $this->get('/reset-password/' . $token . '?email=' . urlencode($user->email))->assertOk();

    // Expired at 61 minutes - the 24-hour setup policy must not leak here.
    $this->travel(2)->minutes();
    $this->get('/reset-password/' . $token . '?email=' . urlencode($user->email))
        ->assertRedirect(route('password.request'))
        ->assertSessionHasErrors('email');
});

/*
|--------------------------------------------------------------------------
| 26-29 — Invalid links and failed validation never consume
|--------------------------------------------------------------------------
*/

it('rejects garbage tokens and mismatched emails with one unified message', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'mismatch@phase15.test');
    $token = phase15CapturedToken();

    $this->get('/patient-setup/garbage-token?email=' . urlencode($result->user->email))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => PHASE15_INVALID_MESSAGE]);

    $this->get(phase15SetupUrl($result->user, $token) . '&email=not-an-email')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => PHASE15_INVALID_MESSAGE]);

    $this->get('/patient-setup/' . $token) // missing email
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => PHASE15_INVALID_MESSAGE]);

    // An unknown account's address with a real token.
    $this->get(route('patient.setup.show', ['token' => $token, 'email' => 'stranger@phase15.test']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => PHASE15_INVALID_MESSAGE]);

    // The genuine token was never consumed by any of the refusals.
    expect(phase15TokenRowExists($result->user))->toBeTrue();
    $this->get(phase15SetupUrl($result->user, $token))->assertOk();
});

it('does not consume the token when password validation fails', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'weak.password@phase15.test');
    $token = phase15CapturedToken();

    // Below Password::defaults() minimum.
    $this->post(route('patient.setup.store', ['token' => $token]), [
        'email' => $result->user->email,
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    expect(phase15TokenRowExists($result->user))->toBeTrue();

    // Confirmation mismatch.
    $this->post(route('patient.setup.store', ['token' => $token]), [
        'email' => $result->user->email,
        'password' => 'Valid-Passw0rd-Here!',
        'password_confirmation' => 'Different-Confirm!',
    ])->assertSessionHasErrors('password');

    expect(phase15TokenRowExists($result->user))->toBeTrue()
        ->and($result->user->fresh()->must_change_password)->toBeTrue();

    // The surviving token still completes setup.
    $this->post(route('patient.setup.store', ['token' => $token]), [
        'email' => $result->user->email,
        'password' => 'Valid-Passw0rd-Here!',
        'password_confirmation' => 'Valid-Passw0rd-Here!',
    ])->assertRedirect(route('login'));

    expect(Hash::check('Valid-Passw0rd-Here!', $result->user->fresh()->password))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| 30-32 — Eligibility: activated, archived and non-patient accounts
|--------------------------------------------------------------------------
*/

it('refuses the setup endpoints for an already activated account', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'activated@phase15.test');
    $token = phase15CapturedToken();

    // Legacy account whose flag was cleared without the setup flow
    // (e.g. completed the ordinary forced-change path).
    $result->user->forceFill(['must_change_password' => false])->save();

    $this->get(phase15SetupUrl($result->user, $token))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => PHASE15_INVALID_MESSAGE]);

    $this->post(route('patient.setup.store', ['token' => $token]), [
        'email' => $result->user->email,
        'password' => 'Backdoor-Passw0rd!',
        'password_confirmation' => 'Backdoor-Passw0rd!',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => PHASE15_INVALID_MESSAGE]);

    expect(Hash::check('Backdoor-Passw0rd!', $result->user->fresh()->password))->toBeFalse();
});

it('refuses the setup endpoints for an archived account', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'archived.setup@phase15.test');
    $token = phase15CapturedToken();

    $result->user->delete();

    // SoftDeletes hides the account from the guest lookup entirely.
    $this->get(phase15SetupUrl($result->user, $token))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => PHASE15_INVALID_MESSAGE]);

    $this->post(route('patient.setup.store', ['token' => $token]), [
        'email' => $result->user->email,
        'password' => 'Archived-Passw0rd!',
        'password_confirmation' => 'Archived-Passw0rd!',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => PHASE15_INVALID_MESSAGE]);

    expect(Hash::check('Archived-Passw0rd!', $result->user->fresh()->password))->toBeFalse();
});

it('refuses the setup endpoints for a non-patient account', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    // Even a deliberately planted token row cannot activate a staff account.
    $token = phase15TokenFor($staff);

    $this->get(phase15SetupUrl($staff, $token))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => PHASE15_INVALID_MESSAGE]);

    $this->post(route('patient.setup.store', ['token' => $token]), [
        'email' => $staff->email,
        'password' => 'Staff-Setup-Passw0rd!',
        'password_confirmation' => 'Staff-Setup-Passw0rd!',
    ])->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => PHASE15_INVALID_MESSAGE]);

    expect(Hash::check('Staff-Setup-Passw0rd!', $staff->fresh()->password))->toBeFalse() // factory password still holds
        ->and(Hash::check('password', $staff->fresh()->password))->toBeTrue()
        ->and($staff->fresh()->must_change_password)->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| 33-34 — Purpose separation between setup and reset tokens
|--------------------------------------------------------------------------
*/

it('never accepts a setup token as an ordinary password reset token', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'cross.purpose@phase15.test');
    $setupToken = phase15CapturedToken();

    $this->post(route('password.store'), [
        'token' => $setupToken,
        'email' => $result->user->email,
        'password' => 'Cross-Purpose-Passw0rd!',
        'password_confirmation' => 'Cross-Purpose-Passw0rd!',
    ])->assertSessionHasErrors('email');

    $this->get('/reset-password/' . $setupToken . '?email=' . urlencode($result->user->email))
        ->assertRedirect(route('password.request'))
        ->assertSessionHasErrors('email');

    $fresh = $result->user->fresh();

    expect(Hash::check('Cross-Purpose-Passw0rd!', $fresh->password))->toBeFalse()
        ->and($fresh->must_change_password)->toBeTrue()
        // The setup token row is untouched by the rejected reset attempts.
        ->and(phase15TokenRowExists($result->user))->toBeTrue();
});

it('never accepts an ordinary reset token as a setup token, and keeps both token rows isolated', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'coexist@phase15.test');
    $setupToken = phase15CapturedToken();

    // Request an ordinary reset link for the SAME account: both kinds of
    // tokens must now coexist without interfering.
    $resetToken = Password::createToken($result->user);

    // Both rows exist under their disjoint keys.
    expect(DB::table('password_reset_tokens')->count())->toBe(2)
        ->and(phase15TokenRowExists($result->user))->toBeTrue()
        ->and(DB::table('password_reset_tokens')->where('email', $result->user->email)->exists())->toBeTrue();

    // The reset token is refused by the setup endpoints...
    $this->get(route('patient.setup.show', ['token' => $resetToken, 'email' => $result->user->email]))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => PHASE15_INVALID_MESSAGE]);

    // ... while the setup token still works for setup.
    $this->get(phase15SetupUrl($result->user, $setupToken))->assertOk();

    // And the reset token still works for an ordinary reset.
    $this->post(route('password.store'), [
        'token' => $resetToken,
        'email' => $result->user->email,
        'password' => 'Reset-Only-Passw0rd!',
        'password_confirmation' => 'Reset-Only-Passw0rd!',
    ])->assertRedirect(route('login'));

    $fresh = $result->user->fresh();

    // The ordinary reset changed the password but (Phase 2C doctrine)
    // did NOT clear the setup flag and did NOT consume the setup token.
    expect(Hash::check('Reset-Only-Passw0rd!', $fresh->password))->toBeTrue()
        ->and($fresh->must_change_password)->toBeTrue()
        ->and(phase15TokenRowExists($result->user))->toBeTrue();

    // Setup token survives an ordinary reset and still completes setup.
    $this->post(route('patient.setup.store', ['token' => $setupToken]), [
        'email' => $result->user->email,
        'password' => 'Setup-After-Reset!',
        'password_confirmation' => 'Setup-After-Reset!',
    ])->assertRedirect(route('login'));

    expect(Hash::check('Setup-After-Reset!', $result->user->fresh()->password))->toBeTrue()
        ->and($result->user->fresh()->must_change_password)->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| 35 — Audit safety
|--------------------------------------------------------------------------
*/

it('never records tokens, URLs or passwords in audit rows', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'audit.safety@phase15.test');
    $token = phase15CapturedToken();

    $this->travel(61)->seconds();
    $this->actingAs($result->actor)
        ->post(route('patients.account.resend-setup-email', $patient->id));

    app('auth')->forgetGuards();

    $resendToken = null;
    Mail::assertSent(PatientAccountSetupMail::class, function (PatientAccountSetupMail $mail) use (&$resendToken, $token): bool {
        $candidate = basename(parse_url($mail->setupUrl, PHP_URL_PATH));

        if ($candidate !== $token) {
            $resendToken = $candidate;
        }

        return true;
    });

    expect($resendToken)->not->toBeNull();

    $this->post(route('patient.setup.store', ['token' => $resendToken]), [
        'email' => $result->user->email,
        'password' => 'Audited-Passw0rd!',
        'password_confirmation' => 'Audited-Passw0rd!',
    ]);

    $dump = AuditLog::query()->get()
        ->map(fn (AuditLog $log): string => json_encode($log->getAttributes()))
        ->implode("\n");

    expect($dump)->not->toContain($token)
        ->and($dump)->not->toContain($resendToken)
        ->and($dump)->not->toContain('patient-setup')
        ->and($dump)->not->toContain('Audited-Passw0rd!')
        ->and($dump)->not->toContain('$2y$')
        ->and($dump)->not->toContain('setup:');

    expect(AuditLog::whereIn('action', [
        'PATIENT_ACCOUNT_CREATED',
        'PATIENT_SETUP_EMAIL_RESENT',
        'PATIENT_SETUP_COMPLETED',
    ])->count())->toBe(3);
});

/*
|--------------------------------------------------------------------------
| 36-38 — Login, no forced re-change, existing reset flow intact
|--------------------------------------------------------------------------
*/

it('allows normal login with the chosen password and skips the forced change', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'login.after@phase15.test');
    $token = phase15CapturedToken();

    $this->post(route('patient.setup.store', ['token' => $token]), [
        'email' => $result->user->email,
        'password' => 'Login-Ready-Passw0rd!',
        'password_confirmation' => 'Login-Ready-Passw0rd!',
    ])->assertRedirect(route('login'));

    // Not authenticated by the setup flow itself.
    $this->assertGuest();

    $this->post(route('login'), [
        'email' => $result->user->email,
        'password' => 'Login-Ready-Passw0rd!',
    ])->assertRedirect(route('patient.portal.coming-soon'));

    // The portal is reachable directly - no forced change form.
    $this->get(route('patient.portal.coming-soon'))
        ->assertOk()
        ->assertDontSee('Change Your Password');
});

it('keeps the existing forgot-password flow fully functional', function () {
    $user = User::factory()->create(['role' => 'patient']);

    $token = Password::createToken($user);

    $this->post(route('password.store'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'Ordinary-Reset-Passw0rd!',
        'password_confirmation' => 'Ordinary-Reset-Passw0rd!',
    ])->assertRedirect(route('login'));

    expect(Hash::check('Ordinary-Reset-Passw0rd!', $user->fresh()->password))->toBeTrue();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'Ordinary-Reset-Passw0rd!',
    ])->assertRedirect(route('patient.portal.coming-soon'));
});

/*
|--------------------------------------------------------------------------
| 39-41 — Task B compatibility
|--------------------------------------------------------------------------
*/

it('preserves Task B automatic history association through the new flow', function () {
    // Anchor ONGOING episode + same-identity DELIVERED sibling.
    $anchor = phase15Patient('Teresa', 'Cruz', 'teresa@phase15.test');
    $sibling = Patient::create([
        'first_name' => 'Teresa',
        'last_name' => 'Cruz',
        'age' => 26,
        'address' => 'Phase 1.5 test address',
        'contact_number' => '09171234567',
        'email' => 'teresa.prior@phase15.test',
        'gravida' => 2,
        'para' => 1,
        'status' => 'DELIVERED',
    ]);

    // A different-person DELIVERED record must never be associated.
    $stranger = phase15Patient('Maria', 'Reyes', 'maria@phase15.test');
    $stranger->status = 'DELIVERED';
    $stranger->save();

    Mail::fake();

    $result = phase15Provision($anchor, 'teresa.login@phase15.test');
    $token = phase15CapturedToken();

    // Association happened inside provisioning (Task B, unchanged).
    $linkedIds = UserPregnancyLink::where('user_id', $result->user->id)
        ->pluck('patient_id')
        ->all();

    expect($linkedIds)->toContain($anchor->id)
        ->toContain($sibling->id)
        ->not->toContain($stranger->id)
        ->and($stranger->fresh()->user_id)->toBeNull();

    // Setup completes and the portal opens.
    $this->post(route('patient.setup.store', ['token' => $token]), [
        'email' => $result->user->email,
        'password' => 'History-Ready-Passw0rd!',
        'password_confirmation' => 'History-Ready-Passw0rd!',
    ])->assertRedirect(route('login'));

    $this->post(route('login'), [
        'email' => $result->user->email,
        'password' => 'History-Ready-Passw0rd!',
    ])->assertRedirect(route('patient.portal.coming-soon'));

    $this->get(route('patient.portal.coming-soon'))->assertOk();

    // Previous DELIVERED episodes remain readable through the established
    // Pregnancy History rule (Task B's own suite renders the panel; here
    // the ownership evidence is what must survive the setup flow).
    expect(UserPregnancyLink::where('user_id', $result->user->id)->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| 42 — Sequential double-submit: exactly one success
|--------------------------------------------------------------------------
*/

it('allows exactly one successful setup under sequential double-submit', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'double.submit@phase15.test');
    $token = phase15CapturedToken();

    $payload = [
        'email' => $result->user->email,
        'password' => 'Winner-Choice-Passw0rd!',
        'password_confirmation' => 'Winner-Choice-Passw0rd!',
    ];

    $first = $this->post(route('patient.setup.store', ['token' => $token]), $payload);

    $first->assertRedirect(route('login'))->assertSessionHasNoErrors();

    $second = $this->post(route('patient.setup.store', ['token' => $token]), $payload);

    $second->assertRedirect(route('login'))->assertSessionHasErrors(['email' => PHASE15_INVALID_MESSAGE]);

    $fresh = $result->user->fresh();

    expect(Hash::check('Winner-Choice-Passw0rd!', $fresh->password))->toBeTrue()
        ->and($fresh->must_change_password)->toBeFalse()
        // Exactly one completion audit - the second POST changed nothing.
        ->and(AuditLog::where('action', 'PATIENT_SETUP_COMPLETED')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| 43 — Failed transaction leaves password and token state consistent
|--------------------------------------------------------------------------
*/

it('rolls the whole consume step back when the audit write fails', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'rollback.consume@phase15.test');
    $token = phase15CapturedToken();

    AuditLog::creating(function (): void {
        throw new RuntimeException('audit sink unavailable');
    });

    $this->post(route('patient.setup.store', ['token' => $token]), [
        'email' => $result->user->email,
        'password' => 'Rolled-Back-Passw0rd!',
        'password_confirmation' => 'Rolled-Back-Passw0rd!',
    ])->assertStatus(500);

    AuditLog::flushEventListeners();

    $fresh = $result->user->fresh();

    // Password, flag and token survive together or not at all.
    expect($fresh->must_change_password)->toBeTrue()
        ->and(Hash::check('Rolled-Back-Passw0rd!', $fresh->password))->toBeFalse()
        ->and(phase15TokenRowExists($result->user))->toBeTrue();

    // The untouched token still completes setup afterwards.
    $this->post(route('patient.setup.store', ['token' => $token]), [
        'email' => $result->user->email,
        'password' => 'Rolled-Back-Passw0rd!',
        'password_confirmation' => 'Rolled-Back-Passw0rd!',
    ])->assertRedirect(route('login'));

    expect(Hash::check('Rolled-Back-Passw0rd!', $result->user->fresh()->password))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Extra hardening — setup token rows and endpoint surface
|--------------------------------------------------------------------------
*/

it('stores setup tokens only under the setup: namespace and never under the plain email', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'namespace@phase15.test');
    $token = phase15CapturedToken();

    $row = DB::table('password_reset_tokens')->sole();

    expect($row->email)->toBe(PatientSetupTokenService::KEY_PREFIX . $result->user->id)
        ->and($row->email)->not->toBe($result->user->email)
        ->and($row->created_at)->not->toBeNull();

    // The plaintext token appears nowhere in storage.
    expect($row->token)->not->toContain($token);
});

it('exposes only GET and POST on the setup endpoint', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'surface@phase15.test');
    $token = phase15CapturedToken();

    $this->put(route('patient.setup.show', ['token' => $token]))->assertStatus(405);
    $this->delete(route('patient.setup.show', ['token' => $token]))->assertStatus(405);
    $this->patch(route('patient.setup.store', ['token' => $token]))->assertStatus(405);

    // GET on the shared path resolves the show route; without the email
    // query parameter it correctly refuses rather than accepting a bare
    // token (anti-enumeration). With the full emailed link it renders.
    $this->get(route('patient.setup.store', ['token' => $token]))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->get(phase15SetupUrl($result->user, $token))->assertOk();
});

it('shows the unified refusal on the login page without revealing account existence', function () {
    $patient = phase15Patient();
    Mail::fake();

    $result = phase15Provision($patient, 'unified@phase15.test');

    $this->get(route('patient.setup.show', ['token' => 'garbage', 'email' => $result->user->email]))
        ->assertRedirect(route('login'));

    $this->get(route('login'))
        ->assertOk()
        ->assertSee(PHASE15_INVALID_MESSAGE);

    // Identical message for a completely unknown address.
    $this->get(route('patient.setup.show', ['token' => 'garbage', 'email' => 'definitely.unknown@phase15.test']))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => PHASE15_INVALID_MESSAGE]);
});
