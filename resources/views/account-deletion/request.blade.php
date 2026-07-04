<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Account Deletion | Peers Global Unity</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --bg-light: #f4f8fa;
            --bg-gradient: linear-gradient(135deg, #eef5fc 0%, #ffffff 100%);
            --text-dark: #0f172a;
            --text-secondary: #334155;
            --text-muted: #64748b;
            
            --brand-primary: #0284c7; /* Blue */
            --brand-cyan: #0ea5e9; /* Cyan */
            --brand-glow: rgba(2, 132, 199, 0.08);
            --border-color: rgba(2, 132, 199, 0.15);
            
            --danger: #ef4444;
            --danger-bg: #fef2f2;
            --danger-border: #fecaca;
            --danger-text: #991b1b;
            
            --success-bg: #ecfdf5;
            --success-border: #d1fae5;
            --success-text: #065f46;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg-gradient);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 0;
            margin: 0;
        }

        .compliance-wrapper {
            width: 100%;
            max-width: 1140px;
            padding: 0 24px;
            margin: auto;
        }

        /* Card panels */
        .info-card, .deletion-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 44px;
            box-shadow: 0 15px 35px -10px rgba(2, 132, 199, 0.06), 0 0 30px rgba(2, 132, 199, 0.02);
            transition: all 0.3s ease;
            height: 100%;
        }

        .info-card:hover, .deletion-card:hover {
            box-shadow: 0 25px 45px -12px rgba(2, 132, 199, 0.12), 0 0 35px rgba(2, 132, 199, 0.04);
            border-color: rgba(2, 132, 199, 0.3);
        }

        /* Brand & Logo Header */
        .brand-header {
            margin-bottom: 24px;
        }

        .logo-container {
            display: inline-flex;
            align-items: center;
            background: #0f172a; /* Dark navy background so white logo shows clearly */
            border-radius: 12px;
            padding: 8px 16px;
            border: 1px solid rgba(2, 132, 199, 0.2);
            margin-bottom: 8px;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.08);
        }

        .brand-logo-img {
            max-width: 180px;
            height: 40px;
            object-fit: contain;
        }

        .brand-subtitle {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--brand-primary);
            font-weight: 700;
            margin-top: 4px;
        }

        .info-title {
            font-size: 1.85rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--text-dark);
            margin-bottom: 14px;
        }

        .info-desc {
            color: var(--text-secondary);
            font-size: 0.975rem;
            line-height: 1.6;
            margin-bottom: 32px;
        }

        /* Left Side Items */
        .info-list-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 20px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px;
            transition: all 0.3s ease;
        }

        .info-list-item:hover {
            background: #f0f7ff;
            border-color: rgba(2, 132, 199, 0.25);
            transform: translateY(-1px);
        }

        .info-icon-box {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(2, 132, 199, 0.08);
            border: 1px solid rgba(2, 132, 199, 0.2);
            color: var(--brand-primary);
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .info-item-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .info-item-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        /* Right Form Card Styles */
        .card-header-area {
            margin-bottom: 24px;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 6px;
            letter-spacing: -0.02em;
        }

        .card-subtitle {
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.5;
            margin: 0;
        }

        /* Warnings */
        .warning-box {
            background: var(--danger-bg);
            border: 1px solid var(--danger-border);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
        }

        .warning-box-text {
            color: var(--danger-text);
            font-size: 0.85rem;
            line-height: 1.5;
            margin: 0;
            font-weight: 500;
        }

        /* Form Controls */
        .form-group-custom {
            margin-bottom: 20px;
            position: relative;
        }

        .form-label {
            color: var(--text-dark);
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: var(--text-dark);
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.95rem;
            transition: all 0.25s ease;
            width: 100%;
            height: 48px;
        }

        textarea.form-control {
            height: 120px;
            resize: vertical;
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        .form-control:focus {
            background: #ffffff;
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 4px var(--brand-glow);
            color: var(--text-dark);
        }

        .form-control.is-invalid {
            border-color: var(--danger);
            background-image: none;
            background-color: #fffafb;
        }

        .form-control.is-invalid:focus {
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.12);
        }

        .invalid-feedback {
            color: var(--danger-text);
            font-size: 0.8rem;
            margin-top: 6px;
            font-weight: 600;
        }

        /* Autocomplete suggestions dropdown styling */
        .suggestions-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            z-index: 1000;
            max-height: 220px;
            overflow-y: auto;
            margin-top: 6px;
        }

        .suggestion-item {
            padding: 10px 16px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            text-align: left;
            transition: background 0.15s ease;
        }

        .suggestion-item:last-child {
            border-bottom: none;
        }

        .suggestion-item:hover {
            background: #f0f9ff;
        }

        .suggestion-name {
            font-size: 0.875rem;
            font-weight: 700;
            color: #0f172a;
        }

        .suggestion-email {
            font-size: 0.775rem;
            color: #475569;
            margin-top: 2px;
        }

        .suggestion-empty {
            padding: 12px 16px;
            color: #64748b;
            font-size: 0.85rem;
            text-align: left;
        }

        /* Checkbox */
        .form-check {
            padding-left: 1.75em;
            margin-bottom: 24px;
        }

        .form-check-input {
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            width: 1.2em;
            height: 1.2em;
            margin-left: -1.75em;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .form-check-input:checked {
            background-color: var(--danger);
            border-color: var(--danger);
        }

        .form-check-input:focus {
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.15);
            border-color: var(--danger);
        }

        .form-check-label {
            color: var(--text-secondary);
            font-size: 0.85rem;
            line-height: 1.45;
            cursor: pointer;
            user-select: none;
            display: inline-block;
            font-weight: 500;
        }

        /* Buttons */
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border: none;
            height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.975rem;
            letter-spacing: 0.02em;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.25);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: 100%;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #f87171, #ef4444);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.35);
        }

        .btn-danger:active {
            transform: translateY(0);
        }

        .footer-note {
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 18px;
            line-height: 1.45;
            font-weight: 500;
        }

        /* User badge style */
        .logged-user-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 18px;
        }

        /* Alerts */
        .custom-alert {
            border-radius: 12px;
            padding: 16px;
            font-size: 0.9rem;
            display: flex;
            align-items: flex-start;
            margin-bottom: 22px;
        }

        .custom-alert-success {
            background: var(--success-bg);
            border: 1px solid var(--success-border);
            color: var(--success-text);
        }

        .custom-alert-danger {
            background: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: var(--danger-text);
        }

        /* Desktop spacing & equal height columns */
        @media (min-width: 992px) {
            .brand-section, .form-section {
                display: flex;
                flex-direction: column;
            }
            .info-card, .deletion-card {
                flex-grow: 1;
            }
        }

        /* Responsive Layout Grid */
        @media (max-width: 991px) {
            body {
                padding: 24px 0;
            }
            .compliance-wrapper {
                padding: 0 16px;
            }
            .brand-section {
                padding-right: 0;
                margin-bottom: 24px;
            }
            .logo-container {
                display: inline-flex;
            }
            .brand-logo-img {
                max-width: 150px;
                height: 34px;
            }
            .info-card, .deletion-card {
                padding: 24px;
                border-radius: 20px;
            }
            .info-list-item {
                padding: 14px;
                margin-bottom: 16px;
            }
            .info-title {
                font-size: 1.5rem;
                text-align: center;
            }
            .info-desc {
                text-align: center;
                margin-bottom: 24px;
            }
        }

        @media (max-width: 768px) {
            .brand-section {
                display: none !important;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 16px 0;
            }
            .info-card, .deletion-card {
                padding: 22px 20px;
                border-radius: 18px;
            }
            .form-check-label {
                font-size: 0.8rem;
            }
            .warning-box {
                padding: 12px;
            }
        }

        @media (max-width: 360px) {
            .info-card, .deletion-card {
                padding: 18px 14px;
            }
            .form-check-label {
                font-size: 0.775rem;
            }
        }
    </style>
