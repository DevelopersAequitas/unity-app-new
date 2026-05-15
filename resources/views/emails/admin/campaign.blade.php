@php
    $displayName = trim((string) ($user->display_name ?? ''));
    if ($displayName === '') {
        $displayName = trim(trim((string) ($user->first_name ?? '')) . ' ' . trim((string) ($user->last_name ?? '')));
    }
    $displayName = $displayName !== '' ? $displayName : 'Peer';

    $logoPath = 'images/logo.png';
    $logoUrl = file_exists(public_path($logoPath)) ? asset($logoPath) : null;

    $buttonText = trim((string) (
        data_get($campaign->filters, 'button_text')
        ?? data_get($campaign->filters, 'cta.button_text')
        ?? data_get($campaign->filters, 'cta.text')
        ?? ''
    ));
    $buttonUrl = trim((string) (
        data_get($campaign->filters, 'button_url')
        ?? data_get($campaign->filters, 'cta.button_url')
        ?? data_get($campaign->filters, 'cta.url')
        ?? ''
    ));
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $campaign->subject }}</title>
    <style>
        @media only screen and (max-width: 680px) {
            .pg-email-wrapper { padding: 16px 10px !important; }
            .pg-email-container { width: 100% !important; max-width: 100% !important; }
            .pg-header { padding: 24px 18px !important; }
            .pg-body { padding: 28px 20px !important; }
            .pg-footer { padding: 18px 20px !important; }
            .pg-title { font-size: 22px !important; line-height: 1.3 !important; }
            .pg-logo-text { font-size: 26px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; width:100% !important; background-color:#f3f0fb; font-family:Arial, Helvetica, sans-serif; color:#1f2937; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent; mso-hide:all;">
        {{ \Illuminate\Support\Str::limit(strip_tags((string) $campaign->email_body), 120) }}
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; margin:0; padding:0; background-color:#f3f0fb; border-collapse:collapse;">
        <tr>
            <td align="center" class="pg-email-wrapper" style="padding:32px 16px;">
                <table role="presentation" width="650" cellspacing="0" cellpadding="0" border="0" class="pg-email-container" style="width:650px; max-width:650px; background-color:#ffffff; border-collapse:separate; border-spacing:0; border-radius:18px; overflow:hidden; box-shadow:0 14px 40px rgba(46,16,101,0.16);">
                    <tr>
                        <td align="center" class="pg-header" style="background-color:#2E1065; padding:30px 28px; text-align:center;">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" width="168" alt="Peers Global" style="display:block; width:168px; max-width:70%; height:auto; margin:0 auto; border:0; outline:none; text-decoration:none;">
                            @else
                                <div class="pg-logo-text" style="font-family:Arial, Helvetica, sans-serif; font-size:30px; line-height:1.2; font-weight:700; letter-spacing:0.2px; color:#ffffff; text-align:center;">
                                    Peers<span style="color:#C4B5FD;">Global</span>
                                </div>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td class="pg-body" style="background-color:#ffffff; padding:38px 42px; font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:1.6; color:#1f2937;">
                            <p style="margin:0 0 18px 0; font-size:16px; line-height:1.6; color:#1f2937;">
                                Dear <strong style="font-weight:700; color:#111827;">{{ $displayName }}</strong>,
                            </p>

                            <div style="font-family:Arial, Helvetica, sans-serif; font-size:16px; line-height:1.6; color:#1f2937; margin:0;">
                                {!! $campaign->email_body !!}
                            </div>

                            @if ($buttonText !== '' && $buttonUrl !== '')
                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin:30px auto 4px auto; border-collapse:separate;">
                                    <tr>
                                        <td align="center" bgcolor="#2E1065" style="border-radius:999px; background-color:#2E1065;">
                                            <a href="{{ $buttonUrl }}" target="_blank" style="display:inline-block; padding:13px 28px; font-family:Arial, Helvetica, sans-serif; font-size:15px; line-height:1.2; font-weight:700; color:#ffffff; text-decoration:none; border-radius:999px; background-color:#2E1065;">
                                                {{ $buttonText }}
                                            </a>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td align="center" class="pg-footer" style="background-color:#2E1065; padding:20px 28px; text-align:center;">
                            <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:14px; line-height:1.5; font-weight:700; color:#ffffff; text-align:center;">
                                Peers are partners in business and friends in life.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
