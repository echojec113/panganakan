<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Depla System') }} — Depla Family Care</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=Lora:ital,wght@0,500;0,600;1,400&display=swap" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 258px;

            --bg-login:     #dce8f8;
            --bg-base:      #eef4fc;
            --blue-accent:  #2563eb;
            --blue-deep:    #1a3d6e;
            --blue-mid:     #4a7fd4;
            --blue-light:   #c5d9f5;
            --border:       #d0e2f5;
            --text-primary: #1e2d45;
            --text-muted:   #64748b;
            --surface:      #ffffff;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', 'figtree', sans-serif;
            background: var(--bg-base);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
        }

        /* ══════════════════════════
           SIDEBAR
        ══════════════════════════ */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(175deg, #dce8f8 0%, #d0e4f7 35%, #c5dff5 100%);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 200;
            border-right: 1px solid rgba(37,99,235,0.18);
            box-shadow: 4px 0 20px rgba(30,70,140,0.11);
            transition: transform 0.3s ease;
        }

        /* ── Brand ── */
        .sidebar-brand {
            padding: 24px 18px 22px;
            border-bottom: 1px solid rgba(37,99,235,0.15);
            display: flex;
            align-items: center;
            gap: 14px;
            background: linear-gradient(180deg, rgba(255,255,255,0.25) 0%, rgba(255,255,255,0) 100%);
            position: relative;
        }

        .sidebar-logo {
            width: 56px;
            height: 56px;
            object-fit: contain;
            flex-shrink: 0;
            filter: drop-shadow(0 3px 8px rgba(30,70,140,0.22));
            transition: transform 0.3s ease;
        }

        .sidebar-logo:hover { transform: scale(1.05); }

        .sidebar-brand-text h1 {
            font-family: 'Lora', serif;
            font-size: 15px;
            font-weight: 700;
            color: var(--blue-deep);
            line-height: 1.3;
            letter-spacing: -0.2px;
        }

        .sidebar-brand-text p {
            font-size: 9.5px;
            color: rgba(26,61,110,0.65);
            letter-spacing: 1.4px;
            text-transform: uppercase;
            margin-top: 2px;
            font-weight: 600;
        }

        /* ── Mobile close button inside sidebar ── */
        .sidebar-close-btn {
            display: none;
            position: absolute;
            top: 14px;
            right: 14px;
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: rgba(37,99,235,0.12);
            border: 1px solid rgba(37,99,235,0.2);
            cursor: pointer;
            align-items: center;
            justify-content: center;
            color: var(--blue-deep);
            font-size: 18px;
            line-height: 1;
            transition: background 0.17s;
        }

        .sidebar-close-btn:hover {
            background: rgba(37,99,235,0.22);
        }

        /* ── Nav label ── */
        .nav-label {
            font-size: 9px;
            letter-spacing: 1.8px;
            text-transform: uppercase;
            color: rgba(26,61,110,0.48);
            padding: 18px 16px 6px;
            font-weight: 700;
        }

        /* ── Scroll area ── */
        .sidebar-nav {
            flex: 1;
            padding: 8px 10px 14px;
            overflow-y: auto;
            scrollbar-width: none;
        }
        .sidebar-nav::-webkit-scrollbar { display: none; }

        /* ── Nav item ── */
        .nav-item {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 10px 12px;
            border-radius: 11px;
            color: var(--blue-deep);
            font-size: 13.5px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.18s ease;
            margin-bottom: 2px;
        }

        .nav-item:hover {
            background: rgba(37,99,235,0.14);
            color: var(--blue-accent);
            transform: translateX(2px);
        }

        .nav-item.active {
            background: rgba(37,99,235,0.18);
            color: var(--blue-accent);
            font-weight: 600;
            box-shadow: inset 4px 0 0 var(--blue-accent), 0 2px 8px rgba(37,99,235,0.15);
        }

        .nav-icon {
            width: 32px; height: 32px;
            border-radius: 9px;
            background: rgba(255,255,255,0.70);
            border: 1px solid rgba(37,99,235,0.16);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
            transition: all 0.18s ease;
        }

        .nav-item:hover .nav-icon {
            background: rgba(255,255,255,0.85);
            border-color: rgba(37,99,235,0.25);
        }

        .nav-item.active .nav-icon {
            background: rgba(37,99,235,0.16);
            border-color: rgba(37,99,235,0.32);
            box-shadow: 0 2px 6px rgba(37,99,235,0.18);
        }

        .nav-divider {
            height: 1px;
            background: rgba(37,99,235,0.15);
            margin: 10px 12px;
        }

        /* ── Footer ── */
        .sidebar-footer {
            padding: 14px 16px;
            border-top: 1px solid rgba(37,99,235,0.15);
            background: linear-gradient(180deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.15) 100%);
        }

        .system-status {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11.5px;
            color: rgba(26,61,110,0.58);
            font-weight: 500;
        }

        .status-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 7px rgba(34,197,94,0.55);
            animation: pulse 2.2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50%       { opacity: 0.42; }
        }

        /* ══════════════════════════
           OVERLAY BACKDROP (mobile)
        ══════════════════════════ */
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 30, 60, 0.45);
            z-index: 150;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }

        /* ══════════════════════════
           MAIN WRAPPER
        ══════════════════════════ */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ══════════════════════════
           TOP BAR
        ══════════════════════════ */
        .topbar {
            background: linear-gradient(90deg, rgba(255,255,255,0.98) 0%, rgba(248,250,252,0.96) 100%);
            border-bottom: 1px solid rgba(37,99,235,0.12);
            padding: 0 26px;
            height: 62px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: 0 10px 30px rgba(30,70,140,0.06);
            backdrop-filter: blur(10px);
        }

        .topbar-left { display: flex; align-items: center; gap: 12px; }

        /* ── Hamburger button ── */
        .hamburger-btn {
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 5px;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--bg-base);
            border: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.17s;
            flex-shrink: 0;
        }

        .hamburger-btn:hover {
            background: #dce8fb;
            border-color: var(--blue-accent);
        }

        .hamburger-btn span {
            display: block;
            width: 18px;
            height: 2px;
            background: var(--blue-deep);
            border-radius: 2px;
            transition: all 0.25s ease;
        }

        /* Animate to X when open */
        .hamburger-btn.open span:nth-child(1) {
            transform: translateY(7px) rotate(45deg);
        }
        .hamburger-btn.open span:nth-child(2) {
            opacity: 0;
            transform: scaleX(0);
        }
        .hamburger-btn.open span:nth-child(3) {
            transform: translateY(-7px) rotate(-45deg);
        }

        .page-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.3px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11.5px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .breadcrumb a { color: var(--blue-accent); text-decoration: none; }

        .topbar-right { display: flex; align-items: center; gap: 11px; }

        /* Date chip */
        .date-chip {
            font-size: 12px;
            color: var(--text-muted);
            background: var(--bg-base);
            border: 1px solid var(--border);
            padding: 5px 12px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }

        /* Icon button */
        .icon-btn {
            width: 38px; height: 38px;
            border-radius: 10px;
            background: var(--bg-base);
            border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            transition: all 0.17s;
            color: var(--text-muted);
            text-decoration: none;
            position: relative;
        }

        .icon-btn:hover {
            background: #dce8fb;
            border-color: var(--blue-accent);
            color: var(--blue-accent);
        }

        .icon-btn .badge {
            position: absolute;
            top: -3px; right: -3px;
            width: 16px; height: 16px;
            background: #ef4444;
            border-radius: 50%;
            border: 2px solid white;
            font-size: 8px;
            color: white;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700;
        }

        /* User chip */
        .user-menu {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 5px 13px 5px 7px;
            border-radius: 12px;
            background: var(--bg-base);
            border: 1px solid var(--border);
            cursor: default;
            transition: all 0.17s;
        }

        .user-avatar {
            width: 32px; height: 32px;
            border-radius: 9px;
            background: linear-gradient(135deg, var(--blue-accent), var(--blue-deep));
            display: flex; align-items: center; justify-content: center;
            color: white;
            font-size: 13px;
            font-weight: 700;
        }

        .user-info .user-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            line-height: 1.2;
        }

        .user-info .user-role {
            font-size: 10.5px;
            color: var(--text-muted);
            text-transform: capitalize;
        }

        /* Logout */
        .logout-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12.5px;
            color: #ef4444;
            background: none;
            border: 1px solid rgba(239,68,68,0.22);
            border-radius: 8px;
            padding: 6px 13px;
            cursor: pointer;
            transition: all 0.17s;
            font-family: inherit;
            font-weight: 500;
        }

        .logout-btn:hover {
            background: rgba(239,68,68,0.07);
            border-color: rgba(239,68,68,0.45);
        }

        /* ══════════════════════════
           PAGE CONTENT
        ══════════════════════════ */
        .page-content {
            flex: 1;
            padding: 28px 30px;
        }

        .page-shell {
            background: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(247,250,255,0.96) 100%);
            border: 1px solid rgba(37,99,235,0.12);
            border-radius: 24px;
            box-shadow: 0 18px 45px rgba(30,70,140,0.08);
            min-height: calc(100vh - 128px);
            padding: 24px;
        }

        /* ══════════════════════════
           RESPONSIVE
        ══════════════════════════ */
        @media (max-width: 768px) {
            /* Sidebar hidden by default on mobile */
            .sidebar {
                transform: translateX(-258px);
                width: 258px;
                z-index: 200;
            }

            /* Sidebar slides in when .open is added */
            .sidebar.open {
                transform: translateX(0);
                box-shadow: 6px 0 30px rgba(30,70,140,0.22);
            }

            /* Show close button inside sidebar */
            .sidebar-close-btn {
                display: flex;
            }

            /* Main content takes full width */
            .main-wrapper {
                margin-left: 0;
            }

            /* Show hamburger */
            .hamburger-btn {
                display: flex;
            }

            .page-content {
                padding: 20px 16px;
            }

            .date-chip {
                display: none;
            }

            /* Hide user name text on very small screens, keep avatar */
            .user-info {
                display: none;
            }

            .user-menu {
                padding: 5px 7px;
            }

            /* Shrink logout to icon only on tiny screens */
            .logout-btn span.logout-text {
                display: none;
            }
        }

        @media (max-width: 400px) {
            .topbar {
                padding: 0 14px;
            }

            .page-title {
                font-size: 15px;
            }
        }

        



    </style>
