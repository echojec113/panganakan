<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->get('/reset-password/'.$notification->token.'?email='.urlencode($user->email));

        $response->assertStatus(200);

        // Guards against the guest layout silently swallowing the slot and
        // rendering the hardcoded Login form instead of the actual
        // reset-password form (the bug fixed in layouts/guest.blade.php).
        $response->assertSee('name="password"', false);
        $response->assertSee('name="password_confirmation"', false);
        $response->assertSee('name="token"', false);
        $response->assertSee(route('password.store'), false);
        $response->assertSee('Reset Password');

        // The Login-specific form/markup must NOT be the content served here.
        $response->assertDontSee('name="remember"', false);
        $response->assertDontSee('Sign In', false);

        return true;
    });
});

test('login screen still renders the existing DEPLA login form', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
    $response->assertSee('Welcome Back');
    $response->assertSee(route('login'), false);
    $response->assertSee('name="remember"', false);
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});
