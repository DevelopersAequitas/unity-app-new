@extends('admin.layouts.app')

@section('title', 'Create New AI Prompt Flow')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('admin.ai-flows.index') }}">AI Prompt Flows</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Create New Flow</li>
                </ol>
            </nav>
            <h2 class="h4 mb-0 fw-bold text-dark"><i class="bi bi-plus-circle me-2 text-primary"></i>Create New AI Prompt Flow</h2>
        </div>
        <div>
            <a href="{{ route('admin.ai-flows.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Flows
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h6 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle me-2"></i>Please correct the following errors:</h6>
            <ul class="mb-0 small">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="card-title fw-bold mb-0">New AI Flow Definition</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.ai-flows.store') }}" method="POST">
                        @csrf

                        <div class="row g-3 mb-3">
                            <div class="col-md-7">
                                <label class="form-label fw-semibold">Flow Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="flowNameInput" class="form-control" placeholder="e.g. Ask Summary Generator, Peer Pitch Evaluator" value="{{ old('name') }}" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">Unique Flow Slug <span class="text-danger">*</span></label>
                                <input type="text" name="slug" id="flowSlugInput" class="form-control font-monospace" placeholder="e.g. ask_summary_generator" value="{{ old('slug') }}" required>
                                <div class="form-text">Used in API endpoint: <code>/api/v1/ai/execute/{slug}</code></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Brief note on what this prompt does and where it is used...">{{ old('description') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">System Instruction (AI Persona / Role)</label>
                            <textarea name="system_instruction" class="form-control font-monospace small" rows="3" placeholder="You are an intelligent AI for Peers Global Unity network. Always return strictly valid JSON.">{{ old('system_instruction', 'You are an intelligent AI for Peers Global Unity network. Return strictly valid JSON matching the specified structure.') }}</textarea>
                            <div class="form-text">Direct system persona given to Google Gemini.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Prompt Template with Variables <span class="text-danger">*</span></label>
                            <textarea name="prompt_template" class="form-control font-monospace small" rows="12" placeholder="Analyze the following data:
- User Message: @{{user_message}}
- Details: @{{details}}

Instructions:
1. Provide a concise summary.
2. Return strictly in JSON structure:
{
  &quot;summary&quot;: &quot;...&quot;
}" required>{{ old('prompt_template') }}</textarea>
                            <div class="form-text">Use <code>@{{variable_name}}</code> tags. When calling the API, pass an object with matching variable keys and they will be dynamically interpolated into the prompt.</div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Google Gemini Model</label>
                                <select name="model" class="form-select font-monospace">
                                    <option value="gemini-2.5-flash" {{ old('model') === 'gemini-2.5-flash' || !old('model') ? 'selected' : '' }}>gemini-2.5-flash (Latest, Ultra Fast & Recommended)</option>
                                    <option value="gemini-2.0-flash" {{ old('model') === 'gemini-2.0-flash' ? 'selected' : '' }}>gemini-2.0-flash (Fast Multimodal)</option>
                                    <option value="gemini-2.5-pro" {{ old('model') === 'gemini-2.5-pro' ? 'selected' : '' }}>gemini-2.5-pro (Advanced Reasoning)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Temperature (0.0 to 1.0)</label>
                                <input type="number" step="0.05" min="0" max="1" name="temperature" class="form-control" value="{{ old('temperature', '0.20') }}" required>
                                <div class="form-text">0.1 - 0.3 for factual/structured classification, 0.7 for creative writing.</div>
                            </div>
                        </div>

                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" role="switch" id="isActiveSwitch" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="isActiveSwitch">Flow is Active & Live in App/API</label>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.ai-flows.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-plus-lg me-1"></i> Create Flow
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-lightning-charge me-2 text-primary"></i>Global Execution Endpoint</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Any new flow you create here is immediately callable anywhere in the backend and frontend without writing extra controllers or endpoints!</p>

                    <div class="bg-light p-2.5 rounded-3 mb-3 border">
                        <div class="text-muted small fw-semibold mb-1">API Endpoint:</div>
                        <code class="text-primary small fw-bold">POST /api/v1/ai/execute/{slug}</code>
                    </div>

                    <div class="bg-light p-2.5 rounded-3 border font-monospace small">
                        <div class="text-muted small fw-sans-serif fw-semibold mb-1">Payload Example:</div>
                        <pre class="mb-0 text-dark" style="font-size: 0.75rem;">{
  "variable_one": "value",
  "variable_two": "value"
}</pre>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-3 bg-light">
                <div class="card-body">
                    <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-info-circle me-2 text-info"></i>Dynamic Template Replacement</h6>
                    <p class="text-muted small mb-0">
                        In your prompt, wrap placeholders like <code>@{{variable_name}}</code>. Whatever keys you send in the request payload or service call will seamlessly substitute those tags before sending to Google Gemini.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const nameInput = document.getElementById('flowNameInput');
    const slugInput = document.getElementById('flowSlugInput');
    let slugManual = false;

    slugInput.addEventListener('input', function() {
        slugManual = true;
    });

    nameInput.addEventListener('input', function() {
        if (!slugManual) {
            slugInput.value = nameInput.value
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '');
        }
    });
});
</script>
@endpush
@endsection
