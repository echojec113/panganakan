{{-- DEPLA Phase 3G — Patient Dashboard UI redesign (UI-only sprint).
     Four cards in a 2x2 grid on the left, Phase 3F widget column on the
     right (~2:1 from 1024px up): Next Appointment + Maternity Tracker.
     Both widgets render ONLY the controller's prepared presentation
     arrays (`$nextAppointment`, `$maternityTracker`) — no calculation,
     no fake data, no progress bar, no provider/clinical staff data.
     Preserved verbatim: the four card routes/labels/body copy, the
     information notice text and conditions, the single logout form (in
     the shared layout), and the Phase 3A required/forbidden strings.
     Responsive: 1 column below 640px, 2-column cards at 640px+ with
     widgets below, full 2:1 split at 1024px+. Shared layout untouched. --}}
@extends('patient.layout')

@section('content')
<div class="portal-dashboard">
    <style>
        /* ── Welcome section ─────────────────────────────────────── */
        .portal-dashboard .portal-welcome-row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }

        .portal-dashboard .portal-welcome-copy {
            min-width: 0;
            flex: 1 1 18rem;
        }

        .portal-dashboard .portal-welcome-heading {
            font-size: 1.75rem;
            line-height: 1.25;
            margin: 0;
            overflow-wrap: anywhere;
        }

        @media (min-width: 640px) {
            .portal-dashboard .portal-welcome-heading {
                font-size: 2rem;
            }
        }

        .portal-dashboard .portal-welcome-lead {
            margin-top: 0.5rem;
            font-size: 0.875rem;
            line-height: 1.625;
        }

        @media (min-width: 640px) {
            .portal-dashboard .portal-welcome-lead {
                font-size: 1rem;
            }
        }

        .portal-dashboard .portal-welcome-date {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            flex: 0 0 auto;
            padding: 0.5rem 0.875rem;
            background: #fff;
            border: 1px solid var(--light-border);
            border-radius: 9999px;
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--depla-navy);
            white-space: nowrap;
        }

        .portal-dashboard .portal-welcome-date svg {
            flex: 0 0 auto;
            color: var(--depla-dark-green);
        }

        /* ── Green information notice ────────────────────────────── */
        .portal-info-notice {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-top: 1.5rem;
            padding: 0.875rem 1rem;
            background: #EDF5EC;
            border: 1px solid #D8E9D5;
            border-radius: 0.875rem;
        }

        .portal-info-notice svg {
            flex: 0 0 auto;
            margin-top: 0.125rem;
            color: var(--depla-dark-green);
        }

        .portal-info-notice p {
            margin: 0;
            font-size: 0.875rem;
            line-height: 1.6;
            color: var(--depla-dark-green);
            overflow-wrap: anywhere;
        }

        @media (min-width: 640px) {
            .portal-info-notice {
                padding: 1rem 1.25rem;
            }
        }

        /* ── Main grid: primary cards + widget column ────────────── */
        .portal-dashboard .portal-main-grid {
            display: grid;
            gap: 1.5rem;
            margin-top: 2rem;
            grid-template-columns: minmax(0, 1fr);
            align-items: start;
        }

        @media (min-width: 1024px) {
            .portal-dashboard .portal-main-grid {
                grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
            }
        }

        .portal-dashboard .portal-primary-cards {
            display: grid;
            gap: 1.25rem;
            grid-template-columns: minmax(0, 1fr);
            min-width: 0;
        }

        @media (min-width: 640px) {
            .portal-dashboard .portal-primary-cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .portal-dashboard .portal-widget-column {
            display: grid;
            gap: 1.5rem;
            min-width: 0;
        }

        /* ── Dashboard cards ─────────────────────────────────────── */
        .portal-card,
        .portal-card-static {
            display: flex;
            flex-direction: column;
            height: 100%;
            min-width: 0;
            padding: 1.5rem;
            background: #fff;
            border: 1px solid var(--light-border);
            border-radius: 1rem;
            text-decoration: none;
            color: inherit;
        }

        .portal-card {
            transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
        }

        .portal-card:hover {
            border-color: #cfe3cc;
            box-shadow: 0 8px 24px rgba(25, 53, 95, 0.08);
            transform: translateY(-1px);
        }

        .portal-card:focus-visible {
            outline: 2px solid var(--depla-green);
            outline-offset: 2px;
        }

        .portal-card-icon-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .portal-card-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 0.75rem;
        }

        .portal-card-icon--soft {
            background: #E8F3E9;
        }

        .portal-card-icon--soft svg {
            color: var(--depla-dark-green);
        }

        .portal-card-arrow {
            flex: 0 0 auto;
            color: var(--depla-dark-green);
            margin-top: 0.375rem;
        }

        .portal-card-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            background: #EDF5EC;
            color: var(--depla-dark-green);
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .portal-card-title {
            margin-top: 1rem;
            font-size: 1.0625rem;
            font-weight: 700;
            line-height: 1.4;
            color: var(--depla-navy);
        }

        .portal-card-body {
            margin-top: 0.375rem;
            font-size: 0.875rem;
            line-height: 1.625;
            color: var(--body-text);
        }

        .portal-card-action {
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid var(--light-border);
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--depla-dark-green);
        }

        /* ── Widget cards (Next Appointment + Maternity Tracker) ─── */
        .portal-widget {
            display: flex;
            flex-direction: column;
            min-width: 0;
            background: #fff;
            border: 1px solid var(--light-border);
            border-radius: 1rem;
            padding: 1.5rem;
        }

        .portal-widget--accent {
            border-top: 4px solid var(--depla-green);
        }

        .portal-widget-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.125rem;
        }

        .portal-widget-header-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 9999px;
            background: #E8F3E9;
            color: var(--depla-dark-green);
        }

        .portal-widget-title {
            margin: 0;
            font-size: 1.125rem;
            line-height: 1.3;
            color: var(--depla-navy);
        }

        .portal-widget-status-chip {
            margin-left: auto;
            flex: 0 0 auto;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            background: #EDF5EC;
            color: var(--depla-dark-green);
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .portal-widget-status-chip--delivered {
            background: #DEF7E4;
            color: #14532d;
        }

        /* Next Appointment — inner detail panel */
        .portal-appointment-panel {
            padding: 1rem 1.125rem;
            background: #F6F8F6;
            border: 1px solid #E4EDE4;
            border-radius: 0.75rem;
            min-width: 0;
        }

        .portal-appointment-type {
            margin: 0;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--depla-dark-green);
            overflow-wrap: anywhere;
        }

        .portal-appointment-date {
            margin: 0.375rem 0 0;
            font-size: 0.9375rem;
            font-weight: 700;
            line-height: 1.45;
            color: var(--depla-navy);
            overflow-wrap: anywhere;
        }

        .portal-appointment-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.375rem 1rem;
            margin-top: 0.625rem;
        }

        .portal-appointment-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.8125rem;
            color: var(--body-text);
            overflow-wrap: anywhere;
        }

        .portal-appointment-meta-item svg {
            flex: 0 0 auto;
            color: var(--depla-dark-green);
        }

        .portal-appointment-meta-item--confirmed {
            font-weight: 600;
            color: var(--depla-dark-green);
        }

        .portal-widget-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.125rem;
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            background: var(--depla-dark-green);
            color: #fff;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            text-align: center;
            transition: background 0.15s ease;
        }

        .portal-widget-cta:hover {
            background: #2c6a3f;
        }

        .portal-widget-cta:focus-visible {
            outline: 2px solid var(--depla-dark-green);
            outline-offset: 2px;
        }

        .portal-widget-empty {
            margin: 0;
            padding: 1rem 1.125rem;
            background: #F6F8F6;
            border: 1px dashed #D8E9D5;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            line-height: 1.625;
            color: var(--body-text);
            overflow-wrap: anywhere;
        }

        .portal-widget-empty-link {
            display: inline-block;
            margin-top: 0.75rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--depla-dark-green);
        }

        /* Maternity Tracker — fact tiles */
        .portal-tracker-grid {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .portal-tracker-tile {
            min-width: 0;
            padding: 0.75rem 0.875rem;
            background: #F3F8F2;
            border-radius: 0.625rem;
            text-align: center;
        }

        .portal-tracker-tile-label {
            display: block;
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--body-text);
            overflow-wrap: anywhere;
        }

        .portal-tracker-tile-value {
            display: block;
            margin-top: 0.25rem;
            font-size: 0.9375rem;
            font-weight: 700;
            line-height: 1.4;
            color: var(--depla-navy);
            overflow-wrap: anywhere;
        }

        .portal-tracker-countdown {
            margin-top: 0.75rem;
            padding: 0.75rem 0.875rem;
            background: #EDF5EC;
            border: 1px solid #D8E9D5;
            border-radius: 0.625rem;
            text-align: center;
            min-width: 0;
        }

        .portal-tracker-countdown-label {
            display: block;
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--body-text);
        }

        .portal-tracker-countdown-value {
            display: block;
            margin-top: 0.25rem;
            font-size: 0.9375rem;
            font-weight: 700;
            line-height: 1.45;
            color: var(--depla-dark-green);
            overflow-wrap: anywhere;
        }
    </style>

    {{-- ═══ Welcome ═══ --}}
    <section aria-labelledby="portal-welcome-heading">
        <div class="portal-welcome-row">
            <div class="portal-welcome-copy">
                <h1 id="portal-welcome-heading"
                    class="portal-heading portal-welcome-heading"
                    style="color: var(--depla-navy);">
                    Welcome, {{ $patient->first_name }}!
                </h1>
                <p class="portal-welcome-lead" style="color: var(--body-text);">
                    Welcome to your DEPLA Family Care patient portal.
                </p>
            </div>

            {{-- Clinic-date badge (Asia/Manila — the same timezone policy
                 the controller uses for every dashboard decision). Purely
                 presentational; no data source beyond the clock. --}}
            <span class="portal-welcome-date">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                Today: {{ now(\App\Services\AppointmentManagementService::CLINIC_TIMEZONE)->format('M d, Y') }}
            </span>
        </div>

        {{-- Green information notice — text and meaning unchanged. --}}
        <div class="portal-info-notice" role="note">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p>Some services are not available yet. Your appointments, notifications and medical records are ready to use.</p>
        </div>
    </section>

    {{-- ═══ Main grid: primary cards (left) + widgets (right) ═══ --}}
    <div class="portal-main-grid">

        {{-- ── Left: four primary cards (2x2 from 640px) ─────────── --}}
        <section class="portal-primary-cards" aria-label="Portal services">

            {{-- 1. My Profile --}}
            <a href="{{ route('patient.profile.show') }}"
               class="portal-card"
               aria-label="My Profile: view your personal information">
                <span class="portal-card-icon-row">
                    <span class="portal-card-icon portal-card-icon--soft">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </span>
                    <svg class="portal-card-arrow h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                    </svg>
                </span>
                <span class="portal-card-title">My Profile</span>
                <span class="portal-card-body">
                    View your personal information.
                </span>
                <span class="portal-card-action">
                    Open My Profile &rarr;
                </span>
            </a>

            {{-- 2. My Appointments — functional (Phase 3B.3). --}}
            <a href="{{ route('patient.appointments.index') }}"
               class="portal-card"
               aria-label="My Appointments: view and request appointments">
                <span class="portal-card-icon-row">
                    <span class="portal-card-icon portal-card-icon--soft">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </span>
                    <svg class="portal-card-arrow h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                    </svg>
                </span>
                <span class="portal-card-title">My Appointments</span>
                <span class="portal-card-body">
                    Request a checkup, counseling, or delivery appointment online and track its status.
                </span>
                <span class="portal-card-action">
                    Open My Appointments &rarr;
                </span>
            </a>

            {{-- 3. Medical Records — functional (Phase 3E.2/3E.3). --}}
            <a href="{{ route('patient.medical-records.index') }}"
               class="portal-card"
               aria-label="My Medical Records: view your pregnancy records">
                <span class="portal-card-icon-row">
                    <span class="portal-card-icon portal-card-icon--soft">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </span>
                    <svg class="portal-card-arrow h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path>
                    </svg>
                </span>
                <span class="portal-card-title">Medical Records</span>
                <span class="portal-card-body">
                    View the records the clinic keeps for your current pregnancy.
                </span>
                <span class="portal-card-action">
                    Open My Medical Records &rarr;
                </span>
            </a>

            {{-- 4. Account Security — informational only, no metadata --}}
            <article class="portal-card-static">
                <span class="portal-card-icon-row">
                    <span class="portal-card-icon portal-card-icon--soft">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </span>
                    <span class="portal-card-badge">
                        Protected
                    </span>
                </span>
                <span class="portal-card-title">Account Security</span>
                <span class="portal-card-body">
                    Your account is protected.
                </span>
            </article>
        </section>

        {{-- ── Right: Phase 3F widgets ───────────────────────────── --}}
        <div class="portal-widget-column">

            {{-- ═══ Next Appointment widget ═══ --}}
            <section class="portal-widget portal-widget--accent"
                     aria-labelledby="portal-next-appointment-title">
                <div class="portal-widget-header">
                    <span class="portal-widget-header-icon" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </span>
                    <h2 id="portal-next-appointment-title"
                        class="portal-heading portal-widget-title">
                        Next Appointment
                    </h2>
                </div>

                @if ($nextAppointment !== null)
                    <div class="portal-appointment-panel">
                        <p class="portal-appointment-type">
                            {{ $nextAppointment['appointment_type_label'] }}
                        </p>
                        <p class="portal-appointment-date">
                            {{ $nextAppointment['effective_date_display'] }}
                        </p>
                        <div class="portal-appointment-meta">
                            <span class="portal-appointment-meta-item">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                {{ $nextAppointment['effective_time_display'] }}
                            </span>
                            <span class="portal-appointment-meta-item portal-appointment-meta-item--confirmed">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                {{ $nextAppointment['status_label'] }}
                            </span>
                        </div>
                    </div>

                    <a class="portal-widget-cta" href="{{ route('patient.appointments.index') }}">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        View My Appointments
                    </a>
                @else
                    <p class="portal-widget-empty">
                        You have no upcoming confirmed appointment.
                    </p>
                    <a class="portal-widget-empty-link" href="{{ route('patient.appointments.index') }}">
                        View My Appointments &rarr;
                    </a>
                @endif
            </section>

            {{-- ═══ Maternity Tracker widget ═══ --}}
            <section class="portal-widget"
                     aria-labelledby="portal-maternity-tracker-title">
                <div class="portal-widget-header">
                    <span class="portal-widget-header-icon" aria-hidden="true">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                        </svg>
                    </span>
                    <h2 id="portal-maternity-tracker-title"
                        class="portal-heading portal-widget-title">
                        Maternity Tracker
                    </h2>
                    <span class="portal-widget-status-chip{{ $maternityTracker['is_delivered'] ? ' portal-widget-status-chip--delivered' : '' }}">
                        {{ $maternityTracker['status_label'] }}
                    </span>
                </div>

                @if ($maternityTracker['is_delivered'])
                    {{-- Completion state — no countdown, no current weeks. --}}
                    <div class="portal-tracker-grid">
                        <div class="portal-tracker-tile">
                            <span class="portal-tracker-tile-label">Delivery Date</span>
                            <span class="portal-tracker-tile-value">
                                {{ $maternityTracker['delivery_date_display'] ?? 'Not recorded' }}
                            </span>
                        </div>
                        <div class="portal-tracker-tile">
                            <span class="portal-tracker-tile-label">Status</span>
                            <span class="portal-tracker-tile-value">
                                {{ $maternityTracker['status_label'] }}
                            </span>
                        </div>
                    </div>
                @else
                    {{-- Ongoing state — prepared values only, no
                         recalculation, no progress bar. --}}
                    <div class="portal-tracker-grid">
                        <div class="portal-tracker-tile">
                            <span class="portal-tracker-tile-label">Weeks Along</span>
                            <span class="portal-tracker-tile-value">
                                {{ $maternityTracker['weeks_along_display'] ?? 'Not recorded' }}
                            </span>
                        </div>
                        <div class="portal-tracker-tile">
                            <span class="portal-tracker-tile-label">Trimester</span>
                            <span class="portal-tracker-tile-value">
                                {{ $maternityTracker['trimester_label'] ?? 'Not recorded' }}
                            </span>
                        </div>
                    </div>

                    <div class="portal-tracker-countdown" style="margin-top: 0.75rem;">
                        <span class="portal-tracker-countdown-label">Estimated Due Date</span>
                        <span class="portal-tracker-countdown-value" style="color: var(--depla-navy);">
                            {{ $maternityTracker['edd_display'] ?? 'Not recorded' }}
                        </span>
                    </div>

                    <div class="portal-tracker-countdown">
                        <span class="portal-tracker-countdown-label">Due Date Countdown</span>
                        <span class="portal-tracker-countdown-value">
                            {{ $maternityTracker['edd_countdown_display'] }}
                        </span>
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection
