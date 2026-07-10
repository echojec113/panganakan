<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Baby Information</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; margin: 32px; }
        h1 { font-size: 22px; margin-bottom: 4px; }
        h2 { font-size: 16px; margin-top: 28px; border-bottom: 1px solid #e5e7eb; padding-bottom: 8px; }
        h3 { color: #db2777; margin-top: 20px; }
        .muted { color: #6b7280; font-size: 13px; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 12px; }
        .box { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; }
        .label { color: #2563eb; font-size: 12px; font-weight: bold; }
        .value { margin-top: 4px; font-weight: bold; }
        @media print { button { display: none; } body { margin: 18px; } }
    </style>
</head>
<body>
    <button onclick="window.print()" style="float:right;padding:8px 14px;">Print</button>
    <h1>Baby Information</h1>
    <div class="muted">{{ $patient->first_name }} {{ $patient->middle_name ? $patient->middle_name . ' ' : '' }}{{ $patient->last_name }}</div>

    @foreach($pregnancies as $pregnancy)
        @php($latestVisit = $pregnancy->prenatalVisits->sortByDesc('visit_date')->first())
        <h2>Pregnancy Delivered {{ $pregnancy->delivery_date ? \Carbon\Carbon::parse($pregnancy->delivery_date)->format('M d, Y') : 'N/A' }}</h2>
        <div class="grid">
            <div class="box"><div class="label">Delivery Type</div><div class="value">{{ $pregnancy->delivery_type ?? 'Normal Delivery' }}</div></div>
            <div class="box"><div class="label">Number of Babies</div><div class="value">{{ $pregnancy->babies->count() }}</div></div>
            <div class="box"><div class="label">Risk Level</div><div class="value">{{ $latestVisit?->risk_level ?: 'N/A' }}</div></div>
        </div>

        @foreach($pregnancy->babies as $baby)
            <h3>Baby {{ $loop->iteration }}</h3>
            <div class="grid">
                <div class="box"><div class="label">Full Name</div><div class="value">{{ $baby->full_name ?: 'N/A' }}</div></div>
                <div class="box"><div class="label">Sex</div><div class="value">{{ $baby->sex ?: 'N/A' }}</div></div>
                <div class="box"><div class="label">Birth Weight</div><div class="value">{{ $baby->birth_weight ? $baby->birth_weight . ' kg' : 'N/A' }}</div></div>
                <div class="box"><div class="label">Birth Length</div><div class="value">{{ $baby->birth_length ? $baby->birth_length . ' cm' : 'N/A' }}</div></div>
                <div class="box"><div class="label">Date of Birth</div><div class="value">{{ $baby->date_of_birth ? \Carbon\Carbon::parse($baby->date_of_birth)->format('M d, Y') : 'N/A' }}</div></div>
                <div class="box"><div class="label">Time of Birth</div><div class="value">{{ $baby->time_of_birth ? \Carbon\Carbon::parse($baby->time_of_birth)->format('h:i A') : 'N/A' }}</div></div>
            </div>
        @endforeach
    @endforeach
</body>
</html>
