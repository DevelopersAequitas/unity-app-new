@extends('emails.layouts.email')

@section('title', 'Account Successfully Deleted')

@section('content')
@php
    $userName = $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'User';
@endphp
    Dear <strong>{{ $userName }}</strong>,<br /><br />

    This email is to notify you that your account with <strong>Peers Global Unity</strong> and all associated personal data have been successfully and permanently deleted from our systems, in accordance with your request and our data retention policies.<br /><br />

    Please note that any active subscriptions have been cancelled, and you will no longer receive communications or notifications from us.<br /><br />

    If you have any questions or require further assistance, please contact our support team at <a href="mailto:{{ config('membership_welcome.support_email', 'support@peersunity.com') }}" style="color:#38bdf8; text-decoration:underline;">{{ config('membership_welcome.support_email', 'support@peersunity.com') }}</a>.<br /><br />

    Thank you for having been part of the Peers Global Unity community.<br /><br />

    Warm Regards,<br />
    Peers Global Unity compliance Team
@endsection

@section('footer')
    <p style="margin:0; font-size:14px; font-weight:bold; color:#ffffff; text-align:center;">
        Peers are partners in business and friends in life.
    </p>
@endsection
