<x-app-layout>
    <x-slot name="header">Profile</x-slot>

    <div class="mx-auto max-w-3xl space-y-6">

        {{-- ============ PROFILE SUMMARY (display only) ============ --}}
        <section class="flex flex-col gap-4 bg-white px-6 py-5 sm:flex-row sm:items-center rounded-2xl shadow-sm border border-gray-100">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-blue-900 text-lg font-bold text-white">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    <h2 class="truncate text-lg font-semibold text-slate-900">{{ auth()->user()->name }}</h2>
                    <span class="{{ auth()->user()->role === 'admin' ? 'status-badge-info' : 'status-badge-neutral' }} status-badge uppercase">
                        {{ auth()->user()->role ?? 'staff' }}
                    </span>
                </div>
                <p class="mt-0.5 truncate text-sm text-slate-500">{{ auth()->user()->email }}</p>
                <p class="mt-0.5 text-xs text-slate-400">Manage your personal account information</p>
            </div>
        </section>

        @include('profile.partials.update-profile-information-form')

        @include('profile.partials.update-password-form')

    </div>

    <script>
        // Independent show/hide password toggle for each eye button.
        document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById(btn.getAttribute('data-password-toggle'));
                if (!input) {
                    return;
                }

                var willShow = input.type === 'password';
                input.type = willShow ? 'text' : 'password';

                btn.setAttribute('aria-pressed', willShow ? 'true' : 'false');
                btn.setAttribute('aria-label', willShow ? 'Hide password' : 'Show password');

                var showIcon = btn.querySelector('[data-icon="show"]');
                var hideIcon = btn.querySelector('[data-icon="hide"]');
                if (showIcon) {
                    showIcon.classList.toggle('hidden', willShow);
                }
                if (hideIcon) {
                    hideIcon.classList.toggle('hidden', !willShow);
                }
            });
        });
    </script>
</x-app-layout>