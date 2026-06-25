@php
    $memberName = $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'Peer';
@endphp

@include('emails.membership.partials.dark_card', [
    'title' => 'Membership Expired',
    'greetingName' => $memberName,
    'message' => 'Your membership has expired. Please renew your membership to continue enjoying Peers Global benefits and services.',
    'rows' => [],
])
