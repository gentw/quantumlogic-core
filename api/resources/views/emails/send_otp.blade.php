<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('mail.otp.title') }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f8f9fa; color: #000000;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; margin: 0 auto; background-color: #ffffff;">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <tr>
                        <td align="center" style="font-size: 24px; font-weight: bold;">
                            Quantum<span style="color: #301068;">Logic</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding: 20px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 500px; background-color: #ffffff; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
                    <tr>
                        <td align="center" style="padding: 20px;">
                            <h2 style="margin: 0 0 15px 0; font-size: 20px; color: #000000;">{{ __('mail.otp.heading') }}</h2>
                            
                            <p style="margin: 0 0 20px 0; line-height: 1.5; color: #6c757d;">{{ __('mail.otp.intro') }}</p>
                            
                            <p style="display: inline-block; background-color: #301068; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 5px; margin-bottom: 20px; font-size: 20px; font-weight:bold;">{{$otp}}</p>
                            
                            <p style="margin: 0; font-size: 14px; color: #6c757d;">{{ __('mail.otp.validity', ['time' => $expiresAt]) }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding: 20px;">
                <p style="margin: 0; font-size: 14px; color: #6c757d; text-align: center;">{{ __('mail.common.footer', ['year' => date('Y')]) }}</p>
            </td>
        </tr>
    </table>
</body>
</html>