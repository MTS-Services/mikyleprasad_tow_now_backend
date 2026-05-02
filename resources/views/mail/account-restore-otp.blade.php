<x-mail::message>
# {{ __('mail.account_restore_otp.title') }}

{{ __('mail.account_restore_otp.intro') }}

<x-mail::panel>
{{ $otp }}
</x-mail::panel>

{{ __('mail.account_restore_otp.expires', ['minutes' => config('account.restore_otp_ttl_minutes')]) }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
