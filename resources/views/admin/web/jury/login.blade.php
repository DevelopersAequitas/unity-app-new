<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jury Portal Login | Peers Global Leadership Selection</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-primary: #4f46e5;
            --brand-primary-hover: #4338ca;
            --brand-dark: #0f172a;
            --brand-surface: #f8fafc;
        }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            color: #1e293b;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35), 0 0 0 1px rgba(255, 255, 255, 0.1);
            max-width: 460px;
            width: 100%;
            overflow: hidden;
            backdrop-filter: blur(12px);
        }
        .login-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            padding: 36px 32px 30px;
            text-align: center;
            color: #ffffff;
            position: relative;
        }
        .login-header::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 16px;
            background: #ffffff;
            border-radius: 16px 16px 0 0;
        }
        .jury-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(8px);
            padding: 6px 14px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 12px;
        }
        .form-control {
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 15px;
            border: 1.5px solid #e2e8f0;
            transition: all 0.2s ease;
        }
        .form-control:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
        }
        .btn-brand {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: #ffffff;
            border: none;
            border-radius: 12px;
            padding: 13px 20px;
            font-size: 15px;
            font-weight: 700;
            transition: all 0.2s ease;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.25);
        }
        .btn-brand:hover {
            background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 6px 10px -1px rgba(79, 70, 229, 0.35);
        }
        .otp-input-group {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin: 20px 0;
        }
        .otp-digit {
            width: 48px;
            height: 56px;
            font-size: 24px;
            font-weight: 800;
            text-align: center;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            background: #f8fafc;
            color: #1e1b4b;
            transition: all 0.2s ease;
        }
        .otp-digit:focus {
            border-color: #6366f1;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
            outline: none;
        }
        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="jury-badge">
            <i class="fa-solid fa-gavel"></i> Official Jury Portal
        </div>
        <h2 class="fw-bold mb-1 fs-4">Peers Global Selection</h2>
        <p class="mb-0 text-white-50 small">Candidate Evaluation & Scoring Portal</p>
    </div>

    <div class="p-4 pt-2">
        <!-- Error Alert -->
        <div id="errorAlert" class="alert alert-danger rounded-3 py-2.5 px-3 small d-flex align-items-center gap-2 hidden" role="alert">
            <i class="fa-solid fa-circle-exclamation flex-shrink-0"></i>
            <span id="errorMessage"></span>
        </div>

        <!-- Success Alert -->
        <div id="successAlert" class="alert alert-success rounded-3 py-2.5 px-3 small d-flex align-items-center gap-2 hidden" role="alert">
            <i class="fa-solid fa-circle-check flex-shrink-0"></i>
            <span id="successMessage"></span>
        </div>

        <!-- Step 1: Request OTP -->
        <div id="stepEmail">
            <p class="text-muted small mb-3">
                Enter your registered email address where you received your Jury Appointment notification to receive your 6-digit access code.
            </p>

            <form id="emailForm" onsubmit="handleSendOtp(event)">
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-dark">Registered Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 rounded-start-3 text-muted">
                            <i class="fa-regular fa-envelope"></i>
                        </span>
                        <input 
                            type="email" 
                            id="jurorEmail" 
                            class="form-control border-start-0 ps-0" 
                            placeholder="name@domain.com" 
                            required 
                            autofocus
                        >
                    </div>
                </div>

                <button type="submit" id="btnSendOtp" class="btn btn-brand w-100 d-flex align-items-center justify-content-center gap-2">
                    <span>Send Login OTP</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>
        </div>

        <!-- Step 2: Verify OTP -->
        <div id="stepOtp" class="hidden">
            <div class="text-center mb-3">
                <span class="badge bg-indigo-subtle text-indigo px-3 py-1.5 rounded-pill small fw-semibold">
                    Code sent to <span id="displayTargetEmail" class="fw-bold text-dark"></span>
                </span>
                <p class="text-muted small mt-2 mb-0">Enter the 6-digit verification code below:</p>
            </div>

            <form id="otpForm" onsubmit="handleVerifyOtp(event)">
                <div class="otp-input-group">
                    <input type="text" maxlength="1" class="otp-digit" data-index="0" inputmode="numeric" pattern="[0-9]*" required>
                    <input type="text" maxlength="1" class="otp-digit" data-index="1" inputmode="numeric" pattern="[0-9]*" required>
                    <input type="text" maxlength="1" class="otp-digit" data-index="2" inputmode="numeric" pattern="[0-9]*" required>
                    <input type="text" maxlength="1" class="otp-digit" data-index="3" inputmode="numeric" pattern="[0-9]*" required>
                    <input type="text" maxlength="1" class="otp-digit" data-index="4" inputmode="numeric" pattern="[0-9]*" required>
                    <input type="text" maxlength="1" class="otp-digit" data-index="5" inputmode="numeric" pattern="[0-9]*" required>
                </div>
                <input type="hidden" id="fullOtp">

                <button type="submit" id="btnVerifyOtp" class="btn btn-brand w-100 d-flex align-items-center justify-content-center gap-2 mb-3">
                    <span>Verify & Enter Dashboard</span>
                    <i class="fa-solid fa-check"></i>
                </button>

                <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                    <button type="button" class="btn btn-link text-decoration-none text-muted small p-0" onclick="backToEmail()">
                        <i class="fa-solid fa-arrow-left me-1"></i> Change Email
                    </button>
                    <button type="button" id="btnResend" class="btn btn-link text-decoration-none text-primary small p-0 fw-semibold" onclick="resendOtp()">
                        Resend Code
                    </button>
                </div>
            </form>
        </div>

        <div class="text-center mt-4 pt-3 border-top">
            <p class="text-muted mb-0" style="font-size: 11.5px;">
                <i class="fa-solid fa-lock me-1 text-secondary"></i> Authorized Jury Members Only • Peers Unity 2026
            </p>
        </div>
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const errorAlert = document.getElementById('errorAlert');
const errorMessage = document.getElementById('errorMessage');
const successAlert = document.getElementById('successAlert');
const successMessage = document.getElementById('successMessage');

