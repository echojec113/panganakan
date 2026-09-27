<section
    class="profile-card"
    x-data="{
        changingPassword: {{ $errors->updatePassword->any() ? 'true' : 'false' }}
    }"
>

    {{-- HEADER --}}
    <div class="profile-card-header flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <h2 class="text-base font-semibold text-slate-900">
                Password &amp; Security
            </h2>

            <p class="mt-0.5 text-sm text-slate-500">
                Manage the password used to access your account.
            </p>
        </div>

        <button
            x-show="!changingPassword"
            type="button"
            @click="changingPassword = true"
            class="profile-secondary-button shrink-0"
        >
            Change Password
        </button>

    </div>


    {{-- ============================================================
         READ-ONLY SECURITY STATE
    ============================================================ --}}
    <div
        x-show="!changingPassword"
        x-transition.opacity
        class="px-6 py-5"
    >

        <div class="flex items-start gap-4">

            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-green-50 text-green-600">

                <svg
                    class="h-5 w-5"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M16.5 10.5V6.75a4.5 4.5 0 00-9 0v3.75m-.75 0h10.5A2.25 2.25 0 0119.5 12.75v6A2.25 2.25 0 0117.25 21H6.75a2.25 2.25 0 01-2.25-2.25v-6A2.25 2.25 0 016.75 10.5z"
                    />
                </svg>

            </div>


            <div class="min-w-0">

                <p class="text-sm font-semibold text-slate-800">
                    Password
                </p>

                <p class="mt-1 font-mono text-sm tracking-widest text-slate-500">
                    ••••••••••••
                </p>

                <p class="mt-2 max-w-xl text-xs leading-5 text-slate-400">
                    Use a strong, unique password. Change it immediately if you believe someone else may know it.
                </p>

            </div>

        </div>

    </div>


    {{-- ============================================================
         CHANGE PASSWORD STATE
    ============================================================ --}}
    <form
        x-show="changingPassword"
        x-cloak
        x-transition.opacity
        method="POST"
        action="{{ route('password.update') }}"
        class="px-6 py-5"
    >
        @csrf
        @method('PUT')


        {{-- Current Password --}}
        <div>

            <x-input-label
                for="update_password_current_password"
                value="Current Password"
            />

            <div class="relative mt-1">

                <input
                    id="update_password_current_password"
                    name="current_password"
                    type="password"
                    autocomplete="current-password"
                    class="block h-10 w-full rounded-lg border bg-white px-3 pr-10 text-sm text-slate-900 shadow-sm outline-none transition focus:ring-2
                    {{ $errors->updatePassword->has('current_password')
                        ? 'border-red-400 focus:border-red-400 focus:ring-red-400/25'
                        : 'border-gray-300 focus:border-green-500 focus:ring-green-500/30' }}"
                >

                <button
                    type="button"
                    data-password-toggle="update_password_current_password"
                    aria-label="Show password"
                    aria-pressed="false"
                    class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-gray-400 transition hover:text-gray-600"
                >
                    <svg
                        data-icon="show"
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        viewBox="0 0 24 24"
                    >
                        <path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                        <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>

                    <svg
                        data-icon="hide"
                        class="hidden h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        viewBox="0 0 24 24"
                    >
                        <path d="M9.88 9.88a3 3 0 1 0 4.243 4.243" />
                        <path d="M10.73 5.08A10.43 10.43 0 0112 5c4.478 0 8.268 2.943 9.543 7a9.96 9.96 0 01-1.563 3.029" />
                        <path d="M6.603 6.602A10.08 10.08 0 002.458 12c1.274 4.057 5.064 7 9.542 7a9.94 9.94 0 005.399-1.603" />
                        <line x1="3" y1="3" x2="21" y2="21" />
                    </svg>
                </button>

            </div>

            <x-input-error
                class="mt-2"
                :messages="$errors->updatePassword->get('current_password')"
            />

        </div>


        {{-- New Password + Confirmation --}}
        <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">

            {{-- New Password --}}
            <div>

                <x-input-label
                    for="update_password_password"
                    value="New Password"
                />

                <div class="relative mt-1">

                    <input
                        id="update_password_password"
                        name="password"
                        type="password"
                        autocomplete="new-password"
                        class="block h-10 w-full rounded-lg border bg-white px-3 pr-10 text-sm text-slate-900 shadow-sm outline-none transition focus:ring-2
                        {{ $errors->updatePassword->has('password')
                            ? 'border-red-400 focus:border-red-400 focus:ring-red-400/25'
                            : 'border-gray-300 focus:border-green-500 focus:ring-green-500/30' }}"
                    >

                    <button
                        type="button"
                        data-password-toggle="update_password_password"
                        aria-label="Show password"
                        aria-pressed="false"
                        class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-gray-400 transition hover:text-gray-600"
                    >
                        <svg
                            data-icon="show"
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                            <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>

                        <svg
                            data-icon="hide"
                            class="hidden h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path d="M9.88 9.88a3 3 0 1 0 4.243 4.243" />
                            <path d="M10.73 5.08A10.43 10.43 0 0112 5c4.478 0 8.268 2.943 9.543 7a9.96 9.96 0 01-1.563 3.029" />
                            <path d="M6.603 6.602A10.08 10.08 0 002.458 12c1.274 4.057 5.064 7 9.542 7a9.94 9.94 0 005.399-1.603" />
                            <line x1="3" y1="3" x2="21" y2="21" />
                        </svg>
                    </button>

                </div>

                <x-input-error
                    class="mt-2"
                    :messages="$errors->updatePassword->get('password')"
                />

            </div>


            {{-- Confirmation --}}
            <div>

                <x-input-label
                    for="update_password_password_confirmation"
                    value="Confirm New Password"
                />

                <div class="relative mt-1">

                    <input
                        id="update_password_password_confirmation"
                        name="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="block h-10 w-full rounded-lg border bg-white px-3 pr-10 text-sm text-slate-900 shadow-sm outline-none transition focus:ring-2
                        {{ $errors->updatePassword->has('password_confirmation')
                            ? 'border-red-400 focus:border-red-400 focus:ring-red-400/25'
                            : 'border-gray-300 focus:border-green-500 focus:ring-green-500/30' }}"
                    >

                    <button
                        type="button"
                        data-password-toggle="update_password_password_confirmation"
                        aria-label="Show password"
                        aria-pressed="false"
                        class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-gray-400 transition hover:text-gray-600"
                    >
                        <svg
                            data-icon="show"
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                            <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>

                        <svg
                            data-icon="hide"
                            class="hidden h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                        >
                            <path d="M9.88 9.88a3 3 0 1 0 4.243 4.243" />
                            <path d="M10.73 5.08A10.43 10.43 0 0112 5c4.478 0 8.268 2.943 9.543 7a9.96 9.96 0 01-1.563 3.029" />
                            <path d="M6.603 6.602A10.08 10.08 0 002.458 12c1.274 4.057 5.064 7 9.542 7a9.94 9.94 0 005.399-1.603" />
                            <line x1="3" y1="3" x2="21" y2="21" />
                        </svg>
                    </button>

                </div>

                <x-input-error
                    class="mt-2"
                    :messages="$errors->updatePassword->get('password_confirmation')"
                />

            </div>

        </div>


        <div class="mt-5 flex flex-wrap justify-end gap-3">

            <button
                type="button"
                @click="changingPassword = false"
                class="profile-secondary-button"
            >
                Cancel
            </button>

            <button
                type="submit"
                class="profile-primary-button"
            >
                Update Password
            </button>

        </div>

    </form>


    {{-- SUCCESS --}}
    @if (session('status') === 'password-updated')
        <div
            x-data="{ show: true }"
            x-show="show"
            x-transition
            x-init="setTimeout(() => show = false, 3000)"
            class="border-t border-green-100 bg-green-50 px-6 py-3 text-sm font-medium text-green-700"
        >
            ✓ Password updated successfully.
        </div>
    @endif

</section>