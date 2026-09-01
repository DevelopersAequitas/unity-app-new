@extends('emails.layouts.email')

@section('title', 'Circle Join Congratulations')

@section('content')
<p>Hello {{ $displayName }},</p>

<!-- EDITABLE_START -->
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Congratulations!</p>
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Your request to join {{ $circleName }} has been approved by both the CD and ID teams.</p>

<p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;"><strong>Joining details:</strong></p>
<p style="margin: 0 0 8px 20px; font-size: 15px; line-height: 22px; color: #d9d9d9;">• Circle: {{ $circleName }}</p>
<p style="margin: 0 0 8px 20px; font-size: 15px; line-height: 22px; color: #d9d9d9;">• Category: {{ $categoryName }}</p>
<p style="margin: 0 0 8px 20px; font-size: 15px; line-height: 22px; color: #d9d9d9;">• Request ID: {{ $joinRequestId }}</p>
<p style="margin: 0 0 8px 20px; font-size: 15px; line-height: 22px; color: #d9d9d9;">• Approval status: Approved</p>
<p style="margin: 0 0 8px 20px; font-size: 15px; line-height: 22px; color: #d9d9d9;">• Payment status: Unpaid</p>
<p style="margin: 0 0 16px 20px; font-size: 15px; line-height: 22px; color: #d9d9d9;">• Amount: {{ $formattedAmount }}</p>

@if(!empty($paymentUrl))
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Please complete your circle joining payment using the button below:</p>
<div style="text-align:center; margin:24px 0;">
    <a href="{{ $paymentUrl }}" style="background:#4f46e5; color:#ffffff; padding:12px 24px; text-decoration:none; font-size:16px; font-weight:700; border-radius:8px; display:inline-block;">Pay Now</a>
</div>
@else
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Your payment link is currently being generated. You can complete the payment in the mobile app once it becomes available.</p>
@endif

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">After successful payment, your membership status will be updated according to the existing workflow.</p>
<!-- EDITABLE_END -->

<p style="margin: 24px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">
    Regards,<br>
    <strong>PeersGlobal Team</strong>
</p>
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
