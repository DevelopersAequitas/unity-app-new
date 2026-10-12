@extends('admin.layouts.app')

@section('title', 'Meta WhatsApp Settings - Peers Global Unity')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="bi bi-gear-fill text-secondary me-2"></i>Meta WhatsApp Configuration & Diagnostics
            </h1>
            <p class="text-muted small mb-0">Verified Meta Business assets, API settings, and live connectivity status.</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm" id="runDiagnosticBtn">
            <i class="bi bi-activity me-1"></i>Test Meta Connection
        </button>
    </div>

    <!-- Live Diagnostic Result Box -->
    <div id="diagnosticAlert" class="card shadow-sm border-0 mb-4 d-none">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="card-title mb-0 fw-bold" id="diagnosticHeaderTitle">
                <i class="bi bi-cpu me-2"></i>Meta Graph API Connectivity Report
            </h6>
            <span class="badge" id="overallStatusBadge">CHECKING</span>
        </div>
        <div class="card-body">
            <div id="diagnosticSpinner" class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <div class="small text-muted mt-2">Connecting to Meta Graph API servers...</div>
            </div>
            <div id="diagnosticResults" class="d-none">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded border text-center">
                            <span class="text-muted small d-block">Meta Connection</span>
                            <span class="fw-bold fs-6" id="diagConnStatus">-</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded border text-center">
                            <span class="text-muted small d-block">Token Validity</span>
                            <span class="fw-bold fs-6" id="diagTokenStatus">-</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded border text-center">
                            <span class="text-muted small d-block">WABA Access</span>
                            <span class="fw-bold fs-6" id="diagWabaStatus">-</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light rounded border text-center">
                            <span class="text-muted small d-block">Phone Number Access</span>
                            <span class="fw-bold fs-6" id="diagPhoneStatus">-</span>
                        </div>
                    </div>
                </div>

                <div id="diagErrorBox" class="alert alert-danger mt-3 d-none">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><span id="diagErrorMessage"></span>
                </div>

                <div class="mt-3">
                    <h6 class="fw-bold small text-muted text-uppercase">Diagnostic Details</h6>
                    <pre class="bg-dark text-light p-3 rounded font-monospace small mb-0" id="diagRawJson"></pre>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Meta Credentials Overview -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="bi bi-shield-lock me-2 text-primary"></i>Meta Credentials & IDs
                    </h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm mb-0">
                        <tr>
                            <td class="text-muted" style="width: 200px;">Meta App ID:</td>
                            <td class="font-monospace fw-bold">{{ $config['app_id'] }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Business ID:</td>
                            <td class="font-monospace fw-bold">{{ $config['business_id'] }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">WABA ID:</td>
                            <td class="font-monospace fw-bold text-primary">{{ $config['waba_id'] }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Phone Number ID:</td>
                            <td class="font-monospace fw-bold text-primary">{{ $config['phone_number_id'] }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Graph API Version:</td>
                            <td><span class="badge bg-secondary font-monospace">{{ $config['api_version'] }}</span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">System User Access Token:</td>
                            <td>
                                @if($config['token_configured'])
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>CONFIGURED (Protected)</span>
                                @else
                                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>NOT CONFIGURED</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Phone Sender & Webhook Overview -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="bi bi-telephone-outbound me-2 text-success"></i>Sender Number & Webhook
                    </h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless table-sm mb-0">
                        <tr>
                            <td class="text-muted" style="width: 200px;">Business Sender Number:</td>
                            <td class="font-monospace fw-bold text-success">{{ $config['display_phone'] }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Verified Name:</td>
                            <td class="fw-semibold">{{ $config['verified_name'] }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Testing Recipient:</td>
                            <td class="font-monospace fw-bold text-warning">{{ $config['test_recipient'] }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted">Webhook Callback URL:</td>
                            <td><code class="small text-break">{{ $config['webhook_url'] }}</code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Webhook Verify Token:</td>
                            <td>
                                @if($config['webhook_verify_token_configured'])
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>CONFIGURED</span>
                                @else
                                    <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>NOT CONFIGURED</span>
                                @endif
                            </td>
                        </tr>
                    </table>

                    <div class="alert alert-warning border-warning mt-3 mb-0 small">
                        <div class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-1 text-warning"></i>Live Webhook Forwarding Notice:</div>
                        Meta Cloud API dispatches <code>DELIVERED</code>, <code>READ</code>, and <code>FAILED</code> status updates via Webhook. Currently, your Meta phone number is configured to send webhooks to <code>https://fleximsg.com/whatsapp/webhook</code>.
                        <div class="mt-1">To receive live delivery status & failure codes directly into Peers Unity, ensure your ngrok tunnel is active and set the Callback URL in <strong>Meta App Dashboard &gt; WhatsApp &gt; Configuration</strong> to:</div>
                        <code class="d-block mt-1 p-1 bg-white rounded border text-primary">{{ $config['webhook_url'] }}</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const runBtn = document.getElementById('runDiagnosticBtn');
    const diagAlert = document.getElementById('diagnosticAlert');
    const spinner = document.getElementById('diagnosticSpinner');
    const results = document.getElementById('diagnosticResults');
    const overallBadge = document.getElementById('overallStatusBadge');

    const diagConn = document.getElementById('diagConnStatus');
    const diagToken = document.getElementById('diagTokenStatus');
    const diagWaba = document.getElementById('diagWabaStatus');
    const diagPhone = document.getElementById('diagPhoneStatus');
    const diagErrorBox = document.getElementById('diagErrorBox');
    const diagErrorMsg = document.getElementById('diagErrorMessage');
    const diagRawJson = document.getElementById('diagRawJson');

    runBtn.addEventListener('click', function() {
        diagAlert.classList.remove('d-none');
        spinner.classList.remove('d-none');
        results.classList.add('d-none');
        diagErrorBox.classList.add('d-none');
        overallBadge.className = 'badge bg-secondary';
        overallBadge.textContent = 'CHECKING...';

        fetch("{{ route('admin.whatsapp.test_connection') }}")
            .then(res => res.json())
            .then(data => {
                spinner.classList.add('d-none');
                results.classList.remove('d-none');

                const report = data.data || {};

                if (data.success) {
                    overallBadge.className = 'badge bg-success';
                    overallBadge.textContent = 'CONNECTED';
                } else {
                    overallBadge.className = 'badge bg-danger';
                    overallBadge.textContent = 'ATTENTION REQUIRED';
                }

                diagConn.textContent = report.connected ? 'CONNECTED' : 'DISCONNECTED';
                diagConn.className = report.connected ? 'fw-bold fs-6 text-success' : 'fw-bold fs-6 text-danger';

                diagToken.textContent = report.token_status || 'INVALID';
                diagToken.className = (report.token_status === 'VALID') ? 'fw-bold fs-6 text-success' : 'fw-bold fs-6 text-danger';

                diagWaba.textContent = report.waba_access || 'NOT AVAILABLE';
                diagWaba.className = (report.waba_access === 'AVAILABLE') ? 'fw-bold fs-6 text-success' : 'fw-bold fs-6 text-danger';

                diagPhone.textContent = report.phone_number_access || 'NOT AVAILABLE';
                diagPhone.className = (report.phone_number_access === 'AVAILABLE') ? 'fw-bold fs-6 text-success' : 'fw-bold fs-6 text-danger';

                if (report.error) {
                    diagErrorBox.classList.remove('d-none');
                    diagErrorMsg.textContent = report.error;
                }

                diagRawJson.textContent = JSON.stringify(report, null, 2);
            })
            .catch(err => {
                spinner.classList.add('d-none');
                results.classList.remove('d-none');
                overallBadge.className = 'badge bg-danger';
                overallBadge.textContent = 'FAILED';
                diagErrorBox.classList.remove('d-none');
                diagErrorMsg.textContent = 'Failed to execute test connection request: ' + err;
            });
    });
});
</script>
@endpush
@endsection
