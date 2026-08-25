<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }} - Login</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            /* === Floating orbs background === */
            .orb {
                position: absolute;
                border-radius: 50%;
                filter: blur(60px);
                opacity: 0.45;
                animation: drift linear infinite;
                pointer-events: none;
            }
            @keyframes drift {
                0%   { transform: translate(0, 0) scale(1); }
                33%  { transform: translate(18px, -22px) scale(1.05); }
                66%  { transform: translate(-12px, 14px) scale(0.97); }
                100% { transform: translate(0, 0) scale(1); }
            }

            /* === Landing-page color palette === */
            :root {
                --depla-navy: #19355F;
                --decla-clinic-green: #55B85A;
                --depla-dark-green: #367E4B;
                --warm-white: #FCFBF8;
                --light-cream: #F6F4EE;
                --pale-sage: #EDF5EC;
                --body-text: #657083;
                --light-border: #E7E9E5;
            }

            /* === Subtle grid texture === */
            .grid-texture {
                background-image:
                    linear-gradient(rgba(246,244,238,0.15) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(237,245,236,0.15) 1px, transparent 1px);
                background-size: 40px 40px;
            }

            /* === Heartbeat pulse on logo === */
            @keyframes pulse-soft {
                0%, 100% { transform: scale(1); }
                50%       { transform: scale(1.04); }
            }
            .logo-pulse { animation: pulse-soft 3.5s ease-in-out infinite; }

            /* === Stagger-reveal on load === */
            .reveal {
                opacity: 0;
                transform: translateY(16px);
                animation: slideUp 0.55s cubic-bezier(.22,.68,0,1.2) forwards;
            }
            @keyframes slideUp {
                to { opacity: 1; transform: translateY(0); }
            }

            /* === Shimmer on the divider line === */
            @keyframes shimmer {
                0%   { background-position: -200% center; }
                100% { background-position: 200% center; }
            }
            .shimmer-line {
                height: 2px;
                border-radius: 999px;
                background: linear-gradient(90deg,
                    #55B85A 0%,
                    #8BAC7F 40%,
                    #79A777 50%,
                    #8BAC7F 60%,
                    #55B85A 100%);
                background-size: 200% auto;
                animation: shimmer 3s linear infinite;
            }

            /* === Badge pill === */
            .badge-pill {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 4px 12px;
                border-radius: 999px;
                font-size: 11px;
                font-weight: 600;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                background: rgba(246,244,238,0.7);
                color: #19355F;
                border: 1px solid rgba(237,245,236,0.5);
                backdrop-filter: blur(4px);
            }

            /* === Input focus glow === */
            .fancy-input:focus {
                box-shadow: 0 0 0 3px rgba(54,126,75,0.18), 0 1px 3px rgba(0,0,0,0.06);
                border-color: #367E4B !important;
                background: white !important;
                outline: none;
            }

            /* === Heartbeat dot === */
            @keyframes beat {
                0%, 100% { transform: scale(1); opacity: 1; }
                50%       { transform: scale(1.7); opacity: 0.4; }
            }
            .beat-dot {
                width: 7px; height: 7px;
                border-radius: 50%;
                background: #367E4B;
                display: inline-block;
                animation: beat 1.8s ease-in-out infinite;
            }

            /* === Card hover lift === */
            .login-card {
                transition: box-shadow 0.3s ease, transform 0.3s ease;
            }
            .login-card:hover {
                box-shadow: 0 20px 50px rgba(54,126,75,0.12), 0 4px 16px rgba(0,0,0,0.07);
                transform: translateY(-2px);
            }

            /* === Button glow on hover === */
            .btn-glow {
                position: relative;
                overflow: hidden;
                transition: all 0.3s ease;
            }
            .btn-glow::before {
                content: '';
                position: absolute;
                inset: 0;
                background: linear-gradient(135deg, rgba(246,244,238,0.15) 0%, transparent 60%);
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            .btn-glow:hover::before { opacity: 1; }
            .btn-glow:hover {
                box-shadow: 0 6px 24px rgba(54,126,75,0.4);
                transform: translateY(-1px) scale(1.01);
            }
            .btn-glow:active {
                transform: scale(0.98);
                box-shadow: none;
            }

            /* === Floating label feature text === */
            .feature-item {
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 13.5px;
                color: #657083;
                padding: 8px 0;
            }
            .feature-icon {
                width: 28px; height: 28px;
                border-radius: 8px;
                background: rgba(255,255,255,0.6);
                border: 1px solid rgba(147,197,253,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            /* === Password toggle button === */
            .password-toggle {
                position: absolute;
                right: 12px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                cursor: pointer;
                padding: 4px 6px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #657080;
                transition: color 0.2s ease;
                z-index: 10;
            }
            .password-toggle:hover {
                color: #367E4B;
            }


            /* ================================
               RESPONSIVE FIX (LOGIN PAGE)
============================== */

/* Tablets */
@media (max-width: 1024px) {

    /* Balance layout better */
    .lg\:w-\[70\%\] {
        width: 55% !important;
    }

    .lg\:w-\[30\%\] {
        width: 45% !important;
    }

    /* Reduce padding */
    .lg\:p-16 {
        padding: 2rem !important;
    }

    .p-12 {
        padding: 2rem !important;
    }
}

/* Mobile */
@media (max-width: 768px) {

    /* Stack layout clean */
    .lg\:flex-row {
        flex-direction: column !important;
    }

    /* Full width */
    .lg\:w-\[70\%\],
    .lg\:w-\[30\%\] {
        width: 100% !important;
    }

    /* Remove big spacing */
    .p-12,
    .lg\:p-16 {
        padding: 1.5rem !important;
    }

    /* Center everything properly */
    .login-card {
        margin: 0 auto;
    }

    /* Fix logo size */
    .logo-pulse img {
        height: 140px !important;
    }

    /* Inputs spacing */
    .px-8 {
        padding-left: 1.5rem !important;
        padding-right: 1.5rem !important;
    }

    .py-8 {
        padding-top: 1.5rem !important;
        padding-bottom: 1.5rem !important;
    }
}

/* Small phones */
@media (max-width: 480px) {

    /* Smaller logo */
    .logo-pulse img {
        height: 110px !important;
    }

    /* Reduce card padding */
    .px-8 {
        padding-left: 1rem !important;
        padding-right: 1rem !important;
    }

    .py-8 {
        padding-top: 1rem !important;
        padding-bottom: 1rem !important;
    }

    /* Button full comfort */
    button[type="submit"] {
        font-size: 14px;
        padding: 12px;
    }

    /* Fix text overflow */
    h1 {
        font-size: 1.8rem !important;
    }
}
        </style>
    </head>
    <body class="font-sans text-gray-900 antialiased bg-white" style="font-family: 'DM Sans', sans-serif;">

    <div class="min-h-screen flex flex-col lg:flex-row overflow-hidden">

        <!-- ═══════════════════════════════════════════════════
             LEFT PANEL — Branding (70%)
         ════════════════════════════════════════════════════ -->
         <div class="hidden lg:flex lg:w-[70%] flex-col items-center justify-center p-12 lg:p-16 relative overflow-hidden"
              style="background-image: url('{{ asset('images/hero-maternity.png') }}'); background-size: cover; background-position: center; background-color: rgba(246,244,238,0.5);">

<!-- Left-to-right fade overlay -->
              <div class="absolute inset-0 pointer-events-none"
                   style="background: linear-gradient(90deg, rgba(246,244,238,0.8) 0%, rgba(246,244,238,0.3) 50%, rgba(246,244,238,0.1) 100%);">

              </div>

              <!-- DEPLA logo -->
              <a
                  href="{{ url('/') }}"
                  aria-label="DEPLA Family Care — Return to home"
                  style="
                      position: absolute;
                      top: 24px;
                      left: 24px;
                      z-index: 9999;
                      display: block;
                      width: 90px;
                      opacity: 1;
                      visibility: visible;
                  "
              >
                  <img
                      src="{{ asset('images/logo.png') }}"
                      alt="DEPLA Family Care"
                      style="
                          display: block;
                          width: 100%;
                          height: auto;
                          opacity: 1;
                          visibility: visible;
                      "
                  >
              </a>

              <!-- Branding content -->
              <div class="w-full max-w-sm space-y-10 text-left relative z-10">
 
                 <!-- Logo -->
                 <div class="reveal flex items-start" style="animation-delay:0.15s; position: relative">
                     <a href="{{ url('/') }}" class="block"
                        title="DEPLA Family Care — Return to home."
                        aria-label="DEPLA Family Care — Return to home."
                        rel="noopener noreferrer">
                         <div class="logo-pulse">
<img src="{{ asset('images/logo.png') }}" alt="DEPLA Family Care Logo"
                                 class="h-24 w-auto object-contain drop-shadow-lg absolute top-32 left-32 z-50">
                         </div>
                     </a>
                 </div>

                 <!-- Clinic name -->
                 <div class="reveal space-y-3 text-center" style="animation-delay:0.25s">
                     <h1 style="font-family:'DM Serif Display',serif;font-size:3rem;color:#19355F;line-height:1.15;font-weight:400;">
                         Depla Family Care
                     </h1>
                     <h2 class="text-base font-semibold tracking-widest text-[#55B85A] uppercase" style="letter-spacing:0.2em;">
                         Maternity & Lying-In Clinic
                     </h2>
                     <div class="flex justify-center pt-1">
                         <div class="shimmer-line w-16"></div>
                     </div>
                     <p class="text-sm text-gray-600 italic pt-1" style="font-family:'DM Serif Display',serif;font-style:italic;">
                         "Caring for Mothers and New Life"
                     </p>
                     <p class="text-xs text-gray-500 pt-1">
                         📍 Santa Maria, Bulacan
                     </p>
                 </div>

                 <!-- Clinic Details -->
                 <div class="reveal space-y-1 text-left" style="animation-delay:0.4s">
                     <div class="feature-item">
                         <div class="feature-icon">
                             <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#55B85A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                         </div>
                         Compassionate healthcare for mothers
                     </div>
                     <div class="feature-item">
                         <div class="feature-icon">
                             <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#55B85A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2L2 7l10 5 10-5-10-5z"></path><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                         </div>
                         Advanced maternity monitoring system
                     </div>
                     <div class="feature-item">
                         <div class="feature-icon">
                             <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#55B85A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                         </div>
                         Privacy & data protection assured
                     </div>
                 </div>
 
                 <!-- Footer -->
                 <div class="pt-8 text-left text-xs text-[#657083]/70">
                     © {{ date('Y') }} Depla Family Care. All rights reserved.
                 </div>
             </div>
         </div>

         <!-- ═══════════════════════════════════════════════════
              RIGHT PANEL — Login Form (30%)
          ════════════════════════════════════════════════════ -->
         <div class="w-full lg:w-[30%] flex flex-col items-center justify-center min-h-screen px-4 sm:px-6 py-12 lg:py-0 bg-white relative overflow-hidden">

             <!-- Subtle background texture -->
             <div class="absolute inset-0 grid-texture opacity-50 pointer-events-none"></div>

             <!-- Logo Silhouette Background (Desktop) -->
             <div class="hidden lg:block absolute inset-0 pointer-events-none opacity-4">
                 <img src="{{ asset('images/logo.png') }}" alt__ class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 h-96 w-auto object-contain"
                      style="filter: grayscale(100%) brightness(80%); opacity: 0.12;">
             </div>

             <!-- Soft corner glow -->
             <div class="absolute top-0 right-0 w-72 h-72 rounded-full pointer-events-none"
                  style="background:radial-gradient(circle, rgba(219,234,254,0.6) 0%, transparent 70%); transform:translate(30%,-30%);"></div>
             <div class="absolute bottom-0 left-0 w-56 h-56 rounded-full pointer-events-none"
                  style="background:radial-gradient(circle, rgba(224,242,254,0.5) 0%, transparent 70%); transform:translate(-30%,30%);"></div>

             <div class="w-full max-w-md relative z-10">

                 <!-- Mobile Header -->
                 <div class="lg:hidden text-center mb-8 reveal" style="animation-delay:0.1s">
                     <img src="{{ asset('images/logo.png') }}" alt="Depla Family Care Logo" class="h-48 w-auto mx-auto mb-4 object-contain logo-pulse">
                     <h1 class="text-3xl font-bold text-gray-900 mb-1" style="font-family:'DM Serif Display',serif;font-weight:400;">Depla Family Care</h1>
                     <p class="text-sm text-gray-500 tracking-widest uppercase" style="letter-spacing:0.15em;">Maternity & Lying-In Clinic</p>
                 </div>

                 <!-- Back to Home -->
                 <div class="mb-6 text-center">
                     <a href="{{ url('/') }}"
                        class="text-[#19355F] text-sm font-medium hover:text-[#367E4B] transition-colors duration-150 block">
                         ← Back to Home
                     </a>
                 </div>

                 <!-- Login Card -->
                 <div class="login-card w-full bg-white rounded-2xl overflow-hidden reveal relative"
                      style="animation-delay:0.25s; box-shadow: 0 8px 32px rgba(54,126,75,0.08), 0 2px 8px rgba(0,0,0,0.05); border: 1px solid rgba(231,233,229,0.8);">
                     <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-[#55B85A] via-[#79A777] to-[#6B9D5A]"></div>

                     <!-- Card Header -->
                     <div class="px-8 pt-8 pb-6" style="border-bottom: 1px solid rgba(219,234,254,0.7);">
                         <div class="flex items-center gap-3 mb-1">
                             <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#367E4B,#58D68D);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(54,126,75,0.3);">
                                 <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                             </div>
                             <div>
                                 <h2 class="text-xl font-bold text-[#19355F]" style="font-family:'DM Serif Display',serif;font-weight:400;font-size:1.4rem;">Welcome Back</h2>
                                 <p class="text-xs text-[#657083]">Sign in to your healthcare portal</p>
                             </div>
                         </div>
                     </div>

                     <!-- Card Body -->
                     <div class="px-8 py-8">
                         <x-auth-session-status class="mb-6" :status="session('status')" />

                         <form method="POST" action="{{ route('login') }}" class="space-y-5">
                             @csrf

                             <!-- Email -->
                             <div>
                                 <label for="email" class="block text-xs font-semibold text-[#657083] mb-1.5 uppercase tracking-wider" style="letter-spacing:0.08em;">Email Address</label>
                                 <div class="relative">
                                     <svg class="absolute left-3.5 top-3.5 w-4 h-4 text-[#55B85A]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                     </svg>
                                     <x-text-input
                                         id="email"
                                         class="fancy-input block w-full pl-10 pr-4 py-3 rounded-xl border text-sm transition-all duration-200"
                                         style="border-color:rgba(231,233,229,0.9);background:#F6F4EE;color:#1e293b;"
                                         type="email"
                                         name="email"
                                         :value="old('email')"
                                         required
                                         autofocus
                                         autocomplete="username"
                                         placeholder="your@email.com"
                                     />
                                 </div>
                                 <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-xs text-red-500" />
                             </div>

                             <!-- Password -->
                             <div>
                                 <label for="password" class="block text-xs font-semibold text-[#657083] mb-1.5 uppercase tracking-wider" style="letter-spacing:0.08em;">Password</label>
                                 <div class="relative">
                                     <svg class="absolute left-3.5 top-3.5 w-4 h-4 text-[#55B85A]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                     </svg>
                                     <x-text-input
                                         id="password"
                                         class="fancy-input block w-full pl-10 pr-10 py-3 rounded-xl border text-sm transition-all duration-200"
                                         style="border-color:rgba(231,233,229,0.9);background:#F6F4EE;color:#1e293b;"
                                         type="password"
                                         name="password"
                                         required
                                         autocomplete="current-password"
                                         placeholder="Password"
                                     />
                                     <button type="button" class="password-toggle" onclick="togglePasswordVisibility()">
                                         <svg id="password-eye" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                     </button>
                                 </div>
                                 <x-input-error :messages="$errors->get('password')" class="mt-1.5 text-xs text-red-500" />
                             </div>

                             <!-- Remember Me & Forgot Password -->
                             <div class="flex items-center justify-between pt-1">
                                 <label for="remember_me" class="inline-flex items-center gap-2 cursor-pointer group">
                                     <input
                                         id="remember_me"
                                         type="checkbox"
                                         class="w-4 h-4 rounded border-[#E7E9E5] text-[#55B85A] focus:ring-[#55B85A] cursor-pointer"
                                         name="remember"
                                     >
                                     <span class="text-xs text-gray-500 group-hover:text-gray-800 transition-colors duration-200">Remember me</span>
                                 </label>

                                 @if (Route::has('password.request'))
                                     <a href="{{ route('password.request') }}"
                                        class="text-xs font-medium text-[#55B85A] hover:text-[#367E4B] transition-colors duration-200 hover:underline">
                                         Forgot Password?
                                     </a>
                                 @endif
                             </div>

                             <!-- Sign In Button -->
                             <div class="pt-1">
                                 <button type="submit"
                                     class="btn-glow w-full px-6 py-3.5 font-semibold rounded-xl text-white text-sm tracking-wide"
                                     style="background: #55B85A; box-shadow: none;">
                                     Sign In &nbsp;→
                                 </button>
                             </div>
                         </form>
                     </div>
                 </div>

                 <!-- Help Text -->
                 <div class="mt-6 text-center reveal" style="animation-delay:0.4s">
                     <p class="text-xs text-gray-400">
                         Need assistance?
                         <a href="mailto:support@depla.clinic" class="text-[#55B85A] hover:text-[#367E4B] font-medium transition-colors duration-200 ml-1">
                             Contact Support
                         </a>
                     </p>
                 </div>
             </div>
         </div>

     </div>

     <script>
         function togglePasswordVisibility() {
             const passwordField = document.getElementById('password');
             const eyeIcon = document.getElementById('password-eye');
             
             if (passwordField.type === 'password') {
                 passwordField.type = 'text';
                 eyeIcon.innerHTML = '<g><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></g>';
             } else {
                 passwordField.type = 'password';
                 eyeIcon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
             }
         }
     </script>
 </body>
</html>