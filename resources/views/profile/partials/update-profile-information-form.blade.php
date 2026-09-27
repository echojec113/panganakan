<section
    class="profile-card"
    x-data="{
        editing: {{ ($errors->has('name') || $errors->has('email') || $errors->has('current_password')) ? 'true' : 'false' }}
    }"
>

    {{-- HEADER --}}
    <div class="profile-card-header flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

        <div>
            <h2 class="text-base font-semibold text-slate-900">
                Account Information
            </h2>

            <p class="mt-0.5 text-sm text-slate-500">
                Your personal account details.
            </p>
        </div>

        <button
            x-show="!editing"
            type="button"
            @click="editing = true"
            class="profile-secondary-button shrink-0"
        >
            Edit Information
        </button>

    </div>


    {{-- ============================================================
         READ-ONLY STATE
    ============================================================ --}}
    <div
        x-show="!editing"
        x-transition.opacity
        class="px-6 py-5"
    >

        <dl class="grid grid-cols-1 gap-x-8 gap-y-5 sm:grid-cols-2">

            {{-- Full Name --}}
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">
                    Full Name
                </dt>

                <dd class="mt-1.5 text-sm font-semibold text-slate-800">
                    {{ $user->name }}
                </dd>
            </div>


            {{-- Email --}}
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">
                    Email Address
                </dt>

                <dd class="mt-1.5 break-all text-sm font-semibold text-slate-800">
                    {{ $user->email }}
                </dd>
            </div>


            {{-- Role --}}
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">
                    Account Role
                </dt>

                <dd class="mt-1.5">
                    <span class="{{ $user->role === 'admin' ? 'status-badge-info' : 'status-badge-neutral' }} status-badge uppercase">
                        {{ $user->role ?? 'staff' }}
                    </span>
                </dd>
            </div>


            {{-- Account status --}}
            <div>
                <dt class="text-xs font-medium uppercase tracking-wide text-slate-400">
                    Account Status
                </dt>

                <dd class="mt-1.5">
                    <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-green-600">
                        <span class="h-2 w-2 rounded-full bg-green-500"></span>
                        Active
                    </span>
                </dd>
            </div>

        </dl>

    </div>


    {{-- ============================================================
         EDIT STATE
    ============================================================ --}}
    <form
        x-show="editing"
        x-cloak
        x-transition.opacity
        method="POST"
        action="{{ route('profile.update') }}"
        class="px-6 py-5"
    >
        @csrf
        @method('PATCH')


        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">

            {{-- Full Name --}}
            <div>
                <x-input-label
                    for="name"
                    value="Full Name"
                />

                <input
                    id="name"
                    name="name"
                    type="text"
                    value="{{ old('name', $user->name) }}"
                    autocomplete="name"
                    required
                    class="mt-1 block h-10 w-full rounded-lg border bg-white px-3 text-sm text-slate-900 shadow-sm outline-none transition focus:ring-2
                    {{ $errors->has('name')
                        ? 'border-red-400 focus:border-red-400 focus:ring-red-400/25'
                        : 'border-gray-300 focus:border-green-500 focus:ring-green-500/30' }}"
                >

                <x-input-error
                    class="mt-2"
                    :messages="$errors->get('name')"
                />
            </div>


            {{-- Email --}}
            <div>
                <x-input-label
                    for="email"
                    value="Email Address"
                />

                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email', $user->email) }}"
                    autocomplete="username"
                    required
                    class="mt-1 block h-10 w-full rounded-lg border bg-white px-3 text-sm text-slate-900 shadow-sm outline-none transition focus:ring-2
                    {{ $errors->has('email')
                        ? 'border-red-400 focus:border-red-400 focus:ring-red-400/25'
                        : 'border-gray-300 focus:border-green-500 focus:ring-green-500/30' }}"
                >

                <x-input-error
                    class="mt-2"
                    :messages="$errors->get('email')"
                />
            </div>

        </div>


        {{-- Current password security confirmation --}}
        <div class="mt-5 rounded-xl border border-amber-100 bg-amber-50/60 p-4">

            <div class="flex gap-3">

                <svg
                    class="mt-0.5 h-5 w-5 shrink-0 text-amber-600"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4"
                    />
                </svg>

                <div class="min-w-0 flex-1">

                    <x-input-label
                        for="profile_current_password"
                        value="Current Password"
                    />

                    <p class="mt-0.5 text-xs text-slate-500">
                        Required only when changing your email address.
                    </p>

                    <input
                        id="profile_current_password"
                        name="current_password"
                        type="password"
                        autocomplete="current-password"
                        class="mt-2 block h-10 w-full rounded-lg border bg-white px-3 text-sm text-slate-900 shadow-sm outline-none transition focus:ring-2
                        {{ $errors->has('current_password')
                            ? 'border-red-400 focus:border-red-400 focus:ring-red-400/25'
                            : 'border-gray-300 focus:border-green-500 focus:ring-green-500/30' }}"
                    >

                    <x-input-error
                        class="mt-2"
                        :messages="$errors->get('current_password')"
                    />

                </div>

            </div>

        </div>


        <div class="mt-5 flex flex-wrap items-center justify-end gap-3">

            <button
                type="button"
                @click="editing = false"
                class="profile-secondary-button"
            >
                Cancel
            </button>

            <button
                type="submit"
                class="profile-primary-button"
            >
                Save Changes
            </button>

        </div>

    </form>


    {{-- SUCCESS --}}
    @if (session('status') === 'profile-updated')
        <div
            x-data="{ show: true }"
            x-show="show"
            x-transition
            x-init="setTimeout(() => show = false, 3000)"
            class="border-t border-green-100 bg-green-50 px-6 py-3 text-sm font-medium text-green-700"
        >
            ✓ Profile information updated successfully.
        </div>
    @endif

</section>