<?php

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/*
|--------------------------------------------------------------------------
| DEPLA Phase 2C — mandatory first-login password change
|--------------------------------------------------------------------------
| Server-side enforcement of must_change_password: a patient-role login is
| held on the dedicated change form until the flag is cleared, the change is
| audited atomically, the portal refuses unlinked/archived patient logins,
| and every pre-existing Admin/Staff/guest behavior stays untouched.
| All cases run against the isolated SQLite :memory: database.
*/

beforeEach(function () {
    $this->withoutVite();
});

function phase2cPatient(string $first = 'Portal', string $last = 'Candidate'): Patient
{
    return Patient::create([
        'first_name' => $first,
        'last_name' => $last,
        'age' => 25,
        'address' => 'Phase 2C test address',
        'contact_number' => '09171234567',
        'email' => strtolower($first).'.lane@phase2c.test',
        'gravida' => 1,
        'para' => 0,
        'status' => 'ONGOING',
    ]);
}

/**
 * must_change_password is deliberately NOT mass assignable (Phase 2B), so the
 * flag can only ever be written through a direct property write - the same
 * shape production code uses.
 */
function phase2cUser(string $role = 'patient', bool $flagged = false): User
{
    $user = User::factory()->create(['role' => $role]);

    if ($flagged) {
        $user->forceFill(['must_change_password' => true])->save();
    }

    return $user;
}

function phase2cLink(Patient $patient, User $user): void
{
    $patient->user_id = $user->id;
    $patient->save();
}

/**
 * A flagged patient login linked to a live patients record: the state a
 * Phase 2B account has immediately after provisioning.
 *
 * @return array{0: User, 1: Patient}
 */
function phase2cFlaggedPatient(): array
{
    $user = phase2cUser('patient', true);
    $patient = phase2cPatient();
    phase2cLink($patient, $user);

    return [$user, $patient];
}

function phase2cChangePayload(array $overrides = []): array
{
    return array_merge([
        'current_password' => 'password',
        'password' => 'Brand-New-Passw0rd!',
        'password_confirmation' => 'Brand-New-Passw0rd!',
    ], $overrides);
}

/*
|--------------------------------------------------------------------------
| 1. Enforcement — the flag is held on the dedicated form
|--------------------------------------------------------------------------
*/

it('sends a flagged patient from the portal holding page to the change form', function () {
    [$user] = phase2cFlaggedPatient();

    $this->actingAs($user)
        ->get(route('patient.portal.coming-soon'))
        ->assertRedirect(route('patient.change-initial-password.edit'));
});

it('keeps an unflagged linked patient on the portal holding page', function () {
    $user = phase2cUser('patient');
    phase2cLink(phase2cPatient(), $user);

    $this->actingAs($user)
        ->get(route('patient.portal.coming-soon'))
        ->assertOk()
        ->assertSee('not available yet');
});

it('walks a flagged patient from login through the portal to the change form', function () {
    [$user] = phase2cFlaggedPatient();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('patient.portal.coming-soon'));

    $this->get(route('patient.portal.coming-soon'))
        ->assertRedirect(route('patient.change-initial-password.edit'));

    $this->get(route('patient.change-initial-password.edit'))
        ->assertOk()
        ->assertSee('Current Password', false);
});

it('blocks the alternate PUT /password path for a flagged patient', function () {
    [$user] = phase2cFlaggedPatient();

    $this->actingAs($user)
        ->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'Sneaky-New-Passw0rd!',
            'password_confirmation' => 'Sneaky-New-Passw0rd!',
        ])
        ->assertRedirect(route('patient.change-initial-password.edit'));

    $fresh = $user->fresh();

    expect($fresh->must_change_password)->toBeTrue()
        ->and(Hash::check('password', $fresh->password))->toBeTrue()
        ->and(Hash::check('Sneaky-New-Passw0rd!', $fresh->password))->toBeFalse();
});

it('blocks a flagged patient from the password confirmation and email verification routes', function () {
    [$user] = phase2cFlaggedPatient();

    $this->actingAs($user)
        ->get(route('password.confirm'))
        ->assertRedirect(route('patient.change-initial-password.edit'));

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertRedirect(route('patient.change-initial-password.edit'));
});

