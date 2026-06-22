@php
    $welcomeStatusRaw = strtolower((string) ($user->welcome_membership_email_status ?? ''));
    $welcomeSent = filled($user->welcome_membership_email_sent_at) && $welcomeStatusRaw === 'sent';
    $welcomeStatus = $welcomeStatusRaw ?: ($welcomeSent ? 'sent' : 'not_sent');
    $welcomeSentLabel = $welcomeSent ? 'Yes' : 'No';
    $welcomeSentAt = $welcomeSent ? optional($user->welcome_membership_email_sent_at)->format('Y-m-d H:i:s') : '—';
    $welcomePlanCode = $user->welcome_membership_email_plan_code ?: '—';
    $welcomeErrorRaw = trim((string) ($user->welcome_membership_email_error ?? ''));
    $welcomeError = $welcomeErrorRaw !== '' ? $welcomeErrorRaw : '—';
    $welcomeErrorPreview = \Illuminate\Support\Str::limit($welcomeError, 180);
    $statusBadgeClass = match ($welcomeStatus) {
        'sent' => 'bg-success-subtle text-success',
        'failed' => 'bg-danger-subtle text-danger',
        default => 'bg-warning-subtle text-warning',
    };
    $statusLabel = match ($welcomeStatus) {
        'sent' => 'Sent',
        'failed' => 'Failed',
        default => 'Not Sent',
    };

    $showSendButton = ($showSendButton ?? false) && ! $welcomeSent;
    $sendButtonClass = $sendButtonClass ?? 'btn btn-outline-primary btn-sm';
    $sendButtonLabel = $sendButtonLabel ?? 'Send Welcome Mail';
@endphp

<div class="card {{ $cardClass ?? '' }}">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center {{ $headerClass ?? '' }}">
        <span>Membership Welcome Email</span>
        <span class="badge {{ $statusBadgeClass }}">{{ $statusLabel }}</span>
    </div>

    <div class="card-body {{ $bodyClass ?? '' }}">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="small text-muted">Welcome Email Sent</div>
                <div class="fw-semibold">{{ $welcomeSentLabel }}</div>
            </div>
            <div class="col-md-4">
                <div class="small text-muted">Sent At</div>
                <div class="fw-semibold">{{ $welcomeSentAt }}</div>
            </div>
            <div class="col-md-4">
                <div class="small text-muted">Status</div>
                <div><span class="badge {{ $statusBadgeClass }}">{{ $statusLabel }}</span></div>
            </div>
            <div class="col-md-4">
                <div class="small text-muted">Plan Code At Send</div>
                <div class="fw-semibold">{{ $welcomePlanCode }}</div>
            </div>
            <div class="col-md-8">
                <div class="small text-muted">Last Error</div>
                @if ($welcomeErrorRaw !== '')
                    <div class="text-break text-danger" title="{{ $welcomeError }}">{{ $welcomeErrorPreview }}</div>
                    @if ($welcomeErrorPreview !== $welcomeError)
                        <details class="mt-1">
                            <summary class="small text-muted">Show full error</summary>
                            <pre class="small text-danger text-break bg-light border rounded p-2 mt-1 mb-0" style="white-space: pre-wrap;">{{ $welcomeError }}</pre>
                        </details>
                    @endif
                @else
                    <div>—</div>
                @endif
            </div>
            <div class="col-12 d-flex justify-content-end">
                @if ($welcomeSent)
                    <button type="button" class="btn btn-success btn-sm" disabled>Already Sent</button>
                @elseif ($showSendButton)
                    @if (! empty($sendFormId ?? null))
                        <button
                            type="submit"
                            form="{{ $sendFormId }}"
                            class="{{ $sendButtonClass }}"
                            onclick="return confirm('Send membership welcome email now?');"
                        >
                            {{ $sendButtonLabel }}
                        </button>
                    @else
                        <form method="POST" action="{{ route('admin.users.membership-welcome-email.send', $user->id) }}">
                            @csrf
                            <button
                                type="submit"
                                class="{{ $sendButtonClass }}"
                                onclick="return confirm('Send membership welcome email now?');"
                            >
                                {{ $sendButtonLabel }}
                            </button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
