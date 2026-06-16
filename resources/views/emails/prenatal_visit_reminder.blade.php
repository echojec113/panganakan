<p>Good day {{ $patient->first_name }},</p>

<p>This is a reminder from the clinic regarding your prenatal care record.</p>

@if($visit->next_visit_date)
<p>
Your recommended follow-up visit is on:
<strong>{{ \Carbon\Carbon::parse($visit->next_visit_date)->format('F d, Y') }}</strong>.
</p>
@endif

@if($visit->risk_level === 'HIGH')
<p>
Based on your latest prenatal record, the clinic recommends that you attend your follow-up visit for proper assessment by healthcare personnel.
</p>
@else
<p>
Please continue your regular prenatal checkups as scheduled.
</p>
@endif

<p>
This message is system-generated and is not a medical diagnosis. For questions or concerns, please contact or visit the clinic directly.
</p>

<p>Thank you.</p>