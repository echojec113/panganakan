<p>Good day,</p>

<p>This is a reminder about your confirmed {{ $appointment->type_label }} appointment.</p>

<p>
    Date: <strong>{{ $appointment->confirmed_date->format('F j, Y') }}</strong><br>
    Time: <strong>{{ \Carbon\Carbon::parse($appointment->confirmed_time, 'Asia/Manila')->format('g:i A') }}</strong>
</p>

<p>Please visit {{ config('mail.from.name') }} at your scheduled time.</p>

<p>If you have questions or need to change your appointment, please contact the clinic directly.</p>

<p>Thank you.</p>
