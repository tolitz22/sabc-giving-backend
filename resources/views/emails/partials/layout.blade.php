<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SABC Giving' }}</title>
</head>
<body style="margin:0;background:#eef4f8;font-family:Arial,Helvetica,sans-serif;color:#172033;">
    <div style="display:none;max-height:0;overflow:hidden;color:#eef4f8;line-height:1px;opacity:0;">
        {{ $preheader ?? 'Thank you for giving to Scripture Alone Baptist Church.' }}
    </div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef4f8;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #d9e3ec;">
                    <tr>
                        <td style="padding:24px 28px;background:#123a5d;color:#ffffff;">
                            <div style="font-size:12px;letter-spacing:1.4px;text-transform:uppercase;color:#f0d28a;font-weight:bold;">Online Giving</div>
                            <div style="margin-top:6px;font-size:21px;font-weight:bold;line-height:1.25;">Scripture Alone Baptist Church</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0;font-size:15px;line-height:1.6;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 28px;background:#f6f9fc;border-top:1px solid #e4ebf2;color:#52667a;font-size:12px;line-height:1.6;">
                            This message was sent by Scripture Alone Baptist Church Online Giving. Please keep this confirmation for your records.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
