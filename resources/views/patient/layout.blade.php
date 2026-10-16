{{-- DEPLA Phase 3A — shared patient-facing portal layout.
     Standalone page (no clinic sidebar, no staff navigation, no admin
     chrome): DEPLA branding, the Patient Portal label, Dashboard / My
     Profile navigation and a POST-only logout with CSRF. Theming reuses
     the established DEPLA palette (navy #19355F, green #55B85A, warm
     white #FCFBF8) with local Vite assets only — no external UI
     libraries and no CDN dependencies.

     Responsive header (dashboard responsive-UI pass): below 1024px the
     portal navigation collapses into an accessible expandable panel
     driven by a menu button (aria-expanded / aria-controls, Escape to
     close, auto-close on link selection). At 1024px and above the
     horizontal navigation is shown directly and the menu button is
     hidden. Route destinations, active-page logic and the POST logout
     form are unchanged. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - Patient Portal</title>

    <!-- Scripts (local build) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --depla-navy: #19355F;
            --depla-green: #55B85A;
            --depla-dark-green: #367E4B;
            --warm-white: #FCFBF8;
            --body-text: #657083;
            --light-border: #E7E9E5;
        }

        body {
            font-family: 'DM Sans', 'figtree', system-ui, -apple-system, sans-serif;
            background: var(--warm-white);
            color: var(--depla-navy);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
        }

        .portal-heading {
            font-family: 'DM Serif Display', Georgia, 'Times New Roman', serif;
            font-weight: 400;
        }

        /* ── Skip link: becomes visible when focused with the keyboard ── */
        .portal-skip:focus {
            position: static;
            display: inline-block;
            width: auto;
            height: auto;
            clip: auto;
            clip-path: none;
            margin: 0.75rem;
            padding: 0.5rem 1rem;
            background: #fff;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--depla-navy);
            z-index: 50;
            outline: 2px solid var(--depla-green);
            outline-offset: 2px;
        }

        /* ── Header brand ───────────────────────────────────────────── */
        .portal-brand img {
            flex: 0 0 auto;
        }

        .portal-brand-title {
            font-size: 1rem;
            line-height: 1.3;
            margin: 0;
        }

        @media (min-width: 640px) {
            .portal-brand-title {
                font-size: 1.125rem;
            }
        }

        /* ── Mobile menu button (hidden at ≥1024px) ─────────────────── */
        .portal-menu-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 44px;
            min-width: 44px;
            padding: 0.5rem 0.875rem;
            border: 1px solid var(--light-border);
            border-radius: 0.75rem;
            background: #fff;
            color: var(--depla-navy);
            font-family: inherit;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.15s ease, border-color 0.15s ease;
        }

        .portal-menu-button:hover {
            background: #EDF5EC;
            border-color: #cfe3cc;
        }

        .portal-menu-button:focus-visible,
        .portal-nav a:focus-visible,
        .portal-nav button:focus-visible {
            outline: 2px solid var(--depla-green);
            outline-offset: 2px;
        }

        .portal-menu-label {
            display: none;
        }

        @media (min-width: 480px) {
            .portal-menu-label {
                display: inline;
            }
        }

        /* ── Portal navigation ──────────────────────────────────────────
           Mobile (<1024px): hidden panel, expanded by the menu button.
           Desktop (≥1024px): always-visible horizontal row. */
        .portal-nav {
            display: none;
            flex-direction: column;
            align-items: stretch;
            gap: 0.25rem;
            width: 100%;
            padding: 0.25rem 0 0.5rem;
        }

        .portal-nav.is-open {
            display: flex;
        }

        .portal-nav a {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
            min-height: 44px;
        }

        .portal-nav a:not(.portal-nav-link-active):hover {
            background: #EDF5EC;
        }

        .portal-nav a.portal-nav-link-active,
        .portal-nav a.portal-nav-link-active:hover {
            background: var(--depla-green);
        }

        .portal-nav form {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
        }

        .portal-nav form button {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 44px;
            transition: opacity 0.15s ease;
        }

        .portal-nav form button:hover {
            opacity: 0.9;
        }

        @media (min-width: 1024px) {
            .portal-menu-button {
                display: none;
            }

            .portal-nav {
                display: flex;
                flex-direction: row;
                align-items: center;
                gap: 0.5rem;
                width: auto;
                padding: 0;
            }

            .portal-nav a {
                width: auto;
                min-height: 40px;
            }

            .portal-nav form {
                display: inline;
                width: auto;
                margin-top: 0;
            }

            .portal-nav form button {
                width: auto;
                min-height: 40px;
            }
        }

        /* ── Footer spacing (text content is unchanged) ──────────────── */
        .portal-footer-inner {
            padding-top: 1.25rem;
            padding-bottom: 1.25rem;
        }

        @media (min-width: 640px) {
            .portal-footer-inner {
                padding-top: 1.5rem;
                padding-bottom: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <a href="#portal-main"
       class="portal-skip sr-only">
        Skip to content
    </a>

    <!-- ═══════════════════════════════════════════
         PORTAL HEADER — branding, navigation, logout
    ════════════════════════════════════════════ -->
    <header class="border-b bg-white" style="border-color: var(--light-border);">
        <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">

            <!-- Brand + mobile menu button (same row on small screens) -->
            <div class="flex items-center justify-between gap-3">
                <!-- Brand -->
                <div class="portal-brand flex items-center gap-3">
                    <img src="{{ asset('images/logo.png') }}"
                         alt=""
                         aria-hidden="true"
                         class="h-10 w-10 rounded-full object-contain">
                    <div style="line-height: 1.25;">
                        <p class="portal-heading portal-brand-title" style="color: var(--depla-navy);">DEPLA Family Care</p>
                        <p class="text-[11px] font-semibold uppercase" style="color: var(--depla-dark-green); letter-spacing: 0.15em;">
                            Patient Portal
                        </p>
                    </div>
                </div>

                <!-- Mobile menu button — expands/collapses the portal nav -->
                <button type="button"
                        id="portal-menu-button"
                        class="portal-menu-button"
                        aria-expanded="false"
                        aria-controls="portal-nav"
                        aria-label="Open main menu">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                    <span class="portal-menu-label">Menu</span>
                </button>
            </div>

            <!-- Navigation + logout -->
            <nav id="portal-nav" class="portal-nav" aria-label="Patient portal">
                <a href="{{ route('patient.portal.coming-soon') }}"
                   @class([
                       'portal-nav-link rounded-xl px-4 py-2 text-sm font-semibold transition-colors',
                       request()->routeIs('patient.portal.coming-soon')
                           ? 'portal-nav-link-active text-white'
                           : 'text-[#19355F]',
                   ])
                   @if(request()->routeIs('patient.portal.coming-soon')) style="background: var(--depla-green);" @endif>
                    Dashboard
                </a>

                {{-- My Appointments — Phase 3B.3 functional online request
                     module. Active state covers the list, the request form
                     and the POST target's redirect landing. --}}
                <a href="{{ route('patient.appointments.index') }}"
                   @class([
                       'portal-nav-link inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold transition-colors',
                       request()->routeIs('patient.appointments.*')
                           ? 'portal-nav-link-active text-white'
                           : 'text-[#19355F]',
                   ])
                   @if(request()->routeIs('patient.appointments.*')) style="background: var(--depla-green);" @endif>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    My Appointments
                </a>

                {{-- My Medical Records — Phase 3E.2 read-only records page. --}}
                <a href="{{ route('patient.medical-records.index') }}"
                   @class([
                       'portal-nav-link inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold transition-colors',
                       request()->routeIs('patient.medical-records.*')
                           ? 'portal-nav-link-active text-white'
                           : 'text-[#19355F]',
                   ])
                   @if(request()->routeIs('patient.medical-records.*')) style="background: var(--depla-green);" @endif>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    My Medical Records
                </a>

                {{-- Pregnancy History — Phase 3H.4 read-only listing of the
                     current (pointer) pregnancy plus STRICTLY verified
                     previous episodes. My Medical Records stays the current
                     pregnancy's records page; this item never replaces it. --}}
                <a href="{{ route('patient.pregnancies.index') }}"
                   @class([
                       'portal-nav-link inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold transition-colors',
                       request()->routeIs('patient.pregnancies.*')
                           ? 'portal-nav-link-active text-white'
                           : 'text-[#19355F]',
                   ])
                   @if(request()->routeIs('patient.pregnancies.*')) style="background: var(--depla-green);" @endif>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Pregnancy History
                </a>

                {{-- Notifications — Phase 3B.4 portal notification tray.
                     The composer (PatientNotificationComposer, bound to
                     patient.layout) supplies the latest 5 + unread count
                     for the authenticated PATIENT account only. --}}
                <a href="{{ route('patient.notifications.index') }}"
                   @class([
                       'portal-nav-link relative inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold transition-colors',
                       request()->routeIs('patient.notifications.*')
                           ? 'portal-nav-link-active text-white'
                           : 'text-[#19355F]',
                   ])
                   @if(request()->routeIs('patient.notifications.*')) style="background: var(--depla-green);" @endif>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    Notifications
                    @if (($patientUnreadNotificationCount ?? 0) > 0)
                        <span class="inline-flex items-center justify-center rounded-full px-1.5 text-[11px] font-bold"
                              style="min-width: 1.15rem; line-height: 1.25rem; background: var(--depla-dark-green); color: #fff;"
                              aria-label="{{ $patientUnreadNotificationCount }} unread notifications">
                            {{ $patientUnreadNotificationCount > 99 ? '99+' : $patientUnreadNotificationCount }}
                        </span>
                    @endif
                </a>

                <a href="{{ route('patient.profile.show') }}"
                   @class([
                       'portal-nav-link rounded-xl px-4 py-2 text-sm font-semibold transition-colors',
                       request()->routeIs('patient.profile.show')
                           ? 'portal-nav-link-active text-white'
                           : 'text-[#19355F]',
                   ])
                   @if(request()->routeIs('patient.profile.show')) style="background: var(--depla-green);" @endif>
                    My Profile
                </a>

                {{-- Logout stays POST + CSRF (routes/auth.php). No GET
                     logout route exists and none was added. --}}
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit"
                            class="rounded-xl px-4 py-2 text-sm font-semibold text-white"
                            style="background: var(--depla-dark-green);">
                        Log Out
                    </button>
                </form>
            </nav>
        </div>
    </header>

    <!-- ═══════════════════════════════════════════
         MAIN CONTENT
    ════════════════════════════════════════════ -->
    <main id="portal-main" class="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6 lg:px-8">
        @yield('content')
    </main>

    <!-- ═══════════════════════════════════════════
         FOOTER — single, patient-safe help line
    ════════════════════════════════════════════ -->
    <footer class="border-t bg-white" style="border-color: var(--light-border);">
        <div class="portal-footer-inner mx-auto max-w-6xl px-4 text-center text-xs sm:px-6 lg:px-8" style="color: var(--body-text);">
            Need help with your account? Please contact the clinic for assistance.
        </div>
    </footer>

    {{-- Mobile navigation behaviour: menu button toggles the portal nav
         panel, Escape closes it (returning focus to the button), and
         choosing any navigation link closes it again. Purely presentational;
         it performs no requests and touches no route destinations. --}}
    <script>
        (function () {
            var button = document.getElementById('portal-menu-button');
            var nav = document.getElementById('portal-nav');

            if (!button || !nav) {
                return;
            }

            function setMenuOpen(open) {
                nav.classList.toggle('is-open', open);
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
                button.setAttribute('aria-label', open ? 'Close main menu' : 'Open main menu');
            }

            button.addEventListener('click', function () {
                setMenuOpen(!nav.classList.contains('is-open'));
            });

            nav.addEventListener('click', function (event) {
                if (event.target.closest('a')) {
                    setMenuOpen(false);
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && nav.classList.contains('is-open')) {
                    setMenuOpen(false);
                    button.focus();
                }
            });

            window.addEventListener('resize', function () {
                if (window.matchMedia('(min-width: 1024px)').matches && nav.classList.contains('is-open')) {
                    setMenuOpen(false);
                }
            });
        })();
    </script>
</body>
</html>
