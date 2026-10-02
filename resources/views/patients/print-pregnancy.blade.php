<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Pregnancy Record - {{ $patient->first_name }} {{ $patient->last_name }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 32px; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        h2 { font-size: 16px; margin-top: 28px; border-bottom: 1px solid #e5e7eb; padding-bottom: 8px; }
        h3 { font-size: 14px; margin: 16px 0 8px; }
        .muted { color: #6b7280; font-size: 13px; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 12px; }
        .box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; }
        .label { color: #2563eb; font-size: 12px; font-weight: bold; }
        .value { margin-top: 4px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 13px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f9fafb; }
        .no-print { float: right; padding: 8px 14px; }
        @media print { .no-print { display: none; } body { margin: 18px; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print</button>
    <h1>Pregnancy Record</h1>
    <div class="muted">{{ $patient->first_name }} {{ $patient->middle_name ? $patient->middle_name . ' ' : '' }}{{ $patient->last_name }} &middot; Pregnancy record ID: {{ $patient->id }}</div>

    <h2>Pregnancy Details</h2>
    <div class="grid">
        <div class="box"><div class="label">Status</div><div class="value">{{ $patient->status }}</div></div>
        <div class="box"><div class="label">Gravida / Para</div><div class="value">{{ $patient->gravida ?? 'N/A' }} / {{ $patient->para ?? 'N/A' }}</div></div>
        <div class="box"><div class="label">LMP</div><div class="value">{{ $patient->lmp?->format('M d, Y') ?: 'Not recorded' }}</div></div>
        <div class="box"><div class="label">EDD</div><div class="value">{{ $patient->edd?->format('M d, Y') ?: 'Not recorded' }}</div></div>
        <div class="box"><div class="label">Delivery Date</div><div class="value">{{ $patient->delivery_date?->format('M d, Y') ?: 'Not recorded' }}</div></div>
        <div class="box"><div class="label">Birthdate</div><div class="value">{{ $patient->birthdate?->format('M d, Y') ?: 'Not recorded' }}</div></div>
        <div class="box"><div class="label">Age</div><div class="value">{{ $patient->age ?? 'Not recorded' }}</div></div>
        <div class="box"><div class="label">Civil Status</div><div class="value">{{ $patient->civil_status ?: 'Not recorded' }}</div></div>
        <div class="box"><div class="label">Contact Number</div><div class="value">{{ $patient->contact_number ?: 'Not recorded' }}</div></div>
        <div class="box"><div class="label">PhilHealth Member</div><div class="value">{{ $patient->philhealth_member ? 'Yes' : 'No' }}</div></div>
        <div class="box"><div class="label">PhilHealth Number</div><div class="value">{{ $patient->philhealth_number ?: 'Not recorded' }}</div></div>
        <div class="box"><div class="label">Address</div><div class="value">{!! $patient->formatted_address !== '' ? nl2br(e($patient->formatted_address)) : 'Not recorded' !!}</div></div>
    </div>

    <h2>Prenatal Visits / Checkups</h2>
    @if($patient->prenatalVisits->isNotEmpty())
        <table>
            <thead><tr><th>Date</th><th>BP</th><th>Weight</th><th>Gestational Age</th><th>Risk</th><th>Assessment</th></tr></thead>
            <tbody>
                @foreach($patient->prenatalVisits as $visit)
                    <tr>
                        <td>{{ $visit->visit_date?->format('M d, Y') ?: 'Not recorded' }}</td>
                        <td>{{ $visit->bp_sys ?? 'N/A' }}/{{ $visit->bp_dia ?? 'N/A' }}</td>
                        <td>{{ $visit->weight !== null ? \App\Support\WeightFormatter::formatKg($visit->weight) . ' kg' : 'N/A' }}</td>
                        <td>{{ $visit->gestational_age ?? 'N/A' }} weeks</td>
                        <td>{{ $visit->risk_level ?: 'Not recorded' }}</td>
                        <td>{{ $visit->assessment ?: 'No assessment recorded.' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">No prenatal visits were recorded for this pregnancy.</p>
    @endif

    <h2>Pregnancy Outcome</h2>
    @if($patient->pregnancyOutcome)
        <div class="grid">
            <div class="box"><div class="label">Outcome</div><div class="value">{{ $patient->pregnancyOutcome->outcome_type ?: 'Not recorded' }}</div></div>
            <div class="box"><div class="label">Delivery Location</div><div class="value">{{ $patient->pregnancyOutcome->delivery_location ?: 'Not recorded' }}</div></div>
            <div class="box"><div class="label">Confirmation Source</div><div class="value">{{ $patient->pregnancyOutcome->confirmation_source ?: 'Not recorded' }}</div></div>
            <div class="box"><div class="label">Confirmed At</div><div class="value">{{ $patient->pregnancyOutcome->confirmed_at?->format('M d, Y H:i') ?: 'Not recorded' }}</div></div>
            <div class="box"><div class="label">Confirmed By</div><div class="value">{{ $patient->pregnancyOutcome->confirmedBy?->name ?: 'Not recorded' }}</div></div>
            <div class="box"><div class="label">Notes</div><div class="value">{{ $patient->pregnancyOutcome->notes ?: 'No outcome notes recorded.' }}</div></div>
        </div>
    @else
        <p class="muted">No pregnancy outcome has been recorded.</p>
    @endif

    <h2>Baby/Babies</h2>
    @forelse($patient->babies as $baby)
        <h3>Baby {{ $loop->iteration }}{{ $baby->full_name ? ': ' . $baby->full_name : '' }}</h3>
        <div class="grid">
            <div class="box"><div class="label">Sex</div><div class="value">{{ $baby->sex ?: 'Not recorded' }}</div></div>
            <div class="box"><div class="label">Date of Birth</div><div class="value">{{ $baby->date_of_birth?->format('M d, Y') ?: 'Not recorded' }}</div></div>
            <div class="box"><div class="label">Time of Birth</div><div class="value">{{ $baby->time_of_birth?->format('h:i A') ?: 'Not recorded' }}</div></div>
            <div class="box"><div class="label">Birth Weight</div><div class="value">{{ $baby->birth_weight !== null ? \App\Support\WeightFormatter::formatKg($baby->birth_weight) . ' kg' : 'Not recorded' }}</div></div>
            <div class="box"><div class="label">Birth Length</div><div class="value">{{ $baby->birth_length ? $baby->birth_length . ' cm' : 'Not recorded' }}</div></div>
        </div>
    @empty
        <p class="muted">No baby records were recorded for this pregnancy.</p>
    @endforelse
</body>
</html>
