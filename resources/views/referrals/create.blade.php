<x-app-layout>

<div style="margin-left: var(--sidebar-width); background: var(--bg-base); min-height: 100vh; padding: 28px 30px;">

    {{-- Header --}}
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 24px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px;">Create Referral</h1>
        <p style="font-size: 14px; color: var(--text-muted);">Refer patient to hospital or OB-GYN specialist</p>
    </div>

    {{-- Error Messages --}}
    @if ($errors->any())
        <div style="margin-bottom: 20px; padding: 14px 16px; background: #fee2e2; border: 1px solid #fecaca; border-radius: 11px; color: #991b1b;">
            <p style="font-weight: 600; margin-bottom: 8px; font-size: 14px;">Validation Errors:</p>
            <ul style="list-style: disc; margin-left: 20px; font-size: 13px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Form Card --}}
    <div style="background: white; border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 1px 3px rgba(30,70,140,0.06); padding: 24px; max-width: 600px;">

        <form action="{{ route('referrals.store') }}" method="POST">
            @csrf

            <input type="hidden" name="patient_id" value="{{ $patient->id }}">

            {{-- Patient Info Display --}}
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 8px;">Patient Name</label>
                <div style="padding: 12px 14px; background: var(--bg-base); border: 1px solid var(--border); border-radius: 9px; color: var(--text-primary); font-size: 14px;">
                    {{ $patient->first_name }} {{ $patient->middle_name ? $patient->middle_name . ' ' : '' }}{{ $patient->last_name }}
                </div>
            </div>

            {{-- Referred To --}}
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 8px;">
                    Referred To <span style="color: #ef4444;">*</span>
                </label>
                <input type="text" name="referred_to" placeholder="e.g., Provincial Hospital, OB-GYN Clinic"
                    value="{{ old('referred_to') }}"
                    style="width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 9px; font-size: 13px; font-family: inherit; background: white;">
            </div>

            {{-- Doctor Name --}}
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 8px;">Doctor Name (Optional)</label>
                <input type="text" name="doctor_name" placeholder="Name of referring doctor"
                    value="{{ old('doctor_name') }}"
                    style="width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 9px; font-size: 13px; font-family: inherit; background: white;">
            </div>

            {{-- Reason --}}
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 8px;">
                    Reason for Referral <span style="color: #ef4444;">*</span>
                </label>
                <textarea name="reason" rows="4" placeholder="Example: Severe hypertension, breech presentation, high-risk pregnancy..."
                    style="width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 9px; font-size: 13px; font-family: inherit; background: white; resize: vertical;">{{ old('reason') }}</textarea>
            </div>

            {{-- Additional Notes --}}
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 8px;">Additional Notes (Optional)</label>
                <textarea name="notes" rows="3" placeholder="Any additional remarks..."
                    style="width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 9px; font-size: 13px; font-family: inherit; background: white; resize: vertical;">{{ old('notes') }}</textarea>
            </div>

            {{-- Date Referred --}}
            <div style="margin-bottom: 28px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 8px;">
                    Date Referred <span style="color: #ef4444;">*</span>
                </label>
                <input type="date" name="date_referred" value="{{ old('date_referred', \Carbon\Carbon::today()->toDateString()) }}"
                    style="width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 9px; font-size: 13px; font-family: inherit; background: white;">
            </div>

            {{-- Buttons --}}
            <div style="display: flex; justify-content: space-between; gap: 12px;">
                <a href="{{ route('patients.show', $patient->id) }}"
                    style="flex: 1; padding: 12px 16px; border: 1px solid var(--border); border-radius: 9px; text-align: center; background: white; color: var(--text-primary); text-decoration: none; font-weight: 500; font-size: 14px; transition: background 0.15s; cursor: pointer;">
                    Back
                </a>

                <button type="submit"
                    style="flex: 1; padding: 12px 16px; border: none; border-radius: 9px; background: var(--blue-accent); color: white; font-weight: 600; font-size: 14px; cursor: pointer; transition: background 0.15s;">
                    Save Referral
                </button>
            </div>

        </form>

    </div>

</div>

</x-app-layout>
