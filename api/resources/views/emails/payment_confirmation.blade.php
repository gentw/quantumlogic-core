<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmed – QuantumLogic</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f8f9fa; color: #000000;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px; margin: 0 auto; background-color: #ffffff;">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">
                    <tr>
                        <td align="center" style="font-size: 24px; font-weight: bold;">
                            Sentri<span style="color: #301068;">Gate</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding: 20px;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 500px; background-color: #ffffff; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
                    <tr>
                        <td align="center" style="padding: 30px 20px;">
                            <div style="background-color: #22c55e; border-radius: 50%; width: 60px; height: 60px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                                <span style="color: #ffffff; font-size: 28px; line-height: 60px;">&#10003;</span>
                            </div>
                            <h2 style="margin: 0 0 15px 0; font-size: 20px; color: #000000;">Payment Confirmed</h2>
                            <p style="margin: 0 0 20px 0; line-height: 1.5; color: #6c757d;">
                                Hello {{ $userName }},<br><br>
                                Your payment has been processed successfully. Your <strong>{{ $packageName }}</strong> subscription is now active.
                            </p>

                            <table border="0" cellpadding="10" cellspacing="0" width="100%" style="max-width: 400px; background-color: #f8f9fa; border-radius: 5px; margin-bottom: 20px;">
                                <tr>
                                    <td align="left"><strong>Plan:</strong> {{ $packageName }}</td>
                                </tr>
                                <tr>
                                    <td align="left"><strong>Billing cycle:</strong> {{ ucfirst($billingCycle) }}</td>
                                </tr>
                                <tr>
                                    <td align="left"><strong>Amount paid:</strong> €{{ number_format($amount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td align="left"><strong>Transaction ID:</strong> {{ $captureId }}</td>
                                </tr>
                            </table>

                            <a href="{{ config('app.frontend_url') }}/client" style="display: inline-block; background-color: #301068; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 5px; margin-bottom: 20px;">Go to Dashboard</a>

                            <p style="margin: 0; font-size: 14px; color: #6c757d;">If you have any questions, please contact our support team.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding: 20px;">
                <p style="margin: 0; font-size: 14px; color: #6c757d; text-align: center;">&copy; {{ date('Y') }} QuantumLogic. All rights reserved.</p>
            </td>
        </tr>
    </table>
</body>
</html>
