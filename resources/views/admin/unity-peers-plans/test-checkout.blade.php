@extends('admin.layouts.app')

@section('title', 'Razorpay & Zoho Invoice Checkout Tester')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h4 mb-1 text-dark fw-bold">Razorpay Checkout &amp; Zoho Invoice Tester</h1>
            <p class="text-muted small mb-0">Simulate mobile/web subscription checkouts, test order verification, and inspect automated Zoho invoice creation.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.unity-peers-plans.index') }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1.5">
                <i class="bi bi-list-check"></i> View Plans
            </a>
        </div>
    </div>

    <div class="row g-4">
        {{-- Left Column: Interactive Checkout & Manual Verify --}}
        <div class="col-lg-7">
            {{-- Mode 1: Full Interactive Razorpay Flow --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-dark d-flex align-items-center gap-2">
                        <span class="badge bg-primary rounded-pill px-2.5 py-1">Mode 1</span>
                        Live Interactive Razorpay Checkout
                    </span>
                    <span class="badge bg-light text-secondary border">Razorpay Test Mode</span>
                </div>
                <div class="card-body">
                    <form id="liveCheckoutForm">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Select User to Upgrade</label>
                            <select id="userSelect" name="user_id" class="form-select form-select-sm" required>
                                <option value="">-- Choose User --</option>
                                @foreach($users as $u)
                                    @php
                                        $uName = $u->display_name ?: trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
                                    @endphp
                                    <option value="{{ $u->id }}" 
                                            data-email="{{ $u->email }}" 
                                            data-name="{{ $uName }}" 
                                            data-phone="{{ $u->phone }}" 
                                            data-gst="{{ $u->gst_number ?? '' }}"
                                            data-status="{{ $u->membership_status }}"
                                            data-zoho-customer="{{ $u->zoho_customer_id }}"
                                            data-zoho-invoice="{{ $u->zoho_last_invoice_id }}">
                                        [{{ $u->membership_status ?? 'no_status' }}] {{ $uName ?: $u->email }} ({{ $u->email }})
                                    </option>
                                @endforeach
                            </select>
                            <div id="selectedUserDetails" class="mt-2 p-2 bg-light rounded small d-none">
                                <div><strong>Email:</strong> <span id="infoUserEmail">—</span> | <strong>Status:</strong> <span id="infoUserStatus" class="badge bg-secondary">—</span> | <strong>GSTIN:</strong> <span id="infoUserGst" class="font-monospace text-primary">—</span></div>
                                <div class="text-muted mt-1">Zoho Customer: <span id="infoUserZohoCustomer">—</span> | Last Zoho Invoice: <span id="infoUserZohoInvoice">—</span></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">Select Membership Plan</label>
                            <select id="planSelect" name="membership_plan_id" class="form-select form-select-sm" required>
                                <option value="">-- Choose Plan --</option>
                                @foreach($plans as $p)
                                    @php
                                        $base = (float) $p->price;
                                        $gst = round($base * ((float) $p->gst_percent / 100), 2);
                                        $tot = $base + $gst;
                                    @endphp
                                    <option value="{{ $p->id }}" data-price="{{ $tot }}" data-name="{{ $p->name }}">
                                        {{ $p->name }} — ₹{{ number_format($tot, 2) }} (Base: ₹{{ number_format($base, 2) }} + GST: ₹{{ number_format($gst, 2) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted">GST Number <span class="text-muted fw-normal">(Optional for GST Invoice)</span></label>
                            <input type="text" id="orderGstNumber" name="gst_number" class="form-control form-control-sm font-monospace text-uppercase" placeholder="e.g. 24AAACC1206D1ZM" maxlength="15">
                            <div class="form-text small" style="font-size: 0.73rem;">If provided, saved to DB backup, passed to Razorpay notes, and registered in Zoho customer &amp; invoice.</div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" id="btnCreateOrder" class="btn btn-primary btn-sm px-3 d-inline-flex align-items-center gap-1.5">
                                <i class="bi bi-box-arrow-in-right"></i> 1. Create Razorpay Order
                            </button>
                            <button type="button" id="btnOpenRazorpay" class="btn btn-success btn-sm px-3 d-inline-flex align-items-center gap-1.5" disabled>
                                <i class="bi bi-credit-card"></i> 2. Pay via Razorpay Popup
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Mode 2: Manual Flutter Order ID Verification --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-dark d-flex align-items-center gap-2">
                        <span class="badge bg-secondary rounded-pill px-2.5 py-1">Mode 2</span>
                        Manual Verification (Flutter / Custom Order ID)
                    </span>
                    <span class="text-muted small">Verify any existing order</span>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">If you created an order from Flutter, Postman, or terminal, paste the order ID and payment details below to verify and test Zoho invoice creation.</p>
                    
                    <form id="manualVerifyForm">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Razorpay Order ID</label>
                            <input type="text" id="manualOrderId" name="razorpay_order_id" class="form-control form-control-sm font-monospace" placeholder="order_..." required>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-semibold">Razorpay Payment ID</label>
                            <input type="text" id="manualPaymentId" name="razorpay_payment_id" class="form-control form-control-sm font-monospace" placeholder="pay_..." required>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label small fw-semibold mb-0">Signature (HMAC-SHA256)</label>
                                <button type="button" id="btnGenerateSig" class="btn btn-link btn-sm p-0 text-decoration-none small" style="font-size: 0.75rem;">
                                    <i class="bi bi-magic"></i> Auto-Generate Valid Signature
                                </button>
                            </div>
                            <input type="text" id="manualSignature" name="razorpay_signature" class="form-control form-control-sm font-monospace" placeholder="64-character hex signature...">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">GST Number <span class="text-muted fw-normal">(Optional for GST Invoice)</span></label>
                            <input type="text" id="manualGstNumber" name="gst_number" class="form-control form-control-sm font-monospace text-uppercase" placeholder="e.g. 24AAACC1206D1ZM" maxlength="15">
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="skipSigCheck" name="skip_signature_verification" value="1">
                            <label class="form-check-label small text-muted" for="skipSigCheck">
                                Skip signature check (Test mode bypass)
                            </label>
                        </div>

                        <button type="button" id="btnManualVerify" class="btn btn-dark btn-sm w-100 py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2">
                            <i class="bi bi-check2-circle"></i> Verify Payment &amp; Sync Zoho Invoice
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Right Column: Live Status & Inspector --}}
        <div class="col-lg-5">
            {{-- Live Status Card --}}
            <div class="card shadow-sm border-0 mb-4 sticky-top" style="top: 20px; z-index: 10;">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-dark"><i class="bi bi-activity text-primary me-1"></i> Live Execution Result</span>
                    <span id="execBadge" class="badge bg-secondary">Idle</span>
                </div>
                <div class="card-body">
                    {{-- Status message --}}
                    <div id="statusAlert" class="alert alert-light border small py-2 d-none"></div>

                    {{-- Step Indicators --}}
                    <div class="list-group list-group-flush border rounded mb-3 small">
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2" id="stepOrder">
                            <span><i class="bi bi-1-circle me-1.5 text-muted"></i> <strong>Order Created:</strong></span>
                            <span class="step-val font-monospace text-muted">—</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2" id="stepPayment">
                            <span><i class="bi bi-2-circle me-1.5 text-muted"></i> <strong>Payment Status:</strong></span>
                            <span class="step-val badge bg-light text-dark">—</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2" id="stepUserStatus">
                            <span><i class="bi bi-3-circle me-1.5 text-muted"></i> <strong>User Membership:</strong></span>
                            <span class="step-val badge bg-light text-dark">—</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2" id="stepZohoCustomer">
                            <span><i class="bi bi-4-circle me-1.5 text-muted"></i> <strong>Zoho Customer ID:</strong></span>
                            <span class="step-val font-monospace text-muted">—</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-2" id="stepZohoInvoice">
                            <span><i class="bi bi-5-circle me-1.5 text-muted"></i> <strong>Zoho Invoice Status:</strong></span>
                            <span class="step-val badge bg-light text-dark">—</span>
                        </div>
                    </div>

                    {{-- Action buttons after sync --}}
                    <div id="postSuccessActions" class="d-none mb-3 d-flex flex-column gap-2">
                        <a id="btnViewUserInvoices" href="https://subscriptions.zoho.in/app/60028737294#/invoices" target="_blank" class="btn btn-outline-primary btn-sm text-start d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-receipt me-1.5"></i> Open Zoho Billing Invoices Console</span>
                            <i class="bi bi-box-arrow-up-right small"></i>
                        </a>
                    </div>

                    {{-- Raw JSON Response --}}
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small fw-semibold text-muted">Raw API Response</span>
                            <button type="button" id="btnClearLog" class="btn btn-link btn-sm p-0 text-muted small text-decoration-none">Clear</button>
                        </div>
                        <pre id="jsonLog" class="bg-dark text-success p-2.5 rounded font-monospace small mb-0" style="max-height: 240px; overflow-y: auto; font-size: 0.73rem;">Waiting for action...</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Bottom Section: Recent Payments Table --}}
    <div class="card shadow-sm border-0 mt-4">
        <div class="card-header bg-white py-3 border-bottom">
            <h5 class="card-title h6 mb-0 fw-bold text-dark">Recent Razorpay Membership Payments</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0" style="font-size: 0.82rem;">
                <thead class="table-light">
                    <tr>
                        <th class="py-2.5 ps-3">User</th>
                        <th class="py-2.5">Plan</th>
                        <th class="py-2.5">Amount</th>
                        <th class="py-2.5">Status</th>
                        <th class="py-2.5">Order ID</th>
                        <th class="py-2.5">Payment ID</th>
                        <th class="py-2.5">Zoho Invoice ID</th>
                        <th class="py-2.5 pe-3">Paid At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentPayments as $pay)
                        @php
                            $pUser = $pay->user;
                            $uDisplayName = $pUser?->display_name ?: trim(($pUser?->first_name ?? '').' '.($pUser?->last_name ?? ''));
                        @endphp
                        <tr>
                            <td class="ps-3">
                                <div class="fw-semibold text-dark">{{ $uDisplayName ?: ($pUser?->email ?? 'Unknown') }}</div>
                                <div class="text-muted small">{{ $pUser?->email }}</div>
                                @if(!empty($pay->gst_number) || !empty($pUser?->gst_number))
                                    <div class="mt-0.5"><span class="badge bg-light text-primary border font-monospace" style="font-size: 0.68rem;">GST: {{ $pay->gst_number ?: $pUser?->gst_number }}</span></div>
                                @endif
                            </td>
                            <td>{{ $pay->plan?->name ?? '—' }}</td>
                            <td class="fw-bold">₹{{ number_format((float) $pay->total_amount, 2) }}</td>
                            <td>
                                @if($pay->status === 'success')
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Success</span>
                                @elseif($pay->status === 'failed')
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Failed</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary border">{{ $pay->status }}</span>
                                @endif
                            </td>
                            <td class="font-monospace text-muted small">{{ $pay->razorpay_order_id ?? '—' }}</td>
                            <td class="font-monospace text-muted small">{{ $pay->razorpay_payment_id ?? '—' }}</td>
                            <td>
                                @if($pay->zoho_invoice_id)
                                    <span class="badge bg-info-subtle text-info border font-monospace">{{ $pay->zoho_invoice_id }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-muted pe-3">{{ $pay->paid_at?->format('Y-m-d H:i') ?? $pay->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No Razorpay payments recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Razorpay Standard Checkout JS --}}
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const razorpayKeyId = @json($razorpayKeyId);
    let activeOrderData = null;

    const userSelect = document.getElementById('userSelect');
    const planSelect = document.getElementById('planSelect');
    const selectedUserDetails = document.getElementById('selectedUserDetails');
    const infoUserEmail = document.getElementById('infoUserEmail');
    const infoUserStatus = document.getElementById('infoUserStatus');
    const infoUserZohoCustomer = document.getElementById('infoUserZohoCustomer');
    const infoUserZohoInvoice = document.getElementById('infoUserZohoInvoice');

    const btnCreateOrder = document.getElementById('btnCreateOrder');
    const btnOpenRazorpay = document.getElementById('btnOpenRazorpay');

    const manualOrderId = document.getElementById('manualOrderId');
    const manualPaymentId = document.getElementById('manualPaymentId');
    const manualSignature = document.getElementById('manualSignature');
    const skipSigCheck = document.getElementById('skipSigCheck');
    const btnGenerateSig = document.getElementById('btnGenerateSig');
    const btnManualVerify = document.getElementById('btnManualVerify');

    const execBadge = document.getElementById('execBadge');
    const statusAlert = document.getElementById('statusAlert');
    const jsonLog = document.getElementById('jsonLog');
    const btnClearLog = document.getElementById('btnClearLog');

    const stepOrder = document.getElementById('stepOrder');
    const stepPayment = document.getElementById('stepPayment');
    const stepUserStatus = document.getElementById('stepUserStatus');
    const stepZohoCustomer = document.getElementById('stepZohoCustomer');
    const stepZohoInvoice = document.getElementById('stepZohoInvoice');
    const postSuccessActions = document.getElementById('postSuccessActions');
    const btnViewUserInvoices = document.getElementById('btnViewUserInvoices');

    const infoUserGst = document.getElementById('infoUserGst');
    const orderGstNumber = document.getElementById('orderGstNumber');
    const manualGstNumber = document.getElementById('manualGstNumber');

    // Update user info panel on select
    userSelect.addEventListener('change', function () {
        const opt = userSelect.options[userSelect.selectedIndex];
        if (!opt || !opt.value) {
            selectedUserDetails.classList.add('d-none');
            return;
        }
        infoUserEmail.textContent = opt.dataset.email || '—';
        infoUserStatus.textContent = opt.dataset.status || 'None';
        infoUserZohoCustomer.textContent = opt.dataset.zohoCustomer || 'Not created yet';
        infoUserZohoInvoice.textContent = opt.dataset.zohoInvoice || 'None';
        if (infoUserGst) infoUserGst.textContent = opt.dataset.gst || 'None';
        if (orderGstNumber) orderGstNumber.value = opt.dataset.gst || '';
        if (manualGstNumber && !manualGstNumber.value) manualGstNumber.value = opt.dataset.gst || '';
        selectedUserDetails.classList.remove('d-none');
    });

    btnClearLog.addEventListener('click', function () {
        jsonLog.textContent = 'Cleared.';
    });

    function logJson(data) {
        jsonLog.textContent = JSON.stringify(data, null, 2);
    }

    function setAlert(type, message) {
        statusAlert.className = `alert alert-${type} border small py-2`;
        statusAlert.innerHTML = message;
        statusAlert.classList.remove('d-none');
    }

    // 1. Create Razorpay Order
    btnCreateOrder.addEventListener('click', async function () {
        const userId = userSelect.value;
        const planId = planSelect.value;
        const gstNumber = orderGstNumber ? orderGstNumber.value.trim() : '';

        if (!userId || !planId) {
            alert('Please select both a User and a Membership Plan.');
            return;
        }

        btnCreateOrder.disabled = true;
        btnCreateOrder.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Creating...';
        execBadge.className = 'badge bg-primary';
        execBadge.textContent = 'Creating Order';

        try {
            const resp = await fetch('{{ route('admin.razorpay-test-checkout.create-order') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ user_id: userId, membership_plan_id: planId, gst_number: gstNumber })
            });

            const data = await resp.json();
            logJson(data);

            if (!data.success) {
                setAlert('danger', `<strong>Order Failed:</strong> ${data.message}`);
                execBadge.className = 'badge bg-danger';
                execBadge.textContent = 'Order Failed';
                return;
            }

            activeOrderData = data;
            manualOrderId.value = data.order_id;
            manualPaymentId.value = 'pay_test_' + Math.random().toString(36).substring(2, 10);

            stepOrder.querySelector('.step-val').textContent = data.order_id;
            stepOrder.querySelector('.step-val').className = 'step-val font-monospace text-success fw-bold';

            setAlert('success', `<strong>Order Created:</strong> Order ID <code>${data.order_id}</code>. Click "Pay via Razorpay Popup" or verify manually.`);
            btnOpenRazorpay.disabled = false;
            execBadge.className = 'badge bg-info';
            execBadge.textContent = 'Order Ready';
        } catch (err) {
            setAlert('danger', 'Network error creating order: ' + err.message);
        } finally {
            btnCreateOrder.disabled = false;
            btnCreateOrder.innerHTML = '<i class="bi bi-box-arrow-in-right"></i> 1. Create Razorpay Order';
        }
    });

    // 2. Open Razorpay Checkout Modal
    btnOpenRazorpay.addEventListener('click', function () {
        if (!activeOrderData) return;

        const optUser = userSelect.options[userSelect.selectedIndex];
        const options = {
            key: activeOrderData.key_id,
            amount: activeOrderData.amount,
            currency: activeOrderData.currency,
            name: 'Unity App',
            description: activeOrderData.plan.name,
            order_id: activeOrderData.order_id,
            prefill: {
                name: optUser.dataset.name,
                email: optUser.dataset.email,
                contact: optUser.dataset.phone || ''
            },
            theme: { color: '#4f46e5' },
            handler: function (response) {
                // Payment succeeded in Razorpay Checkout!
                logJson(response);
                manualOrderId.value = response.razorpay_order_id;
                manualPaymentId.value = response.razorpay_payment_id;
                manualSignature.value = response.razorpay_signature;

                // Auto-trigger verification
                performVerification(response.razorpay_order_id, response.razorpay_payment_id, response.razorpay_signature, false);
            },
            modal: {
                ondismiss: function () {
                    setAlert('warning', 'Razorpay checkout popup closed by user.');
                }
            }
        };

        const rzp = new Razorpay(options);
        rzp.on('payment.failed', function (response) {
            logJson(response.error);
            setAlert('danger', `<strong>Payment Failed:</strong> ${response.error.description}`);
            execBadge.className = 'badge bg-danger';
            execBadge.textContent = 'Failed';
        });
        rzp.open();
    });

    // 3. Auto-Generate Valid Signature Helper
    btnGenerateSig.addEventListener('click', async function () {
        const orderId = manualOrderId.value.trim();
        const paymentId = manualPaymentId.value.trim();

        if (!orderId || !paymentId) {
            alert('Please enter both Order ID and Payment ID first.');
            return;
        }

        try {
            const resp = await fetch('{{ route('admin.razorpay-test-checkout.generate-signature') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ order_id: orderId, payment_id: paymentId })
            });
            const data = await resp.json();
            if (data.success) {
                manualSignature.value = data.signature;
                setAlert('info', 'Valid Razorpay HMAC-SHA256 signature generated.');
            }
        } catch (e) {
            alert('Signature generator error: ' + e.message);
        }
    });

    // 4. Manual Verify Button
    btnManualVerify.addEventListener('click', function () {
        const orderId = manualOrderId.value.trim();
        const paymentId = manualPaymentId.value.trim();
        const sig = manualSignature.value.trim();
        const skip = skipSigCheck.checked;

        if (!orderId || !paymentId) {
            alert('Please provide Order ID and Payment ID.');
            return;
        }

        if (!skip && !sig) {
            alert('Please provide a signature or click "Auto-Generate Valid Signature", or check "Skip signature check".');
            return;
        }

        performVerification(orderId, paymentId, sig, skip);
    });

    // Main verification execution
    async function performVerification(orderId, paymentId, signature, skipCheck) {
        execBadge.className = 'badge bg-primary';
        execBadge.textContent = 'Verifying & Syncing Zoho...';
        btnManualVerify.disabled = true;

        const gstNumber = manualGstNumber?.value.trim() || orderGstNumber?.value.trim() || '';

        try {
            const resp = await fetch('{{ route('admin.razorpay-test-checkout.verify') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    razorpay_order_id: orderId,
                    razorpay_payment_id: paymentId,
                    razorpay_signature: signature,
                    skip_signature_verification: skipCheck ? 1 : 0,
                    gst_number: gstNumber
                })
            });

            const contentType = resp.headers.get('content-type') || '';
            let data;
            if (contentType.includes('application/json')) {
                data = await resp.json();
            } else {
                const rawText = await resp.text();
                setAlert('danger', `<strong>Server Error (${resp.status}):</strong> Please refresh the page if your session timed out.`);
                execBadge.className = 'badge bg-danger';
                execBadge.textContent = 'Server Error';
                jsonLog.textContent = rawText;
                return;
            }
            logJson(data);

            if (!data.success) {
                setAlert('danger', `<strong>Verification Failed:</strong> ${data.message} ${data.hint || ''}`);
                execBadge.className = 'badge bg-danger';
                execBadge.textContent = 'Verify Failed';
                return;
            }

            // Update step badges
            stepPayment.querySelector('.step-val').textContent = 'SUCCESS (Paid)';
            stepPayment.querySelector('.step-val').className = 'step-val badge bg-success';

            stepUserStatus.querySelector('.step-val').textContent = `${data.user.membership_status_label} (${data.user.membership_status})`;
            stepUserStatus.querySelector('.step-val').className = 'step-val badge bg-primary';

            stepZohoCustomer.querySelector('.step-val').textContent = data.user.zoho_customer_id || 'Failed/Pending';
            stepZohoCustomer.querySelector('.step-val').className = data.user.zoho_customer_id ? 'step-val font-monospace text-success fw-bold' : 'step-val text-warning';

            const invoiceStatus = data.zoho.status || 'pending';
            stepZohoInvoice.querySelector('.step-val').textContent = `${invoiceStatus.toUpperCase()}` + (data.zoho.invoice_id ? ` (#${data.zoho.invoice_id})` : '');
            stepZohoInvoice.querySelector('.step-val').className = (invoiceStatus === 'paid') ? 'step-val badge bg-success' : 'step-val badge bg-warning text-dark';

            let msg = `<strong>Payment Verified!</strong> User upgraded to <strong>${data.user.membership_status_label}</strong>.`;
            if (data.zoho.invoice_id) {
                msg += `<br>Zoho Invoice <strong>#${data.zoho.invoice_id}</strong> created with status <strong>PAID</strong>!`;
            } else if (data.zoho.error) {
                msg += `<br><span class="text-danger">Zoho Sync Notice: ${data.zoho.error}</span>`;
            }

            setAlert('success', msg);
            execBadge.className = 'badge bg-success';
            execBadge.textContent = 'Completed';

            postSuccessActions.classList.remove('d-none');
            btnViewUserInvoices.href = data.zoho.invoice_id
                ? `https://subscriptions.zoho.in/app/60028737294#/invoices/${data.zoho.invoice_id}`
                : `https://subscriptions.zoho.in/app/60028737294#/invoices`;
        } catch (err) {
            setAlert('danger', 'Network error during verification: ' + err.message);
            execBadge.className = 'badge bg-danger';
            execBadge.textContent = 'Error';
        } finally {
            btnManualVerify.disabled = false;
        }
    }
});
</script>
@endsection