</head>
<body>

    <div class="compliance-wrapper">
        <div class="row align-items-stretch g-4">
            
            <!-- Left Info Panel Card -->
            <div class="col-lg-6 col-md-12 brand-section text-center text-lg-start">
                <div class="info-card">
                    <div class="brand-header">
                        <div class="logo-container">
                            <img src="{{ asset('images/peersglobal-logo.png') }}" alt="Peers Global Logo" class="brand-logo-img">
                        </div>
                        <div class="brand-subtitle">Account & Privacy Request</div>
                    </div>
                    
                    <h1 class="info-title">Before you continue</h1>
                    <p class="info-desc">
                        We value your privacy rights. Submitting this request starts the account deletion process for your Peers Global Unity account.
                    </p>

                    <!-- Information items -->
                    <div class="info-list-item">
                        <div class="info-icon-box">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div>
                            <div class="info-item-title">Permanent deletion</div>
                            <div class="info-item-desc">Your profile, account access, and related activity data may be permanently removed.</div>
                        </div>
                    </div>

                    <div class="info-list-item">
                        <div class="info-icon-box">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <div>
                            <div class="info-item-title">Account verification</div>
                            <div class="info-item-desc">We may verify your account email before processing the request.</div>
                        </div>
                    </div>

                    <div class="info-list-item">
                        <div class="info-icon-box">
                            <i class="bi bi-file-earmark-lock2"></i>
                        </div>
                        <div>
                            <div class="info-item-title">Privacy compliance</div>
                            <div class="info-item-desc">Requests are handled according to applicable privacy and data protection policies.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Deletion Card -->
            <div class="col-lg-6 col-md-12 form-section">
                <div class="deletion-card">
                    <div class="card-header-area text-start">
                        <h2 class="card-title">Request Account Deletion</h2>
                        <p class="card-subtitle">Please complete the form below to submit your deletion request.</p>
                    </div>

                    @if(session('success'))
                        <div class="custom-alert custom-alert-success text-start" role="alert">
                            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                            <div>
                                <strong>Request Received:</strong> {{ session('success') }}
                            </div>
                        </div>
                        <a href="/" class="btn btn-outline-light w-100 py-2.5" style="border-radius: 10px; font-weight: 600; color: var(--text-dark); border-color: var(--text-muted); height: 48px; display: inline-flex; align-items: center; justify-content: center;">
                            <i class="bi bi-house-door me-2"></i>Back to Home
                        </a>
                    @else
                        <div class="warning-box">
                            <p class="warning-box-text text-start">
                                <i class="bi bi-info-circle-fill me-1"></i>
                                Notice: Once finalized, this process is irreversible. Please make sure you have backed up any necessary data.
                            </p>
                        </div>

                        <form action="{{ route('account-deletion.submit') }}" method="POST" class="text-start" id="accountDeletionForm">
                            @csrf

                            @if(session('error'))
                                <div class="custom-alert custom-alert-danger" role="alert">
                                    <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
                                    <div>
                                        <strong>Error:</strong> {{ session('error') }}
                                    </div>
                                </div>
                            @endif

                            @if($user)
                                <div class="form-group-custom">
                                    <label class="form-label">Authorized Account</label>
                                    <div class="logged-user-box">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <span style="color: var(--text-dark); font-weight: 600;">{{ $user->display_name ?? $user->first_name }}</span>
                                            <span class="badge bg-info-subtle text-info px-2 py-0.5" style="font-size: 0.7rem;">Verified Session</span>
                                        </div>
                                        <div class="small" style="color: var(--text-muted-dark); font-size: 0.8rem;">{{ $user->email }}</div>
                                    </div>
                                </div>
                            @else
                                <div class="form-group-custom">
                                    <label for="email" class="form-label">Your Account Email Address</label>
                                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" placeholder="Enter your email" required autocomplete="off">
                                    <div id="emailSuggestions" class="suggestions-dropdown d-none"></div>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endif

                            <div class="form-group-custom">
                                <label for="reason" class="form-label">Reason for Deletion</label>
                                <textarea class="form-control @error('reason') is-invalid @enderror" id="reason" name="reason" placeholder="Briefly tell us why you are choosing to delete your account..." required>{{ old('reason') }}</textarea>
                                @error('reason')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirm_deletion" required>
                                <label class="form-check-label" for="confirm_deletion">
                                    I understand this action is permanent and cannot be undone.
                                </label>
                            </div>

                            <button type="submit" class="btn btn-danger" id="submitBtn">
                                <i class="bi bi-trash3 me-2"></i>Submit Deletion Request
                            </button>

                            <div class="footer-note">
                                Please make sure the email address matches your Peers Global Unity account.
                            </div>
                        </form>
                    @endif
                </div>
            </div>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Form Submit loader state
            const form = document.getElementById('accountDeletionForm');
            if (form) {
                form.addEventListener('submit', function() {
                    const submitBtn = document.getElementById('submitBtn');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing Request...';
                    }
                });
            }

            // Autocomplete suggestions logic
            const emailInput = document.getElementById('email');
            const suggestionsDiv = document.getElementById('emailSuggestions');
            let debounceTimer;

            if (emailInput && suggestionsDiv) {
                emailInput.addEventListener('input', function() {
                    const query = this.value.trim().toLowerCase();
                    clearTimeout(debounceTimer);
                    
                    if (query.length < 2) {
                        suggestionsDiv.classList.add('d-none');
                        suggestionsDiv.innerHTML = '';
                        return;
                    }

                    // Show searching indicator
                    suggestionsDiv.innerHTML = `
                        <div class="suggestion-empty">
                            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" style="width: 0.85rem; height: 0.85rem; border-width: 0.12em; color: var(--brand-primary);"></span>
                            Searching members...
                        </div>
                    `;
                    suggestionsDiv.classList.remove('d-none');

                    debounceTimer = setTimeout(() => {
                        // Fetch from members API
                        fetch('https://peersunity.com/api/v1/members', {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json'
                            }
                        })
                        .then(response => response.json())
                        .then(res => {
                            let members = [];
                            if (res) {
                                if (res.data) {
                                    if (Array.isArray(res.data)) {
                                        members = res.data;
                                    } else if (res.data.items && Array.isArray(res.data.items)) {
                                        members = res.data.items;
                                    } else if (res.data.data && Array.isArray(res.data.data)) {
                                        members = res.data.data;
                                    }
                                } else if (res.members && Array.isArray(res.members)) {
                                    members = res.members;
                                } else if (res.items && Array.isArray(res.items)) {
                                    members = res.items;
                                } else if (Array.isArray(res)) {
                                    members = res;
                                }
                            }

                            // Filter results locally (case-insensitive)
                            const filtered = members.filter(m => {
                                const email = (m.email || '').toLowerCase();
                                const dispName = (m.display_name || '').toLowerCase();
                                const firstName = (m.first_name || '').toLowerCase();
                                const lastName = (m.last_name || '').toLowerCase();
                                const fullName = `${firstName} ${lastName}`.trim().toLowerCase();
                                
                                return email.includes(query) || 
                                       dispName.includes(query) || 
                                       firstName.includes(query) || 
                                       lastName.includes(query) ||
                                       fullName.includes(query);
                            });

                            showSuggestions(filtered, query);
                        })
                        .catch(err => {
                            console.error('API Error:', err);
                            // Hide loading state on error so user typing is not blocked
                            suggestionsDiv.classList.add('d-none');
                        });
                    }, 300);
                });

                function showSuggestions(list, query) {
                    suggestionsDiv.innerHTML = '';
                    if (list.length === 0) {
                        suggestionsDiv.innerHTML = `<div class="suggestion-empty">No matching member found</div>`;
                    } else {
                        list.forEach(m => {
                            const email = m.email || '';
                            const name = m.display_name || (m.first_name ? `${m.first_name || ''} ${m.last_name || ''}`.trim() : 'Member');
                            const item = document.createElement('div');
                            item.className = 'suggestion-item';
                            item.innerHTML = `
                                <div class="suggestion-name">${escapeHtml(name)}</div>
                                <div class="suggestion-email">${escapeHtml(email)}</div>
                            `;
                            item.addEventListener('click', () => {
                                emailInput.value = email;
                                suggestionsDiv.classList.add('d-none');
                                suggestionsDiv.innerHTML = '';
                            });
                            suggestionsDiv.appendChild(item);
                        });
                    }
                    suggestionsDiv.classList.remove('d-none');
                }

                // Close suggestions on outside click
                document.addEventListener('click', function(e) {
                    if (e.target !== emailInput && !suggestionsDiv.contains(e.target)) {
                        suggestionsDiv.classList.add('d-none');
                    }
                });

                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.innerText = text;
                    return div.innerHTML;
                }
            }
        });
    </script>
</body>
</html>
