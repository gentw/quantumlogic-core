<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to QuantumLogic</title>
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
                            <h2 style="margin: 0 0 15px 0; font-size: 20px; color: #000000;">Mirë se vini në QuantumLogic</h2>
                            
                            <p style="margin: 0 0 20px 0; line-height: 1.5; color: #6c757d;">Përshëndetje {{$name}},<br><br>
                            Regjistrimi juaj si {{$role}} në platformën tonë është konfirmuar me sukses. 
                            Më poshtë janë të dhënat tuaja të hyrjes:</p>

                            <p style="margin: 0 0 20px 0; line-height: 1.5; color: #6c757d;">Ju lutemi përdorni këto të dhëna për të hyrë në llogarinë tuaj. Për arsye sigurie, 
                            ju rekomandojmë të ndryshoni fjalëkalimin tuaj pas hyrjes së parë.</p>

                            <table border="0" cellpadding="10" cellspacing="0" width="100%" style="max-width: 400px; background-color: #f8f9fa; border-radius: 5px; margin-bottom: 20px;">
                                <tr>
                                    <td align="left"><strong>Emri i përdoruesit:</strong> {{$username}}</td>
                                </tr>
                                <tr>
                                    <td align="left"><strong>Fjalëkalimi:</strong> {{$password}}</td>
                                </tr>
                            </table>
                            
                            <a href="https://ds-web.bitemybytes.com" style="display: inline-block; background-color: #301068; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 5px; margin-bottom: 20px;">Hyni në llogarinë tuaj</a>
                            
                            <p style="margin: 0; font-size: 14px; color: #6c757d;">Nëse nuk keni kërkuar të regjistroheni si {{$role}}, ju lutemi injoroni këtë email dhe kontaktoni ekipin tonë të mbështetjes menjëherë.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding: 20px;">
                <p style="margin: 0; font-size: 14px; color: #6c757d; text-align: center;">&copy;2026 QuantumLogic. Të gjitha të drejtat e rezervuara.</p>
            </td>
        </tr>
    </table>
</body>
</html>