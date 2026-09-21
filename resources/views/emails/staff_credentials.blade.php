<p>Good day {{ $staff->name }},</p>

<p>Your DEPLA Family Care Staff account has been created successfully.</p>

<p>You may sign in to the system using the following credentials:</p>

<p>
Login email: <strong>{{ $staff->email }}</strong><br>
Password: <strong>{{ $plainTextPassword }}</strong>
</p>

<p>Please use your email address and password to log in to the DEPLA Family Care system.</p>

<p>
Keep these credentials private. Change your password if the system provides a password-change mechanism.
</p>

<p>Thank you.</p>
