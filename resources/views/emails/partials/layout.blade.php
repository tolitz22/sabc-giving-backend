<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SABC Giving' }}</title>
</head>
<body style="margin:0;background:#f6f7f9;font-family:Arial,Helvetica,sans-serif;color:#172033;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7f9;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;">
                    <tr>
                        <td style="padding:24px;background:#132238;color:#ffffff;">
                            <strong style="font-size:18px;">Scripture Alone Baptist Church</strong>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px;font-size:15px;line-height:1.55;">
                            @yield('content')
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
