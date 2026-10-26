@extends('emails.layouts.email')

@section('title', $heading ?? 'Peers Global Advertiser Update')

@section('content')
<p>Dear {{ $recipientName }},</p>

<p style="margin: 0 0 16px 0; font-size: 16px; font-weight: 600; line-height: 24px; color: #ffffff;">{{ $heading }}</p>

<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">{!! nl2br(e($mainMessage)) !!}</p>

@if(!empty($details))
<div style="background-color: #1e293b; border-radius: 8px; padding: 16px; margin: 20px 0; border: 1px solid #334155;">
    <table style="width: 100%; border-collapse: collapse;">
        @foreach($details as $label => $value)
        <tr>
            <td style="padding: 6px 0; font-size: 14px; color: #94a3b8; font-weight: 500; width: 40%;">{{ $label }}:</td>
            <td style="padding: 6px 0; font-size: 14px; color: #f8fafc; font-weight: 600;">{{ $value }}</td>
        </tr>
        @endforeach
    </table>
</div>
@endif

@if(!empty($actionUrl) && !empty($actionText))
<p style="margin: 24px 0 16px 0; text-align: center;">
    <a href="{{ $actionUrl }}" style="display: inline-block; background-color: #6366f1; color: #ffffff; padding: 12px 28px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 15px;">{{ $actionText }}</a>
</p>
@endif

<p style="margin: 24px 0 0 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">
    With appreciation,<br>
    <strong>Peers Global Unity Team</strong>
</p>
@endsection

@section('footer')
<p style="margin:0;">Peers are partners in business and friends in life.</p>
@endsection
