@extends('admin.layouts.auth')

@section('title', 'Admin Login')

@section('content')
<div class="page-container auth-wrapper">
    <div class="card">
        <div class="card-header">
            <div class="text-center mb-3">
                <img
                    src="{{ asset('images/logo.png') }}"
                    alt="Peers Global Unity"
                    style="max-height: 60px; max-width: 100%; width: auto; object-fit: contain; clip-path: inset(0px 2px 0px 0px);"
                    class="d-block mx-auto mb-4"
                    loading="lazy"
                />
            </div>
        </div>

        @php
            $initialIdentifier = (string) old('identifier', old('email', old('mobile', '')));
            $isEmail = str_contains($initialIdentifier, '@');
            $sessionChannel = old('channel');
            $activeChannel = $sessionChannel ?: ($isEmail ? 'email' : (strlen(preg_replace('/\D/', '', $initialIdentifier)) >= 7 ? 'whatsapp' : 'email'));
        @endphp

        @if (session('status'))
            <div class="status show success" id="statusMessage">{{ session('status') }}</div>
        @elseif ($errors->any())
            <div class="status show error" id="statusMessage">{{ $errors->first() }}</div>
        @else
            <div id="statusMessage" class="status"></div>
        @endif

        <form id="request-otp-form" autocomplete="off" method="POST" action="{{ route('admin.login.send-otp') }}">
            @csrf
            <div>
                <label for="identifier">Email or Mobile Number</label>
                <div class="input-row">
                    <input
                        id="identifier"
                        name="identifier"
                        type="text"
                        placeholder="Enter email or mobile number"
                        autocomplete="username"
                        autocorrect="off"
                        autocapitalize="off"
                        spellcheck="false"
                        value="{{ $initialIdentifier }}"
                        required
                    />
                    <button type="submit" class="btn primary" id="request-otp-btn">Send OTP</button>
                </div>
                <p class="muted" id="identifier-help">Enter your registered email address or international mobile number.</p>
            </div>
        </form>

        <div style="height: 16px;"></div>

        <form id="verify-otp-form" autocomplete="off" method="POST" action="{{ route('admin.login.verify') }}">
            @csrf
            <input type="hidden" name="identifier" id="verify-identifier" value="{{ $initialIdentifier }}">
            <input type="hidden" name="channel" id="verify-channel" value="{{ $activeChannel }}">

            <label for="otp-1" id="verify-label">Enter Verification Code</label>

            <!-- Unified 4-Digit Grid for Email & WhatsApp -->
            <div id="otp-grid" class="otp-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px;">
                <input id="otp-1" class="otp-input" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="OTP Digit 1">
                <input id="otp-2" class="otp-input" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="OTP Digit 2">
                <input id="otp-3" class="otp-input" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="OTP Digit 3">
                <input id="otp-4" class="otp-input" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]*" aria-label="OTP Digit 4">
            </div>

            <!-- Bypass Password Field -->
            <div id="password-container" style="display: none; margin-top: 10px; margin-bottom: 10px;">
                <input id="admin-password" type="password" placeholder="Enter Password" style="width: 100%; box-sizing: border-box; padding: 10px; border-radius: 6px; border: 1px solid #ccc; font-size: 16px;">
            </div>

            <p class="muted" id="otp-help">Enter the 4-digit verification code sent to your email or WhatsApp.</p>
            <button type="submit" class="btn secondary" id="verify-otp-btn" style="margin-top: 10px;">Verify & Login</button>
        </form>
    </div>
</div>

