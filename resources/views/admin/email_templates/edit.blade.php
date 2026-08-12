@extends('admin.layouts.app')

@section('title', 'Edit Template - ' . $template['name'])

@section('content')
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="{{ route('admin.email-templates.index') }}" class="text-decoration-none text-secondary small fw-semibold">
                    <i class="bi bi-arrow-left"></i> Back to Catalog
                </a>
            </div>
            <h4 class="mb-1 fw-bold text-dark">Edit: {{ $template['name'] }}</h4>
            <p class="text-muted small mb-0">Modify email body content, styling, or raw HTML code.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" onclick="refreshPreview()" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                <i class="bi bi-arrow-clockwise"></i> Refresh Preview
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <!-- Editor Panel (Left) -->
        <div class="col-lg-7 mb-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-3">
                    <ul class="nav nav-pills card-header-pills" id="editorTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active d-flex align-items-center gap-2" id="simple-tab" data-bs-toggle="tab" data-bs-target="#simple-pane" type="button" role="tab">
                                <i class="bi bi-card-text"></i> Simple Content Editor
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link d-flex align-items-center gap-2" id="html-tab" data-bs-toggle="tab" data-bs-target="#html-pane" type="button" role="tab">
                                <i class="bi bi-code-slash"></i> HTML Code Editor
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="card-body p-4">
                    <div class="tab-content" id="editorTabsContent">
                        <!-- Tab 1: Simple Content Editor -->
                        <div class="tab-pane fade show active" id="simple-pane" role="tabpanel">
                            <form action="{{ route('admin.email-templates.update', $template['key']) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="mode" value="simple">

                                <div class="mb-3">
                                    <label for="subject_simple" class="form-label fw-bold text-dark small">Email Subject</label>
                                    <input type="text" name="subject" id="subject_simple" class="form-control rounded-3" value="{{ old('subject', $dbTemplate->subject ?? '') }}" placeholder="Enter custom subject line...">
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-bold text-dark small">Body Text (Static Content)</label>
                                    <div class="alert alert-info py-2 px-3 mb-3 rounded-3" style="font-size: 12.5px;">
                                        <i class="bi bi-info-circle me-1"></i> You can edit each text section of this email layout below.
                                    </div>
                                    @foreach($editableBlocks as $index => $block)
                                        <div class="mb-3">
                                            <span class="badge bg-primary rounded-pill mb-2" style="background-color: #240e5c !important;">Section {{ $index + 1 }}</span>
                                            <textarea name="simple_content[]" class="form-control rounded-3 font-monospace" rows="6" style="font-size: 14px; line-height: 1.6;">{{ old('simple_content.' . $index, $block) }}</textarea>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary px-4 py-2 rounded-3" style="background-color: #240e5c; border-color: #240e5c;">
                                        <i class="bi bi-save me-1"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Tab 2: HTML Code Editor -->
                        <div class="tab-pane fade" id="html-pane" role="tabpanel">
                            <form action="{{ route('admin.email-templates.update', $template['key']) }}" method="POST">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="mode" value="html">

                                <div class="mb-3">
                                    <label for="subject_html" class="form-label fw-bold text-dark small">Email Subject</label>
                                    <input type="text" name="subject" id="subject_html" class="form-control rounded-3" value="{{ old('subject', $dbTemplate->subject ?? '') }}" placeholder="Enter custom subject line...">
                                </div>

                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label for="html_content" class="form-label fw-bold text-dark small mb-0">Raw HTML / Blade Code</label>
                                        <span class="badge bg-danger rounded-pill px-2 py-1" style="font-size: 10px;">Advanced Mode</span>
                                    </div>
                                    <textarea name="html_content" id="html_content" class="form-control rounded-3 font-monospace bg-white text-dark p-3 border" rows="18" style="font-size: 13px; line-height: 1.5; tab-size: 4; border-color: #dee2e6 !important;">{{ old('html_content', $fullHtml) }}</textarea>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary px-4 py-2 rounded-3" style="background-color: #240e5c; border-color: #240e5c;">
                                        <i class="bi bi-save me-1"></i> Save HTML Structure
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Preview & Variable Help (Right) -->
        <div class="col-lg-5 mb-4">
            <!-- Parameters Help -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom p-3">
                    <h5 class="card-title fw-bold text-dark mb-0" style="font-size: 15px;">Dynamic Parameter Reference</h5>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-warning py-2 px-3 rounded-3 mb-3" style="font-size: 12.5px;">
                        <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                        <strong>Important:</strong> Please do not modify or delete these dynamic parameters (highlighted in red) as they load information automatically from the database.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless align-middle mb-0" style="font-size: 13px;">
                            <thead>
                                <tr class="text-secondary" style="font-size: 11px; text-transform: uppercase;">
                                    <th>Variable Tag</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($template['dynamic_params'] as $param => $desc)
                                    <tr>
                                        <td>
                                            <span class="badge bg-danger-subtle text-danger font-monospace border border-danger-subtle" style="font-size: 12px; cursor: pointer;" onclick="copyToClipboard('{{ $param }}')" title="Click to copy">
                                                {{ $param }}
                                            </span>
                                        </td>
                                        <td class="text-muted">{{ $desc }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Live Preview Frame -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title fw-bold text-dark mb-0" style="font-size: 15px;">Live Visual Preview</h5>
                    <span class="badge bg-success rounded-pill px-2 py-1" style="font-size: 10px;">Rendered Mock</span>
                </div>
                <div class="card-body p-0 bg-light">
                    <!-- Live preview frame container -->
                    <div class="ratio ratio-1x1" style="min-height: 480px;">
                        <iframe id="preview-iframe" src="{{ route('admin.email-templates.preview', $template['key']) }}" style="border: 0; background-color: #ffffff; width: 100%; height: 100%;"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function refreshPreview() {
        const iframe = document.getElementById('preview-iframe');
        iframe.src = iframe.src;
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Copied to clipboard: ' + text);
        });
    }
</script>
@endsection
