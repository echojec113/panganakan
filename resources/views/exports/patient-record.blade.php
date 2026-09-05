<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Record</title>
    <style>
        @page { margin: 1.5cm 1.4cm; }
        body { font-family: Helvetica, Arial, sans-serif; color: #1f2937; font-size: 11px; line-height: 1.45; margin: 0; padding: 0; }

        /* ── Header ─────────────────────────────────────────── */
        .header { text-align: center; margin-bottom: 16px; }
        .brand { font-size: 12px; font-weight: 700; letter-spacing: 2px; color: #1a3d6e; }
        .brand-address { font-size: 9.5px; letter-spacing: 1px; color: #6b7280; margin-top: 2px; }
        .doc-title { font-size: 20px; font-weight: 700; color: #1a3d6e; margin: 8px 0 6px; }
        .header-meta { font-size: 10.5px; color: #374151; }

        /* ── Sections ───────────────────────────────────────── */
        .section { margin-bottom: 14px; }
        .keep-together { page-break-inside: avoid; }
        .section-title { font-size: 13px; font-weight: 700; color: #1a3d6e; border-bottom: 2px solid #1a3d6e; padding-bottom: 3px; margin: 14px 0 8px; }
        .subsection-title { font-size: 11.5px; font-weight: 700; color: #374151; margin: 10px 0 5px; }

        /* ── Label/value grids ──────────────────────────────── */
        table.data-table { width: 100%; border-collapse: collapse; }
        table.data-table td { padding: 3px 6px; vertical-align: top; }
        .lbl { color: #6b7280; font-weight: 600; }

        /* ── Bordered tables ────────────────────────────────── */
        table.tbl { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.tbl th { background: #eef2f7; border: 1px solid #d1d5db; padding: 5px 7px; text-align: left; font-size: 10px; font-weight: 700; color: #374151; }
        table.tbl td { border: 1px solid #d1d5db; padding: 5px 7px; vertical-align: top; }
        .row-even td { background: #fafbfc; }

        /* ── Emphasis ───────────────────────────────────────── */
        .yes { color: #15803d; font-weight: 700; }
        .no { color: #9ca3af; }
        .muted { color: #6b7280; }
        .mono { font-family: "Courier New", monospace; font-size: 9.5px; color: #6b7280; }

        /* ── Risk badge ─────────────────────────────────────── */
        .badge { display: inline-block; padding: 6px 14px; font-size: 14px; font-weight: 800; letter-spacing: 1px; }
        .badge-high { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
        .badge-low { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
        .badge-incomplete { background: #fef3c7; color: #b45309; border: 1px solid #fcd34d; }
        .badge-other { background: #eef2f7; color: #374151; border: 1px solid #d1d5db; }

        /* ── Lists / cards ──────────────────────────────────── */
        ul.factor-list { margin: 4px 0 4px 16px; padding: 0; }
        ul.factor-list li { margin-bottom: 2px; }
        .factor-card { border: 1px solid #e5e7eb; padding: 6px 8px; margin-bottom: 6px; page-break-inside: avoid; }
        .factor-card .head { font-weight: 700; }
        .block { page-break-inside: avoid; }

        /* ── Disclaimer ─────────────────────────────────────── */
        .disclaimer { border: 1px solid #fcd34d; background: #fffbeb; padding: 10px 12px; font-size: 10px; color: #92400e; line-height: 1.5; page-break-inside: avoid; }
    </style>
</head>
<body>
@php
    // ---------- Formatting helpers ----------
    $dash = fn ($v) => ($v === null || $v === '') ? '—' : $v;
    $date = function ($v) use ($dash) {
        if ($v === null || $v === '') { return '—'; }
        try { return \Carbon\Carbon::parse($v)->format('M d, Y'); } catch (\Throwable $e) { return $dash($v); }
    };
    $yesno = fn ($v) => $v ? 'Yes' : 'No';
    $fullName = trim($patient->first_name . ' ' . ($patient->middle_name ? $patient->middle_name . ' ' : '') . $patient->last_name);
    $generatedDate = now()->format('M d, Y');

    $history = $patient->medicalHistory;
    $birthPlan = $patient->birthPlan;
    $ultrasounds = $patient->ultrasounds->sortByDesc('scan_date')->values();

    // Risk / decision labels
    $rl = $latestVisit?->risk_level;
    $ds = $latestVisit?->decision_source;
    $riskLabel = match ($rl) {
        'HIGH' => 'HIGH',
        'LOW' => 'LOW',
        'ASSESSMENT INCOMPLETE' => 'ASSESSMENT INCOMPLETE',
        default => $rl,
    };
    $riskBadgeClass = match ($rl) {
        'HIGH' => 'badge-high',
        'LOW' => 'badge-low',
        'ASSESSMENT INCOMPLETE' => 'badge-incomplete',
        default => 'badge-other',
    };
    $dsLabel = match ($ds) {
        'COMPLETENESS' => 'Completeness Check',
        'RULE_BASED' => 'Clinical Rules',
        'MACHINE_LEARNING' => 'Machine Learning',
        'MACHINE_LEARNING_INVALID' => 'ML Assessment Unavailable',
        null => 'Legacy Assessment',
        default => $ds,
    };
    $urgencyLabels = [
        'URGENT_CLINICAL_REVIEW' => 'Urgent Clinical Review',
        'PROMPT' => 'Prompt Clinical Review',
    ];
    $urgencyDisplay = $latestVisit?->urgency ? ($urgencyLabels[$latestVisit->urgency] ?? $latestVisit->urgency) : null;

    // Plain-language identified risk factors (mirrors the patient profile).
    $identifiedFactors = array_values(array_unique(array_merge(
        \App\Support\ListNormalizer::normalize($latestVisit?->rule_reasons),
        \App\Support\ListNormalizer::normalize($latestVisit?->risk_reasons)
    )));
    $bpArray = is_array($latestVisit?->bp_assessment) ? $latestVisit->bp_assessment : [];
    if (($bpArray['reason_code'] ?? null) === 'BP-URG' && !empty($bpArray['label']) && !in_array($bpArray['label'], $identifiedFactors, true)) {
        array_unshift($identifiedFactors, $bpArray['label']);
    }

    // Follow-up / overdue status
    if (!$latestVisit) {
        $nextStatus = 'N/A';
    } elseif ($patient->status === 'DELIVERED') {
        $nextStatus = 'Delivered';
    } elseif ($patient->status === 'REFERRED') {
        $nextStatus = 'Referred';
    } elseif ($latestVisit->next_visit_date && \Carbon\Carbon::parse($latestVisit->next_visit_date)->isPast()) {
        $nextStatus = 'Overdue';
    } elseif ($latestVisit->next_visit_date) {
        $nextStatus = 'On schedule';
    } else {
        $nextStatus = 'Not scheduled';
    }

    // Decision path narrative
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

    // Structured explainability data
    $structuredFactors = \App\ValueObjects\ClinicalFactorEvidence::normalizeList($latestVisit?->factor_evidence);
    $factorSourceLabels = [
        'MATERNAL_DEMOGRAPHICS' => 'Maternal demographics',
        'VITAL_SIGNS' => 'Vital signs',
        'CURRENT_CONDITION' => 'Current condition',
        'OBSTETRIC_HISTORY' => 'Obstetric history',
        'ULTRASOUND' => 'Ultrasound finding',
    ];
    $assessmentMetadata = is_array($latestVisit?->assessment_metadata) ? $latestVisit->assessment_metadata : [];
    $metadataContext = is_array($assessmentMetadata['context'] ?? null) ? $assessmentMetadata['context'] : [];
    $metadataFlags = is_array($assessmentMetadata['data_quality_flags'] ?? null) ? $assessmentMetadata['data_quality_flags'] : [];
    $metadataTrace = is_array($assessmentMetadata['decision_trace'] ?? null) ? $assessmentMetadata['decision_trace'] : [];
    $structuredInteractions = \App\ValueObjects\ClinicalInteractionEvidence::normalizeList($assessmentMetadata['interaction_evidence'] ?? $latestVisit->interaction_evidence ?? []);
    $interactionContextLabels = [
        'ultrasound_inputs.amniotic_fluid' => 'Amniotic fluid',
        'ultrasound_inputs.presentation' => 'Fetal presentation',
    ];

    // Medical history conditions, positives first for visibility
    $historyConditions = [
        'Epilepsy' => $history?->epilepsy,
        'Severe Headache' => $history?->severe_headache,
        'Visual Disturbance' => $history?->visual_disturbance,
        'Chest Pain' => $history?->chest_pain,
        'Shortness of Breath' => $history?->shortness_breath,
        'Breast Mass' => $history?->breast_mass,
        'Liver Disease' => $history?->liver_disease,
        'Smoking' => $history?->smoking,
        'Allergies' => $history?->allergies,
        'Drug Intake' => $history?->drug_intake,
        'STD History' => $history?->std_history,
        'Diabetes' => $history?->diabetes,
        'Hypertension' => $history?->hypertension,
        'Asthma' => $history?->asthma,
        'Thyroid Disease' => $history?->thyroid_disease,
        'Heart Disease' => $history?->heart_disease,
        'Anemia' => $history?->anemia,
        'Mental Health Condition' => $history?->mental_health_condition,
    ];
    $yesConditions = [];
    $noConditions = [];
    foreach ($historyConditions as $label => $value) {
        if ($value) { $yesConditions[$label] = true; } else { $noConditions[$label] = false; }
    }
    $sortedHistory = $yesConditions + $noConditions;
    $historyPairs = array_chunk(array_keys($sortedHistory), 2);
@endphp

    <div class="header">
        <div class="brand">DEPLA FAMILY CARE MATERNITY &amp; LYING-IN</div>
        <div class="brand-address">901 PARADA STA. MARIA BULACAN</div>
        <div class="doc-title">PATIENT RECORD</div>
        <div class="header-meta">
            Patient: <strong>{{ $fullName }}</strong> &nbsp;·&nbsp; Patient ID: {{ $patient->id }} &nbsp;·&nbsp; Generated: {{ $generatedDate }}
        </div>
    </div>

    <!-- ═══════════════ 1. PATIENT INFORMATION ═══════════════ -->
    <div class="section keep-together">
        <div class="section-title">1. Patient Information</div>
        <table class="data-table">
            <tr>
                <td class="lbl">Full Name</td><td>{{ $fullName }}</td>
                <td class="lbl">Civil Status</td><td>{{ $dash($patient->civil_status) }}</td>
            </tr>
            <tr>
                <td class="lbl">Age</td><td>{{ $dash($patient->age) }}</td>
                <td class="lbl">PhilHealth Member</td><td>{{ $yesno($patient->philhealth_member) }}</td>
            </tr>
            <tr>
                <td class="lbl">Birthdate</td><td>{{ $date($patient->birthdate) }}</td>
                <td class="lbl">PhilHealth Number</td><td>{{ $patient->philhealth_number ?: 'N/A' }}</td>
            </tr>
            <tr>
                <td class="lbl">Address</td><td colspan="3">{{ $dash($patient->address) }}</td>
            </tr>
            <tr>
                <td class="lbl">Contact Number</td><td colspan="3">{{ $dash($patient->contact_number) }}</td>
            </tr>
        </table>
    </div>

    <!-- ═══════════════ 2. CURRENT PREGNANCY ═══════════════ -->
    <div class="section keep-together">
        <div class="section-title">2. Current Pregnancy</div>
        <table class="data-table">
            <tr>
                <td class="lbl">Gravida (G)</td><td>{{ $dash($patient->gravida) }}</td>
                <td class="lbl">Para (P)</td><td>{{ $dash($patient->para) }}</td>
            </tr>
            <tr>
                <td class="lbl">LMP</td><td>{{ $date($patient->lmp) }}</td>
                <td class="lbl">EDD</td><td>{{ $date($patient->edd) }}</td>
            </tr>
            <tr>
                <td class="lbl">Pregnancy Status</td><td>{{ $patient->status === 'DELIVERED' ? 'Delivered' : 'Ongoing' }}</td>
                <td class="lbl">Delivery Date</td><td>{{ $patient->delivery_date ? $date($patient->delivery_date) : 'N/A' }}</td>
            </tr>
        </table>
    </div>

    <!-- ═══════════════ 3. LATEST PRENATAL VISIT ═══════════════ -->
    <div class="section keep-together">
        <div class="section-title">3. Latest Prenatal Visit</div>
        @if($latestVisit)
        <table class="data-table">
            <tr>
                <td class="lbl">Visit Date</td><td>{{ $date($latestVisit->visit_date) }}</td>
                <td class="lbl">Gestational Age</td><td>{{ $latestVisit->gestational_age ? $latestVisit->gestational_age . ' wks' : '—' }}</td>
            </tr>
            <tr>
                <td class="lbl">Blood Pressure</td><td>{{ ($latestVisit->bp_sys && $latestVisit->bp_dia) ? $latestVisit->bp_sys . '/' . $latestVisit->bp_dia : '—' }}</td>
                <td class="lbl">Weight</td><td>{{ $latestVisit->weight ? $latestVisit->weight . ' kg' : '—' }}</td>
            </tr>
            <tr>
                <td class="lbl">Temperature</td><td colspan="3">{{ $latestVisit->temperature ? $latestVisit->temperature . ' °C' : '—' }}</td>
            </tr>
        </table>
        @else
        <p class="muted">No prenatal visits recorded.</p>
        @endif
    </div>

    <!-- ═══════════════ 4. SUPPORTING RECORDS SUMMARY ═══════════════ -->
    <div class="section">
        <div class="section-title">4. Supporting Records Summary</div>

        @if($history)
        <div class="block">
            <div class="subsection-title">Medical History</div>
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Condition</th><th>Status</th>
                        <th>Condition</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($historyPairs as $pair)
                    <tr>
                        @foreach($pair as $condition)
                            <td>{{ $condition }}</td>
                            <td class="{{ $sortedHistory[$condition] ? 'yes' : 'no' }}">{{ $sortedHistory[$condition] ? 'Yes' : 'No' }}</td>
                        @endforeach
                        @if(count($pair) === 1)
                            <td>&nbsp;</td><td>&nbsp;</td>
                        @endif
                    </tr>
                    @endforeach
                    @if($history->other_specify)
                    <tr>
                        <td>Other</td>
                        <td class="yes">{{ $history->other_specify }}</td>
                        <td>&nbsp;</td><td>&nbsp;</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
        @else
        <div class="block">
            <div class="subsection-title">Medical History</div>
            <p class="muted">No medical history recorded.</p>
        </div>
        @endif

        @if($ultrasounds->isNotEmpty())
        <div class="block">
            <div class="subsection-title">Ultrasound Summary</div>
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Scan Date</th><th>GA</th><th>Presentation</th><th>Amniotic Fluid</th><th>Heartbeat</th><th>Weight</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ultrasounds as $index => $us)
                    <tr class="{{ $index % 2 === 1 ? 'row-even' : '' }}">
                        <td>{{ $date($us->scan_date) }}</td>
                        <td>{{ $dash($us->gestational_age_scan) }}</td>
                        <td>{{ $dash($us->presentation) }}</td>
                        <td>{{ $dash($us->amniotic_fluid) }}</td>
                        <td>{{ $dash($us->fetal_heartbeat) }}</td>
                        <td>{{ $dash($us->estimated_fetal_weight) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if($birthPlan)
        <div class="block">
            <div class="subsection-title">Birth Plan Summary</div>
            <table class="data-table">
                @php
                    $bpRows = [
                        'Delivery Location' => $birthPlan->delivery_location,
                        'Deliver in Clinic' => $birthPlan->deliver_in_clinic !== null ? $yesno($birthPlan->deliver_in_clinic) : null,
                        'Planned Visits' => $birthPlan->planned_visits,
                        'Transportation' => $birthPlan->transportation,
                        'Payment Method' => $birthPlan->payment_method,
                        'Birth Companion' => $birthPlan->birth_companion !== null ? $yesno($birthPlan->birth_companion) : null,
                        'Caregiver at Home' => $birthPlan->caregiver_home !== null ? $yesno($birthPlan->caregiver_home) : null,
                        'Saving Started' => $birthPlan->saving_started !== null ? $yesno($birthPlan->saving_started) : null,
                        'Plans More Children' => $birthPlan->plan_more_children !== null ? $yesno($birthPlan->plan_more_children) : null,
                        'Family Planning Method' => $birthPlan->family_planning_method,
                    ];
                    $bpPairs = [];
                    foreach ($bpRows as $label => $value) {
                        if ($value !== null && $value !== '') {
                            $bpPairs[] = [$label, $value];
                        }
                    }
                    $bpChunks = array_chunk($bpPairs, 2);
                @endphp
                @foreach($bpChunks as $chunk)
                <tr>
                    @foreach($chunk as $pair)
                        <td class="lbl">{{ $pair[0] }}</td>
                        <td>{{ $pair[1] }}</td>
                    @endforeach
                    @if(count($chunk) === 1)
                        <td class="lbl">&nbsp;</td><td>&nbsp;</td>
                    @endif
                </tr>
                @endforeach
                @if(empty($bpChunks))
                <tr><td class="lbl">Notes</td><td>{{ $dash($birthPlan->notes) }}</td></tr>
                @endif
            </table>
        </div>
        @endif
    </div>

    <!-- ═══════════════ 5. RISK ASSESSMENT SUMMARY ═══════════════ -->
    <div class="section keep-together">
        <div class="section-title">5. Risk Assessment Summary</div>
        @if($latestVisit)
        <div class="block" style="margin-bottom:8px;">
            <span class="badge {{ $riskBadgeClass }}">{{ $dash($riskLabel) }}</span>
        </div>
        <table class="data-table">
            @if($urgencyDisplay)
            <tr>
                <td class="lbl">Urgency</td><td><strong>{{ $urgencyDisplay }}</strong></td>
            </tr>
            @endif
            <tr>
                <td class="lbl">Decision Source</td><td>{{ $dsLabel }}</td>
            </tr>
            <tr>
                <td class="lbl">Identified Risk Factors</td>
                <td>
                    @if(!empty($identifiedFactors))
                    <ul class="factor-list">
                        @foreach($identifiedFactors as $factor)
                        <li>{{ $factor }}</li>
                        @endforeach
                    </ul>
                    @else
                    <span class="muted">None identified.</span>
                    @endif
                </td>
            </tr>
            @if($latestVisit->bp_assessment)
            <tr>
                <td class="lbl">Blood Pressure Assessment</td>
                <td>
                    @if($latestVisit->bp_sys && $latestVisit->bp_dia)
                        Reading: {{ $latestVisit->bp_sys }}/{{ $latestVisit->bp_dia }}
                        @if($latestVisit->repeat_bp_sys && $latestVisit->repeat_bp_dia)
                            &nbsp;·&nbsp; Repeat: {{ $latestVisit->repeat_bp_sys }}/{{ $latestVisit->repeat_bp_dia }}
                        @endif
                    @else
                        Reading: — 
                    @endif
                    @if(!empty($bpArray['label']))<br>Classification: {{ $bpArray['label'] }}@endif
                    @if(!empty($bpArray['interpretation']))<br>{{ $bpArray['interpretation'] }}@endif
                    @if(!empty($bpArray['action']))<br><strong>Action:</strong> {{ $bpArray['action'] }}@endif
                    @if($latestVisit->bp_verification_status)
                    <br><span class="muted">Verification: {{ str_replace('_', ' ', $latestVisit->bp_verification_status) }}</span>
                    @endif
                </td>
            </tr>
            @endif
            <tr>
                <td class="lbl">Clinical Assessment</td><td>{{ $dash($latestVisit->assessment) }}</td>
            </tr>
            <tr>
                <td class="lbl">Recommended Action</td><td>{{ $dash($latestVisit->recommendation) }}</td>
            </tr>
            <tr>
                <td class="lbl">Recommended Follow-up</td><td>{{ $latestVisit->next_visit_date ? $date($latestVisit->next_visit_date) : 'Not scheduled' }}</td>
            </tr>
            @if($nextStatus !== 'N/A')
            <tr>
                <td class="lbl">Next Visit Status</td><td>{{ $nextStatus }}</td>
            </tr>
            @endif
        </table>
        @else
        <p class="muted">No assessment data available.</p>
        @endif
    </div>

    <!-- ═══════════════ 6. DETAILED ASSESSMENT EXPLANATION ═══════════════ -->
    <div class="section">
        <div class="section-title">6. Clinical Decision Summary — Detailed Assessment Explanation</div>
        @if($latestVisit)
            @if(!empty($structuredFactors))
                <div class="subsection-title">Structured Clinical Factors</div>
                @foreach($structuredFactors as $index => $factor)
                    <div class="factor-card">
                        <div class="head">{{ $factor['label'] ?? '' }} <span class="mono">({{ $factor['code'] ?? '' }})</span></div>
                        <div class="factor-detail">
                            <strong>Source:</strong> {{ $factorSourceLabels[$factor['category'] ?? ''] ?? ($factor['category'] ?? '—') }}
                            &nbsp;·&nbsp; <strong>Observed:</strong> {{ \App\ValueObjects\ClinicalFactorEvidence::displayObserved($factor['observed_value'] ?? null) }}
                            @if(!empty($factor['threshold_or_rule']))
                                <br><strong>Rule / Threshold:</strong> {{ $factor['threshold_or_rule'] }}
                            @endif
                            @if(!empty($factor['explanation']))
                                <br>{{ $factor['explanation'] }}
                            @endif
                            @if(!empty($factor['suggested_action']))
                                <br><em>Action: {{ $factor['suggested_action'] }}</em>
                            @endif
                        </div>
                    </div>
                @endforeach
            @elseif($ds === 'RULE_BASED' && !empty($latestVisit->rule_reasons))
                <div class="block">
                    <div class="subsection-title">Triggered Clinical Rules</div>
                    <ul class="factor-list">
                        @foreach(\App\Support\ListNormalizer::normalize($latestVisit->rule_reasons) as $rule)
                            <li>{{ $rule }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!empty($structuredInteractions))
                <div class="block">
                    <div class="subsection-title">Clinical Interactions Identified</div>
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>Interaction</th><th>Code</th><th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($structuredInteractions as $index => $interaction)
                            @php
                                $observedLines = [];
                                foreach (($interaction['observed_context'] ?? []) as $path => $value) {
                                    if (isset($interactionContextLabels[$path]) && $value !== null && trim((string) $value) !== '') {
                                        $observedLines[] = $interactionContextLabels[$path] . ': ' . $value;
                                    }
                                }
                            @endphp
                            <tr class="{{ $index % 2 === 1 ? 'row-even' : '' }}">
                                <td style="font-weight:500;">{{ $interaction['label'] ?? '' }}</td>
                                <td class="mono">{{ $interaction['code'] ?? '' }}</td>
                                <td>
                                    @if(!empty($interaction['required_factor_codes']))<span class="mono">Factors: {{ implode(', ', $interaction['required_factor_codes']) }}</span><br>@endif
                                    @if(!empty($observedLines)){{ implode(' · ', $observedLines) }}<br>@endif
                                    {{ $interaction['explanation'] ?? '' }}
                                    @if(!empty($interaction['suggested_action']))<br><em>Action: {{ $interaction['suggested_action'] }}</em>@endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if($ds === 'COMPLETENESS' && !empty($latestVisit->missing_records))
                <div class="block">
                    <div class="subsection-title">Missing Required Records</div>
                    <ul class="factor-list">
                        @foreach(\App\Support\ListNormalizer::normalize($latestVisit->missing_records) as $record)
                            <li>{{ $record }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($ds === 'MACHINE_LEARNING' && $latestVisit->ml_prediction)
                <div class="block">
                    <div class="subsection-title">Machine-Learning Contribution</div>
                    <p style="margin:2px 0 6px 0;">Prediction: {{ $latestVisit->ml_prediction }} (Valid)</p>
                </div>
            @endif

            @if(!empty($metadataContext))
                <div class="block">
                    <div class="subsection-title">Assessment Context Used</div>
                    <table class="tbl">
                        <tbody>
                            <tr>
                                <td style="font-weight:500;">Ultrasound</td>
                                <td>{{ !empty($metadataContext['ultrasound_date']) ? $date($metadataContext['ultrasound_date']) : 'No ultrasound record' }}
                                    @if(!empty($metadataContext['ultrasound_inputs']))
                                        <br><span class="muted">presentation: {{ $metadataContext['ultrasound_inputs']['presentation'] ?? '—' }} · fluid: {{ $metadataContext['ultrasound_inputs']['amniotic_fluid'] ?? '—' }} · heartbeat: {{ $metadataContext['ultrasound_inputs']['fetal_heartbeat'] ?? '—' }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr class="row-even">
                                <td style="font-weight:500;">Medical History</td>
                                <td>{{ !empty($metadataContext['medical_history_exists']) ? 'Active record present' : 'No active record' }}</td>
                            </tr>
                            <tr>
                                <td style="font-weight:500;">Birth Plan</td>
                                <td>{{ !empty($metadataContext['birth_plan_exists']) ? 'Active record present' : 'No active record' }}</td>
                            </tr>
                            <tr class="row-even">
                                <td style="font-weight:500;">Assessment Date</td>
                                <td>{{ !empty($metadataContext['assessment_date']) ? $date($metadataContext['assessment_date']) : '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif

            @if(!empty($metadataFlags))
                <div class="block">
                    <div class="subsection-title">Data Requiring Verification</div>
                    <table class="tbl">
                        <tbody>
                            @foreach($metadataFlags as $index => $flag)
                            <tr class="{{ $index % 2 === 1 ? 'row-even' : '' }}">
                                <td style="font-weight:500;">{{ $flag['label'] ?? '' }} ({{ $flag['severity'] ?? 'INFO' }})</td>
                                <td>
                                    {{ $flag['explanation'] ?? '' }}
                                    @if(!empty($flag['suggested_verification']))<br><em>Suggested verification: {{ $flag['suggested_verification'] }}</em>@endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if(!empty($metadataTrace))
                <div class="block">
                    <div class="subsection-title">Assessment Decision Path</div>
                    <table class="tbl">
                        <tbody>
                            @foreach($metadataTrace as $index => $step)
                            <tr class="{{ $index % 2 === 1 ? 'row-even' : '' }}">
                                <td class="mono" style="font-weight:500;">{{ $step['step_code'] ?? '' }}</td>
                                <td>
                                    <strong>{{ $step['status'] ?? '' }}</strong>
                                    @if(!empty($step['summary']))<br><em>{{ $step['summary'] }}</em>@endif
                                    @if(!empty($step['related_factor_codes']))<br><span class="mono">Factors: {{ implode(', ', $step['related_factor_codes']) }}</span>@endif
                                    @if(!empty($step['related_interaction_codes']))<br><span class="mono" style="color:#6d28d9;">Interactions: {{ implode(', ', $step['related_interaction_codes']) }}</span>@endif
                                    @if(!empty($step['missing_records']))<br><span style="color:#b45309;">Missing: {{ implode(', ', $step['missing_records']) }}</span>@endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="block">
                <div class="subsection-title">Decision Path</div>
                <p style="margin:2px 0 6px 0;line-height:1.5;">{{ $decisionPath }}</p>
            </div>
        @else
            <p class="muted">No assessment data available.</p>
        @endif
    </div>

    @if($patient->status === 'DELIVERED' && $patient->babies->count() > 0)
    <div class="section">
        <div class="section-title">7. Baby Information</div>
        @foreach($patient->babies as $index => $baby)
            <div class="block" style="border:1px solid #e9d5ff;padding:10px 12px;margin-bottom:10px;">
                <div style="font-weight:700;margin-bottom:6px;color:#7c3aed;">Baby {{ $index + 1 }}: {{ $baby->full_name }}</div>
                <table class="data-table">
                    <tr>
                        <td class="lbl">Sex</td><td>{{ $dash($baby->sex) }}</td>
                        <td class="lbl">Date of Birth</td><td>{{ $baby->date_of_birth ? $date($baby->date_of_birth) : 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Time of Birth</td><td>{{ $baby->time_of_birth ? \Carbon\Carbon::parse($baby->time_of_birth)->format('g:i A') : 'N/A' }}</td>
                        <td class="lbl">Birth Weight</td><td>{{ $baby->birth_weight ? $baby->birth_weight . ' kg' : 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td class="lbl">Birth Length</td><td colspan="3">{{ $baby->birth_length ? $baby->birth_length . ' cm' : 'N/A' }}</td>
                    </tr>
                </table>
            </div>
        @endforeach
    </div>
    @endif

    <div class="block" style="margin-top:16px;">
        <div class="disclaimer">
            <strong>Safety Disclaimer:</strong> This system-generated assessment is intended to support clinical decision-making and is not a medical diagnosis. Final clinical judgment remains with qualified clinic personnel.
        </div>
    </div>
</body>
</html>
