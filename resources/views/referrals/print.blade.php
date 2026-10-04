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
            width: 100%; max-width: 1100px;
            height: auto;
            margin: 0 auto;
            padding: 28px 32px;
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

        .print-container, .referral-history { min-width: 0; overflow-wrap: anywhere; }
        .print-container { border: 1px solid #e2e8f0; border-radius: 12px; }
        .print-container * { min-width: 0; max-width: 100%; }
        .clinic-header { display: flex; align-items: center; gap: 14px; text-align: left; border-bottom: 1px solid #e2e8f0; margin-bottom: 18px; padding-bottom: 16px; }
        .clinic-logo { width: 60px; height: 60px; object-fit: contain; flex-shrink: 0; }
        .clinic-header h1 { font-size: 20px; letter-spacing: 0; }
        .clinic-subtitle { letter-spacing: .12em; }
        .letter-title { color: #19355f; margin: 18px 0; }
        .patient-info, .signature-area, .evidence-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .evidence-grid { display: grid; gap: 8px 20px; margin-bottom: 12px; }
        .info-item { margin-bottom: 0; }
        .content-section { line-height: 1.6; }
        .section-label, .info-label { color: #19355f; }
        .section-value { margin-left: 0; }
        .toolbar, .referral-history { max-width: 1100px; margin: 0 auto 18px; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; }
        .toolbar a, .print-button { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; margin: 0; padding: 10px 18px; border-radius: 8px; font-size: 14px; text-decoration: none; }
        .toolbar a { color: #19355f; background: white; border: 1px solid #e2e8f0; }
        .print-button { background: #55b85a; }
        .print-button:hover { background: #4aa04c; }
        .referral-history { margin-top: 24px; }
        .history-entry { margin-top: 12px; border: 1px solid #e2e8f0; border-radius: 10px; background: white; }
        .history-entry summary { cursor: pointer; padding: 14px 16px; overflow-wrap: anywhere; }
        .history-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px 16px; margin-top: 8px; font-size: 13px; }
        .history-entry .print-container { border: 0; box-shadow: none; }
        .history-entry .history-print { padding: 0 16px 16px; }
        @media screen {
            .referral-letter.print-selected::before { content: 'Selected for printing'; display: block; margin-bottom: 12px; color: #367e4b; font-size: 12px; font-weight: 600; }
        }
        .section-label { break-after: avoid; page-break-after: avoid; }
        @media screen and (max-width: 639px) {
            body { padding: 16px 12px; }
            .print-container { padding: 20px 16px; }
            .clinic-header { align-items: flex-start; gap: 10px; }
            .clinic-header h1 { font-size: 18px; }
            .patient-info, .signature-area, .evidence-grid, .history-meta { grid-template-columns: minmax(0, 1fr); }
            .signature-area { gap: 24px; margin-top: 30px; }
        }
        @media print {
            .no-print, .toolbar, .history-entry summary, .history-print, .referral-history > h2 { display: none !important; }
            .referral-letter:not(.print-selected), .history-entry:not(.print-selected-entry) { display: none !important; }
            .referral-history { margin: 0; max-width: none; }
            .history-entry { margin: 0; border: 0; }
            .print-container { max-width: none; border: 0; border-radius: 0; }
            .print-selected-entry { display: block; }
            .content-section, .info-item, .signature-area { page-break-inside: avoid; }
        }
    </style>
</head>

<body>
    <nav class="toolbar no-print" aria-label="Referral letter actions">
        <a href="{{ route('referrals.show', $referral->id) }}">Back to Referral</a>
        <button class="print-button" type="button" onclick="window.print()">Print Selected Referral Letter</button>
    </nav>
    <article id="referral-letter-{{ $referralHistory->first()->id }}" class="referral-letter print-container print-selected">
        @include('referrals.print-content', ['referral' => $referralHistory->first()])
    </article>
    @if($referralHistory->count() > 1)
    <section class="referral-history" aria-label="Older referral records">
        <h2 class="no-print">Older Referral Records</h2>
        @foreach($referralHistory->skip(1) as $historicalReferral)
        <details class="history-entry" data-referral-id="{{ $historicalReferral->id }}">
            <summary>
                <strong>Referral Date: {{ $historicalReferral->referral_date?->format('M d, Y') ?? 'Not recorded' }} · Referral ID: {{ $historicalReferral->id }}</strong>
                <span class="history-meta">
                    <span>Referral Destination: {{ $historicalReferral->referred_to }}</span>
                    <span>Referral Source: {{ $historicalReferral->prenatal_visit_id && is_array($historicalReferral->assessment_snapshot) && count($historicalReferral->assessment_snapshot) > 0 ? 'Prenatal Visit Referral' : 'Manual Referral' }}</span>
                    <span>Status: {{ $historicalReferral->status }}</span>
                </span>
            </summary>
            <article id="referral-letter-{{ $historicalReferral->id }}" class="referral-letter print-container">
                @include('referrals.print-content', ['referral' => $historicalReferral])
            </article>
            <div class="history-print no-print">
                <button type="button" class="print-button" onclick="printReferral({{ $historicalReferral->id }})">Print This Referral Letter</button>
            </div>
        </details>
        @endforeach
    </section>
    @endif
    <script>
        const latestReferralId = @json($referralHistory->first()->id);
        function selectReferral(id) {
            document.querySelectorAll('.referral-letter').forEach(letter => letter.classList.remove('print-selected'));
            document.querySelectorAll('.history-entry').forEach(entry => entry.classList.remove('print-selected-entry'));
            const letter = document.getElementById('referral-letter-' + id);
            letter.classList.add('print-selected');
            const entry = letter.closest('details');
            if (entry) entry.classList.add('print-selected-entry');
        }
        function printReferral(id) { selectReferral(id); window.print(); }
        document.querySelectorAll('.history-entry').forEach(entry => {
            entry.addEventListener('toggle', () => {
                if (entry.open) selectReferral(entry.dataset.referralId);
                else if (entry.classList.contains('print-selected-entry')) selectReferral(latestReferralId);
            });
        });
    </script>
</body>
</html>
