@extends('admin.layouts.auth')

@section('title', 'Admin Login')

@section('content')
<!-- intl-tel-input CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/css/intlTelInput.min.css">

<style>
    /* Dark Theme Customization for intl-tel-input */
    .iti {
        width: 100%;
        display: block;
    }
    .iti__country-container {
        border-top-left-radius: 12px;
        border-bottom-left-radius: 12px;
    }
    .iti__selected-country {
        background: rgba(255, 255, 255, 0.03) !important;
        border-radius: 12px 0 0 12px;
        padding: 0 12px !important;
        pointer-events: none !important;
        cursor: default !important;
        user-select: none !important;
    }
    .iti__selected-dial-code {
        color: #cbd5e1 !important;
        font-weight: 600;
        font-size: 15px;
        margin-left: 6px;
    }
    .iti__selected-dial-code:empty {
        display: none !important;
        margin-left: 0 !important;
    }
    .iti__arrow {
        display: none !important;
    }
    .iti__dropdown-content {
        background-color: #111827 !important;
        border: 1px solid rgba(255, 255, 255, 0.15) !important;
        border-radius: 14px !important;
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.6) !important;
        backdrop-filter: blur(12px) !important;
        max-height: 280px !important;
        z-index: 100 !important;
    }
    .iti__search-input {
        background: rgba(255, 255, 255, 0.07) !important;
        color: #f3f4f6 !important;
        border: 1px solid rgba(255, 255, 255, 0.15) !important;
        border-radius: 8px !important;
        padding: 10px 12px !important;
        margin: 8px !important;
        width: calc(100% - 16px) !important;
        font-size: 14px !important;
    }
    .iti__search-input:focus {
        outline: none;
        border-color: var(--accent) !important;
    }
    .iti__country-list {
        background-color: transparent !important;
    }
    .iti__country {
        padding: 10px 14px !important;
        color: #f3f4f6 !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04) !important;
        font-size: 14px !important;
        transition: background-color 0.15s ease;
    }
    .iti__country:hover,
    .iti__country.iti__highlight {
        background-color: #1f2937 !important;
    }
    .iti__country-name {
        color: #f3f4f6 !important;
        font-weight: 500;
        margin-right: 6px;
    }
    .iti__dial-code {
        color: #9ca3af !important;
    }
    .iti--neutral-mode .iti__country-container {
        display: none !important;
    }
    .iti--neutral-mode input#identifier {
        padding-left: 16px !important;
    }
</style>

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
            $initialIdentifier = (string) old('identifier', '');
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
                        autocomplete="off"
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

