<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Reset Password') }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f5;font-family:ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:480px;background-color:#ffffff;border-radius:8px;border:1px solid #e4e4e7;overflow:hidden;">
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 16px;font-size:20px;font-weight:600;color:#18181b;">
                                {{ __('Reset your password') }}
                            </h1>
                            @isset($name)
                                <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#3f3f46;">
                                    {{ __('Hi :name,', ['name' => $name]) }}
                                </p>
                            @endisset
                            <p style="margin:0 0 24px;font-size:14px;line-height:1.6;color:#3f3f46;">
                                {{ __('You are receiving this email because we received a password reset request for your account.') }}
                            </p>
                            <p style="margin:0 0 24px;text-align:center;">
                                <a href="{{ $url }}" style="display:inline-block;padding:12px 24px;background-color:#18181b;color:#ffffff;text-decoration:none;border-radius:6px;font-size:14px;font-weight:500;">
                                    {{ __('Reset Password') }}
                                </a>
                            </p>
                            <p style="margin:0 0 24px;font-size:13px;line-height:1.6;color:#71717a;">
                                {{ __('This password reset link will expire in :count minutes.', ['count' => $expiresIn]) }}
                            </p>
                            <p style="margin:0;font-size:13px;line-height:1.6;color:#71717a;">
                                {{ __('If you did not request a password reset, no further action is required.') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
