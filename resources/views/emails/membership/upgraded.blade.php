@extends('emails.layouts.email')

@section('title', 'Membership Updated')

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

    Your Peers Global Unity membership has been updated.<br /><br />

    @if($previousStatus !== $newStatus)
        <strong>Membership Status:</strong> Changed from <strong>{{ $label($previousStatus) }}</strong> to <strong>{{ $label($newStatus) }}</strong>.<br />
    @endif

    @if($changeType === 'increased')
        <strong>Membership Expiry Date:</strong> Extended (Increased) from <strong>{{ $formatDate($previousExpiryDate) }}</strong> to <strong>{{ $formatDate($newExpiryDate) }}</strong>.<br />
    @elseif($changeType === 'decreased')
        <strong>Membership Expiry Date:</strong> Updated (Decreased) from <strong>{{ $formatDate($previousExpiryDate) }}</strong> to <strong>{{ $formatDate($newExpiryDate) }}</strong>.<br />
    @endif

    @if(filled($remark))
        <strong>Reason / Remark:</strong> {{ $remark }}<br />
    @endif

    <br />
    <strong>Update Details:</strong><br />
    <table style="width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 20px;">
        <tr>
            <td style="padding: 6px 0; font-weight: bold; width: 180px; border-bottom: 1px solid #f3f4f6;">User Name:</td>
            <td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6;">{{ $peerName }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; font-weight: bold; border-bottom: 1px solid #f3f4f6;">Membership Type:</td>
            <td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6;">{{ $label($user->membership_type ?? $user->membership_status) }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; font-weight: bold; border-bottom: 1px solid #f3f4f6;">Membership Expiry Date:</td>
            <td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6;">{{ $formatDate($user->membership_ends_at ?? $user->membership_expiry) }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; font-weight: bold; border-bottom: 1px solid #f3f4f6;">Updated By Admin:</td>
            <td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6;">{{ $updatedBy ?: 'Peers Global Admin' }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 0; font-weight: bold; border-bottom: 1px solid #f3f4f6;">Update Timestamp:</td>
            <td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6;">{{ now()->format('d M Y h:i A') }}</td>
        </tr>
    </table>

    Support: <a href="mailto:pravin@peersunity.com" style="color:#38bdf8; text-decoration:underline;">pravin@peersunity.com</a><br /><br />
    Warm Regards,<br />
    Peers Global Unity
@endsection
