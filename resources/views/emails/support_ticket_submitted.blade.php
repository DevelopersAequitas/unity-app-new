@extends('emails.layouts.email')

@section('title', 'Support Ticket Submitted')

@section('content')
<p>Dear {{ $ticket->contact_name }},</p>

<!-- EDITABLE_START -->
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">We have received your support request.</p>
<p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Ticket Number:</strong> {{ $ticket->ticket_number }}</p>
<p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Subject:</strong> {{ $ticket->subject }}</p>
@if(!empty($ticket->description))
<p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Description:</strong> {{ $ticket->description }}</p>
@endif
@if(!empty($ticket->media_url))
<p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Attached Media:</strong> <a href="{{ $ticket->media_url }}" target="_blank" style="color: #6366f1;">View Attachment</a></p>
@endif

<p style="margin: 16px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Our support team will review it and contact you soon.</p>
<!-- EDITABLE_END -->

<p style="margin: 24px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">
    Thank you,<br>
    <strong>Peers Global Team</strong>
</p>
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
