<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PatientSetupTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

/**
 * DEPLA Phase 1.5 — first-time Patient Portal password setup endpoints.
 *
 * Guest routes, deliberately separate from the ordinary Forgot Password
 * flow: the token is namespaced and consumed through
 * PatientSetupTokenService (24-hour lifetime, strictly single-use,
 * concurrency-safe), and the ordinary password broker keeps its 60-minute
 * behavior untouched.
 *
 * Security conventions mirrored from NewPasswordController:
 *   - the emailed address is normalized (trim + lower-case) before lookup;
 *   - every invalid/expired/used/mismatched link answers with ONE message
 *     so the endpoint never reveals whether an email belongs to an account;
 *   - GET only renders the form and never consumes the token (refresh is
 *     safe); only a successful POST consumes it;
 *   - failed password validation (422) never reaches the consume step.
 */
class PatientSetupLinkController extends Controller
{
    /**
     * Single anti-enumeration failure message for every ineligible link.
     */
    public const INVALID_MESSAGE = 'This setup link has expired, has already been used, or is invalid. Please contact the clinic to request a new setup email.';

    public function __construct(
        private readonly PatientSetupTokenService $setupTokens,
    ) {
    }

    /**
     * Validate the emailed link and render the password setup form.
     * Read-only: the token is never consumed here.
     */
    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $email = $this->normalizeEmail($request->query('email'));

        $user = filled($email) ? User::where('email', $email)->first() : null;

        if (! $this->setupTokens->isEligible($user) || ! $this->setupTokens->exists($user, $token)) {
            return $this->refuse();
        }

        // Referrer-Policy: the token lives in this page's URL and must never
        // leak through an outbound referrer header.
        return response()->view('auth.patient-setup', [
            'token' => $token,
            'email' => $email,
        ], 200, ['Referrer-Policy' => 'no-referrer']);
    }

    /**
     * Validate the password payload, then atomically set the password and
     * consume the token. Validation failures (422) redirect back to the form
     * WITHOUT consuming; an ineligible token redirects to login with the
     * unified failure message.
     */
    public function store(Request $request, string $token): RedirectResponse
    {
        $request->merge([
            'token' => $token,
            'email' => $this->normalizeEmail($request->input('email')),
        ]);

        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! $this->setupTokens->consume($user, $token, $request->input('password'))) {
            return $this->refuse();
        }

        // Deliberately NOT logged in: the patient signs in manually with the
        // password they just created, exactly like the ordinary reset flow.
        return redirect()
            ->route('login')
            ->with('status', 'Your password has been set. You may now sign in to the patient portal.');
    }

    private function refuse(): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->withErrors(['email' => self::INVALID_MESSAGE]);
    }

    private function normalizeEmail(mixed $rawEmail): ?string
    {
        return is_string($rawEmail) ? Str::lower(trim($rawEmail)) : null;
    }
}
