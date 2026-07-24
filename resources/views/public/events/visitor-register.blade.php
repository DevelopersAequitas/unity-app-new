<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $event->title }} – Visitor Registration | Peers Global Unity</title>
    <meta name="description" content="Register as a visitor for {{ $event->title }}. Official Peers Global Unity corporate event registration.">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v={{ time() }}">
    <style>
        /* ==========================================================
           PEERS GLOBAL UNITY — CLEAN CORPORATE REGISTRATION LAYOUT
           ========================================================== */
        :root {
            /* Palette System */
            --navy-dark:     #0c1222;
            --navy-mid:      #111a2e;
            --navy-light:    #1e2d4a;
            --royal:         #2563eb;
            --royal-dark:    #1d4ed8;
            --royal-light:   #3b82f6;
            --royal-subtle:  rgba(37, 99, 235, 0.08);
            --royal-glow:    rgba(37, 99, 235, 0.18);
            --gold:          #d97706;
            --gold-subtle:   rgba(217, 119, 6, 0.1);

            /* Alert Systems */
            --success:       #10b981;
            --success-subtle:rgba(16, 185, 129, 0.08);
            --success-border:rgba(16, 185, 129, 0.3);
            --danger:        #ef4444;
            --danger-subtle: rgba(239, 68, 68, 0.07);
            --danger-border: rgba(239, 68, 68, 0.25);

            /* Surfaces & Borders */
            --bg-page:       #f4f7fb;
            --bg-card:       #ffffff;
            --bg-muted:      #f8fafc;
            --bg-field:      #ffffff;
            --bg-field-focus:#fafcff;
            --border:        #e2e8f0;
            --border-light:  #f1f5f9;
            --border-mid:    #cbd5e1;

            /* Text Colors */
            --text-primary:  #0f172a;
            --text-secondary:#334155;
            --text-muted:    #64748b;
            --text-light:    #94a3b8;

            /* Elevation */
            --shadow-xs:     0 1px 2px rgba(0,0,0,.04);
            --shadow-card:   0 1px 3px rgba(0,0,0,.05), 0 1px 2px rgba(0,0,0,.03);
            --shadow-lg:     0 10px 25px -5px rgba(0,0,0,.06), 0 4px 10px -5px rgba(0,0,0,.04);
            --shadow-royal:  0 4px 16px rgba(37,99,235,.2);

            --r-md:  8px;
            --r-lg:  12px;
            --r-xl:  16px;

            --ease:     cubic-bezier(.4,0,.2,1);
            --duration: 200ms;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Plus Jakarta Sans', 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-page);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .vr-shell {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .vr-container {
            width: calc(100% - 32px);
            max-width: 1000px;
            margin-inline: auto;
        }

        /* ==========================================================
           1. COMPACT PAGE HEADER
           ========================================================== */
        .vr-header {
            background: #0c1222;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            box-shadow: 0 2px 8px rgba(0,0,0,.25);
            position: sticky;
            top: 0;
            z-index: 200;
            min-height: 72px;
        }

        .vr-header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 0;
            gap: 16px;
            flex-wrap: wrap;
            min-height: 72px;
        }

        .vr-header-brand {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            flex-shrink: 0;
        }

        .logo-image {
            display: block;
            height: 50px;
            width: auto;
            max-width: 200px;
            object-fit: contain;
            vertical-align: middle;
        }

        .vr-header-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: rgba(255,255,255,0.75);
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 999px;
            padding: 5px 14px 5px 10px;
            white-space: nowrap;
        }

        .vr-header-badge-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #d97706;
            flex-shrink: 0;
        }

        /* ==========================================================
           2. MAIN CONTENT FLOW
           ========================================================== */
        .vr-main {
            flex: 1;
            padding: 24px 0 48px;
        }

        .vr-stack {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* ── Event Banner Card (Uncropped Full Image) ── */
        .event-banner-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: var(--r-xl);
            box-shadow: var(--shadow-card);
            padding: 6px;
            overflow: hidden;
        }

        .event-banner-media {
            width: 100%;
            border-radius: calc(var(--r-xl) - 4px);
            overflow: hidden;
            background: var(--navy-mid);
            line-height: 0;
        }

        .event-banner-image {
            display: block;
            width: 100%;
            height: auto;
            object-fit: contain;
        }

        .event-banner-default {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 180px;
            padding: 36px 24px;
            background: linear-gradient(135deg, var(--navy-dark) 0%, var(--navy-mid) 60%, var(--navy-light) 100%);
            border-radius: calc(var(--r-xl) - 4px);
            text-align: center;
        }

        .event-banner-default-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 6px;
        }

        .event-banner-default-desc {
            font-size: .88rem;
            color: rgba(255,255,255,0.7);
            max-width: 520px;
        }

        /* ── Event Information Card ── */
        .vr-info-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: var(--r-xl);
            box-shadow: var(--shadow-card);
            padding: 24px 28px;
        }

        .vr-info-header {
            margin-bottom: 20px;
        }

        .vr-info-title {
            font-size: clamp(1.3rem, 2.5vw, 1.65rem);
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1.3;
            letter-spacing: -.02em;
            margin-bottom: 6px;
        }

        .vr-info-desc {
            font-size: .9rem;
            color: var(--text-muted);
            line-height: 1.65;
        }

        .vr-details-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            padding-top: 18px;
            border-top: 1px solid var(--border-light);
        }

        .vr-detail-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 14px;
            background: var(--bg-muted);
            border: 1px solid var(--border-light);
            border-radius: var(--r-lg);
            min-height: 72px;
        }

        .vr-detail-icon {
            width: 34px;
            height: 34px;
            flex-shrink: 0;
            border-radius: var(--r-md);
            background: var(--royal-subtle);
            color: var(--royal);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 1px;
        }

        .vr-detail-icon.gold {
            background: var(--gold-subtle);
            color: var(--gold);
        }

        .vr-detail-icon svg {
            width: 16px;
            height: 16px;
            stroke-width: 2;
        }

        .vr-detail-content {
            min-width: 0;
            flex: 1;
        }

        .vr-detail-lbl {
            font-size: .65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--text-light);
            margin-bottom: 2px;
            white-space: nowrap;
        }

        .vr-detail-val {
            font-size: .84rem;
            font-weight: 600;
            color: var(--text-secondary);
            line-height: 1.4;
            word-break: break-word;
        }

        .vr-detail-val.gold { color: var(--gold); font-weight: 700; }

        .ep-link {
            color: var(--royal);
            text-decoration: none;
            font-weight: 600;
        }
        .ep-link:hover { text-decoration: underline; }

        /* ==========================================================
           3. REGISTRATION FORM CARD
           ========================================================== */
        .vr-form-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: var(--r-xl);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        .vr-form-accent {
            height: 3px;
            background: linear-gradient(90deg, var(--navy-dark) 0%, var(--royal-dark) 40%, var(--royal) 70%, var(--royal-light) 100%);
        }

        .vr-form-header {
            padding: 24px 28px 18px;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .vr-form-heading {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -.02em;
            margin-bottom: 2px;
        }

        .vr-form-subtext {
            font-size: .85rem;
            color: var(--text-muted);
        }

        .vr-secure-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: .68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--success);
            background: var(--success-subtle);
            border: 1px solid var(--success-border);
            border-radius: 999px;
            padding: 5px 13px 5px 10px;
            white-space: nowrap;
        }
        .vr-secure-badge svg { width: 13px; height: 13px; stroke-width: 2.2; flex-shrink: 0; }

        .vr-form-body {
            padding: 24px 28px 28px;
        }

        .vr-error-banner {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 16px;
            background: var(--danger-subtle);
            border: 1px solid var(--danger-border);
            border-radius: var(--r-md);
            margin-bottom: 20px;
            font-size: .85rem;
            font-weight: 600;
            color: #991b1b;
            line-height: 1.5;
        }
        .vr-error-banner svg { width: 18px; height: 18px; stroke-width: 2; flex-shrink: 0; margin-top: 1px; }

        /* ── Grid & Form Controls ── */
        .vr-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px 18px;
        }

        .col-full { grid-column: 1 / -1; }

        .vr-field {
            display: flex;
            flex-direction: column;
            gap: 5px;
            width: 100%;
        }

        .vr-label {
            font-size: .78rem;
            font-weight: 700;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 3px;
        }
        .vr-label .req { color: var(--danger); font-size: .8rem; }

        .vr-input-wrap {
            position: relative;
            width: 100%;
        }

        .vr-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            pointer-events: none;
            display: flex;
            align-items: center;
            line-height: 0;
        }
        .vr-icon svg { width: 16px; height: 16px; stroke-width: 2; }
        .vr-textarea-icon { top: 15px; transform: none; }

        .vr-input,
        .vr-select,
        .vr-textarea {
            width: 100%;
            height: 48px;
            padding: 0 14px 0 44px;
            font-family: inherit;
            font-size: .875rem;
            color: var(--text-primary);
            background: var(--bg-field);
            border: 1px solid var(--border-mid);
            border-radius: var(--r-md);
            outline: none;
            -webkit-appearance: none;
            transition: border-color var(--duration) var(--ease),
                        box-shadow var(--duration) var(--ease),
                        background var(--duration) var(--ease);
        }

        .vr-input::placeholder,
        .vr-textarea::placeholder { color: var(--text-light); }

        .vr-input:focus,
        .vr-select:focus,
        .vr-textarea:focus {
            border-color: var(--royal);
            box-shadow: 0 0 0 3.5px var(--royal-glow);
            background: var(--bg-field-focus);
        }

        .vr-input.is-err,
        .vr-textarea.is-err { border-color: var(--danger); }
        .vr-input.is-err:focus,
        .vr-textarea.is-err:focus { box-shadow: 0 0 0 3.5px rgba(239,68,68,.18); }

        .vr-textarea {
            height: auto;
            min-height: 110px;
            padding: 14px 14px 14px 44px;
            resize: vertical;
            line-height: 1.6;
        }

        .vr-field-err {
            font-size: .75rem;
            font-weight: 600;
            color: var(--danger);
            display: flex;
            align-items: center;
            gap: 4px;
            line-height: 1.3;
        }
        .vr-field-err svg { width: 12px; height: 12px; stroke-width: 2.5; flex-shrink: 0; }

        /* ── Submit Row ── */
        .vr-submit-row {
            grid-column: 1 / -1;
            margin-top: 4px;
        }

        .vr-btn-submit {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            width: 100%;
            height: 50px;
            padding: 0 24px;
            font-family: inherit;
            font-size: .95rem;
            font-weight: 800;
            color: #ffffff;
            background: linear-gradient(135deg, var(--navy-dark) 0%, var(--royal-dark) 50%, var(--royal) 100%);
            border: none;
            border-radius: var(--r-md);
            cursor: pointer;
            box-shadow: var(--shadow-royal);
            transition: all var(--duration) var(--ease);
            letter-spacing: .01em;
        }

        .vr-btn-submit:hover {
            background: linear-gradient(135deg, var(--navy-mid) 0%, var(--royal-dark) 40%, var(--royal-light) 100%);
            box-shadow: 0 6px 22px rgba(37,99,235,.32);
            transform: translateY(-1px);
        }

        .vr-btn-submit:active { transform: translateY(0); }
        .vr-btn-submit:disabled { opacity: .65; cursor: wait; transform: none; }
        .vr-btn-submit svg { width: 17px; height: 17px; stroke-width: 2.5; flex-shrink: 0; }

        /* ── Trust Line ── */
        .vr-trust-line {
            grid-column: 1 / -1;
            text-align: center;
            font-size: .75rem;
            color: var(--text-muted);
            padding-top: 12px;
        }

        /* ==========================================================
           4. PAYMENT STATUS & PENDING CARDS
           ========================================================== */
        .vr-status-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: var(--r-xl);
            box-shadow: var(--shadow-lg);
            padding: 32px;
        }

        .vr-alert-header {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 20px;
        }

        .vr-alert-icon-wrap {
            width: 44px;
            height: 44px;
            border-radius: var(--r-md);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .vr-alert-icon-wrap.pending {
            background: var(--gold-subtle);
            color: var(--gold);
        }

        .vr-alert-icon-wrap.success {
            background: var(--success-subtle);
            color: var(--success);
        }

        .vr-alert-icon-wrap svg { width: 24px; height: 24px; stroke-width: 2; }

        .vr-alert-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: var(--text-primary);
            letter-spacing: -.02em;
            margin-bottom: 4px;
        }

        .vr-alert-sub {
            font-size: .88rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .vr-status-details {
            background: var(--bg-muted);
            border: 1px solid var(--border-light);
            border-radius: var(--r-lg);
            padding: 16px 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px 24px;
            margin-bottom: 24px;
        }

        .vr-status-prop {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .vr-status-prop-lbl {
            font-size: .66rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--text-light);
        }

        .vr-status-prop-val {
            font-size: .9rem;
            font-weight: 700;
            color: var(--text-primary);
        }

        .vr-pay-actions {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .vr-btn-pay {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 26px;
            background: var(--success);
            color: #ffffff;
            font-size: .9rem;
            font-weight: 800;
            border-radius: var(--r-md);
            text-decoration: none;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(16,185,129,.28);
            transition: background var(--duration) var(--ease);
        }
        .vr-btn-pay:hover { background: #059669; }
        .vr-btn-pay svg { width: 16px; height: 16px; stroke-width: 2.2; }

        .vr-safe-notice {
            padding: 14px 18px;
            background: rgba(245,158,11,0.08);
            border: 1px solid rgba(245,158,11,0.25);
            border-radius: var(--r-md);
            font-size: .86rem;
            color: #92400e;
            line-height: 1.5;
        }

        /* ==========================================================
           5. FOOTER
           ========================================================== */
        .vr-footer {
            padding: 24px 0 32px;
            margin-top: auto;
        }

        .vr-footer-inner {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .vr-footer-text {
            font-size: .76rem;
            font-weight: 600;
            color: var(--text-light);
            letter-spacing: .02em;
        }

        /* ==========================================================
           RESPONSIVE BREAKPOINTS
           ========================================================== */
        @media (max-width: 900px) {
            .vr-details-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 576px) {
            .vr-container { width: calc(100% - 24px); }
            .vr-main { padding: 16px 0 36px; }
            .vr-header-inner { flex-direction: row; align-items: center; gap: 8px; min-height: 60px; }
            .logo-image { height: 38px; max-width: 150px; }

            .vr-info-card { padding: 20px; }
            .vr-details-grid { grid-template-columns: minmax(0, 1fr); }

            .vr-form-header { padding: 18px 20px; flex-direction: column; align-items: flex-start; gap: 10px; }
            .vr-form-body { padding: 20px; }

            .vr-grid { grid-template-columns: minmax(0, 1fr); gap: 14px; }
            .vr-submit-row { grid-column: 1 / -1; }
            .vr-status-card { padding: 20px; }
        }
    </style>
</head>
<body>

@php
    /* ── Asset URLs with Fallbacks ── */
    $logoUrl         = asset('images/peersglobal-logo.png');
    $fallbackLogoUrl = asset('images/logo.png');

    /* ── Payment resolution ── */
    $paymentUrl = $payment['payment_url']
        ?? $payment['checkout_url']
        ?? $payment['zoho_checkout_url']
        ?? $payment['zoho_payment_link_url']
        ?? $payment['zoho_hosted_page_url']
        ?? $registration?->payment_url
        ?? $registration?->zoho_checkout_url
        ?? $registration?->zoho_payment_link_url
        ?? $registration?->zoho_hosted_page_url
        ?? null;

    $paymentStatus   = strtolower((string) ($payment['payment_status'] ?? $registration?->payment_status ?? ''));
    $isPaidStatus    = in_array($paymentStatus, ['paid', 'success', 'completed'], true);
    $paymentRequired = isset($payment['requires_payment'])
        ? (bool) $payment['requires_payment']
        : ((bool) ($registration?->payment_required ?? false)
            || (bool) ($event->is_paid ?? false)
            || (float) ($event->ticket_price ?? 0) > 0);
    $isPendingPayment = $paymentRequired && ! $isPaidStatus;

    /* ── Banner URL resolution ── */
    $bannerUrl = $event->banner_url ?? null;
    if (is_string($bannerUrl) && trim($bannerUrl) !== '') {
        $bannerUrl = trim($bannerUrl);
        if (! str_starts_with($bannerUrl, 'http://') && ! str_starts_with($bannerUrl, 'https://') && ! str_starts_with($bannerUrl, '/')) {
            $bannerUrl = url('/api/v1/files/'.$bannerUrl);
        }
    } else {
        $bannerUrl = null;
    }

    /* ── Event metadata ── */
    $eventMode  = strtolower((string) ($event->mode ?? ($event->is_virtual ? 'online' : 'offline')));
    $circleName = $event->circle?->name ?? null;
    $feeAmount  = (float) ($event->ticket_price ?? 0);
    $feeCurrency= strtoupper(data_get($event->metadata, 'currency', 'INR'));
    $hasFee     = $event->is_paid || $feeAmount > 0;

    /* ── Occurrence date & time ── */
    $startAt = $occurrence->start_at ?? $event->start_at ?? null;
    $endAt   = $occurrence->end_at   ?? null;
    $occDate = $occurrence->occurrence_date ?? null;
@endphp

<div class="vr-shell">

    {{-- ═══════════════════════════════════════════════
         1. COMPACT PAGE HEADER
    ════════════════════════════════════════════════ --}}
    <header class="vr-header">
        <div class="vr-container">
            <div class="vr-header-inner">
                <a href="#" class="vr-header-brand">
                    <img
                        src="{{ $logoUrl }}"
                        alt="Peers Global Unity"
                        class="logo-image"
                        onerror="this.onerror=null; this.src='{{ $fallbackLogoUrl }}';"
                    >
                </a>
                <div class="vr-header-badge">
                    <span class="vr-header-badge-dot"></span>
                    <span>Official Event Registration</span>
                </div>
            </div>
        </div>
    </header>

    {{-- ═══════════════════════════════════════════════
         2. MAIN CONTENT AREA
    ════════════════════════════════════════════════ --}}
    <main class="vr-main">
        <div class="vr-container">
            <div class="vr-stack">

                {{-- ── Event Banner Card (Uncropped Full Image) ── --}}
                <div class="event-banner-card">
                    <div class="event-banner-media">
                        @if($bannerUrl)
                            <img
                                class="event-banner-image"
                                src="{{ $bannerUrl }}"
                                alt="{{ $event->title }}"
                                loading="eager"
                            >
                        @else
                            <div class="event-banner-default">
                                <div class="event-banner-default-title">{{ $event->title }}</div>
                                @if($event->description)
                                    <div class="event-banner-default-desc">{{ $event->description }}</div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                {{-- ── Event Information Section ── --}}
                <div class="vr-info-card">
                    <div class="vr-info-header">
                        <h1 class="vr-info-title">{{ $event->title }}</h1>
                        @if($event->description)
                            <p class="vr-info-desc">{{ $event->description }}</p>
                        @endif
                    </div>

                    <div class="vr-details-grid">
                        {{-- 1. Date & Time --}}
                        <div class="vr-detail-item">
                            <div class="vr-detail-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            </div>
                            <div class="vr-detail-content">
                                <div class="vr-detail-lbl">Date & Time</div>
                                <div class="vr-detail-val">
                                    @if($startAt)
                                        {{ $startAt->format('d M Y, h:i A') }}@if($endAt) – {{ $endAt->format('h:i A') }}@endif
                                    @else
                                        To be announced
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- 2. Location --}}
                        <div class="vr-detail-item">
                            <div class="vr-detail-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            </div>
                            <div class="vr-detail-content">
                                <div class="vr-detail-lbl">Location</div>
                                <div class="vr-detail-val">
                                    @if($eventMode === 'online')
                                        @if($event->online_meeting_url)
                                            <a href="{{ $event->online_meeting_url }}" target="_blank" rel="noopener" class="text-primary text-decoration-none fw-semibold">Join Online Meeting ↗</a>
                                        @else
                                            Online Link to be shared
                                        @endif
                                    @else
                                        {{ $event->location_text ?: 'To be announced' }}
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- 3. Mode --}}
                        <div class="vr-detail-item">
                            <div class="vr-detail-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>
                            </div>
                            <div class="vr-detail-content">
                                <div class="vr-detail-lbl">Mode</div>
                                <div class="vr-detail-val">{{ ucfirst($eventMode) }}</div>
                            </div>
                        </div>

                        {{-- 4. Scope / Circle / Location Scope --}}
                        @php
                            $scopeLabel = 'Circle';
                            $scopeValue = $circleName ?? 'Peers Global Unity';
                            
                            $eventTypeLower = strtolower((string) ($event->event_type ?? ''));
                            if (str_contains($eventTypeLower, 'global') || $event->visibility === 'global') {
                                $scopeLabel = 'Scope';
                                $scopeValue = 'Global Event';
                            } elseif (str_contains($eventTypeLower, 'state')) {
                                $scopeLabel = 'State';
                                $scopeValue = $event->state_name ?: 'State Wide';
                            } elseif (str_contains($eventTypeLower, 'city') || $event->city_name || data_get($event->metadata, 'city')) {
                                $scopeLabel = 'City';
                                $scopeValue = $event->city_name ?: data_get($event->metadata, 'city') ?: 'City Wide';
                            }
                        @endphp
                        <div class="vr-detail-item">
                            <div class="vr-detail-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </div>
                            <div class="vr-detail-content">
                                <div class="vr-detail-lbl">{{ $scopeLabel }}</div>
                                <div class="vr-detail-val">{{ $scopeValue }}</div>
                            </div>
                        </div>

                        {{-- 5. Occurrence --}}
                        <div class="vr-detail-item">
                            <div class="vr-detail-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                            </div>
                            <div class="vr-detail-content">
                                <div class="vr-detail-lbl">Occurrence</div>
                                <div class="vr-detail-val">
                                    {{ ucfirst(trim((string) ($event->recurrence_type ?: 'Single Session'))) }}
                                </div>
                            </div>
                        </div>

                        {{-- 6. Fee --}}
                        @if($hasFee)
                        <div class="vr-detail-item">
                            <div class="vr-detail-icon gold">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                            </div>
                            <div class="vr-detail-content">
                                <div class="vr-detail-lbl">Registration Fee</div>
                                <div class="vr-detail-val gold">{{ $feeCurrency }} {{ number_format($feeAmount, 0) }}</div>
                            </div>
                        </div>
                        @else
                        <div class="vr-detail-item">
                            <div class="vr-detail-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            </div>
                            <div class="vr-detail-content">
                                <div class="vr-detail-lbl">Registration Fee</div>
                                <div class="vr-detail-val">Free Registration</div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- ═══════════════════════════════════════════════
                     3. PAYMENT STATUS & PENDING CARDS (Part 8)
                ════════════════════════════════════════════════ --}}
                @if($registration)
                    @if($isPendingPayment)
                        <div class="vr-status-card">
                            <div class="vr-alert-header">
                                <div class="vr-alert-icon-wrap pending">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                </div>
                                <div>
                                    <h2 class="vr-alert-title">Registration received — Payment pending</h2>
                                    <p class="vr-alert-sub">Your registration details have been securely saved. Please complete your fee payment to finalize your registration and receive your event entry pass.</p>
                                </div>
                            </div>

                            <div class="vr-status-details">
                                <div class="vr-status-prop">
                                    <span class="vr-status-prop-lbl">Registration ID</span>
                                    <span class="vr-status-prop-val">{{ $registration->id }}</span>
                                </div>
                                <div class="vr-status-prop">
                                    <span class="vr-status-prop-lbl">Status</span>
                                    <span class="vr-status-prop-val" style="text-transform:capitalize">{{ $payment['payment_status'] ?? $registration->payment_status ?? 'pending' }}</span>
                                </div>
                                <div class="vr-status-prop">
                                    <span class="vr-status-prop-lbl">Amount Due</span>
                                    <span class="vr-status-prop-val">{{ $payment['amount'] ?? $registration->amount ?? $event->ticket_price }} {{ strtoupper($payment['currency'] ?? $registration->currency ?? data_get($event->metadata,'currency','INR')) }}</span>
                                </div>
                            </div>

                            @if($paymentUrl)
                                <div class="vr-pay-actions">
                                    <a class="vr-btn-pay" href="{{ $paymentUrl }}" target="_blank" rel="noopener">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                                        Pay Fees Now
                                    </a>
                                </div>
                            @else
                                <div class="vr-safe-notice">
                                    We could not generate the payment link right now. Your registration has been saved. Please try again shortly.
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="vr-status-card">
                            <div class="vr-alert-header">
                                <div class="vr-alert-icon-wrap success">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                </div>
                                <div>
                                    <h2 class="vr-alert-title">{{ $isPaidStatus ? 'Payment Confirmed — Registration Complete!' : 'Registered Successfully!' }}</h2>
                                    <p class="vr-alert-sub">Your registration ID is <strong>{{ $registration->id }}</strong>. Your Event Entry Pass with QR Code has been sent to <strong>{{ $registration->visitor_email ?: $registration->user?->email }}</strong>.</p>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif

                @if($unavailableMessage)
                    <div class="vr-status-card">
                        <div class="vr-alert-header">
                            <div class="vr-alert-icon-wrap" style="background:var(--danger-subtle);color:var(--danger);">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            </div>
                            <div>
                                <h2 class="vr-alert-title">Registration Unavailable</h2>
                                <p class="vr-alert-sub">{{ $unavailableMessage }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- ═══════════════════════════════════════════════
                     4. REGISTRATION FORM CARD
                ════════════════════════════════════════════════ --}}
                @unless($registration || $unavailableMessage)
                <div class="vr-form-card">
                    <div class="vr-form-accent"></div>

                    {{-- Form Header --}}
                    <div class="vr-form-header">
                        <div>
                            <h2 class="vr-form-heading">Visitor Registration</h2>
                            <p class="vr-form-subtext">Register your details to attend this Peers Global Unity event.</p>
                        </div>
                        <div class="vr-secure-badge">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            Secure Registration
                        </div>
                    </div>

                    {{-- Form Body --}}
                    <div class="vr-form-body">
                        @if($errors->any())
                            <div class="vr-error-banner">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                <div>
                                    Please correct the highlighted fields to continue.
                                    @if($errors->has('registration'))
                                        {{ $errors->first('registration') }}
                                    @endif
                                </div>
                            </div>
                        @endif

                        <form id="visitor-registration-form" method="post" action="{{ url('/events/'.$event->id.'/occurrences/'.$occurrence->id.'/visitor-register') }}" novalidate>
                            @csrf
                            <div class="vr-grid">

                                {{-- First Name --}}
                                <div class="vr-field">
                                    <label class="vr-label" for="visitor_first_name">First Name <span class="req">*</span></label>
                                    <div class="vr-input-wrap">
                                        <span class="vr-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                                        <input
                                            id="visitor_first_name"
                                            name="visitor_first_name"
                                            class="vr-input @error('visitor_first_name') is-err @enderror"
                                            value="{{ old('visitor_first_name') }}"
                                            placeholder="Enter your first name"
                                            required
                                            autocomplete="given-name"
                                        >
                                    </div>
                                    @error('visitor_first_name')
                                        <div class="vr-field-err"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Last Name --}}
                                <div class="vr-field">
                                    <label class="vr-label" for="visitor_last_name">Last Name <span class="req">*</span></label>
                                    <div class="vr-input-wrap">
                                        <span class="vr-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
                                        <input
                                            id="visitor_last_name"
                                            name="visitor_last_name"
                                            class="vr-input @error('visitor_last_name') is-err @enderror"
                                            value="{{ old('visitor_last_name') }}"
                                            placeholder="Enter your last name"
                                            required
                                            autocomplete="family-name"
                                        >
                                    </div>
                                    @error('visitor_last_name')
                                        <div class="vr-field-err"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Email --}}
                                <div class="vr-field">
                                    <label class="vr-label" for="visitor_email">Email Address <span class="req">*</span></label>
                                    <div class="vr-input-wrap">
                                        <span class="vr-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
                                        <input
                                            id="visitor_email"
                                            type="email"
                                            name="visitor_email"
                                            class="vr-input @error('visitor_email') is-err @enderror"
                                            value="{{ old('visitor_email') }}"
                                            placeholder="you@example.com"
                                            required
                                            autocomplete="email"
                                        >
                                    </div>
                                    @error('visitor_email')
                                        <div class="vr-field-err"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Phone --}}
                                <div class="vr-field">
                                    <label class="vr-label" for="visitor_phone">Phone Number <span class="req">*</span></label>
                                    <div class="vr-input-wrap">
                                        <span class="vr-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
                                        <input
                                            id="visitor_phone"
                                            name="visitor_phone"
                                            class="vr-input @error('visitor_phone') is-err @enderror"
                                            value="{{ old('visitor_phone') }}"
                                            placeholder="+91 98765 43210"
                                            required
                                            autocomplete="tel"
                                        >
                                    </div>
                                    @error('visitor_phone')
                                        <div class="vr-field-err"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Company --}}
                                <div class="vr-field">
                                    <label class="vr-label" for="visitor_company">Company / Organisation <span class="req">*</span></label>
                                    <div class="vr-input-wrap">
                                        <span class="vr-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="9" y1="22" x2="9" y2="16"/><line x1="15" y1="22" x2="15" y2="16"/><line x1="9" y1="16" x2="15" y2="16"/></svg></span>
                                        <input
                                            id="visitor_company"
                                            name="visitor_company"
                                            class="vr-input @error('visitor_company') is-err @enderror"
                                            value="{{ old('visitor_company') }}"
                                            placeholder="Your company name"
                                            required
                                            autocomplete="organization"
                                        >
                                    </div>
                                    @error('visitor_company')
                                        <div class="vr-field-err"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- City --}}
                                <div class="vr-field">
                                    <label class="vr-label" for="visitor_city">City <span class="req">*</span></label>
                                    <div class="vr-input-wrap">
                                        <span class="vr-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
                                        <input
                                            id="visitor_city"
                                            name="visitor_city"
                                            class="vr-input @error('visitor_city') is-err @enderror"
                                            value="{{ old('visitor_city') }}"
                                            placeholder="City"
                                            required
                                            autocomplete="address-level2"
                                        >
                                    </div>
                                    @error('visitor_city')
                                        <div class="vr-field-err"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Designation --}}
                                <div class="vr-field">
                                    <label class="vr-label" for="visitor_designation">Designation</label>
                                    <div class="vr-input-wrap">
                                        <span class="vr-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg></span>
                                        <input
                                            id="visitor_designation"
                                            name="visitor_designation"
                                            class="vr-input @error('visitor_designation') is-err @enderror"
                                            value="{{ old('visitor_designation') }}"
                                            placeholder="CEO, Founder, Manager…"
                                            autocomplete="organization-title"
                                        >
                                    </div>
                                    @error('visitor_designation')
                                        <div class="vr-field-err"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Business Website --}}
                                <div class="vr-field col-full">
                                    <label class="vr-label" for="visitor_business_website">Business Website</label>
                                    <div class="vr-input-wrap">
                                        <span class="vr-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></span>
                                        <input
                                            id="visitor_business_website"
                                            type="url"
                                            name="visitor_business_website"
                                            class="vr-input @error('visitor_business_website') is-err @enderror"
                                            value="{{ old('visitor_business_website') }}"
                                            placeholder="https://yourcompany.com"
                                            autocomplete="url"
                                        >
                                    </div>
                                    @error('visitor_business_website')
                                        <div class="vr-field-err"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Business Brief --}}
                                <div class="vr-field col-full">
                                    <label class="vr-label" for="visitor_business_brief">Business Brief</label>
                                    <div class="vr-input-wrap">
                                        <span class="vr-icon vr-textarea-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
                                        <textarea
                                            id="visitor_business_brief"
                                            name="visitor_business_brief"
                                            class="vr-textarea @error('visitor_business_brief') is-err @enderror"
                                            placeholder="Briefly describe your business, products, or services…"
                                        >{{ old('visitor_business_brief') }}</textarea>
                                    </div>
                                    @error('visitor_business_brief')
                                        <div class="vr-field-err"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>{{ $message }}</div>
                                    @enderror
                                </div>

                                {{-- Submit Row --}}
                                <div class="vr-submit-row">
                                    <button id="submit-button" class="vr-btn-submit" type="submit">
                                        <span id="submit-label">Submit Registration</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                    </button>
                                </div>

                                {{-- Trust Line --}}
                                <div class="vr-trust-line">
                                    Your information is used only for event registration.
                                </div>

                            </div>{{-- /vr-grid --}}
                        </form>

                    </div>{{-- /vr-form-body --}}
                </div>{{-- /vr-form-card --}}
                @endunless

            </div>{{-- /vr-stack --}}
        </div>{{-- /vr-container --}}
    </main>

    {{-- ═══════════════════════════════════════════════
         5. FOOTER
    ════════════════════════════════════════════════ --}}
    <footer class="vr-footer">
        <div class="vr-container">
            <div class="vr-footer-inner">
                <span class="vr-footer-text">Powered by Peers Global Unity</span>
            </div>
        </div>
    </footer>