<!-- intl-tel-input JS -->
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/js/intlTelInput.min.js"></script>

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

    let iti = null;
    if (window.intlTelInput && identifierInput) {
        iti = window.intlTelInput(identifierInput, {
            initialCountry: "",
            separateDialCode: true,
            allowDropdown: false,
            strictMode: true,
            autoPlaceholder: "polite",
            formatOnDisplay: true,
            utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@24.5.0/build/js/utils.js",
        });

        const initialVal = identifierInput.value.trim();
        const itiWrapper = identifierInput.closest('.iti');
        if (itiWrapper && (initialVal === '' || initialVal.includes('@') || /^[a-zA-Z]/.test(initialVal))) {
            itiWrapper.classList.add('iti--neutral-mode');
        }
    }

    /**
     * Get maximum allowed national mobile digits for a given ISO2 country code.
     * Uses intl-tel-input library metadata (libphonenumber) dynamically.
     */
    function getMaxNationalDigits(iso2) {
        if (!iso2) return null;
        const utils = window.intlTelInputUtils || (window.intlTelInput && window.intlTelInput.utils) || (iti && iti.utils);
        if (utils && utils.numberType && utils.getExampleNumber && utils.isPossibleNumber && utils.getCoreNumber) {
            const numberType = utils.numberType['MOBILE'] || 1;
            let example = utils.getExampleNumber(iso2, false, numberType, true);
            let possible = example;
            let s = example;
            while (utils.isPossibleNumber(s, iso2, 'MOBILE') || utils.isPossibleNumber(s, iso2, 'FIXED_LINE_OR_MOBILE')) {
                possible = s;
                s += '0';
            }
            const core = utils.getCoreNumber(possible, iso2);
            if (core && core.length > 0) {
                return core.length;
            }
        }
        if (iti && iti.maxCoreNumberLength) {
            return iti.maxCoreNumberLength;
        }
        return null;
    }

    /**
     * Auto-detect country from international phone number prefix using intl-tel-input metadata.
     */
    function detectCountryFromNumber(input) {
        if (!input || typeof input !== 'string') return null;
        let clean = input.trim();
        if (clean.startsWith('00')) {
            clean = '+' + clean.slice(2);
        }
        if (!clean.startsWith('+')) {
            return null;
        }

        const digits = clean.slice(1).replace(/\D/g, '');
        if (!digits) return null;

        const allCountries = (window.intlTelInput && window.intlTelInput.getCountryData)
            ? window.intlTelInput.getCountryData()
            : [];

        if (!allCountries || allCountries.length === 0) return null;

        for (let len = Math.min(4, digits.length); len >= 1; len--) {
            const prefix = digits.substring(0, len);
            const rest = digits.substring(len);
            const matches = allCountries.filter(c => c.dialCode === prefix);

            if (matches.length > 0) {
                // NANP (+1) area code resolution (US vs Canada vs Caribbean)
                if (matches.length > 1 && prefix === '1' && rest.length > 0) {
                    const areaMatch = matches.find(c => c.areaCodes && c.areaCodes.some(ac => rest.startsWith(ac)));
                    if (areaMatch) return areaMatch;
                }
                // Pick primary country with priority 0 (e.g. gb for +44, us for +1, in for +91, ae for +971)
                matches.sort((a, b) => (a.priority || 0) - (b.priority || 0));
                return matches[0];
            }
        }

        return null;
    }

    /**
     * Parse pasted text or full international number to extract international prefix and national number.
     */
    function parsePastedNumber(pastedText) {
        const clean = (pastedText || '').trim();
        const detected = detectCountryFromNumber(clean);
        if (!detected) {
            return { isInternational: false, nationalNumber: clean, detected: null };
        }

        const dialCode = detected.dialCode;
        const regex = new RegExp('^(?:\\+|00)?' + dialCode + '[\\s\\-\\.]*');
        const nationalNumber = clean.replace(regex, '').trim();

        return { isInternational: true, nationalNumber, detected };
    }

    function isEmail(val) {
        val = (val || '').trim();
        return val.includes('@') || (/^[a-zA-Z]/.test(val) && !val.startsWith('+') && !/^\d+$/.test(val.replace(/[\s\-\(\)]/g, '')));
    }

    function detectChannelFromIdentifier(val) {
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

    function getNormalizedSubmissionValue() {
        if (!identifierInput) return '';
        const rawVal = identifierInput.value.trim();
        if (isEmail(rawVal)) {
            return rawVal;
        }

        const cleanDigits = rawVal.replace(/\D/g, '');
        if (!cleanDigits) return '';

        if (iti) {
            const selectedCountry = iti.getSelectedCountryData();
            const dialCode = selectedCountry?.dialCode || '';

            if (dialCode) {
                // If clean digits already start with dial code (e.g. 447700900123 or 971501234567)
                if (cleanDigits.startsWith(dialCode)) {
                    return '+' + cleanDigits;
                }
                // If clean digits are national number (e.g. 7700900123 with dialCode 44), strip trunk 0 if any
                const nationalDigits = cleanDigits.replace(/^0+/, '');
                return '+' + dialCode + nationalDigits;
            }
        }

        return rawVal.startsWith('+') ? rawVal : '+' + cleanDigits;
    }

    function updateUiForIdentifier(event) {
        const val = identifierInput ? identifierInput.value.trim() : '';
        const itiWrapper = identifierInput ? identifierInput.closest('.iti') : null;

        // 1. Empty State: No country flag, no globe, no dial code
        if (val === '') {
            if (itiWrapper) {
                itiWrapper.classList.add('iti--neutral-mode');
            }
            if (iti) {
                iti.setCountry('');
            }
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

        // 2. Email Mode Check: No country UI & no phone length restriction
        if (isEmail(val)) {
            if (itiWrapper) {
                itiWrapper.classList.add('iti--neutral-mode');
            }
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

        // 3. Phone Mode: Show Country UI with dynamic country detection
        if (itiWrapper) {
            itiWrapper.classList.remove('iti--neutral-mode');
        }
        if (passwordContainer) passwordContainer.style.display = 'none';
        if (adminPasswordInput) adminPasswordInput.required = false;
        if (otpGrid) otpGrid.style.display = 'grid';
        if (otpHelp) otpHelp.style.display = 'block';
        if (verifyLabel) verifyLabel.textContent = 'Enter Verification Code';

        // Dynamic Real-Time Country Detection on Typing / Keystroke
        if (val.startsWith('+') || val.startsWith('00')) {
            const detected = detectCountryFromNumber(val);
            if (detected && iti) {
                const currentCountry = iti.getSelectedCountryData();
                if (!currentCountry || currentCountry.iso2 !== detected.iso2) {
                    iti.setCountry(detected.iso2);
                }
            }
        } else if (val.replace(/\D/g, '').length >= 1) {
            // Local number entered without + prefix: default to India if no country selected yet
            if (iti) {
                const currentCountry = iti.getSelectedCountryData();
                if (!currentCountry || !currentCountry.iso2) {
                    iti.setCountry('in');
                }
            }
        }

        // Country-Specific Max Length Enforcement
        if (iti && !val.startsWith('+') && !val.startsWith('00')) {
            const currentCountry = iti.getSelectedCountryData();
            const maxDigits = getMaxNationalDigits(currentCountry?.iso2);
            if (maxDigits) {
                const cleanDigits = val.replace(/\D/g, '');
                if (cleanDigits.length > maxDigits) {
                    const truncated = cleanDigits.slice(0, maxDigits);
                    identifierInput.value = truncated;
                }
            }
        }

        const channel = detectChannelFromIdentifier(val);
        if (verifyChannelInput) {
            verifyChannelInput.value = channel;
        }

        if (channel === 'whatsapp') {
            if (identifierHelp) identifierHelp.textContent = 'Enter your registered international mobile number.';
            if (otpHelp) otpHelp.textContent = 'Enter the 4-digit verification code sent to your WhatsApp.';
        } else {
            if (identifierHelp) identifierHelp.textContent = 'Enter your registered email address or international mobile number.';
            if (otpHelp) otpHelp.textContent = 'Enter the 4-digit verification code sent to your email or WhatsApp.';
        }

        const normalizedVal = getNormalizedSubmissionValue();
        if (verifyIdentifierInput) {
            verifyIdentifierInput.value = normalizedVal || val;
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
        identifierInput.addEventListener('input', updateUiForIdentifier);
        identifierInput.addEventListener('countrychange', updateUiForIdentifier);

        // Strip country calling code on blur if full international number was typed into input
        identifierInput.addEventListener('blur', () => {
            const rawVal = identifierInput.value.trim();
            if (rawVal.startsWith('+') || rawVal.startsWith('00')) {
                const parsed = parsePastedNumber(rawVal);
                if (parsed.isInternational && parsed.detected && iti) {
                    iti.setCountry(parsed.detected.iso2);
                    let national = parsed.nationalNumber;
                    const maxDigits = getMaxNationalDigits(parsed.detected.iso2);
                    if (maxDigits) {
                        national = national.replace(/\D/g, '').slice(0, maxDigits);
                    }
                    identifierInput.value = national;
                    updateUiForIdentifier();
                }
            }
        });

        // Instant Paste handling for international numbers & emails with length validation
        identifierInput.addEventListener('paste', (event) => {
            const pasteData = (event.clipboardData || window.clipboardData)?.getData('text') || '';
            const trimmed = pasteData.trim();

            if (!trimmed) return;

            if (isEmail(trimmed)) {
                // Email paste handled by standard input event
                return;
            }

            if (trimmed.startsWith('+') || trimmed.startsWith('00')) {
                event.preventDefault();
                const parsed = parsePastedNumber(trimmed);
                if (parsed.isInternational && parsed.detected && iti) {
                    iti.setCountry(parsed.detected.iso2);
                    let national = parsed.nationalNumber;
                    const maxDigits = getMaxNationalDigits(parsed.detected.iso2);
                    if (maxDigits) {
                        national = national.replace(/\D/g, '').slice(0, maxDigits);
                    }
                    identifierInput.value = national;
                } else {
                    identifierInput.value = trimmed;
                }
                updateUiForIdentifier();
            }
        });
    }

    window.addEventListener('load', () => {
        const initialVal = identifierInput ? identifierInput.value.trim() : '';
        if (iti && initialVal && !isEmail(initialVal)) {
            if (initialVal.startsWith('+') || initialVal.startsWith('00')) {
                const parsed = parsePastedNumber(initialVal);
                if (parsed.isInternational && parsed.detected) {
                    iti.setCountry(parsed.detected.iso2);
                    identifierInput.value = parsed.nationalNumber;
                }
            } else if (initialVal.replace(/\D/g, '').length >= 7) {
                iti.setCountry('in');
            }
        }
        updateUiForIdentifier();
    });

    function setupOtpInputs(inputs) {
        inputs.forEach((input, index) => {
            input.addEventListener('input', (event) => {
                const value = event.target.value.replace(/\D/g, '');
                event.target.value = value;
                if (value && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });

            input.addEventListener('keydown', (event) => {
                if (event.key === 'Backspace' && !event.target.value && index > 0) {
                    inputs[index - 1].focus();
                }
            });

            input.addEventListener('paste', (event) => {
                event.preventDefault();
                const pasteData = (event.clipboardData || window.clipboardData).getData('text');
                const cleanDigits = pasteData.replace(/\D/g, '').slice(0, inputs.length);
                if (cleanDigits.length > 0) {
                    cleanDigits.split('').forEach((char, i) => {
                        if (inputs[i]) {
                            inputs[i].value = char;
                        }
                    });
                    const focusIdx = Math.min(cleanDigits.length, inputs.length - 1);
                    inputs[focusIdx].focus();
                }
            });
        });
    }

    setupOtpInputs(otpInputs);

    if (requestForm) {
        requestForm.addEventListener('submit', () => {
            const normalizedVal = getNormalizedSubmissionValue();
            if (verifyIdentifierInput) {
                verifyIdentifierInput.value = normalizedVal;
            }
            if (identifierInput && !isEmail(identifierInput.value) && normalizedVal) {
                identifierInput.value = normalizedVal;
            }
            setStatus('');
            setLoading(requestOtpBtn, true, 'Sending OTP...');
        });
    }

    if (verifyForm) {
        verifyForm.addEventListener('submit', () => {
            const normalizedVal = getNormalizedSubmissionValue();
            if (verifyIdentifierInput) {
                verifyIdentifierInput.value = normalizedVal || (identifierInput ? identifierInput.value.trim() : '');
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


