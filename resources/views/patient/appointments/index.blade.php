{{-- DEPLA Phase 3B.3 — My Appointments (patient portal).
     Shows ONLY the authenticated patient's own appointments (resolved by
     the controller from patientAccount — no ID enters this view). Three
     sections: Pending (awaiting clinic confirmation), Upcoming Confirmed
     (the clinic-approved schedule), and History (completed / cancelled /
     declined, plus confirmed schedules that have already passed), with
     pagination on History. Read-only: no confirm/decline/cancel/complete
     actions, no staff audit details, no other patient's rows. --}}
@extends('patient.layout')

@section('content')
    <div class="mb-6">
        <a href="{{ route('patient.portal.coming-soon') }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold transition-colors hover:underline"
           style="color: var(--depla-dark-green);">
            &larr; Back to Dashboard
        </a>
    </div>

    <header class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="portal-heading text-2xl sm:text-3xl" style="color: var(--depla-navy);">
                My Appointments
            </h1>
            <p class="mt-2 text-sm leading-6" style="color: var(--body-text);">
                View your checkup requests and their status. Checkups are confirmed immediately when the selected slot is available.
            </p>
        </div>
        <a href="{{ route('patient.appointments.create') }}"
           class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold text-white transition hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
           style="background: var(--depla-dark-green); outline-color: var(--depla-green);">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
            </svg>
            Request Appointment
        </a>
    </header>

    {{-- Success flash (after a submitted request redirects back here). --}}
    @if (session('success'))
        <div class="mb-6 rounded-xl border px-4 py-3 text-sm font-medium"
             style="border-color: #BBF7D0; background: #F0FDF4; color: #166534;"
             role="status">
            {{ session('success') }}
        </div>
    @endif

    {{-- ═══ Pending Requests ═══ --}}
    <section class="mb-8" aria-labelledby="portal-pending-heading">
        <h2 id="portal-pending-heading" class="mb-3 text-sm font-bold uppercase tracking-wider" style="color: var(--depla-navy);">
            Pending Requests
        </h2>

        @if ($pending->isEmpty())
            <p class="rounded-2xl border bg-white px-5 py-6 text-sm" style="border-color: var(--light-border); color: var(--body-text);">
                You have no pending appointment requests.
            </p>
        @else
            <div class="space-y-4">
                @foreach ($pending as $appointment)
                    @include('patient.appointments.partials.card', ['appointment' => $appointment])
                @endforeach
            </div>
        @endif
    </section>

    {{-- ═══ Upcoming Confirmed Appointments ═══ --}}
    <section class="mb-8" aria-labelledby="portal-upcoming-heading">
        <h2 id="portal-upcoming-heading" class="mb-3 text-sm font-bold uppercase tracking-wider" style="color: var(--depla-navy);">
            Upcoming Confirmed Appointments
        </h2>

        @if ($upcoming->isEmpty())
            <p class="rounded-2xl border bg-white px-5 py-6 text-sm" style="border-color: var(--light-border); color: var(--body-text);">
                You have no upcoming confirmed appointments. Confirmed appointments will appear here with the clinic-approved schedule.
            </p>
        @else
            <div class="space-y-4">
                @foreach ($upcoming as $appointment)
                    @include('patient.appointments.partials.card', ['appointment' => $appointment])
                @endforeach
            </div>
        @endif
    </section>

    {{-- ═══ Appointment History ═══ --}}
    <section aria-labelledby="portal-history-heading">
        <h2 id="portal-history-heading" class="mb-3 text-sm font-bold uppercase tracking-wider" style="color: var(--depla-navy);">
            Appointment History
        </h2>

        @if ($history->isEmpty())
            <p class="rounded-2xl border bg-white px-5 py-6 text-sm" style="border-color: var(--light-border); color: var(--body-text);">
                You have no appointment history yet. Completed, cancelled, and declined appointments will appear here.
            </p>
        @else
            <div class="space-y-4">
                @foreach ($history as $appointment)
                    @include('patient.appointments.partials.card', ['appointment' => $appointment])
                @endforeach
            </div>

            <nav class="mt-6 flex items-center justify-between rounded-2xl border bg-white px-4 py-3"
                 style="border-color: var(--light-border);"
                 aria-label="Appointment history pages">
                <p class="text-xs" style="color: var(--body-text);">
                    Showing {{ $history->firstItem() }}&ndash;{{ $history->lastItem() }} of {{ $history->total() }} history entries
                </p>
                <div class="flex items-center gap-2">
                    @if ($history->onFirstPage())
                        <span class="rounded-lg px-3 py-1.5 text-sm font-semibold" style="background: #F6F4EE; color: #9CA3AF;">Previous</span>
                    @else
                        <a href="{{ $history->previousPageUrl() }}"
                           class="rounded-lg px-3 py-1.5 text-sm font-semibold transition hover:opacity-90"
                           style="background: #EDF5EC; color: var(--depla-dark-green);">
                            Previous
                        </a>
                    @endif

                    @if ($history->hasMorePages())
                        <a href="{{ $history->nextPageUrl() }}"
                           class="rounded-lg px-3 py-1.5 text-sm font-semibold transition hover:opacity-90"
                           style="background: #EDF5EC; color: var(--depla-dark-green);">
                            Next
                        </a>
                    @else
                        <span class="rounded-lg px-3 py-1.5 text-sm font-semibold" style="background: #F6F4EE; color: #9CA3AF;">Next</span>
                    @endif
                </div>
            </nav>
        @endif
    </section>
@endsection
