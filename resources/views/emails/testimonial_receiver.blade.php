@extends('emails.layouts.email')

@section('title', 'Testimonial Received')

@section('content')
<p>Dear {{ $otherName ?? '' }},</p>

<!-- EDITABLE_START -->
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Great news! You’ve just <strong>received a testimonial</strong> from {{ $actorName ?? '' }}.</p>
<p style="margin: 0 0 8px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Here’s what they shared about you:</p>
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9; font-style: italic; background: rgba(255,255,255,0.05); padding: 12px; border-left: 3px solid #6366f1;">"{{ $testimonialContent ?? '' }}"</p>
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">Testimonials are proof of the impact you’re creating in the community — keep shining and inspiring others.</p>
<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">You can view and showcase this testimonial anytime from your Unity dashboard.</p>
<!-- EDITABLE_END -->

<p style="margin: 24px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">
    With appreciation,<br>
    <strong>Peers Global Team</strong>
</p>
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
