@extends('admin.layouts.app')

@section('title', 'AI Prompt Flows & Gemini Workflows')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h4 mb-1 fw-bold text-dark"><i class="bi bi-robot me-2 text-primary"></i>AI Prompt Flows & Gemini Workflows</h2>
            <p class="text-muted small mb-0">Control prompts, system instructions, and temperature used by Google Gemini across the platform.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.ai-flows.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> Add New AI Flow
            </a>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#testAiModal">
                <i class="bi bi-play-circle me-1"></i> Live Test Gemini
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4 py-3">Flow Name / Code</th>
                            <th>Description</th>
                            <th>Model</th>
                            <th>Temperature</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($flows as $flow)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark">{{ $flow->name }}</div>
                                    <span class="badge bg-secondary font-monospace">{{ $flow->slug }}</span>
                                </td>
                                <td>
                                    <span class="text-muted small">{{ Str::limit($flow->description ?? 'Custom AI Gemini prompt flow.', 80) }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-info text-dark font-monospace">{{ $flow->model ?? 'gemini-3.1-flash-lite' }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $flow->temperature ?? '0.20' }}</span>
                                </td>
                                <td>
                                    @if($flow->is_active ?? true)
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>
                                    @else
                                        <span class="badge bg-secondary">Disabled</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('admin.ai-flows.edit', $flow->id ?? $flow->slug) }}" class="btn btn-primary">
                                            <i class="bi bi-pencil-square me-1"></i> Edit
                                        </a>
                                        @if(empty($flow->is_virtual) && ($flow->slug ?? '') !== 'business_category_suggestion')
                                        <form action="{{ route('admin.ai-flows.destroy', $flow->id ?? $flow->slug) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this AI Flow?');" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger ms-1" title="Delete Flow">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No AI flows configured.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Live Test Gemini -->
<div class="modal fade" id="testAiModal" tabindex="-1" aria-labelledby="testAiModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="testAiModalLabel"><i class="bi bi-cpu text-primary me-2"></i>Live Test Gemini Category Classifier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="testAiForm">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Company Name</label>
                        <input type="text" class="form-control" id="testCompanyName" value="SolarTech Green Energies Pvt Ltd" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Designation / Role</label>
                        <input type="text" class="form-control" id="testDesignation" value="Founder & Managing Director" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Business Description / Services</label>
                        <textarea class="form-control" id="testDescription" rows="2" required>Manufacturer of rooftop solar inverters, lithium batteries, and grid-connected renewable power solutions.</textarea>
                    </div>
                    <button type="button" class="btn btn-primary" id="runTestBtn" onclick="executeAiTest()">
                        <i class="bi bi-lightning-charge me-1"></i> Run Classification via Gemini
                    </button>
                </form>
                <div class="mt-4 d-none" id="testResultContainer">
                    <h6 class="fw-bold text-dark">Gemini Output (Matched from 18 Categories):</h6>
                    <pre class="bg-dark text-success p-3 rounded-3 font-monospace small" id="testResultJson" style="max-height: 250px; overflow-y: auto;"></pre>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
async function executeAiTest() {
    const btn = document.getElementById('runTestBtn');
    const container = document.getElementById('testResultContainer');
    const resultBox = document.getElementById('testResultJson');

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
    container.classList.add('d-none');

    try {
        const response = await fetch("{{ route('admin.ai-flows.test') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                company_name: document.getElementById('testCompanyName').value,
                designation: document.getElementById('testDesignation').value,
                description: document.getElementById('testDescription').value,
            })
        });

        const data = await response.json();
        resultBox.textContent = JSON.stringify(data, null, 2);
        container.classList.remove('d-none');
    } catch (e) {
        resultBox.textContent = 'Error: ' + e.message;
        container.classList.remove('d-none');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-lightning-charge me-1"></i> Run Classification via Gemini';
    }
}
</script>
@endsection
