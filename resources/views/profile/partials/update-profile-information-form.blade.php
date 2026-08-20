<section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
    <div class="border-b bg-gray-50 px-6 py-4">
        <h2 class="text-base font-semibold text-slate-900">Account Information</h2>
        <p class="mt-0.5 text-sm text-slate-500">Update your personal account details.</p>
    </div>

    <form method="post" action="{{ route('profile.update') }}" class="p-6">
        @csrf
        @method('patch')

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <x-input-label for="name" value="Full Name" />
                <input
                    id="name"
                    name="name"
                    type="text"
                    value="{{ old('name', $user->name) }}"
                    autocomplete="name"
                    required
                    autofocus
                    aria-describedby="{{ $errors->has('name') ? 'name-error' : '' }}"
                    class="mt-1 block h-10 w-full rounded-lg border bg-white px-3 text-sm text-slate-900 shadow-sm outline-none transition focus:ring-2 {{ $errors->has('name') ? 'border-red-400 focus:border-red-400 focus:ring-red-400/25' : 'border-gray-300 focus:border-primary focus:ring-primary/30' }}"
                />
                <x-input-error id="name-error" class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="email" value="Email Address" />
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email', $user->email) }}"
                    autocomplete="username"
                    required
                    aria-describedby="{{ $errors->has('email') ? 'email-error' : '' }}"
                    class="mt-1 block h-10 w-full rounded-lg border bg-white px-3 text-sm text-slate-900 shadow-sm outline-none transition focus:ring-2 {{ $errors->has('email') ? 'border-red-400 focus:border-red-400 focus:ring-red-400/25' : 'border-gray-300 focus:border-primary focus:ring-primary/30' }}"
                />
                <x-input-error id="email-error" class="mt-2" :messages="$errors->get('email')" />
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <x-primary-button>Save Changes</x-primary-button>

            @if (session('status') === 'profile-updated')
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
                    Profile information updated successfully.
                </span>
            @endif
        </div>
    </form>
</section>