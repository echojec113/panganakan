<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prenatal Visit Assessment - {{ $visit->patient->first_name }} {{ $visit->patient->last_name }}</title>
    <style>
    * {
        box-sizing: border-box;
    }

    :root {
        --text: #111827;
        --muted: #64748b;
        --border: #e2e8f0;
        --soft-border: #edf1f5;
        --surface: #ffffff;
        --surface-soft: #f8fafc;
        --blue: #1d4ed8;
        --green: #55B85A;
    }

    body {
        margin: 0;
        padding: 32px 24px 48px;
        background: #f4f6f8;
        color: var(--text);
        font-family: Arial, sans-serif;
        line-height: 1.45;
    }

    /*
     * Keeps the clinical record at a readable document width instead of
     * stretching every field across the entire monitor.
     */
    body > * {
        max-width: 1100px;
        margin-left: auto;
        margin-right: auto;
    }

    .document {
        padding: 28px 32px 32px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 6px 24px rgba(15, 23, 42, .06);
        overflow-wrap: anywhere;
    }

    .document-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;
        padding-bottom: 20px;
        border-bottom: 1px solid var(--border);
    }

    .clinic-identity { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .clinic-logo { width: 60px; height: 60px; object-fit: contain; flex-shrink: 0; }
    .clinic-name { font-size: 20px; font-weight: 700; color: #19355f; line-height: 1.25; }
    .clinic-subtitle { margin-top: 4px; font-size: 10px; letter-spacing: .12em; color: var(--muted); }
    .document-heading { min-width: 0; max-width: 55%; text-align: right; }
    .document-heading h1 { font-size: 17px; text-transform: uppercase; letter-spacing: .04em; }
    .document-heading .muted { margin-bottom: 0; }
    .document-heading .patient-name { margin: 8px 0 4px; font-size: 14px; font-weight: 600; }
    .document-heading .visit-meta { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 4px 14px; }
    .record-note { padding-top: 14px; border-top: 1px solid var(--border); }

    .print-btn {
        display: block;
        margin-bottom: 18px;
        padding: 10px 18px;
        background: var(--green);
        color: #ffffff;
        border: 0;
        border-radius: 7px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        width: fit-content;
        margin-left: auto;
        margin-right: 0;
    }

    .print-btn:hover {
        background: #49a94e;
    }

    h1 {
        margin-top: 0;
        margin-bottom: 6px;
        font-size: 26px;
        line-height: 1.25;
        letter-spacing: -0.02em;
    }

    h1 + .muted {
        margin-bottom: 30px;
    }

    h2 {
        margin-top: 30px;
        margin-bottom: 0;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border);
        font-size: 16px;
        line-height: 1.35;
    }

    .muted {
        color: var(--muted);
        font-size: 13px;
    }

    .grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        margin-top: 14px;
    }

    .grid > *,
    .box {
        min-width: 0;
    }

    .box {
        padding: 15px 16px;
        border: 1px solid var(--border);
        border-radius: 9px;
        background: var(--surface);
        overflow-wrap: anywhere;
    }

    .label {
        color: var(--blue);
        font-size: 11px;
        font-weight: 700;
        line-height: 1.35;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    .value {
        margin-top: 6px;
        color: var(--text);
        font-size: 14px;
        font-weight: 400;
        line-height: 1.45;
    }

    .badge {
        display: inline-block;
        max-width: 100%;
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        line-height: 1.3;
        white-space: normal;
    }

    .badge-high {
        background: #fee2e2;
        color: #991b1b;
    }

    .badge-low {
        background: #dcfce7;
        color: #166534;
    }

    .badge-incomplete {
        background: #fef3c7;
        color: #92400e;
    }

    .badge-unknown {
        background: #f3f4f6;
        color: #374151;
    }

    ul {
        margin: 14px 0 0;
        color: #374151;
        font-size: 14px;
        line-height: 1.6;
        padding: 12px 16px 12px 34px;
        border: 1px solid var(--border);
        border-radius: 9px;
        background: var(--surface-soft);
        overflow-wrap: anywhere;
    }

    li + li {
        margin-top: 5px;
    }

    /*
     * Tablet
     */
    @media screen and (min-width: 640px) and (max-width: 1023px) {
        body {
            padding: 24px 20px 40px;
        }

        .grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        h1 {
            font-size: 24px;
        }

        .document { padding: 24px; }
        .document-header { flex-direction: column; gap: 16px; }
        .document-heading { max-width: 100%; text-align: left; }
        .document-heading .visit-meta { justify-content: flex-start; }
    }

    /*
     * Mobile
     */
    @media screen and (max-width: 639px) {
        body {
            padding: 20px 14px 32px;
        }

        .print-btn {
            margin-bottom: 20px;
        }

        .document { padding: 18px 14px; border-radius: 9px; }
        .document-header { flex-direction: column; gap: 16px; padding-bottom: 16px; }
        .clinic-name { font-size: 18px; }
        .document-heading { max-width: 100%; text-align: left; }
        .document-heading h1 { font-size: 15px; }
        .document-heading .visit-meta { justify-content: flex-start; }

        h1 {
            font-size: 21px;
        }

        h1 + .muted {
            margin-bottom: 24px;
        }

        h2 {
            margin-top: 26px;
            font-size: 15px;
        }

        .grid {
            grid-template-columns: minmax(0, 1fr);
            gap: 10px;
        }

        .box {
            padding: 11px 12px;
        }

        .value {
            font-size: 14px;
        }
    }

    @media screen and (min-width: 380px) and (max-width: 639px) {
        .grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .patient-grid > :last-child { grid-column: 1 / -1; }
        .risk-grid { grid-template-columns: minmax(0, 1fr); }
    }

    /*
     * Printed document
     */
    @media print {
        @page {
            margin: 14mm;
        }

        body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: #000000;
            font-size: 10pt;
        }

        body > * {
            max-width: none;
        }

        .document-toolbar,
        .print-btn {
            display: none !important;
        }

        .document { padding: 0; border: 0; border-radius: 0; box-shadow: none; }
        .document-header { gap: 16px; padding-bottom: 12px; break-inside: avoid; page-break-inside: avoid; }
        .clinic-name { font-size: 14pt; }
        .document-heading h1 { font-size: 11pt; }
        .document-heading .patient-name { font-size: 9pt; }

        h1 {
            font-size: 18pt;
        }

        h1 + .muted {
            margin-bottom: 18px;
        }

        h2 {
            margin-top: 20px;
            padding-bottom: 6px;
            font-size: 11pt;
            break-after: avoid;
            page-break-after: avoid;
        }

        .grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 7px;
            margin-top: 8px;
        }

        .box {
            padding: 8px 9px;
            border-color: #d1d5db;
            border-radius: 5px;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .label {
            color: #374151;
            font-size: 7.5pt;
        }

        .value {
            margin-top: 3px;
            font-size: 9pt;
        }

        .muted {
            color: #4b5563;
            font-size: 8pt;
        }

        .badge {
            padding: 3px 7px;
            font-size: 7.5pt;
        }

        ul {
            font-size: 9pt;
            padding: 8px 10px 8px 26px;
            background: #ffffff;
        }

        li { break-inside: avoid; page-break-inside: avoid; }
    }
