{{-- DEPLA Phase 3A — Patient Dashboard.
     Four cards only: My Profile (functional), Appointments and Medical
     Records (Coming Soon placeholders), Account Security (informational).
     No clinical data, no risk/pregnancy/visit information, no clinic
     navigation. The patient identity comes from the controller's own
     `patientAccount` link — nothing here accepts an ID. --}}
@extends('patient.layout')

@section('content')
    {{-- ═══ Welcome ═══ --}}
    <section class="mb-8" aria-labelledby="portal-welcome-heading">
        <h1 id="portal-welcome-heading"
            class="portal-heading text-2xl sm:text-3xl"
            style="color: var(--depla-navy);">
            Welcome, {{ $patient->first_name }}!
        </h1>
        <p class="mt-2 text-sm leading-6 sm:text-base" style="color: var(--body-text);">
            Welcome to your DEPLA Family Care patient portal.
        </p>
        <p class="mt-3 inline-block rounded-full px-4 py-1.5 text-xs font-medium sm:text-sm"
           style="background: #EDF5EC; color: var(--depla-dark-green);">
            Some services are not available yet &mdash; appointments and medical records are coming soon.
        </p>
    </section>

    {{-- ═══ Dashboard cards ═══ --}}
    <section aria-label="Portal services">
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">

            {{-- 1. My Profile — the only functional card --}}
            <a href="{{ route('patient.profile.show') }}"
               class="group flex flex-col rounded-2xl border bg-white p-6 transition hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
               style="border-color: var(--light-border); outline-color: var(--depla-green);"
               aria-label="My Profile: view your personal information">
                <span class="mb-4 inline-flex h-11 w-11 items-center justify-center rounded-xl"
                      style="background: linear-gradient(135deg, #367E4B, #58D68D);">
                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </span>
                <span class="text-base font-bold" style="color: var(--depla-navy);">My Profile</span>
                <span class="mt-1.5 text-sm leading-6" style="color: var(--body-text);">
                    View your personal information.
                </span>
                <span class="mt-4 text-sm font-semibold" style="color: var(--depla-dark-green);">
                    Open My Profile &rarr;
                </span>
            </a>

            {{-- 2. Appointments — placeholder only, no route, no form --}}
            <article class="flex flex-col rounded-2xl border bg-white p-6"
                     style="border-color: var(--light-border);">
                <div class="mb-4 flex items-start justify-between gap-2">
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl"
                          style="background: #EDF5EC;">
                        <svg class="h-5 w-5" style="color: var(--depla-dark-green);" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </span>
                    <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide"
                          style="background: #F6F4EE; color: #657083;">
                        Coming Soon
                    </span>
                </div>
                <span class="text-base font-bold" style="color: var(--depla-navy);">Appointments</span>
                <span class="mt-1.5 text-sm leading-6" style="color: var(--body-text);">
                    Appointment services will be available soon.
                </span>
            </article>

            {{-- 3. Medical Records — placeholder only, no clinical data --}}
            <article class="flex flex-col rounded-2xl border bg-white p-6"
                     style="border-color: var(--light-border);">
                <div class="mb-4 flex items-start justify-between gap-2">
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl"
                          style="background: #EDF5EC;">
                        <svg class="h-5 w-5" style="color: var(--depla-dark-green);" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </span>
                    <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide"
                          style="background: #F6F4EE; color: #657083;">
                        Coming Soon
                    </span>
                </div>
                <span class="text-base font-bold" style="color: var(--depla-navy);">Medical Records</span>
                <span class="mt-1.5 text-sm leading-6" style="color: var(--body-text);">
                    Medical record access will be available soon.
                </span>
            </article>

            {{-- 4. Account Security — informational only, no metadata --}}
            <article class="flex flex-col rounded-2xl border bg-white p-6"
                     style="border-color: var(--light-border);">
                <div class="mb-4 flex items-start justify-between gap-2">
                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl"
                          style="background: #EDF5EC;">
                        <svg class="h-5 w-5" style="color: var(--depla-dark-green);" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </span>
                    <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide"
                          style="background: #EDF5EC; color: var(--depla-dark-green);">
                        Protected
                    </span>
                </div>
                <span class="text-base font-bold" style="color: var(--depla-navy);">Account Security</span>
                <span class="mt-1.5 text-sm leading-6" style="color: var(--body-text);">
                    Your account is protected.
                </span>
            </article>
        </div>
    </section>
@endsection
