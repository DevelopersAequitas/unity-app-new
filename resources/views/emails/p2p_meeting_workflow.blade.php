@php
    $actorName = $actor?->display_name ?: trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? '')) ?: 'A peer';
    $recipientName = $recipient?->display_name ?: trim(($recipient->first_name ?? '') . ' ' . ($recipient->last_name ?? '')) ?: 'Peer';
@endphp
@extends('emails.layouts.email')

@section('title', 'P2P Meeting Workflow Update')

@section('content')
<p>Hello {{ $recipientName }},</p>

<!-- EDITABLE_START -->
@if ($eventType === 'p2p_reschedule_requested')
    <p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">{{ $actorName }} has requested to reschedule your P2P meeting.</p>
@elseif ($eventType === 'p2p_reschedule_approved')
    <p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">{{ $actorName }} has approved your P2P meeting reschedule request.</p>
@elseif ($eventType === 'p2p_reschedule_rejected')
    <p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">{{ $actorName }} has rejected your P2P meeting reschedule request.</p>
@else
    <p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">There is an update about your P2P meeting.</p>
@endif

<p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Current meeting time:</strong> {{ optional($meetingRequest->scheduled_at)->format('Y-m-d H:i') ?? 'Not scheduled' }}</p>
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Current place:</strong> {{ $meetingRequest->place ?: 'Not specified' }}</p>

@if ($rescheduleRequest)
    <p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Old time:</strong> {{ optional($rescheduleRequest->old_scheduled_at)->format('Y-m-d H:i') ?? 'Not scheduled' }}</p>
    <p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>New time:</strong> {{ optional($rescheduleRequest->new_scheduled_at)->format('Y-m-d H:i') ?? 'Not scheduled' }}</p>
    <p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Old place:</strong> {{ $rescheduleRequest->old_place ?: 'Not specified' }}</p>
    <p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>New place:</strong> {{ $rescheduleRequest->new_place ?: 'Not specified' }}</p>
    @if ($rescheduleRequest->reason)
        <p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Reason:</strong> {{ $rescheduleRequest->reason }}</p>
    @endif
@endif

@if ($responseReason)
    <p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Response note:</strong> {{ $responseReason }}</p>
@endif
<!-- EDITABLE_END -->

<p style="margin: 24px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">
    Regards,<br>
    <strong>Peers Global Team</strong>
</p>
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