</div>{{-- /vr-shell --}}

<script>
(function () {
    'use strict';

    var form         = document.getElementById('visitor-registration-form');
    var submitButton = document.getElementById('submit-button');
    var submitLabel  = document.getElementById('submit-label');
    var mainSelect   = document.getElementById('visitor_business_category_main_id');
    var subSelect    = document.getElementById('visitor_business_category_sub_id');

    /* ── Duplicate-submission prevention (always active) ── */
    if (form && submitButton) {
        form.addEventListener('submit', function () {
            submitButton.disabled = true;
            if (submitLabel) { submitLabel.textContent = 'Submitting Registration…'; }
        });
    }

    /* ── Category select loader (only runs when the selects exist) ── */
    if (!mainSelect || !subSelect) { return; }

    var selectedMain = mainSelect.dataset.selected || mainSelect.value;
    var selectedSub  = subSelect.dataset.selected  || subSelect.value;

    function buildOptions(select, items, placeholder, selectedValue) {
        select.innerHTML = '';
        select.appendChild(new Option(placeholder, ''));
        items.forEach(function (item) {
            var opt = new Option(item.name, item.id);
            opt.selected = String(item.id) === String(selectedValue || '');
            select.appendChild(opt);
        });
    }

    function extractItems(payload, keys) {
        var data = (payload && payload.data) ? payload.data : payload;
        for (var i = 0; i < keys.length; i++) {
            if (Array.isArray(data && data[keys[i]])) { return data[keys[i]]; }
        }
        return Array.isArray(data && data.items) ? data.items : [];
    }

    function loadMainCategories() {
        fetch('/api/v1/circle-categories', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
            .then(function (payload) {
                var items = extractItems(payload, ['items']);
                if (items.length) { buildOptions(mainSelect, items, 'Select category', selectedMain); }
                if (mainSelect.value) { loadSubCategories(mainSelect.value, selectedSub); }
            })
            .catch(function (e) { console.warn('Unable to load categories', e); });
    }

    function loadSubCategories(mainId, selectedValue) {
        if (!mainId) { buildOptions(subSelect, [], 'Select sub category', null); return; }
        subSelect.disabled = true;
        fetch('/api/v1/circle-categories/' + encodeURIComponent(mainId), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
            .then(function (payload) {
                var items = extractItems(payload, ['level4_categories', 'sub_categories', 'children']);
                buildOptions(subSelect, items, 'Select sub category', selectedValue);
            })
            .catch(function (e) { console.warn('Unable to load sub categories', e); })
            .finally(function () { subSelect.disabled = false; });
    }

    mainSelect.addEventListener('change', function () { loadSubCategories(mainSelect.value, null); });
    loadMainCategories();
})();
</script>
</body>
</html>
