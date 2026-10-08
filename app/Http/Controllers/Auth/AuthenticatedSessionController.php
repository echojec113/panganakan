<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        // Role-aware post-login destination (Phase 1 security hardening).
        // Patient and unknown roles must NEVER fall through to the clinic
        // dashboard: patients get the neutral portal holding page, and
        // unknown roles are denied with 403 after their session is cleared.
        return match ($user->role) {
            'admin', 'staff' => redirect()->route('dashboard'),
            'patient' => redirect()->route('patient.portal.coming-soon'),
            default => $this->denyUnknownRole($request),
        };
    }

    /**
     * End the session of an authenticated user whose role is not recognized
     * and answer with access denied. Unknown roles are never implicitly
     * treated as Patient or Staff.
     */
    private function denyUnknownRole(Request $request): never
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        abort(403);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