it('keeps every clinic route denied for a flagged patient', function () {
    [$user] = phase2cFlaggedPatient();

    $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    $this->actingAs($user)->get(route('patients.index'))->assertForbidden();
    $this->actingAs($user)->get(route('profile.edit'))->assertForbidden();

    // The role-aware root redirect is unchanged; the portal then forces the form.
    $this->actingAs($user)->get('/')->assertRedirect(route('patient.portal.coming-soon'));
});

it('still lets a flagged patient sign out', function () {
    [$user] = phase2cFlaggedPatient();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});

it('never applies the flag rule to staff, admin or unknown roles', function () {
    $staff = phase2cUser('staff', true);
    $admin = phase2cUser('admin', true);
    $unknown = phase2cUser('superuser', true);

    // A flagged STAFF account keeps working exactly as before: the flag only
    // ever means something on role 'patient'.
    $this->actingAs($staff)->get(route('dashboard'))->assertOk();
    $this->actingAs($staff)->put(route('password.update'), [
        'current_password' => 'password',
        'password' => 'Staff-New-Passw0rd!',
        'password_confirmation' => 'Staff-New-Passw0rd!',
    ])->assertSessionHas('status', 'password-updated');

    expect(Hash::check('Staff-New-Passw0rd!', $staff->fresh()->password))->toBeTrue()
        ->and($staff->fresh()->must_change_password)->toBeTrue();

    // Portal destinations for non-patients are unchanged by both new rules.
    $this->actingAs($admin)->get(route('patient.portal.coming-soon'))->assertRedirect(route('dashboard'));
    $this->actingAs($unknown)->get(route('patient.portal.coming-soon'))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| 2. The change form (GET)
|--------------------------------------------------------------------------
*/

it('renders the change form for a flagged patient without any clinic navigation', function () {
    [$user] = phase2cFlaggedPatient();

    $response = $this->actingAs($user)
        ->get(route('patient.change-initial-password.edit'));

    $response->assertOk()
        ->assertSee('Change Your Password', false)
        ->assertSee('Current Password', false)
        ->assertSee('New Password', false)
        ->assertSee('Confirm New Password', false)
        ->assertSee('action="'.route('patient.change-initial-password.update').'"', false)
        ->assertSee('autocomplete="current-password"', false)
        ->assertSee('autocomplete="new-password"', false)
        ->assertSee('Sign Out instead', false)
        ->assertDontSee('sidebar-nav')
        ->assertDontSee('Manage Staff')
        ->assertDontSee('Audit Logs')
        ->assertDontSee('Prenatal Visits');

    // The form must never be cacheable: a stale page must not be able to
    // resubmit a password change. (Symfony re-orders the directives, so the
    // directive itself is asserted rather than the whole header string.)
    expect($response->headers->get('Cache-Control'))
        ->toContain('no-store')
        ->toContain('private');

    expect($response->getContent())->not->toContain('name="password" value');
});

it('forwards a patient whose flag is already cleared back to the portal', function () {
    $user = phase2cUser('patient');
    phase2cLink(phase2cPatient(), $user);

    $this->actingAs($user)
        ->get(route('patient.change-initial-password.edit'))
        ->assertRedirect(route('patient.portal.coming-soon'));
});

it('refuses the change form to staff, admin and unknown roles', function () {
    $staff = phase2cUser('staff');
    $admin = phase2cUser('admin');
    $unknown = phase2cUser('superuser');

    $this->actingAs($staff)->get(route('patient.change-initial-password.edit'))->assertForbidden();
    $this->actingAs($admin)->get(route('patient.change-initial-password.edit'))->assertForbidden();
    $this->actingAs($unknown)->get(route('patient.change-initial-password.edit'))->assertForbidden();
});

it('sends a guest to the login page for the change form', function () {
    $this->get(route('patient.change-initial-password.edit'))
        ->assertRedirect(route('login'));

    $this->post(route('patient.change-initial-password.update'), phase2cChangePayload())
        ->assertRedirect(route('login'));
});

/*
|--------------------------------------------------------------------------
| 3. Applying the change (POST — success path)
|--------------------------------------------------------------------------
*/

it('changes the password, clears the flag and lands the patient on the portal', function () {
    [$user] = phase2cFlaggedPatient();

    $this->actingAs($user)
        ->post(route('patient.change-initial-password.update'), phase2cChangePayload())
        ->assertRedirect(route('patient.portal.coming-soon'))
        ->assertSessionHas('status');

    $fresh = $user->fresh();

    expect($fresh->must_change_password)->toBeFalse()
        ->and(Hash::check('Brand-New-Passw0rd!', $fresh->password))->toBeTrue()
        ->and(Hash::check('password', $fresh->password))->toBeFalse();

    // The flow completes: the portal is now reachable and still shows no data.
    $this->get(route('patient.portal.coming-soon'))
        ->assertOk()
        ->assertSee('not available yet');
});

it('writes exactly one audit entry naming the account and patient but no secret', function () {
    [$user, $patient] = phase2cFlaggedPatient();

    $this->actingAs($user)
        ->post(route('patient.change-initial-password.update'), phase2cChangePayload())
        ->assertRedirect(route('patient.portal.coming-soon'));

    $log = AuditLog::where('action', 'PATIENT_INITIAL_PASSWORD_CHANGED')->sole();

    expect($log->user_id)->toBe($user->id)
        ->and($log->patient_id)->toBe($patient->id)
        ->and($log->module)->toBe('PATIENT')
        ->and($log->description)->toContain('user ID '.$user->id)
        ->and($log->description)->not->toContain('Brand-New-Passw0rd!')
        ->and($log->description)->not->toContain('Current-Passw0rd');
});

it('rotates the session id when the password changes', function () {
    [$user] = phase2cFlaggedPatient();

    $pinnedSessionId = str_repeat('a', 40);
    $this->withCookies([config('session.cookie') => $pinnedSessionId]);

    // Premise check: the pinned cookie IS adopted as the session id, so the
    // assertion after the change below is meaningful rather than trivial.
    $this->actingAs($user)
        ->get(route('patient.change-initial-password.edit'))
        ->assertOk();
    expect(session()->getId())->toBe($pinnedSessionId);

    $this->post(route('patient.change-initial-password.update'), phase2cChangePayload())
        ->assertRedirect(route('patient.portal.coming-soon'));

    expect(session()->getId())->not->toBe($pinnedSessionId);
});

/*
|--------------------------------------------------------------------------
| 4. Applying the change (POST — rejected submissions change nothing)
|--------------------------------------------------------------------------
*/

it('rejects a wrong current password and leaves the account untouched', function () {
    [$user] = phase2cFlaggedPatient();

    $this->actingAs($user)
        ->post(route('patient.change-initial-password.update'), phase2cChangePayload([
            'current_password' => 'not-my-password',
        ]))
        ->assertSessionHasErrors('current_password');

    $fresh = $user->fresh();

    expect($fresh->must_change_password)->toBeTrue()
        ->and(Hash::check('password', $fresh->password))->toBeTrue()
        ->and(Hash::check('Brand-New-Passw0rd!', $fresh->password))->toBeFalse();

    expect(AuditLog::where('action', 'PATIENT_INITIAL_PASSWORD_CHANGED')->count())->toBe(0);
});

it('rejects a new password that is identical to the current one', function () {
    [$user] = phase2cFlaggedPatient();

    $this->actingAs($user)
        ->post(route('patient.change-initial-password.update'), phase2cChangePayload([
            'password' => 'password',
            'password_confirmation' => 'password',
        ]))
        ->assertSessionHasErrors('password');

    expect($user->fresh()->must_change_password)->toBeTrue()
        ->and(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('rejects a new password below the password policy minimum', function () {
    [$user] = phase2cFlaggedPatient();

    $this->actingAs($user)
        ->post(route('patient.change-initial-password.update'), phase2cChangePayload([
            'password' => 'abc1234',
            'password_confirmation' => 'abc1234',
        ]))
        ->assertSessionHasErrors('password');

    expect($user->fresh()->must_change_password)->toBeTrue()
        ->and(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

it('rejects a mismatched password confirmation', function () {
    [$user] = phase2cFlaggedPatient();

    $this->actingAs($user)
        ->post(route('patient.change-initial-password.update'), phase2cChangePayload([
            'password_confirmation' => 'Different-Confirm!',
        ]))
        ->assertSessionHasErrors('password');

    expect($user->fresh()->must_change_password)->toBeTrue();
});

it('requires all three fields', function () {
    [$user] = phase2cFlaggedPatient();

    $this->actingAs($user)
        ->post(route('patient.change-initial-password.update'), [])
        ->assertSessionHasErrors([
            'current_password',
            'password',
            'password_confirmation',
        ]);

    expect($user->fresh()->must_change_password)->toBeTrue()
        ->and(Hash::check('password', $user->fresh()->password))->toBeTrue();

    expect(AuditLog::where('action', 'PATIENT_INITIAL_PASSWORD_CHANGED')->count())->toBe(0);
});

it('never repopulates a password field after a rejected submission', function () {
    [$user] = phase2cFlaggedPatient();

    $this->actingAs($user)
        ->post(route('patient.change-initial-password.update'), phase2cChangePayload([
            'password' => 'abc1234',
            'password_confirmation' => 'nope',
        ]))
        ->assertSessionHasErrors('password');

    $content = $this->get(route('patient.change-initial-password.edit'))
        ->assertOk()
        ->getContent();

    expect($content)->not->toContain('name="password" value')
        ->and($content)->not->toContain('name="current_password" value')
        ->and($content)->not->toContain('name="password_confirmation" value');
});

/*
|--------------------------------------------------------------------------
| 5. Applying the change (POST — authorization and the already-cleared flag)
|--------------------------------------------------------------------------
*/

it('refuses the change submission from staff, admin and unknown roles', function () {
    $staff = phase2cUser('staff');
    $admin = phase2cUser('admin');
    $unknown = phase2cUser('superuser');
    $payload = phase2cChangePayload();

    $this->actingAs($staff)->post(route('patient.change-initial-password.update'), $payload)->assertForbidden();
    $this->actingAs($admin)->post(route('patient.change-initial-password.update'), $payload)->assertForbidden();
    $this->actingAs($unknown)->post(route('patient.change-initial-password.update'), $payload)->assertForbidden();

    expect(AuditLog::where('action', 'PATIENT_INITIAL_PASSWORD_CHANGED')->count())->toBe(0);
});

it('forwards an already-changed patient without touching the account, even for a bad payload', function () {
    $user = phase2cUser('patient');
    phase2cLink(phase2cPatient(), $user);

    // The current password is deliberately WRONG: authorization (flag cleared)
    // must resolve the request before any validation runs, so nothing can be
    // changed and nothing can be flashed.
    $this->actingAs($user)
        ->post(route('patient.change-initial-password.update'), phase2cChangePayload([
            'current_password' => 'definitely-wrong',
            'password' => 'No-Change-Here!',
            'password_confirmation' => 'No-Change-Here!',
        ]))
        ->assertRedirect(route('patient.portal.coming-soon'))
        ->assertSessionHasNoErrors();

    $fresh = $user->fresh();

    expect(Hash::check('password', $fresh->password))->toBeTrue()
        ->and(Hash::check('No-Change-Here!', $fresh->password))->toBeFalse()
        ->and($fresh->must_change_password)->toBeFalse();

    expect(AuditLog::where('action', 'PATIENT_INITIAL_PASSWORD_CHANGED')->count())->toBe(0);
});

it('rolls the whole change back when the audit entry cannot be written', function () {
    [$user] = phase2cFlaggedPatient();

    AuditLog::creating(function (): void {
        throw new RuntimeException('audit sink unavailable');
    });

    $this->actingAs($user)
        ->post(route('patient.change-initial-password.update'), phase2cChangePayload())
        ->assertStatus(500);

    $fresh = $user->fresh();

    // Password and flag survive together or not at all: a patient is never
    // left with a new password and a stuck flag.
    expect($fresh->must_change_password)->toBeTrue()
        ->and(Hash::check('password', $fresh->password))->toBeTrue()
        ->and(Hash::check('Brand-New-Passw0rd!', $fresh->password))->toBeFalse();

    expect(AuditLog::where('action', 'PATIENT_INITIAL_PASSWORD_CHANGED')->count())->toBe(0);

    AuditLog::flushEventListeners();
});

/*
|--------------------------------------------------------------------------
| 6. Patient Portal eligibility (deny by default)
|--------------------------------------------------------------------------
*/

it('denies the portal to a patient login with no linked patient record', function () {
    $user = phase2cUser('patient');

    $this->actingAs($user)->get(route('patient.portal.coming-soon'))->assertForbidden();
});

it('denies the portal to a patient login linked to an archived patient record', function () {
    $user = phase2cUser('patient');
    $patient = phase2cPatient();
    phase2cLink($patient, $user);

    $patient->delete();
    expect($patient->trashed())->toBeTrue();

    $this->actingAs($user)->get(route('patient.portal.coming-soon'))->assertForbidden();
});

it('keeps the change form open for a flagged patient while the portal stays closed', function () {
    // Documented ordering: portal eligibility gates the holding page only.
    // The credential flow is never blocked, so a flagged patient can still
    // rotate the password even without (or before) a live record link.
    $user = phase2cUser('patient', true);

    $this->actingAs($user)->get(route('patient.change-initial-password.edit'))->assertOk();
    $this->actingAs($user)->get(route('patient.portal.coming-soon'))->assertForbidden();
});

it('still allows a flagged patient to change the password when the record is archived', function () {
    $user = phase2cUser('patient', true);
    $patient = phase2cPatient();
    phase2cLink($patient, $user);
    $patient->delete();

    $this->actingAs($user)
        ->post(route('patient.change-initial-password.update'), phase2cChangePayload())
        ->assertRedirect(route('patient.portal.coming-soon'));

    expect($user->fresh()->must_change_password)->toBeFalse();

    // ... and the archived link still keeps the portal closed afterwards.
    $this->get(route('patient.portal.coming-soon'))->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| 7. Login email normalization
|--------------------------------------------------------------------------
*/

it('accepts a login with surrounding whitespace and mixed case', function () {
    $user = phase2cUser('patient');
    phase2cLink(phase2cPatient(), $user);

    $this->post(route('login'), [
        'email' => '   '.strtoupper($user->email).'  ',
        'password' => 'password',
    ])->assertRedirect(route('patient.portal.coming-soon'));

    $this->get(route('patient.portal.coming-soon'))->assertOk();
});

it('still rejects wrong credentials typed in a case variant', function () {
    $user = phase2cUser('patient');

    $this->post(route('login'), [
        'email' => strtoupper($user->email),
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('normalizes staff logins the same way', function () {
    $staff = phase2cUser('staff');

    $this->post(route('login'), [
        'email' => '  '.strtoupper($staff->email),
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));
});

/*
|--------------------------------------------------------------------------
| 8. Password reset interaction (flag preservation)
|--------------------------------------------------------------------------
*/

it('keeps the mandatory flag set after a completed password reset', function () {
    [$user] = phase2cFlaggedPatient();

    $token = Password::createToken($user);

    $this->post(route('password.store'), [
        'token' => $token,
        'email' => strtoupper($user->email),
        'password' => 'Reset-Passw0rd!',
        'password_confirmation' => 'Reset-Passw0rd!',
    ])->assertRedirect(route('login'));

    $fresh = $user->fresh();

    expect($fresh->must_change_password)->toBeTrue()
        ->and(Hash::check('Reset-Passw0rd!', $fresh->password))->toBeTrue();

    // The enforcement still applies on the next visit to the portal.
    $this->actingAs($fresh)
        ->get(route('patient.portal.coming-soon'))
        ->assertRedirect(route('patient.change-initial-password.edit'));
});

it('leaves the flag untouched when a reset link is only requested', function () {
    [$user] = phase2cFlaggedPatient();

    $this->post(route('password.email'), ['email' => strtoupper($user->email)])
        ->assertSessionHas('status');

    expect($user->fresh()->must_change_password)->toBeTrue()
        ->and(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| 9. Route surface and regression guards
|--------------------------------------------------------------------------
*/

it('exposes only GET and POST on the change-initial-password endpoint', function () {
    [$user] = phase2cFlaggedPatient();

    $this->actingAs($user)->put(route('patient.change-initial-password.edit'))->assertStatus(405);
    $this->actingAs($user)->delete(route('patient.change-initial-password.edit'))->assertStatus(405);
    $this->actingAs($user)->patch(route('patient.change-initial-password.update'))->assertStatus(405);
});

it('leaves unflagged patient access to password confirmation and logout unchanged', function () {
    $user = phase2cUser('patient');
    phase2cLink(phase2cPatient(), $user);

    $this->actingAs($user)->get(route('password.confirm'))->assertOk();
    $this->actingAs($user)->post(route('logout'))->assertRedirect('/');
});

it('keeps guest access to the shared auth routes unchanged', function () {
    $this->get(route('login'))->assertOk();
    $this->get(route('password.request'))->assertOk();
    $this->get(route('password.confirm'))->assertRedirect(route('login'));
});
