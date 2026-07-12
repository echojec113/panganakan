<p>Good day {{ $patient->first_name }},</p>

@if($type === 'tomorrow')
<p>
This is a reminder that your prenatal follow-up visit is scheduled for
<strong>tomorrow, {{ \Carbon\Carbon::parse($visit->next_visit_date)->format('F d, Y') }}</strong>.
</p>
@else
<p>
This is a reminder that your prenatal follow-up visit is scheduled for
<strong>today, {{ \Carbon\Carbon::parse($visit->next_visit_date)->format('F d, Y') }}</strong>.
</p>
@endif

<p>
Please visit {{ config('mail.from.name') }} at your scheduled time.
</p>

<p>
If you have any questions or concerns, please contact the clinic directly.
</p>

<p>Thank you.</p>
