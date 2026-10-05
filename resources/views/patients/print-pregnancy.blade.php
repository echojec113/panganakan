<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pregnancy Record - {{ $patient->first_name }} {{ $patient->last_name }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Calibri', 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
            padding: 20px;
        }

        .toolbar,
        .print-container {
            max-width: 1100px;
            margin-left: auto;
            margin-right: auto;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .toolbar a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            margin: 0;
            padding: 10px 18px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #ffffff;
            color: #19355f;
            font-family: inherit;
            font-size: 14px;
            text-decoration: none;
        }

        .toolbar a:hover {
            background: #f8fafc;
        }

        .print-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 10px 18px;
            border: 0;
            border-radius: 8px;
            background: #55b85a;
            color: #ffffff;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .print-button:hover {
            background: #4aa04c;
        }

        .print-container {
            background: #ffffff;
            width: 100%;
            padding: 28px 32px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .print-container * {
            min-width: 0;
            max-width: 100%;
        }

        .clinic-header {
            display: flex;
            align-items: center;
            gap: 14px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 18px;
            padding-bottom: 16px;
        }

        .clinic-logo {
            width: 60px;
            height: 60px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .clinic-name {
            font-size: 20px;
            font-weight: 700;
            color: #19355f;
            line-height: 1.25;
        }

        .clinic-header p {
            font-size: 12px;
            color: #666;
            margin-bottom: 3px;
        }

        .clinic-header p:last-child {
            margin-bottom: 0;
        }

        .clinic-subtitle {
            letter-spacing: .12em;
        }

        .document-title {
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            color: #19355f;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.4;
            margin: 18px 0 6px;
        }

        .document-subtitle {
            text-align: center;
            color: #666;
            font-size: 13px;
            margin-bottom: 4px;
        }

        h2 {
            font-size: 13px;
            font-weight: 700;
            color: #19355f;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 26px 0 12px;
            break-after: avoid;
            page-break-after: avoid;
        }

        h3 {
            font-size: 14px;
            font-weight: 700;
            color: #19355f;
            margin: 18px 0 10px;
            break-after: avoid;
            page-break-after: avoid;
        }

        .muted {
            color: #666;
            font-size: 13px;
            overflow-wrap: anywhere;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 20px;
        }

        .box {
            min-width: 0;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .label {
            font-weight: 600;
            color: #19355f;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 3px;
        }

        .value {
            color: #333;
            font-size: 13px;
            overflow-wrap: anywhere;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 12px;
            font-size: 13px;
        }

        th:nth-child(1) { width: 14%; }
        th:nth-child(2) { width: 9%; }
        th:nth-child(3) { width: 10%; }
        th:nth-child(4) { width: 13%; }
        th:nth-child(5) { width: 7%; }
        th:nth-child(6) { width: 47%; }

        th,
        td {
            border: 1px solid #e2e8f0;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
            overflow-wrap: anywhere;
        }

        th {
            background: #f8fafc;
            color: #19355f;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        td {
            color: #333;
        }

        tr {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        thead {
            display: table-header-group;
        }

        @media screen and (max-width: 639px) {
            body {
                padding: 16px 12px;
            }

            .print-container {
                padding: 20px 16px;
            }

            .clinic-header {
                align-items: flex-start;
                gap: 10px;
            }

            .clinic-name {
                font-size: 18px;
            }

            .grid {
                grid-template-columns: minmax(0, 1fr);
            }

            table {
                font-size: 12px;
            }
        }

        @media print {
            @page {
                size: letter;
                margin: 12mm;
            }

            body {
                background: #ffffff;
                padding: 0;
                margin: 0;
                line-height: 1.45;
            }

            .no-print,
            .toolbar {
                display: none !important;
            }

            .print-container {
                width: 100%;
                max-width: none;
                margin: 0;
                padding: 0;
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .clinic-header {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            h2,
            h3 {
                margin-top: 18px;
                break-after: avoid;
                page-break-after: avoid;
            }

            .box,
            tr {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            thead {
                display: table-header-group;
            }

            .grid {
                gap: 10px 18px;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            table {
                margin-top: 10px;
                font-size: 12px;
            }

            th,
            td {
                padding: 6px 8px;
            }
        }
    </style>
</head>
<body>
    <nav class="toolbar no-print" aria-label="Pregnancy record actions">
        <a href="{{ route('view-all-records.pregnancy', $patient) }}">Back to Pregnancy Record</a>
        <button class="print-button" type="button" onclick="window.print()">Print</button>
    </nav>

    <article class="print-container">
        <header class="clinic-header">
            <img class="clinic-logo" src="{{ asset('images/logo.png') }}" alt="Depla Family Care Logo" width="60" height="60">
            <div>
                <div class="clinic-name">Depla Family Care</div>
                <p class="clinic-subtitle">MATERNITY &amp; LYING-IN</p>
                <p>Contact: {{ config('app.clinic_phone', '(555) 123-4567') }} | Address: {{ config('app.clinic_address', 'Maternity Clinic Building') }}</p>
            </div>
        </header>

        <h1 class="document-title">Pregnancy Record</h1>
        <div class="muted document-subtitle">{{ $patient->first_name }} {{ $patient->middle_name ? $patient->middle_name . ' ' : '' }}{{ $patient->last_name }} &middot; Pregnancy record ID: {{ $patient->id }}</div>

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
    </article>
</body>
</html>
