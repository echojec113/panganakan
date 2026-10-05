<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view after validating the emailed token.
     *
     * The broker check happens before the form is rendered so an expired,
     * already-used, unknown, or email-mismatched link never looks usable.
     * Every failure mode shares one message so this endpoint does not reveal
     * whether an email address belongs to an account.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $token = $request->route('token');
        $email = $request->query('email');
        $broker = Password::broker();

        $user = filled($email) ? $broker->getUser(['email' => $email]) : null;

        if (blank($token) || is_null($user) || ! $broker->tokenExists($user, $token)) {
            $redirect = redirect()->route('password.request');

            if (filled($email)) {
                $redirect->withInput(['email' => $email]);
            }

            return $redirect->withErrors([
                'email' => 'This password reset link is invalid or has expired. Please request a new password reset link.',
            ]);
        }

        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        return $status == Password::PASSWORD_RESET
                    ? redirect()->route('login')->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
