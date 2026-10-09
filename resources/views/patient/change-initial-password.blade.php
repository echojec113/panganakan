{{-- Phase 2C — mandatory first-login password change.
     Rendered on the shared guest layout: no clinic navigation, no records,
     no dashboards. Only the three password fields and a way out (Sign Out). --}}
<x-guest-layout>
    <div class="space-y-5">
        {{-- Why the patient is here --}}
        <div class="text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full"
                 style="background: linear-gradient(135deg, #367E4B, #58D68D);">
                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>

            <p class="mt-3 text-sm leading-6 text-[#657083]">
                For your security, you must set a new password before you can continue.
                Enter your current password, then choose a new one.
            </p>
        </div>

        {{-- Validation summary (wrong current password, weak/mismatched new one, ...) --}}
        <x-error-summary :errors="$errors" title="Please review the following:" />

        <form method="POST" action="{{ route('patient.change-initial-password.update') }}" class="space-y-4">
            @csrf

            {{-- Current password --}}
            <div>
                <label for="current_password"
                       class="block text-xs font-semibold text-[#657083] mb-1.5 uppercase tracking-wider"
                       style="letter-spacing:0.08em;">Current Password</label>
                <div class="relative">
                    <x-text-input
                        id="current_password"
                        class="fancy-input block w-full pl-10 pr-10 py-3 rounded-xl border text-sm transition-all duration-200"
                        style="border-color:rgba(231,233,229,0.9);background:#F6F4EE;color:#1e293b;"
                        type="password"
                        name="current_password"
                        required
                        autocomplete="current-password"
                    />
                    <button type="button" class="password-toggle" aria-label="Show or hide the current password"
                            onclick="toggleInitialPassword('current_password', 'current-eye')">
                        <svg id="current-eye" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('current_password')" class="mt-1.5 text-xs text-red-500" />
            </div>

            {{-- New password --}}
            <div>
                <label for="password"
                       class="block text-xs font-semibold text-[#657083] mb-1.5 uppercase tracking-wider"
                       style="letter-spacing:0.08em;">New Password</label>
                <div class="relative">
                    <x-text-input
                        id="password"
                        class="fancy-input block w-full pl-10 pr-10 py-3 rounded-xl border text-sm transition-all duration-200"
                        style="border-color:rgba(231,233,229,0.9);background:#F6F4EE;color:#1e293b;"
                        type="password"
                        name="password"
                        required
                        autocomplete="new-password"
                    />
                    <button type="button" class="password-toggle" aria-label="Show or hide the new password"
                            onclick="toggleInitialPassword('password', 'new-eye')">
                        <svg id="new-eye" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
                <p class="mt-1.5 text-xs text-[#657083]">Minimum of 8 characters.</p>
                <x-input-error :messages="$errors->get('password')" class="mt-1.5 text-xs text-red-500" />
            </div>

            {{-- Confirm new password --}}
            <div>
                <label for="password_confirmation"
                       class="block text-xs font-semibold text-[#657083] mb-1.5 uppercase tracking-wider"
                       style="letter-spacing:0.08em;">Confirm New Password</label>
                <div class="relative">
                    <x-text-input
                        id="password_confirmation"
                        class="fancy-input block w-full pl-10 pr-10 py-3 rounded-xl border text-sm transition-all duration-200"
                        style="border-color:rgba(231,233,229,0.9);background:#F6F4EE;color:#1e293b;"
                        type="password"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                    />
                    <button type="button" class="password-toggle" aria-label="Show or hide the password confirmation"
                            onclick="toggleInitialPassword('password_confirmation', 'confirm-eye')">
                        <svg id="confirm-eye" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5 text-xs text-red-500" />
            </div>

            <div class="pt-1">
                <button type="submit"
                        class="btn-glow w-full px-6 py-3.5 font-semibold rounded-xl text-white text-sm tracking-wide"
                        style="background: #55B85A; box-shadow: none;">
                    Save New Password &nbsp;→
                </button>
            </div>
        </form>

        {{-- Always-available way out: a patient who cannot supply the current
             password must never be trapped on this screen. --}}
        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf
            <button type="submit"
                    class="text-xs font-medium text-[#657083] hover:text-[#367E4B] transition-colors duration-200 underline">
                Sign Out instead
            </button>
        </form>
    </div>

    <script>
        function toggleInitialPassword(inputId, iconId) {
            const field = document.getElementById(inputId);
            const icon = document.getElementById(iconId);

            if (field.type === 'password') {
                field.type = 'text';
                icon.innerHTML = '<g><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></g>';
            } else {
                field.type = 'password';
                icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
            }
        }
    </script>
</x-guest-layout>
