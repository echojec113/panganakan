{{-- Temporary Patient Portal holding page (Phase 1 security hardening).
     Renders on the shared guest layout only: no clinic navigation, no
     dashboards, no medical records, no staff/admin menus. --}}
<x-guest-layout>
    <div class="space-y-5 text-center">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full"
             style="background: linear-gradient(135deg, #367E4B, #58D68D);">
            <svg class="h-7 w-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 8v4l2.5 2.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>

        <div>
            <h1 class="text-xl font-bold text-[#19355F]" style="font-family:'DM Serif Display',serif;font-weight:400;">
                Patient Portal &mdash; Coming Soon
            </h1>
            <p class="mt-3 text-sm leading-6 text-[#657083]">
                Your account is recognized, but the patient portal is not available yet.
                Please contact the clinic for assistance.
            </p>
        </div>

        <form method="POST" action="{{ route('logout') }}" class="pt-1">
            @csrf
            <button type="submit"
                    class="w-full px-6 py-3 font-semibold rounded-xl text-white text-sm tracking-wide transition"
                    style="background: #55B85A;">
                Sign Out
            </button>
        </form>
    </div>
</x-guest-layout>
