<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Letter - {{ $referral->patient->first_name }} {{ $referral->patient->last_name }}</title>

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

        .print-container {
            background: white;
            width: 8.5in;
            height: 11in;
            margin: 0 auto;
            padding: 50px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .clinic-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 20px;
        }

        .clinic-header h1 {
            font-size: 22px;
            font-weight: 700;
            color: #1e2d45;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .clinic-header p {
            font-size: 12px;
            color: #666;
            margin-bottom: 3px;
        }

        .letter-title {
            text-align: center;
            font-size: 16px;
            font-weight: 700;
            margin: 30px 0 20px;
            color: #2563eb;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .date-info {
            margin-bottom: 20px;
            text-align: right;
            font-size: 13px;
        }

        .content-section {
            margin-bottom: 20px;
            font-size: 13px;
            line-height: 1.8;
            break-inside: avoid;
        }

        .section-label {
            font-weight: 700;
            color: #1e2d45;
            margin-bottom: 5px;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }

        .section-value {
            margin-left: 10px;
            color: #333;
            margin-bottom: 10px;
        }

        .patient-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-label {
            font-weight: 600;
            color: #2563eb;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 3px;
        }

        .info-text {
            color: #333;
            font-size: 13px;
        }

        .reason-box {
            background: #eaf4fb;
            border-left: 4px solid #2563eb;
            padding: 12px 15px;
            margin: 15px 0;
            border-radius: 4px;
        }

        .signature-area {
            margin-top: 50px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            break-inside: avoid;
        }

        .signature-line {
            text-align: center;
        }

        .signature-blank {
            border-top: 1px solid #333;
            margin-bottom: 5px;
            height: 60px;
        }

        .signature-label {
            font-size: 11px;
            color: #666;
            font-weight: 600;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 11px;
            color: #666;
        }

        @media print {
            @page {
                size: letter;
                margin: 12mm;
            }

            body {
                background: white;
                padding: 0;
            }

            .print-container {
                width: 100%;
                height: auto;
                min-height: 100%;
                box-shadow: none;
                padding: 0;
            }

            .no-print {
                display: none;
            }
        }

        .print-button {
            display: block;
            margin: 20px auto;
            padding: 12px 24px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .print-button:hover {
            background: #1e40af;
        }
    </style>
</head>

<body>

    <div class="print-container">

        {{-- Clinic Header --}}
        <div class="clinic-header">
            <h1>DEPLA FAMILY CARE MATERNITY CLINIC</h1>
            <p>Professional Maternity & Prenatal Services</p>
            <p>Contact: {{ config('app.clinic_phone', '(555) 123-4567') }} | Address: {{ config('app.clinic_address', 'Maternity Clinic Building') }}</p>
        </div>

        {{-- Title --}}
        <div class="letter-title">PATIENT REFERRAL LETTER</div>

        {{-- Date --}}
        <div class="date-info">
            <strong>Print Date:</strong> {{ \Carbon\Carbon::now()->format('F d, Y') }}
        </div>

        {{-- TO Section --}}
        <div class="content-section">
            <div class="section-label">To:</div>
            <div class="section-value">
                <strong>{{ $referral->referred_to }}</strong><br>
                @if($referral->doctor_name)
                    <span>Dr. {{ $referral->doctor_name }}</span><br>
                @endif
            </div>
        </div>

        {{-- Patient Information --}}
        <div class="content-section">
            <div class="section-label">Patient Information</div>
            <div class="patient-info">
                <div class="info-item">
                    <div class="info-label">Name:</div>
                    <div class="info-text">
                        {{ $referral->patient->first_name }}
                        @if($referral->patient->middle_name)
                            {{ $referral->patient->middle_name }}
                        @endif
                        {{ $referral->patient->last_name }}
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Age:</div>
                    <div class="info-text">{{ $referral->patient->age }} years old</div>
                </div>

                <div class="info-item">
                    <div class="info-label">Address:</div>
                    <div class="info-text">{{ $referral->patient->address }}</div>
                </div>

                <div class="info-item">
                    <div class="info-label">Contact:</div>
                    <div class="info-text">{{ $referral->patient->contact_number }}</div>
                </div>

                <div class="info-item">
                    <div class="info-label">Civil Status:</div>
                    <div class="info-text">{{ $referral->patient->civil_status }}</div>
                </div>

                <div class="info-item">
                    <div class="info-label">PhilHealth:</div>
                    <div class="info-text">
                        @if($referral->patient->philhealth_member)
                            Yes ({{ $referral->patient->philhealth_number }})
                        @else
                            No
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Pregnancy Information --}}
        <div class="content-section">
            <div class="section-label">Pregnancy Information:</div>
            <div class="patient-info">
                <div class="info-item">
                    <div class="info-label">Gravida:</div>
                    <div class="info-text">{{ $referral->patient->gravida }}</div>
                </div>

                <div class="info-item">
                    <div class="info-label">Para:</div>
                    <div class="info-text">{{ $referral->patient->para }}</div>
                </div>

                <div class="info-item">
                    <div class="info-label">Last Menstrual Period (LMP):</div>
                    <div class="info-text">{{ $referral->patient->lmp ? $referral->patient->lmp->format('F d, Y') : '—' }}</div>
                </div>

                <div class="info-item">
                    <div class="info-label">Expected Delivery Date (EDD):</div>
                    <div class="info-text">{{ $referral->patient->edd ? $referral->patient->edd->format('F d, Y') : '—' }}</div>
                </div>
            </div>
        </div>

        {{-- Reason for Referral --}}
        <div class="content-section">
            <div class="section-label">Reason for Referral:</div>
            <div class="reason-box">
                {!! nl2br(e($referral->reason)) !!}
            </div>
        </div>

        {{-- Additional Notes --}}
        @if($referral->notes)
        <div class="content-section">
            <div class="section-label">Additional Notes:</div>
            <div class="section-value">
                {!! nl2br(e($referral->notes)) !!}
            </div>
        </div>
        @endif

        {{-- Referral Details --}}
        <div class="content-section">
            <div class="section-label">Referral Details:</div>
            <div class="patient-info">
                <div class="info-item">
                    <div class="info-label">Date of Referral:</div>
                    <div class="info-text">{{ $referral->referral_date->format('F d, Y') }}</div>
                </div>

                <div class="info-item">
                    <div class="info-label">Status:</div>
                    <div class="info-text">{{ $referral->status }}</div>
                </div>

                @if($referral->status === 'Pending')
                <div class="info-item">
                    <div class="info-label">Referral State:</div>
                    <div class="info-text">Pending Referral — awaiting follow-through.</div>
                </div>
                @endif

                @if($referral->status === 'Completed' && $referral->completed_at)
                <div class="info-item">
                    <div class="info-label">Completed On:</div>
                    <div class="info-text">{{ $referral->completed_at->format('F d, Y g:i A') }}</div>
                </div>
                @endif

                @if($referral->status === 'Refused' && $referral->refusal_recorded_at)
                <div class="info-item">
                    <div class="info-label">Refusal Recorded On:</div>
                    <div class="info-text">{{ $referral->refusal_recorded_at->format('F d, Y g:i A') }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Refusal Recorded By:</div>
                    <div class="info-text">{{ $referral->refusalRecordedBy?->name ?? 'Staff account no longer available' }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Physical Waiver:</div>
                    <div class="info-text">{{ $referral->waiver_signed ? 'Signed / recorded' : 'Not signed' }}</div>
                </div>
                @endif

                <div class="info-item">
                    <div class="info-label">Source:</div>
                    <div class="info-text">
                        @if($referral->prenatal_visit_id && is_array($referral->assessment_snapshot) && count($referral->assessment_snapshot) > 0)
                            Assessment-linked
                        @else
                            Manual Referral
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Assessment Evidence at Referral (immutable snapshot, linked referrals only) --}}
        @if(is_array($referral->assessment_snapshot) && count($referral->assessment_snapshot) > 0)
        @php
            $snap = $referral->assessment_snapshot;
            $factorEvidence = is_array($snap['factor_evidence'] ?? null) ? $snap['factor_evidence'] : [];
            $interactionEvidence = is_array($snap['interaction_evidence'] ?? null) ? $snap['interaction_evidence'] : [];
            $bpAssessment = is_array($snap['bp_assessment'] ?? null) ? $snap['bp_assessment'] : [];
            $observedContextLabels = [
                'ultrasound_inputs.amniotic_fluid' => 'Amniotic fluid',
                'ultrasound_inputs.presentation' => 'Fetal presentation',
            ];
            $versions = is_array($snap['versions'] ?? null) ? $snap['versions'] : [];
        @endphp
        <div class="content-section">
            <div class="section-label">Assessment Evidence at Referral</div>
            <p style="font-size: 11px; color: #666; margin-bottom: 10px;">Recorded at referral creation from the stored assessment. Read-only; does not change.</p>

            @if(($snap['urgency'] ?? null) === 'URGENT_CLINICAL_REVIEW')
            <div style="border: 2px solid #dc2626; border-radius: 4px; padding: 10px 14px; background: #fff5f5; font-weight: 700; color: #dc2626; text-align: center; margin-bottom: 12px;">
                URGENT CLINICAL REVIEW
            </div>
            @endif

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px 20px; margin-bottom: 12px;">
                <div>
                    <div class="info-label">Risk Level</div>
                    <div class="info-text">{{ $snap['risk_level'] ?? '—' }}</div>
                </div>
                <div>
                    <div class="info-label">Assessment Date</div>
                    <div class="info-text">{{ $snap['assessment_date'] ?? '—' }}</div>
                </div>
                <div>
                    <div class="info-label">Visit Date</div>
                    <div class="info-text">{{ ($snap['visit_date'] ?? null) ? \Carbon\Carbon::parse($snap['visit_date'])->format('F d, Y') : '—' }}</div>
                </div>
                <div>
                    <div class="info-label">Decision Source</div>
                    <div class="info-text">{{ $snap['decision_source'] ?? 'Legacy' }}</div>
                </div>
            </div>

            @if(!empty($snap['assessment']))
            <div class="info-label" style="margin-top: 8px;">Clinical Assessment</div>
            <div class="section-value" style="margin-left: 0;">{{ $snap['assessment'] }}</div>
            @endif

            @if(!empty($snap['recommendation']))
            <div class="info-label" style="margin-top: 8px;">Recommendation</div>
            <div class="section-value" style="margin-left: 0;">{{ $snap['recommendation'] }}</div>
            @endif

            @if(!empty($bpAssessment))
            <div class="info-label" style="margin-top: 8px;">Blood Pressure Finding</div>
            <div class="section-value" style="margin-left: 0;">
                @if(($bpAssessment['reason_code'] ?? null) === 'BP-URG')
                    Urgent blood-pressure finding captured in this assessment.
                @elseif(!empty($bpAssessment['label']))
                    {{ $bpAssessment['label'] }}
                @else
                    Blood-pressure finding captured in this assessment.
                @endif
            </div>
            @endif

            @if(!empty($factorEvidence))
            <div class="info-label" style="margin-top: 8px;">Clinical Factors</div>
            <div class="section-value" style="margin-left: 0;">
                @foreach($factorEvidence as $factor)
                <span style="display: inline-block; border: 1px solid #ddd; border-radius: 4px; padding: 2px 8px; font-size: 11px; margin-right: 5px; margin-bottom: 5px;">
                    {{ $factor['label'] ?? $factor['code'] ?? 'Factor' }}
                </span>
                @endforeach
            </div>
            @endif

            @if(!empty($interactionEvidence))
            <div class="info-label" style="margin-top: 8px;">Clinical Interactions</div>
            <div class="section-value" style="margin-left: 0;">
                @foreach($interactionEvidence as $interaction)
                @php
                    $contextLines = collect();
                    foreach (($interaction['observed_context'] ?? []) as $path => $value) {
                        if (isset($observedContextLabels[$path]) && $value !== null && trim((string) $value) !== '') {
                            $contextLines->push($observedContextLabels[$path] . ': ' . $value);
                        }
                    }
                @endphp
                <div style="border: 1px solid #e0e7ff; border-radius: 6px; padding: 8px 10px; margin-bottom: 8px; background: #f5f3ff;">
                    <span style="font-weight: 700; color: #4c1d95;">{{ $interaction['label'] ?? 'Clinical interaction' }}</span>
                    @if($contextLines->isNotEmpty())
                    <div style="margin-top: 4px; font-size: 11px; color: #555;">
                        @foreach($contextLines as $line)<span style="margin-right: 6px;">{{ $line }}</span>@endforeach
                    </div>
                    @endif
                    @if(!empty($interaction['explanation']))
                    <div style="margin-top: 4px; font-size: 11px; color: #666;">{{ $interaction['explanation'] }}</div>
                    @endif
                    @if(!empty($interaction['suggested_action']))
                    <div style="margin-top: 4px; font-size: 11px; color: #4c1d95; font-weight: 600;">Suggested follow-through: {{ $interaction['suggested_action'] }}</div>
                    @endif
                </div>
                @endforeach
            </div>
            @endif

            @if(count($versions) > 0)
            <div style="font-size: 10px; color: #888; margin-top: 8px;">
                @if(!empty($versions['clinical_rules']))Clinical Rules Version: {{ $versions['clinical_rules'] }}@endif
                @if(!empty($versions['clinical_rules']) && !empty($versions['assessment_engine'])) &middot; @endif
                @if(!empty($versions['assessment_engine']))Assessment Engine Version: {{ $versions['assessment_engine'] }}@endif
            </div>
            @endif
        </div>
        @endif

        {{-- Signature Area --}}
        <div class="signature-area">
            <div class="signature-line">
                <div class="signature-blank"></div>
                <div class="signature-label">Prepared By (Signature & Date)</div>
            </div>

            <div class="signature-line">
                <div class="signature-blank"></div>
                <div class="signature-label">Clinic Seal / Stamp</div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="footer">
            <p>This is an official referral document from DEPLA FAMILY CARE MATERNITY CLINIC</p>
            <p style="margin-top: 5px;">Confidential - For Medical Use Only</p>
        </div>

    </div>

    {{-- Print Button --}}
    <div class="no-print" style="text-align: center; margin-top: 20px;">
        <button class="print-button" onclick="window.print()">Print Referral Letter</button>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                window.print();
            }, 500);
        });
    </script>

</body>
</html>
