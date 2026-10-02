<?php

use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('admin can request a password reset for active staff and safely audit it', function () {
    Notification::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create([
        'role' => 'staff',
        'name' => 'Reset Target',
        'email' => 'reset-target@example.com',
    ]);

    $this->actingAs($admin)
        ->post(route('staff.reset-account', $staff))
        ->assertRedirect(route('staff.index'))
        ->assertSessionHas('success', 'Password reset link sent to the staff member\'s registered email.');

    Notification::assertSentTo($staff, ResetPassword::class);

    $audit = AuditLog::where('action', 'PASSWORD_RESET_REQUESTED')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($admin->id)
        ->and($audit->module)->toBe('STAFF')
        ->and($audit->description)->toContain('Reset Target')
        ->and($audit->description)->toContain((string) $staff->id);

    Notification::assertSentTo($staff, ResetPassword::class, function (ResetPassword $notification) use ($audit) {
        expect($audit->description)->not->toContain($notification->token)
            ->and($audit->description)->not->toContain('/reset-password/');

        return true;
    });
});

test('manage staff shows a per-account reset confirmation using the selected account details', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create([
        'role' => 'staff',
        'name' => 'Modal Target',
        'email' => 'modal-target@example.com',
    ]);

    $this->actingAs($admin)
        ->get(route('staff.index'))
        ->assertOk()
        ->assertSee('title="Reset Account"', false)
        ->assertSee('data-reset-url="'.route('staff.reset-account', $staff).'"', false)
        ->assertSee('data-staff-name="Modal Target"', false)
        ->assertSee('data-staff-email="modal-target@example.com"', false)
        ->assertSee('Reset Staff Account?')
        ->assertSee('Send Reset Link')
        ->assertSee('type="button" onclick="closeResetStaffModal()"', false)
        ->assertSee('id="resetStaffForm" method="POST"', false)
        ->assertSee('id="sendResetStaffButton" disabled', false)
        ->assertSee('resetStaffForm.action = resetUrl', false)
        ->assertSee("resetStaffForm.removeAttribute('action')", false)
        ->assertSee('sendResetStaffButton.disabled = true', false);

    $archivedStaff = User::factory()->create(['role' => 'staff']);
    $archivedStaff->delete();

    $this->get(route('staff.archived'))
        ->assertOk()
        ->assertDontSee('Reset Account')
        ->assertDontSee('staff.reset-account');
});

test('staff and guests cannot trigger the admin password reset endpoint', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $target = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)
        ->post(route('staff.reset-account', $target))
        ->assertForbidden();

    auth()->logout();

    $this->post(route('staff.reset-account', $target))
        ->assertRedirect(route('login'));
});

test('reset endpoint rejects admin archived and unknown targets', function () {
    Notification::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $targetAdmin = User::factory()->create(['role' => 'admin']);
    $archivedStaff = User::factory()->create(['role' => 'staff']);
    $archivedStaff->delete();

    $this->actingAs($admin)
        ->post(route('staff.reset-account', $targetAdmin))
        ->assertNotFound();

    $this->post('/staff/'.$archivedStaff->id.'/reset-account')->assertNotFound();
    $this->post('/staff/999999999/reset-account')->assertNotFound();

    Notification::assertNothingSent();
    expect(AuditLog::where('action', 'PASSWORD_RESET_REQUESTED')->count())->toBe(0);
});

test('reset request ignores an alternate email and targets the staff registered email', function () {
    Notification::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create([
        'role' => 'staff',
        'email' => 'registered@example.com',
    ]);

    $this->actingAs($admin)
        ->post(route('staff.reset-account', $staff), ['email' => 'attacker@example.com'])
        ->assertRedirect(route('staff.index'))
        ->assertSessionHas('success');

    Notification::assertSentTo($staff, ResetPassword::class);
    Notification::assertNothingSentTo(User::factory()->make(['email' => 'attacker@example.com']));
});

test('staff can use the emailed token to set a new password without changing their profile or assigned records', function () {
    Notification::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create([
        'role' => 'staff',
        'name' => 'Unchanged Staff',
        'email' => 'unchanged@example.com',
        'password' => Hash::make('old-password'),
    ]);
    $patient = Patient::create([
        'first_name' => 'Assigned',
        'last_name' => 'Patient',
        'age' => 29,
        'assigned_staff_id' => $staff->id,
    ]);

    $this->actingAs($admin)->post(route('staff.reset-account', $staff));

    Notification::assertSentTo($staff, ResetPassword::class, function (ResetPassword $notification) use ($staff, $patient) {
        $this->post(route('logout'));

        $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => $staff->email,
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ])->assertRedirect(route('login'));

        return true;
    });

    $staff->refresh();
    expect($staff->name)->toBe('Unchanged Staff')
        ->and($staff->email)->toBe('unchanged@example.com')
        ->and($staff->role)->toBe('staff')
        ->and($staff->deleted_at)->toBeNull()
        ->and(Hash::check('NewSecurePassword123!', $staff->password))->toBeTrue()
        ->and($staff->assignedPatients()->pluck('patients.id')->all())->toBe([$patient->id]);

    $this->post(route('login'), [
        'email' => $staff->email,
        'password' => 'old-password',
    ])->assertSessionHasErrors('email');

    $this->post(route('login'), [
        'email' => $staff->email,
        'password' => 'NewSecurePassword123!',
    ])->assertRedirect(route('dashboard'));
});

test('invalid password reset token is rejected', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->post(route('password.store'), [
        'token' => 'invalid-token',
        'email' => $staff->email,
        'password' => 'NewSecurePassword123!',
        'password_confirmation' => 'NewSecurePassword123!',
    ])->assertSessionHasErrors('email');
});

test('expired password reset token is rejected', function () {
    Notification::fake();

    $staff = User::factory()->create(['role' => 'staff']);
    $this->post('/forgot-password', ['email' => $staff->email]);

    Notification::assertSentTo($staff, ResetPassword::class, function (ResetPassword $notification) use ($staff) {
        $this->travel(61)->minutes();

        $this->post(route('password.store'), [
            'token' => $notification->token,
            'email' => $staff->email,
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ])->assertSessionHasErrors('email');

        return true;
    });
});

test('a successfully used reset token cannot be reused', function () {
    Notification::fake();

    $staff = User::factory()->create(['role' => 'staff']);
    $this->post('/forgot-password', ['email' => $staff->email]);

    Notification::assertSentTo($staff, ResetPassword::class, function (ResetPassword $notification) use ($staff) {
        $payload = [
            'token' => $notification->token,
            'email' => $staff->email,
            'password' => 'NewSecurePassword123!',
            'password_confirmation' => 'NewSecurePassword123!',
        ];

        $this->post(route('password.store'), $payload)
            ->assertRedirect(route('login'));

        $this->post(route('password.store'), $payload)
            ->assertSessionHasErrors('email');

        return true;
    });
});

test('reset endpoint is post-only and throttled requests are reported safely', function () {
    Notification::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($admin)
        ->get(route('staff.reset-account', $staff))
        ->assertMethodNotAllowed();

    $this->post(route('staff.reset-account', $staff))
        ->assertSessionHas('success');

    $this->post(route('staff.reset-account', $staff))
        ->assertSessionHas('error', 'A password reset link was requested recently. Please wait before trying again.');

    Notification::assertSentToTimes($staff, ResetPassword::class, 1);
    expect(AuditLog::where('action', 'PASSWORD_RESET_REQUESTED')->count())->toBe(1);
});
