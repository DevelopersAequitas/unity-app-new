@extends('emails.layouts.email')

@section('title', 'Account Deletion Request Received')

@section('content')
@php
    $userName = $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'User';
@endphp
    Dear <strong>{{ $userName }}</strong>,<br /><br />

    We have received your request to permanently delete your account and all associated personal data from <strong>Peers Global Unity</strong>.<br /><br />

    Our compliance team is currently reviewing your request. Please note that this process may take some time as we ensure all data removal protocols are securely handled in compliance with our data protection policies.<br /><br />

    <strong>Request Status:</strong> Under Review / Pending<br /><br />

    If you did not initiate this request, or if you wish to cancel the deletion request, please contact our support team immediately at <a href="mailto:{{ config('membership_welcome.support_email', 'support@peersunity.com') }}" style="color:#38bdf8; text-decoration:underline;">{{ config('membership_welcome.support_email', 'support@peersunity.com') }}</a>.<br /><br />

    Thank you for your patience.<br /><br />

    Warm Regards,<br />
    Peers Global Unity Compliance Team
@endsection

@section('footer')
    <p style="margin:0; font-size:14px; font-weight:bold; color:#ffffff; text-align:center;">
        Peers are partners in business and friends in life.
    </p>
@endsection
