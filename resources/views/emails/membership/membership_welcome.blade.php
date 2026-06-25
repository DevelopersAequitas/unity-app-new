@php
    $memberName = $user->display_name ?: trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: 'Peer';
    $formatDate = static function ($value) {
        if (blank($value)) {
            return '—';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d M Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    };
@endphp

@include('emails.membership.partials.dark_card', [
    'title' => 'Welcome to Peers Global Unity',
    'greetingName' => $memberName,
    'message' => 'Welcome to Peers Global Unity. We are pleased to confirm that your membership has been successfully activated. Your welcome kit and membership documents are attached for your reference. Thank you for joining the Peers Global community. We look forward to your active participation and growth journey with us.',
    'rows' => [
        'Peer Name' => $memberName,
        'Email' => $user->email ?: '—',
        'Membership Status' => \Illuminate\Support\Str::headline(str_replace('_', ' ', (string) ($user->membership_status ?: '—'))),
        'Membership Start Date' => $formatDate($user->membership_starts_at ?? null),
        'Membership Expiry Date' => $formatDate($user->membership_ends_at ?? $user->membership_expiry ?? null),
        'Plan Code / Membership Plan' => $user->zoho_plan_code ?: '—',
        'Activated At' => now()->format('d M Y h:i A'),
    ],
])
