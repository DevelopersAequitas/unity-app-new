@php
    $memberName = $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'Peer';
@endphp

@include('emails.membership.partials.dark_card', [
    'title' => 'Membership Status Updated',
    'greetingName' => $memberName,
    'message' => 'Your membership status has been updated. If you have any questions, please contact the Peers Global Team.',
    'rows' => [
        'Status' => $statusLabel,
    ],
])
