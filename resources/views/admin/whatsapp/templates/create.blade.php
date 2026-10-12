@extends('admin.layouts.app')

@section('title', 'Create Meta WhatsApp Template - Peers Global Unity')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="bi bi-plus-circle text-success me-2"></i>Create WhatsApp Template
            </h1>
            <p class="text-muted small mb-0">Create and submit a native Meta WhatsApp message template for review.</p>
        </div>
        <a href="{{ route('admin.whatsapp.templates') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Templates
        </a>
    </div>

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
        <!-- Template Builder Form -->
        <div class="col-lg-7">
            <form action="{{ route('admin.whatsapp.templates.store') }}" method="POST" id="templateForm" enctype="multipart/form-data">
                @csrf

                <!-- Step 1: Basic Information -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0 fw-bold text-primary">
                            <span class="badge bg-primary me-2">1</span>Basic Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Template Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="templateNameInput" class="form-control" placeholder="e.g. membership_renewal_alert" value="{{ old('name') }}" required pattern="^[a-z0-9_]+$">
                            <div class="form-text">Only lowercase letters, numbers, and underscores are allowed by Meta.</div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                <select name="category" id="categorySelect" class="form-select" required>
                                    <option value="UTILITY" {{ old('category', 'UTILITY') === 'UTILITY' ? 'selected' : '' }}>UTILITY (Transaction, Account updates, Confirmations)</option>
                                    <option value="MARKETING" {{ old('category') === 'MARKETING' ? 'selected' : '' }}>MARKETING (Promotions, Events, News)</option>
                                    <option value="AUTHENTICATION" {{ old('category') === 'AUTHENTICATION' ? 'selected' : '' }}>AUTHENTICATION (OTP codes, verification)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Language <span class="text-danger">*</span></label>
                                <select name="language" id="languageSelect" class="form-select" required>
                                    <option value="en_US" {{ old('language', 'en_US') === 'en_US' ? 'selected' : '' }}>English (US) [en_US]</option>
                                    <option value="en_IN" {{ old('language') === 'en_IN' ? 'selected' : '' }}>English (India) [en_IN]</option>
                                    <option value="hi" {{ old('language') === 'hi' ? 'selected' : '' }}>Hindi [hi]</option>
                                </select>
                            </div>
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
                                <option value="NONE" {{ old('header_type', 'NONE') === 'NONE' ? 'selected' : '' }}>None</option>
                                <option value="TEXT" {{ old('header_type') === 'TEXT' ? 'selected' : '' }}>Text</option>
                                <option value="IMAGE" {{ old('header_type') === 'IMAGE' ? 'selected' : '' }}>Media - Image</option>
                                <option value="VIDEO" {{ old('header_type') === 'VIDEO' ? 'selected' : '' }}>Media - Video</option>
                                <option value="DOCUMENT" {{ old('header_type') === 'DOCUMENT' ? 'selected' : '' }}>Media - Document</option>
                            </select>
                        </div>

                        <!-- Text Header -->
                        <div class="mb-3 d-none" id="headerTextWrapper">
                            <label class="form-label fw-semibold">Header Text</label>
                            <input type="text" name="header_text" id="headerTextInput" class="form-control" placeholder="e.g. Welcome to Peers Global" maxlength="60" value="{{ old('header_text') }}">
                            <div class="form-text">Maximum 60 characters.</div>
                        </div>

                        <!-- Image Media Header -->
                        <div class="mb-3 d-none" id="headerImageWrapper">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-image text-primary me-1"></i>Sample Header Image <span class="text-danger">*</span>
                            </label>
                            <div class="p-3 border rounded bg-light">
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
                                        <button type="button" class="btn btn-outline-danger btn-sm" id="btnRemoveImage" title="Remove image">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Video Media Header -->
                        <div class="mb-3 d-none" id="headerVideoWrapper">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-play-circle text-primary me-1"></i>Sample Header Video <span class="text-danger">*</span>
                            </label>
                            <div class="p-3 border rounded bg-light">
                                <input type="file" name="header_video_file" id="headerVideoInput" class="form-control" accept="video/mp4,video/3gpp">
                                <div class="form-text mt-2 small text-muted">
                                    <i class="bi bi-info-circle me-1"></i>Meta requires a sample video for template review. Supports MP4, 3GP (Max 16MB).
                                </div>
                            </div>
                        </div>

                        <!-- Document Media Header -->
                        <div class="mb-3 d-none" id="headerDocumentWrapper">
                            <label class="form-label fw-semibold">
                                <i class="bi bi-file-earmark-pdf text-primary me-1"></i>Sample Header Document <span class="text-danger">*</span>
                            </label>
                            <div class="p-3 border rounded bg-light">
                                <input type="file" name="header_document_file" id="headerDocumentInput" class="form-control" accept="application/pdf">
                                <div class="form-text mt-2 small text-muted">
                                    <i class="bi bi-info-circle me-1"></i>Meta requires a sample PDF document for template review (Max 10MB).
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Body & Variables -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0 fw-bold text-primary">
                            <span class="badge bg-primary me-2">3</span>Message Body <span class="text-danger">*</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Message Content</label>
                            <textarea name="body_text" id="bodyTextInput" class="form-control" rows="5" placeholder="Hello {{1}},\n\nYour Peers Unity membership renewal is confirmed on {{2}}." required maxlength="1024">{{ old('body_text') }}</textarea>
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <div class="form-text small">Use <code>@{{1}}</code>, <code>@{{2}}</code> for variables. Meta formatting: *bold*, _italic_, ~strike~, `code`.</div>
                                <span class="small text-muted" id="charCounter">0 / 1024</span>
                            </div>

                            <!-- Single Brace Warning -->
                            <div id="singleBraceWarning" class="alert alert-warning py-2 px-3 mt-2 d-none small shadow-sm">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div>
                                        <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>
                                        <strong>Syntax Error:</strong> You entered single curly braces <code class="text-danger fw-bold" id="singleBraceFound">{1}</code>. Meta will <strong>reject</strong> this template! WhatsApp variables require double braces like <code>@{{1}}</code>.
                                    </div>
                                    <button type="button" class="btn btn-warning btn-sm py-1 fw-bold" id="btnFixSingleBraces">
                                        <i class="bi bi-magic me-1"></i>Auto-Fix to @{{1}}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic Variable Example Inputs -->
                        <div id="variableExamplesContainer" class="p-3 bg-light rounded border mb-2 d-none">
                            <div class="fw-bold small text-muted text-uppercase mb-2">
                                <i class="bi bi-info-circle me-1"></i>Meta Variable Examples (Required by Meta for Review)
                            </div>
                            <div id="variableInputsList"></div>
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
                        <div class="mb-0">
                            <label class="form-label fw-semibold">Footer Text</label>
                            <input type="text" name="footer_text" id="footerTextInput" class="form-control" placeholder="e.g. Peers Global Business Media Pvt Ltd" maxlength="60" value="{{ old('footer_text') }}">
                            <div class="form-text">Small subtle text at the bottom. Max 60 characters.</div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="d-grid mb-5">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-cloud-upload me-2"></i>Submit Template to Meta for Review
                    </button>
                </div>
            </form>
        </div>

        <!-- Live Authentic WhatsApp Message Preview -->
        <div class="col-lg-5">
            <div class="sticky-top" style="top: 20px;">
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                        <h6 class="card-title mb-0 fw-bold text-dark">
                            <i class="bi bi-eye text-primary me-2"></i>Template Preview
                        </h6>
                        <span class="badge bg-success-subtle text-success">
                            <i class="bi bi-whatsapp me-1"></i>Live WhatsApp Render
                        </span>
                    </div>
                </div>

                <div class="wa-preview-wrapper shadow">
                    <!-- WhatsApp Mobile Top Bar -->
                    <div class="wa-chat-header">
                        <i class="bi bi-arrow-left fs-5"></i>
                        <div class="wa-chat-avatar">
                            PG
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-bold small text-truncate mb-0">Peers Global Unity</div>
                            <div class="text-white-50 small d-flex align-items-center" style="font-size: 10px;">
                                <i class="bi bi-patch-check-fill text-info me-1"></i>Official Business Account
                            </div>
                        </div>
                        <div class="d-flex gap-3 text-white-50">
                            <i class="bi bi-camera-video-fill"></i>
                            <i class="bi bi-telephone-fill"></i>
                            <i class="bi bi-three-dots-vertical"></i>
                        </div>
                    </div>

                    <!-- WhatsApp Chat Area -->
                    <div class="wa-chat-body">
                        <div class="wa-date-pill">TODAY</div>
                        <div class="text-center mb-3">
                            <span class="badge bg-warning-subtle text-dark border border-warning-subtle py-1 px-2" style="font-size: 10px; font-weight: normal; border-radius: 6px;">
                                <i class="bi bi-lock-fill me-1"></i>Messages and calls are end-to-end encrypted.
                            </span>
                        </div>

                        <!-- Chat Bubble with Notch Tail -->
                        <div class="wa-bubble-container">
                            <div class="wa-bubble-tail"></div>
                            <div class="wa-bubble">
                                <div class="wa-msg-content">
                                    <!-- Dynamic Header Preview -->
                                    <div id="previewHeader" class="wa-header-text d-none"></div>

                                    <!-- Dynamic Body Preview -->
                                    <div id="previewBody" class="wa-body-text">Type message body on the left to see live WhatsApp preview...</div>

                                    <!-- Dynamic Footer Preview -->
                                    <div id="previewFooter" class="wa-footer-text d-none"></div>

                                    <!-- Meta Time & Ticks -->
                                    <div class="wa-meta-time">
                                        <span>12:35</span>
                                        <i class="bi bi-check2 text-muted" style="font-size: 13px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Info Bar -->
                    <div class="bg-light text-center small text-muted py-2 border-top">
                        <i class="bi bi-shield-check text-success me-1"></i>Official Meta Cloud API Template Format
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Authentic WhatsApp Preview Styles */
.wa-preview-wrapper {
    max-width: 380px;
    margin: 0 auto;
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid #d1d7db;
    background: #ffffff;
}
.wa-chat-header {
    background: #008069;
    padding: 10px 14px;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 10px;
}
.wa-chat-avatar {
    width: 36px;
    height: 36px;
    background: #ffffff;
    color: #008069;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 13px;
    flex-shrink: 0;
}
.wa-chat-body {
    background-color: #efeae2;
    background-image: radial-gradient(#d1c7b7 12%, transparent 13%);
    background-size: 16px 16px;
    padding: 16px 12px 24px;
    min-height: 420px;
    display: flex;
    flex-direction: column;
}
.wa-date-pill {
    background: #ffffff;
    color: #54656f;
    font-size: 11px;
    font-weight: 500;
    padding: 3px 10px;
    border-radius: 6px;
    box-shadow: 0 1px 1px rgba(11,20,26,.12);
    align-self: center;
    margin-bottom: 10px;
    text-transform: uppercase;
}
.wa-bubble-container {
    position: relative;
    max-width: 90%;
    margin-left: 8px;
}
.wa-bubble {
    background: #ffffff;
    border-radius: 7.5px;
    box-shadow: 0 1px 0.5px rgba(11,20,26,.13);
    position: relative;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: #111b21;
    overflow: hidden;
}
.wa-bubble-tail {
    position: absolute;
    top: 0;
    left: -8px;
    width: 0;
    height: 0;
    border-top: 0px solid transparent;
    border-bottom: 10px solid transparent;
    border-right: 8px solid #ffffff;
}
.wa-msg-content {
    padding: 8px 10px 4px 10px;
}
.wa-header-text {
    font-size: 14.5px;
    font-weight: 700;
    color: #111b21;
    margin-bottom: 6px;
    line-height: 1.35;
}
.wa-header-media {
    background: #e9edef;
    border-radius: 6px;
    padding: 22px 12px;
    text-align: center;
    color: #54656f;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 8px;
    border: 1px dashed #c4ccd0;
}
.wa-body-text {
    font-size: 14px;
    line-height: 1.42;
    color: #111b21;
    white-space: pre-wrap;
    word-break: break-word;
}
.wa-body-var-badge {
    background: #e7f8f5;
    color: #008069;
    padding: 0 4px;
    border-radius: 4px;
    font-weight: 600;
    font-size: 13.5px;
    border: 1px solid #a3e6d8;
    display: inline-block;
}
.wa-footer-text {
    font-size: 11.5px;
    color: #667781;
    margin-top: 6px;
    margin-bottom: 2px;
    line-height: 1.3;
}
.wa-meta-time {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 3px;
    font-size: 11px;
    color: #667781;
    margin-top: 2px;
}
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const nameInput = document.getElementById('templateNameInput');
    const headerTypeSelect = document.getElementById('headerTypeSelect');
    const headerTextWrapper = document.getElementById('headerTextWrapper');
    const headerTextInput = document.getElementById('headerTextInput');
    const headerImageWrapper = document.getElementById('headerImageWrapper');
    const headerImageInput = document.getElementById('headerImageInput');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const imagePreviewThumb = document.getElementById('imagePreviewThumb');
    const imageFileName = document.getElementById('imageFileName');
    const imageFileSize = document.getElementById('imageFileSize');
    const btnRemoveImage = document.getElementById('btnRemoveImage');
    const headerVideoWrapper = document.getElementById('headerVideoWrapper');
    const headerDocumentWrapper = document.getElementById('headerDocumentWrapper');

    const bodyTextInput = document.getElementById('bodyTextInput');
    const footerTextInput = document.getElementById('footerTextInput');
    const charCounter = document.getElementById('charCounter');
    const variableContainer = document.getElementById('variableExamplesContainer');
    const variableInputsList = document.getElementById('variableInputsList');

    const previewHeader = document.getElementById('previewHeader');
    const previewBody = document.getElementById('previewBody');
    const previewFooter = document.getElementById('previewFooter');

    let uploadedImageDataUrl = null;

    // Force lowercase and underscore on template name
    nameInput.addEventListener('input', function() {
        this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '');
    });

    headerTypeSelect.addEventListener('change', function() {
        headerTextWrapper.classList.toggle('d-none', this.value !== 'TEXT');
        headerImageWrapper.classList.toggle('d-none', this.value !== 'IMAGE');
        headerVideoWrapper.classList.toggle('d-none', this.value !== 'VIDEO');
        headerDocumentWrapper.classList.toggle('d-none', this.value !== 'DOCUMENT');
        updatePreview();
    });

    if (headerImageInput) {
        headerImageInput.addEventListener('change', function(e) {
            const file = e.target.files && e.target.files[0];
            if (file) {
                imageFileName.textContent = file.name;
                imageFileSize.textContent = (file.size / 1024).toFixed(1) + ' KB';
                const reader = new FileReader();
                reader.onload = function(evt) {
                    uploadedImageDataUrl = evt.target.result;
                    imagePreviewThumb.src = uploadedImageDataUrl;
                    imagePreviewContainer.classList.remove('d-none');
                    updatePreview();
                };
                reader.readAsDataURL(file);
            } else {
                uploadedImageDataUrl = null;
                imagePreviewContainer.classList.add('d-none');
                updatePreview();
            }
        });
    }

    if (btnRemoveImage) {
        btnRemoveImage.addEventListener('click', function() {
            headerImageInput.value = '';
            uploadedImageDataUrl = null;
            imagePreviewContainer.classList.add('d-none');
            updatePreview();
        });
    }

    headerTextInput.addEventListener('input', updatePreview);
    footerTextInput.addEventListener('input', updatePreview);

    const singleBraceWarning = document.getElementById('singleBraceWarning');
    const singleBraceFound = document.getElementById('singleBraceFound');
    const btnFixSingleBraces = document.getElementById('btnFixSingleBraces');

    function checkSingleBraces(text) {
        if (!singleBraceWarning) return;
        const match = text.match(/(?<!\{)\{(\d+)\}(?!})/);
        if (match) {
            singleBraceFound.textContent = match[0];
            singleBraceWarning.classList.remove('d-none');
        } else {
            singleBraceWarning.classList.add('d-none');
        }
    }

    if (btnFixSingleBraces) {
        btnFixSingleBraces.addEventListener('click', function() {
            bodyTextInput.value = bodyTextInput.value.replace(/(?<!\{)\{(\d+)\}(?!})/g, '{' + '{' + '$1' + '}' + '}');
            bodyTextInput.dispatchEvent(new Event('input'));
        });
    }

    bodyTextInput.addEventListener('input', function() {
        charCounter.textContent = this.value.length + ' / 1024';
        checkSingleBraces(this.value);
        detectVariables(this.value);
        updatePreview();
    });

    function escapeHtml(str) {
        return (str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function detectVariables(text) {
        const matches = text.match(/\{\{(\d+)\}\}/g);
        if (!matches || matches.length === 0) {
            variableContainer.classList.add('d-none');
            variableInputsList.innerHTML = '';
            return;
        }

        const uniqueVars = [...new Set(matches.map(m => m.replace(/[\{\}]/g, '')))].sort((a,b) => a - b);
        variableContainer.classList.remove('d-none');

        // Preserve existing values
        const currentVals = {};
        variableInputsList.querySelectorAll('input').forEach(inp => {
            currentVals[inp.dataset.var] = inp.value;
        });

        variableInputsList.innerHTML = '';
        uniqueVars.forEach(v => {
            const val = currentVals[v] || '';
            const row = document.createElement('div');
            row.className = 'mb-2';
            
            const group = document.createElement('div');
            group.className = 'input-group input-group-sm';

            const span = document.createElement('span');
            span.className = 'input-group-text font-monospace bg-white';
            span.textContent = '{{' + v + '}}';

            const input = document.createElement('input');
            input.type = 'text';
            input.name = 'example_body_params[' + v + ']';
            input.className = 'form-control';
            input.placeholder = 'Sample value for {{' + v + '}} (e.g. John)';
            input.dataset.var = v;
            input.value = val;
            input.required = true;
            input.addEventListener('input', updatePreview);

            group.appendChild(span);
            group.appendChild(input);
            row.appendChild(group);
            variableInputsList.appendChild(row);
        });
    }

    function updatePreview() {
        // Header
        const headerType = headerTypeSelect.value;
        if (headerType === 'TEXT' && headerTextInput.value.trim() !== '') {
            previewHeader.textContent = headerTextInput.value;
            previewHeader.className = 'wa-header-text';
            previewHeader.classList.remove('d-none');
        } else if (headerType === 'IMAGE') {
            if (uploadedImageDataUrl) {
                previewHeader.innerHTML = '<img src="' + uploadedImageDataUrl + '" alt="Header Image" style="width: 100%; max-height: 220px; object-fit: cover; border-radius: 6px; margin-bottom: 8px; display: block;" />';
            } else {
                previewHeader.innerHTML = '<div class="wa-header-media"><i class="bi bi-image fs-4 d-block mb-1"></i>[IMAGE Header Sample Required]</div>';
            }
            previewHeader.className = '';
            previewHeader.classList.remove('d-none');
        } else if (headerType === 'VIDEO') {
            previewHeader.innerHTML = '<div class="wa-header-media"><i class="bi bi-play-btn-fill fs-4 d-block mb-1"></i>[VIDEO Header]</div>';
            previewHeader.className = '';
            previewHeader.classList.remove('d-none');
        } else if (headerType === 'DOCUMENT') {
            previewHeader.innerHTML = '<div class="wa-header-media"><i class="bi bi-file-earmark-pdf-fill fs-4 d-block mb-1"></i>[DOCUMENT PDF Header]</div>';
            previewHeader.className = '';
            previewHeader.classList.remove('d-none');
        } else {
            previewHeader.classList.add('d-none');
            previewHeader.innerHTML = '';
        }

        // Body
        let rawBody = bodyTextInput.value;
        if (rawBody.trim() === '') {
            previewBody.innerHTML = 'Type message body on the left to see live WhatsApp preview...';
        } else {
            let safeBody = escapeHtml(rawBody);
            // Replace variables with sample values if typed
            variableInputsList.querySelectorAll('input').forEach(inp => {
                const varNum = inp.dataset.var;
                const sampleVal = inp.value.trim();
                const placeholder = '{{' + varNum + '}}';
                const re = new RegExp('\\{\\{' + varNum + '\\}\\}', 'g');
                if (sampleVal) {
                    safeBody = safeBody.replace(re, '<span class="wa-body-var-badge">' + escapeHtml(sampleVal) + '</span>');
                } else {
                    safeBody = safeBody.replace(re, '<span class="wa-body-var-badge">' + placeholder + '</span>');
                }
            });
            previewBody.innerHTML = safeBody;
        }

        // Footer
        if (footerTextInput.value.trim() !== '') {
            previewFooter.textContent = footerTextInput.value;
            previewFooter.classList.remove('d-none');
        } else {
            previewFooter.classList.add('d-none');
        }
    }

    // Trigger initial calculation
    if (headerTypeSelect.value !== 'NONE') {
        headerTypeSelect.dispatchEvent(new Event('change'));
    }
    if (bodyTextInput.value) {
        detectVariables(bodyTextInput.value);
        updatePreview();
    }
});
</script>
@endpush
@endsection
