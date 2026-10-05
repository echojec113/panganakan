<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
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

test('valid unused reset token still shows the reset form', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $response = $this->get('/reset-password/'.$notification->token.'?email='.urlencode($user->email));

        $response->assertOk()->assertSee('name="password"', false);

        return true;
    });
});

test('expired reset token no longer shows the reset form and redirects to request a new link', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $this->travel(61)->minutes();

        $this->get('/reset-password/'.$notification->token.'?email='.urlencode($user->email))
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email')
            ->assertDontSee('name="password"', false);

        return true;
    });
});

test('successfully used reset token no longer shows the reset form and its row is deleted', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $link = '/reset-password/'.$notification->token.'?email='.urlencode($user->email);

        $this->post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('login'));

        expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeFalse();

        $this->get($link)
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email')
            ->assertDontSee('name="password"', false);

        return true;
    });
});

test('garbage reset token is redirected without showing the reset form', function () {
    $user = User::factory()->create();

    $this->get('/reset-password/garbage-token?email='.urlencode($user->email))
        ->assertRedirect(route('password.request'))
        ->assertSessionHasErrors('email')
        ->assertDontSee('name="password"', false);
});

test('reset links with a missing invalid or mismatched email are redirected safely', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
        $token = $notification->token;
        $expected = 'This password reset link is invalid or has expired. Please request a new password reset link.';

        $this->get('/reset-password/'.$token)
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors(['email' => $expected]);

        $this->get('/reset-password/'.$token.'?email=not-an-email')
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors(['email' => $expected]);

        $unknown = User::factory()->create();

        $this->get('/reset-password/'.$token.'?email='.urlencode($unknown->email))
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors(['email' => $expected]);

        return true;
    });
});

test('requesting a new reset token invalidates the previously emailed token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email]);
    $firstToken = Notification::sent($user, ResetPassword::class)->first()->token;

    $this->travel(61)->seconds();

    $this->post('/forgot-password', ['email' => $user->email]);
    $secondToken = Notification::sent($user, ResetPassword::class)->last()->token;

    expect($secondToken)->not->toBe($firstToken)
        ->and(DB::table('password_reset_tokens')->where('email', $user->email)->count())->toBe(1);

    $this->get('/reset-password/'.$firstToken.'?email='.urlencode($user->email))
        ->assertRedirect(route('password.request'))
        ->assertSessionHasErrors('email');

    $this->post('/reset-password', [
        'token' => $firstToken,
        'email' => $user->email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');
});