</head>

<body>

{{-- ===================== OVERLAY ===================== --}}
<div class="sidebar-overlay" id="sidebarOverlay"></div>

{{-- ===================== SIDEBAR ===================== --}}
<aside class="sidebar" id="sidebar">

    {{-- Brand --}}
    <div class="sidebar-brand">
        <img
            src="{{ asset('images/logo.png') }}"
            alt="Depla Family Care Logo"
            class="sidebar-logo"
        >
        <div class="sidebar-brand-text">
            <h1>Depla Family Care</h1>
            <p>Maternity &amp; Lying-in</p>
        </div>

        {{-- Close button (mobile only) --}}
        <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Close sidebar">
            &times;
        </button>
    </div>

    {{-- Navigation --}}
    <nav class="sidebar-nav">

        <div class="nav-label">Main Menu</div>

        <a href="{{ route('dashboard') }}"
           class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg></span>
            Dashboard
        </a>

        @if(auth()->user()->role !== 'admin')
        <a href="{{ route('patients.index') }}"
           class="nav-item {{ request()->routeIs('patients.*') ? 'active' : '' }}">
            <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>
            Patients
        </a>
        @endif

        @if(auth()->user()->role !== 'admin')
        <a href="{{ route('prenatal-visits.index') }}"
           class="nav-item {{ request()->routeIs('prenatal-visits.*') ? 'active' : '' }}">
            <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2c6.627 0 12 1.34 12 3v7c0 1.66-5.373 3-12 3S0 13.66 0 12V5c0-1.66 5.373-3 12-3z"></path><path d="M12 23v-9"></path><path d="M0 12c0 1.66 5.373 3 12 3s12-1.34 12-3"></path></svg></span>
            Prenatal Visits
        </a>
        @endif

        <a href="{{ route('risk.monitoring') }}"
           class="nav-item {{ request()->routeIs('risk.*') ? 'active' : '' }}">
            <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3.05h16.94a2 2 0 0 0 1.71-3.05L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg></span>
            Risk Monitoring
        </a>

        <a href="{{ route('patients.delivered') }}"
           class="nav-item {{ request()->routeIs('patients.delivered') ? 'active' : '' }}">
            <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg></span>
            Delivered Patients
        </a>

        <a href="{{ route('referrals.index') }}"
           class="nav-item {{ request()->routeIs('referrals.*') ? 'active' : '' }}">
            <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="11" x2="12" y2="17"></line><line x1="9" y1="14" x2="15" y2="14"></line></svg></span>
            Referrals
        </a>

        

        @if(auth()->user()->role === 'admin')
            <div class="nav-divider"></div>
            <div class="nav-label">Administration</div>

            <a href="{{ route('staff.index') }}"
               class="nav-item {{ request()->routeIs('staff.*') ? 'active' : '' }}">
                <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M12 1v6m0 6v6M4.22 4.22l4.24 4.24m5.08 5.08l4.24 4.24M1 12h6m6 0h6M4.22 19.78l4.24-4.24m5.08-5.08l4.24-4.24"></path></svg></span>
                Manage Staff
            </a>

            <a href="{{ route('audit-logs.index') }}"
               class="nav-item {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">
                <span class="nav-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="11" x2="12" y2="17"></line><line x1="9" y1="14" x2="15" y2="14"></line></svg></span>
                Audit Logs
            </a>
        @endif

    </nav>

   
