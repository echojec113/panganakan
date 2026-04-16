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
            body {
                background: white;
                padding: 0;
            }

            .print-container {
                width: 100%;
                height: 100%;
                box-shadow: none;
                padding: 40px 50px;
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
            <strong>Date:</strong> {{ \Carbon\Carbon::now()->format('F d, Y') }}
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
                    <div class="info-text">{{ $referral->patient->lmp->format('F d, Y') }}</div>
                </div>

                <div class="info-item">
                    <div class="info-label">Expected Delivery Date (EDD):</div>
                    <div class="info-text">{{ $referral->patient->edd->format('F d, Y') }}</div>
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
            </div>
        </div>

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
    <div style="text-align: center; margin-top: 20px; no-print;" class="no-print">
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
