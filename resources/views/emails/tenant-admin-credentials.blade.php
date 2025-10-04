<x-mail::message>
# Welcome to {{ $companyName }}

Your admin account has been created for the {{ $companyName }} attendance monitoring system.

## Login Credentials

**Email:** {{ $email }}
**Password:** `{{ $password }}`

<x-mail::button :url="$loginUrl">
Login to Your Account
</x-mail::button>

## Important Security Notes

⚠️ **Please change your password immediately after your first login.**

For security reasons, we recommend:
- Using a strong, unique password
- Enabling two-factor authentication (if available)
- Not sharing your credentials with anyone

If you have any questions or need assistance, please contact our support team.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
