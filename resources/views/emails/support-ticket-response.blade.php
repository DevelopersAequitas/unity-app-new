<table style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 30px;" width="100%" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td align="center">
<table style="background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05);" width="600" cellspacing="0" cellpadding="0">
<tbody>
<tr>
<td style="padding: 14px 14px; background-color: #240e5c; text-align: center;"><img style="vertical-align: middle;" src="https://peersunity.com/images/peersglobal-logo.png" alt="Peers Global" width="135" /></td>
</tr>
<tr>
<td style="padding: 18px 20px; font-size: 16px; color: #333333;">
Dear <strong>{{ $ticket->contact_name }}</strong>,<br /><br />

<div style="background-color: #f9f9fc; border-left: 4px solid #4f46e5; padding: 15px; margin: 10px 0; border-radius: 4px; font-size: 15px; line-height: 1.6; white-space: pre-wrap;">{{ $responseMessage }}</div>

<br />
<hr style="border: 0; border-top: 1px solid #eeeeee; margin: 15px 0;" />
<span style="font-size: 13px; color: #777777;">
<strong>Ticket Reference:</strong> #{{ $ticket->ticket_number }}<br />
<strong>Original Subject:</strong> {{ $ticket->subject }}
</span>
<br /><br />
If you have any further questions or require additional assistance, please feel free to reply to this email or contact support.<br /><br />
Best regards,<br />
<strong>Peers Global Support Team</strong>
</td>
</tr>
<tr>
<td style="padding: 10px 14px; background-color: #240e5c; text-align: center; border-bottom-left-radius: 10px; border-bottom-right-radius: 10px;">
<p style="font-size: 14px; font-weight: bold; color: #ffffff; margin: 4px 0;">Peers are partners in business and friends in life.</p>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>
