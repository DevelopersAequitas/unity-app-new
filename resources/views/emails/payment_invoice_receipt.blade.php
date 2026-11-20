<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt &amp; Invoice</title>
</head>
<body style="margin:0;padding:24px 16px;background-color:#f4f6f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;margin:0 auto;background-color:#ffffff;border-radius:10px;border:1px solid #e5e7eb;overflow:hidden;">
        <tr>
            <td style="padding:24px 28px 18px 28px;border-bottom:1px solid #f0f2f5;">
                <div style="font-size:18px;font-weight:700;color:#111827;letter-spacing:-0.2px;">Peers Global</div>
                <div style="font-size:13px;color:#6b7280;margin-top:2px;">Payment Receipt &amp; Tax Invoice</div>
            </td>
        </tr>
        <tr>
            <td style="padding:24px 28px;">
                <p style="margin:0 0 16px 0;font-size:15px;line-height:1.6;">
                    Hello {{ $recipientName }},
                </p>

                <p style="margin:0 0 18px 0;font-size:15px;line-height:1.6;">
                    We’ve successfully received your payment of <strong>{{ $formattedAmount }}</strong>.
                </p>

                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:18px;">
                    @if($invoiceNumber !== '')
                    <tr>
                        <td style="padding:10px 14px;font-size:13px;color:#6b7280;border-bottom:1px solid #e5e7eb;">Invoice No.</td>
                        <td style="padding:10px 14px;font-size:13px;font-weight:600;color:#111827;text-align:right;border-bottom:1px solid #e5e7eb;">{{ $invoiceNumber }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding:10px 14px;font-size:13px;color:#6b7280;@if($invoiceDate)border-bottom:1px solid #e5e7eb;@endif">Amount Paid</td>
                        <td style="padding:10px 14px;font-size:13px;font-weight:600;color:#111827;text-align:right;@if($invoiceDate)border-bottom:1px solid #e5e7eb;@endif">{{ $formattedAmount }}</td>
                    </tr>
                    @if($invoiceDate)
                    <tr>
                        <td style="padding:10px 14px;font-size:13px;color:#6b7280;">Date</td>
                        <td style="padding:10px 14px;font-size:13px;font-weight:600;color:#111827;text-align:right;">{{ $invoiceDate }}</td>
                    </tr>
                    @endif
                </table>

                <p style="margin:0 0 20px 0;font-size:14px;line-height:1.6;color:#374151;">
                    Your invoice PDF is attached to this email. You can also view and download your invoices anytime in the Peers Global Unity app under <strong>Invoice History</strong>.
                </p>

                <p style="margin:0;font-size:14px;line-height:1.6;color:#111827;">
                    Thank you,<br>
                    <strong>Peers Global</strong>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