function showError(msg) {
    errorMessage.textContent = msg;
    errorAlert.classList.remove('hidden');
    successAlert.classList.add('hidden');
}

function showSuccess(msg) {
    successMessage.textContent = msg;
    successAlert.classList.remove('hidden');
    errorAlert.classList.add('hidden');
}

function clearAlerts() {
    errorAlert.classList.add('hidden');
    successAlert.classList.add('hidden');
}

// Step 1: Send OTP
async function handleSendOtp(e) {
    if (e) e.preventDefault();
    clearAlerts();

    const email = document.getElementById('jurorEmail').value.trim();
    if (!email) return;

    const btn = document.getElementById('btnSendOtp');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status"></span> Sending Code...`;

    try {
        const res = await fetch("{{ route('admin.web.jury.send-otp') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ email })
        });

        const data = await res.json();

        if (res.ok && data.success) {
            document.getElementById('displayTargetEmail').textContent = email;
            document.getElementById('stepEmail').classList.add('hidden');
            document.getElementById('stepOtp').classList.remove('hidden');
            showSuccess(data.message || 'Verification code sent to your email.');
            // Focus first digit
            setTimeout(() => {
                const first = document.querySelector('.otp-digit[data-index="0"]');
                if (first) first.focus();
            }, 100);
        } else {
            showError(data.message || 'Could not find jury assignments for this email.');
        }
    } catch (err) {
        showError('Network error while requesting verification code. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

// OTP digit auto advance
const otpInputs = document.querySelectorAll('.otp-digit');
otpInputs.forEach((input, idx) => {
    input.addEventListener('input', (e) => {
        const val = e.target.value;
        if (val.length > 0) {
            e.target.value = val.slice(-1);
            if (idx < otpInputs.length - 1) {
                otpInputs[idx + 1].focus();
            }
        }
        updateFullOtp();
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !e.target.value && idx > 0) {
            otpInputs[idx - 1].focus();
        }
    });

    input.addEventListener('paste', (e) => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text').trim();
        if (/^\d{6}$/.test(pasted)) {
            pasted.split('').forEach((char, i) => {
                if (otpInputs[i]) otpInputs[i].value = char;
            });
            updateFullOtp();
            handleVerifyOtp();
        }
    });
});

function updateFullOtp() {
    let combined = '';
    otpInputs.forEach(i => combined += i.value);
    document.getElementById('fullOtp').value = combined;
}

// Step 2: Verify OTP
async function handleVerifyOtp(e) {
    if (e) e.preventDefault();
    clearAlerts();

    updateFullOtp();
    const otp = document.getElementById('fullOtp').value.trim();
    const email = document.getElementById('jurorEmail').value.trim();

    if (otp.length < 6) {
        showError('Please enter all 6 digits of the OTP code.');
        return;
    }

    const btn = document.getElementById('btnVerifyOtp');
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status"></span> Verifying...`;

    try {
        const res = await fetch("{{ route('admin.web.jury.verify-otp') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ email, otp })
        });

        const data = await res.json();

        if (res.ok && data.success) {
            showSuccess('Verification successful! Redirecting to dashboard...');
            setTimeout(() => {
                window.location.href = data.redirect_url || "{{ route('admin.web.jury.dashboard') }}";
            }, 600);
        } else {
            showError(data.message || 'Invalid or expired verification code.');
        }
    } catch (err) {
        showError('Network error during verification. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

function backToEmail() {
    clearAlerts();
    document.getElementById('stepOtp').classList.add('hidden');
    document.getElementById('stepEmail').classList.remove('hidden');
}

function resendOtp() {
    handleSendOtp();
}
</script>
</body>
</html>
