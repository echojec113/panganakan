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
            <table class="table">
                <thead>
                    <tr><th>Condition</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @php
                        $history = $patient->medicalHistory;
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
                        ];
                    @endphp
                    @foreach($conditions as $label => $value)
                        <tr>
                            <td>{{ $label }}</td>
                            <td class="check">{{ $value ? '✔ Yes' : '❌ No' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="section">
            <div class="section-title">4. Prenatal Visit (Latest Only)</div>
            @if($latestVisit)
                <div class="row"><div class="label">Visit Date</div><div class="value">{{ $latestVisit->visit_date }}</div></div>
                <div class="row"><div class="label">Blood Pressure</div><div class="value">{{ $latestVisit->bp_sys }}/{{ $latestVisit->bp_dia }}</div></div>
                <div class="row"><div class="label">Weight</div><div class="value">{{ $latestVisit->weight }} kg</div></div>
                <div class="row"><div class="label">Temperature</div><div class="value">{{ $latestVisit->temperature }} °C</div></div>
                <div class="row"><div class="label">Gestational Age</div><div class="value">{{ $latestVisit->gestational_age }}</div></div>
                <div class="row"><div class="label">Assessment</div><div class="value">{{ $latestVisit->assessment }}</div></div>
                <div class="row"><div class="label">Risk Level</div><div class="value">{{ $latestVisit->risk_level }}</div></div>
                <div class="row"><div class="label">Risk Factors</div><div class="value">{{ $latestVisit->risk_reasons ?: 'N/A' }}</div></div>
                <div class="row"><div class="label">Next Visit Date</div><div class="value">{{ $latestVisit->next_visit_date ?: 'N/A' }}</div></div>
            @else
                <p class="small">No prenatal visits recorded.</p>
            @endif
        </div>

        <div class="section">
            <div class="section-title">5. Risk Monitoring Summary</div>
            <div class="row"><div class="label">Current Risk Level</div><div class="value">{{ $riskSummary['currentRiskLevel'] }}</div></div>
            <div class="row"><div class="label">Identified Risk Factors</div><div class="value">{{ $riskSummary['identifiedRiskFactors'] }}</div></div>
            <div class="row"><div class="label">Overdue Status</div><div class="value">{{ $riskSummary['overdueStatus'] }}</div></div>
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