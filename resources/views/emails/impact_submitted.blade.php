@extends('emails.layouts.email')

@section('title', 'Impact Submitted')

@section('content')
<p>Dear {{ $impact->user?->display_name ?? trim(($impact->user?->first_name ?? '') . ' ' . ($impact->user?->last_name ?? '')) ?: 'Peer' }},</p>

<!-- EDITABLE_START -->
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Your Impact has been submitted successfully and is now awaiting review.</p>

<p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Action:</strong> {{ $impact->action }}</p>
<p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Impact Date:</strong> {{ optional($impact->impact_date)->toDateString() }}</p>
<p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Story:</strong> {{ $impact->story_to_share }}</p>
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Status:</strong> {{ ucfirst($impact->status) }}</p>

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Thank you for contributing to our community.</p>
<!-- EDITABLE_END -->

<p style="margin: 24px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">
    With appreciation,<br>
    <strong>Peers Global Team</strong>
</p>
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
