@php
    $userName = trim((string) ($user->display_name ?? ''));
    if ($userName === '') {
        $userName = trim(trim((string) ($user->first_name ?? '')) . ' ' . trim((string) ($user->last_name ?? '')));
    }
    $userName = $userName !== '' ? $userName : 'Member';

    $logoPath = 'images/peers-global-logo.png';
    $logoUrl = file_exists(public_path($logoPath)) ? asset($logoPath) : null;
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="dark light">
    <meta name="supported-color-schemes" content="dark light">
    <title>{{ $campaign->subject }}</title>
    <style>
        @media only screen and (max-width: 640px) {
            .pg-wrapper { padding: 20px 0 !important; }
            .pg-card { width: 92% !important; max-width: 92% !important; }
            .pg-header { padding: 24px 16px !important; }
            .pg-body { padding: 24px 20px !important; font-size: 22px !important; line-height: 1.38 !important; }
            .pg-footer { padding: 20px 18px !important; font-size: 20px !important; }
            .pg-logo-fallback { font-size: 30px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background:#242424;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;mso-hide:all;">
        {{ \Illuminate\Support\Str::limit(strip_tags((string) $campaign->email_body), 120) }}
    </div>

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" class="pg-wrapper" style="background:#242424;padding:30px 0;font-family:Arial,Helvetica,sans-serif;border-collapse:collapse;margin:0;width:100%;">
        <tr>
            <td align="center" style="padding:0;margin:0;">
                <table width="600" cellpadding="0" cellspacing="0" role="presentation" class="pg-card" style="max-width:600px;width:90%;border-radius:14px;overflow:hidden;background:#111111;border-collapse:separate;border-spacing:0;margin:0 auto;">
                    <tr>
                        <td align="center" class="pg-header" style="background:#2b0b63;padding:28px 20px;text-align:center;">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="PeersGlobal" style="display:block;max-width:230px;width:70%;height:auto;margin:0 auto;border:0;outline:none;text-decoration:none;">
                            @else
                                <div class="pg-logo-fallback" style="font-family:Arial,Helvetica,sans-serif;font-size:34px;line-height:1.1;font-weight:700;letter-spacing:0.2px;color:#ffffff;text-align:center;">
                                    Peers<span style="color:#c9b8ff;">Global</span>
                                </div>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td class="pg-body" style="background:#111111;color:#f5f5f5;padding:24px 22px;font-family:Arial,Helvetica,sans-serif;font-size:24px;line-height:1.35;">
                            <p style="margin:0 0 24px;color:#f5f5f5;font-family:Arial,Helvetica,sans-serif;font-size:24px;line-height:1.35;">
                                Dear <strong style="font-weight:700;color:#ffffff;">{{ $userName }}</strong>,
                            </p>

                            <div style="margin:0 0 28px;color:#f5f5f5;font-family:Arial,Helvetica,sans-serif;font-size:24px;line-height:1.35;">
                                {!! $campaign->email_body !!}
                            </div>

                            <p style="margin:0;color:#f5f5f5;font-family:Arial,Helvetica,sans-serif;font-size:24px;line-height:1.35;">
                                With appreciation,<br>
                                <strong style="font-weight:700;color:#ffffff;">Peers Global Team</strong>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" class="pg-footer" style="background:#2b0b63;color:#ffffff;padding:22px 24px;font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:bold;line-height:1.25;text-align:center;">
                            Peers are partners in business and friends in life.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
