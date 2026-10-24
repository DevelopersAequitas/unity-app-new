@extends('admin.layouts.app')

@section('title', 'Edit AI Prompt Flow - ' . $flow->name)

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('admin.ai-flows.index') }}">AI Prompt Flows</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Edit {{ $flow->name }}</li>
                </ol>
            </nav>
            <h2 class="h4 mb-0 fw-bold text-dark"><i class="bi bi-sliders me-2 text-primary"></i>Edit AI Prompt Flow</h2>
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
                    <h5 class="card-title fw-bold mb-0">Prompt Configuration</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.ai-flows.update', $flow->id ?? $flow->slug) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row g-3 mb-3">
                            <div class="col-md-7">
                                <label class="form-label fw-semibold">Flow Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $flow->name) }}" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label fw-semibold">Flow Slug</label>
                                <input type="text" name="slug" class="form-control font-monospace" value="{{ old('slug', $flow->slug) }}" {{ ($flow->slug === 'business_category_suggestion') ? 'readonly' : 'required' }}>
                                <div class="form-text">Endpoint: <code>/api/v1/ai/execute/{{ $flow->slug }}</code></div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description', $flow->description) }}</textarea>
                            <div class="form-text">Internal note describing where this AI prompt is used.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">System Instruction</label>
                            <textarea name="system_instruction" class="form-control font-monospace small" rows="3">{{ old('system_instruction', $flow->system_instruction) }}</textarea>
                            <div class="form-text">Direct role definition given to Google Gemini.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Prompt Template with Variables</label>
                            <textarea name="prompt_template" class="form-control font-monospace small" rows="14" required>{{ old('prompt_template', $flow->prompt_template) }}</textarea>
                            <div class="form-text">Use <code>@{{variable_name}}</code> tags. These are replaced dynamically at runtime.</div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Google Gemini Model</label>
                                <select name="model" class="form-select font-monospace">
                                    <option value="gemini-2.5-flash" {{ old('model', $flow->model) === 'gemini-2.5-flash' || old('model', $flow->model) === 'gemini-1.5-flash' ? 'selected' : '' }}>gemini-2.5-flash (Latest, Ultra Fast & Recommended)</option>
                                    <option value="gemini-2.0-flash" {{ old('model', $flow->model) === 'gemini-2.0-flash' ? 'selected' : '' }}>gemini-2.0-flash (Fast Multimodal)</option>
                                    <option value="gemini-2.5-pro" {{ old('model', $flow->model) === 'gemini-2.5-pro' || old('model', $flow->model) === 'gemini-1.5-pro' ? 'selected' : '' }}>gemini-2.5-pro (Advanced Reasoning)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Temperature (0.0 to 1.0)</label>
                                <input type="number" step="0.05" min="0" max="1" name="temperature" class="form-control" value="{{ old('temperature', $flow->temperature ?? 0.20) }}" required>
                                <div class="form-text">Lower temperature (0.1 - 0.3) produces more factual, strictly classified results.</div>
                            </div>
                        </div>

                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" role="switch" id="isActiveSwitch" name="is_active" value="1" {{ old('is_active', $flow->is_active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label fw-semibold" for="isActiveSwitch">Flow is Active & Live in App</label>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.ai-flows.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-save me-1"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-code-square me-2 text-primary"></i>Available Prompt Variables</h6>
                </div>
                <div class="card-body">
                    <p class="text-muted small">You can insert any of the following variables into the prompt template. They will be dynamically replaced when the user requests a suggestion:</p>
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <code>@{{company_name}}</code>
                            <span class="text-muted">User's Company Name</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <code>@{{designation}}</code>
                            <span class="text-muted">Job Title / Role</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <code>@{{description}}</code>
                            <span class="text-muted">Business Bio / Services</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <code>@{{industry}}</code>
                            <span class="text-muted">LinkedIn Headline</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <code>@{{categories_list}}</code>
                            <span class="text-muted">Active 18 Main Categories</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-3 bg-light">
                <div class="card-body">
                    <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-info-circle me-2 text-info"></i>Instant Global Update</h6>
                    <p class="text-muted small mb-0">
                        When you update the prompt here, the mobile app (Flutter/iOS/Android) and web portals automatically reflect the changes immediately without requiring any app store update or deployment.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