</aside>


{{-- ===================== MAIN WRAPPER ===================== --}}
<div class="main-wrapper">

    {{-- TOP HEADER --}}
    <header class="topbar">

        <div class="topbar-left">

            {{-- Hamburger (visible on mobile only) --}}
            <button class="hamburger-btn" id="hamburgerBtn" aria-label="Open navigation menu">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <div>
                <div class="page-title">{{ $header ?? 'Dashboard' }}</div>
                <div class="breadcrumb">
                    <a href="{{ route('dashboard') }}">Home</a>
                    <span>›</span>
                    <span>{{ $header ?? 'Dashboard' }}</span>
                </div>
            </div>
        </div>

        <div class="topbar-right">

            {{-- Date --}}
            <div class="date-chip">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                <span id="current-date">—</span>
            </div>

            {{-- Notifications --}}
            <a href="#" class="icon-btn" title="Notifications">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
                <span class="badge">3</span>
            </a>

            {{-- User --}}
            <div class="user-menu">
                <div class="user-avatar">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div class="user-info">
                    <div class="user-name">{{ auth()->user()->name }}</div>
                    <div class="user-role">{{ auth()->user()->role ?? 'Staff' }}</div>
                </div>
            </div>

            {{-- Logout --}}
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="logout-btn">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/>
                        <line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    <span class="logout-text">Logout</span>
                </button>
            </form>

        </div>
    </header>

    {{-- PAGE CONTENT --}}
    <main class="page-content">
        <div class="page-shell">
            {{ $slot }}
        </div>
    </main>

</div>

<script>
    // Current date
    const d = new Date();
    document.getElementById('current-date').textContent =
        d.toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });

    // Sidebar toggle
    const sidebar     = document.getElementById('sidebar');
    const overlay     = document.getElementById('sidebarOverlay');
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const closeBtn    = document.getElementById('sidebarCloseBtn');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        hamburgerBtn.classList.add('open');
        document.body.style.overflow = 'hidden'; // prevent background scroll
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        hamburgerBtn.classList.remove('open');
        document.body.style.overflow = '';
    }

    hamburgerBtn.addEventListener('click', () => {
        sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
    });

    closeBtn.addEventListener('click', closeSidebar);
    overlay.addEventListener('click', closeSidebar);

    // Close sidebar when a nav link is tapped on mobile
    document.querySelectorAll('.nav-item').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) closeSidebar();
        });
    });

    // Reset on resize back to desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });
</script>

</body>
</html>