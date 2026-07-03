@extends('emails.layouts.email')

@section('title', $manual ? 'Membership Information' : 'Membership Status Updated')

@section('content')
@php
    $peerName = $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'Peer';
    $formatDate = static function ($value) {
        if (blank($value)) return '—';
        try { return \Illuminate\Support\Carbon::parse($value)->format('d M Y'); } catch (\Throwable) { return (string) $value; }
    };
    $label = static fn ($value) => \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) ($value ?: '—')));
@endphp
    Dear <strong>{{ $peerName }}</strong>,<br /><br />
    @if($manual)
        Here is your latest Peers Global Unity membership information.<br /><br />
    @else
        Your Peers Global Unity membership status has been updated.<br /><br />
    @endif

    <strong>User Name:</strong> {{ $peerName }}<br />
    <strong>Membership Type:</strong> {{ $label($user->membership_type ?? $user->membership_status) }}<br />
    <strong>Previous Status:</strong> {{ $label($previousStatus) }}<br />
    <strong>New Status:</strong> {{ $label($newStatus) }}<br />
    <strong>Effective Date:</strong> {{ now()->format('d M Y') }}<br />
    <strong>Membership Start Date:</strong> {{ $formatDate($user->membership_starts_at) }}<br />
    <strong>Membership End Date:</strong> {{ $formatDate($user->membership_ends_at ?? $user->membership_expiry) }}<br />
    <strong>Updated By Admin:</strong> {{ $updatedBy ?: 'Peers Global Admin' }}<br />
    <strong>Update Timestamp:</strong> {{ now()->format('d M Y h:i A') }}<br /><br />

    Support: <a href="mailto:pravin@peersunity.com" style="color:#38bdf8; text-decoration:underline;">pravin@peersunity.com</a><br /><br />
    Warm Regards,<br />
    Peers Global Unity
@endsection
