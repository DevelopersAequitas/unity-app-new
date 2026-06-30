@extends('emails.layouts.email')

@section('title', 'Welcome to your Peers Unity Membership')

@section('content')
@php
    $peerName = $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'Peer';
    $formatDate = static function ($value) { if (blank($value)) return '—'; try { return \Illuminate\Support\Carbon::parse($value)->format('d M Y'); } catch (\Throwable) { return (string) $value; } };
    $label = static fn ($value) => \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) ($value ?: '—')));
@endphp
    @if(! blank($bannerUrl))
        <img src="{{ $bannerUrl }}" alt="Peers Global Unity" style="display:block; width:100%; max-width:560px; height:auto; margin:0 0 18px; border-radius:8px;" />
    @endif

    Dear <strong>{{ $peerName }}</strong>,<br /><br />

    Welcome to <strong>Peers Global Unity</strong>.<br /><br />

    We are pleased to confirm that your membership has been successfully activated. @if(! empty($attachmentLinks)) Your welcome kit and membership documents are attached below and also available through the links below. @else Your welcome kit and membership documents will be shared with you separately. @endif<br /><br />

    <strong>User Name:</strong> {{ $peerName }}<br />
    <strong>Membership Type:</strong> {{ $label($user->membership_type ?? $user->membership_status) }}<br />
    <strong>Membership Plan Name:</strong> {{ $user->zoho_plan_code ?: 'Peers Global Unity Membership' }}<br />
    <strong>Membership Start Date:</strong> {{ $formatDate($user->membership_starts_at) }}<br />
    <strong>Membership Expiry Date:</strong> {{ $formatDate($user->membership_ends_at ?? $user->membership_expiry) }}<br />
    <strong>Purchase Date:</strong> {{ $formatDate($user->last_payment_at) }}<br />
    <strong>Membership Status:</strong> {{ $label($user->membership_status) }}<br />
    <strong>Membership ID:</strong> {{ $user->id }}<br />
    <strong>Transaction ID:</strong> {{ $user->zoho_last_invoice_id ?: '—' }}<br />
    @if(! empty($attachmentLinks))
        <strong>Membership Documents:</strong><br />
        @foreach($attachmentLinks as $attachment)
            &bull; {{ $attachment['name'] ?? 'Document' }}: <a href="{{ $attachment['url'] }}" style="color:#38bdf8; text-decoration:underline;">{{ $attachment['url'] }}</a><br />
        @endforeach
    @endif
    <strong>Support Contact:</strong> <a href="mailto:{{ config('membership_welcome.support_email', 'pravin@peersunity.com') }}" style="color:#38bdf8; text-decoration:underline;">{{ config('membership_welcome.support_email', 'pravin@peersunity.com') }}</a><br /><br />

    Thank you for joining the Peers Global community. We look forward to your active participation and growth journey with us.<br /><br />

    Warm Regards,<br />
    Peers Global Unity
@endsection

@section('footer')
    <p style="margin:0; font-size:14px; font-weight:bold; color:#ffffff; text-align:center;">
        Peers are partners in business and friends in life.
    </p>
@endsection
