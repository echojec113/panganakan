<x-app-layout>
    <x-slot name="header">Profile</x-slot>

    <style>
        .staff-profile-theme {
            --profile-primary: #55B85A;
            --profile-primary-hover: #49A64E;
            --profile-surface-muted: #F6F4EE;
            --profile-border: #E5E8E3;
            --profile-text: #19355F;
            --profile-muted: #657083;
        }

        .staff-profile-theme {
            color: var(--profile-text);
        }

        .staff-profile-theme .profile-card {
            overflow: hidden;
            border: 1px solid var(--profile-border);
            border-radius: 1rem;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        }

        .staff-profile-theme .profile-card-header {
            border-bottom: 1px solid var(--profile-border);
            background: var(--profile-surface-muted);
            padding: 1rem 1.5rem;
        }

        .staff-profile-theme .profile-primary-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
            background: var(--profile-primary);
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #ffffff;
            transition: background-color 150ms ease;
        }

        .staff-profile-theme .profile-primary-button:hover {
            background: var(--profile-primary-hover);
        }

        .staff-profile-theme .profile-secondary-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #D9DED8;
            border-radius: 0.5rem;
            background: #ffffff;
            padding: 0.625rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--profile-text);
            transition: background-color 150ms ease;
        }

        .staff-profile-theme .profile-secondary-button:hover {
            background: #F8F9F7;
        }
    </style>


    <div class="staff-profile-theme mx-auto max-w-3xl space-y-6">

        {{-- =========================================================
             PROFILE SUMMARY
        ========================================================== --}}
        <section class="profile-card">
            <div class="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center">

                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-xl bg-green-500 text-lg font-bold text-white">
                    {{ strtoupper(mb_substr(trim($user->name), 0, 1)) }}
                </div>

                <div class="min-w-0 flex-1">

                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                        <h1 class="truncate text-lg font-semibold text-slate-900">
                            {{ $user->name }}
                        </h1>

                        <span class="{{ $user->role === 'admin' ? 'status-badge-info' : 'status-badge-neutral' }} status-badge uppercase">
                            {{ $user->role ?? 'staff' }}
                        </span>
                    </div>

                    <p class="mt-0.5 truncate text-sm text-slate-500">
                        {{ $user->email }}
                    </p>

                    <p class="mt-0.5 text-xs text-slate-400">
                        Manage your personal account information and security.
                    </p>

                </div>

            </div>
        </section>


        {{-- =========================================================
             ACCOUNT INFORMATION
        ========================================================== --}}
        @include('profile.partials.update-profile-information-form')


        {{-- =========================================================
             PASSWORD & SECURITY
        ========================================================== --}}
        @include('profile.partials.update-password-form')


        {{-- =========================================================
             ACCOUNT SESSION
        ========================================================== --}}
        <section class="profile-card">

            <div class="profile-card-header">
                <h2 class="text-base font-semibold text-slate-900">
                    Account Session
                </h2>

                <p class="mt-0.5 text-sm text-slate-500">
                    Manage your current signed-in session.
                </p>
            </div>

            <div class="flex flex-col gap-4 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">

                <div>
                    <p class="text-sm font-medium text-slate-800">
                        Signed in as {{ $user->name }}
                    </p>

                    <p class="mt-1 text-xs text-slate-400">
                        Logging out will end your current session on this device.
                    </p>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <button
                        type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-200"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6A2.25 2.25 0 005.25 5.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3-6 3 3m0 0-3 3m3-3H9"
                            />
                        </svg>

                        Logout
                    </button>
                </form>

            </div>

        </section>

    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function () {

            /*
             * =====================================================
             * PASSWORD VISIBILITY
             * =====================================================
             */
            document
                .querySelectorAll('[data-password-toggle]')
                .forEach(function (button) {

                    button.addEventListener('click', function () {

                        const input = document.getElementById(
                            button.getAttribute('data-password-toggle')
                        );

                        if (!input) {
                            return;
                        }

                        const willShow = input.type === 'password';

                        input.type = willShow
                            ? 'text'
                            : 'password';

                        button.setAttribute(
                            'aria-pressed',
                            willShow ? 'true' : 'false'
                        );

                        button.setAttribute(
                            'aria-label',
                            willShow
                                ? 'Hide password'
                                : 'Show password'
                        );

                        const showIcon =
                            button.querySelector('[data-icon="show"]');

                        const hideIcon =
                            button.querySelector('[data-icon="hide"]');

                        if (showIcon) {
                            showIcon.classList.toggle('hidden', willShow);
                        }

                        if (hideIcon) {
                            hideIcon.classList.toggle('hidden', !willShow);
                        }

                    });

                });

        });
    </script>

</x-app-layout>