</style>
</head>
<body>
    <div class="document-toolbar">
        <button class="print-btn" onclick="window.print()">Print</button>
    </div>

    <main class="document">
    <header class="document-header">
        <div class="clinic-identity">
            <img class="clinic-logo" src="{{ asset('images/logo.png') }}" alt="Depla Family Care Logo" width="60" height="60">
            <div>
                <div class="clinic-name">Depla Family Care</div>
                <div class="clinic-subtitle">MATERNITY &amp; LYING-IN</div>
            </div>
        </div>
        <div class="document-heading">
            <h1>Prenatal Visit Assessment</h1>
            <div class="patient-name">
                {{ $visit->patient->first_name }} {{ $visit->patient->middle_name ? $visit->patient->middle_name . ' ' : '' }}{{ $visit->patient->last_name }}
            </div>
            <div class="muted visit-meta">
                <span>Visit Date: {{ $visit->visit_date ? \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') : 'N/A' }}</span>
                <span>Visit ID: {{ $visit->id }}</span>
            </div>
        </div>
    </header>

    <h2>Patient Information</h2>
    <div class="grid patient-grid">
        <div class="box"><div class="label">Age</div><div class="value">{{ $visit->patient->age ?: 'N/A' }}</div></div>
        <div class="box"><div class="label">Gravida / Para</div><div class="value">{{ $visit->patient->gravida ?? 'N/A' }} / {{ $visit->patient->para ?? 'N/A' }}</div></div>
        <div class="box"><div class="label">Address</div><div class="value">{!! $visit->patient->formatted_address !== '' ? nl2br(e($visit->patient->formatted_address)) : 'N/A' !!}</div></div>
    </div>

    <h2>Visit Findings</h2>
    <div class="grid">
        <div class="box"><div class="label">Visit Date</div><div class="value">{{ $visit->visit_date ? \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') : 'N/A' }}</div></div>
        <div class="box"><div class="label">Blood Pressure</div><div class="value">{{ $visit->bp_sys }}/{{ $visit->bp_dia }}</div></div>
        <div class="box"><div class="label">Repeat Blood Pressure</div><div class="value">{{ ($visit->repeat_bp_sys && $visit->repeat_bp_dia) ? $visit->repeat_bp_sys . '/' . $visit->repeat_bp_dia : 'Not recorded' }}</div></div>
        <div class="box"><div class="label">Weight</div><div class="value">{{ \App\Support\WeightFormatter::formatKg($visit->weight) !== null ? \App\Support\WeightFormatter::formatKg($visit->weight) . ' kg' : 'N/A' }}</div></div>
        <div class="box"><div class="label">Temperature</div><div class="value">{{ $visit->temperature ?? 'N/A' }}&deg;C</div></div>
        <div class="box"><div class="label">Gestational Age</div><div class="value">{{ $visit->gestational_age ?? 'N/A' }} wks</div></div>
        <div class="box"><div class="label">Fetal Heart Tone</div><div class="value">{{ $visit->fetal_heart_tone ?: 'N/A' }}</div></div>
        <div class="box"><div class="label">Fetal Movement</div><div class="value">{{ $visit->fetal_movement ?: 'N/A' }}</div></div>
        <div class="box"><div class="label">Next Visit Date</div><div class="value">{{ $visit->next_visit_date ? \Carbon\Carbon::parse($visit->next_visit_date)->format('M d, Y') : 'Not scheduled' }}</div></div>
    </div>

    <h2>Risk Assessment (as recorded for this visit)</h2>
    <div class="grid risk-grid">
        <div class="box">
            <div class="label">Risk Level</div>
            <div class="value">
                @if($visit->risk_level === 'HIGH')
                    <span class="badge badge-high">HIGH</span>
                @elseif($visit->risk_level === 'LOW')
                    <span class="badge badge-low">LOW</span>
                @elseif($visit->risk_level === 'ASSESSMENT INCOMPLETE')
                    <span class="badge badge-incomplete">ASSESSMENT INCOMPLETE</span>
                @else
                    <span class="badge badge-unknown">{{ $visit->risk_level ?: 'UNKNOWN' }}</span>
                @endif
            </div>
        </div>
        <div class="box">
            <div class="label">Decision Source</div>
            <div class="value">
                @if($visit->decision_source === 'COMPLETENESS') Completeness Check
                @elseif($visit->decision_source === 'RULE_BASED') Clinical Rules
                @elseif($visit->decision_source === 'MACHINE_LEARNING') Machine Learning
                @elseif($visit->decision_source === 'MACHINE_LEARNING_INVALID') ML Assessment Unavailable
                @elseif($visit->decision_source === null) Legacy assessment
                @else {{ $visit->decision_source }}
                @endif
            </div>
        </div>
        <div class="box"><div class="label">Urgency</div><div class="value">{{ $visit->urgency ?: 'None' }}</div></div>
    </div>

    <div class="box" style="margin-top:12px;">
        <div class="label">Clinical Assessment</div>
        <div class="value">{{ $visit->assessment ?: 'No assessment text recorded.' }}</div>
    </div>
    <div class="box" style="margin-top:12px;">
        <div class="label">Recommendation</div>
        <div class="value">{{ $visit->recommendation ?: 'No recommendation recorded.' }}</div>
    </div>

    @php
        $riskReasons = \App\Support\ListNormalizer::normalize($visit->risk_reasons);
        $ruleReasons = \App\Support\ListNormalizer::normalize($visit->rule_reasons);
        $missingRecords = \App\Support\ListNormalizer::normalize($visit->missing_records);
        $structuredFactors = \App\ValueObjects\ClinicalFactorEvidence::normalizeList($visit->factor_evidence);
    @endphp

    @if(!empty($riskReasons) || !empty($ruleReasons))
        <h2>Risk Reasons / Clinical Factors</h2>
        <ul>
            @foreach(array_unique(array_merge($ruleReasons, $riskReasons)) as $reason)
                <li>{{ $reason }}</li>
            @endforeach
        </ul>
    @endif

    @if(!empty($structuredFactors))
        <h2>Clinical Factor Evidence</h2>
        <ul>
            @foreach($structuredFactors as $factor)
                <li>{{ $factor['label'] ?? 'Factor' }} @if(!empty($factor['category'])) ({{ $factor['category'] }}) @endif</li>
            @endforeach
        </ul>
    @endif

    @if(!empty($missingRecords))
        <h2>Missing Records at Time of Assessment</h2>
        <ul>
            @foreach($missingRecords as $missing)
                <li>{{ $missing }}</li>
            @endforeach
        </ul>
    @endif

    @if($visit->ml_prediction !== null)
        <h2>Machine Learning Prediction</h2>
        <div class="grid">
            <div class="box"><div class="label">ML Prediction</div><div class="value">{{ $visit->ml_prediction }}</div></div>
            <div class="box"><div class="label">ML Valid</div><div class="value">{{ $visit->ml_valid ? 'Yes' : 'No' }}</div></div>
        </div>
    @endif

    <div class="muted record-note" style="margin-top:24px;">
        Printed record reflects the data persisted for this specific visit (ID {{ $visit->id }}) and is not recalculated at print time.
    </div>
    </main>
</body>
</html>
