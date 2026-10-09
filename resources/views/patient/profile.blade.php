{{-- DEPLA Phase 3A — read-only My Profile.
     Displays ONLY the logged-in patient's own basic information, resolved
     by the controller from `auth()->user()->patientAccount` — no ID enters
     this view. Strictly read-only: no edit/update/delete form, no POST
     target other than the layout's logout, no internal identifiers, no
     clinical data. Missing optional values render as "Not provided". The
     patient's clinical contact email and the account login email are shown
     as separate, clearly labelled rows whenever they differ — neither ever
     overwrites the other. --}}
@extends('patient.layout')

@section('content')
    <div class="mb-6">
        <a href="{{ route('patient.portal.coming-soon') }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold transition-colors hover:underline"
           style="color: var(--depla-dark-green);">
            &larr; Back to Dashboard
        </a>
    </div>

    <header class="mb-6">
        <h1 class="portal-heading text-2xl sm:text-3xl" style="color: var(--depla-navy);">
            My Profile
        </h1>
        <p class="mt-2 text-sm leading-6" style="color: var(--body-text);">
            Your personal information as recorded by the clinic. This page is read-only.
        </p>
    </header>

    <section class="overflow-hidden rounded-2xl border bg-white"
             style="border-color: var(--light-border);"
             aria-label="Personal information">
        <div class="border-b px-6 py-4" style="border-color: var(--light-border); background: #F6F4EE;">
            <h2 class="text-sm font-bold uppercase tracking-wider" style="color: var(--depla-navy);">
                Personal Information
            </h2>
        </div>

        <dl class="divide-y" style="divide-color: var(--light-border);">
            <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:gap-6">
                <dt class="w-56 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                    First Name
                </dt>
                <dd class="text-sm font-medium" style="color: var(--depla-navy);">
                    {{ $patient->first_name !== '' && $patient->first_name !== null ? $patient->first_name : 'Not provided' }}
                </dd>
            </div>

            <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:gap-6">
                <dt class="w-56 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                    Middle Name
                </dt>
                <dd class="text-sm font-medium" style="color: var(--depla-navy);">
                    {{ $patient->middle_name ?: 'Not provided' }}
                </dd>
            </div>

            <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:gap-6">
                <dt class="w-56 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                    Last Name
                </dt>
                <dd class="text-sm font-medium" style="color: var(--depla-navy);">
                    {{ $patient->last_name !== '' && $patient->last_name !== null ? $patient->last_name : 'Not provided' }}
                </dd>
            </div>

            {{-- Patient Contact Email — the clinical contact email stored on
                 the patients row. Never replaced by the login email. --}}
            <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:gap-6">
                <dt class="w-56 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                    Patient Contact Email
                </dt>
                <dd class="text-sm font-medium break-all" style="color: var(--depla-navy);">
                    {{ $patient->email ?: 'Not provided' }}
                </dd>
            </div>

            {{-- Login Email — shown only when it differs from the clinical
                 contact email, so the two are never conflated or hidden. --}}
            @if ($loginEmail !== (string) $patient->email)
                <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:gap-6">
                    <dt class="w-56 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                        Login Email
                    </dt>
                    <dd class="text-sm font-medium break-all" style="color: var(--depla-navy);">
                        {{ $loginEmail }}
                    </dd>
                </div>
            @endif

            <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:gap-6">
                <dt class="w-56 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                    Contact Number
                </dt>
                <dd class="text-sm font-medium" style="color: var(--depla-navy);">
                    {{ $patient->contact_number ?: 'Not provided' }}
                </dd>
            </div>

            {{-- Existing structured address accessor (formatted_address);
                 falls back to "Not provided" when nothing is recorded. --}}
            <div class="flex flex-col gap-1 px-6 py-4 sm:flex-row sm:items-baseline sm:gap-6">
                <dt class="w-56 shrink-0 text-xs font-semibold uppercase tracking-wider" style="color: var(--body-text);">
                    Address
                </dt>
                <dd class="text-sm font-medium" style="color: var(--depla-navy);">
                    {{ $patient->hasRecordedAddress() ? $patient->formatted_address : 'Not provided' }}
                </dd>
            </div>
        </dl>
    </section>

    <div class="mt-6 flex flex-wrap items-center gap-3">
        <a href="{{ route('patient.portal.coming-soon') }}"
           class="rounded-xl px-5 py-2.5 text-sm font-semibold text-white transition hover:opacity-90"
           style="background: var(--depla-dark-green);">
            Back to Dashboard
        </a>

        {{-- Logout stays POST + CSRF via the shared layout header; no other
             form action exists on this read-only page. --}}
        <span class="text-xs" style="color: var(--body-text);">
            Use <span class="font-semibold">Log Out</span> in the header to end your session.
        </span>
    </div>
@endsection
