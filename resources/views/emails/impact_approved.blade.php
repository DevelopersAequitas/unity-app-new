@extends('emails.layouts.email')

@section('title', 'Impact Approved')

@section('content')
<p>Dear {{ $submitter->display_name ?? trim(($submitter->first_name ?? '') . ' ' . ($submitter->last_name ?? '')) ?: 'Peer' }},</p>

<!-- EDITABLE_START -->
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Great news! Your Impact has been approved successfully.</p>

<p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Action:</strong> {{ $impact->action }}</p>
<p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Impact Date:</strong> {{ optional($impact->impact_date)->toDateString() }}</p>
<p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Story:</strong> {{ $impact->story_to_share }}</p>
<p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Status:</strong> {{ ucfirst($impact->status) }}</p>
<p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Life Impacted:</strong> {{ (int) ($impact->life_impacted ?? 1) }}</p>
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Total Life Impacted:</strong> {{ (int) ($submitter->life_impacted_count ?? 0) }}</p>

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Thank you for creating meaningful impact.</p>
<!-- EDITABLE_END -->

<p style="margin: 24px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">
    With appreciation,<br>
    <strong>Peers Global Team</strong>
</p>
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
