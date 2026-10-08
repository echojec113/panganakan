<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

/**
 * The web middleware group registers ValidateCsrfToken (an alias subclass of
 * VerifyCsrfToken), and that middleware skips validation whenever the
 * application reports unit-test mode (VerifyCsrfToken::runningUnitTests()),
 * which would make stale-token tests pass without ever exercising the
 * mismatch. This rebinds the registered middleware to an instance whose
 * bypass returns false, so the real tokensMatch() logic runs against the
 * request while everything else (web group, session, exception handling)
 * stays untouched.
 */
function enableCsrfVerificationForLogoutTests(): void
{
    app()->bind(ValidateCsrfToken::class, function ($app) {
        return new class($app, $app->make('encrypter')) extends ValidateCsrfToken {
            protected function runningUnitTests()
            {
                return false;
            }
        };
    });
}

test('valid POST logout with a matching CSRF token still logs out and redirects home', function () {
    enableCsrfVerificationForLogoutTests();

    $user = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user)
        ->withSession(['_token' => 'valid-token'])
        ->post(route('logout'), ['_token' => 'valid-token'])
        ->assertRedirect('/');

    $this->assertGuest();
});

test('expired-session stale CSRF token on POST logout redirects to login with a friendly message', function () {
    enableCsrfVerificationForLogoutTests();

    // No authenticated user and a stale token: the same shape as a browser
    // submitting the profile logout form after the session expired.
    $response = $this->post(route('logout'), ['_token' => 'stale-token']);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status', 'Your session has expired. Please log in again.');
    $response->assertDontSee('CSRF token mismatch');
});

test('stale token does not execute the logout controller or invalidate a valid authenticated session', function () {
    enableCsrfVerificationForLogoutTests();

    $user = User::factory()->create(['role' => 'staff']);

    $response = $this->actingAs($user)
        ->withSession(['_token' => 'real-token'])
        ->post(route('logout'), ['_token' => 'WRONG-token']);

    $response->assertRedirect(route('login'));

    // destroy() would have logged the user out and invalidated the session;
    // staying authenticated proves the controller never ran.
    $this->assertAuthenticated();
});

test('GET logout still returns 405 for guests and authenticated users', function () {
    $this->get('/logout')->assertStatus(405);

    $user = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user)->get('/logout')->assertStatus(405);
});

test('unrelated POST routes with invalid CSRF tokens still return 419', function () {
    enableCsrfVerificationForLogoutTests();

    $user = User::factory()->create(['role' => 'staff']);

    $this->actingAs($user)
        ->withSession(['_token' => 'real-token'])
        ->post(route('notifications.markAllRead'), ['_token' => 'WRONG-token'])
        ->assertStatus(419);
});
