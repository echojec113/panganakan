<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Prenatal Visit Assessment - {{ $visit->patient->first_name }} {{ $visit->patient->last_name }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 32px; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        h2 { font-size: 16px; margin-top: 28px; border-bottom: 1px solid #e5e7eb; padding-bottom: 8px; }
        .muted { color: #6b7280; font-size: 13px; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 12px; }
        .box { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; }
        .label { color: #2563eb; font-size: 12px; font-weight: bold; }
        .value { margin-top: 4px; font-weight: bold; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: bold; }
        .badge-high { background: #fee2e2; color: #991b1b; }
        .badge-low { background: #dcfce7; color: #166534; }
        .badge-incomplete { background: #fef3c7; color: #92400e; }
        .badge-unknown { background: #f3f4f6; color: #374151; }
        ul { margin: 8px 0 0 18px; padding: 0; }
        @media print { button { display: none; } body { margin: 18px; } }
    </style>
</head>
<body>
    <button onclick="window.print()" style="float:right;padding:8px 14px;">Print</button>

    <h1>Prenatal Visit Assessment</h1>
    <div class="muted">
        {{ $visit->patient->first_name }} {{ $visit->patient->middle_name ? $visit->patient->middle_name . ' ' : '' }}{{ $visit->patient->last_name }}
        &middot; Visit Date: {{ $visit->visit_date ? \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') : 'N/A' }}
        &middot; Visit ID: {{ $visit->id }}
    </div>

    <h2>Patient Information</h2>
    <div class="grid">
        <div class="box"><div class="label">Age</div><div class="value">{{ $visit->patient->age ?: 'N/A' }}</div></div>
        <div class="box"><div class="label">Gravida / Para</div><div class="value">{{ $visit->patient->gravida ?? 'N/A' }} / {{ $visit->patient->para ?? 'N/A' }}</div></div>
        <div class="box"><div class="label">Address</div><div class="value">{{ $visit->patient->address ?: 'N/A' }}</div></div>
    </div>

    <h2>Visit Findings</h2>
    <div class="grid">
        <div class="box"><div class="label">Visit Date</div><div class="value">{{ $visit->visit_date ? \Carbon\Carbon::parse($visit->visit_date)->format('M d, Y') : 'N/A' }}</div></div>
        <div class="box"><div class="label">Blood Pressure</div><div class="value">{{ $visit->bp_sys }}/{{ $visit->bp_dia }}</div></div>
        <div class="box"><div class="label">Repeat BP</div><div class="value">{{ ($visit->repeat_bp_sys && $visit->repeat_bp_dia) ? $visit->repeat_bp_sys . '/' . $visit->repeat_bp_dia : 'Not recorded' }}</div></div>
        <div class="box"><div class="label">Weight</div><div class="value">{{ $visit->weight ?? 'N/A' }} kg</div></div>
        <div class="box"><div class="label">Temperature</div><div class="value">{{ $visit->temperature ?? 'N/A' }}&deg;C</div></div>
        <div class="box"><div class="label">Gestational Age</div><div class="value">{{ $visit->gestational_age ?? 'N/A' }} wks</div></div>
        <div class="box"><div class="label">Fetal Heart Tone</div><div class="value">{{ $visit->fetal_heart_tone ?: 'N/A' }}</div></div>
        <div class="box"><div class="label">Fetal Movement</div><div class="value">{{ $visit->fetal_movement ?: 'N/A' }}</div></div>
        <div class="box"><div class="label">Next Visit Date</div><div class="value">{{ $visit->next_visit_date ? \Carbon\Carbon::parse($visit->next_visit_date)->format('M d, Y') : 'Not scheduled' }}</div></div>
    </div>

    <h2>Risk Assessment (as recorded for this visit)</h2>
    <div class="grid">
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

    <div class="muted" style="margin-top:24px;">
        Printed record reflects the data persisted for this specific visit (ID {{ $visit->id }}) and is not recalculated at print time.
    </div>
</body>
</html>
