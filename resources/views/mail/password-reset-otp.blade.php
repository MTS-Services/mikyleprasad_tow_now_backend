<x-mail::message>
# {{ __('mail.password_reset_otp.title') }}

{{ __('mail.password_reset_otp.intro') }}

<x-mail::panel>
{{ $otp }}
</x-mail::panel>

{{ __('mail.password_reset_otp.expires', ['minutes' => config('account.password_reset_otp_ttl_minutes')]) }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
