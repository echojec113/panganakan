<p>Good day {{ $patient->first_name }},</p>

<p>Your prenatal follow-up schedule has been updated.</p>

@if($visit->next_visit_date)
<p>
Your new scheduled visit date is:
<strong>{{ \Carbon\Carbon::parse($visit->next_visit_date)->format('F d, Y') }}</strong>.
</p>
@endif

<p>
Please visit {{ config('mail.from.name') }} on your scheduled date for your prenatal checkup.
</p>

<p>
If you have any questions or concerns, please contact the clinic directly.
</p>

<p>Thank you.</p>
