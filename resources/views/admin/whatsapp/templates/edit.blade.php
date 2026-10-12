@extends('admin.layouts.app')

@section('title', 'Edit WhatsApp Template: ' . $template->name . ' - Peers Global Unity')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="bi bi-pencil-square text-warning me-2"></i>Edit WhatsApp Template
            </h1>
            <p class="text-muted small mb-0">Modify template text, fix punctuation/typos, and submit updated version to Meta for re-approval.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.whatsapp.templates.show', $template->id) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Cancel & Back
            </a>
            <a href="{{ route('admin.whatsapp.templates') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list-ul me-1"></i>Templates List
            </a>
        </div>
    </div>

    <!-- Review Information Banner -->
    <div class="alert alert-info border-info-subtle shadow-sm mb-4">
        <div class="d-flex align-items-start">
            <i class="bi bi-info-circle-fill text-info fs-4 me-3 mt-1"></i>
            <div>
                <h6 class="fw-bold mb-1">Meta Template Review & Approval Policy</h6>
                <p class="small mb-2 text-dark">
                    <strong>Approval Time:</strong> Minor typo fixes, punctuation corrections (such as fixing double commas or spacing like <code>Hi,Harsh,</code> to <code>Hi Harsh,</code>), and formatting edits are evaluated by Meta's automated AI reviewer within <strong>1 to 15 minutes</strong> (often under 5 minutes). If flagged for manual review, it can take up to <strong>24 hours</strong>.
                </p>
                <div class="small text-muted">
                    <i class="bi bi-shield-check text-success me-1"></i><strong>Zero Message Downtime:</strong> While the edited template is in review, Meta ensures the previously approved version can still be sent. Once approved, Meta seamlessly switches to delivering your new version.
                </div>
            </div>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Validation errors:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        <!-- Edit Form Column -->
        <div class="col-lg-7">
            <form action="{{ route('admin.whatsapp.templates.update', $template->id) }}" method="POST" id="editTemplateForm" enctype="multipart/form-data" data-filter-form="false">
                @csrf
                @method('PUT')

                <!-- Step 1: Basic Information -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0 fw-bold text-primary">
                            <span class="badge bg-primary me-2">1</span>Template Identity
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-muted small text-uppercase">Template Name (Immutable)</label>
                                <input type="text" class="form-control bg-light font-monospace" value="{{ $template->name }}" readonly disabled>
                                <div class="form-text small">Template names cannot be changed in Meta after creation.</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-muted small text-uppercase">Language</label>
                                <input type="text" class="form-control bg-light font-monospace" value="{{ $template->language }}" readonly disabled>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-muted small text-uppercase">Current Status</label>
                                <div>
                                    <span class="badge bg-{{ $template->status === 'APPROVED' ? 'success' : 'warning' }} fs-6 py-2 px-3">
                                        {{ $template->status }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                            <select name="category" id="categorySelect" class="form-select" required>
                                <option value="MARKETING" {{ old('category', $template->category) === 'MARKETING' ? 'selected' : '' }}>MARKETING (Nurturing, Engagement, Daily Journey)</option>
                                <option value="UTILITY" {{ old('category', $template->category) === 'UTILITY' ? 'selected' : '' }}>UTILITY (Account updates, Order updates)</option>
                                <option value="AUTHENTICATION" {{ old('category', $template->category) === 'AUTHENTICATION' ? 'selected' : '' }}>AUTHENTICATION (OTP codes)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Header (Optional) -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0 fw-bold text-primary">
                            <span class="badge bg-primary me-2">2</span>Header (Optional)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Header Type</label>
                            <select name="header_type" id="headerTypeSelect" class="form-select">
                                <option value="NONE" {{ old('header_type', $headerType) === 'NONE' ? 'selected' : '' }}>None</option>
                                <option value="TEXT" {{ old('header_type', $headerType) === 'TEXT' ? 'selected' : '' }}>Text</option>
                                <option value="IMAGE" {{ old('header_type', $headerType) === 'IMAGE' ? 'selected' : '' }}>Media - Image</option>
                                <option value="VIDEO" {{ old('header_type', $headerType) === 'VIDEO' ? 'selected' : '' }}>Media - Video</option>
                                <option value="DOCUMENT" {{ old('header_type', $headerType) === 'DOCUMENT' ? 'selected' : '' }}>Media - Document</option>
                            </select>
                        </div>

                        <!-- Existing Media Handle / URL to Preserve -->
                        <input type="hidden" name="existing_header_handle" id="existingHeaderHandleInput" value="{{ old('existing_header_handle', $headerMediaUrl) }}">

                        <!-- Text Header -->
                        <div class="mb-3 {{ old('header_type', $headerType) === 'TEXT' ? '' : 'd-none' }}" id="headerTextWrapper">
                            <label class="form-label fw-semibold">Header Text</label>
                            <input type="text" name="header_text" id="headerTextInput" class="form-control" placeholder="e.g. Welcome to Peers Global" maxlength="60" value="{{ old('header_text', $headerText) }}">
                            <div class="form-text small">Maximum 60 characters.</div>
                        </div>

                        <!-- Image Media Header -->
                        <div class="mb-3 {{ old('header_type', $headerType) === 'IMAGE' ? '' : 'd-none' }}" id="headerImageWrapper">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-image text-primary me-1"></i>Sample Header Image
                            </label>
                            <div class="p-3 border rounded bg-light">
                                @if(!empty($headerMediaUrl) && $headerType === 'IMAGE')
                                    <div class="mb-3 p-3 bg-white border rounded shadow-sm d-flex align-items-center gap-3" id="currentApprovedImageCard">
                                        <img src="{{ $headerMediaUrl }}" alt="Current Approved Image" style="width: 64px; height: 64px; object-fit: cover; border-radius: 8px; border: 1px solid #dee2e6;">
                                        <div class="flex-grow-1 overflow-hidden">
                                            <span class="badge bg-success-subtle text-success border border-success-subtle mb-1">
                                                <i class="bi bi-patch-check-fill me-1"></i>Approved Image on Meta
                                            </span>
                                            <div class="small text-muted">
                                                This template already has an approved sample image on Meta. If you don't select a new file below, this image is kept.
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <label class="form-label small fw-semibold text-secondary">
                                    {{ !empty($headerMediaUrl) && $headerType === 'IMAGE' ? 'Replace Image (Optional)' : 'Upload Sample Image *' }}
                                </label>
                                <input type="file" name="header_image_file" id="headerImageInput" class="form-control" accept="image/jpeg,image/png,image/jpg">
                                <div class="form-text mt-2 small text-muted">
                                    <i class="bi bi-info-circle me-1"></i>Meta strictly requires a sample image to review and approve media templates. Supports JPG, JPEG, PNG (Max 5MB).
                                </div>
                                <div id="imagePreviewContainer" class="mt-3 d-none">
                                    <div class="d-flex align-items-center gap-3 p-2 bg-white border rounded shadow-sm">
                                        <img id="imagePreviewThumb" src="" alt="Thumbnail" style="width: 56px; height: 56px; object-fit: cover; border-radius: 6px; border: 1px solid #dee2e6;">
                                        <div class="flex-grow-1 overflow-hidden">
                                            <div class="small fw-bold text-dark text-truncate" id="imageFileName">image.jpg</div>
                                            <div class="small text-muted" id="imageFileSize">0 KB</div>
                                        </div>
                                        <button type="button" class="btn btn-outline-danger btn-sm" id="btnRemoveImage" title="Remove selected image">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Video Media Header -->
                        <div class="mb-3 {{ old('header_type', $headerType) === 'VIDEO' ? '' : 'd-none' }}" id="headerVideoWrapper">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-play-circle text-primary me-1"></i>Sample Header Video
                            </label>
                            <div class="p-3 border rounded bg-light">
                                @if(!empty($headerMediaUrl) && $headerType === 'VIDEO')
                                    <div class="mb-3 p-3 bg-white border rounded shadow-sm d-flex align-items-center gap-3">
                                        <div class="rounded bg-dark text-white d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                                            <i class="bi bi-film fs-3"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <span class="badge bg-success-subtle text-success border border-success-subtle mb-1">
                                                <i class="bi bi-patch-check-fill me-1"></i>Approved Video on Meta
                                            </span>
                                            <div class="small text-muted">Current approved video sample from Meta is retained unless replaced.</div>
                                        </div>
                                    </div>
                                @endif
                                <label class="form-label small fw-semibold text-secondary">
                                    {{ !empty($headerMediaUrl) && $headerType === 'VIDEO' ? 'Replace Video (Optional)' : 'Upload Sample Video *' }}
                                </label>
                                <input type="file" name="header_video_file" id="headerVideoInput" class="form-control" accept="video/mp4,video/3gpp">
                                <div class="form-text mt-2 small text-muted">
                                    <i class="bi bi-info-circle me-1"></i>Meta requires a sample video for review. Supports MP4, 3GP (Max 16MB).
                                </div>
                            </div>
                        </div>

                        <!-- Document Media Header -->
                        <div class="mb-3 {{ old('header_type', $headerType) === 'DOCUMENT' ? '' : 'd-none' }}" id="headerDocumentWrapper">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-file-earmark-pdf text-primary me-1"></i>Sample Header Document
                            </label>
                            <div class="p-3 border rounded bg-light">
                                @if(!empty($headerMediaUrl) && $headerType === 'DOCUMENT')
                                    <div class="mb-3 p-3 bg-white border rounded shadow-sm d-flex align-items-center gap-3">
                                        <div class="rounded bg-danger text-white d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                                            <i class="bi bi-file-pdf fs-3"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <span class="badge bg-success-subtle text-success border border-success-subtle mb-1">
                                                <i class="bi bi-patch-check-fill me-1"></i>Approved Document on Meta
                                            </span>
                                            <div class="small text-muted">Current approved PDF document from Meta is retained unless replaced.</div>
                                        </div>
                                    </div>
                                @endif
                                <label class="form-label small fw-semibold text-secondary">
                                    {{ !empty($headerMediaUrl) && $headerType === 'DOCUMENT' ? 'Replace PDF (Optional)' : 'Upload Sample PDF *' }}
                                </label>
                                <input type="file" name="header_document_file" id="headerDocumentInput" class="form-control" accept="application/pdf">
                                <div class="form-text mt-2 small text-muted">
                                    <i class="bi bi-info-circle me-1"></i>Meta requires a sample PDF document for review (Max 10MB).
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Body Text (Main Message) -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fw-bold text-primary">
                            <span class="badge bg-primary me-2">3</span>Body Message <span class="text-danger">*</span>
                        </h5>
                        <span class="small text-muted" id="charCounter">0 / 1024</span>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-2">
                            Edit the message text below to fix typos, double commas, or punctuation. Use <code>@{{1}}</code> for the First Name placeholder.
                        </p>

                        <div class="mb-3">
                            <textarea name="body_text" id="bodyTextInput" class="form-control font-monospace" rows="8" maxlength="1024" required placeholder="Type your message here...">{{ old('body_text', $bodyText) }}</textarea>
                        </div>

                        <!-- Double Comma Auto-Fix Helper -->
                        <div class="p-3 bg-light rounded border mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <i class="bi bi-magic text-primary me-1"></i>
                                <span class="small fw-semibold">Quick Fix Typos:</span>
                                <span class="small text-muted ms-1">Fix <code>Hi,&#123;&#123;1&#125;&#125;,</code> to clean <code>Hi &#123;&#123;1&#125;&#125;,</code></span>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btnFixDoubleComma">
                                <i class="bi bi-wrench me-1"></i>Auto-clean "Hi,&#123;&#123;1&#125;&#125;," Typos
                            </button>
                        </div>

                        <!-- Variable Sample Values -->
                        <div id="variableContainer" class="p-3 bg-light rounded border">
                            <h6 class="fw-bold mb-2 small text-uppercase text-muted">
                                <i class="bi bi-braces me-1 text-primary"></i>Sample Values for Meta Approval
                            </h6>
                            <div class="small text-muted mb-3">
                                Meta requires an example value for each variable (e.g. <code>@{{1}}</code> = <code>Harsh</code>).
                            </div>
                            <div id="variableInputsList">
                                <!-- Dynamic variable sample inputs -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Footer (Optional) -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0 fw-bold text-primary">
                            <span class="badge bg-primary me-2">4</span>Footer (Optional)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <input type="text" name="footer_text" id="footerTextInput" class="form-control" placeholder="e.g. Peers Global Unity" maxlength="60" value="{{ old('footer_text', $footerText) }}">
                            <div class="form-text small">Short disclaimers or tagline (max 60 characters).</div>
                        </div>
                    </div>
                </div>

                <!-- Step 5: Buttons -->
                @php
                    $initialButtons = old('buttons', $buttons ?? []);
                @endphp
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0 fw-bold text-primary">
                                <span class="badge bg-primary me-2">5</span>Buttons (Call-to-Action / Quick Reply)
                            </h5>
                            <small class="text-muted">Optional: Add up to 3 interactive buttons (Website URL, Phone Call, or Quick Reply)</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" id="btnAddButton">
                            <i class="bi bi-plus-circle me-1"></i>Add Button
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="noButtonsNotice" class="alert alert-light border text-muted small py-3 mb-0 text-center">
                            <i class="bi bi-info-circle me-1 text-primary"></i>No buttons configured. Click <strong>+ Add Button</strong> above to add interactive buttons (up to 3 buttons).
                        </div>
                        <div id="buttonsList" class="d-flex flex-column gap-3"></div>
                    </div>
                </div>

                <!-- Form Submit Action -->
                <div class="d-flex gap-2 mb-5">
                    <button type="submit" class="btn btn-warning btn-lg px-4 fw-bold">
                        <i class="bi bi-cloud-arrow-up-fill me-2"></i>Save & Submit to Meta for Review
                    </button>
                    <a href="{{ route('admin.whatsapp.templates.show', $template->id) }}" class="btn btn-outline-secondary btn-lg">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- Live WhatsApp Chat Preview Column -->
        <div class="col-lg-5">
            <div class="position-sticky" style="top: 20px;">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="bi bi-phone me-2 text-success"></i>Live WhatsApp Preview
                        </h5>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Real-Time</span>
                    </div>
                    <div class="card-body p-3" style="background-color: #ECE5DD; min-height: 480px; border-radius: 0 0 8px 8px;">
                        <!-- WhatsApp Message Bubble -->
                        <div class="d-flex flex-column align-items-end mb-2">
                            <div class="bg-white rounded-3 shadow-sm p-3 position-relative" style="max-width: 90%; border-top-right-radius: 0 !important; color: #111;">
                                <!-- Header Preview -->
                                <div id="previewHeader" class="fw-bold mb-2 pb-1 border-bottom text-dark d-none"></div>

                                <!-- Body Preview -->
                                <div id="previewBody" class="mb-2" style="white-space: pre-wrap; font-size: 0.95rem; line-height: 1.45;"></div>

                                <!-- Footer Preview -->
                                <div id="previewFooter" class="text-muted small mb-2 d-none" style="font-size: 0.75rem;"></div>

                                <!-- Timestamp -->
                                <div class="text-end text-muted small" style="font-size: 0.7rem; margin-top: 4px;">
                                    <span>{{ now()->format('h:i A') }}</span>
                                    <i class="bi bi-check2 text-primary ms-1"></i>
                                </div>

                                <!-- Buttons Preview Container -->
                                <div id="previewButtonsContainer" class="border-top mt-2 pt-0 d-none"></div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white py-2 small text-muted text-center border-0">
                        <i class="bi bi-eye me-1"></i>Shows how the message renders with sample variable values.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bodyTextInput = document.getElementById('bodyTextInput');
    const headerTextInput = document.getElementById('headerTextInput');
    const headerTypeSelect = document.getElementById('headerTypeSelect');
    const headerTextWrapper = document.getElementById('headerTextWrapper');
    const footerTextInput = document.getElementById('footerTextInput');
    const charCounter = document.getElementById('charCounter');

    const previewHeader = document.getElementById('previewHeader');
    const previewBody = document.getElementById('previewBody');
    const previewFooter = document.getElementById('previewFooter');
    const previewButtonsContainer = document.getElementById('previewButtonsContainer');

    const variableContainer = document.getElementById('variableContainer');
    const variableInputsList = document.getElementById('variableInputsList');
    const btnFixDoubleComma = document.getElementById('btnFixDoubleComma');

    const btnAddButton = document.getElementById('btnAddButton');
    const buttonsList = document.getElementById('buttonsList');
    const noButtonsNotice = document.getElementById('noButtonsNotice');

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    const headerImageWrapper = document.getElementById('headerImageWrapper');
    const headerVideoWrapper = document.getElementById('headerVideoWrapper');
    const headerDocumentWrapper = document.getElementById('headerDocumentWrapper');
    const headerImageInput = document.getElementById('headerImageInput');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const imagePreviewThumb = document.getElementById('imagePreviewThumb');
    const imageFileName = document.getElementById('imageFileName');
    const imageFileSize = document.getElementById('imageFileSize');
    const btnRemoveImage = document.getElementById('btnRemoveImage');

    let uploadedImageDataUrl = @json($headerMediaUrl ?? '');

    // Header toggle
    headerTypeSelect.addEventListener('change', function() {
        const val = this.value;
        headerTextWrapper.classList.toggle('d-none', val !== 'TEXT');
        if (headerImageWrapper) headerImageWrapper.classList.toggle('d-none', val !== 'IMAGE');
        if (headerVideoWrapper) headerVideoWrapper.classList.toggle('d-none', val !== 'VIDEO');
        if (headerDocumentWrapper) headerDocumentWrapper.classList.toggle('d-none', val !== 'DOCUMENT');
        updatePreview();
    });

    if (headerImageInput) {
        headerImageInput.addEventListener('change', function(e) {
            const file = e.target.files && e.target.files[0];
            if (!file) return;

            if (file.size > 5 * 1024 * 1024) {
                alert('Image must be under 5MB.');
                this.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function(evt) {
                uploadedImageDataUrl = evt.target.result;
                if (imagePreviewThumb) imagePreviewThumb.src = uploadedImageDataUrl;
                if (imageFileName) imageFileName.textContent = file.name;
                if (imageFileSize) imageFileSize.textContent = (file.size / 1024).toFixed(1) + ' KB';
                if (imagePreviewContainer) imagePreviewContainer.classList.remove('d-none');
                updatePreview();
            };
            reader.readAsDataURL(file);
        });
    }

    if (btnRemoveImage) {
        btnRemoveImage.addEventListener('click', function() {
            if (headerImageInput) headerImageInput.value = '';
            if (imagePreviewContainer) imagePreviewContainer.classList.add('d-none');
            uploadedImageDataUrl = @json($headerMediaUrl ?? '');
            updatePreview();
        });
    }

    const openBrace = String.fromCharCode(123, 123);
    const closeBrace = String.fromCharCode(125, 125);
    const serverSamples = @json($sampleVariables ?? []);

    // Format WhatsApp markdown (*bold*, _italic_, ~strike~, ```code```)
    function renderWhatsAppFormatting(text) {
        const div = document.createElement('div');
        div.textContent = text;
        let escaped = div.innerHTML;

        escaped = escaped.replace(/\*([^\*\n]+)\*/g, '<strong class="fw-bold">$1</strong>');
        escaped = escaped.replace(/_([^_\n]+)_/g, '<em>$1</em>');
        escaped = escaped.replace(/~([^~\n]+)~/g, '<del>$1</del>');
        escaped = escaped.replace(/```([^`]+)```/g, '<code class="bg-light px-1 rounded font-monospace">$1</code>');

        return escaped;
    }

    // Auto-fix double comma helper
    if (btnFixDoubleComma) {
        btnFixDoubleComma.addEventListener('click', function() {
            let val = bodyTextInput.value;
            const cleanToken = 'Hi ' + openBrace + '1' + closeBrace + ',';
            val = val.replace(/HI,\*?\{\{1\}\}\*?,/gi, cleanToken);
            val = val.replace(/Hi,\*?\{\{1\}\}\*?,/gi, cleanToken);
            val = val.replace(/Hi,\*?\{\{1\}\}\.?\*?/gi, cleanToken);
            bodyTextInput.value = val;
            bodyTextInput.dispatchEvent(new Event('input'));
        });
    }

    bodyTextInput.addEventListener('input', function() {
        charCounter.textContent = this.value.length + ' / 1024';
        detectVariables(this.value);
        updatePreview();
    });

    headerTextInput.addEventListener('input', updatePreview);
    footerTextInput.addEventListener('input', updatePreview);

    function detectVariables(text) {
        const matches = text.match(/\{\{(\d+)\}\}/g);
        if (!matches || matches.length === 0) {
            variableContainer.classList.add('d-none');
            variableInputsList.innerHTML = '';
            return;
        }

        const uniqueVars = [...new Set(matches.map(m => m.replace(/[\{\}]/g, '')))].sort((a,b) => a - b);
        variableContainer.classList.remove('d-none');

        const currentVals = {};
        variableInputsList.querySelectorAll('input').forEach(inp => {
            const idx = inp.getAttribute('data-var-index');
            if (idx) currentVals[idx] = inp.value;
        });

        variableInputsList.innerHTML = '';
        uniqueVars.forEach(v => {
            const defaultVal = currentVals[v] || (serverSamples[parseInt(v) - 1] || (v === '1' ? 'Harsh' : ('Sample ' + v)));
            const row = document.createElement('div');
            row.className = 'mb-2 row align-items-center';
            row.innerHTML = 
                '<label class="col-sm-3 col-form-label small fw-bold font-monospace">' + openBrace + v + closeBrace + '</label>' +
                '<div class="col-sm-9">' +
                    '<input type="text" name="body_sample_' + v + '" data-var-index="' + v + '" class="form-control form-control-sm var-sample-input" value="' + defaultVal + '" placeholder="e.g. Harsh">' +
                '</div>';
            variableInputsList.appendChild(row);
        });

        variableInputsList.querySelectorAll('.var-sample-input').forEach(inp => {
            inp.addEventListener('input', updatePreview);
        });
    }

    // Dynamic Multi-Button Management
    function renumberButtons() {
        const rows = buttonsList ? buttonsList.querySelectorAll('.button-row') : [];
        rows.forEach((row, index) => {
            row.setAttribute('data-index', index);
            const label = row.querySelector('.button-row-num');
            if (label) {
                label.innerHTML = '<i class="bi bi-cursor-fill text-primary me-1"></i>Button #' + (index + 1);
            }
            const select = row.querySelector('.btn-type-select');
            if (select) select.name = 'buttons[' + index + '][type]';

            const textInput = row.querySelector('.btn-text-input');
            if (textInput) textInput.name = 'buttons[' + index + '][text]';

            const urlInput = row.querySelector('.btn-url-input');
            if (urlInput) urlInput.name = 'buttons[' + index + '][url]';

            const phoneInput = row.querySelector('.btn-phone-input');
            if (phoneInput) phoneInput.name = 'buttons[' + index + '][phone_number]';
        });

        if (noButtonsNotice) {
            if (rows.length === 0) {
                noButtonsNotice.classList.remove('d-none');
            } else {
                noButtonsNotice.classList.add('d-none');
            }
        }

        if (btnAddButton) {
            if (rows.length >= 3) {
                btnAddButton.disabled = true;
                btnAddButton.classList.add('disabled');
                btnAddButton.innerHTML = '<i class="bi bi-slash-circle me-1"></i>Max 3 Buttons';
            } else {
                btnAddButton.disabled = false;
                btnAddButton.classList.remove('disabled');
                btnAddButton.innerHTML = '<i class="bi bi-plus-circle me-1"></i>Add Button';
            }
        }

        updatePreview();
    }

    function addButtonRow(data = {}) {
        if (!buttonsList) return;
        const currentCount = buttonsList.querySelectorAll('.button-row').length;
        if (currentCount >= 3) {
            return;
        }

        const idx = currentCount;
        const type = (data.type || 'URL').toUpperCase();
        const text = data.text || '';
        const url = data.url || '';
        const phone = data.phone_number || '';

        const card = document.createElement('div');
        card.className = 'border rounded p-3 bg-white position-relative button-row shadow-sm';
        card.setAttribute('data-index', idx);

        card.innerHTML = 
            '<div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">' +
                '<span class="fw-bold small text-dark button-row-num">' +
                    '<i class="bi bi-cursor-fill text-primary me-1"></i>Button #' + (idx + 1) +
                '</span>' +
                '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2 btn-remove-button" title="Remove this button">' +
                    '<i class="bi bi-trash3-fill me-1"></i>Delete' +
                '</button>' +
            '</div>' +
            '<div class="row g-3">' +
                '<div class="col-md-5">' +
                    '<label class="form-label small fw-semibold mb-1 text-secondary">' +
                        '<i class="bi bi-lightning-charge-fill text-warning me-1"></i>Action Type' +
                    '</label>' +
                    '<select name="buttons[' + idx + '][type]" class="form-select btn-type-select js-no-select2 js-no-searchable-select" style="height: 38px; font-size: 13px; font-weight: 500;">' +
                        '<option value="URL"' + (type === 'URL' ? ' selected' : '') + '>🌐 Website URL</option>' +
                        '<option value="PHONE_NUMBER"' + (type === 'PHONE_NUMBER' ? ' selected' : '') + '>📞 Phone Call</option>' +
                        '<option value="QUICK_REPLY"' + (type === 'QUICK_REPLY' ? ' selected' : '') + '>💬 Quick Reply</option>' +
                    '</select>' +
                '</div>' +
                '<div class="col-md-7">' +
                    '<label class="form-label small fw-semibold mb-1 text-secondary">' +
                        '<i class="bi bi-fonts text-primary me-1"></i>Button Text <span class="text-danger">*</span>' +
                        '<span class="text-muted fw-normal small"> (Max 25)</span>' +
                    '</label>' +
                    '<input type="text" name="buttons[' + idx + '][text]" class="form-control btn-text-input" placeholder="e.g. Visit Website / RSVP" maxlength="25" value="' + escapeHtml(text) + '" style="height: 38px; font-size: 13px;" required>' +
                '</div>' +
                '<div class="col-12 target-url-group' + (type === 'URL' ? '' : ' d-none') + '">' +
                    '<label class="form-label small fw-semibold mb-1 text-secondary">' +
                        '<i class="bi bi-link-45deg text-primary me-1"></i>Website URL <span class="text-danger">*</span>' +
                    '</label>' +
                    '<input type="url" name="buttons[' + idx + '][url]" class="form-control font-monospace btn-url-input" placeholder="https://example.com/page" value="' + escapeHtml(url) + '" style="height: 38px; font-size: 13px;">' +
                '</div>' +
                '<div class="col-12 target-phone-group' + (type === 'PHONE_NUMBER' ? '' : ' d-none') + '">' +
                    '<label class="form-label small fw-semibold mb-1 text-secondary">' +
                        '<i class="bi bi-telephone-fill text-primary me-1"></i>Phone Number with Country Code <span class="text-danger">*</span>' +
                    '</label>' +
                    '<input type="text" name="buttons[' + idx + '][phone_number]" class="form-control font-monospace btn-phone-input" placeholder="+919876543210" value="' + escapeHtml(phone) + '" style="height: 38px; font-size: 13px;">' +
                '</div>' +
                '<div class="col-12 target-qr-group' + (type === 'QUICK_REPLY' ? '' : ' d-none') + '">' +
                    '<div class="alert alert-info py-2 px-3 mb-0 small border-0 bg-info-subtle text-info-emphasis d-flex align-items-center">' +
                        '<i class="bi bi-info-circle-fill me-2 fs-6"></i>' +
                        '<span><strong>Quick Reply:</strong> When tapped on WhatsApp, this button sends the button text back as an instant response to your webhook.</span>' +
                    '</div>' +
                '</div>' +
            '</div>';

        buttonsList.appendChild(card);

        // Bind events
        const typeSelect = card.querySelector('.btn-type-select');
        const textInp = card.querySelector('.btn-text-input');
        const urlInp = card.querySelector('.btn-url-input');
        const phoneInp = card.querySelector('.btn-phone-input');
        const removeBtn = card.querySelector('.btn-remove-button');
        const urlGrp = card.querySelector('.target-url-group');
        const phoneGrp = card.querySelector('.target-phone-group');
        const qrGrp = card.querySelector('.target-qr-group');

        typeSelect.addEventListener('change', function() {
            const val = this.value;
            urlGrp.classList.toggle('d-none', val !== 'URL');
            phoneGrp.classList.toggle('d-none', val !== 'PHONE_NUMBER');
            qrGrp.classList.toggle('d-none', val !== 'QUICK_REPLY');
            updatePreview();
        });

        textInp.addEventListener('input', updatePreview);
        urlInp.addEventListener('input', updatePreview);
        phoneInp.addEventListener('input', updatePreview);

        removeBtn.addEventListener('click', function() {
            card.remove();
            renumberButtons();
        });

        renumberButtons();
    }

    if (btnAddButton) {
        btnAddButton.addEventListener('click', function() {
            addButtonRow({ type: 'URL', text: '', url: '' });
            const lastRow = buttonsList.querySelector('.button-row:last-child');
            if (lastRow) {
                const textInput = lastRow.querySelector('.btn-text-input');
                if (textInput) textInput.focus();
            }
        });
    }

    // Preload buttons from server
    const initialButtons = @json($initialButtons ?? []);
    if (Array.isArray(initialButtons) && initialButtons.length > 0) {
        initialButtons.forEach(btn => addButtonRow(btn));
    } else {
        renumberButtons();
    }

    function updatePreview() {
        // Header
        const headerType = headerTypeSelect.value;
        if (headerType === 'TEXT' && headerTextInput.value.trim() !== '') {
            previewHeader.textContent = headerTextInput.value.trim();
            previewHeader.className = 'fw-bold mb-2 pb-1 border-bottom text-dark';
            previewHeader.classList.remove('d-none');
        } else if (headerType === 'IMAGE') {
            if (uploadedImageDataUrl) {
                previewHeader.innerHTML = '<img src="' + uploadedImageDataUrl + '" alt="Header Image" style="width: 100%; max-height: 220px; object-fit: cover; border-radius: 6px; margin-bottom: 8px; display: block;" />';
            } else {
                previewHeader.innerHTML = '<div class="p-3 text-center bg-light border rounded text-muted mb-2 small"><i class="bi bi-image fs-4 d-block mb-1"></i>[IMAGE Header Sample]</div>';
            }
            previewHeader.className = 'mb-2';
            previewHeader.classList.remove('d-none');
        } else if (headerType === 'VIDEO') {
            previewHeader.innerHTML = '<div class="p-3 text-center bg-dark text-white rounded mb-2 small"><i class="bi bi-play-btn-fill fs-4 d-block mb-1"></i>[VIDEO Header]</div>';
            previewHeader.className = 'mb-2';
            previewHeader.classList.remove('d-none');
        } else if (headerType === 'DOCUMENT') {
            previewHeader.innerHTML = '<div class="p-3 text-center bg-light border rounded text-muted mb-2 small"><i class="bi bi-file-earmark-pdf-fill fs-4 text-danger d-block mb-1"></i>[DOCUMENT PDF Header]</div>';
            previewHeader.className = 'mb-2';
            previewHeader.classList.remove('d-none');
        } else {
            previewHeader.classList.add('d-none');
            previewHeader.innerHTML = '';
        }

        // Body with variable replacement and WhatsApp formatting
        let bodyRender = bodyTextInput.value || 'Type your message...';
        variableInputsList.querySelectorAll('.var-sample-input').forEach(inp => {
            const idx = inp.getAttribute('data-var-index');
            const val = inp.value.trim() || ('[' + openBrace + idx + closeBrace + ']');
            const token = openBrace + idx + closeBrace;
            bodyRender = bodyRender.replaceAll(token, val);
        });
        previewBody.innerHTML = renderWhatsAppFormatting(bodyRender);

        // Footer
        if (footerTextInput.value.trim() !== '') {
            previewFooter.textContent = footerTextInput.value.trim();
            previewFooter.classList.remove('d-none');
        } else {
            previewFooter.classList.add('d-none');
        }

        // Multi-Buttons Preview
        if (previewButtonsContainer) {
            previewButtonsContainer.innerHTML = '';
            const rows = buttonsList ? buttonsList.querySelectorAll('.button-row') : [];
            let hasAnyButton = false;

            rows.forEach(row => {
                const type = row.querySelector('.btn-type-select').value;
                const text = row.querySelector('.btn-text-input').value.trim();
                if (text !== '') {
                    hasAnyButton = true;
                    const item = document.createElement('div');
                    item.className = 'text-center py-2 border-top fw-semibold small d-flex align-items-center justify-content-center';
                    item.style.cssText = 'color: #00a884; font-size: 0.85rem;';
                    let iconHtml = '<i class="bi bi-box-arrow-up-right me-1"></i>';
                    if (type === 'PHONE_NUMBER') {
                        iconHtml = '<i class="bi bi-telephone-fill me-1"></i>';
                    } else if (type === 'QUICK_REPLY') {
                        iconHtml = '<i class="bi bi-reply-fill me-1"></i>';
                    }
                    item.innerHTML = iconHtml + '<span>' + escapeHtml(text) + '</span>';
                    previewButtonsContainer.appendChild(item);
                }
            });

            if (hasAnyButton) {
                previewButtonsContainer.classList.remove('d-none');
            } else {
                previewButtonsContainer.classList.add('d-none');
            }
        }
    }

    // Initialize on load
    detectVariables(bodyTextInput.value);
    updatePreview();
});
</script>
@endsection
