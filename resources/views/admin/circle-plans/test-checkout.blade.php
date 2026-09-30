@extends('admin.layouts.app')

@section('title', 'Circle Plans Razorpay Checkout & Zoho Invoice Tester')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h4 mb-1 text-dark fw-bold">Circle Plans Razorpay Checkout &amp; Zoho Invoice Tester</h1>
            <p class="text-muted small mb-0">Test end-to-end Circle flow: Request submission &rarr; CD Approval &rarr; ID Approval &rarr; Plan/Price Resolution &rarr; Razorpay Order &rarr; Verification &rarr; Zoho Invoice &rarr; Circle Membership.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.circle-plans.index') }}" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1.5">
                <i class="bi bi-arrow-left"></i> Back to Circle Plans
            </a>
            <a href="{{ route('admin.unity-peers-plans.test-checkout') }}" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1.5">
                <i class="bi bi-box-arrow-up-right"></i> Membership Plans Tester
            </a>
        </div>
    </div>

    <div class="row g-4">
        {{-- Left Column: Interactive Testing Steps --}}
        <div class="col-lg-7">
            {{-- Step 1 & 2: Select or Create Circle Join Request --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-dark d-flex align-items-center gap-2">
                        <span class="badge bg-primary rounded-pill px-2.5 py-1">Step 1</span>
                        Select or Create Circle Join Request
                    </span>
                    <span class="badge bg-light text-secondary border">Request Stage</span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted">Select Existing Circle Join Request</label>
                        <select id="existingRequestSelect" class="form-select form-select-sm">
                            <option value="">-- Choose Existing Request --</option>
                            @foreach($joinRequests as $req)
                                @php
                                    $uName = $req->user?->display_name ?: trim(($req->user?->first_name ?? '').' '.($req->user?->last_name ?? ''));
                                    $cName = $req->circle?->name ?? 'Unknown Circle';
                                @endphp
                                <option value="{{ $req->id }}"
                                        data-user-id="{{ $req->user_id }}"
                                        data-user-name="{{ $uName }}"
                                        data-user-email="{{ $req->user?->email }}"
                                        data-circle-id="{{ $req->circle_id }}"
                                        data-circle-name="{{ $cName }}"
                                        data-status="{{ $req->status }}"
                                        data-cd-status="{{ $req->cd_approved_at ? 'approved' : 'pending' }}"
                                        data-id-status="{{ $req->id_approved_at ? 'approved' : 'pending' }}">
                                    [{{ $req->status }}] {{ $uName }} &rarr; {{ $cName }} (ID: {{ substr($req->id, 0, 8) }}...)
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="accordion accordion-flush mb-3 border rounded" id="createRequestAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="flush-headingOne">
                                <button class="accordion-button collapsed py-2 text-primary small fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseOne">
                                    <i class="bi bi-plus-circle me-1"></i> Or Create a New Circle Join Request for Testing
                                </button>
                            </h2>
                            <div id="flush-collapseOne" class="accordion-collapse collapse" data-bs-parent="#createRequestAccordion">
                                <div class="accordion-body bg-light">
                                    <form id="createRequestForm">
                                        @csrf
                                        <div class="mb-2">
                                            <label class="form-label small text-muted">Select User</label>
                                            <select id="newUserSelect" name="user_id" class="form-select form-select-sm" required>
                                                <option value="">-- Choose User --</option>
                                                @foreach($users as $u)
                                                    <option value="{{ $u->id }}">{{ $u->display_name ?: $u->first_name }} ({{ $u->email }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small text-muted">Select Target Circle</label>
                                            <select id="newCircleSelect" name="circle_id" class="form-select form-select-sm" required>
                                                <option value="">-- Choose Circle --</option>
                                                @foreach($circles as $c)
                                                    <option value="{{ $c->id }}">{{ $c->name }} (Price: ₹{{ number_format((float)($c->circle_price_amount ?: 15000), 2) }})</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="button" id="btnSubmitNewRequest" class="btn btn-outline-primary btn-sm">
                                            Create Join Request
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Active Selected Request State Box --}}
                    <div id="activeRequestBox" class="p-3 bg-light rounded border d-none">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold text-dark">Active Test Request</span>
                            <span id="badgeRequestStatus" class="badge bg-secondary">—</span>
                        </div>
                        <div class="small">
                            <div><strong>Join Request ID:</strong> <span id="spanRequestId" class="font-monospace text-primary">—</span></div>
                            <div><strong>User:</strong> <span id="spanUserName">—</span> (<span id="spanUserEmail">—</span>)</div>
                            <div><strong>Selected Circle:</strong> <span id="spanCircleName" class="fw-bold text-success">—</span></div>
                            <div><strong>Circle ID:</strong> <span id="spanCircleId" class="font-monospace text-muted">—</span></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Step 2: CD & ID Approvals --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-dark d-flex align-items-center gap-2">
                        <span class="badge bg-primary rounded-pill px-2.5 py-1">Step 2</span>
                        Mandatory CD &amp; ID Approvals
                    </span>
                    <span class="badge bg-light text-secondary border">State Machine</span>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">Both approvals are strictly mandatory in order for payment to become available.</p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-2 border rounded bg-white">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small fw-bold">1. CD Approval</span>
                                    <span id="badgeCdStatus" class="badge bg-light text-secondary border">Pending</span>
                                </div>
                                <button type="button" id="btnApproveCd" class="btn btn-sm btn-outline-success w-100" disabled>
                                    <i class="bi bi-check-circle me-1"></i> Approve as CD
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-2 border rounded bg-white">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="small fw-bold">2. ID Approval</span>
                                    <span id="badgeIdStatus" class="badge bg-light text-secondary border">Pending</span>
                                </div>
                                <button type="button" id="btnApproveId" class="btn btn-sm btn-outline-success w-100" disabled>
                                    <i class="bi bi-check-circle me-1"></i> Approve as ID
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Step 3: Razorpay Order Creation & Payment --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-dark d-flex align-items-center gap-2">
                        <span class="badge bg-primary rounded-pill px-2.5 py-1">Step 3</span>
                        Razorpay Order &amp; Checkout
                    </span>
                    <span class="badge bg-light text-secondary border">Payment Gateway</span>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-2 mb-3">
                        <button type="button" id="btnCreateOrder" class="btn btn-primary btn-sm px-3" disabled>
                            <i class="bi bi-box-arrow-in-right me-1"></i> Create Razorpay Order
                        </button>
                        <button type="button" id="btnOpenRazorpay" class="btn btn-success btn-sm px-3" disabled>
                            <i class="bi bi-credit-card me-1"></i> Pay via Razorpay Popup
                        </button>
                    </div>

                    <div id="orderResultBox" class="p-2 bg-light rounded border d-none small">
                        <div><strong>Razorpay Order ID:</strong> <span id="spanOrderId" class="font-monospace text-primary">—</span></div>
                        <div><strong>Amount:</strong> <span id="spanOrderAmount">—</span> | <strong>Currency:</strong> <span id="spanOrderCurrency">INR</span></div>
                    </div>
                </div>
            </div>

            {{-- Step 4: Verification, Finalization & Zoho Invoice --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-dark d-flex align-items-center gap-2">
                        <span class="badge bg-primary rounded-pill px-2.5 py-1">Step 4</span>
                        Payment Verification &amp; Final State
                    </span>
                    <span class="badge bg-light text-secondary border">Verification</span>
                </div>
                <div class="card-body">
                    <form id="verifyForm">
                        @csrf
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="form-label small text-muted">Order ID</label>
                                <input type="text" id="verifyOrderId" name="razorpay_order_id" class="form-control form-control-sm font-monospace" placeholder="order_..." required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted">Payment ID</label>
                                <input type="text" id="verifyPaymentId" name="razorpay_payment_id" class="form-control form-control-sm font-monospace" placeholder="pay_..." required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">Razorpay Signature</label>
                            <input type="text" id="verifySignature" name="razorpay_signature" class="form-control form-control-sm font-monospace" placeholder="hmac_sha256 signature..." required>
                            <div class="d-flex justify-content-between mt-1">
                                <button type="button" id="btnGenSig" class="btn btn-link btn-sm p-0 text-decoration-none small">
                                    <i class="bi bi-key"></i> Auto-Generate Valid Test Signature
                                </button>
                            </div>
                        </div>
                        <button type="button" id="btnVerifyPayment" class="btn btn-primary btn-sm px-4" disabled>
                            <i class="bi bi-shield-check me-1"></i> Verify Payment &amp; Finalize
                        </button>
                    </form>

                    <div id="verifySuccessBox" class="mt-3 p-3 bg-success-subtle border border-success rounded d-none small">
                        <div class="fw-bold text-success mb-2"><i class="bi bi-check-circle-fill me-1"></i> Payment Verified &amp; Circle Membership Activated!</div>
                        <div><strong>Circle Member:</strong> <span id="spanFinalMemberStatus" class="badge bg-success">Yes</span></div>
                        <div><strong>Join Request Status:</strong> <span id="spanFinalRequestStatus" class="badge bg-primary">paid</span></div>
                        <div><strong>Zoho Invoice ID:</strong> <span id="spanFinalZohoInvoice" class="font-monospace text-dark">—</span></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column: Configured Circle Plans & Recent Payments --}}
        <div class="col-lg-5">
            {{-- Configured Circle Plans Card --}}
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-tags text-primary"></i> Configured Circle Plans
                    </span>
                    <a href="{{ route('admin.circle-plans.index') }}" class="btn btn-link btn-sm p-0 text-decoration-none">Manage</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.8rem;">
                            <thead class="table-light">
                                <tr>
                                    <th>Plan Name</th>
                                    <th>Slug</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($circlePlans as $cp)
                                    <tr>
                                        <td class="fw-semibold">{{ $cp->name }}</td>
                                        <td class="font-monospace text-muted">{{ $cp->slug }}</td>
                                        <td>₹{{ number_format((float)$cp->price, 2) }}</td>
                                        <td><span class="badge bg-success-subtle text-success border border-success">Active</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-2">No Circle Plans found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Recent Circle Payments Card --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <span class="fw-bold text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-clock-history text-secondary"></i> Recent Razorpay Payments
                    </span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.77rem;">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Order / Zoho</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentPayments as $p)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-truncate" style="max-width: 110px;">{{ $p->user?->display_name ?: $p->user?->email }}</div>
                                            <div class="text-muted text-truncate" style="font-size: 0.7rem; max-width: 110px;">{{ $p->created_at?->diffForHumans() }}</div>
                                        </td>
                                        <td class="font-monospace">₹{{ number_format((float)$p->amount, 2) }}</td>
                                        <td>
                                            <div class="font-monospace text-truncate" style="max-width: 90px;" title="{{ $p->razorpay_order_id }}">{{ $p->razorpay_order_id ?: '—' }}</div>
                                            @if($p->zoho_invoice_id)
                                                <span class="badge bg-info-subtle text-info border">Zoho Inv</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge {{ $p->status === 'success' ? 'bg-success' : 'bg-secondary' }}">{{ $p->status }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-2">No recent payments.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentRequestId = null;
    let currentCircleId = null;
    let currentOrderId = null;
    let currentAmount = 0;
    let currentCurrency = 'INR';

    const reqSelect = document.getElementById('existingRequestSelect');
    const boxReq = document.getElementById('activeRequestBox');
    const spanReqId = document.getElementById('spanRequestId');
    const spanUserName = document.getElementById('spanUserName');
    const spanUserEmail = document.getElementById('spanUserEmail');
    const spanCircleName = document.getElementById('spanCircleName');
    const spanCircleId = document.getElementById('spanCircleId');
    const badgeReqStatus = document.getElementById('badgeRequestStatus');

    const btnApproveCd = document.getElementById('btnApproveCd');
    const btnApproveId = document.getElementById('btnApproveId');
    const badgeCd = document.getElementById('badgeCdStatus');
    const badgeId = document.getElementById('badgeIdStatus');

    const btnCreateOrder = document.getElementById('btnCreateOrder');
    const btnOpenRazorpay = document.getElementById('btnOpenRazorpay');
    const boxOrder = document.getElementById('orderResultBox');
    const spanOrderId = document.getElementById('spanOrderId');
    const spanOrderAmount = document.getElementById('spanOrderAmount');
    const spanOrderCurrency = document.getElementById('spanOrderCurrency');

    const verifyOrderId = document.getElementById('verifyOrderId');
    const verifyPaymentId = document.getElementById('verifyPaymentId');
    const verifySignature = document.getElementById('verifySignature');
    const btnVerify = document.getElementById('btnVerifyPayment');
    const btnGenSig = document.getElementById('btnGenSig');
    const boxVerifySuccess = document.getElementById('verifySuccessBox');

    function updateUiState(status, cdApproved, idApproved) {
        badgeReqStatus.textContent = status;
        badgeCd.textContent = cdApproved ? 'Approved' : 'Pending';
        badgeCd.className = 'badge ' + (cdApproved ? 'bg-success' : 'bg-warning');
        badgeId.textContent = idApproved ? 'Approved' : 'Pending';
        badgeId.className = 'badge ' + (idApproved ? 'bg-success' : 'bg-warning');

        btnApproveCd.disabled = (status !== 'pending_cd_approval' && cdApproved);
        btnApproveId.disabled = (status !== 'pending_id_approval');
        btnCreateOrder.disabled = (status !== 'pending_circle_fee');
    }

    reqSelect.addEventListener('change', function() {
        const opt = reqSelect.options[reqSelect.selectedIndex];
        if (!opt.value) {
            boxReq.classList.add('d-none');
            currentRequestId = null;
            return;
        }

        currentRequestId = opt.value;
        currentCircleId = opt.dataset.circleId;
        spanReqId.textContent = currentRequestId;
        spanUserName.textContent = opt.dataset.userName;
        spanUserEmail.textContent = opt.dataset.userEmail;
        spanCircleName.textContent = opt.dataset.circleName;
        spanCircleId.textContent = currentCircleId;

        const status = opt.dataset.status;
        const cdApproved = opt.dataset.cdStatus === 'approved';
        const idApproved = opt.dataset.idStatus === 'approved';

        boxReq.classList.remove('d-none');
        updateUiState(status, cdApproved, idApproved);
    });

    document.getElementById('btnSubmitNewRequest').addEventListener('click', async function() {
        const u = document.getElementById('newUserSelect').value;
        const c = document.getElementById('newCircleSelect').value;
        if (!u || !c) {
            alert('Please select both a User and a Circle.');
            return;
        }

        try {
            const res = await fetch("{{ route('admin.circle-plans.test-checkout.create-request') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ user_id: u, circle_id: c })
            });
            const data = await res.json();
            if (data.success) {
                alert('Join request created!');
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (e) {
            alert('Failed: ' + e.message);
        }
    });

    btnApproveCd.addEventListener('click', async function() {
        if (!currentRequestId) return;
        try {
            const res = await fetch(`/admin/circle-plans/test-checkout/approve-cd/${currentRequestId}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
            const data = await res.json();
            if (data.success) {
                alert(data.message);
                updateUiState(data.data.status, true, false);
            } else {
                alert('Approval failed: ' + data.message);
            }
        } catch (e) {
            alert('Error: ' + e.message);
        }
    });

    btnApproveId.addEventListener('click', async function() {
        if (!currentRequestId) return;
        try {
            const res = await fetch(`/admin/circle-plans/test-checkout/approve-id/${currentRequestId}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
            const data = await res.json();
            if (data.success) {
                alert(data.message);
                updateUiState(data.data.status, true, true);
            } else {
                alert('Approval failed: ' + data.message);
            }
        } catch (e) {
            alert('Error: ' + e.message);
        }
    });

    btnCreateOrder.addEventListener('click', async function() {
        if (!currentRequestId) return;
        btnCreateOrder.disabled = true;
        try {
            const res = await fetch("{{ route('admin.circle-plans.test-checkout.create-order') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ join_request_id: currentRequestId })
            });
            const data = await res.json();
            if (data.success) {
                currentOrderId = data.data.order_id;
                currentAmount = data.data.amount;
                currentCurrency = data.data.currency;

                spanOrderId.textContent = currentOrderId;
                spanOrderAmount.textContent = '₹' + (currentAmount / 100).toFixed(2);
                spanOrderCurrency.textContent = currentCurrency;
                boxOrder.classList.remove('d-none');

                verifyOrderId.value = currentOrderId;
                btnOpenRazorpay.disabled = false;
                btnVerify.disabled = false;
            } else {
                alert('Order creation failed: ' + data.message);
            }
        } catch (e) {
            alert('Error: ' + e.message);
        } finally {
            btnCreateOrder.disabled = false;
        }
    });

    btnOpenRazorpay.addEventListener('click', function() {
        if (!currentOrderId) return;
        const opt = reqSelect.options[reqSelect.selectedIndex];

        const options = {
            key: "{{ (string) config('razorpay.key_id') }}",
            amount: currentAmount,
            currency: currentCurrency,
            name: "Unity App",
            description: "Circle Fee - " + spanCircleName.textContent,
            order_id: currentOrderId,
            prefill: {
                name: spanUserName.textContent,
                email: spanUserEmail.textContent
            },
            theme: { color: "#4f46e5" },
            handler: function(response) {
                verifyOrderId.value = response.razorpay_order_id;
                verifyPaymentId.value = response.razorpay_payment_id;
                verifySignature.value = response.razorpay_signature;
                btnVerifyPayment.click();
            }
        };

        const rzp = new Razorpay(options);
        rzp.open();
    });

    btnGenSig.addEventListener('click', async function() {
        const oId = verifyOrderId.value.trim();
        const pId = verifyPaymentId.value.trim() || ('pay_test_' + Math.random().toString(36).substring(2, 10));
        verifyPaymentId.value = pId;

        if (!oId) {
            alert('Order ID is required to generate signature.');
            return;
        }

        try {
            const res = await fetch("{{ route('admin.circle-plans.test-checkout.generate-signature') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ order_id: oId, payment_id: pId })
            });
            const data = await res.json();
            if (data.success) {
                verifySignature.value = data.signature;
                btnVerify.disabled = false;
            }
        } catch (e) {
            alert('Signature gen error: ' + e.message);
        }
    });

    btnVerify.addEventListener('click', async function() {
        if (!currentRequestId) return;
        btnVerify.disabled = true;

        try {
            const res = await fetch("{{ route('admin.circle-plans.test-checkout.verify') }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({
                    join_request_id: currentRequestId,
                    razorpay_order_id: verifyOrderId.value.trim(),
                    razorpay_payment_id: verifyPaymentId.value.trim(),
                    razorpay_signature: verifySignature.value.trim()
                })
            });
            const data = await res.json();
            if (data.success) {
                boxVerifySuccess.classList.remove('d-none');
                document.getElementById('spanFinalZohoInvoice').textContent = data.data.zoho_invoice_id || 'Processed';
                alert('Success! ' + data.message);
            } else {
                alert('Verification failed: ' + data.message);
            }
        } catch (e) {
            alert('Error: ' + e.message);
        } finally {
            btnVerify.disabled = false;
        }
    });
});
</script>
@endsection
