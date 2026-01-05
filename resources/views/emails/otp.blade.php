@component('mail::message')
# {{ config('app.name') }} - Verification Code

Hello {{ $userName }},

Your verification code is:

@component('mail::panel')
<div style="font-size: 32px; font-weight: bold; letter-spacing: 8px; text-align: center; color: #667eea;">
{{ $code }}
</div>
@endcomponent

This code will expire in **{{ $expiresInMinutes }} minutes**.

If you didn't request this code, please ignore this email or contact support if you have concerns about your account security.

@component('mail::button', ['url' => config('app.url')])
Go to {{ config('app.name') }}
@endcomponent

Thanks,<br>
{{ config('app.name') }}

---

<small style="color: #6b7280;">
This is an automated message. Please do not reply to this email.
</small>
@endcomponent