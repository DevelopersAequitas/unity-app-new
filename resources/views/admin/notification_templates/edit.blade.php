@extends('admin.layouts.app')

@section('title', 'Edit Notification Template')

@section('content')
<div class="container-fluid">
    <!-- Back Navigation & Title -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="{{ route('admin.notification-templates.index') }}" class="btn btn-outline-secondary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 40px; height: 40px; padding: 0;">
            <i class="bi bi-arrow-left fs-5"></i>
        </a>
        <div>
            <h4 class="mb-1 fw-bold text-dark">Edit Notification Template</h4>
            <p class="text-muted small mb-0">{{ $template['name'] }} &raquo; Configure Title, Body, and Payload</p>
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
        <!-- Edit Form Pane (Left) -->
        <div class="col-lg-7 mb-4">
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-3">
                    <h5 class="card-title fw-bold text-dark mb-0" style="font-size: 15px;">Notification Content Editor</h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('admin.notification-templates.update', $template['key']) }}" method="POST" id="notificationForm">
                        @csrf
                        @method('PUT')

                        <!-- Notification Title Input -->
                        <div class="mb-4">
                            <label for="title_template" class="form-label fw-bold text-dark small">Notification Title</label>
                            <input type="text" name="title_template" id="title_template" class="form-control rounded-3" 
                                   value="{{ old('title_template', $dbTemplate->title_template ?? $template['default_title']) }}" 
                                   placeholder="Enter notification title..." oninput="updateLivePreview()">
                        </div>

                        <!-- Notification Body Textarea -->
                        <div class="mb-4">
                            <label for="body_template" class="form-label fw-bold text-dark small">Notification Body Text</label>
                            <div class="alert alert-info py-2 px-3 mb-2 rounded-3" style="font-size: 12.5px;">
                                <i class="bi bi-info-circle me-1"></i> You can use dynamic parameters from the reference guide on the right side.
                            </div>
                            <textarea name="body_template" id="body_template" class="form-control rounded-3 font-monospace" rows="6" 
                                      style="font-size: 14px; line-height: 1.6;" placeholder="Enter notification body message..." oninput="updateLivePreview()">{{ old('body_template', $dbTemplate->body_template ?? $template['default_body']) }}</textarea>
                        </div>

                        <!-- Payload (Static Display) -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark small mb-1">Target Action Payload</label>
                            <div class="p-3 bg-light border rounded-3 font-monospace text-secondary" style="font-size: 12.5px;">
                                <pre class="mb-0" style="white-space: pre-wrap;">{{ json_encode($dbTemplate->default_payload ?? $template['default_payload'], JSON_PRETTY_PRINT) }}</pre>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary px-4 py-2 rounded-3" style="background-color: #240e5c; border-color: #240e5c;">
                                <i class="bi bi-save me-1"></i> Save Notification Template
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Side: Variables & Mobile Mockup -->
        <div class="col-lg-5 mb-4">
            <!-- Dynamic Params Guide -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-bottom p-3">
                    <h5 class="card-title fw-bold text-dark mb-0" style="font-size: 15px;">Dynamic Parameter Reference</h5>
                </div>
                <div class="card-body p-4">
                    <div class="alert alert-warning py-2 px-3 rounded-3 mb-3" style="font-size: 12.5px;">
                        <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
                        <strong>Important:</strong> Placeholders inside curly brackets (like <code>{name}</code>) will be replaced dynamically from database.
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
                                            <span class="badge bg-danger-subtle text-danger font-monospace border border-danger-subtle" style="font-size: 12px; cursor: pointer;" onclick="insertAtCursor('{{ $param }}')" title="Click to insert">
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

            <!-- Mobile Phone Mockup Preview -->
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title fw-bold text-dark mb-0" style="font-size: 15px;">Live Mobile Mockup Preview</h5>
                    <span class="badge bg-success rounded-pill px-2 py-1" style="font-size: 10px;">Push Banner</span>
                </div>
                <div class="card-body p-4 bg-light d-flex justify-content-center">
                    
                    <!-- Phone Container -->
                    <div class="phone-mockup shadow-lg position-relative" style="width: 300px; height: 580px; background: #000; border-radius: 40px; padding: 12px; border: 4px solid #333;">
                        <!-- Speaker & Notch -->
                        <div class="position-absolute top-0 start-50 translate-middle-x" style="width: 120px; height: 20px; background: #000; border-bottom-left-radius: 15px; border-bottom-right-radius: 15px; z-index: 10;"></div>
                        
                        <!-- Phone Screen -->
                        <div class="w-100 h-100 rounded-4 overflow-hidden position-relative" style="background: linear-gradient(135deg, #1e1b4b, #311042); background-size: cover; padding: 28px 12px 12px 12px;">
                            
                            <!-- iOS / Android Style Status Bar -->
                            <div class="position-absolute top-0 start-0 w-100 px-3 py-1 d-flex justify-content-between text-white small" style="font-size: 10.5px; opacity: 0.85; z-index: 9;">
                                <span>9:41 AM</span>
                                <div class="d-flex gap-1 align-items-center">
                                    <i class="bi bi-reception-4"></i>
                                    <i class="bi bi-wifi"></i>
                                    <i class="bi bi-battery-full fs-6"></i>
                                </div>
                            </div>

                            <!-- Push Notification Banner Card -->
                            <div class="card border-0 shadow-lg rounded-3 mb-3 animate-fade-in" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); z-index: 8;">
                                <div class="card-body p-3">
                                    <!-- App Header Info -->
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <div class="rounded-3 d-inline-flex align-items-center justify-content-center flex-shrink-0" style="background-color: #240e5c; width: 22px; height: 22px;">
                                            <img src="https://peersunity.com/images/peersglobal-logo.png" alt="Logo" style="width: 14px; height: auto;">
                                        </div>
                                        <span class="fw-bold text-dark" style="font-size: 11.5px; letter-spacing: 0.2px;">PEERS GLOBAL</span>
                                        <span class="text-muted ms-auto" style="font-size: 10px;">now</span>
                                    </div>
                                    
                                    <!-- Notification Title -->
                                    <h6 class="fw-bold text-dark mb-1" id="live-preview-title" style="font-size: 12.5px; line-height: 1.3;">
                                        Welcome to Peers Global!
                                    </h6>
                                    
                                    <!-- Notification Body -->
                                    <p class="text-secondary mb-0" id="live-preview-body" style="font-size: 12px; line-height: 1.45;">
                                        Your account is now active. Explore circles and start networking with peers!
                                    </p>
                                </div>
                            </div>

                            <!-- Phone Lockscreen Helper Info -->
                            <div class="position-absolute bottom-0 start-50 translate-middle-x mb-4 text-center w-100 text-white-50" style="font-size: 11px;">
                                <i class="bi bi-lock-fill"></i> Swipe up to open
                                <div class="mx-auto mt-2" style="width: 100px; height: 4px; background: #fff; border-radius: 2px; opacity: 0.6;"></div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const mockValues = {
        '{name}': 'John Doe',
        '{otp}': '692018',
        '{actorName}': 'John Doe',
        '{meetingDate}': '2026-08-15 14:30',
        '{meetingPlace}': 'Conference Room B / Zoom',
        '{coins}': '150',
        '{activityName}': 'Business Referral',
        '{ticketNumber}': 'SUP-2026-904',
        '{referredName}': 'Jane Smith',
        '{circleName}': 'Fintech Leaders Circle',
        '{storyTitle}': 'How I Scaled My Startup',
        '{amount}': '5,00,000',
        '{creatorName}': 'John Doe',
        '{title}': 'Looking for Laravel Developer partner',
        '{circularTitle}': 'Independence Day Holiday Announcement',
        '{circularSummary}': 'Please note that the office will remain closed on August 15th.',
        '{pendingCount}': '5',
        '{senderName}': 'John Doe',
        '{partnerName}': 'Greenpreneur Network',
        '{offerTitle}': '20% off on Green Consultations',
        '{days}': '7',
        '{action}': 'Planting 100 trees in urban area',
        '{status}': 'approved'
    };

    function replacePlaceholders(text) {
        let rendered = text;
        for (const [key, val] of Object.entries(mockValues)) {
            rendered = rendered.split(key).join(val);
        }
        return rendered;
    }

    function updateLivePreview() {
        const titleInput = document.getElementById('title_template').value;
        const bodyInput = document.getElementById('body_template').value;

        document.getElementById('live-preview-title').innerText = replacePlaceholders(titleInput) || 'Notification Title';
        document.getElementById('live-preview-body').innerText = replacePlaceholders(bodyInput) || 'Notification Body Message';
    }

    function insertAtCursor(tag) {
        const textarea = document.getElementById('body_template');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        
        textarea.value = text.substring(0, start) + tag + text.substring(end);
        textarea.focus();
        textarea.selectionStart = textarea.selectionEnd = start + tag.length;
        
        updateLivePreview();
    }

    // Initialize preview on page load
    window.addEventListener('DOMContentLoaded', () => {
        updateLivePreview();
    });
</script>

<style>
    .animate-fade-in {
        animation: slideDown 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
</style>
@endsection
