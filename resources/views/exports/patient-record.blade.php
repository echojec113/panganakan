<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Record</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1f2937; margin: 0; padding: 0; }
        .page { padding: 32px; }
        .header { text-align: center; margin-bottom: 24px; }
        .brand { font-size: 14px; letter-spacing: 1px; margin-bottom: 8px; }
        .title { font-size: 24px; font-weight: 700; margin-bottom: 16px; }
        .section { margin-bottom: 18px; }
        .section-title { font-size: 16px; font-weight: 700; margin-bottom: 10px; border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; }
        .row { display: flex; flex-wrap: wrap; margin-bottom: 6px; }
        .label { width: 36%; font-weight: 600; color: #374151; }
        .value { width: 64%; color: #111827; }
        .table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .table th, .table td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }
        .table th { background: #f3f4f6; font-weight: 700; }
        .check { font-weight: 700; }
        .small { font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="brand">DEPLA FAMILY CARE MATERNITY & LYING-IN</div>
            <div class="brand">901 PARADA STA. MARIA BULACAN</div>
            <div class="title">PATIENT RECORD</div>
        </div>

        <div class="section">
            <div class="section-title">1. Basic Patient Information</div>
            <div class="row"><div class="label">Full Name</div><div class="value">{{ trim($patient->first_name . ' ' . ($patient->middle_name ? $patient->middle_name . ' ' : '') . $patient->last_name) }}</div></div>
            <div class="row"><div class="label">Age</div><div class="value">{{ $patient->age }}</div></div>
            <div class="row"><div class="label">Birthdate</div><div class="value">{{ $patient->birthdate }}</div></div>
            <div class="row"><div class="label">Address</div><div class="value">{{ $patient->address }}</div></div>
            <div class="row"><div class="label">Contact Number</div><div class="value">{{ $patient->contact_number }}</div></div>
            <div class="row"><div class="label">Civil Status</div><div class="value">{{ $patient->civil_status }}</div></div>
            <div class="row"><div class="label">PhilHealth Member</div><div class="value">{{ $patient->philhealth_member ? 'Yes' : 'No' }}</div></div>
            <div class="row"><div class="label">PhilHealth Number</div><div class="value">{{ $patient->philhealth_number ?: 'N/A' }}</div></div>
        </div>

        <div class="section">
            <div class="section-title">2. Pregnancy Information</div>
            <div class="row"><div class="label">Gravida (G)</div><div class="value">{{ $patient->gravida }}</div></div>
            <div class="row"><div class="label">Para (P)</div><div class="value">{{ $patient->para }}</div></div>
            <div class="row"><div class="label">LMP</div><div class="value">{{ $patient->lmp }}</div></div>
            <div class="row"><div class="label">EDD</div><div class="value">{{ $patient->edd }}</div></div>
            <div class="row"><div class="label">Pregnancy Status</div><div class="value">{{ $patient->status === 'DELIVERED' ? 'Delivered' : 'Ongoing' }}</div></div>
            <div class="row"><div class="label">Delivery Date</div><div class="value">{{ $patient->delivery_date ?: 'N/A' }}</div></div>
        </div>

        <div class="section">
            <div class="section-title">3. Medical History</div>
            @php $history = $patient->medicalHistory; @endphp
            @if($history)
            <table class="table">
                <thead>
                    <tr><th>Condition</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @php
                        $conditions = [
                            'Epilepsy' => $history->epilepsy,
                            'Severe Headache' => $history->severe_headache,
                            'Visual Disturbance' => $history->visual_disturbance,
                            'Chest Pain' => $history->chest_pain,
                            'Shortness of Breath' => $history->shortness_breath,
                            'Breast Mass' => $history->breast_mass,
                            'Liver Disease' => $history->liver_disease,
                            'Smoking' => $history->smoking,
                            'Allergies' => $history->allergies,
                            'Drug Intake' => $history->drug_intake,
                            'STD History' => $history->std_history,
                            'Diabetes' => $history->diabetes,
                            'Hypertension' => $history->hypertension,
                            'Asthma' => $history->asthma,
                            'Thyroid Disease' => $history->thyroid_disease,
                            'Heart Disease' => $history->heart_disease,
                            'Anemia' => $history->anemia,
                            'Mental Health Condition' => $history->mental_health_condition,
                        ];
                    @endphp
                    @foreach($conditions as $label => $value)
                        <tr>
                            <td>{{ $label }}</td>
                            <td class="check">{{ $value ? '✔ Yes' : '❌ No' }}</td>
                        </tr>
                    @endforeach
                    @if($history->other_specify)
                        <tr>
                            <td>Other</td>
                            <td class="check">✔ {{ $history->other_specify }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
            @else
                <p class="small">No medical history recorded.</p>
            @endif
        </div>

        <div class="section">
            <div class="section-title">4. Prenatal Visit (Latest)</div>
            @if($latestVisit)
                <div class="row"><div class="label">Visit Date</div><div class="value">{{ $latestVisit->visit_date }}</div></div>
                <div class="row"><div class="label">Blood Pressure</div><div class="value">{{ $latestVisit->bp_sys }}/{{ $latestVisit->bp_dia }}</div></div>
                <div class="row"><div class="label">Weight</div><div class="value">{{ $latestVisit->weight }} kg</div></div>
                <div class="row"><div class="label">Temperature</div><div class="value">{{ $latestVisit->temperature }} °C</div></div>
                <div class="row"><div class="label">Gestational Age</div><div class="value">{{ $latestVisit->gestational_age }}</div></div>
            @else
                <p class="small">No prenatal visits recorded.</p>
            @endif
        </div>

        <div class="section">
            <div class="section-title">5. Clinical Decision Summary</div>
            @if($latestVisit)
                @php
                    $ds = $latestVisit->decision_source;
                    $rl = $latestVisit->risk_level;
                    $riskLabel = match($rl) {
                        'HIGH' => 'High',
                        'LOW' => 'Low',
                        'ASSESSMENT INCOMPLETE' => 'Assessment Incomplete',
                        default => $rl,
                    };
                    $dsLabel = match($ds) {
                        'COMPLETENESS' => 'Completeness Check',
                        'RULE_BASED' => 'Clinical Rules',
                        'MACHINE_LEARNING' => 'Machine Learning',
                        'MACHINE_LEARNING_INVALID' => 'ML Assessment Unavailable',
                        null => 'Legacy Assessment',
                        default => $ds,
                    };
                    $decisionPath = '';
                    if ($ds === 'RULE_BASED') {
                        $decisionPath = 'Deterministic clinical rules identified the listed risk factors. Machine learning was not executed because the rule-based safety pathway had already established a HIGH assessment.';
                    } elseif ($ds === 'COMPLETENESS') {
                        $decisionPath = 'The final assessment could not be completed because required clinical records were missing. Machine learning was not executed.';
                    } elseif ($ds === 'MACHINE_LEARNING') {
                        $decisionPath = 'No deterministic HIGH-risk rule was triggered. The machine-learning model produced a valid ' . ($latestVisit->ml_prediction ?? '') . ' contribution.';
                    } elseif ($ds === 'MACHINE_LEARNING_INVALID') {
                        $decisionPath = 'Required records were complete and no deterministic HIGH-risk rule was triggered, but the machine-learning component did not produce a valid result. The final state remains ASSESSMENT INCOMPLETE.';
                    } elseif ($ds === null) {
                        $decisionPath = 'This assessment predates structured explanation metadata. Detailed decision reconstruction is unavailable.';
                    }
                    $structuredFactors = \App\ValueObjects\ClinicalFactorEvidence::normalizeList($latestVisit->factor_evidence);
                    $factorSourceLabels = [
                        'MATERNAL_DEMOGRAPHICS' => 'Maternal demographics',
                        'VITAL_SIGNS' => 'Vital signs',
                        'CURRENT_CONDITION' => 'Current condition',
                        'OBSTETRIC_HISTORY' => 'Obstetric history',
                        'ULTRASOUND' => 'Ultrasound finding',
                    ];
                @endphp

                <div class="row"><div class="label">Final Risk Assessment</div><div class="value">{{ $riskLabel }}</div></div>

                @if($latestVisit->urgency === 'URGENT_CLINICAL_REVIEW')
                <div class="row"><div class="label">Urgency</div><div class="value" style="color:#b91c1c;font-weight:700;">URGENT CLINICAL REVIEW REQUIRED</div></div>
                @elseif($latestVisit->urgency === 'PROMPT')
                <div class="row"><div class="label">Urgency</div><div class="value" style="color:#d97706;font-weight:600;">PROMPT (within 1 week)</div></div>
                @endif

                <div class="row"><div class="label">Decision Source</div><div class="value">{{ $dsLabel }}</div></div>

                @if($latestVisit->bp_assessment)
                <div style="margin-top:8px;font-weight:600;color:#374151;">Blood Pressure Assessment:</div>
                <div style="margin:4px 0 10px 20px;font-size:13px;color:#555;">
                    <div>Reading: {{ $latestVisit->bp_sys }}/{{ $latestVisit->bp_dia }}</div>
                    @if($latestVisit->repeat_bp_sys && $latestVisit->repeat_bp_dia)
                    <div>Repeat: {{ $latestVisit->repeat_bp_sys }}/{{ $latestVisit->repeat_bp_dia }}</div>
                    @endif
                    <div>Classification: {{ $latestVisit->bp_assessment['label'] ?? '' }}</div>
                    <div>Interpretation: {{ $latestVisit->bp_assessment['interpretation'] ?? '' }}</div>
                    <div>Action: {{ $latestVisit->bp_assessment['action'] ?? '' }}</div>
                    @if($latestVisit->bp_verification_status)
                    <div>Verification: {{ str_replace('_', ' ', $latestVisit->bp_verification_status) }}</div>
                    @endif
                </div>
                @endif

                @if(!empty($structuredFactors))
                    <div style="margin-top:8px;font-weight:600;color:#374151;">Structured Clinical Factors:</div>
                    <table style="width:100%;border-collapse:collapse;margin:6px 0 10px 0;font-size:12px;">
                        <thead>
                            <tr style="background:#f9fafb;">
                                <th style="border:1px solid #e5e7eb;padding:6px 8px;text-align:left;">Factor</th>
                                <th style="border:1px solid #e5e7eb;padding:6px 8px;text-align:left;">Code</th>
                                <th style="border:1px solid #e5e7eb;padding:6px 8px;text-align:left;">Source</th>
                                <th style="border:1px solid #e5e7eb;padding:6px 8px;text-align:left;">Observed</th>
                                <th style="border:1px solid #e5e7eb;padding:6px 8px;text-align:left;">Rule / Threshold</th>
                                <th style="border:1px solid #e5e7eb;padding:6px 8px;text-align:left;">Explanation / Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($structuredFactors as $factor)
                            <tr>
                                <td style="border:1px solid #e5e7eb;padding:6px 8px;font-weight:500;">{{ $factor['label'] ?? '' }}</td>
                                <td style="border:1px solid #e5e7eb;padding:6px 8px;font-family:monospace;">{{ $factor['code'] ?? '' }}</td>
                                <td style="border:1px solid #e5e7eb;padding:6px 8px;">{{ $factorSourceLabels[$factor['category'] ?? ''] ?? ($factor['category'] ?? '') }}</td>
                                <td style="border:1px solid #e5e7eb;padding:6px 8px;">{{ \App\ValueObjects\ClinicalFactorEvidence::displayObserved($factor['observed_value'] ?? null) }}</td>
                                <td style="border:1px solid #e5e7eb;padding:6px 8px;">{{ $factor['threshold_or_rule'] ?? '' }}</td>
                                <td style="border:1px solid #e5e7eb;padding:6px 8px;">
                                    {{ $factor['explanation'] ?? '' }}
                                    @if(!empty($factor['suggested_action']))<br><em>Action: {{ $factor['suggested_action'] }}</em>@endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @elseif($ds === 'RULE_BASED' && !empty($latestVisit->rule_reasons))
                    <div style="margin-top:8px;font-weight:600;color:#374151;">Triggered Clinical Rules:</div>
                    <ul style="margin:4px 0 10px 20px;font-size:13px;color:#555;">
                        @foreach($latestVisit->rule_reasons as $rule)
                            <li>{{ $rule }}</li>
                        @endforeach
                    </ul>
                @endif

                @if($ds === 'COMPLETENESS' && !empty($latestVisit->missing_records))
                    <div style="margin-top:8px;font-weight:600;color:#374151;">Missing Required Records:</div>
                    <ul style="margin:4px 0 10px 20px;font-size:13px;color:#555;">
                        @foreach($latestVisit->missing_records as $record)
                            <li>{{ $record }}</li>
                        @endforeach
                    </ul>
                @endif

                @if($ds === 'MACHINE_LEARNING' && $latestVisit->ml_prediction)
                    <div style="margin-top:8px;font-weight:600;color:#374151;">Machine-Learning Contribution:</div>
                    <div style="margin:4px 0 10px 20px;font-size:13px;color:#555;">
                        Prediction: {{ $latestVisit->ml_prediction }} (Valid)
                    </div>
                @endif

                <div style="margin-top:8px;font-weight:600;color:#374151;">Decision Path:</div>
                <div class="value" style="margin:4px 0 10px 0;font-size:13px;color:#555;line-height:1.5;">{{ $decisionPath }}</div>

                <div class="row"><div class="label">Clinical Assessment</div><div class="value">{{ $latestVisit->assessment }}</div></div>
                <div class="row"><div class="label">Recommended Action</div><div class="value">{{ $latestVisit->recommendation }}</div></div>
                <div class="row"><div class="label">Recommended Follow-up</div><div class="value">{{ $latestVisit->next_visit_date ? \Carbon\Carbon::parse($latestVisit->next_visit_date)->format('M d, Y') : 'Not scheduled' }}</div></div>

                <div style="margin-top:16px;padding:12px;background:#fef3c7;border:1px solid #fde68a;border-radius:6px;font-size:11px;color:#92400e;line-height:1.5;">
                    <strong>Safety Disclaimer:</strong> This system-generated assessment is intended to support clinical decision-making and is not a medical diagnosis. Final clinical judgment remains with qualified clinic personnel.
                </div>
            @else
                <p class="small">No assessment data available.</p>
            @endif
        </div>

        @if($patient->status === 'DELIVERED' && $patient->babies->count() > 0)
        <div class="section">
            <div class="section-title">6. Baby Information</div>
            @foreach($patient->babies as $index => $baby)
                <div style="margin-bottom: 16px; padding: 12px; background: #fef7ff; border: 1px solid #e9d5ff; border-radius: 6px;">
                    <div style="font-weight: 700; margin-bottom: 8px; color: #7c3aed;">Baby {{ $index + 1 }}: {{ $baby->full_name }}</div>
                    <div class="row"><div class="label">Sex</div><div class="value">{{ $baby->sex ?: 'N/A' }}</div></div>
                    <div class="row"><div class="label">Date of Birth</div><div class="value">{{ $baby->date_of_birth ? \Carbon\Carbon::parse($baby->date_of_birth)->format('M d, Y') : 'N/A' }}</div></div>
                    <div class="row"><div class="label">Time of Birth</div><div class="value">{{ $baby->time_of_birth ? \Carbon\Carbon::parse($baby->time_of_birth)->format('g:i A') : 'N/A' }}</div></div>
                    <div class="row"><div class="label">Birth Weight</div><div class="value">{{ $baby->birth_weight ? $baby->birth_weight . ' kg' : 'N/A' }}</div></div>
                    <div class="row"><div class="label">Birth Length</div><div class="value">{{ $baby->birth_length ? $baby->birth_length . ' cm' : 'N/A' }}</div></div>
                </div>
            @endforeach
        </div>
        @endif
    </div>
</body>
</html>