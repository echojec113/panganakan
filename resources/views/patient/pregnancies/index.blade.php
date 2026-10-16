{{-- DEPLA Phase 3H.4 / 3H.4A — Pregnancy History (patient portal, read-only,
     DELIVERED episodes only). Lists one card per VERIFIED previous DELIVERED
     episode (strict user_pregnancy_links membership; ONGOING episodes, the
     current pointer episode, archived episodes and anything foreign or
     unlinked are excluded - the current pregnancy lives on the Patient
     Dashboard and My Medical Records). "View Medical Records" opens the
     historical seven-tab records view (strictly membership-checked, 404
     for anything out of scope). This page performs NO writes: no switching,
     linking, editing, uploading or deleting endpoint exists, and no "Link
     Previous Pregnancy" control exists anywhere. Visible data is limited
     to safe dates and obstetric counts - no internal database IDs are
     rendered as labels, and no email, contact number, birthdate, address
     or clinical-intern field is selected. --}}
@extends('patient.layout')

@section('content')
    <div class="mb-5">
        <a href="{{ route('patient.portal.coming-soon') }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold transition-colors hover:underline"
           style="color: var(--depla-dark-green);">
            &larr; Back to Dashboard
        </a>
    </div>

    {{-- ═══ Page header ═══ --}}
    <header class="mb-5 overflow-hidden rounded-2xl border bg-white"
            style="border-color: var(--light-border);">
        <div class="flex flex-col gap-3 px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6 sm:py-6">
            <div class="min-w-0">
                <h1 class="portal-heading text-2xl leading-tight sm:text-3xl" style="color: var(--depla-navy);">
                    Pregnancy History
                </h1>
                <p class="mt-1.5 max-w-2xl text-sm leading-6" style="color: var(--body-text);">
                    View your previous delivered pregnancies and their medical records.
                </p>
            </div>

            <span class="inline-flex shrink-0 self-start items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-bold uppercase tracking-wider sm:self-start"
                  style="background: #EDF5EC; color: var(--depla-dark-green);">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
                Read-only view
            </span>
        </div>

        <p class="border-t px-5 py-3 text-xs leading-5 sm:px-6"
           style="border-color: var(--light-border); color: var(--body-text);">
            Nothing on this page can be changed from your account.
        </p>
    </header>

    {{-- ═══ Previous Delivered Pregnancies ═══ --}}
    <section aria-label="Previous delivered pregnancies">
        <h2 class="mb-3 text-sm font-bold uppercase tracking-wider" style="color: var(--depla-dark-green);">
            Previous Delivered Pregnancies
        </h2>

        @if (count($previous) === 0)
            <p class="rounded-2xl border bg-white px-5 py-6 text-sm"
               style="border-color: var(--light-border); color: var(--body-text);">
                You have no previous delivered pregnancy records available yet.
            </p>
        @else
            <div class="space-y-4">
                @foreach ($previous as $index => $previousPregnancy)
                    <article class="overflow-hidden rounded-2xl border bg-white"
                             style="border-color: var(--light-border);"
                             aria-label="Previous delivered pregnancy {{ $index + 1 }}">
                        <div class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider"
                                      style="background: #F4F6F9; color: var(--body-text);">
                                    Delivered Pregnancy
                                </span>

                                <dl class="mt-2.5 flex flex-wrap gap-x-6 gap-y-1 text-sm" style="color: var(--body-text);">
                                    @if ($previousPregnancy['delivery_date'] ?? null)
                                        <div class="flex items-baseline gap-1.5">
                                            <dt class="text-xs font-semibold uppercase tracking-wider">Delivered</dt>
                                            <dd class="font-medium" style="color: var(--depla-navy);">{{ $previousPregnancy['delivery_date'] }}</dd>
                                        </div>
                                    @endif

                                    @if ($previousPregnancy['lmp'] ?? null)
                                        <div class="flex items-baseline gap-1.5">
                                            <dt class="text-xs font-semibold uppercase tracking-wider">LMP</dt>
                                            <dd class="font-medium" style="color: var(--depla-navy);">{{ $previousPregnancy['lmp'] }}</dd>
                                        </div>
                                    @endif

                                    @if ($previousPregnancy['edd'] ?? null)
                                        <div class="flex items-baseline gap-1.5">
                                            <dt class="text-xs font-semibold uppercase tracking-wider">EDD</dt>
                                            <dd class="font-medium" style="color: var(--depla-navy);">{{ $previousPregnancy['edd'] }}</dd>
                                        </div>
                                    @endif

                                    @if (($previousPregnancy['gravida'] ?? null) !== null)
                                        <div class="flex items-baseline gap-1.5">
                                            <dt class="text-xs font-semibold uppercase tracking-wider">Gravida</dt>
                                            <dd class="font-medium" style="color: var(--depla-navy);">{{ $previousPregnancy['gravida'] }}</dd>
                                        </div>
                                    @endif

                                    @if (($previousPregnancy['para'] ?? null) !== null)
                                        <div class="flex items-baseline gap-1.5">
                                            <dt class="text-xs font-semibold uppercase tracking-wider">Para</dt>
                                            <dd class="font-medium" style="color: var(--depla-navy);">{{ $previousPregnancy['para'] }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            </div>

                            {{-- episodeId is used only to build the route
                                 URL (the show controller re-verifies strict
                                 link membership and delivered-only scope
                                 for it); it is never displayed as visible
                                 text. --}}
                            <a href="{{ route('patient.pregnancies.show', ['episodeId' => $previousPregnancy['episodeId']]) }}"
                               class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors"
                               style="border: 1.5px solid var(--depla-dark-green); color: var(--depla-dark-green);"
                               aria-label="View medical records for previous delivered pregnancy {{ $index + 1 }}">
                                View Medical Records &rarr;
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
