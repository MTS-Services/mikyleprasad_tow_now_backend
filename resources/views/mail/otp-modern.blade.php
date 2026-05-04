<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subjectLine }}</title>
</head>

<body
    style="margin:0;padding:0;background-color:#0b0f14;font-family:system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;-webkit-font-smoothing:antialiased;">
    <span
        style="display:none !important;visibility:hidden;mso-hide:all;font-size:1px;color:#0b0f14;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">
        {{ $preheader }}
    </span>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#0b0f14;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="560" cellspacing="0" cellpadding="0" border="0"
                    style="max-width:560px;width:100%;background-color:#111827;border:1px solid #1f2937;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td
                            style="padding:24px 28px;background:linear-gradient(135deg,#1e3a5f 0%,#0f172a 50%,#111827 100%);border-bottom:1px solid #334155;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <span
                                            style="font-size:11px;font-weight:700;letter-spacing:0.28em;color:#93c5fd;text-transform:uppercase;">{{ __('mail.otp_modern.badge') }}</span>
                                    </td>
                                    <td align="right" style="vertical-align:middle;">
                                        <span
                                            style="font-size:13px;font-weight:600;color:#f8fafc;">{{ $appName }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 28px 8px 28px;background-color:#f8fafc;">
                            <h1
                                style="margin:0 0 16px 0;font-size:22px;line-height:1.3;font-weight:700;color:#0f172a;letter-spacing:-0.02em;">
                                {{ $title }}
                            </h1>
                            <p
                                style="margin:0 0 28px 0;font-size:15px;line-height:1.65;color:#475569;">
                                {!! nl2br(e($intro)) !!}
                            </p>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                                style="background:linear-gradient(180deg,#eff6ff 0%,#dbeafe 100%);border:1px solid #93c5fd;border-radius:10px;">
                                <tr>
                                    <td align="center" style="padding:28px 20px;">
                                        <p
                                            style="margin:0 0 8px 0;font-size:11px;font-weight:700;letter-spacing:0.2em;color:#1d4ed8;text-transform:uppercase;">
                                            {{ __('mail.otp_modern.code_label') }}</p>
                                        <p
                                            style="margin:0;font-size:32px;font-weight:800;letter-spacing:0.35em;color:#0f172a;font-family:ui-monospace,SFMono-Regular,'SF Mono',Menlo,Consolas,monospace;">
                                            {{ $code }}</p>
                                    </td>
                                </tr>
                            </table>
                            <p
                                style="margin:24px 0 0 0;font-size:13px;line-height:1.6;color:#64748b;text-align:center;">
                                {{ __('mail.otp_modern.expires', ['minutes' => $expiryMinutes]) }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 28px 28px 28px;background-color:#f1f5f9;border-top:1px solid #e2e8f0;">
                            <p style="margin:0;font-size:12px;line-height:1.55;color:#64748b;text-align:center;">
                                {{ __('mail.otp_modern.security_note') }}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 28px;background-color:#0f172a;border-top:1px solid #1e293b;">
                            <p style="margin:0;font-size:11px;line-height:1.5;color:#94a3b8;text-align:center;">
                                © {{ date('Y') }} {{ $appName }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
