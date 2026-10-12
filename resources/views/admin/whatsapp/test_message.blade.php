@extends('admin.layouts.app')

@section('title', 'Send Test WhatsApp Message - Peers Global Unity')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="bi bi-send text-primary me-2"></i>Send Test WhatsApp Message
            </h1>
            <p class="text-muted small mb-0">Dispatch a live test template message using Meta Cloud API.</p>
        </div>
        <a href="{{ route('admin.whatsapp.messages') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-journal-text me-1"></i>View Message Logs
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Validation error:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <form action="{{ route('admin.whatsapp.test_message.send') }}" method="POST" id="testSendForm" enctype="multipart/form-data">
                @csrf

                <!-- Sender Asset Overview -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="bi bi-building me-2 text-primary"></i>Meta Business Sender Identity
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Sending Phone Number</span>
                                <span class="fw-bold font-monospace text-dark">{{ $senderNumber }}</span>
                                <span class="badge bg-success-subtle text-success ms-1">Active</span>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted small d-block">Verified Business Name</span>
                                <span class="fw-semibold text-dark">Peers Global Business Media Pvt Ltd</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recipient & Template Config -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="bi bi-person-lines-fill me-2 text-primary"></i>Recipient & Template Selection
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-semibold mb-0">Recipient Mobile Number <span class="text-danger">*</span></label>
                                <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 11px;">
                                    <i class="bi bi-globe me-1"></i>Any Recipient Number Supported
                                </span>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-phone"></i></span>
                                <input type="text" name="recipient" id="recipientInput" class="form-control font-monospace" placeholder="+91XXXXXXXXXX" value="{{ old('recipient', $allowedRecipient) }}" required>
                            </div>
                            <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                                <span class="small text-muted">Quick Select:</span>
                                @foreach($allowedRecipients ?? ['+916353025164', '+917041151714'] as $num)
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 font-monospace quick-number-btn" data-phone="{{ $num }}">
                                        <i class="bi bi-person-check me-1"></i>{{ $num }}
                                    </button>
                                @endforeach
                            </div>
                            <div class="form-text small text-muted">Enter any mobile number with country code (e.g. +91XXXXXXXXXX). You can also click the quick select buttons.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Select Approved WhatsApp Template <span class="text-danger">*</span></label>
                            <select name="template_name" id="templateSelect" class="form-select" required>
                                <option value="">-- Choose an approved template --</option>
                                @foreach($templates as $tmpl)
                                    <option value="{{ $tmpl->name }}" data-language="{{ $tmpl->language }}" data-components='@json($tmpl->components)' {{ (old('template_name', request('template')) === $tmpl->name) ? 'selected' : '' }}>
                                        {{ $tmpl->name }} ({{ $tmpl->language }}) - [{{ $tmpl->category }}]
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <input type="hidden" name="language" id="templateLanguageInput" value="{{ old('language', request('language', 'en_US')) }}">

                        <!-- Dynamic Header Media Input Container -->
                        <div id="headerMediaContainer" class="p-3 bg-light rounded border mb-3 d-none">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-bold small text-muted text-uppercase">
                                    <i class="bi bi-image me-1"></i><span id="headerMediaTypeLabel">Header Image / Media</span>
                                </div>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 11px;">
                                    <i class="bi bi-stars me-1"></i>Template Media Header
                                </span>
                            </div>

                            <!-- Sample Media Detected Alert -->
                            <div id="sampleMediaAlert" class="alert alert-info py-2 px-3 small d-none mb-3">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div>
                                        <i class="bi bi-check-circle-fill text-info me-1"></i>
                                        <strong id="sampleMediaAlertTitle">Meta Approved Sample Media Available:</strong> 
                                        <span id="sampleMediaAlertDesc">The approved sample media will be automatically sent if no custom file is selected.</span>
                                    </div>
                                    <button type="button" class="btn btn-xs btn-outline-primary btn-sm py-0 px-2" id="useSampleMediaBtn">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Use Approved Sample
                                    </button>
                                </div>
                            </div>

                            <!-- Options to Select/Upload Media -->
                            <div class="card border-0 bg-white p-3 shadow-sm mb-2">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-dark mb-1" id="headerFileLabel">
                                            <i class="bi bi-upload me-1 text-primary"></i>Upload Media File
                                        </label>
                                        <input type="file" name="header_image_file" id="headerImageFileInput" class="form-control form-control-sm" accept="image/png,image/jpeg,image/jpg,video/mp4,video/3gpp,application/pdf">
                                        <div class="form-text small text-muted" id="headerFileHelp">Supports JPG, PNG (Max 5MB), MP4 (Max 16MB). Uploads directly to Meta.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold text-dark mb-1" id="headerUrlLabel">
                                            <i class="bi bi-link-45deg me-1 text-primary"></i>Or Enter Public Media URL
                                        </label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-white"><i class="bi bi-globe"></i></span>
                                            <input type="url" name="header_image_url" id="headerImageUrlInput" class="form-control" placeholder="https://example.com/media" value="{{ old('header_image_url') }}">
                                        </div>
                                        <div class="form-text small text-muted" id="headerUrlHelp">Direct public HTTPS link.</div>
                                    </div>
                                </div>
                            </div>

                            <div id="mediaSelectedNotice" class="text-success small fw-semibold d-none">
                                <i class="bi bi-check2-circle me-1"></i><span id="mediaSelectedText"></span>
                            </div>
                        </div>

                        <!-- Dynamic Variables Input Container -->
                        <div id="variablesContainer" class="p-3 bg-light rounded border d-none">
                            <div class="fw-bold small text-muted text-uppercase mb-2">
                                <i class="bi bi-braces me-1"></i>Template Parameters
                            </div>
                            <div id="variablesFields"></div>
                        </div>
                    </div>
                </div>

                <!-- Send Button -->
                <div class="d-grid mb-5">
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                        <i class="bi bi-send-fill me-2"></i>Send Test Message via Meta Cloud API
                    </button>
                </div>
            </form>
        </div>

        <!-- WhatsApp Authentic Phone Preview -->
        <div class="col-lg-5">
            <div class="sticky-top" style="top: 20px;">
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                        <h6 class="card-title mb-0 fw-bold text-dark">
                            <i class="bi bi-eye text-primary me-2"></i>Template Preview
                        </h6>
                        <span class="badge bg-success-subtle text-success">
                            <i class="bi bi-whatsapp me-1"></i>WhatsApp Cloud API
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
                                    <!-- Dynamic Header -->
                                    <div id="waHeaderContainer"></div>

                                    <!-- Dynamic Body -->
                                    <div id="waBodyText" class="wa-body-text">Select a template to view the message preview.</div>

                                    <!-- Dynamic Footer -->
                                    <div id="waFooterText" class="wa-footer-text d-none"></div>

                                    <!-- Time & Ticks -->
                                    <div class="wa-meta-time">
                                        <span>12:35</span>
                                        <i class="bi bi-check2-all" style="color: #53bdeb; font-size: 13px;"></i>
                                    </div>
                                </div>

                                <!-- Dynamic Action Buttons -->
                                <div id="waButtonsContainer" class="wa-buttons-list"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Info Bar -->
                    <div class="bg-light text-center small text-muted py-2 border-top">
                        Sender: <strong class="font-monospace text-dark">{{ $senderNumber }}</strong>
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
.wa-buttons-list {
    border-top: 1px solid #e9edef;
    margin-top: 6px;
}
.wa-btn-item {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 12px;
    color: #00a884;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    border-bottom: 1px solid #e9edef;
    background: #ffffff;
    transition: background 0.15s ease;
}
.wa-btn-item:last-child {
    border-bottom: none;
}
.wa-btn-item:hover {
    background: #f5f6f6;
    color: #008069;
}
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const templateSelect = document.getElementById('templateSelect');
    const languageInput = document.getElementById('templateLanguageInput');
    const headerMediaContainer = document.getElementById('headerMediaContainer');
    const headerMediaTypeLabel = document.getElementById('headerMediaTypeLabel');
    const headerFileLabel = document.getElementById('headerFileLabel');
    const headerFileHelp = document.getElementById('headerFileHelp');
    const headerUrlLabel = document.getElementById('headerUrlLabel');
    const headerUrlHelp = document.getElementById('headerUrlHelp');
    const sampleMediaAlert = document.getElementById('sampleMediaAlert');
    const sampleMediaAlertTitle = document.getElementById('sampleMediaAlertTitle');
    const sampleMediaAlertDesc = document.getElementById('sampleMediaAlertDesc');
    const useSampleMediaBtn = document.getElementById('useSampleMediaBtn');
    const headerImageFileInput = document.getElementById('headerImageFileInput');
    const headerImageUrlInput = document.getElementById('headerImageUrlInput');
    const mediaSelectedNotice = document.getElementById('mediaSelectedNotice');
    const mediaSelectedText = document.getElementById('mediaSelectedText');
    const variablesContainer = document.getElementById('variablesContainer');
    const variablesFields = document.getElementById('variablesFields');
    const waHeaderContainer = document.getElementById('waHeaderContainer');
    const waBodyText = document.getElementById('waBodyText');
    const waFooterText = document.getElementById('waFooterText');
    const waButtonsContainer = document.getElementById('waButtonsContainer');

    let currentTemplateSampleUrl = null;
    let customSelectedMediaSrc = null;

    if (headerImageFileInput) {
        headerImageFileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(evt) {
                    customSelectedMediaSrc = evt.target.result;
                    renderPreview();
                };
                reader.readAsDataURL(file);
                mediaSelectedNotice.classList.remove('d-none');
                mediaSelectedText.textContent = 'Selected: ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
                headerImageUrlInput.value = '';
            } else {
                customSelectedMediaSrc = headerImageUrlInput.value.trim() || currentTemplateSampleUrl;
                mediaSelectedNotice.classList.add('d-none');
                renderPreview();
            }
        });
    }

    if (headerImageUrlInput) {
        headerImageUrlInput.addEventListener('input', function() {
            const url = this.value.trim();
            if (url) {
                customSelectedMediaSrc = url;
                if (headerImageFileInput) headerImageFileInput.value = '';
                mediaSelectedNotice.classList.remove('d-none');
                mediaSelectedText.textContent = 'Using Custom URL: ' + url;
            } else {
                customSelectedMediaSrc = currentTemplateSampleUrl;
                mediaSelectedNotice.classList.add('d-none');
            }
            renderPreview();
        });
    }

    if (useSampleMediaBtn) {
        useSampleMediaBtn.addEventListener('click', function() {
            if (headerImageFileInput) headerImageFileInput.value = '';
            if (headerImageUrlInput) headerImageUrlInput.value = '';
            customSelectedMediaSrc = currentTemplateSampleUrl;
            mediaSelectedNotice.classList.remove('d-none');
            mediaSelectedText.textContent = 'Reset to approved template sample image';
            renderPreview();
        });
    }

    templateSelect.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        customSelectedMediaSrc = null;
        currentTemplateSampleUrl = null;
        if (headerImageFileInput) headerImageFileInput.value = '';
        if (headerImageUrlInput) headerImageUrlInput.value = '';
        if (mediaSelectedNotice) mediaSelectedNotice.classList.add('d-none');

        if (!selected || !selected.value) {
            headerMediaContainer.classList.add('d-none');
            variablesContainer.classList.add('d-none');
            variablesFields.innerHTML = '';
            waHeaderContainer.innerHTML = '';
            waBodyText.textContent = 'Select a template to view the message preview.';
            waFooterText.classList.add('d-none');
            waButtonsContainer.innerHTML = '';
            return;
        }

        const lang = selected.dataset.language || 'en_US';
        languageInput.value = lang;

        let components = [];
        try {
            components = JSON.parse(selected.dataset.components || '[]');
        } catch (e) {
            components = [];
        }

        // Header Check
        const headerComp = components.find(c => c.type === 'HEADER');
        if (headerComp && headerComp.format && headerComp.format !== 'TEXT') {
            headerMediaContainer.classList.remove('d-none');
            const fmt = headerComp.format.toUpperCase();
            headerMediaTypeLabel.textContent = 'Header ' + fmt + ' Selection';

            if (fmt === 'VIDEO') {
                if (headerFileLabel) headerFileLabel.innerHTML = '<i class="bi bi-camera-video me-1 text-primary"></i>Upload Video File (MP4, 3GP)';
                if (headerImageFileInput) {
                    headerImageFileInput.name = 'header_video_file';
                    headerImageFileInput.accept = 'video/mp4,video/3gpp,video/quicktime';
                }
                if (headerFileHelp) headerFileHelp.textContent = 'Supports MP4, 3GP (Max 16MB). Uploads directly to Meta Cloud API.';
                if (headerUrlLabel) headerUrlLabel.innerHTML = '<i class="bi bi-link-45deg me-1 text-primary"></i>Or Enter Public Video URL (MP4)';
                if (headerImageUrlInput) {
                    headerImageUrlInput.name = 'header_video_url';
                    headerImageUrlInput.placeholder = 'https://example.com/video.mp4';
                }
                if (headerUrlHelp) headerUrlHelp.textContent = 'Direct public HTTPS video link (MP4).';
                if (sampleMediaAlertTitle) sampleMediaAlertTitle.textContent = 'Meta Approved Sample Video Available:';
                if (sampleMediaAlertDesc) sampleMediaAlertDesc.textContent = 'The approved sample video will be automatically sent if no custom video is selected.';
            } else if (fmt === 'DOCUMENT') {
                if (headerFileLabel) headerFileLabel.innerHTML = '<i class="bi bi-file-earmark-pdf me-1 text-primary"></i>Upload Document (PDF)';
                if (headerImageFileInput) {
                    headerImageFileInput.name = 'header_document_file';
                    headerImageFileInput.accept = 'application/pdf';
                }
                if (headerFileHelp) headerFileHelp.textContent = 'Supports PDF (Max 16MB). Uploads directly to Meta Cloud API.';
                if (headerUrlLabel) headerUrlLabel.innerHTML = '<i class="bi bi-link-45deg me-1 text-primary"></i>Or Enter Public Document URL';
                if (headerImageUrlInput) {
                    headerImageUrlInput.name = 'header_document_url';
                    headerImageUrlInput.placeholder = 'https://example.com/document.pdf';
                }
                if (headerUrlHelp) headerUrlHelp.textContent = 'Direct public HTTPS document link (PDF).';
                if (sampleMediaAlertTitle) sampleMediaAlertTitle.textContent = 'Meta Approved Sample Document Available:';
                if (sampleMediaAlertDesc) sampleMediaAlertDesc.textContent = 'The approved sample document will be automatically sent if no custom document is selected.';
            } else {
                if (headerFileLabel) headerFileLabel.innerHTML = '<i class="bi bi-upload me-1 text-primary"></i>Upload Image File (JPG, PNG)';
                if (headerImageFileInput) {
                    headerImageFileInput.name = 'header_image_file';
                    headerImageFileInput.accept = 'image/png,image/jpeg,image/jpg,image/webp';
                }
                if (headerFileHelp) headerFileHelp.textContent = 'Supports JPG, PNG (Max 16MB). Uploads directly to Meta Cloud API.';
                if (headerUrlLabel) headerUrlLabel.innerHTML = '<i class="bi bi-link-45deg me-1 text-primary"></i>Or Enter Public Image URL';
                if (headerImageUrlInput) {
                    headerImageUrlInput.name = 'header_image_url';
                    headerImageUrlInput.placeholder = 'https://example.com/banner.jpg';
                }
                if (headerUrlHelp) headerUrlHelp.textContent = 'Direct public HTTPS image link.';
                if (sampleMediaAlertTitle) sampleMediaAlertTitle.textContent = 'Meta Approved Sample Image Available:';
                if (sampleMediaAlertDesc) sampleMediaAlertDesc.textContent = 'The approved sample image will be automatically sent if no custom image is selected.';
            }

            // Check if sample media exists in template definition
            if (headerComp.example && Array.isArray(headerComp.example.header_handle) && headerComp.example.header_handle[0]) {
                const handle = headerComp.example.header_handle[0];
                if (typeof handle === 'string' && (handle.startsWith('http://') || handle.startsWith('https://'))) {
                    currentTemplateSampleUrl = handle;
                    sampleMediaAlert.classList.remove('d-none');
                } else {
                    sampleMediaAlert.classList.add('d-none');
                }
            } else {
                sampleMediaAlert.classList.add('d-none');
            }
        } else {
            headerMediaContainer.classList.add('d-none');
        }

        // Find BODY component
        const bodyComp = components.find(c => c.type === 'BODY') || {};
        const bodyRaw = bodyComp.text || '';

        // Extract {{1}}, {{2}}
        const matches = bodyRaw.match(/\{\{(\d+)\}\}/g);
        if (matches && matches.length > 0) {
            const uniqueVars = [...new Set(matches.map(m => m.replace(/[\{\}]/g, '')))].sort((a,b) => a - b);
            variablesContainer.classList.remove('d-none');
            variablesFields.innerHTML = '';

            uniqueVars.forEach(v => {
                const row = document.createElement('div');
                row.className = 'mb-2';
                
                const label = document.createElement('label');
                label.className = 'form-label small fw-semibold';
                label.textContent = 'Parameter {{' + v + '}}';

                const input = document.createElement('input');
                input.type = 'text';
                input.name = 'variables[' + v + ']';
                input.className = 'form-control form-control-sm var-input';
                input.dataset.var = v;
                input.placeholder = 'Enter value for {{' + v + '}}';
                input.required = true;
                input.addEventListener('input', renderPreview);

                row.appendChild(label);
                row.appendChild(input);
                variablesFields.appendChild(row);
            });
        } else {
            variablesContainer.classList.add('d-none');
            variablesFields.innerHTML = '';
        }

        renderPreview();
    });

    function escapeHtml(str) {
        return (str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderPreview() {
        const selected = templateSelect.options[templateSelect.selectedIndex];
        if (!selected || !selected.value) return;

        let components = [];
        try {
            components = JSON.parse(selected.dataset.components || '[]');
        } catch (e) {
            components = [];
        }

        // 1. Header Preview
        const headerComp = components.find(c => c.type === 'HEADER');
        waHeaderContainer.innerHTML = '';
        if (headerComp) {
            if (headerComp.format === 'TEXT' && headerComp.text) {
                const hdr = document.createElement('div');
                hdr.className = 'wa-header-text';
                hdr.textContent = headerComp.text;
                waHeaderContainer.appendChild(hdr);
            } else if (headerComp.format === 'IMAGE') {
                const activeImg = customSelectedMediaSrc || currentTemplateSampleUrl;
                if (activeImg) {
                    const img = document.createElement('img');
                    img.src = activeImg;
                    img.className = 'w-100 rounded mb-2';
                    img.style.maxHeight = '200px';
                    img.style.objectFit = 'cover';
                    img.style.border = '1px solid #d1d7db';
                    img.alt = 'Header Image';
                    waHeaderContainer.appendChild(img);
                } else {
                    const mediaBox = document.createElement('div');
                    mediaBox.className = 'wa-header-media';
                    mediaBox.innerHTML = '<i class="bi bi-image fs-4 d-block mb-1"></i>[IMAGE Header]';
                    waHeaderContainer.appendChild(mediaBox);
                }
            } else if (headerComp.format === 'VIDEO') {
                const activeVid = customSelectedMediaSrc || currentTemplateSampleUrl;
                if (activeVid) {
                    const vid = document.createElement('video');
                    vid.src = activeVid;
                    vid.controls = true;
                    vid.className = 'w-100 rounded mb-2';
                    vid.style.maxHeight = '200px';
                    vid.style.objectFit = 'cover';
                    vid.style.border = '1px solid #d1d7db';
                    waHeaderContainer.appendChild(vid);
                } else {
                    const mediaBox = document.createElement('div');
                    mediaBox.className = 'wa-header-media';
                    mediaBox.innerHTML = '<i class="bi bi-play-btn-fill fs-4 d-block mb-1"></i>[VIDEO Header]';
                    waHeaderContainer.appendChild(mediaBox);
                }
            } else if (headerComp.format && headerComp.format !== 'TEXT') {
                const mediaBox = document.createElement('div');
                mediaBox.className = 'wa-header-media';
                mediaBox.innerHTML = '<i class="bi bi-file-earmark-pdf-fill fs-4 d-block mb-1"></i>[' + headerComp.format + ' Header]';
                waHeaderContainer.appendChild(mediaBox);
            }
        }

        // 2. Body
        const bodyComp = components.find(c => c.type === 'BODY') || {};
        let rawBody = bodyComp.text || 'No body text found in this template.';
        let safeBody = escapeHtml(rawBody);

        variablesFields.querySelectorAll('.var-input').forEach(inp => {
            const num = inp.dataset.var;
            const val = inp.value.trim();
            const placeholder = '{{' + num + '}}';
            const re = new RegExp('\\{\\{' + num + '\\}\\}', 'g');
            if (val) {
                safeBody = safeBody.replace(re, '<span class="wa-body-var-badge">' + escapeHtml(val) + '</span>');
            } else {
                safeBody = safeBody.replace(re, '<span class="wa-body-var-badge">' + placeholder + '</span>');
            }
        });
        waBodyText.innerHTML = safeBody;

        // 3. Footer
        const footerComp = components.find(c => c.type === 'FOOTER');
        if (footerComp && footerComp.text) {
            waFooterText.textContent = footerComp.text;
            waFooterText.classList.remove('d-none');
        } else {
            waFooterText.classList.add('d-none');
        }

        // 4. Buttons
        const buttonsComp = components.find(c => c.type === 'BUTTONS');
        waButtonsContainer.innerHTML = '';
        if (buttonsComp && Array.isArray(buttonsComp.buttons) && buttonsComp.buttons.length > 0) {
            buttonsComp.buttons.forEach(btn => {
                const btnDiv = document.createElement('div');
                btnDiv.className = 'wa-btn-item';
                let iconClass = 'bi-reply-fill';
                if (btn.type === 'URL') iconClass = 'bi-box-arrow-up-right';
                if (btn.type === 'PHONE_NUMBER') iconClass = 'bi-telephone-fill';
                btnDiv.innerHTML = '<i class="bi ' + iconClass + '"></i><span>' + escapeHtml(btn.text || btn.type) + '</span>';
                waButtonsContainer.appendChild(btnDiv);
            });
        }
    }

        document.querySelectorAll('.quick-number-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const input = document.getElementById('recipientInput');
                input.value = this.dataset.phone;
                input.focus();
            });
        });

        if (templateSelect.value) {
            templateSelect.dispatchEvent(new Event('change'));
        }
    });
</script>
@endpush
@endsection
