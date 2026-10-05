<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Baby Information</title>
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
            font-size: 14px;
            font-weight: 600;
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
            margin: 0;
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
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 12px;
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

            .grid {
                gap: 10px 18px;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .box {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <nav class="toolbar no-print" aria-label="Baby information actions">
        <a href="{{ route('patients.delivered.history', $patient->id) }}">Back to Baby Information</a>
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

        <h1 class="document-title">Baby Information</h1>
        <div class="muted document-subtitle">{{ $patient->first_name }} {{ $patient->middle_name ? $patient->middle_name . ' ' : '' }}{{ $patient->last_name }}</div>

        @foreach($pregnancies as $pregnancy)
            @php
                $latestVisit = $pregnancy->prenatalVisits->sortByDesc('visit_date')->first();
                $outcome = $pregnancy->pregnancyOutcome;
                $confirmed = $outcome && $outcome->hasConfirmedOutcome();
            @endphp
            <h2>Pregnancy Delivered {{ $pregnancy->delivery_date ? \Carbon\Carbon::parse($pregnancy->delivery_date)->format('M d, Y') : 'N/A' }}</h2>
            <div class="grid">
                <div class="box"><div class="label">Delivery Location</div><div class="value">{{ $confirmed && $outcome->delivery_location !== null ? \App\Support\PregnancyOutcomeVocabulary::deliveryLocationLabel($outcome->delivery_location) : 'Not recorded' }}</div></div>
                <div class="box"><div class="label">Number of Babies</div><div class="value">{{ $pregnancy->babies->count() }}</div></div>
                <div class="box"><div class="label">Risk Level</div><div class="value">{{ $latestVisit?->risk_level ?: 'N/A' }}</div></div>
            </div>
            @if($confirmed)
                <div class="grid">
                    <div class="box"><div class="label">Confirmation Source</div><div class="value">{{ $outcome->confirmation_source !== null ? \App\Support\PregnancyOutcomeVocabulary::confirmationSourceLabel($outcome->confirmation_source) : 'N/A' }}</div></div>
                    <div class="box"><div class="label">Confirmed At</div><div class="value">{{ $outcome->confirmed_at?->format('M d, Y H:i') ?: 'N/A' }}</div></div>
                    <div class="box"><div class="label">Recorded By</div><div class="value">{{ $outcome->confirmedBy?->name ?: 'N/A' }}</div></div>
                </div>
            @endif

            @foreach($pregnancy->babies as $baby)
                <h3>Baby {{ $loop->iteration }}</h3>
                <div class="grid">
                    <div class="box"><div class="label">Full Name</div><div class="value">{{ $baby->full_name ?: 'N/A' }}</div></div>
                    <div class="box"><div class="label">Sex</div><div class="value">{{ $baby->sex ?: 'N/A' }}</div></div>
                    <div class="box"><div class="label">Birth Weight</div><div class="value">{{ $baby->birth_weight !== null ? \App\Support\WeightFormatter::formatKg($baby->birth_weight) . ' kg' : 'N/A' }}</div></div>
                    <div class="box"><div class="label">Birth Length</div><div class="value">{{ $baby->birth_length ? $baby->birth_length . ' cm' : 'N/A' }}</div></div>
                    <div class="box"><div class="label">Date of Birth</div><div class="value">{{ $baby->date_of_birth ? \Carbon\Carbon::parse($baby->date_of_birth)->format('M d, Y') : 'N/A' }}</div></div>
                    <div class="box"><div class="label">Time of Birth</div><div class="value">{{ $baby->time_of_birth ? \Carbon\Carbon::parse($baby->time_of_birth)->format('h:i A') : 'N/A' }}</div></div>
                </div>
            @endforeach
        @endforeach
    </article>
</body>
</html>
