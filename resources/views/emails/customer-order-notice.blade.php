<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;background:#f5f1e7;color:#332b1e;font-family:Arial,Helvetica,sans-serif;line-height:1.6;">
    <table role="presentation" style="width:100%;border-collapse:collapse;background:#f5f1e7;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" style="width:100%;max-width:620px;border:1px solid #dfc995;border-radius:16px;background:#fffdf8;">
                    <tr>
                        <td style="padding:28px 32px 12px;text-align:center;">
                            <div style="font-size:13px;font-weight:700;letter-spacing:2px;color:#805916;">KING KAD KAHWIN</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 32px 28px;">
                            <p style="margin:0 0 8px;">Salam {{ $customerName }},</p>
                            <h1 style="margin:0 0 16px;font-size:24px;line-height:1.25;color:#332b1e;">{{ $title }}</h1>
                            <p style="margin:0 0 18px;">{{ $bodyText }}</p>
                            <p style="margin:0 0 6px;color:#70634d;font-size:13px;font-weight:700;">NO. TEMPAHAN</p>
                            <p style="margin:0 0 20px;font-size:17px;font-weight:700;">{{ $orderReference }}</p>
                            @if ($statusLabel)
                                <p style="margin:0 0 6px;color:#70634d;font-size:13px;font-weight:700;">KEMAS KINI STATUS</p>
                                <p style="margin:0 0 20px;font-weight:700;">{{ $statusLabel }}</p>
                            @endif
                            @if ($amount)
                                <p style="margin:0 0 6px;color:#70634d;font-size:13px;font-weight:700;">BAKI BAYARAN</p>
                                <p style="margin:0 0 20px;font-size:18px;font-weight:700;">{{ $amount }}</p>
                            @endif
                            <p style="margin:0 0 24px;">
                                <a href="{{ $actionUrl }}" style="display:inline-block;border-radius:8px;background:#805916;padding:12px 20px;color:#fff;text-decoration:none;font-weight:700;">{{ $actionLabel }}</a>
                            </p>
                            <p style="margin:0;color:#70634d;font-size:13px;">Pautan ini akan meminta pengesahan nombor telefon bagi melindungi maklumat tempahan anda.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="border-top:1px solid #eadfc8;padding:16px 32px;text-align:center;color:#8a806f;font-size:12px;">
                            E-mel automatik berkaitan tempahan anda. Sila balas e-mel ini jika anda memerlukan bantuan.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
