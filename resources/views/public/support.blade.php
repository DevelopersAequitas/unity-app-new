<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support & Help Center | Peers Global Unity</title>
    <meta name="description" content="Official Help Desk and Support Portal for Peers Global Unity. Submit tickets, track issues, or contact our customer support team.">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v={{ time() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: dark;
            --bg-start: #0a0f1f;
            --bg-end: #101833;
            --card-bg: rgba(255, 255, 255, 0.05);
            --card-border: rgba(255, 255, 255, 0.12);
            --card-hover-border: rgba(107, 228, 255, 0.35);
            --text-primary: #f5f7ff;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --accent: #6be4ff;
            --accent-strong: #0284c7;
            --accent-glow: rgba(107, 228, 255, 0.15);
            --input-bg: rgba(15, 23, 42, 0.65);
            --input-border: rgba(255, 255, 255, 0.15);
            --shadow-subtle: 0 20px 45px -10px rgba(0, 0, 0, 0.5);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: radial-gradient(circle at top center, #1e295d 0%, var(--bg-end) 40%, var(--bg-start) 100%);
            color: var(--text-primary);
            min-height: 100vh;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        a { color: var(--accent); text-decoration: none; transition: color 0.2s ease; }
        a:hover { color: #bae6fd; text-decoration: underline; }

        /* --- NAVBAR --- */
        .portal-nav {
            background: rgba(10, 15, 31, 0.75);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--card-border);
            padding: 16px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .brand-badge-box {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: inherit;
        }
        .brand-logo-img {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        .brand-title {
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: -0.01em;
            color: var(--text-primary);
            line-height: 1.2;
        }
        .brand-subtitle {
            font-size: 0.75rem;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-weight: 600;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-nav-outline {
            color: var(--text-primary);
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid var(--card-border);
            padding: 8px 18px;
            border-radius: 100px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .btn-nav-outline:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: var(--accent);
            color: #ffffff;
            text-decoration: none;
        }

        /* --- HERO --- */
        .hero-section {
            padding: 60px 0 35px;
            text-align: center;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 100px;
            font-size: 0.78rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            background: var(--accent-glow);
            color: var(--accent);
            border: 1px solid rgba(107, 228, 255, 0.25);
            font-weight: 700;
            margin-bottom: 20px;
        }
        .hero-title {
            font-size: clamp(2rem, 4.5vw, 3.2rem);
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.15;
            margin-bottom: 16px;
            background: linear-gradient(135deg, #ffffff 30%, #93c5fd 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-desc {
            font-size: clamp(1rem, 2vw, 1.15rem);
            color: var(--text-secondary);
            max-width: 680px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* --- CHANNEL CARDS --- */
        .channel-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 24px;
            height: 100%;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            backdrop-filter: blur(12px);
            display: flex;
            flex-direction: column;
        }
        .channel-card:hover {
            border-color: var(--card-hover-border);
            transform: translateY(-4px);
            box-shadow: 0 16px 32px -8px rgba(2, 132, 199, 0.25);
        }
        .channel-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(107, 228, 255, 0.1);
            border: 1px solid rgba(107, 228, 255, 0.2);
            color: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            margin-bottom: 16px;
        }
        .channel-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 6px;
        }
        .channel-desc {
            font-size: 0.88rem;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 14px;
            flex-grow: 1;
        }
        .channel-action {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--accent);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .channel-action:hover {
            color: #ffffff;
            text-decoration: none;
        }

        /* --- MAIN INTERACTIVE CONTAINER --- */
        .main-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 28px;
            box-shadow: var(--shadow-subtle);
            backdrop-filter: blur(20px);
            padding: 36px;
            position: relative;
        }

        /* Tabs */
        .nav-pills-support {
            display: flex;
            gap: 10px;
            background: rgba(15, 23, 42, 0.7);
            padding: 6px;
            border-radius: 100px;
            border: 1px solid var(--card-border);
            max-width: 440px;
            margin: 0 auto 32px;
        }
        .nav-pills-support .nav-link {
            flex: 1;
            text-align: center;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.9rem;
            padding: 10px 18px;
            border-radius: 100px;
            transition: all 0.2s ease;
            border: none;
            background: transparent;
        }
        .nav-pills-support .nav-link.active {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(2, 132, 199, 0.4);
        }

        /* Form Controls */
        .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .form-control, .form-select {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            color: var(--text-primary);
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }
        .form-control:focus, .form-select:focus {
            background: rgba(15, 23, 42, 0.9);
            border-color: var(--accent);
            color: #ffffff;
            box-shadow: 0 0 0 3px rgba(107, 228, 255, 0.15);
            outline: none;
        }
        .form-control::placeholder {
            color: var(--text-muted);
        }
        .form-select option {
            background: #0f172a;
            color: #f5f7ff;
        }

        .btn-submit-ticket {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: #ffffff;
            font-weight: 700;
            font-size: 1rem;
            border: none;
            border-radius: 14px;
            padding: 14px 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            transition: all 0.2s ease;
            box-shadow: 0 8px 24px -4px rgba(2, 132, 199, 0.5);
        }
        .btn-submit-ticket:hover {
            background: linear-gradient(135deg, #38bdf8 0%, #0284c7 100%);
            transform: translateY(-2px);
            box-shadow: 0 12px 28px -4px rgba(2, 132, 199, 0.65);
            color: #ffffff;
        }

        /* Success & Alert States */
        .ticket-alert-success {
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.35);
            border-radius: 18px;
            padding: 24px;
            margin-bottom: 28px;
            color: #e2e8f0;
        }
        .ticket-number-pill {
            font-family: monospace;
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            border: 1px solid rgba(52, 211, 153, 0.3);
        }

        /* Status Timeline / Card */
        .status-result-card {
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 28px;
            margin-top: 24px;
        }
        .badge-status {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 6px 14px;
            border-radius: 100px;
        }
        .badge-status-open { background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); }
        .badge-status-in_progress { background: rgba(251, 191, 36, 0.15); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.3); }
        .badge-status-resolved { background: rgba(52, 211, 153, 0.15); color: #34d399; border: 1px solid rgba(52, 211, 153, 0.3); }
        .badge-status-closed { background: rgba(148, 163, 184, 0.15); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.3); }

        /* FAQs Accordion */
        .accordion-item {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px !important;
            margin-bottom: 12px;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        .accordion-button {
            background: transparent;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 1rem;
            padding: 20px 24px;
            box-shadow: none !important;
        }
        .accordion-button:not(.collapsed) {
            background: rgba(255, 255, 255, 0.03);
            color: var(--accent);
        }
        .accordion-button::after {
            filter: invert(1);
        }
        .accordion-body {
            padding: 0 24px 22px;
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.7;
        }

        /* Footer */
        footer {
            margin-top: auto;
            border-top: 1px solid var(--card-border);
            padding: 32px 0;
            font-size: 0.88rem;
            color: var(--text-muted);
            text-align: center;
        }
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        .footer-links a {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
        }
        .footer-links a:hover {
            color: var(--accent);
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <header class="portal-nav">
        <div class="container d-flex align-items-center justify-content-between">
            <a href="{{ url('/') }}" class="brand-badge-box">
                <img src="{{ url('/api/v1/files/019be538-1251-705b-b26e-5460ee4ef526') }}" alt="Peers Logo" class="brand-logo-img" onerror="this.src='{{ asset('favicon.png') }}';">
                <div>
                    <div class="brand-title">Peers Global Unity</div>
                    <div class="brand-subtitle">Support & Help Desk</div>
                </div>
            </a>
            <div class="nav-actions">
                <a href="{{ url('/') }}" class="btn-nav-outline d-none d-sm-inline-flex align-items-center gap-2">
                    <i class="bi bi-phone"></i>
                    <span>Download App</span>
                </a>
                <a href="{{ route('account-deletion.show') }}" class="btn-nav-outline d-none d-md-inline-flex align-items-center gap-2">
                    <i class="bi bi-shield-lock"></i>
                    <span>Account Deletion</span>
                </a>
            </div>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-badge">
                <i class="bi bi-headset"></i> 24/7 Dedicated Assistance
            </div>
            <h1 class="hero-title">How can we help you today?</h1>
            <p class="hero-desc">
                Have questions about your Peers Global account, membership circles, events, or facing technical issues? Submit a ticket below or reach out to our team.
            </p>
        </div>
    </section>

    <!-- CONTENT WRAPPER -->
    <main class="container mb-5">

        <!-- QUICK CHANNELS -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="channel-card">
                    <div class="channel-icon">
                        <i class="bi bi-envelope-at"></i>
                    </div>
                    <div class="channel-title">Email Support</div>
                    <div class="channel-desc">
                        Direct communication with our operations & member success team. We respond within 24-48 business hours.
                    </div>
                    <a href="mailto:support@peersunity.com" class="channel-action">
                        <span>support@peersunity.com</span>
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="channel-card">
                    <div class="channel-icon">
                        <i class="bi bi-whatsapp"></i>
                    </div>
                    <div class="channel-title">WhatsApp & Phone</div>
                    <div class="channel-desc">
                        Connect with our helpline for urgent inquiries, membership onboarding, and circle queries.
                    </div>
                    <a href="https://wa.me/917016231097?text=Hi%2C%20I%20need%20support%20with%20Peers%20Global%20Unity" target="_blank" rel="noopener" class="channel-action">
                        <span>+91 70162 31097</span>
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="channel-card">
                    <div class="channel-icon">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="channel-title">Operating Hours</div>
                    <div class="channel-desc">
                        Mon – Sat: 10:00 AM – 7:00 PM IST.<br>
                        Need account deletion or data removal?
                    </div>
                    <a href="{{ route('account-deletion.show') }}" class="channel-action">
                        <span>Privacy & Data Request</span>
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- MAIN INTERACTIVE SECTION -->
        <div class="main-card mb-5">

            <!-- TICKET SUBMITTED SUCCESS BANNER -->
            @if(session('ticket_success'))
                <div class="ticket-alert-success text-center">
                    <div class="d-inline-flex align-items-center justify-content-center bg-success text-white rounded-circle mb-3" style="width: 48px; height: 48px; font-size: 1.5rem;">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <h3 class="fw-bold mb-2 text-white">{{ session('ticket_success') }}</h3>
                    <p class="mb-3 text-light">We have emailed your confirmation to <strong>{{ session('ticket_email') }}</strong>. Please save your reference ticket number:</p>
                    <div class="mb-3">
                        <span class="ticket-number-pill">{{ session('ticket_number') }}</span>
                    </div>
                    <p class="small text-secondary mb-0">Our support engineers will review your request and get back to you shortly.</p>
                </div>
            @endif

            <!-- NAVIGATION PILLS -->
            <ul class="nav nav-pills-support" id="supportTabs" role="tablist">
                <li class="nav-item flex-fill" role="presentation">
                    <button class="nav-link w-100 {{ (!isset($searchAttempted) && !request()->filled('ticket_number')) ? 'active' : '' }}" id="new-ticket-tab" data-bs-toggle="pill" data-bs-target="#new-ticket-pane" type="button" role="tab" aria-controls="new-ticket-pane" aria-selected="{{ (!isset($searchAttempted) && !request()->filled('ticket_number')) ? 'true' : 'false' }}">
                        <i class="bi bi-plus-circle me-1.5"></i> Submit Ticket
                    </button>
                </li>
                <li class="nav-item flex-fill" role="presentation">
                    <button class="nav-link w-100 {{ (isset($searchAttempted) || request()->filled('ticket_number')) ? 'active' : '' }}" id="track-ticket-tab" data-bs-toggle="pill" data-bs-target="#track-ticket-pane" type="button" role="tab" aria-controls="track-ticket-pane" aria-selected="{{ (isset($searchAttempted) || request()->filled('ticket_number')) ? 'true' : 'false' }}">
                        <i class="bi bi-search me-1.5"></i> Track Ticket
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="supportTabsContent">

                <!-- TAB 1: SUBMIT TICKET FORM -->
                <div class="tab-pane fade {{ (!isset($searchAttempted) && !request()->filled('ticket_number')) ? 'show active' : '' }}" id="new-ticket-pane" role="tabpanel" aria-labelledby="new-ticket-tab" tabindex="0">
                    
                    @if ($errors->any() && (!isset($searchAttempted) && !request()->filled('ticket_number')))
                        <div class="alert alert-danger rounded-4 mb-4">
                            <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i>Please check the highlighted fields:</h6>
                            <ul class="mb-0 ps-3 small">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('support.submit') }}" method="POST" enctype="multipart/form-data" id="supportTicketForm">
                        @csrf

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="contact_name" class="form-label">
                                    <span>Your Full Name <span class="text-danger">*</span></span>
                                </label>
                                <input type="text" class="form-control @error('contact_name') is-invalid @enderror" id="contact_name" name="contact_name" value="{{ old('contact_name', $user?->name ?? $user?->display_name ?? '') }}" placeholder="e.g. Rahul Sharma" required>
                                @error('contact_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">
                                    <span>Email Address <span class="text-danger">*</span></span>
                                </label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user?->email ?? '') }}" placeholder="name@example.com" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="phone" class="form-label">Phone Number (Optional)</label>
                                <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $user?->phone ?? '') }}" placeholder="+91 98765 43210">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="category" class="form-label">Issue Category</label>
                                <select class="form-select @error('category') is-invalid @enderror" id="category" name="category">
                                    <option value="" disabled {{ old('category') ? '' : 'selected' }}>Select a category</option>
                                    <option value="Account & Login" {{ old('category') == 'Account & Login' ? 'selected' : '' }}>Account & Login (OTP, Profile)</option>
                                    <option value="Membership & Circles" {{ old('category') == 'Membership & Circles' ? 'selected' : '' }}>Membership & Circles</option>
                                    <option value="Events & QR Passes" {{ old('category') == 'Events & QR Passes' ? 'selected' : '' }}>Events & QR Entry Passes</option>
                                    <option value="Store & Payments" {{ old('category') == 'Store & Payments' ? 'selected' : '' }}>Store, Coins & Payments</option>
                                    <option value="Technical Bug" {{ old('category') == 'Technical Bug' ? 'selected' : '' }}>Technical Bug / App Glitch</option>
                                    <option value="General Inquiry" {{ old('category') == 'General Inquiry' ? 'selected' : '' }}>General Inquiries & Feedback</option>
                                </select>
                                @error('category')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select @error('priority') is-invalid @enderror" id="priority" name="priority">
                                    <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>Low - Non-urgent query</option>
                                    <option value="normal" {{ old('priority', 'normal') == 'normal' ? 'selected' : '' }}>Normal - Standard assistance</option>
                                    <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>High - Impeding daily usage</option>
                                    <option value="urgent" {{ old('priority') == 'urgent' ? 'selected' : '' }}>Urgent - Event / Account blocked</option>
                                </select>
                                @error('priority')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="subject" class="form-label">
                                <span>Subject <span class="text-danger">*</span></span>
                            </label>
                            <input type="text" class="form-control @error('subject') is-invalid @enderror" id="subject" name="subject" value="{{ old('subject') }}" placeholder="Brief summary of your question or issue" required>
                            @error('subject')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">
                                <span>Detailed Description <span class="text-danger">*</span></span>
                                <span class="text-muted small">Min 10 characters</span>
                            </label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="5" placeholder="Please describe what happened, steps to reproduce, or details of your request..." required>{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="media" class="form-label">
                                <span>Attach File or Screenshot (Optional)</span>
                                <span class="text-muted small">JPG, PNG, WEBP, PDF, MP4 (Max 20MB)</span>
                            </label>
                            <input type="file" class="form-control @error('media') is-invalid @enderror" id="media" name="media" accept="image/*,video/*,application/pdf">
                            @error('media')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn-submit-ticket" id="submitBtn">
                            <i class="bi bi-send-fill"></i>
                            <span>Submit Support Ticket</span>
                        </button>
                    </form>
                </div>

                <!-- TAB 2: TRACK TICKET STATUS -->
                <div class="tab-pane fade {{ (isset($searchAttempted) || request()->filled('ticket_number')) ? 'show active' : '' }}" id="track-ticket-pane" role="tabpanel" aria-labelledby="track-ticket-tab" tabindex="0">
                    
                    <p class="text-secondary mb-4">
                        Already submitted a ticket? Enter your reference ticket number (e.g. <code>SUP-20261020-0001</code>) and the email address used during submission to check real-time progress.
                    </p>

                    <form action="{{ route('support.check-status') }}" method="POST">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="track_ticket_number" class="form-label">Ticket Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('ticket_number') is-invalid @enderror" id="track_ticket_number" name="ticket_number" value="{{ old('ticket_number', $searchedTicketNumber ?? request('ticket_number')) }}" placeholder="e.g. SUP-20261020-0001" required>
                                @error('ticket_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="track_email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" id="track_email" name="email" value="{{ old('email', $searchedEmail ?? request('email', $user?->email ?? '')) }}" placeholder="name@example.com" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn-submit-ticket">
                                <i class="bi bi-search"></i>
                                <span>Check Ticket Status</span>
                            </button>
                        </div>
                    </form>

                    <!-- SEARCH RESULTS -->
                    @if(isset($searchAttempted) || isset($trackedTicket))
                        @if($trackedTicket)
                            <div class="status-result-card">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom pb-3 mb-3">
                                    <div>
                                        <div class="small text-secondary mb-1">Ticket Reference</div>
                                        <h4 class="fw-bold mb-0 text-white font-monospace">{{ $trackedTicket->ticket_number }}</h4>
                                    </div>
                                    <div>
                                        @php
                                            $status = strtolower($trackedTicket->status ?? 'open');
                                            $statusClass = match($status) {
                                                'open' => 'badge-status-open',
                                                'in_progress' => 'badge-status-in_progress',
                                                'resolved' => 'badge-status-resolved',
                                                'closed' => 'badge-status-closed',
                                                default => 'badge-status-open',
                                            };
                                            $statusLabel = match($status) {
                                                'open' => 'Open',
                                                'in_progress' => 'In Progress',
                                                'resolved' => 'Resolved',
                                                'closed' => 'Closed',
                                                default => ucfirst($status),
                                            };
                                        @endphp
                                        <span class="badge-status {{ $statusClass }}">
                                            <i class="bi bi-record-fill me-1"></i> {{ $statusLabel }}
                                        </span>
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <span class="text-secondary small d-block">Subject</span>
                                        <span class="fw-semibold text-white">{{ $trackedTicket->subject }}</span>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="text-secondary small d-block">Submitted On</span>
                                        <span class="text-light">{{ $trackedTicket->created_at ? $trackedTicket->created_at->format('d M Y, h:i A') : '—' }}</span>
                                    </div>
                                    <div class="col-md-3">
                                        <span class="text-secondary small d-block">Priority</span>
                                        <span class="text-uppercase fw-semibold text-light small">{{ $trackedTicket->priority ?? 'normal' }}</span>
                                    </div>
                                </div>

                                <div class="bg-black bg-opacity-25 p-3 rounded-3 mb-3 border border-secondary border-opacity-25">
                                    <span class="text-secondary small d-block mb-1">Inquiry Details:</span>
                                    <div class="text-light small text-break" style="white-space: pre-wrap;">{{ $trackedTicket->description }}</div>
                                </div>

                                @if($trackedTicket->admin_note)
                                    <div class="bg-primary bg-opacity-10 border border-primary border-opacity-25 p-3 rounded-3">
                                        <span class="text-info fw-semibold small d-block mb-1">
                                            <i class="bi bi-info-circle-fill me-1"></i> Support Update / Resolution:
                                        </span>
                                        <div class="text-white small text-break">{{ $trackedTicket->admin_note }}</div>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="alert alert-warning rounded-4 mt-4 text-center">
                                <i class="bi bi-exclamation-circle-fill fs-3 d-block mb-2"></i>
                                <h6 class="fw-bold mb-1">No ticket found</h6>
                                <p class="small mb-0 text-dark">
                                    We could not find any support ticket matching reference <strong>"{{ $searchedTicketNumber ?? '' }}"</strong> and email <strong>"{{ $searchedEmail ?? '' }}"</strong>. Please double-check your inputs or submit a new ticket.
                                </p>
                            </div>
                        @endif
                    @endif

                </div>

            </div>
        </div>

        <!-- FREQUENTLY ASKED QUESTIONS -->
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="text-center mb-4">
                    <span class="hero-badge"><i class="bi bi-question-circle"></i> Self-Service Help</span>
                    <h3 class="fw-bold text-white">Frequently Asked Questions</h3>
                    <p class="text-secondary small">Quick answers to common questions about Peers Global Unity.</p>
                </div>

                <div class="accordion" id="faqAccordion">

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                How do I log in or fix OTP issues on the Peers app?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Make sure your registered mobile number is entered with the correct country code (+91 for India). Check your SMS spam or WhatsApp for the verification OTP. If you continue experiencing issues, submit a ticket above with your phone number and we will inspect your account status.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                How do event registration and entry passes work?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                After registering for a Peers Global event, a unique QR code entry pass is generated in your app and emailed to you. Simply display the QR pass at the venue registration desk for immediate check-in.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                How do I join a Circle or Chapter?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                You can browse available business circles in your city or industry through the Peers mobile application. Tap "Join Circle" or request an introduction from an active chapter leader.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                How do I request account deletion or data removal?
                            </button>
                        </h2>
                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                We fully respect your privacy and data autonomy. You can submit an official account deletion and data removal request directly via our <a href="{{ route('account-deletion.show') }}">Account Deletion Request Portal</a> at any time.
                            </div>
                        </div>
                    </div>

                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                What is the average response time for tickets?
                            </button>
                        </h2>
                        <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Our support team processes inquiries during business hours (Monday to Saturday, 10:00 AM to 7:00 PM IST). Normal inquiries are answered within 24 to 48 business hours. Urgent issues related to live events are expedited.
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </main>

    <!-- FOOTER -->
    <footer>
        <div class="container">
            <div class="footer-links">
                <a href="{{ url('/') }}">Download App</a>
                <span>•</span>
                <a href="{{ route('support.show') }}">Support & Help Desk</a>
                <span>•</span>
                <a href="{{ route('account-deletion.show') }}">Account Deletion</a>
                <span>•</span>
                <a href="mailto:support@peersunity.com">support@peersunity.com</a>
            </div>
            <div>
                © {{ date('Y') }} Peers Global Unity • Vyapaar Jagat. All Rights Reserved.
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Form Submit Spinner -->
    <script>
        document.getElementById('supportTicketForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Submitting Ticket...';
            }
        });
    </script>

    <!-- Zoho SalesIQ Widget -->
    <script>window.$zoho=window.$zoho || {};$zoho.salesiq=$zoho.salesiq||{ready:function(){}}</script>
    <script id="zsiqscript" src="https://salesiq.zohopublic.in/widget?wc=siq562ff461f3e0bb558e823904742f628af186f76d1cd9ee7dbedb3fd935b95ee10481df70ef316a7d25f11c1bc50fa6ee" defer></script>
</body>
</html>
