{{-- DEPLA Phase 3B.3 — Request an Appointment (patient portal).
     POSTs only appointment_type, requested_date and requested_time. The
     patient identity, source = online and status = pending are assigned
     SERVER-SIDE (PatientAppointmentController + AppointmentManagementService)
     — no patient_id, status, reviewer or confirmed-schedule field exists
     in this form. Client script is convenience only (type-conditional
     time hints + double-submit guard); the server is authoritative for
     every rule. Alpine is provided by the shared layout (resources/js/app.js). --}}
@extends('patient.layout')

@php
    $typeLabels = [
        \App\Models\Appointment::TYPE_CHECKUP => 'Checkup',
        \App\Models\Appointment::TYPE_COUNSELING => 'Counseling',
        \App\Models\Appointment::TYPE_DELIVERY_REQUEST => 'Delivery Request',
    ];
@endphp

@section('content')
    {{-- Alpine hides x-cloak elements only after it boots; the compiled
         stylesheet ships no [x-cloak] rule (the clinic calendar lives
         with the flash), so this page carries its own one-line rule to
         keep the delivery notice and required-time asterisk hidden on
         first paint for the default Checkup type. --}}
    <style>[x-cloak] { display: none !important; }</style>

    <div class="mb-6">
        <a href="{{ route('patient.appointments.index') }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold transition-colors hover:underline"
           style="color: var(--depla-dark-green);">
            &larr; Back to My Appointments
        </a>
    </div>

    <header class="mb-6">
        <h1 class="portal-heading text-2xl sm:text-3xl" style="color: var(--depla-navy);">
            Request an Appointment
        </h1>
        <p class="mt-2 text-sm leading-6" style="color: var(--body-text);">
            Choose an appointment type and your preferred schedule. The clinic will review your request and confirm a schedule.
        </p>
    </header>

    {{-- Clinic information — always visible, not conditional on type. --}}
    <div class="mb-6 rounded-2xl border px-5 py-4 text-sm leading-6"
         style="border-color: #BBF7D0; background: #F0FDF4; color: #166534;"
         role="note">
        <p class="font-semibold">Submitting an appointment request does not guarantee confirmation.</p>
        <p class="mt-2">
            Checkup and counseling appointments are available Monday&ndash;Sunday, 8:00 AM to before 5:00 PM (Philippine time, provisional).
            Delivery requests are accepted any day, at any time, and may be date-only.
        </p>
    </div>

    {{-- General error (e.g. duplicate active request caught at the write boundary). --}}
    @if ($errors->has('appointment'))
        <div class="mb-6 rounded-xl border px-4 py-3 text-sm font-medium"
             style="border-color: #FECACA; background: #FEF2F2; color: #991B1B;"
             role="alert">
            {{ $errors->first('appointment') }}
        </div>
    @endif

    <section class="overflow-hidden rounded-2xl border bg-white"
             style="border-color: var(--light-border);"
             aria-label="Appointment request form">
        <div class="border-b px-6 py-4" style="border-color: var(--light-border); background: #F6F4EE;">
            <h2 class="text-sm font-bold uppercase tracking-wider" style="color: var(--depla-navy);">
                Appointment Details
            </h2>
        </div>

        <form method="POST"
              action="{{ route('patient.appointments.store') }}"
              x-data="{
                  submitting: false,
                  type: '{{ old('appointment_type', \App\Models\Appointment::TYPE_CHECKUP) }}',
                  get requiresTime() {
                      return this.type !== '{{ \App\Models\Appointment::TYPE_DELIVERY_REQUEST }}';
                  }
              }"
              @submit="submitting = true"
              class="space-y-6 px-6 py-6">
            @csrf

            {{-- ═══ Appointment Type ═══ --}}
            <fieldset>
                <legend class="mb-2 block text-sm font-semibold" style="color: var(--depla-navy);">
                    Appointment Type <span class="text-red-600" aria-hidden="true">*</span>
                </legend>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3" role="radiogroup" aria-label="Appointment Type">
                    @foreach ($typeLabels as $value => $label)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 text-sm font-medium transition"
                               :style="type === '{{ $value }}'
                                   ? 'border-color: var(--depla-green); background: #F0FDF4;'
                                   : 'border-color: var(--light-border);'"
                               style="border-color: var(--light-border); color: var(--depla-navy);">
                            <input type="radio"
                                   name="appointment_type"
                                   value="{{ $value }}"
                                   x-model="type"
                                   {{ old('appointment_type', \App\Models\Appointment::TYPE_CHECKUP) === $value ? 'checked' : '' }}
                                   class="h-4 w-4 border-gray-300 text-[#367E4B] focus:ring-[#55B85A]"
                                   aria-describedby="type-hint">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>

                @error('appointment_type')
                    <p class="mt-2 text-sm text-red-600" role="alert">{{ $message }}</p>
                @enderror

                <p id="type-hint" class="mt-2 text-xs" style="color: var(--body-text);">
                    Checkup and counseling need a preferred time inside clinic hours. Delivery requests are 24/7 and may be date-only.
                </p>
            </fieldset>

            {{-- ═══ Delivery request notice — shown only for Delivery Request. --}}
            <div x-show="type === '{{ \App\Models\Appointment::TYPE_DELIVERY_REQUEST }}'"
                 x-cloak
                 class="rounded-xl border px-4 py-3 text-sm leading-6"
                 style="border-color: #FDE68A; background: #FFFBEB; color: #92400E;">
                <p class="font-semibold">Delivery requests do not reserve a bed.</p>
                <p class="mt-1">
                    A delivery request does not create an admission or guarantee bed availability.
                    For urgent concerns or active labor, contact or visit the clinic directly rather than waiting for online confirmation.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                {{-- ═══ Preferred Date ═══ --}}
                <div>
                    <label for="requested_date" class="mb-2 block text-sm font-semibold" style="color: var(--depla-navy);">
                        Preferred Date <span class="text-red-600" aria-hidden="true">*</span>
                    </label>
                    <input type="date"
                           id="requested_date"
                           name="requested_date"
                           value="{{ old('requested_date') }}"
                           min="{{ $clinicToday }}"
                           required
                           class="h-11 w-full rounded-xl border px-3 text-sm focus:outline-none focus:ring-2"
                           style="border-color: #D1D5DB; color: var(--depla-navy);"
                           aria-describedby="date-hint @error('requested_date') date-error @enderror">
                    <p id="date-hint" class="mt-1.5 text-xs" style="color: var(--body-text);">
                        Today or a later date (Philippine time). Today is {{ \Carbon\Carbon::parse($clinicToday)->format('F j, Y') }}.
                    </p>
                    @error('requested_date')
                        <p id="date-error" class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                {{-- ═══ Preferred Time ═══ --}}
                <div>
                    <label for="requested_time" class="mb-2 block text-sm font-semibold" style="color: var(--depla-navy);">
                        Preferred Time
                        <span class="text-red-600" aria-hidden="true" x-show="requiresTime" x-cloak>*</span>
                        <span class="text-xs font-normal" style="color: var(--body-text);" x-show="!requiresTime">(optional for delivery requests)</span>
                    </label>
                    <input type="time"
                           id="requested_time"
                           name="requested_time"
                           value="{{ old('requested_time') }}"
                           :required="requiresTime"
                           :min="requiresTime ? '08:00' : null"
                           :max="requiresTime ? '16:59' : null"
                           class="h-11 w-full rounded-xl border px-3 text-sm focus:outline-none focus:ring-2"
                           style="border-color: #D1D5DB; color: var(--depla-navy);"
                           aria-describedby="time-hint @error('requested_time') time-error @enderror">
                    <p id="time-hint" class="mt-1.5 text-xs" style="color: var(--body-text);">
                        <span x-show="requiresTime" x-cloak>Between 8:00 AM and before 5:00 PM (Philippine clinic hours, provisional). Leave empty only for a date-only delivery request.</span>
                        <span x-show="!requiresTime">Optional. Leave empty for a date-only delivery request.</span>
                    </p>
                    @error('requested_time')
                        <p id="time-error" class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 border-t pt-5" style="border-color: var(--light-border);">
                <button type="submit"
                        :disabled="submitting"
                        class="inline-flex items-center gap-2 rounded-xl px-6 py-2.5 text-sm font-semibold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
                        style="background: var(--depla-dark-green); outline-color: var(--depla-green);">
                    <svg x-show="!submitting" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                    </svg>
                    <span x-text="submitting ? 'Submitting…' : 'Submit Appointment Request'">Submit Appointment Request</span>
                </button>

                <a href="{{ route('patient.appointments.index') }}"
                   class="rounded-xl border px-5 py-2.5 text-sm font-semibold transition hover:bg-gray-50"
                   style="border-color: #D1D5DB; color: var(--depla-navy);">
                    Cancel
                </a>
            </div>
        </form>
    </section>

@endsection
