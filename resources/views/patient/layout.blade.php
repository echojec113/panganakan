{{-- DEPLA Phase 3A — shared patient-facing portal layout.
     Standalone page (no clinic sidebar, no staff navigation, no admin
     chrome): DEPLA branding, the Patient Portal label, Dashboard / My
     Profile navigation and a POST-only logout with CSRF. Theming reuses
     the established DEPLA palette (navy #19355F, green #55B85A, warm
     white #FCFBF8) with local Vite assets only — no external UI
     libraries and no CDN dependencies. --}}
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

        .portal-skip:focus {
            outline: 2px solid var(--depla-green);
            outline-offset: 2px;
        }
    </style>
</head>

<body>
    <a href="#portal-main"
       class="portal-skip sr-only focus:not-sr-only focus:absolute focus:z-50 focus:m-3 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-sm">
        Skip to content
    </a>

    <!-- ═══════════════════════════════════════════
         PORTAL HEADER — branding, navigation, logout
    ════════════════════════════════════════════ -->
    <header class="border-b bg-white" style="border-color: var(--light-border);">
        <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
            <!-- Brand -->
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/logo.png') }}"
                     alt=""
                     aria-hidden="true"
                     class="h-10 w-10 rounded-full object-contain">
                <div class="leading-tight">
                    <p class="portal-heading text-lg" style="color: var(--depla-navy);">DEPLA Family Care</p>
                    <p class="text-[11px] font-semibold uppercase tracking-widest" style="color: var(--depla-dark-green); letter-spacing: 0.15em;">
                        Patient Portal
                    </p>
                </div>
            </div>

            <!-- Navigation + logout -->
            <nav class="flex flex-wrap items-center gap-2 sm:gap-3" aria-label="Patient portal">
                <a href="{{ route('patient.portal.coming-soon') }}"
                   @class([
                       'rounded-xl px-4 py-2 text-sm font-semibold transition-colors',
                       request()->routeIs('patient.portal.coming-soon')
                           ? 'text-white'
                           : 'text-[#19355F] hover:bg-[#EDF5EC]',
                   ])
                   @if(request()->routeIs('patient.portal.coming-soon')) style="background: var(--depla-green);" @endif>
                    Dashboard
                </a>

                <a href="{{ route('patient.profile.show') }}"
                   @class([
                       'rounded-xl px-4 py-2 text-sm font-semibold transition-colors',
                       request()->routeIs('patient.profile.show')
                           ? 'text-white'
                           : 'text-[#19355F] hover:bg-[#EDF5EC]',
                   ])
                   @if(request()->routeIs('patient.profile.show')) style="background: var(--depla-green);" @endif>
                    My Profile
                </a>

                {{-- Logout stays POST + CSRF (routes/auth.php). No GET
                     logout route exists and none was added. --}}
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit"
                            class="rounded-xl px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
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
        <div class="mx-auto max-w-6xl px-4 py-5 text-center text-xs sm:px-6 lg:px-8" style="color: var(--body-text);">
            Need help with your account? Please contact the clinic for assistance.
        </div>
    </footer>
</body>
</html>
