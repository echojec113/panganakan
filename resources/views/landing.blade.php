<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>DEPLA Family Care</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">

    <style>
        /* ===== Landing Page Base ===== */
        body.landing {
            font-family: 'DM Sans', 'figtree', sans-serif;
            background: #fafafa;
            color: #334155;
            margin: 0;
            padding: 0;
            scroll-behavior: smooth;
        }

        /* ===== CONTAINER ===== */
        .landing-container {
            max-width: 1440px;
            margin-left: auto;
            margin-right: auto;
            padding-left: 2rem;
            padding-right: 2rem;
        }

        @media (min-width: 768px) {
            .landing-container {
                padding-left: 32px;
                padding-right: 32px;
            }
        }

        @media (min-width: 1024px) {
            .landing-container {
                padding-left: 48px;
                padding-right: 48px;
            }
        }

        /* ===== HEADER ===== */
        .landing-header {
            background: #ffffff;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid #e2e8f0;
            padding-top: 20px;
            padding-bottom: 20px;
        }

        .header-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .landing-logo {
            width: 40px;
            height: 40px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .landing-title {
            font-family: 'DM Sans', 'figtree', sans-serif;
            font-size: 1rem;
            font-weight: 500;
            color: #1e2d45;
            margin: 0;
            white-space: nowrap;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .landing-login-btn {
            padding: 6px 12px;
            background: transparent;
            color: #1e2d45;
            border: 1px solid #9ca3af;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            white-space: nowrap;
            font-family: 'DM Sans', 'figtree', sans-serif;
        }

        .landing-login-btn:hover {
            background: #f1f5f9;
            border-color: #64748b;
        }

        .landing-login-btn:focus-visible {
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
        }

        /* ===== HEADER NAVIGATION ===== */
        .header-nav {
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .header-nav-list {
            display: flex;
            gap: 1.5rem;
            margin: 0;
            padding: 0;
        }

        .header-nav-item {
            list-style: none;
        }

        .header-nav-link {
            font-family: 'DM Sans', 'figtree', sans-serif;
            font-size: 0.875rem;
            font-weight: 500;
            color: #64748b;
            text-decoration: none;
            transition: color 0.15s ease;
        }

        .header-nav-link:hover {
            color: #1e2d45;
        }

        .header-nav-link:focus-visible {
            color: #1e2d45;
            outline: 2px solid #3b82f6;
            outline-offset: 2px;
        }

        /* ===== HERO SECTION ===== */
        .hero-section {
            padding: 6rem 0;
            background: #fafafa;
        }

        .hero-wrapper {
            max-width: 1440px;
            margin-left: auto;
            margin-right: auto;
            padding-left: 2rem;
            padding-right: 2rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        @media (max-width: 1024px) {
            .hero-wrapper {
                grid-template-columns: 1fr;
                gap: 3rem;
            }
        }

        @media (max-width: 768px) {
            .hero-wrapper {
                gap: 2rem;
            }
        }

        .hero-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #d1f9e6;
            color: #059669;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 0.5rem;
        }

        .hero-heading {
            font-family: 'DM Sans', 'figtree', sans-serif;
            font-size: 2.25rem;
            font-weight: 600;
            line-height: 1.2;
            color: #1e2d45;
            margin: 0 0 1rem;
        }

        .hero-heading .highlight {
            color: #059669;
        }

        .hero-paragraph {
            font-size: 1.125rem;
            line-height: 1.7;
            color: #64748b;
            margin: 0 0 1.5rem;
        }

        .hero-button {
            display: inline-block;
            padding: 8px 24px;
            background: transparent;
            border: 1px solid #059669;
            color: #059669;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s ease;
            font-family: 'DM Sans', 'figtree', sans-serif;
            text-decoration: none;
        }

        .hero-button:hover {
            background: #059669;
            color: #ffffff;
        }

        .hero-button:focus-visible {
            outline: 2px solid #059669;
            outline-offset: 2px;
        }

        /* Vertically center hero text when in two-column layout */
        .hero-content {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        /* Hero image */
        .hero-image {
            display: flex;
            justify-content: center;
        }

        .hero-image img {
            max-width: 100%;
            width: 520px;
            height: auto;
            border-radius: 0.5rem;
            object-fit: cover;
            object-center;
        }

        /* ===== CONTACT & LOCATION ===== */
        .contact-section {
            padding: 6rem 0;
            background: #f1f5f9;
        }

        .contact-wrapper {
            max-width: 1440px;
            margin-left: auto;
            margin-right: auto;
            padding-left: 2rem;
            padding-right: 2rem;
        }

        @media (min-width: 768px) {
            .contact-wrapper {
                padding-left: 32px;
                padding-right: 32px;
            }
        }

        @media (min-width: 1024px) {
            .contact-wrapper {
                padding-left: 48px;
                padding-right: 48px;
            }
        }

        .section-wrapper {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-heading {
            font-family: 'DM Sans', 'figtree', sans-serif;
            font-size: 1.75rem;
            font-weight: 600;
            color: #1e2d45;
            margin: 0 0 0.5rem;
            padding-bottom: 0.25rem;
            border-bottom: 2px solid #10b981;
            display: inline-block;
        }

        .section-description {
            font-size: 1.0625rem;
            color: #64748b;
            margin: 0 auto 2rem;
            max-width: 640px;
            line-height: 1.6;
        }

        .contact-grid {
            max-width: 1440px;
            margin-left: auto;
            margin-right: auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 1024px) {
            .contact-grid {
                grid-template-columns: 1fr;
            }
        }

        .contact-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1.5rem;
        }

        .contact-card .icon {
            width: 24px;
            height: 24px;
            margin-bottom: 1rem;
        }

        .contact-card h3 {
            font-family: 'DM Sans', 'figtree', sans-serif;
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e2d45;
            margin: 0 0 0.75rem;
        }

        .contact-card p {
            font-size: 0.875rem;
            color: #64748b;
            margin: 0;
        }

        .contact-card .tel-number, .contact-card .cp-number {
            color: #334155;
            font-size: 0.875rem;
            display: block;
            margin-top: 0.25rem;
        }

        .contact-card .email-link {
            color: #64748b;
            font-size: 0.875rem;
            text-decoration: none;
            margin-top: 0.75rem;
            display: inline-block;
        }

        .contact-card .email-link:hover {
            color: #1e2d45;
        }

        /* ===== OUR SERVICES ===== */
        .services-section {
            padding: 6rem 0;
            background: #ffffff;
        }

        .services-wrapper {
            max-width: 1440px;
            margin-left: auto;
            margin-right: auto;
            padding-left: 2rem;
            padding-right: 2rem;
        }

        @media (min-width: 768px) {
            .services-wrapper {
                padding-left: 32px;
                padding-right: 32px;
            }
        }

        @media (min-width: 1024px) {
            .services-wrapper {
                padding-left: 48px;
                padding-right: 48px;
            }
        }

        .services-section .heading-wrapper {
            text-align: center;
            margin-bottom: 1rem;
        }

        .services-section .section-heading {
            font-family: 'DM Sans', 'figtree', sans-serif;
            font-size: 1.75rem;
            font-weight: 600;
            color: #1e2d45;
            margin: 0 0 0.5rem;
            padding-bottom: 0.25rem;
            border-bottom: 2px solid #10b981;
            display: block;
            width: fit-content;
            margin-left: auto;
            margin-right: auto;
        }

        .services-section .section-description {
            font-size: 1.0625rem;
            color: #64748b;
            margin-bottom: 3rem;
            max-width: 640px;
            margin-left: auto;
            margin-right: auto;
        }

        .services-grid {
            max-width: 1440px;
            margin-left: auto;
            margin-right: auto;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1.5rem;
        }

        @media (max-width: 1024px) {
            .services-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .services-grid {
                gap: 1rem;
            }
        }

        .service-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1.5rem;
        }

        .service-card .category-icon {
            width: 28px;
            height: 28px;
            margin-bottom: 1rem;
        }

        .service-card .category {
            font-family: 'DM Sans', 'figtree', sans-serif;
            font-size: 0.8125rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
            margin-bottom: 0.5rem;
            display: block;
        }

        .service-card h5 {
            font-family: 'DM Sans', 'figtree', sans-serif;
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e2d45;
            margin: 0 0 1rem;
        }

        .service-card .divider {
            width: 100%;
            height: 1px;
            background: #e2e8f0;
            margin: 1rem 0;
        }

        .service-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .service-card li {
            font-size: 0.875rem;
            color: #334155;
            padding: 0.25rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .service-card li:last-child {
            border-bottom: none;
        }

        /* ===== CLINIC LEADERSHIP ===== */
        .leadership-section {
            padding: 6rem 0;
            background: #f8fafc;
        }

        .leadership-wrapper {
            max-width: 560px;
            margin-left: auto;
            margin-right: auto;
            text-align: center;
            padding: 2rem;
        }

        .leadership-wrapper .icon {
            width: 32px;
            height: 32px;
            margin: 0 auto 1.5rem;
            opacity: 0.6;
        }

        .leadership-wrapper .heading {
            font-family: 'DM Sans', 'figtree', sans-serif;
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e2d45;
            margin: 0 0 0.5rem;
        }

        .leadership-wrapper .subheading {
            color: #64748b;
            font-size: 1rem;
            margin-bottom: 1.5rem;
        }

        .leadership-wrapper .name {
            font-family: 'DM Sans', 'figtree', sans-serif;
            font-size: 1.25rem;
            font-weight: 500;
            color: #1e2d45;
        }

        .leadership-wrapper .position {
            color: #64748b;
            font-size: 0.875rem;
        }

        /* ===== FOOTER ===== */
        .landing-footer {
            border-top: 1px solid #e2e8f0;
        }

        .footer-wrapper {
            max-width: 1440px;
            margin-left: auto;
            margin-right: auto;
            padding: 4rem 2rem 2rem;
        }

        @media (min-width: 768px) {
            .footer-wrapper {
                padding: 4rem 32px 2px;
            }
        }

        @media (min-width: 1024px) {
            .footer-wrapper {
                padding: 4rem 48px 2px;
            }
        }

        .footer-cols {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 3rem;
        }

        @media (max-width: 1024px) {
            .footer-cols {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }

        @media (max-width: 768px) {
            .footer-cols {
                gap: 1.5rem;
            }
        }

        .footer-col.brand {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .footer-logo {
            width: 40px;
            height: 40px;
            object-fit: contain;
            margin-bottom: 1rem;
        }

        .footer-desc {
            font-size: 0.875rem;
            color: #64748b;
            line-height: 1.6;
            margin: 0;
        }

        .footer-col.contact {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .footer-col.contact .footer-label {
            font-family: 'DM Sans', 'figtree', sans-serif;
            font-size: 0.8125rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
            margin: 0;
        }

        .footer-col.contact .footer-value {
            color: #334155;
            font-size: 0.875rem;
            text-decoration: none;
            font-family: 'DM Sans', 'figtree', sans-serif;
        }

        .footer-col.contact .footer-value:hover {
            color: #1e2d45;
        }

        .footer-col.contact .footer-tel, .footer-col.contact .footer-cp {
            display: inline-block;
            /* No link behavior, no hover styling changes */
        }

        .footer-col.location {
            font-size: 0.875rem;
            color: #64748b;
        }

        .footer-col.location p {
            margin: 0.25rem 0;
        }

        .footer-copyright {
            max-width: 1440px;
            margin: 2rem auto 0;
            text-align: center;
            font-size: 0.75rem;
            color: #98a2a6;
        }

        /* Reduce footer height - remove the extra padding that was creating unused space */
        .footer-wrapper > * {
            padding-top: 0;
        }

        /* ===== PATIENT POLICIES ===== */
        .patient-policies-section {
            padding: 6rem 0;
            background: #f1f5f9;
        }

        .patient-policies-wrapper {
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
            padding-left: 2rem;
            padding-right: 2rem;
        }

        @media (min-width: 768px) {
            .patient-policies-wrapper {
                padding-left: 32px;
                padding-right: 32px;
            }
        }

        @media (min-width: 1024px) {
            .patient-policies-wrapper {
                padding-left: 48px;
                padding-right: 48px;
            }
        }

        .patient-policies-heading {
            font-family: 'DM Sans', 'figtree', sans-serif;
            font-size: 1.75rem;
            font-weight: 600;
            color: #1e2d45;
            text-align: center;
            margin-bottom: 0.5rem;
            padding-bottom: 0.25rem;
            border-bottom: 2px solid #10b981;
            display: inline-block;
        }

        .patient-policies-description {
            font-size: 1.0625rem;
            color: #64748b;
            text-align: center;
            margin-bottom: 3rem;
            max-width: 640px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }

        .patient-policies-accordions {
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .patient-policy-accordion {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .patient-policy-summary {
            padding: 1rem 1.5rem;
            cursor: pointer;
            font-family: 'DM Sans', 'figtree', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            color: #1e2d45;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .patient-policy-summary svg {
            width: 16px;
            height: 16px;
            transition: transform 0.2s ease;
        }

        .patient-policy-accordion[open] .patient-policy-summary svg {
            transform: rotate(180deg);
        }

        .patient-policy-content {
            padding: 0 1.5rem 1rem;
        }

        .patient-policy-content p {
            font-size: 0.875rem;
            color: #334155;
            line-height: 1.6;
            margin: 0;
        }

        /* ===== SCROLL MARGINS FOR STICKY HEADER ===== */
        section {
            scroll-margin-top: 80px;
        }

        @media (max-width: 768px) {
            section {
                scroll-margin-top: 64px;
            }
        }

        /* ===== RESPONSIVE HEADER NAVIGATION ===== */
        @media (max-width: 1024px) {
            .header-nav-list {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
                width: 100%;
            }

            .header-nav-item {
                width: 100%;
            }

            .header-nav-link {
                display: block;
                padding: 0.5rem 1rem;
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .header-nav-list {
                border-top: 1px solid #e2e8f0;
            }

            .header-nav {
                flex-direction: column;
                padding-top: 1rem;
            }

            .header-nav-list {
                border: none;
                flex-direction: column;
            }

            .header-nav-item {
                width: 100%;
            }

            .header-nav-link {
                display: block;
                padding: 0.5rem 1rem;
                border-bottom: 1px solid #e2e8f0;
                width: 100%;
            }
        }
    </style>
</head>
<body class="landing">

<!-- ===== HEADER ===== -->
<header class="landing-header">
    <div class="landing-container">
        <div class="header-wrapper">
            <div class="header-left">
                <img src="{{ asset('images/logo.png') }}" alt="DEPLA Family Care Logo" class="landing-logo" />

                <span class="landing-title">DEPLA Family Care</span>
            </div>

            <nav class="header-nav">
                <ul class="header-nav-list">
                    <li class="header-nav-item">
                        <a href="#home" class="header-nav-link">Home</a>
                    </li>
                    <li class="header-nav-item">
                        <a href="#services" class="header-nav-link">Services</a>
                    </li>
                    <li class="header-nav-item">
                        <a href="#team" class="header-nav-link">Our Team</a>
                    </li>
                    <li class="header-nav-item">
                        <a href="#contact" class="header-nav-link">Contact & Location</a>
                    </li>
                    <li class="header-nav-item">
                        <a href="#policies" class="header-nav-link">Patient Info</a>
                    </li>
                </ul>
            </nav>

            <div class="header-right">
                <a href="{{ route('login') }}" class="landing-login-btn">Login</a>
            </div>
        </div>
    </div>
</header>

<!-- ===== HERO SECTION ===== -->
<section class="hero-section" id="home">
    <div class="landing-container">
        <div class="hero-wrapper">
            <div>
                <span class="hero-badge">Compassionate Maternity Care</span>

                <h1 class="hero-heading">
                    Caring for Mothers and <span class="highlight">Growing Families</span>
                </h1>

                <p class="hero-paragraph">
                    Providing compassionate and dependable maternity care for mothers and their babies throughout pregnancy, delivery, and postpartum recovery.
                </p>

                <a href="#services" class="hero-button smooth-scroll">Our Services</a>
            </div>

            <div class="hero-image">
                <img src="{{ asset('images/hero-maternity.png') }}" alt="Maternity care clinic exterior" loading="lazy" />
            </div>
        </div>
    </div>
</section>

<!-- ===== CONTACT & LOCATION ===== -->
<section class="contact-section" id="contact">
    <div class="landing-container">
        <div class="contact-wrapper">
            <div class="section-wrapper">
                <h2 class="section-heading">Contact & Location</h2>
                <p class="section-description">
                    Visit or contact DEPLA Family Care Maternity Clinic & Lying-In for compassionate and dependable maternal care.
                </p>
            </div>

            <div class="contact-grid">
                <!-- VISIT US CARD -->
                <div class="contact-card">
                    <div class="icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                    </div>
                    <h3>Visit Us</h3>
                    <p>901 Parada, Sta. Maria, Bulacan</p>
                </div>

                <!-- GET IN TOUCH CARD -->
                <div class="contact-card">
                    <div class="icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2 2 2 0 0 1 2 4.11 19.79 19.79 0 0 1 8.67 3 19.5 19.5 0 0 1 3 8.63 2 2 0 0 1 2 15.9a2 2 0 0 1-2 2 19.79 19.79 0 0 1-6 6 2 2 0 0 1-2-2 19.79 19.79 0 0 1 8.67-3.07 19.5 19.5 0 0 1 6-6c2 0 4.51 1.23 6 3.55a19.73 19.73 0 0 1-6 6 2 2 0 0 1-3.55-6z"/>
                            <line x1="21" y1="15" x2="21" y2="3"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                        </svg>
                    </div>
                    <h3>Get in Touch</h3>
                    <p class="tel-number">Tel. No. : (044) 8122-797</p>
                    <p class="cp-number">Cell. No. : 0922-9423-697</p>
                    <a href="mailto:flordelizadepla0802@gmail.com" class="email-link">Email: flordelizadepla0802@gmail.com</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== OUR SERVICES ===== -->
<section class="services-section" id="services">
    <div class="landing-container">
        <div class="services-wrapper">
            <div class="heading-wrapper text-center mb-8">
                <h2 class="section-heading">Our Services</h2>
                <p class="section-description">
                    Essential maternity, newborn, reproductive, and family-planning services for mothers and families in the community.
                </p>
            </div>

            <div class="services-grid">
                <!-- MATERNAL CARE CARD -->
                <div class="service-card">
                    <span class="category">Maternal Care</span>
                    <h5>Prenatal Check-Ups</h5>
                    <div class="divider"></div>
                    <ul>
                        <li>Prenatal Check-Ups</li>
                        <li>Follow-Up Check-Ups</li>
                        <li>Postnatal Check-Ups</li>
                        <li>Pregnancy Test</li>
                        <li>Internal Examination</li>
                    </ul>
                    <ul>
                        <li>Delivery Room Services</li>
                        <li>Ward Care</li>
                    </ul>
                </div>

                <!-- NEWBORN & DOCUMENTS CARD -->
                <div class="service-card">
                    <span class="category">Newborn & Documents</span>
                    <h5>Expanded Newborn Screening</h5>
                    <div class="divider"></div>
                    <ul>
                        <li>Expanded Newborn Screening</li>
                        <li>Hearing Test</li>
                        <li>Birth Certificate Processing</li>
                        <li>Ear Piercing</li>
                    </ul>
                </div>

                <!-- FAMILY PLANNING CARD -->
                <div class="service-card">
                    <span class="category">Family Planning</span>
                    <h5>Family Planning Consultation</h5>
                    <div class="divider"></div>
                    <ul>
                        <li>Family Planning Consultation</li>
                        <li>Contraceptives</li>
                        <li>Implant Insertion / Removal</li>
                        <li>IUD Insertion / Removal</li>
                        <li>Ligation Referral (MOA OB)</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== CLINIC LEADERSHIP ===== -->
<section class="leadership-section" id="team">
    <div class="landing-container">
        <div class="leadership-wrapper">
            <img src="{{ asset('images/logo.png') }}" alt="Clinic" class="icon" />
            <div class="heading">Clinic Leadership</div>
            <div class="subheading">Under the professional direction of</div>
            <div class="name">Flordeliza P. Depla, RM</div>
            <div class="position">HEAD OF FACILITY</div>
        </div>
    </div>
</section>

<!-- ===== PATIENT POLICIES ===== -->
<section class="patient-policies-section" id="policies">
    <div class="landing-container">
        <div class="patient-policies-wrapper">
            <h2 class="patient-policies-heading">Patient Policies</h2>
            <p class="patient-policies-description">
                Important information about how we protect patient information and respond to concerns.
            </p>

            <div class="patient-policies-accordions">
                <!-- Privacy Policy Accordion -->
                <details class="patient-policy-accordion">
                    <summary class="patient-policy-summary">
                        Privacy Policy
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </summary>
                    <div class="patient-policy-content">
                        <p>
                            Patient health information and personal details collected by DEPLA Family Care Maternity Clinic & Lying-In are treated as confidential. Information is used only for providing care, maintaining medical records, and authorized clinic operations. It will not be shared with third parties without the patient's consent unless disclosure is required for treatment, patient safety, or by law.
                        </p>
                    </div>
                </details>

                <!-- Complaints Resolution Accordion -->
                <details class="patient-policy-accordion">
                    <summary class="patient-policy-summary">
                        Complaints Resolution
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </summary>
                    <div class="patient-policy-content">
                        <p>
                            We value patient feedback and use it to improve our services. Concerns or complaints may be raised directly with the clinic or the Head of Facility through the contact details provided on this page. Each concern will be reviewed respectfully, confidentially, and fairly, and the clinic will communicate the appropriate response or next steps.
                        </p>
                    </div>
                </details>
            </div>
        </div>
    </div>
</section>

<!-- ===== FOOTER ===== -->
<footer class="landing-footer">
    <div class="landing-container">
        <div class="footer-wrapper">
            <div class="footer-cols">
                <!-- Column 1 — Brand -->
                <div class="footer-col brand">
                    <img src="{{ asset('images/logo.png') }}" alt="DEPLA Family Care Logo" class="footer-logo" />

                    <div class="footer-desc">
                        Maternity Clinic & Lying-In. Providing compassionate and dependable care for mothers and families.
                    </div>
                </div>

                <!-- Column 2 — Contact -->
                <div class="footer-col contact">
                    <div class="footer-label">Tel.</div>
                    <a href="#" class="footer-value">(044) 8122-797</a>

                    <div class="footer-label">CP</div>
                    <a href="#" class="footer-value">0922-9423-697</a>

                    <div class="footer-label">Email</div>
                    <a href="mailto:flordelizadepla0802@gmail.com" class="footer-value subtle">flordelizadepla0802@gmail.com</a>
                </div>

                <!-- Column 3 — Location -->
                <div class="footer-col location">
                    <p>901 Parada, Sta. Maria, Bulacan</p>
                </div>
            </div>
        </div>

        <div class="footer-copyright">
            © {{ date('Y') }} DEPLA Family Care Maternity Clinic & Lying-In. All rights reserved.
        </div>
    </div>
</footer>

<script>
    // Respect prefers-reduced-motion for smooth scroll
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        document.documentElement.style.scrollBehavior = 'auto';
    } else {
        // Initialize smooth scroll for all internal same-page anchors
        initSmoothScroll();
    }

    function initSmoothScroll() {
        // Select all internal anchor links (href starting with #) except empty href="#"
        const anchors = document.querySelectorAll('a[href^="#"]:not([href="#"])');

        const header = document.querySelector('.landing-header');

        anchors.forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');

                // Skip if href is just "#" (no target)
                if (href === '#') {
                    return;
                }

                // Calculate target element
                const targetId = href.substring(1); // remove leading #
                const targetElement = document.getElementById(targetId);

                if (!targetElement) {
                    return;
                }

                // Prevent the browser's immediate default jump
                e.preventDefault();

                // Calculate header height dynamically
                const headerHeight = header ? header.offsetHeight : 80;
                const extraSpacing = 16; // breathing space

                // Calculate destination position:
                // target element's top position + current scrollY - header height - extra spacing
                const targetPosition = targetElement.getBoundingClientRect().top + window.scrollY - headerHeight - extraSpacing;

                // Scroll smoothly to the calculated position
                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            });
        });
    }
</script>

</body>
</html>