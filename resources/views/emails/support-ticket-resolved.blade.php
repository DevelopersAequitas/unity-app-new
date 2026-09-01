@extends('emails.layouts.email')

@section('title', 'Support Ticket Resolved')

@section('content')
<p>Dear {{ $ticket->contact_name }},</p>

<!-- EDITABLE_START -->
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Your support ticket has been resolved.</p>
<p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Ticket Number:</strong> {{ $ticket->ticket_number }}</p>
<p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Subject:</strong> {{ $ticket->subject }}</p>
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Status:</strong> Resolved</p>

@if(!empty($ticket->admin_note))
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">
    <strong>Admin Note:</strong><br>
    {{ $ticket->admin_note }}
</p>
@endif

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">If you still need help, please contact our support team again.</p>
<!-- EDITABLE_END -->

<p style="margin: 24px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">
    Thank you,<br>
    <strong>Peers Global Team</strong>
</p>
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
