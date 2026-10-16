<p>Good day{{ isset($user->first_name) && $user->first_name !== '' ? ' ' . $user->first_name : '' }},</p>

<p>Welcome to DEPLA Family Care Maternity &amp; Lying-In Clinic.</p>

<p>A Patient Portal account has been created for you.</p>

<p>To choose your own password, open the secure setup link below:</p>

<p>
    <a href="{{ $setupUrl }}">Set up my Patient Portal password</a>
</p>

<p>
    This link can only be used <strong>once</strong> and expires
    <strong>24 hours</strong> after it was sent. If it is used, expired, or
    replaced by a newer setup email, it will no longer work.
</p>

<p>If you did not expect this email, please contact the clinic so we can
assist you. For your safety, please do not share this link with anyone.</p>

<p>Thank you.</p>

<p>
    DEPLA Family Care Maternity &amp; Lying-In Clinic<br>
    Santa Maria, Bulacan
</p>
