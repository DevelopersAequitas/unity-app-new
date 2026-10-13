@extends('admin.layouts.app')

@section('title', 'Create WhatsApp Campaign - Peers Global Unity')

@push('styles')
<style>
    .stepper-nav {
        display: flex;
        gap: 12px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }
    .stepper-item {
        flex: 1;
        min-width: 160px;
        padding: 10px 16px;
        border-radius: 8px;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        font-weight: 600;
        font-size: 0.875rem;
        display: flex;
        align-items: center;
        gap: 8px;
        color: #64748b;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .stepper-item.active {
        background-color: #ef4444; /* ReshoBrand style active stepper red */
        color: #ffffff;
        border-color: #ef4444;
    }
    .stepper-item.completed {
        background-color: #e0e7ff;
        color: #4338ca;
        border-color: #c7d2fe;
    }
    .stepper-item.future {
        background-color: #f1f5f9;
        color: #64748b;
    }

    .dropzone-box {
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        padding: 40px 20px;
        text-align: center;
        background-color: #ffffff;
        cursor: pointer;
        transition: border-color 0.2s;
    }
    .dropzone-box:hover, .dropzone-box.dragover {
        border-color: #6366f1;
        background-color: #f8fafc;
    }

    /* WhatsApp Live Preview Frame */
    .whatsapp-preview-card {
        background: #efeae2;
        background-image: radial-gradient(#d6cfc7 1px, transparent 1px);
        background-size: 16px 16px;
        border-radius: 12px;
        padding: 24px 16px;
        min-height: 480px;
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        align-items: center;
    }
    .whatsapp-bubble {
        background: #ffffff;
        border-radius: 8px;
        padding: 8px 10px 6px 10px;
        box-shadow: 0 1px 2px rgba(11,20,26,0.15);
        width: 100%;
        max-width: 330px;
        font-size: 0.8875rem;
        color: #111b21;
        position: relative;
        word-wrap: break-word;
        line-height: 1.42;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    }
    .whatsapp-bubble-body {
        color: #111b21;
        font-size: 0.8875rem;
        line-height: 1.42;
        word-wrap: break-word;
    }
    .whatsapp-bubble-body .wa-p {
        margin-bottom: 8px;
        line-height: 1.42;
    }
    .whatsapp-bubble-body .wa-p:last-child {
        margin-bottom: 0;
    }
    .whatsapp-bubble-body strong {
        font-weight: 700;
        color: #111b21;
    }
    .whatsapp-bubble-body em {
        font-style: italic;
    }
    .whatsapp-bubble-header-media {
        border-radius: 6px;
        overflow: hidden;
        margin-bottom: 8px;
        background-color: #0b141a;
        text-align: center;
        position: relative;
    }
    .whatsapp-bubble-header-media img {
        width: 100%;
        max-height: 200px;
        object-fit: cover;
        display: block;
        border-radius: 6px;
    }
    .whatsapp-bubble-header-media video {
        width: 100%;
        max-height: 220px;
        object-fit: contain;
        background-color: #000000;
        display: block;
        border-radius: 6px;
        outline: none;
    }
    .whatsapp-bubble-header-media .media-placeholder {
        padding: 24px 12px;
        color: #475569;
        background: #f1f5f9;
        border-radius: 6px;
    }
    .whatsapp-bubble-btn {
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid #f1f5f9;
        color: #0284c7;
        text-align: center;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
    }
    .whatsapp-bubble-time {
        font-size: 0.7rem;
        color: #94a3b8;
        text-align: right;
        margin-top: 6px;
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 3px;
    }

    .step-content {
        display: none;
    }
    .step-content.active {
        display: block;
    }
    .btn-resho-primary {
        background-color: #5542f6;
        color: #ffffff;
        border-radius: 8px;
        font-weight: 600;
    }
    .btn-resho-primary:hover {
        background-color: #4332d6;
        color: #ffffff;
    }
    .schedule-option-card {
        cursor: pointer;
        transition: all 0.2s;
    }
    .schedule-option-card.active {
        border-color: #5542f6 !important;
        background-color: #f5f3ff;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    {{-- Breadcrumb Navigation --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb small text-muted">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.whatsapp.campaigns.index') }}" class="text-decoration-none">Bulk Campaign</a></li>
            <li class="breadcrumb-item active" aria-current="page">Create Campaign</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0 text-gray-800 fw-bold">Create Campaign</h1>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <h6 class="alert-heading fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Please check the following before submitting:</h6>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Main Wizard Form --}}
    <form id="campaignWizardForm" action="{{ route('admin.whatsapp.campaigns.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="import_id" id="import_id" value="{{ old('import_id') }}">

        <div class="row g-4">
            {{-- Left Column: 4-Step Form Content --}}
            <div class="col-lg-8">
                {{-- Stepper Progress Bar (ReshoBrand style) --}}
                <div class="stepper-nav" id="stepperNav">
                    <div class="stepper-item active" data-step="1">
                        <i class="bi bi-info-circle"></i>
                        <span>Basic Information</span>
                    </div>
                    <div class="stepper-item future" data-step="2">
                        <i class="bi bi-file-earmark-spreadsheet"></i>
                        <span>Upload CSV</span>
                    </div>
                    <div class="stepper-item future" data-step="3">
                        <i class="bi bi-sliders"></i>
                        <span>Variables & Files</span>
                    </div>
                    <div class="stepper-item future" data-step="4">
                        <i class="bi bi-clock-history"></i>
                        <span>Scheduling</span>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-body p-4">

                        {{-- ==================== STEP 1: BASIC INFORMATION ==================== --}}
                        <div class="step-content active" id="step1">
                            <div class="d-flex align-items-center mb-4">
                                <div class="rounded-circle p-2 bg-primary bg-opacity-10 text-primary me-3">
                                    <i class="bi bi-exclamation-circle fs-4"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0 fw-bold text-gray-800">Basic Information</h5>
                                    <small class="text-muted">Enter campaign details and select template</small>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold text-gray-700">
                                    <span class="text-danger">*</span> Campaign Name
                                </label>
                                <input type="text" name="name" id="campaign_name" class="form-control form-control-lg" placeholder="Enter campaign name" value="{{ old('name') }}" required>
                                <div class="form-text">Choose a descriptive name for tracking and reporting purposes.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold text-gray-700">
                                    <span class="text-danger">*</span> Template
                                </label>
                                <select name="whatsapp_template_id" id="whatsapp_template_id" class="form-select form-select-lg" required>
                                    <option value="" disabled selected>Nothing Selected</option>
                                    @foreach($templates as $tmpl)
                                        <option value="{{ $tmpl['template_id'] }}" 
                                            data-name="{{ $tmpl['name'] }}"
                                            data-language="{{ $tmpl['language'] }}"
                                            data-category="{{ $tmpl['category'] }}"
                                            data-json="{{ json_encode($tmpl) }}"
                                            {{ old('whatsapp_template_id') === $tmpl['template_id'] ? 'selected' : '' }}>
                                            {{ $tmpl['name'] }} ({{ $tmpl['language'] }}) - {{ $tmpl['category'] ?? 'UTILITY' }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">Select an approved Meta WhatsApp template. Live preview updates immediately.</div>
                            </div>
                        </div>

                        {{-- ==================== STEP 2: UPLOAD CSV ==================== --}}
                        <div class="step-content" id="step2">
                            <div class="d-flex align-items-center mb-4">
                                <div class="rounded-circle p-2 bg-primary bg-opacity-10 text-primary me-3">
                                    <i class="bi bi-file-earmark-text fs-4"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0 fw-bold text-gray-800">Upload CSV File</h5>
                                    <small class="text-muted">Import your contacts via CSV</small>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-semibold text-gray-700">Choose CSV File</span>
                                <a href="{{ route('admin.whatsapp.campaigns.sample_csv') }}" class="text-decoration-none text-primary small fw-semibold">
                                    <i class="bi bi-download me-1"></i>Download sample file
                                </a>
                            </div>

                            {{-- Drag & Drop Upload Zone --}}
                            <div class="dropzone-box mb-3" id="csvDropzone">
                                <i class="bi bi-display fs-1 text-muted mb-2 d-block"></i>
                                <div class="fw-semibold text-gray-700">Drag and drop or click to browse</div>
                                <div class="text-muted small">CSV files only (Max 10MB)</div>
                                <input type="file" id="csvFileInput" accept=".csv,text/csv" class="d-none">
                            </div>

                            <div class="d-flex justify-content-end mb-3">
                                <button type="button" class="btn btn-resho-primary px-4" id="btnUploadCsv">
                                    <i class="bi bi-cloud-arrow-up me-1"></i>Upload
                                </button>
                            </div>

                            {{-- CSV Parsed Success Banner (Matches ReshoBrand screenshot 4) --}}
                            <div id="csvSuccessCard" class="card border-success border-2 rounded-3 mb-3 p-3 d-none" style="background-color: #f0fdf4;">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-check-circle text-success fs-3 me-3"></i>
                                        <div>
                                            <div class="fw-bold text-gray-800" id="csvFileName">contacts_sample.csv</div>
                                            <div class="small text-success fw-semibold" id="csvValidCount">611 valid records ready to send</div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-link text-danger text-decoration-none small" id="btnRemoveCsv">
                                        <i class="bi bi-x me-1"></i>Remove
                                    </button>
                                </div>
                            </div>

                            {{-- Informational Alert Note --}}
                            <div id="csvSummaryNote" class="alert alert-primary border-primary d-none" role="alert">
                                <strong>Note:</strong> <span id="csvSummaryText">Out of the 621 records in your CSV file, 611 records are valid. The campaign will be sent to these 611 contacts.</span>
                            </div>

                            {{-- Invalid Records Error Alert --}}
                            <div id="csvErrorsAlert" class="alert alert-warning border-warning d-none" role="alert">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                        <span id="csvErrorCount">10 rows had validation errors.</span>
                                    </div>
                                    <a href="#" id="btnDownloadErrors" class="btn btn-outline-dark btn-sm">
                                        <i class="bi bi-download me-1"></i>Download Error Report
                                    </a>
                                </div>
                            </div>
                        </div>

                        {{-- ==================== STEP 3: VARIABLES AND FILES ==================== --}}
                        <div class="step-content" id="step3">
                            <div class="d-flex align-items-center mb-4">
                                <div class="rounded-circle p-2 bg-primary bg-opacity-10 text-primary me-3">
                                    <i class="bi bi-sliders fs-4"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0 fw-bold text-gray-800">Variables and Files</h5>
                                    <small class="text-muted">customize your message with variables and media</small>
                                </div>
                            </div>

                            {{-- Header Media Container (If template has IMAGE, VIDEO, or DOCUMENT) --}}
                            <div id="headerMediaSection" class="card border p-3 rounded-3 mb-4 bg-light d-none">
                                <h6 class="fw-bold text-gray-800 mb-2">
                                    <i class="bi bi-image me-2 text-primary"></i>Header Media (<span id="headerMediaType">IMAGE</span>)
                                </h6>
                                <p class="text-muted small mb-3">Choose how to provide the header media: upload a file, enter a public URL, or take it from a column in your CSV sheet.</p>

                                <div class="row g-2 mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label small fw-semibold text-muted">Media Source</label>
                                        <select name="media_source" id="media_source" class="form-select form-select-sm">
                                            <option value="file" selected>Upload Single File</option>
                                            <option value="url">Public Media URL</option>
                                            <option value="csv_column">From CSV Sheet (Column)</option>
                                        </select>
                                    </div>

                                    <div class="col-md-8">
                                        {{-- 1. File Upload --}}
                                        <div id="mediaBoxFile">
                                            <label class="form-label small fw-semibold text-muted">Choose File</label>
                                            <input type="file" name="media_file" id="media_file" class="form-control form-control-sm" accept="image/*,video/*,application/pdf">
                                            <div class="form-text small">Upload image, video, or PDF (Max 16MB).</div>
                                        </div>

                                        {{-- 2. Fixed URL --}}
                                        <div id="mediaBoxUrl" class="d-none">
                                            <label class="form-label small fw-semibold text-muted">Public Media URL</label>
                                            <input type="url" name="media_url" id="media_url" class="form-control form-control-sm" placeholder="https://example.com/banner.jpg">
                                            <div class="form-text small">Direct HTTPS link accessible to Meta.</div>
                                        </div>

                                        {{-- 3. CSV Column --}}
                                        <div id="mediaBoxCsv" class="d-none">
                                            <label class="form-label small fw-semibold text-muted">Select CSV Column (Sheet)</label>
                                            <select name="media_column" id="media_column" class="form-select form-select-sm">
                                                {{-- Dynamically populated --}}
                                            </select>
                                            <div class="form-text small text-info"><i class="bi bi-info-circle me-1"></i>Each contact receives the media URL from their corresponding row in this sheet column.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Body Variables Container --}}
                            <div id="bodyVariablesSection" class="mb-4">
                                <h6 class="fw-bold text-gray-800 mb-3">
                                    <i class="bi bi-body-text me-2 text-primary"></i>Body Variables
                                </h6>
                                <div id="bodyVariablesList">
                                    <p class="text-muted small">Select a template to configure dynamic variables.</p>
                                </div>
                            </div>

                            {{-- Button URL Parameters Container --}}
                            <div id="buttonsSection" class="mb-4 d-none">
                                <h6 class="fw-bold text-gray-800 mb-3">
                                    <i class="bi bi-link-45deg me-2 text-primary"></i>Button URL Parameters
                                </h6>
                                <div id="buttonVariablesList"></div>
                            </div>
                        </div>

                        {{-- ==================== STEP 4: SCHEDULING ==================== --}}
                        <div class="step-content" id="step4">
                            <div class="d-flex align-items-center mb-4">
                                <div class="rounded-circle p-2 bg-primary bg-opacity-10 text-primary me-3">
                                    <i class="bi bi-clock-history fs-4"></i>
                                </div>
                                <div>
                                    <h5 class="mb-0 fw-bold text-gray-800">Scheduling</h5>
                                    <small class="text-muted">Choose when this campaign should be executed</small>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="card h-100 border p-3 rounded-3 schedule-option-card active" id="cardSendImmediate">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="schedule_type" id="schedule_immediate" value="immediate" checked>
                                            <label class="form-check-label fw-bold text-gray-800" for="schedule_immediate">
                                                Send Immediately
                                            </label>
                                        </div>
                                        <p class="text-muted small mt-2 mb-0">The campaign will be queued and sent immediately upon confirmation.</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card h-100 border p-3 rounded-3 schedule-option-card" id="cardScheduleLater">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="schedule_type" id="schedule_later" value="scheduled">
                                            <label class="form-check-label fw-bold text-gray-800" for="schedule_later">
                                                Schedule for Later
                                            </label>
                                        </div>
                                        <p class="text-muted small mt-2 mb-0">Pick a specific future date and time to automatically trigger sending.</p>
                                    </div>
                                </div>
                            </div>

                            <div id="scheduledTimeInputs" class="mb-4 p-3 bg-light rounded-3 d-none">
                                <label class="form-label fw-semibold text-gray-700">Scheduled Date & Time (IST / System Timezone)</label>
                                <input type="datetime-local" name="scheduled_at" id="scheduled_at" class="form-control" min="{{ now()->format('Y-m-d\TH:i') }}">
                                <div class="form-text">Campaign must be scheduled for a future time.</div>
                            </div>

                            {{-- Final Review Summary Box --}}
                            <div class="card border-0 bg-light p-3 rounded-3">
                                <h6 class="fw-bold text-gray-800 mb-2">Campaign Summary</h6>
                                <div class="row g-2 small">
                                    <div class="col-6 text-muted">Campaign Name:</div>
                                    <div class="col-6 fw-bold" id="summaryCampaignName">—</div>
                                    <div class="col-6 text-muted">WhatsApp Template:</div>
                                    <div class="col-6 fw-bold" id="summaryTemplateName">—</div>
                                    <div class="col-6 text-muted">Total Eligible Recipients:</div>
                                    <div class="col-6 fw-bold text-success" id="summaryValidRecipients">0</div>
                                    <div class="col-6 text-muted">Execution Timing:</div>
                                    <div class="col-6 fw-bold" id="summaryExecutionTiming">Immediate</div>
                                </div>
                            </div>
                        </div>

                    </div>

                    {{-- Stepper Footer Controls --}}
                    <div class="card-footer bg-white border-top py-3 d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-outline-secondary btn-sm px-3" id="btnPrevious" disabled>
                            <i class="bi bi-chevron-left me-1"></i>Previous
                        </button>
                        <div class="d-flex gap-1" id="paginationDots">
                            <span class="badge rounded-circle p-1 bg-primary"> </span>
                            <span class="badge rounded-circle p-1 bg-secondary bg-opacity-25"> </span>
                            <span class="badge rounded-circle p-1 bg-secondary bg-opacity-25"> </span>
                            <span class="badge rounded-circle p-1 bg-secondary bg-opacity-25"> </span>
                        </div>
                        <button type="button" class="btn btn-resho-primary btn-sm px-4" id="btnNext">
                            Next <i class="bi bi-chevron-right ms-1"></i>
                        </button>
                        <button type="submit" class="btn btn-success btn-sm px-4 d-none" id="btnSubmitCampaign">
                            <i class="bi bi-check2-circle me-1"></i>Confirm & Send
                        </button>
                    </div>
                </div>
            </div>

            {{-- Right Column: WhatsApp Live Preview --}}
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-3 sticky-top" style="top: 20px;">
                    <div class="card-header bg-white border-bottom py-3">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-eye text-primary me-2"></i>
                            <h6 class="mb-0 fw-bold text-gray-800">Live Preview</h6>
                        </div>
                        <div class="text-muted small">See how your WhatsApp message will look to recipients</div>
                    </div>
                    <div class="card-body p-3">
                        <div class="whatsapp-preview-card">
                            <div class="whatsapp-bubble" id="whatsappBubble">
                                {{-- Header Media Preview --}}
                                <div id="previewHeaderMedia" class="whatsapp-bubble-header-media d-none">
                                    <img id="previewHeaderImg" src="" alt="" class="d-none">
                                    <video id="previewHeaderVideo" controls playsinline preload="metadata" class="d-none"></video>
                                    <div id="previewHeaderDoc" class="d-none p-3 bg-light border rounded text-start d-flex align-items-center">
                                        <i class="bi bi-file-earmark-pdf-fill text-danger fs-2 me-2"></i>
                                        <div class="overflow-hidden">
                                            <div class="fw-bold small text-truncate" id="previewHeaderDocName">Document.pdf</div>
                                            <div class="text-muted" style="font-size: 0.72rem;">Document Header</div>
                                        </div>
                                    </div>
                                    <div id="previewHeaderPlaceholder" class="media-placeholder">
                                        <i class="bi bi-camera-video fs-1 d-block mb-1 text-primary" id="previewHeaderPlaceholderIcon"></i>
                                        <span class="small fw-semibold" id="previewHeaderLabel">Header Media</span>
                                    </div>
                                </div>
                                {{-- Header Text Preview --}}
                                <div id="previewHeaderText" class="fw-bold mb-2 text-dark fs-6 d-none"></div>
                                {{-- Body Text Preview --}}
                                <div id="previewBodyText" class="whatsapp-bubble-body">
                                    Select a template to see preview
                                </div>
                                {{-- Footer Preview --}}
                                <div id="previewFooterText" class="small text-muted mt-2 d-none"></div>
                                {{-- Buttons Preview --}}
                                <div id="previewButtonsContainer"></div>
                                {{-- Timestamp & Checkmarks --}}
                                <div class="whatsapp-bubble-time">
                                    <span>{{ now()->format('h:i A') }}</span>
                                    <i class="bi bi-check2-all text-primary ms-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    let currentStep = 1;
    let selectedTemplateData = null;
    let csvData = null;
    let uploadedHeaders = [];

    const stepContents = [
        document.getElementById('step1'),
        document.getElementById('step2'),
        document.getElementById('step3'),
        document.getElementById('step4')
    ];
    const stepperItems = document.querySelectorAll('.stepper-item');
    const btnPrevious = document.getElementById('btnPrevious');
    const btnNext = document.getElementById('btnNext');
    const btnSubmit = document.getElementById('btnSubmitCampaign');
    const dots = document.querySelectorAll('#paginationDots span');

    // Step navigation handler
    function showStep(step) {
        currentStep = step;
        stepContents.forEach((el, idx) => {
            el.classList.toggle('active', idx + 1 === step);
        });

        stepperItems.forEach((item, idx) => {
            const itemStep = idx + 1;
            item.classList.remove('active', 'completed', 'future');
            if (itemStep === step) {
                item.classList.add('active');
            } else if (itemStep < step) {
                item.classList.add('completed');
            } else {
                item.classList.add('future');
            }
        });

        dots.forEach((dot, idx) => {
            dot.className = (idx + 1 === step) ? 'badge rounded-circle p-1 bg-primary' : 'badge rounded-circle p-1 bg-secondary bg-opacity-25';
        });

        btnPrevious.disabled = (step === 1);
        if (step === 4) {
            btnNext.classList.add('d-none');
            btnSubmit.classList.remove('d-none');
            updateSummary();
        } else {
            btnNext.classList.remove('d-none');
            btnSubmit.classList.add('d-none');
        }

        updateLivePreview();
    }

    btnNext.addEventListener('click', function () {
        if (validateStep(currentStep)) {
            showStep(currentStep + 1);
        }
    });

    btnPrevious.addEventListener('click', function () {
        if (currentStep > 1) {
            showStep(currentStep - 1);
        }
    });

    stepperItems.forEach(item => {
        item.addEventListener('click', function () {
            const targetStep = parseInt(this.getAttribute('data-step'));
            if (targetStep < currentStep || validateStep(currentStep)) {
                showStep(targetStep);
            }
        });
    });

    function validateStep(step) {
        if (step === 1) {
            const name = document.getElementById('campaign_name').value.trim();
            const templateId = document.getElementById('whatsapp_template_id').value;
            if (!name) {
                alert('Please enter a campaign name.');
                return false;
            }
            if (!templateId) {
                alert('Please select an approved WhatsApp template.');
                return false;
            }
            return true;
        }
        if (step === 2) {
            const importId = document.getElementById('import_id').value;
            if (!importId || !csvData || csvData.valid_count <= 0) {
                alert('Please upload a valid CSV file containing recipients.');
                return false;
            }
            return true;
        }
        return true;
    }

    // Template selection and dynamic fields generation
    const templateSelect = document.getElementById('whatsapp_template_id');
    templateSelect.addEventListener('change', function () {
        const option = this.options[this.selectedIndex];
        if (!option || !option.getAttribute('data-json')) return;

        selectedTemplateData = JSON.parse(option.getAttribute('data-json'));
        buildVariableInputs(selectedTemplateData);
        updateLivePreview();
    });

    // If template was already selected on page load (e.g. old input)
    if (templateSelect.value) {
        const option = templateSelect.options[templateSelect.selectedIndex];
        if (option && option.getAttribute('data-json')) {
            selectedTemplateData = JSON.parse(option.getAttribute('data-json'));
            buildVariableInputs(selectedTemplateData);
            updateLivePreview();
        }
    }

    // CSV Dropzone & Upload Handler
    const dropzone = document.getElementById('csvDropzone');
    const fileInput = document.getElementById('csvFileInput');
    const btnUpload = document.getElementById('btnUploadCsv');

    dropzone.addEventListener('click', () => fileInput.click());
    dropzone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropzone.classList.add('dragover');
    });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            handleCsvUpload();
        }
    });

    btnUpload.addEventListener('click', handleCsvUpload);
    fileInput.addEventListener('change', handleCsvUpload);

    function handleCsvUpload() {
        if (!fileInput.files.length) {
            alert('Please select a CSV file first.');
            return;
        }

        const file = fileInput.files[0];
        const formData = new FormData();
        formData.append('file', file);
        formData.append('_token', '{{ csrf_token() }}');

        btnUpload.disabled = true;
        btnUpload.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Uploading...';

        fetch('{{ route("admin.whatsapp.campaigns.upload_csv") }}', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(response => {
            btnUpload.disabled = false;
            btnUpload.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i>Upload';

            if (!response.success) {
                alert('Upload Error: ' + response.message);
                return;
            }

            csvData = response.data;
            uploadedHeaders = csvData.headers || [];
            document.getElementById('import_id').value = csvData.import_id;

            // Display Green Success Card
            document.getElementById('csvFileName').textContent = file.name;
            document.getElementById('csvValidCount').textContent = csvData.valid_count + ' valid records ready to send';
            document.getElementById('csvSuccessCard').classList.remove('d-none');

            // Display Note Box
            document.getElementById('csvSummaryText').textContent = `Out of the ${csvData.total_rows} records in your CSV file, ${csvData.valid_count} records are valid. The campaign will be sent to these ${csvData.valid_count} contacts.`;
            document.getElementById('csvSummaryNote').classList.remove('d-none');

            // Handle Errors Alert
            if (csvData.invalid_count > 0) {
                document.getElementById('csvErrorCount').textContent = `${csvData.invalid_count} row(s) had errors and were excluded.`;
                document.getElementById('btnDownloadErrors').href = `{{ url('admin/whatsapp/campaigns/errors') }}/${csvData.import_id}`;
                document.getElementById('csvErrorsAlert').classList.remove('d-none');
            } else {
                document.getElementById('csvErrorsAlert').classList.add('d-none');
            }

            // Re-render variable mapping dropdowns with new CSV columns
            if (selectedTemplateData) {
                buildVariableInputs(selectedTemplateData);
            }
            updateLivePreview();
        })
        .catch(err => {
            btnUpload.disabled = false;
            btnUpload.innerHTML = '<i class="bi bi-cloud-arrow-up me-1"></i>Upload';
            alert('Upload failed. Please check your network or CSV format.');
        });
    }

    document.getElementById('btnRemoveCsv').addEventListener('click', function () {
        csvData = null;
        uploadedHeaders = [];
        fileInput.value = '';
        document.getElementById('import_id').value = '';
        document.getElementById('csvSuccessCard').classList.add('d-none');
        document.getElementById('csvSummaryNote').classList.add('d-none');
        document.getElementById('csvErrorsAlert').classList.add('d-none');
        if (selectedTemplateData) {
            buildVariableInputs(selectedTemplateData);
        }
        updateLivePreview();
    });

    // Header Media Source selector handler (Step 3)
    const mediaSourceSelect = document.getElementById('media_source');
    const mediaBoxFile = document.getElementById('mediaBoxFile');
    const mediaBoxUrl = document.getElementById('mediaBoxUrl');
    const mediaBoxCsv = document.getElementById('mediaBoxCsv');
    const mediaFileInput = document.getElementById('media_file');
    const mediaUrlInput = document.getElementById('media_url');
    const mediaColumnSelect = document.getElementById('media_column');

    let currentVideoBlobUrl = null;
    let currentImageBlobUrl = null;

    mediaSourceSelect.addEventListener('change', function () {
        const val = this.value;
        mediaBoxFile.classList.toggle('d-none', val !== 'file');
        mediaBoxUrl.classList.toggle('d-none', val !== 'url');
        mediaBoxCsv.classList.toggle('d-none', val !== 'csv_column');
        updateLivePreview();
    });

    mediaFileInput.addEventListener('change', updateLivePreview);
    mediaUrlInput.addEventListener('input', updateLivePreview);
    mediaColumnSelect.addEventListener('change', updateLivePreview);

    // Dynamic Variables Builder for Step 3
    function buildVariableInputs(template) {
        // 1. Header Media
        const mediaSection = document.getElementById('headerMediaSection');
        if (template.header && template.header.is_media) {
            mediaSection.classList.remove('d-none');
            document.getElementById('headerMediaType').textContent = template.header.format;
            document.getElementById('previewHeaderLabel').textContent = template.header.format + ' Header';

            // Populate media column dropdown
            mediaColumnSelect.innerHTML = buildColumnOptions();
        } else {
            mediaSection.classList.add('d-none');
        }

        // 2. Body Variables
        const bodyContainer = document.getElementById('bodyVariablesList');
        bodyContainer.innerHTML = '';

        if (!template.body_variables || template.body_variables.length === 0) {
            bodyContainer.innerHTML = '<div class="alert alert-light border small text-muted mb-0"><i class="bi bi-info-circle me-1"></i>This template has no dynamic body variables. All recipients receive the exact message text.</div>';
        } else {
            template.body_variables.forEach((v, idx) => {
                const row = document.createElement('div');
                row.className = 'card border p-3 rounded-3 mb-3 bg-light';
                row.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label fw-bold text-gray-800 mb-0">
                            <span class="text-danger">*</span> Body — Variable ${v.index} (${v.token})
                        </label>
                        ${v.example ? `<span class="badge bg-secondary bg-opacity-10 text-secondary">Example: ${v.example}</span>` : ''}
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <select name="variable_mapping[body_var_${v.index}][source]" class="form-select form-select-sm var-source-select" data-var-idx="${v.index}">
                                <option value="csv_column" selected>CSV Column (from sheet)</option>
                                <option value="fixed">Fixed Value (same for all)</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <div class="source-csv-box" id="source_csv_${v.index}">
                                <select name="variable_mapping[body_var_${v.index}][column]" class="form-select form-select-sm var-column-select" data-var-idx="${v.index}">
                                    ${buildColumnOptions(v.index)}
                                </select>
                            </div>
                            <div class="source-fixed-box d-none" id="source_fixed_${v.index}">
                                <input type="text" name="variable_mapping[body_var_${v.index}][value]" class="form-control form-control-sm var-fixed-input" data-var-idx="${v.index}" placeholder="Enter fixed value for all recipients">
                            </div>
                        </div>
                    </div>
                `;
                bodyContainer.appendChild(row);
            });
        }

        // 3. Dynamic URL Button Parameters
        const btnSection = document.getElementById('buttonsSection');
        const btnList = document.getElementById('buttonVariablesList');
        btnList.innerHTML = '';
        const dynamicBtns = (template.buttons || []).filter(b => b.has_variable);

        if (dynamicBtns.length > 0) {
            btnSection.classList.remove('d-none');
            dynamicBtns.forEach(b => {
                const bRow = document.createElement('div');
                bRow.className = 'card border p-3 rounded-3 mb-3 bg-light';
                bRow.innerHTML = `
                    <label class="form-label fw-bold text-gray-800 mb-2">
                        <span class="text-danger">*</span> Button URL Parameter (${b.text})
                    </label>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <select name="variable_mapping[button_var_${b.index}][source]" class="form-select form-select-sm btn-source-select" data-btn-idx="${b.index}">
                                <option value="fixed" selected>Fixed Value</option>
                                <option value="csv_column">CSV Column</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <div class="btn-fixed-box" id="btn_fixed_${b.index}">
                                <input type="text" name="variable_mapping[button_var_${b.index}][value]" class="form-control form-control-sm btn-fixed-input" placeholder="e.g. order_123 or promo_code">
                            </div>
                            <div class="btn-csv-box d-none" id="btn_csv_${b.index}">
                                <select name="variable_mapping[button_var_${b.index}][column]" class="form-select form-select-sm btn-column-select">
                                    ${buildColumnOptions()}
                                </select>
                            </div>
                        </div>
                    </div>
                `;
                btnList.appendChild(bRow);
            });
        } else {
            btnSection.classList.add('d-none');
        }

        // Attach listeners for dynamic source toggle
        document.querySelectorAll('.var-source-select').forEach(sel => {
            sel.addEventListener('change', function () {
                const idx = this.getAttribute('data-var-idx');
                const isFixed = (this.value === 'fixed');
                document.getElementById(`source_fixed_${idx}`).classList.toggle('d-none', !isFixed);
                document.getElementById(`source_csv_${idx}`).classList.toggle('d-none', isFixed);
                updateLivePreview();
            });
        });

        document.querySelectorAll('.btn-source-select').forEach(sel => {
            sel.addEventListener('change', function () {
                const idx = this.getAttribute('data-btn-idx');
                const isFixed = (this.value === 'fixed');
                document.getElementById(`btn_fixed_${idx}`).classList.toggle('d-none', !isFixed);
                document.getElementById(`btn_csv_${idx}`).classList.toggle('d-none', isFixed);
                updateLivePreview();
            });
        });

        document.querySelectorAll('.var-column-select, .var-fixed-input, .btn-fixed-input, .btn-column-select').forEach(el => {
            el.addEventListener('input', updateLivePreview);
            el.addEventListener('change', updateLivePreview);
        });
    }

    function buildColumnOptions(autoSelectIdx = null) {
        if (!uploadedHeaders.length) {
            const defaults = ['name', 'phone', 'company', 'city', 'email', 'image_url'];
            return defaults.map((col, i) => {
                const isSelected = (autoSelectIdx !== null && i === autoSelectIdx - 1) ? 'selected' : '';
                return `<option value="${col}" ${isSelected}>${col}</option>`;
            }).join('');
        }
        return uploadedHeaders.map((col, i) => {
            const isSelected = (autoSelectIdx !== null && (i === autoSelectIdx - 1 || col === 'name')) ? 'selected' : '';
            return `<option value="${col}" ${isSelected}>${col}</option>`;
        }).join('');
    }

    // Header Media Live Preview Renderer
    function renderHeaderMediaPreview() {
        const headerMediaEl = document.getElementById('previewHeaderMedia');
        const headerTextEl = document.getElementById('previewHeaderText');
        const imgEl = document.getElementById('previewHeaderImg');
        const vidEl = document.getElementById('previewHeaderVideo');
        const docEl = document.getElementById('previewHeaderDoc');
        const placeholderEl = document.getElementById('previewHeaderPlaceholder');
        const placeholderIcon = document.getElementById('previewHeaderPlaceholderIcon');
        const placeholderLabel = document.getElementById('previewHeaderLabel');

        if (!selectedTemplateData || !selectedTemplateData.header) {
            headerMediaEl.classList.add('d-none');
            headerTextEl.classList.add('d-none');
            return;
        }

        if (selectedTemplateData.header.text) {
            headerMediaEl.classList.add('d-none');
            headerTextEl.classList.remove('d-none');
            headerTextEl.textContent = selectedTemplateData.header.text;
            return;
        }

        if (!selectedTemplateData.header.is_media) {
            headerMediaEl.classList.add('d-none');
            headerTextEl.classList.add('d-none');
            return;
        }

        headerMediaEl.classList.remove('d-none');
        headerTextEl.classList.add('d-none');

        const hFormat = String(selectedTemplateData.header.format || 'IMAGE').toUpperCase();
        const mediaSource = mediaSourceSelect ? mediaSourceSelect.value : 'file';

        // Update placeholder icon and label for expected format
        if (hFormat === 'VIDEO') {
            if (placeholderIcon) placeholderIcon.className = 'bi bi-camera-video fs-1 d-block mb-1 text-primary';
            if (placeholderLabel) placeholderLabel.textContent = 'VIDEO Header (Preview)';
        } else if (hFormat === 'DOCUMENT') {
            if (placeholderIcon) placeholderIcon.className = 'bi bi-file-earmark-pdf fs-1 d-block mb-1 text-danger';
            if (placeholderLabel) placeholderLabel.textContent = 'DOCUMENT Header';
        } else {
            if (placeholderIcon) placeholderIcon.className = 'bi bi-image fs-1 d-block mb-1 text-muted';
            if (placeholderLabel) placeholderLabel.textContent = 'IMAGE Header';
        }

        function showPlaceholder(customText) {
            imgEl.classList.add('d-none');
            imgEl.src = '';
            vidEl.classList.add('d-none');
            try { vidEl.pause(); } catch (e) {}
            docEl.classList.add('d-none');
            placeholderEl.classList.remove('d-none');
            if (customText && placeholderLabel) {
                placeholderLabel.textContent = customText;
            }
        }

        if (mediaSource === 'file') {
            const file = (mediaFileInput && mediaFileInput.files && mediaFileInput.files[0]) ? mediaFileInput.files[0] : null;

            if (!file) {
                showPlaceholder();
                return;
            }

            placeholderEl.classList.add('d-none');
            const fileName = file.name || '';
            const isVideo = (hFormat === 'VIDEO') || (file.type && file.type.startsWith('video/')) || /\.(mp4|mov|avi|webm|mkv|m4v)$/i.test(fileName);
            const isDoc = (hFormat === 'DOCUMENT') || (file.type === 'application/pdf') || /\.(pdf|doc|docx|csv|xls|xlsx)$/i.test(fileName);

            if (isVideo) {
                imgEl.classList.add('d-none');
                imgEl.src = '';
                docEl.classList.add('d-none');
                vidEl.classList.remove('d-none');

                const fileKey = file.name + '_' + file.size + '_' + file.lastModified;
                if (vidEl.dataset.fileKey !== fileKey) {
                    if (currentVideoBlobUrl) {
                        try { URL.revokeObjectURL(currentVideoBlobUrl); } catch (e) {}
                    }
                    try {
                        currentVideoBlobUrl = URL.createObjectURL(file);
                        vidEl.src = currentVideoBlobUrl;
                        vidEl.dataset.fileKey = fileKey;
                        vidEl.controls = true;
                        vidEl.muted = true;
                        vidEl.playsInline = true;
                        vidEl.load();
                    } catch (e) {
                        console.error('Failed to create video preview URL:', e);
                        showPlaceholder('Video: ' + file.name);
                    }
                }
            } else if (isDoc) {
                imgEl.classList.add('d-none');
                imgEl.src = '';
                vidEl.classList.add('d-none');
                try { vidEl.pause(); } catch (e) {}
                docEl.classList.remove('d-none');
                document.getElementById('previewHeaderDocName').textContent = file.name || 'document.pdf';
            } else {
                vidEl.classList.add('d-none');
                try { vidEl.pause(); } catch (e) {}
                docEl.classList.add('d-none');
                imgEl.classList.remove('d-none');

                const fileKey = file.name + '_' + file.size + '_' + file.lastModified;
                if (imgEl.dataset.fileKey !== fileKey) {
                    if (currentImageBlobUrl) {
                        try { URL.revokeObjectURL(currentImageBlobUrl); } catch (e) {}
                    }
                    try {
                        currentImageBlobUrl = URL.createObjectURL(file);
                        imgEl.src = currentImageBlobUrl;
                        imgEl.dataset.fileKey = fileKey;
                    } catch (e) {
                        console.error('Failed to create image preview URL:', e);
                        showPlaceholder();
                    }
                }
            }
        } else if (mediaSource === 'url') {
            const url = (mediaUrlInput ? mediaUrlInput.value.trim() : '');
            if (!url) {
                showPlaceholder();
                return;
            }

            placeholderEl.classList.add('d-none');
            const isVideo = (hFormat === 'VIDEO') || /\.(mp4|mov|avi|webm|mkv|m4v)(\?.*)?$/i.test(url);
            const isDoc = (hFormat === 'DOCUMENT') || /\.(pdf|doc|docx)(\?.*)?$/i.test(url);

            if (isVideo) {
                imgEl.classList.add('d-none');
                imgEl.src = '';
                docEl.classList.add('d-none');
                vidEl.classList.remove('d-none');
                if (vidEl.src !== url) {
                    vidEl.src = url;
                    vidEl.controls = true;
                    vidEl.muted = true;
                }
            } else if (isDoc) {
                imgEl.classList.add('d-none');
                imgEl.src = '';
                vidEl.classList.add('d-none');
                try { vidEl.pause(); } catch (e) {}
                docEl.classList.remove('d-none');
                document.getElementById('previewHeaderDocName').textContent = url.split('/').pop().split('?')[0] || 'Document.pdf';
            } else {
                vidEl.classList.add('d-none');
                try { vidEl.pause(); } catch (e) {}
                docEl.classList.add('d-none');
                imgEl.classList.remove('d-none');
                imgEl.src = url;
            }
        } else if (mediaSource === 'csv_column') {
            const colName = mediaColumnSelect ? mediaColumnSelect.value : '';
            let sampleVal = null;
            if (csvData && csvData.sample_valid_recipients && csvData.sample_valid_recipients[0]) {
                const row = csvData.sample_valid_recipients[0];
                sampleVal = (row.custom_data && row.custom_data[colName]) ? row.custom_data[colName] : (row[colName] || null);
            }

            if (sampleVal && (sampleVal.startsWith('http://') || sampleVal.startsWith('https://'))) {
                placeholderEl.classList.add('d-none');
                const isVideo = (hFormat === 'VIDEO') || /\.(mp4|mov|avi|webm|mkv|m4v)(\?.*)?$/i.test(sampleVal);
                const isDoc = (hFormat === 'DOCUMENT') || /\.(pdf|doc|docx)(\?.*)?$/i.test(sampleVal);

                if (isVideo) {
                    imgEl.classList.add('d-none');
                    imgEl.src = '';
                    docEl.classList.add('d-none');
                    vidEl.classList.remove('d-none');
                    vidEl.src = sampleVal;
                    vidEl.controls = true;
                } else if (isDoc) {
                    imgEl.classList.add('d-none');
                    imgEl.src = '';
                    vidEl.classList.add('d-none');
                    try { vidEl.pause(); } catch (e) {}
                    docEl.classList.remove('d-none');
                    document.getElementById('previewHeaderDocName').textContent = sampleVal.split('/').pop().split('?')[0] || 'Document.pdf';
                } else {
                    vidEl.classList.add('d-none');
                    try { vidEl.pause(); } catch (e) {}
                    docEl.classList.add('d-none');
                    imgEl.classList.remove('d-none');
                    imgEl.src = sampleVal;
                }
            } else {
                showPlaceholder(colName ? `${hFormat} from Sheet [${colName}]` : `${hFormat} from Sheet`);
            }
        } else {
            showPlaceholder();
        }
    }

    // Live Preview Updater (Renders EXACT WhatsApp Bubble with dynamic values)
    function updateLivePreview() {
        if (!selectedTemplateData) {
            document.getElementById('previewBodyText').textContent = 'Select a template to see preview';
            return;
        }

        const bodyEl = document.getElementById('previewBodyText');
        const footerEl = document.getElementById('previewFooterText');
        const buttonsContainer = document.getElementById('previewButtonsContainer');

        // 1. Header Media or Text
        renderHeaderMediaPreview();

        // 2. Body Text (Replaces variables {{1}}, {{2}}, etc. inside template body_text)
        let rawBody = selectedTemplateData.body_text || '';

        // Get sample recipient data
        let sampleRecipient = (csvData && csvData.sample_valid_recipients && csvData.sample_valid_recipients[0]) 
            ? csvData.sample_valid_recipients[0] 
            : { name: 'Jay', phone: '919876543210', custom_data: { company: 'Peers Global', city: 'Ahmedabad' } };

        if (selectedTemplateData.body_variables && selectedTemplateData.body_variables.length) {
            selectedTemplateData.body_variables.forEach(v => {
                const varIdx = v.index;
                const tokenStr = '{' + '{' + varIdx + '}' + '}';
                let replacementHtml = '';

                const sourceSelect = document.querySelector('.var-source-select[data-var-idx="' + varIdx + '"]');
                const source = sourceSelect ? sourceSelect.value : 'csv_column';

                if (source === 'fixed') {
                    const fixedInput = document.querySelector('.var-fixed-input[data-var-idx="' + varIdx + '"]');
                    const fixedVal = fixedInput ? fixedInput.value.trim() : '';
                    if (fixedVal) {
                        replacementHtml = '<strong class="text-primary">' + escapeHtml(fixedVal) + '</strong>';
                    } else {
                        replacementHtml = '<span class="badge bg-light text-danger border border-danger">' + tokenStr + '</span>';
                    }
                } else {
                    const colSelect = document.querySelector('.var-column-select[data-var-idx="' + varIdx + '"]');
                    const col = colSelect ? colSelect.value : (varIdx === 1 ? 'name' : (varIdx === 2 ? 'phone' : 'company'));
                    
                    let sampleVal = null;
                    if (col === 'name') sampleVal = sampleRecipient.name;
                    else if (col === 'phone') sampleVal = sampleRecipient.phone;
                    else if (sampleRecipient.custom_data && sampleRecipient.custom_data[col]) sampleVal = sampleRecipient.custom_data[col];

                    if (sampleVal) {
                        replacementHtml = '<strong class="text-success">' + escapeHtml(sampleVal) + '</strong>';
                    } else {
                        replacementHtml = '<span class="badge bg-light text-muted border">[' + escapeHtml(col) + ']</span>';
                    }
                }

                // Replace all instances of token
                rawBody = rawBody.split(tokenStr).join(replacementHtml);
            });
        }

        // Convert WhatsApp formatting: *bold*, _italic_, ~strike~, clean paragraphs
        let formattedBody = formatWhatsAppText(rawBody);
        bodyEl.innerHTML = formattedBody;

        // 3. Footer Text
        if (selectedTemplateData.footer_text) {
            footerEl.classList.remove('d-none');
            footerEl.textContent = selectedTemplateData.footer_text;
        } else {
            footerEl.classList.add('d-none');
        }

        // 4. Buttons
        buttonsContainer.innerHTML = '';
        if (selectedTemplateData.buttons && selectedTemplateData.buttons.length) {
            selectedTemplateData.buttons.forEach((b, bIdx) => {
                const btnDiv = document.createElement('div');
                btnDiv.className = 'whatsapp-bubble-btn';

                let icon = 'bi-box-arrow-up-right';
                if (b.type === 'PHONE_NUMBER') icon = 'bi-telephone';
                if (b.type === 'QUICK_REPLY') icon = 'bi-reply';
                if (b.type === 'OTP' || b.type === 'COPY_CODE') icon = 'bi-clipboard';

                let btnLabel = escapeHtml(b.text);
                btnDiv.innerHTML = `<i class="bi ${icon} me-1"></i> ${btnLabel}`;
                buttonsContainer.appendChild(btnDiv);
            });
        }
    }

    function formatWhatsAppText(text) {
        if (!text) return '';
        // 1. Normalize line breaks to \n
        let norm = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');

        // 2. Clean each line of trailing spaces
        let lines = norm.split('\n');
        lines = lines.map(line => line.trimEnd());
        let cleanText = lines.join('\n');

        // 3. Split into paragraphs by 2 or more newlines
        let paragraphs = cleanText.trim().split(/\n{2,}/);

        let htmlParas = [];
        paragraphs.forEach(para => {
            let p = para.trim();
            if (!p) return;

            // WhatsApp formatting inside paragraph:
            // Monospace ```code```
            p = p.replace(/```([^`]+)```/g, '<code class="bg-light px-1 rounded font-monospace small">$1</code>');
            // Bold **text** (markdown format)
            p = p.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
            // Bold *text* (WhatsApp format)
            p = p.replace(/\*([^*\n]+)\*/g, '<strong>$1</strong>');
            // Italic _text_
            p = p.replace(/_([^_\n]+)_/g, '<em>$1</em>');
            // Strikethrough ~text~
            p = p.replace(/~([^~\n]+)~/g, '<del>$1</del>');

            // Convert remaining single newlines within this paragraph to <br>
            p = p.replace(/\n/g, '<br>');

            htmlParas.push('<div class="wa-p">' + p + '</div>');
        });

        return htmlParas.join('');
    }

    function escapeHtml(string) {
        return String(string)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Scheduling toggles
    const radioImmediate = document.getElementById('schedule_immediate');
    const radioLater = document.getElementById('schedule_later');
    const scheduledTimeInputs = document.getElementById('scheduledTimeInputs');
    const cardImmediate = document.getElementById('cardSendImmediate');
    const cardLater = document.getElementById('cardScheduleLater');

    radioImmediate.addEventListener('change', function () {
        scheduledTimeInputs.classList.add('d-none');
        cardImmediate.classList.add('active');
        cardLater.classList.remove('active');
        updateSummary();
    });

    radioLater.addEventListener('change', function () {
        scheduledTimeInputs.classList.remove('d-none');
        cardLater.classList.add('active');
        cardImmediate.classList.remove('active');
        updateSummary();
    });

    function updateSummary() {
        document.getElementById('summaryCampaignName').textContent = document.getElementById('campaign_name').value || '—';
        document.getElementById('summaryTemplateName').textContent = selectedTemplateData ? selectedTemplateData.name : '—';
        document.getElementById('summaryValidRecipients').textContent = csvData ? csvData.valid_count : (document.getElementById('import_id').value ? 'Imported' : '0');
        document.getElementById('summaryExecutionTiming').textContent = radioLater.checked ? 'Scheduled for later' : 'Immediate';
    }

    // Form submission validation & UI feedback
    const wizardForm = document.getElementById('campaignWizardForm');
    wizardForm.addEventListener('submit', function (e) {
        const name = document.getElementById('campaign_name').value.trim();
        const templateId = document.getElementById('whatsapp_template_id').value;
        const importId = document.getElementById('import_id').value.trim();

        if (!name) {
            e.preventDefault();
            alert('Please enter a Campaign Name in Step 1.');
            showStep(1);
            return false;
        }

        if (!templateId) {
            e.preventDefault();
            alert('Please select an approved WhatsApp Template in Step 1.');
            showStep(1);
            return false;
        }

        if (!importId) {
            e.preventDefault();
            alert('Please upload a valid CSV file containing recipient phone numbers in Step 2.');
            showStep(2);
            return false;
        }

        if (selectedTemplateData && selectedTemplateData.header && selectedTemplateData.header.is_media) {
            const mediaSource = mediaSourceSelect ? mediaSourceSelect.value : 'file';
            const hFormat = selectedTemplateData.header.format || 'Media';

            if (mediaSource === 'file') {
                if (!mediaFileInput.files || !mediaFileInput.files[0]) {
                    e.preventDefault();
                    alert(`This template requires a ${hFormat} header. Please choose a file in Step 3.`);
                    showStep(3);
                    return false;
                }
            } else if (mediaSource === 'url') {
                if (!mediaUrlInput.value.trim()) {
                    e.preventDefault();
                    alert(`This template requires a ${hFormat} header. Please enter a public URL in Step 3.`);
                    showStep(3);
                    return false;
                }
            }
        }

        // Disable button to prevent double-clicks and display sending state
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Dispatching Campaign...';
        return true;
    });
});
</script>
@endpush
