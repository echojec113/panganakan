{{-- DEPLA Phase 3B.4 — Notifications (patient portal).
     Shows ONLY the authenticated patient's own database notifications
     (resolved by the controller from the authenticated user — no ID
     enters this view). Newest first, paginated, with unread items
     visually distinguished. Each item may carry a destination link back
     to My Appointments (built server-side from the notification payload's
     named route — never an arbitrary user-supplied URL). Mark-as-read
     actions are POST + CSRF and scoped to the owner. --}}
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
                Notifications
            </h1>
            <p class="mt-2 text-sm leading-6" style="color: var(--body-text);">
                Updates about your appointment requests. Only you can see these.
            </p>
        </div>

        @if ($unreadCount > 0)
            <form method="POST" action="{{ route('patient.notifications.markAllRead') }}">
                @csrf
                <button type="submit"
                        class="rounded-xl px-5 py-2.5 text-sm font-semibold transition hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
                        style="background: var(--depla-dark-green); color: #fff; outline-color: var(--depla-green);">
                    Mark all as read
                </button>
            </form>
        @endif
    </header>

    @if ($notifications->isEmpty())
        <p class="rounded-2xl border bg-white px-5 py-6 text-sm"
           style="border-color: var(--light-border); color: var(--body-text);">
            You have no notifications yet. Appointment updates will appear here.
        </p>
    @else
        <div class="space-y-3">
            @foreach ($notifications as $notification)
                @php
                    $nRead = $notification->read_at !== null;
                    $nData = $notification->data ?? [];
                    $nUrl = isset($nData['destination']['route'])
                        ? route($nData['destination']['route'], $nData['destination']['parameters'] ?? [])
                        : null;
                @endphp

                <article class="rounded-2xl border bg-white px-5 py-4"
                         style="border-color: var(--light-border);{{ $nRead ? '' : ' border-left: 3px solid var(--depla-green);' }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-2 text-sm font-bold" style="color: var(--depla-navy);">
                                {{ $nData['title'] ?? 'Notification' }}
                                @unless ($nRead)
                                    <span class="inline-block h-2 w-2 shrink-0 rounded-full"
                                          style="background: var(--depla-green);"
                                          aria-label="Unread"></span>
                                @endunless
                            </p>

                            <p class="mt-1 text-sm leading-6" style="color: var(--body-text);">
                                {{ $nData['message'] ?? '' }}
                            </p>

                            <p class="mt-2 text-xs" style="color: var(--body-text);">
                                {{ $notification->created_at->setTimezone('Asia/Manila')->format('M j, Y g:i A') }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            @if ($nUrl)
                                <a href="{{ $nUrl }}"
                                   class="rounded-lg px-3 py-1.5 text-sm font-semibold transition hover:opacity-90"
                                   style="background: #EDF5EC; color: var(--depla-dark-green);">
                                    {{ $nData['action_label'] ?? 'View' }}
                                </a>
                            @endif

                            @unless ($nRead)
                                <form method="POST" action="{{ route('patient.notifications.read', $notification->id) }}">
                                    @csrf
                                    <button type="submit"
                                            class="rounded-lg px-3 py-1.5 text-sm font-semibold transition hover:opacity-90"
                                            style="background: #F6F4EE; color: var(--depla-dark-green);">
                                        Mark as read
                                    </button>
                                </form>
                            @endunless
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <nav class="mt-6 flex items-center justify-between rounded-2xl border bg-white px-4 py-3"
             style="border-color: var(--light-border);"
             aria-label="Notification pages">
            <p class="text-xs" style="color: var(--body-text);">
                Showing {{ $notifications->firstItem() }}&ndash;{{ $notifications->lastItem() }} of {{ $notifications->total() }} notifications
            </p>
            <div class="flex items-center gap-2">
                @if ($notifications->onFirstPage())
                    <span class="rounded-lg px-3 py-1.5 text-sm font-semibold" style="background: #F6F4EE; color: #9CA3AF;">Previous</span>
                @else
                    <a href="{{ $notifications->previousPageUrl() }}"
                       class="rounded-lg px-3 py-1.5 text-sm font-semibold transition hover:opacity-90"
                       style="background: #EDF5EC; color: var(--depla-dark-green);">
                        Previous
                    </a>
                @endif

                @if ($notifications->hasMorePages())
                    <a href="{{ $notifications->nextPageUrl() }}"
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
@endsection
