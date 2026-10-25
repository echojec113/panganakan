<p>Good day,</p>

@if($appointment->status === \App\Models\Appointment::STATUS_CONFIRMED)
    <p>Your {{ $appointment->type_label }} appointment is confirmed.</p>
    <p>
        Date: <strong>{{ ($appointment->confirmed_date ?? $appointment->requested_date)->format('F j, Y') }}</strong>
        @if($appointment->confirmed_time)
            <br>Time: <strong>{{ \Carbon\Carbon::parse($appointment->confirmed_time, 'Asia/Manila')->format('g:i A') }}</strong>
        @endif
    </p>
@else
    <p>We received your {{ $appointment->type_label }} appointment request.</p>
    <p>Your requested schedule is:</p>
    <p>
        Date: <strong>{{ $appointment->requested_date->format('F j, Y') }}</strong>
        @if($appointment->requested_time)
            <br>Time: <strong>{{ \Carbon\Carbon::parse($appointment->requested_time, 'Asia/Manila')->format('g:i A') }}</strong>
        @endif
    </p>
    <p>This is a request and is not confirmed yet. The clinic will notify you after review.</p>
@endif

<p>Please contact {{ config('mail.from.name') }} if you have questions.</p>
<p>Thank you.</p>
