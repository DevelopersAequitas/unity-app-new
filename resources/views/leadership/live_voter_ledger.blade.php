<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Strictly Unindexed Private Portal -->
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet">
    <title>Private Candidate Live Voter Ledger | Peers Global Unity</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --pg-primary: #1e3a8a;
            --pg-accent: #2563eb;
            --pg-dark: #0f172a;
            --pg-light: #f8fafc;
            --pg-emerald: #10b981;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
            color: #334155;
            min-height: 100vh;
        }
        .header-bg {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
            color: #ffffff;
            padding: 3rem 1rem 2.5rem;
            position: relative;
        }
        .header-card {
            max-width: 960px;
            margin: -2.5rem auto 1.5rem;
            position: relative;
            z-index: 10;
        }
        .card-custom {
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            background: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        }
        .btn-pg-primary {
            background-color: #2563eb;
            color: #ffffff;
            font-weight: 600;
            padding: 0.75rem 1.75rem;
            border-radius: 0.6rem;
            border: none;
            transition: all 0.2s ease;
        }
        .btn-pg-primary:hover {
            background-color: #1d4ed8;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }
        .pulsing-dot {
            width: 8px;
            height: 8px;
            background-color: #10b981;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: pulse-green 2s infinite;
        }
        @keyframes pulse-green {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        .badge-live {
            background-color: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
            font-weight: 600;
        }
        .badge-verified {
            background-color: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
            font-weight: 600;
        }
        .badge-ref {
            font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
        }
        .vote-statement-box {
            background-color: #f8fafc;
            border-left: 3px solid #3b82f6;
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
            color: #475569;
            border-radius: 0 0.5rem 0.5rem 0;
            font-style: italic;
        }
        .table-hover tbody tr:hover {
            background-color: #f8fafc;
        }
        @media (max-width: 767.98px) {
            .table-responsive-cards thead {
                display: none;
            }
            .table-responsive-cards, .table-responsive-cards tbody, .table-responsive-cards tr, .table-responsive-cards td {
                display: block;
                width: 100%;
            }
            .table-responsive-cards tr {
                margin-bottom: 1rem;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 0.75rem;
                padding: 1rem;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }
            .table-responsive-cards td {
                padding: 0.35rem 0;
                border: none;
            }
            .table-responsive-cards td::before {
                content: attr(data-label);
                font-weight: 700;
                font-size: 0.75rem;
                text-transform: uppercase;
                color: #94a3b8;
                display: block;
                margin-bottom: 0.15rem;
            }
        }
    </style>
</head>
<body>

    <!-- A. Header (Hero Section) -->
    <header class="header-bg text-center">
        <div class="container">
            <div class="mb-3 text-center">
                <a href="https://peersglobal.com" target="_blank" rel="noopener noreferrer" class="d-inline-flex align-items-center justify-content-center text-decoration-none">
                    <img src="{{ asset('images/logo-full.png') }}" onerror="this.onerror=null; this.src='https://peersglobal.com/images/logo-full.png';" alt="Peers Global Unity" style="height: 48px; max-width: 250px; object-fit: contain; filter: brightness(0) invert(1);" />
                </a>
            </div>
            <h1 class="h2 fw-bold text-white mb-2">Candidate Private Voter Ledger</h1>
            <p class="text-white-50 mb-4 mx-auto" style="max-width: 650px;">
                Live, verified peer voting breakdown for your official leadership candidacy.
            </p>
        </div>
    </header>

    <div class="container pb-5">
        <!-- B. Candidate Dossier Summary Card -->
        <div class="card card-custom header-card p-3 p-md-4 mb-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold fs-4" style="width: 58px; height: 58px;">
                        {{ strtoupper(substr($candidateName, 0, 1)) }}
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h2 class="h5 fw-bold text-dark mb-0">{{ $candidateName }}</h2>
                            <span class="badge bg-primary text-white">{{ $roleName }}</span>
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">{{ $region }}</span>
                        </div>
                        <div class="text-muted small mt-1">
                            Application No: <span class="fw-semibold text-dark">{{ $applicationNumber }}</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-column align-items-md-end gap-2">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        @if(!$isExpired)
                            <span class="badge badge-live px-3 py-2 rounded-pill d-inline-flex align-items-center gap-1.5">
                                <span class="pulsing-dot"></span> Voting Active & Open
                            </span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 rounded-pill">
                                <i class="bi bi-clock-history me-1"></i> Voting Closed
                            </span>
                        @endif
                    </div>
                    @if(!$isExpired && $votingCountdown)
                        <div class="small text-muted">
                            <i class="bi bi-hourglass-split text-primary me-1"></i> Voting closes in <strong class="text-dark">{{ $votingCountdown }}</strong>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if($isExpired)
            <!-- Lifecycle Notice: Voting Expired or Closed -->
            <div class="card card-custom p-4 p-md-5 text-center mx-auto" style="max-width: 650px;">
                <div class="mx-auto rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                    <i class="bi bi-lock-fill fs-2"></i>
                </div>
                <h3 class="h5 fw-bold text-dark mb-2">Voting Concluded</h3>
                <p class="text-muted mb-4 mx-auto" style="max-width: 500px;">
                    {{ $expiredNotice ?? 'Voting for this candidacy has concluded. The private live voter ledger is now closed.' }}
                </p>
                <div class="p-3 bg-light rounded-3 text-start small text-muted border border-light-subtle mx-auto" style="max-width: 500px;">
                    <i class="bi bi-info-circle text-primary me-1"></i> In accordance with Peers Global governance protocols, live nominee voter ledgers automatically seal when voting concludes. Final certified tallies will be released post-scrutiny.
                </div>
            </div>
        @else

            <!-- Step 1: Candidate Email + OTP Verification Gate -->
            <div id="ledgerSecurityGate" class="card card-custom p-4 p-md-5 mx-auto {{ $isAuthenticated ? 'd-none' : '' }}" style="max-width: 560px;">
                <div class="text-center mb-4">
                    <div class="mx-auto rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center mb-3" style="width: 64px; height: 64px;">
                        <i class="bi bi-shield-lock-fill fs-2"></i>
                    </div>
                    <h3 class="h5 fw-bold text-dark mb-1">Candidate Identity Verification</h3>
                    <p class="text-muted small mb-0">
                        To protect candidate and voter privacy, please verify your registered email address for <strong>{{ $candidateName }}</strong>.
                    </p>
                </div>

                <div id="gateAlert" class="alert d-none py-2 px-3 small rounded-3 mb-3" role="alert"></div>

                <!-- Step A: Enter Email -->
                <div id="gateStepEmail">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small" for="inputCandidateEmail">Registered Candidate Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-envelope text-muted"></i></span>
                            <input type="email" id="inputCandidateEmail" class="form-control" placeholder="Enter your registered email address" value="" required autocomplete="off">
                        </div>
                        <div class="form-text small">Must match the registered email for this candidate nomination.</div>
                    </div>

                    <button type="button" id="btnSendOtp" class="btn btn-pg-primary w-100 py-2.5">
                        <span id="sendOtpSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
                        <i class="bi bi-send me-1" id="sendOtpIcon"></i> Send Access OTP
                    </button>
                </div>

                <!-- Step B: Enter 6-Digit OTP -->
                <div id="gateStepOtp" class="d-none">
                    <div class="p-3 bg-light rounded-3 border border-light-subtle mb-3 text-center">
                        <div class="small text-muted mb-1">Access OTP sent to:</div>
                        <div class="fw-bold text-dark" id="displayTargetEmail">{{ $maskedEmail }}</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-center d-block fw-semibold small" for="inputOtpCode">Enter 6-Digit Verification Code</label>
                        <input type="text" id="inputOtpCode" maxlength="6" inputmode="numeric" pattern="[0-9]*" class="form-control form-control-lg text-center fw-bold fs-4" placeholder="&bull; &bull; &bull; &bull; &bull; &bull;" style="letter-spacing: 0.5rem;" autocomplete="one-time-code" required>
                        <div class="form-text small text-center">Check your inbox or spam folder for your 6-digit access code.</div>
                    </div>

                    <button type="button" id="btnVerifyOtp" class="btn btn-pg-primary w-100 py-2.5 mb-3">
                        <span id="verifyOtpSpinner" class="spinner-border spinner-border-sm me-1 d-none" role="status"></span>
                        <i class="bi bi-shield-check me-1" id="verifyOtpIcon"></i> Verify OTP &amp; Reveal Ledger
                    </button>

                    <div class="d-flex align-items-center justify-content-between small text-muted">
                        <button type="button" id="btnChangeEmail" class="btn btn-link p-0 text-decoration-none small text-secondary">
                            <i class="bi bi-arrow-left"></i> Change email
                        </button>
                        <button type="button" id="btnResendOtp" class="btn btn-link p-0 text-decoration-none small text-primary fw-semibold">
                            Resend code
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 2: Live Voter Ledger Container -->
            <div id="liveLedgerContainer" class="{{ $isAuthenticated ? '' : 'd-none' }}" style="max-width: 960px; margin: 0 auto;">
                
                <!-- C. Live Tally & Quick Refresh Bar -->
                <div class="card card-custom p-3 p-md-4 mb-4">
                    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3">
                        <div>
                            <div class="text-muted small text-uppercase fw-bold letter-spacing-1">Verified Votes Tally</div>
                            <div class="d-flex align-items-baseline gap-2 mt-1">
                                <span class="display-6 fw-bold text-dark" id="tallyCount">0</span>
                                <span class="fs-5 text-muted fw-semibold">Verified Votes</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="small text-muted d-none d-md-inline" id="lastUpdatedNotice">Auto-synced</span>
                            <button type="button" id="btnRefreshLedger" class="btn btn-outline-primary d-inline-flex align-items-center gap-1.5 px-3 py-2">
                                <i class="bi bi-arrow-clockwise" id="refreshIcon"></i>
                                <span>Refresh Live Votes</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- D. Voters Audit Ledger Table / Cards -->
                <div class="card card-custom p-3 p-md-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-people-fill text-primary fs-5"></i>
                            <h3 class="h6 fw-bold text-dark mb-0">Verified Peer Voters Ledger</h3>
                        </div>
                        <span class="badge bg-light text-muted border">Real-Time Audit Trail</span>
                    </div>

                    <!-- Loading Skeleton -->
                    <div id="ledgerLoading" class="py-5 text-center d-none">
                        <div class="spinner-border text-primary mb-2" role="status"></div>
                        <div class="small text-muted">Retrieving live verified peer votes...</div>
                    </div>

                    <!-- Empty State -->
                    <div id="ledgerEmpty" class="py-5 text-center d-none">
                        <div class="mx-auto rounded-circle bg-light text-muted d-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px;">
                            <i class="bi bi-inbox fs-3"></i>
                        </div>
                        <h4 class="h6 fw-bold text-dark mb-1">No Verified Votes Cast Yet</h4>
                        <p class="text-muted small mb-3 mx-auto" style="max-width: 450px;">
                            Share your official candidate page with your Circle and fellow Peers. As soon as a verified member votes via OTP, their endorsement will appear live here.
                        </p>
                    </div>

                    <!-- Table Container -->
                    <div id="ledgerTableWrapper" class="d-none">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle table-responsive-cards mb-0">
                                <thead class="table-light text-uppercase small text-muted">
                                    <tr>
                                        <th style="width: 50px;">#</th>
                                        <th>Verified Voter Name</th>
                                        <th>Business / Org &amp; Role</th>
                                        <th>Chapter / City</th>
                                        <th>Support Statement</th>
                                        <th>Vote Ref Code</th>
                                        <th>Voted At</th>
                                    </tr>
                                </thead>
                                <tbody id="ledgerTbody">
                                    <!-- Dynamic rows loaded via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top text-muted small d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2">
                        <div>
                            <i class="bi bi-shield-check text-success me-1"></i> Every vote is authenticated through verified Peer OTP credentials.
                        </div>
                        <div>
                            <span class="text-muted">Contact data is masked per privacy standards.</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const nominationId = "{{ $nomination->id }}";
            let currentToken = "{{ $sessionToken ?? '' }}";
            let verificationId = null;
            let cooldownTimer = null;
            let cooldownRemaining = 0;

            const gate = document.getElementById('ledgerSecurityGate');
            const gateAlert = document.getElementById('gateAlert');
            const stepEmail = document.getElementById('gateStepEmail');
            const stepOtp = document.getElementById('gateStepOtp');
            const inputEmail = document.getElementById('inputCandidateEmail');
            const inputOtp = document.getElementById('inputOtpCode');
            const btnSendOtp = document.getElementById('btnSendOtp');
            const btnVerifyOtp = document.getElementById('btnVerifyOtp');
            const btnChangeEmail = document.getElementById('btnChangeEmail');
            const btnResendOtp = document.getElementById('btnResendOtp');
            const displayTargetEmail = document.getElementById('displayTargetEmail');

            const ledgerContainer = document.getElementById('liveLedgerContainer');
            const tallyCount = document.getElementById('tallyCount');
            const btnRefresh = document.getElementById('btnRefreshLedger');
            const refreshIcon = document.getElementById('refreshIcon');
            const lastUpdatedNotice = document.getElementById('lastUpdatedNotice');
            const ledgerLoading = document.getElementById('ledgerLoading');
            const ledgerEmpty = document.getElementById('ledgerEmpty');
            const ledgerTableWrapper = document.getElementById('ledgerTableWrapper');
            const ledgerTbody = document.getElementById('ledgerTbody');

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            function showGateAlert(msg, isSuccess = false) {
                if (!gateAlert) return;
                gateAlert.className = `alert py-2 px-3 small rounded-3 mb-3 ${isSuccess ? 'alert-success' : 'alert-danger'}`;
                gateAlert.textContent = msg;
                gateAlert.classList.remove('d-none');
            }

            function clearGateAlert() {
                if (!gateAlert) return;
                gateAlert.classList.add('d-none');
                gateAlert.textContent = '';
            }

            function startCooldown(seconds = 60) {
                cooldownRemaining = seconds;
                if (btnResendOtp) {
                    btnResendOtp.disabled = true;
                    btnResendOtp.classList.add('text-muted');
                    btnResendOtp.classList.remove('text-primary');
                }
                clearInterval(cooldownTimer);
                cooldownTimer = setInterval(() => {
                    cooldownRemaining--;
                    if (btnResendOtp) {
                        if (cooldownRemaining > 0) {
                            btnResendOtp.textContent = `Resend code (${cooldownRemaining}s)`;
                        } else {
                            clearInterval(cooldownTimer);
                            btnResendOtp.disabled = false;
                            btnResendOtp.textContent = 'Resend code';
                            btnResendOtp.classList.remove('text-muted');
                            btnResendOtp.classList.add('text-primary');
                        }
                    }
                }, 1000);
            }

            async function requestWithFallback(primaryUrl, fallbackUrl, options) {
                try {
                    let res = await fetch(primaryUrl, options);
                    if (res.status === 404 && fallbackUrl) {
                        res = await fetch(fallbackUrl, options);
                    }
                    return res;
                } catch (e) {
                    if (fallbackUrl) {
                        return await fetch(fallbackUrl, options);
                    }
                    throw e;
                }
            }

            // Step A: Send OTP
            async function sendOtp() {
                const email = inputEmail.value.trim();
                if (!email) {
                    showGateAlert('Please enter your registered candidate email address.');
                    return;
                }
                clearGateAlert();

                const spinner = document.getElementById('sendOtpSpinner');
                const icon = document.getElementById('sendOtpIcon');
                spinner.classList.remove('d-none');
                icon.classList.add('d-none');
                btnSendOtp.disabled = true;

                try {
                    const primaryUrl = `/api/v1/leadership/nominations/${nominationId}/voter-ledger/send-otp`;
                    const fallbackUrl = `/leadership/nomination/${nominationId}/live-votes/send-otp`;
                    const response = await requestWithFallback(primaryUrl, fallbackUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({ email: email })
                    });
                    const data = await response.json();

                    if (response.ok && data.status) {
                        verificationId = data.data?.verification_id;
                        if (displayTargetEmail) {
                            displayTargetEmail.textContent = data.data?.masked_email || email;
                        }
                        stepEmail.classList.add('d-none');
                        stepOtp.classList.remove('d-none');
                        showGateAlert(data.message || 'Access OTP sent to your registered email address.', true);
                        startCooldown(60);
                        setTimeout(() => inputOtp?.focus(), 150);
                    } else {
                        showGateAlert(data.message || 'Email does not match this nomination application.');
                    }
                } catch (e) {
                    showGateAlert('Network error sending verification code. Please try again.');
                } finally {
                    spinner.classList.add('d-none');
                    icon.classList.remove('d-none');
                    btnSendOtp.disabled = false;
                }
            }

            if (btnSendOtp) {
                btnSendOtp.addEventListener('click', sendOtp);
            }
            if (inputEmail) {
                inputEmail.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        sendOtp();
                    }
                });
            }

            // Step B: Verify OTP
            async function verifyOtp() {
                const otp = inputOtp.value.trim();
                const email = inputEmail.value.trim();
                if (!otp || otp.length !== 6) {
                    showGateAlert('Please enter the complete 6-digit access OTP.');
                    return;
                }
                clearGateAlert();

                const spinner = document.getElementById('verifyOtpSpinner');
                const icon = document.getElementById('verifyOtpIcon');
                spinner.classList.remove('d-none');
                icon.classList.add('d-none');
                btnVerifyOtp.disabled = true;

                try {
                    const primaryUrl = `/api/v1/leadership/nominations/${nominationId}/voter-ledger/verify-otp`;
                    const fallbackUrl = `/leadership/nomination/${nominationId}/live-votes/verify-otp`;
                    const response = await requestWithFallback(primaryUrl, fallbackUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            email: email,
                            otp: otp,
                            verification_id: verificationId
                        })
                    });
                    const data = await response.json();

                    if (response.ok && data.status) {
                        currentToken = data.access_token;
                        if (gate) gate.classList.add('d-none');
                        if (ledgerContainer) ledgerContainer.classList.remove('d-none');
                        loadLedgerData();
                    } else {
                        showGateAlert(data.message || 'Invalid or expired OTP code. Please try again.');
                    }
                } catch (e) {
                    showGateAlert('Server verification error. Please try again.');
                } finally {
                    spinner.classList.add('d-none');
                    icon.classList.remove('d-none');
                    btnVerifyOtp.disabled = false;
                }
            }

            if (btnVerifyOtp) {
                btnVerifyOtp.addEventListener('click', verifyOtp);
            }
            if (inputOtp) {
                inputOtp.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        verifyOtp();
                    }
                });
                inputOtp.addEventListener('input', function () {
                    if (this.value.length === 6) {
                        verifyOtp();
                    }
                });
            }

            if (btnChangeEmail) {
                btnChangeEmail.addEventListener('click', () => {
                    clearGateAlert();
                    stepOtp.classList.add('d-none');
                    stepEmail.classList.remove('d-none');
                    inputOtp.value = '';
                    inputEmail.focus();
                });
            }

            if (btnResendOtp) {
                btnResendOtp.addEventListener('click', () => {
                    if (cooldownRemaining <= 0) {
                        sendOtp();
                    }
                });
            }

            // Fetch and render Ledger data
            async function loadLedgerData(isManualRefresh = false) {
                if (isManualRefresh && refreshIcon) {
                    refreshIcon.classList.add('spinner-border', 'spinner-border-sm');
                    refreshIcon.classList.remove('bi-arrow-clockwise');
                } else if (!isManualRefresh) {
                    ledgerLoading.classList.remove('d-none');
                    ledgerTableWrapper.classList.add('d-none');
                    ledgerEmpty.classList.add('d-none');
                }

                try {
                    const headers = {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    };
                    if (currentToken) {
                        headers['Authorization'] = `Bearer ${currentToken}`;
                        headers['X-Ledger-Token'] = currentToken;
                    }

                    const primaryUrl = `/api/v1/leadership/nominations/${nominationId}/voter-ledger`;
                    const fallbackUrl = `/leadership/nomination/${nominationId}/live-votes/data`;
                    const response = await requestWithFallback(primaryUrl, fallbackUrl, {
                        method: 'GET',
                        headers: headers
                    });

                    const data = await response.json();

                    if (response.ok && data.status) {
                        if (tallyCount) tallyCount.textContent = data.total_votes || 0;
                        if (lastUpdatedNotice) {
                            const nowStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                            lastUpdatedNotice.textContent = `Last refreshed at ${nowStr}`;
                        }

                        renderVotes(data.votes || []);
                    } else if (response.status === 401) {
                        if (gate) gate.classList.remove('d-none');
                        if (ledgerContainer) ledgerContainer.classList.add('d-none');
                        showGateAlert('Session expired. Please re-verify your OTP to view your ledger.');
                    } else if (data.expired) {
                        window.location.reload();
                    }
                } catch (err) {
                    console.error('Failed loading voter ledger:', err);
                } finally {
                    ledgerLoading.classList.add('d-none');
                    if (refreshIcon) {
                        refreshIcon.classList.remove('spinner-border', 'spinner-border-sm');
                        refreshIcon.classList.add('bi-arrow-clockwise');
                    }
                }
            }

            function renderVotes(votes) {
                if (!votes || votes.length === 0) {
                    ledgerTableWrapper.classList.add('d-none');
                    ledgerEmpty.classList.remove('d-none');
                    return;
                }

                ledgerEmpty.classList.add('d-none');
                ledgerTableWrapper.classList.remove('d-none');

                let rowsHtml = '';
                votes.forEach((v, idx) => {
                    rowsHtml += `
                        <tr>
                            <td data-label="#">
                                <span class="fw-bold text-muted small">${v.index || (idx + 1)}</span>
                            </td>
                            <td data-label="Verified Voter Name">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold small" style="width: 32px; height: 32px; min-width: 32px;">
                                        ${(v.voter_name || 'P').charAt(0).toUpperCase()}
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark">${escapeHtml(v.voter_name || 'Verified Peer')}</div>
                                        <div class="text-muted small" style="font-size: 0.78rem;">
                                            <i class="bi bi-shield-check text-success"></i> OTP Authenticated
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Business / Org & Role">
                                <div class="fw-semibold text-dark">${escapeHtml(v.organization || 'Peers Global Member')}</div>
                                <div class="text-muted small">${escapeHtml(v.role || 'Member')}</div>
                            </td>
                            <td data-label="Chapter / City">
                                <span class="badge bg-light text-dark border">${escapeHtml(v.city || 'India')}</span>
                            </td>
                            <td data-label="Support Statement" style="max-width: 280px;">
                                <div class="vote-statement-box">
                                    &ldquo;${escapeHtml(v.support_statement || 'Verified community support for circle growth')}&rdquo;
                                </div>
                            </td>
                            <td data-label="Vote Ref Code">
                                <span class="badge-ref">${escapeHtml(v.vote_ref_code || '-')}</span>
                            </td>
                            <td data-label="Voted At" class="text-nowrap small text-muted">
                                <i class="bi bi-calendar3 me-1"></i> ${escapeHtml(v.voted_at || '')}
                            </td>
                        </tr>
                    `;
                });

                if (ledgerTbody) {
                    ledgerTbody.innerHTML = rowsHtml;
                }
            }

            function escapeHtml(str) {
                if (!str) return '';
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            if (btnRefresh) {
                btnRefresh.addEventListener('click', () => loadLedgerData(true));
            }

            // Auto-load if already authenticated
            if (currentToken && ledgerContainer && !ledgerContainer.classList.contains('d-none')) {
                loadLedgerData();
            }
        });
    </script>
</body>
</html>
