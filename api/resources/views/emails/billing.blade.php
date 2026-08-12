<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f8f9fa; color: #000000;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; margin: 0 auto; background-color: #ffffff;">
        <tr>
            <td align="center" style="padding: 20px 0; font-size: 24px; font-weight: bold;">
                Quantum<span style="color: #301068;">Logic</span>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding: 20px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 500px; background-color: #ffffff; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="padding: 30px 24px;">
                            <h2 style="margin: 0 0 15px 0; font-size: 20px; color: #000000;">{{ $heading }}</h2>
                            <p style="margin: 0 0 20px 0; line-height: 1.6; color: #6c757d;">
                                Hello {{ $userName }},<br><br>
                                {!! nl2br(e($body)) !!}
                            </p>

                            @if (! empty($details))
                                <table border="0" cellpadding="8" cellspacing="0" width="100%" style="background-color: #f8f9fa; border-radius: 5px; margin-bottom: 20px;">
                                    @foreach ($details as $label => $value)
                                        <tr>
                                            <td align="left" style="color: #6c757d;">{{ $label }}</td>
                                            <td align="right" style="font-weight: bold;">{{ $value }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            @endif

                            @if (! empty($ctaUrl))
                                <p align="center" style="margin: 0 0 20px 0;">
                                    <a href="{{ $ctaUrl }}" style="display: inline-block; background-color: #301068; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 5px;">{{ $ctaLabel }}</a>
                                </p>
                            @endif

                            <p style="margin: 0; font-size: 14px; color: #6c757d;">{{ __('mail.common.reply') }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding: 20px;">
                <p style="margin: 0; font-size: 13px; color: #6c757d; text-align: center;">
                    {{ config('company.legal_name') }}@if (config('company.address')) · {{ config('company.address') }}@endif
                    @if (config('company.uid'))<br>UID: {{ config('company.uid') }}@endif
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
