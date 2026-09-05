<section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
    <div class="border-b bg-gray-50 px-6 py-4">
        <h2 class="text-base font-semibold text-slate-900">Password &amp; Security</h2>
        <p class="mt-0.5 text-sm text-slate-500">Keep your account protected with a secure password.</p>
    </div>

    <form method="post" action="{{ route('password.update') }}" class="p-6">
        @csrf
        @method('put')

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="update_password_current_password" value="Current Password" />
                <div class="relative mt-1">
                    <input
                        id="update_password_current_password"
                        name="current_password"
                        type="password"
                        autocomplete="current-password"
                        aria-describedby="{{ $errors->updatePassword->has('current_password') ? 'current-password-error' : '' }}"
                        class="block h-10 w-full rounded-lg border bg-white px-3 pr-10 text-sm text-slate-900 shadow-sm outline-none transition focus:ring-2 {{ $errors->updatePassword->has('current_password') ? 'border-red-400 focus:border-red-400 focus:ring-red-400/25' : 'border-gray-300 focus:border-primary focus:ring-primary/30' }}"
                    />
                    <button
                        type="button"
                        data-password-toggle="update_password_current_password"
                        aria-label="Show password"
                        aria-pressed="false"
                        class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-lg text-gray-400 transition hover:text-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                    >
                        <svg data-icon="show" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                            <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg data-icon="hide" class="hidden h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M9.88 9.88a3 3 0 1 0 4.243 4.243" />
                            <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c4.478 0 8.268 2.943 9.543 7a9.96 9.96 0 0 1-1.563 3.029" />
                            <path d="M6.603 6.602A10.08 10.08 0 0 0 2.458 12c1.274 4.057 5.064 7 9.542 7a9.94 9.94 0 0 0 5.399-1.603" />
                            <line x1="3" y1="3" x2="21" y2="21" />
                        </svg>
                    </button>
                </div>
                <x-input-error id="current-password-error" class="mt-2" :messages="$errors->updatePassword->get('current_password')" />
            </div>

            <div>
                <x-input-label for="update_password_password" value="New Password" />
                <div class="relative mt-1">
                    <input
                        id="update_password_password"
                        name="password"
                        type="password"
                        autocomplete="new-password"
                        aria-describedby="{{ $errors->updatePassword->has('password') ? 'password-error' : '' }}"
                        class="block h-10 w-full rounded-lg border bg-white px-3 pr-10 text-sm text-slate-900 shadow-sm outline-none transition focus:ring-2 {{ $errors->updatePassword->has('password') ? 'border-red-400 focus:border-red-400 focus:ring-red-400/25' : 'border-gray-300 focus:border-primary focus:ring-primary/30' }}"
                    />
                    <button
                        type="button"
                        data-password-toggle="update_password_password"
                        aria-label="Show password"
                        aria-pressed="false"
                        class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-lg text-gray-400 transition hover:text-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                    >
                        <svg data-icon="show" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                            <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg data-icon="hide" class="hidden h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M9.88 9.88a3 3 0 1 0 4.243 4.243" />
                            <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c4.478 0 8.268 2.943 9.543 7a9.96 9.96 0 0 1-1.563 3.029" />
                            <path d="M6.603 6.602A10.08 10.08 0 0 0 2.458 12c1.274 4.057 5.064 7 9.542 7a9.94 9.94 0 0 0 5.399-1.603" />
                            <line x1="3" y1="3" x2="21" y2="21" />
                        </svg>
                    </button>
                </div>
                <x-input-error id="password-error" class="mt-2" :messages="$errors->updatePassword->get('password')" />
            </div>

            <div>
                <x-input-label for="update_password_password_confirmation" value="Confirm New Password" />
                <div class="relative mt-1">
                    <input
                        id="update_password_password_confirmation"
                        name="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        aria-describedby="{{ $errors->updatePassword->has('password_confirmation') ? 'password-confirmation-error' : '' }}"
                        class="block h-10 w-full rounded-lg border bg-white px-3 pr-10 text-sm text-slate-900 shadow-sm outline-none transition focus:ring-2 {{ $errors->updatePassword->has('password_confirmation') ? 'border-red-400 focus:border-red-400 focus:ring-red-400/25' : 'border-gray-300 focus:border-primary focus:ring-primary/30' }}"
                    />
                    <button
                        type="button"
                        data-password-toggle="update_password_password_confirmation"
                        aria-label="Show password"
                        aria-pressed="false"
                        class="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-lg text-gray-400 transition hover:text-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                    >
                        <svg data-icon="show" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                            <path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg data-icon="hide" class="hidden h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M9.88 9.88a3 3 0 1 0 4.243 4.243" />
                            <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c4.478 0 8.268 2.943 9.543 7a9.96 9.96 0 0 1-1.563 3.029" />
                            <path d="M6.603 6.602A10.08 10.08 0 0 0 2.458 12c1.274 4.057 5.064 7 9.542 7a9.94 9.94 0 0 0 5.399-1.603" />
                            <line x1="3" y1="3" x2="21" y2="21" />
                        </svg>
                    </button>
                </div>
                <x-input-error id="password-confirmation-error" class="mt-2" :messages="$errors->updatePassword->get('password_confirmation')" />
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <x-primary-button>Update Password</x-primary-button>

            @if (session('status') === 'password-updated')
                <span
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 3000)"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-green-200 bg-green-50 px-3 py-1.5 text-sm font-medium text-green-700"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Password updated successfully.
                </span>
            @endif
        </div>
    </form>
</section>