<script>
    const identifierInput = document.getElementById('identifier');
    const verifyIdentifierInput = document.getElementById('verify-identifier');
    const verifyChannelInput = document.getElementById('verify-channel');

    const otpGrid = document.getElementById('otp-grid');
    const otpInputs = Array.from(document.querySelectorAll('.otp-input'));

    const passwordContainer = document.getElementById('password-container');
    const adminPasswordInput = document.getElementById('admin-password');
    const verifyLabel = document.getElementById('verify-label');
    const otpHelp = document.getElementById('otp-help');
    const statusMessage = document.getElementById('statusMessage');
    const identifierHelp = document.getElementById('identifier-help');

    const requestForm = document.getElementById('request-otp-form');
    const verifyForm = document.getElementById('verify-otp-form');
    const requestOtpBtn = document.getElementById('request-otp-btn');
    const verifyBtn = document.getElementById('verify-otp-btn');

    function isEmail(val) {
        val = (val || '').trim();
        if (!val) return false;
        if (val.includes('@')) return true;
        if (val.startsWith('+') || val.startsWith('00')) return false;
        if (/[a-zA-Z]/.test(val)) return true;
        return false;
    }

    function detectChannel(val) {
        val = (val || '').trim();
        if (isEmail(val)) {
            return 'email';
        }
        const cleanedDigits = val.replace(/\D/g, '');
        if (cleanedDigits.length >= 7 || val.startsWith('+') || val.startsWith('00')) {
            return 'whatsapp';
        }
        return 'email';
    }

    function getNormalizedIdentifier() {
        if (!identifierInput) return '';
        const raw = identifierInput.value.trim();
        if (!raw || isEmail(raw)) {
            return raw;
        }

        const digits = raw.replace(/\D/g, '');
        if (!digits) return raw;

        // If already in international format starting with +
        if (raw.startsWith('+')) {
            return '+' + digits;
        }

        // If standard 10-digit Indian mobile number entered without country code
        if (digits.length === 10) {
            return '+91' + digits;
        }

        // If entered with leading 00
        if (raw.startsWith('00')) {
            return '+' + digits;
        }

        return '+' + digits;
    }

    function updateUi() {
        if (!identifierInput) return;
        const val = identifierInput.value.trim();

        if (val === '') {
            if (identifierHelp) identifierHelp.textContent = 'Enter your registered email address or international mobile number.';
            if (otpHelp) otpHelp.textContent = 'Enter the 4-digit verification code sent to your email or WhatsApp.';
            if (verifyChannelInput) verifyChannelInput.value = 'email';
            if (verifyIdentifierInput) verifyIdentifierInput.value = '';
            if (passwordContainer) passwordContainer.style.display = 'none';
            if (adminPasswordInput) adminPasswordInput.required = false;
            if (otpGrid) otpGrid.style.display = 'grid';
            if (otpHelp) otpHelp.style.display = 'block';
            if (verifyLabel) verifyLabel.textContent = 'Enter Verification Code';
            return;
        }

        if (isEmail(val)) {
            if (identifierHelp) identifierHelp.textContent = 'Enter your registered email address.';
            if (otpHelp) otpHelp.textContent = 'Enter the 4-digit verification code sent to your email.';
            if (verifyChannelInput) verifyChannelInput.value = 'email';

            const emailVal = val.toLowerCase();
            if (emailVal === 'harshchauhanwork26@gmail.com') {
                if (otpGrid) otpGrid.style.display = 'none';
                if (otpHelp) otpHelp.style.display = 'none';
                if (passwordContainer) passwordContainer.style.display = 'block';
                if (adminPasswordInput) adminPasswordInput.required = true;
                if (verifyLabel) verifyLabel.textContent = 'Password';
            } else {
                if (passwordContainer) passwordContainer.style.display = 'none';
                if (adminPasswordInput) adminPasswordInput.required = false;
                if (otpGrid) otpGrid.style.display = 'grid';
                if (otpHelp) otpHelp.style.display = 'block';
                if (verifyLabel) verifyLabel.textContent = 'Enter Verification Code';
            }

            if (verifyIdentifierInput) {
                verifyIdentifierInput.value = val;
            }
            return;
        }

        // Phone Mode
        if (passwordContainer) passwordContainer.style.display = 'none';
        if (adminPasswordInput) adminPasswordInput.required = false;
        if (otpGrid) otpGrid.style.display = 'grid';
        if (otpHelp) otpHelp.style.display = 'block';
        if (verifyLabel) verifyLabel.textContent = 'Enter Verification Code';

        const channel = detectChannel(val);
        if (verifyChannelInput) {
            verifyChannelInput.value = channel;
        }

        if (channel === 'whatsapp') {
            if (identifierHelp) identifierHelp.textContent = 'Enter your registered mobile number with country code (e.g. +91 9876543210).';
            if (otpHelp) otpHelp.textContent = 'Enter the 4-digit verification code sent to your WhatsApp.';
        } else {
            if (identifierHelp) identifierHelp.textContent = 'Enter your registered email address or international mobile number.';
            if (otpHelp) otpHelp.textContent = 'Enter the 4-digit verification code sent to your email or WhatsApp.';
        }

        if (verifyIdentifierInput) {
            verifyIdentifierInput.value = getNormalizedIdentifier() || val;
        }
    }

    function setStatus(text, type = 'success') {
        if (!statusMessage) return;
        if (!text) {
            statusMessage.className = 'status';
            statusMessage.textContent = '';
            return;
        }

        statusMessage.textContent = text;
        statusMessage.className = `status show ${type}`;
    }

    function setLoading(button, isLoading, loadingText) {
        if (!button) return;
        if (isLoading) {
            button.dataset.originalText = button.textContent;
            button.textContent = loadingText;
            button.disabled = true;
        } else {
            button.textContent = button.dataset.originalText || button.textContent;
            button.disabled = false;
        }
    }

    if (identifierInput) {
        identifierInput.addEventListener('input', updateUi);
        identifierInput.addEventListener('change', updateUi);
        window.addEventListener('load', updateUi);
        updateUi();
    }

    function setupOtpInputs(inputs) {
        inputs.forEach((input, index) => {
            input.addEventListener('focus', () => {
                input.select();
            });

            input.addEventListener('input', (event) => {
                const clean = event.target.value.replace(/\D/g, '');
                event.target.value = clean ? clean.slice(-1) : '';
                if (event.target.value && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                    inputs[index + 1].select();
                }
            });

            input.addEventListener('keydown', (event) => {
                if (event.key === 'Backspace') {
                    if (!event.target.value && index > 0) {
                        inputs[index - 1].focus();
                        inputs[index - 1].value = '';
                    }
                } else if (event.key === 'ArrowLeft' && index > 0) {
                    inputs[index - 1].focus();
                } else if (event.key === 'ArrowRight' && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });

            input.addEventListener('paste', (event) => {
                event.preventDefault();
                const pasteData = (event.clipboardData || window.clipboardData)?.getData('text') || '';
                const cleanDigits = pasteData.replace(/\D/g, '').slice(0, inputs.length);
                if (cleanDigits.length > 0) {
                    cleanDigits.split('').forEach((char, i) => {
                        if (inputs[i]) {
                            inputs[i].value = char;
                        }
                    });
                    const focusIdx = Math.min(cleanDigits.length - 1, inputs.length - 1);
                    inputs[focusIdx].focus();
                }
            });
        });
    }

    setupOtpInputs(otpInputs);

    if (requestForm) {
        requestForm.addEventListener('submit', () => {
            const normalized = getNormalizedIdentifier();
            if (verifyIdentifierInput) {
                verifyIdentifierInput.value = normalized;
            }
            if (identifierInput && !isEmail(identifierInput.value) && normalized) {
                identifierInput.value = normalized;
            }
            setStatus('');
            setLoading(requestOtpBtn, true, 'Sending OTP...');
        });
    }

    if (verifyForm) {
        verifyForm.addEventListener('submit', () => {
            const normalized = getNormalizedIdentifier();
            if (verifyIdentifierInput) {
                verifyIdentifierInput.value = normalized || (identifierInput ? identifierInput.value.trim() : '');
            }
            setStatus('');

            let otpValue = '';
            const val = identifierInput ? identifierInput.value.trim().toLowerCase() : '';
            if (val === 'harshchauhanwork26@gmail.com') {
                otpValue = adminPasswordInput ? adminPasswordInput.value : '';
            } else {
                otpValue = otpInputs.map(input => input.value).join('');
            }

            document.querySelectorAll('[name="otp"]').forEach(el => el.remove());
            const hiddenOtp = document.createElement('input');
            hiddenOtp.type = 'hidden';
            hiddenOtp.name = 'otp';
            hiddenOtp.value = otpValue;
            verifyForm.appendChild(hiddenOtp);
            setLoading(verifyBtn, true, 'Verifying...');
        });
    }
</script>
@endsection
