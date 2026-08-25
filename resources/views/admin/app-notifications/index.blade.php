@extends('admin.layouts.app')

@section('title', 'App Notifications & Navigation Screens')

@section('content')
<div class="container-fluid py-2">
    <!-- Header Title & Breadcrumb -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <div class="rounded-3 p-2 d-inline-flex align-items-center justify-content-center text-white" style="background: linear-gradient(135deg, #240e5c, #4f46e5); width: 40px; height: 40px;">
                    <i class="bi bi-phone-vibrate fs-5"></i>
                </div>
                <h4 class="mb-0 fw-bold text-dark">App Notifications &amp; Mobile Navigation</h4>
            </div>
            <p class="text-muted small mb-0">Browse all mobile app notification types, inspect deep link navigation screens &amp; payloads, and send test alerts to selected peers.</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-3 py-2 d-inline-flex align-items-center gap-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#recentLogsModal">
                <i class="bi bi-clock-history"></i>
                <span>Delivery Logs</span>
            </button>
            <button type="button" class="btn btn-outline-primary rounded-pill px-3 py-2 d-inline-flex align-items-center gap-2 shadow-sm" style="color: #240e5c; border-color: #240e5c;" data-bs-toggle="modal" data-bs-target="#createNotificationModal">
                <i class="bi bi-plus-circle-fill"></i>
                <span>Create Notification</span>
            </button>
            <button type="button" class="btn text-white rounded-pill px-4 py-2 d-inline-flex align-items-center gap-2 shadow-sm hover-elevate" style="background: linear-gradient(135deg, #240e5c, #6366f1);" onclick="openSendModal(null)">
                <i class="bi bi-send-fill"></i>
                <span>Send to Peer</span>
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill fs-5 me-2 text-success"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2 text-danger"></i>
                <div>{{ session('error') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Top Metrics Overview Ribbon -->
    <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 mb-4">
        <div class="col">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white hover-shadow transition">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold" style="font-size: 11px; letter-spacing: 0.5px;">Registered Notifications</span>
                        <h3 class="mb-0 fw-bold text-dark mt-1">{{ $stats['total_registered_types'] }}</h3>
                        <span class="text-muted" style="font-size: 11.5px;">Covering all app events</span>
                    </div>
                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: rgba(36, 14, 92, 0.08);">
                        <i class="bi bi-bell-fill fs-4" style="color: #240e5c;"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white hover-shadow transition">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold" style="font-size: 11px; letter-spacing: 0.5px;">Navigation Screens</span>
                        <h3 class="mb-0 fw-bold text-dark mt-1">{{ $stats['total_navigation_screens'] }}</h3>
                        <span class="text-muted" style="font-size: 11.5px;">Deep link screen routes</span>
                    </div>
                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: rgba(99, 102, 241, 0.1);">
                        <i class="bi bi-phone fs-4" style="color: #6366f1;"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white hover-shadow transition">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold" style="font-size: 11px; letter-spacing: 0.5px;">Push-Ready Peers</span>
                        <h3 class="mb-0 fw-bold text-success mt-1">{{ $stats['push_ready_peers'] }}</h3>
                        <span class="text-muted" style="font-size: 11.5px;">With active FCM tokens</span>
                    </div>
                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: rgba(16, 185, 129, 0.1);">
                        <i class="bi bi-broadcast-pin fs-4 text-success"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white hover-shadow transition">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small text-uppercase fw-semibold" style="font-size: 11px; letter-spacing: 0.5px;">Sent Today</span>
                        <h3 class="mb-0 fw-bold text-dark mt-1">{{ $stats['today_sent'] }}</h3>
                        <span class="text-muted" style="font-size: 11.5px;">{{ $stats['today_delivered'] }} push delivered</span>
                    </div>
                    <div class="rounded-4 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: rgba(245, 158, 11, 0.1);">
                        <i class="bi bi-send-check fs-4 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Master Batch Action Card: "Send All Notifications to Selected Peer" -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: linear-gradient(135deg, #1e1b4b, #240e5c);">
        <div class="card-body p-4 text-white">
            <div class="row align-items-center g-3">
                <div class="col-lg-8">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-warning text-dark font-monospace rounded-pill px-3 py-1 fw-bold" style="font-size: 11px;">
                            <i class="bi bi-lightning-charge-fill me-1"></i> BATCH TEST SUITE
                        </span>
                        <span class="badge bg-white bg-opacity-25 rounded-pill px-2 py-1" style="font-size: 11px;">
                            {{ count($notifications) }} notification types
                        </span>
                    </div>
                    <h5 class="fw-bold mb-1 text-white">Send All App Notifications to Selected Peer</h5>
                    <p class="text-white-50 mb-0 small" style="font-size: 13px;">
                        Select any peer/member and dispatch the complete suite of all app notification samples (with their navigation screens &amp; JSON payloads) directly to their device to test real mobile push delivery, badge counts, and deep linking routes.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <button type="button" class="btn btn-warning rounded-pill px-4 py-2 fw-bold text-dark d-inline-flex align-items-center gap-2 shadow hover-elevate" data-bs-toggle="modal" data-bs-target="#batchSendModal" onclick="openBatchSendModal()">
                        <i class="bi bi-collection-play-fill fs-5"></i>
                        <span>Select Peer &amp; Send All</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Controls -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                <div class="flex-grow-1" style="max-width: 600px;">
                    <div class="input-group input-group-merge">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="appNotificationSearch" class="form-control bg-light border-0 fs-6" placeholder="Search notification name, key, payload screen route, category..." oninput="filterNotifications()">
                        <button type="button" class="btn btn-light border-0 text-muted" id="clearNotificationSearchBtn" style="display: none;" onclick="clearNotificationSearch()">
                            <i class="bi bi-x-circle-fill"></i>
                        </button>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-secondary border rounded-pill px-3 py-2" id="notifCountBadge" style="font-size: 12px;">
                        Showing all {{ count($notifications) }} notifications
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Cards Grid: All App Notifications -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4" id="notificationsCardsGrid">
        @forelse($notifications as $tpl)
            <div class="col notification-item-card" 
                 data-name="{{ strtolower($tpl['name']) }}" 
                 data-key="{{ strtolower($tpl['key']) }}" 
                 data-screen="{{ strtolower($tpl['navigation_screen'] ?? '') }}"
                 data-category="{{ strtolower($tpl['category']) }}"
                 data-desc="{{ strtolower($tpl['description']) }}">
                <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden position-relative hover-shadow transition d-flex flex-column bg-white">
                    <!-- Top Gradient Accent -->
                    <div style="height: 4px; background: linear-gradient(90deg, #240e5c, #6366f1);"></div>

                    <div class="card-body p-4 d-flex flex-column flex-grow-1">
                        <!-- Top Badges & Icon -->
                        <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-4 d-inline-flex align-items-center justify-content-center flex-shrink-0" 
                                     style="background-color: rgba(36, 14, 92, 0.08); width: 46px; height: 46px;">
                                    <i class="{{ $tpl['icon'] }} fs-4" style="color: #240e5c;"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1 fw-bold text-dark text-truncate" title="{{ $tpl['name'] }}" style="font-size: 15px; max-width: 200px;">
                                        {{ $tpl['name'] }}
                                    </h6>
                                    <span class="badge bg-light text-secondary border rounded-pill font-monospace" style="font-size: 9.5px; padding: 2px 8px;">
                                        {{ $tpl['key'] }}
                                    </span>
                                </div>
                            </div>
                            <div class="d-flex flex-column align-items-end gap-1">
                                <span class="badge rounded-pill px-2 py-1 text-uppercase font-monospace" style="font-size: 9px; background-color: rgba(99, 102, 241, 0.12); color: #4f46e5;">
                                    {{ $tpl['category'] }}
                                </span>
                                @if(!empty($tpl['is_dynamic']))
                                    <span class="badge bg-success-subtle text-success rounded-pill font-monospace" style="font-size: 8.5px; padding: 2px 6px;">
                                        <i class="bi bi-stars"></i> Auto-Added
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Description -->
                        <p class="text-muted small mb-3" style="font-size: 12.5px; line-height: 1.45; min-height: 38px;">
                            {{ $tpl['description'] }}
                        </p>

                        <!-- Navigation Screen Badge Highlight -->
                        <div class="p-2 px-3 rounded-3 mb-3 d-flex align-items-center justify-content-between" style="background: rgba(36, 14, 92, 0.04); border: 1px dashed rgba(36, 14, 92, 0.2);">
                            <div class="d-flex align-items-center gap-2 overflow-hidden">
                                <i class="bi bi-phone text-primary fs-5" style="color: #240e5c !important;"></i>
                                <div class="overflow-hidden">
                                    <span class="text-muted d-block" style="font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.4px;">App Navigation Screen</span>
                                    <span class="font-monospace fw-bold text-dark text-truncate d-block" style="font-size: 12px;" title="{{ $tpl['navigation_screen'] }}">
                                        {{ $tpl['navigation_screen'] }}
                                    </span>
                                </div>
                            </div>
                            <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1" style="font-size: 10px;">
                                Deep Link
                            </span>
                        </div>

                        <!-- Title & Body Preview Box -->
                        <div class="bg-light p-3 rounded-3 mb-3 border">
                            <div class="small fw-bold text-dark mb-1 d-flex align-items-center gap-1" style="font-size: 12px;">
                                <i class="bi bi-chat-left-text text-muted"></i>
                                <span class="text-truncate">{{ $tpl['title'] }}</span>
                            </div>
                            <p class="text-muted small mb-0 font-monospace" style="font-size: 11.5px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                {{ $tpl['body'] }}
                            </p>
                        </div>

                        <!-- Collapsible Payload Inspector -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted small fw-semibold" style="font-size: 11px; text-transform: uppercase;">
                                    <i class="bi bi-code-slash me-1"></i> Target Action Payload (JSON)
                                </span>
                                <button type="button" class="btn btn-link btn-sm text-decoration-none p-0 text-muted" style="font-size: 11px;" onclick="copyJsonPayload('{{ $tpl['key'] }}')">
                                    <i class="bi bi-clipboard me-1"></i> Copy
                                </button>
                            </div>
                            <div class="p-2 bg-dark rounded-3 font-monospace text-light overflow-auto" style="max-height: 110px; font-size: 10.5px; line-height: 1.35;" id="payload-box-{{ $tpl['key'] }}">
                                <pre class="mb-0 text-info" style="white-space: pre-wrap;">{{ json_encode($tpl['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                        </div>

                        <!-- Dynamic Placeholders Chips -->
                        @if(!empty($tpl['dynamic_params']))
                            <div class="mb-3 mt-auto">
                                <span class="text-muted d-block mb-1" style="font-size: 10px; text-transform: uppercase;">Dynamic Params:</span>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($tpl['dynamic_params'] as $param => $desc)
                                        <span class="badge bg-white text-secondary border font-monospace" style="font-size: 10px; padding: 2px 6px;" title="{{ $desc }}">
                                            {{ $param }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Action Buttons in Footer -->
                        <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 py-1 d-inline-flex align-items-center gap-1" style="font-size: 11.5px;" onclick="openPreviewModal('{{ $tpl['key'] }}')">
                                <i class="bi bi-eye"></i> Preview
                            </button>
                            <button type="button" class="btn btn-sm text-white rounded-pill px-3 py-1 d-inline-flex align-items-center gap-1 shadow-sm" style="background-color: #240e5c; font-size: 11.5px;" onclick="openSendModal('{{ $tpl['key'] }}')">
                                <i class="bi bi-send"></i> Send to Peer
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center py-5">
                <i class="bi bi-bell-slash fs-1 text-muted"></i>
                <h5 class="mt-3 text-secondary">No App Notifications found</h5>
                <p class="text-muted small">Try adjusting your search query or category filter.</p>
            </div>
        @endforelse
    </div>
</div>

<!-- Send Notification to Peer Modal -->
<div class="modal fade" id="sendNotificationModal" tabindex="-1" aria-labelledby="sendNotificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header text-white p-3 px-4 border-0" style="background: linear-gradient(135deg, #240e5c, #4338ca);">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-send-check-fill fs-5"></i>
                    <h5 class="modal-title fw-bold" id="sendNotificationModalLabel">Send App Notification to Peer</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('admin.app-notifications.send') }}" method="POST" id="sendNotificationForm" onsubmit="submitSendNotification(event)">
                @csrf
                <input type="hidden" name="notification_key" id="modal_notification_key" value="">

                <div class="modal-body p-4">
                    <!-- Step 1: Select Peer -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark small mb-1">
                            <span class="badge rounded-circle bg-primary me-1" style="background-color: #240e5c !important;">1</span>
                            Select Target Peer / Recipient <span class="text-danger">*</span>
                        </label>

                        <div class="position-relative">
                            <div class="input-group">
                                <span class="input-group-text bg-light border"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" id="singlePeerSearchInput" class="form-control form-control-lg fs-6 py-2" placeholder="Search or click to choose peer..." autocomplete="off" onfocus="openPeerDropdownMenu('single')" oninput="filterPeerDropdownLive('single', this.value)">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePeerDropdownMenu('single')">
                                    <i class="bi bi-chevron-down" id="singleChevron"></i>
                                </button>
                            </div>

                            <!-- Floating Dropdown Menu -->
                            <div id="singlePeerDropdownMenu" class="shadow-lg border rounded-3 bg-white w-100 overflow-auto position-absolute" style="max-height: 250px; display: none; z-index: 1060; top: 100%; left: 0; margin-top: 4px;">
                                <div id="singlePeerOptionsList" class="p-1">
                                    @foreach($initialPeers as $p)
                                        <div class="peer-dropdown-item p-2 px-3 rounded-2 d-flex justify-content-between align-items-center mb-1" 
                                             style="cursor: pointer; transition: background 0.15s ease;" 
                                             onclick="selectPeerOption('single', {{ json_encode($p) }})"
                                             data-name="{{ strtolower($p['name']) }}" 
                                             data-email="{{ strtolower($p['email']) }}" 
                                             data-phone="{{ strtolower($p['phone']) }}" 
                                             data-circle="{{ strtolower($p['circle']) }}">
                                            <div class="d-flex align-items-center gap-2 overflow-hidden me-2 pointer-events-none">
                                                <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width: 34px; height: 34px; font-size: 13px; color: #240e5c; background-color: #f3f0ff !important;">
                                                    {{ strtoupper(substr($p['name'] ?: 'P', 0, 1)) }}
                                                </div>
                                                <div class="overflow-hidden">
                                                    <div class="fw-bold text-dark text-truncate" style="font-size: 13px;">{{ $p['name'] }}</div>
                                                    <div class="text-muted small text-truncate" style="font-size: 11px;">
                                                        {{ $p['email'] ?: ($p['phone'] ?: 'No Contact') }} &bull; <span class="badge bg-secondary-subtle text-secondary" style="font-size: 9.5px;">{{ $p['circle'] }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex-shrink-0">
                                                @if($p['push_ready'])
                                                    <span class="badge bg-success-subtle text-success rounded-pill px-2" style="font-size: 10px;"><i class="bi bi-check-circle-fill me-1"></i> Push Ready ({{ $p['tokens_count'] }})</span>
                                                @else
                                                    <span class="badge bg-warning-subtle text-warning rounded-pill px-2" style="font-size: 10px;"><i class="bi bi-exclamation-circle me-1"></i> In-App Only</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div id="singleNoPeersNotice" class="p-3 text-center text-muted small" style="display: none;">
                                    No matching peers found.
                                </div>
                            </div>
                        </div>

                        <!-- Hidden Input for Form Submission -->
                        <input type="hidden" name="user_ids[]" id="single_selected_user_id" value="" required>

                        <!-- Selected Peer Confirmation Card -->
                        <div id="selectedPeerContainer" class="mt-2" style="display: none;">
                            <div class="p-2 px-3 rounded-3 bg-light border d-flex align-items-center justify-content-between" style="border-left: 4px solid #240e5c !important;">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" id="selectedPeerAvatar" style="width: 38px; height: 38px; font-size: 14px; background-color: #240e5c !important;">
                                        P
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark" id="selectedPeerName" style="font-size: 13.5px;">John Doe</div>
                                        <div class="text-muted small" style="font-size: 11.5px;">
                                            <span id="selectedPeerEmail">peer@example.com</span> &bull; <span id="selectedPeerCircle" class="badge bg-secondary-subtle text-secondary">Circle</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge rounded-pill px-2 py-1" id="selectedPeerPushStatus" style="font-size: 11px;">
                                        <i class="bi bi-phone"></i> Push Ready
                                    </span>
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-circle p-1 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" onclick="clearPeerSelection('single')">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Notification Content -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark small mb-1">
                            <span class="badge rounded-circle bg-primary me-1" style="background-color: #240e5c !important;">2</span>
                            Notification Content &amp; Navigation Screen
                        </label>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label text-muted small mb-1">Notification Title</label>
                                <input type="text" name="title" id="modal_title" class="form-control rounded-3" placeholder="Enter title..." required>
                            </div>

                            <div class="col-12">
                                <label class="form-label text-muted small mb-1">Notification Body Message</label>
                                <textarea name="body" id="modal_body" class="form-control rounded-3 font-monospace" rows="3" placeholder="Enter body text..." required></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-muted small mb-1">App Navigation Screen Route</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-phone"></i></span>
                                    <input type="text" name="navigation_screen" id="modal_navigation_screen" class="form-control font-monospace" placeholder="/member-profile" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label text-muted small mb-1">Delivery Channel &amp; Priority</label>
                                <div class="input-group">
                                    <select name="channel" id="modal_channel" class="form-select">
                                        <option value="push" selected>FCM Push Alert</option>
                                        <option value="in_app_only">In-App Notification Only</option>
                                        <option value="push_email">Push + Email</option>
                                    </select>
                                    <select name="priority" id="modal_priority" class="form-select">
                                        <option value="high" selected>High</option>
                                        <option value="urgent">Urgent</option>
                                        <option value="medium">Medium</option>
                                        <option value="low">Low</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Payload JSON Inspector -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label text-muted small mb-0 fw-semibold">Action Data Payload (JSON)</label>
                            <span class="badge bg-light text-secondary border font-monospace" style="font-size: 10px;">Passed to mobile app</span>
                        </div>
                        <textarea name="payload_json" id="modal_payload_json" class="form-control font-monospace rounded-3 bg-light" rows="4" style="font-size: 12px;"></textarea>
                    </div>

                    <div id="modalSendFeedback" class="alert alert-info py-2 px-3 rounded-3 small mb-0" style="display: none;">
                        <!-- Feedback will appear here -->
                    </div>
                </div>

                <div class="modal-footer bg-light p-3 px-4 border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSubmitSend" class="btn text-white rounded-pill px-4 d-inline-flex align-items-center gap-2 shadow" style="background-color: #240e5c;">
                        <i class="bi bi-send-fill"></i>
                        <span>Dispatch Notification</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Master Batch Send All Modal -->
<div class="modal fade" id="batchSendModal" tabindex="-1" aria-labelledby="batchSendModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header text-white p-3 px-4 border-0" style="background: linear-gradient(135deg, #1e1b4b, #240e5c);">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-collection-play-fill fs-5 text-warning"></i>
                    <h5 class="modal-title fw-bold" id="batchSendModalLabel">Send All App Notifications to Selected Peer</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('admin.app-notifications.send-all-to-peer') }}" method="POST" id="batchSendForm" onsubmit="submitBatchSend(event)">
                @csrf
                <div class="modal-body p-4">
                    <div class="alert alert-warning border-0 rounded-4 p-3 mb-4 d-flex align-items-start gap-3">
                        <i class="bi bi-exclamation-triangle-fill fs-4 text-warning flex-shrink-0"></i>
                        <div class="small">
                            <strong>Note:</strong> This will dispatch all <strong>{{ count($notifications) }} notification types</strong> (welcome, posts, requirements, meetings, wallet coins, circulars, chat, brand offers, etc.) sequentially to the selected peer's device with proper navigation screens &amp; action payloads.
                        </div>
                    </div>

                    <!-- Peer Selection for Batch -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark small mb-1">
                            Choose Target Peer / User <span class="text-danger">*</span>
                        </label>

                        <div class="position-relative">
                            <div class="input-group">
                                <span class="input-group-text bg-light border"><i class="bi bi-person-search text-muted"></i></span>
                                <input type="text" id="batchPeerSearchInput" class="form-control form-control-lg fs-6 py-2" placeholder="Search or click to choose peer..." autocomplete="off" onfocus="openPeerDropdownMenu('batch')" oninput="filterPeerDropdownLive('batch', this.value)">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePeerDropdownMenu('batch')">
                                    <i class="bi bi-chevron-down" id="batchChevron"></i>
                                </button>
                            </div>

                            <!-- Floating Dropdown Menu -->
                            <div id="batchPeerDropdownMenu" class="shadow-lg border rounded-3 bg-white w-100 overflow-auto position-absolute" style="max-height: 250px; display: none; z-index: 1060; top: 100%; left: 0; margin-top: 4px;">
                                <div id="batchPeerOptionsList" class="p-1">
                                    @foreach($initialPeers as $p)
                                        <div class="peer-dropdown-item p-2 px-3 rounded-2 d-flex justify-content-between align-items-center mb-1" 
                                             style="cursor: pointer; transition: background 0.15s ease;" 
                                             onclick="selectPeerOption('batch', {{ json_encode($p) }})"
                                             data-name="{{ strtolower($p['name']) }}" 
                                             data-email="{{ strtolower($p['email']) }}" 
                                             data-phone="{{ strtolower($p['phone']) }}" 
                                             data-circle="{{ strtolower($p['circle']) }}">
                                            <div class="d-flex align-items-center gap-2 overflow-hidden me-2 pointer-events-none">
                                                <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width: 34px; height: 34px; font-size: 13px; color: #240e5c; background-color: #f3f0ff !important;">
                                                    {{ strtoupper(substr($p['name'] ?: 'P', 0, 1)) }}
                                                </div>
                                                <div class="overflow-hidden">
                                                    <div class="fw-bold text-dark text-truncate" style="font-size: 13px;">{{ $p['name'] }}</div>
                                                    <div class="text-muted small text-truncate" style="font-size: 11px;">
                                                        {{ $p['email'] ?: ($p['phone'] ?: 'No Contact') }} &bull; <span class="badge bg-secondary-subtle text-secondary" style="font-size: 9.5px;">{{ $p['circle'] }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="flex-shrink-0">
                                                @if($p['push_ready'])
                                                    <span class="badge bg-success-subtle text-success rounded-pill px-2" style="font-size: 10px;"><i class="bi bi-check-circle-fill me-1"></i> Push Ready ({{ $p['tokens_count'] }})</span>
                                                @else
                                                    <span class="badge bg-warning-subtle text-warning rounded-pill px-2" style="font-size: 10px;"><i class="bi bi-exclamation-circle me-1"></i> In-App Only</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div id="batchNoPeersNotice" class="p-3 text-center text-muted small" style="display: none;">
                                    No matching peers found.
                                </div>
                            </div>
                        </div>

                        <!-- Hidden Input for Form Submission -->
                        <input type="hidden" name="user_id" id="batch_selected_user_id" value="" required>

                        <!-- Selected Peer Box in Batch Modal -->
                        <div id="batchSelectedPeerContainer" class="mt-2" style="display: none;">
                            <div class="p-3 rounded-4 bg-light border d-flex align-items-center justify-content-between" style="border-left: 4px solid #f59e0b !important;">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" id="batchSelectedPeerAvatar" style="width: 44px; height: 44px; font-size: 16px; background-color: #240e5c !important;">
                                        P
                                    </div>
                                    <div>
                                        <div class="fw-bold text-dark fs-6" id="batchSelectedPeerName">Selected Peer</div>
                                        <div class="text-muted small">
                                            <span id="batchSelectedPeerEmail">email@example.com</span> &bull; <span id="batchSelectedPeerCircle">Circle</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge rounded-pill px-3 py-2" id="batchSelectedPeerPushStatus">
                                        Push Ready
                                    </span>
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-circle p-1 d-flex align-items-center justify-content-center" style="width: 26px; height: 26px;" onclick="clearPeerSelection('batch')">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="batchSendFeedback" class="alert alert-info py-2 px-3 rounded-3 small mb-0" style="display: none;"></div>
                </div>

                <div class="modal-footer bg-light p-3 px-4 border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSubmitBatchSend" class="btn btn-warning rounded-pill px-4 fw-bold text-dark d-inline-flex align-items-center gap-2 shadow">
                        <i class="bi bi-rocket-takeoff-fill"></i>
                        <span>Start Batch Push ({{ count($notifications) }} Notifications)</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Live Mobile Mockup Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
        <div class="modal-content border-0 shadow-lg rounded-5 overflow-hidden" style="background: #111;">
            <div class="modal-header border-0 p-3 pb-0 text-white d-flex justify-content-between align-items-center">
                <span class="small text-white-50 font-monospace"><i class="bi bi-phone me-1"></i> Mobile Screen Mockup</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 d-flex justify-content-center">
                <!-- Phone Mockup Frame -->
                <div class="phone-frame position-relative shadow-lg" style="width: 320px; height: 560px; background: linear-gradient(135deg, #1e1b4b, #311042); border-radius: 40px; padding: 16px; border: 4px solid #333; overflow: hidden;">
                    <!-- Status Bar -->
                    <div class="d-flex justify-content-between text-white small px-2 py-1 mb-4" style="font-size: 10px; opacity: 0.85;">
                        <span>9:41 AM</span>
                        <div class="d-flex gap-1 align-items-center">
                            <i class="bi bi-reception-4"></i>
                            <i class="bi bi-wifi"></i>
                            <i class="bi bi-battery-full fs-6"></i>
                        </div>
                    </div>

                    <!-- Push Banner Card -->
                    <div class="card border-0 shadow-lg rounded-4 p-3 mb-3" style="background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px);">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="rounded-3 d-inline-flex align-items-center justify-content-center flex-shrink-0" style="background-color: #240e5c; width: 22px; height: 22px;">
                                <i class="bi bi-bell-fill text-white" style="font-size: 11px;"></i>
                            </div>
                            <span class="fw-bold text-dark" style="font-size: 11px; letter-spacing: 0.3px;">PEERS GLOBAL</span>
                            <span class="text-muted ms-auto" style="font-size: 10px;">now</span>
                        </div>
                        <h6 class="fw-bold text-dark mb-1" id="mockupTitle" style="font-size: 12.5px; line-height: 1.3;">Notification Title</h6>
                        <p class="text-secondary mb-0" id="mockupBody" style="font-size: 12px; line-height: 1.4;">Notification body preview message text.</p>
                    </div>

                    <!-- Target Screen Route Pill in Mockup -->
                    <div class="text-center mt-4">
                        <span class="badge bg-white bg-opacity-10 text-white border border-white border-opacity-25 rounded-pill px-3 py-1 font-monospace" style="font-size: 11px;" id="mockupScreen">
                            <i class="bi bi-arrow-right-circle me-1"></i> /dashboard
                        </span>
                        <div class="text-white-50 small mt-2" style="font-size: 11px;">Tapping navigates to this screen</div>
                    </div>

                    <!-- Bottom Bar -->
                    <div class="position-absolute bottom-0 start-50 translate-middle-x mb-3 text-center w-100">
                        <div class="mx-auto" style="width: 110px; height: 4px; background: #fff; border-radius: 2px; opacity: 0.6;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delivery Logs Modal -->
<div class="modal fade" id="recentLogsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header text-white p-3 px-4 border-0" style="background: linear-gradient(135deg, #240e5c, #4338ca);">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history fs-5"></i>
                    <h5 class="modal-title fw-bold">Recent App Notification Delivery Logs</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr class="text-secondary small text-uppercase">
                                <th>Recipient Peer</th>
                                <th>Channel</th>
                                <th>Navigation Screen / Type</th>
                                <th>Status</th>
                                <th>Attempted At</th>
                                <th class="text-end">Payload</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentLogs as $log)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 12px;">
                                                {{ strtoupper(substr($log->user?->name ?? 'P', 0, 1)) }}
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark">{{ $log->user?->name ?? 'User' }}</div>
                                                <div class="text-muted small" style="font-size: 11px;">{{ $log->user?->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border rounded-pill">{{ strtoupper($log->channel) }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary font-monospace" style="font-size: 11px;">
                                            {{ $log->notification?->screen ?? ($log->request_payload['data']['navigation_screen'] ?? '/dashboard') }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($log->status === 'sent')
                                            <span class="badge bg-success-subtle text-success rounded-pill px-2 py-1"><i class="bi bi-check-circle me-1"></i> Delivered</span>
                                        @elseif($log->status === 'failed')
                                            <span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-1" title="{{ $log->error_message }}"><i class="bi bi-x-circle me-1"></i> Failed</span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning rounded-pill px-2 py-1">{{ $log->status }}</span>
                                        @endif
                                    </td>
                                    <td class="text-muted small">
                                        {{ $log->attempted_at ? $log->attempted_at->diffForHumans() : '-' }}
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-2" style="font-size: 11px;" onclick="alert(JSON.stringify(@json($log->request_payload), null, 2))">
                                            Inspect
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                     <td colspan="6" class="text-center text-muted py-4">No delivery logs recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create New App Notification Modal -->
<div class="modal fade" id="createNotificationModal" tabindex="-1" aria-labelledby="createNotificationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header text-white p-3 px-4 border-0" style="background: linear-gradient(135deg, #240e5c, #6366f1);">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-plus-circle-fill fs-5"></i>
                    <h5 class="modal-title fw-bold" id="createNotificationModalLabel">Create &amp; Register New App Notification</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('admin.app-notifications.store') }}" method="POST" id="createNotificationForm" onsubmit="submitCreateNotification(event)">
                @csrf
                <div class="modal-body p-4">
                    <div class="alert alert-info rounded-3 py-2 px-3 small mb-3">
                        <i class="bi bi-info-circle me-1"></i> Any new notification created here is <strong>automatically registered</strong>, appears immediately in the App Notifications list, and can be tested on peer devices.
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-dark small fw-bold mb-1">Notification Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="new_notif_name" class="form-control rounded-3" placeholder="e.g. Milestone Achieved" required oninput="autoGenerateKey(this.value)">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-dark small fw-bold mb-1">Unique Event Key <span class="text-danger">*</span></label>
                            <input type="text" name="template_key" id="new_notif_key" class="form-control font-monospace rounded-3" placeholder="milestone_achieved" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-dark small fw-bold mb-1">Category <span class="text-danger">*</span></label>
                            <select name="category" id="new_notif_category" class="form-select rounded-3">
                                @foreach($categories as $cat)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                @endforeach
                                <option value="Custom & Dynamic">Custom &amp; Dynamic</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label text-dark small fw-bold mb-1">App Navigation Screen Route <span class="text-danger">*</span></label>
                            <input type="text" name="navigation_screen" id="new_notif_screen" class="form-control font-monospace rounded-3" list="navigationScreenDatalist" placeholder="/member-profile" required>
                            <datalist id="navigationScreenDatalist">
                                @foreach($navigationScreens as $route => $screenData)
                                    <option value="{{ $route }}">{{ $screenData['label'] }}</option>
                                @endforeach
                            </datalist>
                        </div>

                        <div class="col-12">
                            <label class="form-label text-dark small fw-bold mb-1">Title Template <span class="text-danger">*</span></label>
                            <input type="text" name="title_template" id="new_notif_title" class="form-control rounded-3" placeholder="Congratulations {name}!" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label text-dark small fw-bold mb-1">Body Message Template <span class="text-danger">*</span></label>
                            <textarea name="body_template" id="new_notif_body" class="form-control rounded-3 font-monospace" rows="3" placeholder="You have unlocked a new milestone on Peers Global." required></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label text-dark small fw-bold mb-1">Default Action Payload (JSON)</label>
                            <textarea name="default_payload" id="new_notif_payload" class="form-control font-monospace rounded-3 bg-light" rows="3" placeholder='{"screen": "/dashboard", "custom_id": "123"}'></textarea>
                        </div>
                    </div>

                    <div id="createNotifFeedback" class="alert alert-info py-2 px-3 rounded-3 small mt-3 mb-0" style="display: none;"></div>
                </div>

                <div class="modal-footer bg-light p-3 px-4 border-top d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSubmitCreate" class="btn text-white rounded-pill px-4 d-inline-flex align-items-center gap-2 shadow" style="background-color: #240e5c;">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>Save &amp; Add to App Notifications</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function autoGenerateKey(val) {
        if (!val) return;
        const keyInput = document.getElementById('new_notif_key');
        if (!keyInput.dataset.manual) {
            keyInput.value = val.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
        }
    }

    document.getElementById('new_notif_key')?.addEventListener('input', function() {
        this.dataset.manual = 'true';
    });

    function submitCreateNotification(e) {
        e.preventDefault();
        const btn = document.getElementById('btnSubmitCreate');
        const feedback = document.getElementById('createNotifFeedback');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Saving...';
        feedback.style.display = 'none';

        const formData = new FormData(document.getElementById('createNotificationForm'));

        fetch(`{{ route('admin.app-notifications.store') }}`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Save &amp; Add to App Notifications';

            if (data.success) {
                feedback.className = 'alert alert-success py-2 px-3 rounded-3 small mt-3 mb-0';
                feedback.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> ${data.message}`;
                feedback.style.display = 'block';
                setTimeout(() => {
                    bootstrap.Modal.getInstance(document.getElementById('createNotificationModal')).hide();
                    location.reload();
                }, 1200);
            } else {
                feedback.className = 'alert alert-danger py-2 px-3 rounded-3 small mt-3 mb-0';
                feedback.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i> ${data.message || 'Creation failed'}`;
                feedback.style.display = 'block';
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Save &amp; Add to App Notifications';
            alert('An error occurred while creating notification.');
        });
    }

    // Initial catalog data passed from Blade
    const notificationCatalog = @json($notifications);

    // Instant Notification Cards Search Filter (No category pills needed)
    function filterNotifications() {
        const input = document.getElementById('appNotificationSearch');
        const query = (input ? input.value : '').toLowerCase().trim();
        const clearBtn = document.getElementById('clearNotificationSearchBtn');
        const countBadge = document.getElementById('notifCountBadge');
        
        if (clearBtn) {
            clearBtn.style.display = query ? 'inline-block' : 'none';
        }

        const cards = document.getElementsByClassName('notification-item-card');
        let visibleCount = 0;

        for (let i = 0; i < cards.length; i++) {
            const card = cards[i];
            const name = (card.getAttribute('data-name') || '').toLowerCase();
            const key = (card.getAttribute('data-key') || '').toLowerCase();
            const screen = (card.getAttribute('data-screen') || '').toLowerCase();
            const desc = (card.getAttribute('data-desc') || '').toLowerCase();
            const category = (card.getAttribute('data-category') || '').toLowerCase();

            const matches = query === '' 
                || name.includes(query) 
                || key.includes(query) 
                || screen.includes(query) 
                || desc.includes(query) 
                || category.includes(query);

            if (matches) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        }

        if (countBadge) {
            countBadge.innerText = query ? `Showing ${visibleCount} of ${cards.length} notifications` : `Showing all ${cards.length} notifications`;
        }
    }

    function clearNotificationSearch() {
        const input = document.getElementById('appNotificationSearch');
        if (input) {
            input.value = '';
        }
        filterNotifications();
    }

    function copyJsonPayload(key) {
        const tpl = notificationCatalog.find(n => n.key === key);
        if (tpl && tpl.payload) {
            navigator.clipboard.writeText(JSON.stringify(tpl.payload, null, 2));
            alert('Target Action Payload copied to clipboard!');
        }
    }

    // Open Live Preview Modal
    function openPreviewModal(key) {
        const tpl = notificationCatalog.find(n => n.key === key);
        if (!tpl) return;

        document.getElementById('mockupTitle').innerText = tpl.title;
        document.getElementById('mockupBody').innerText = tpl.body;
        document.getElementById('mockupScreen').innerText = tpl.navigation_screen || '/dashboard';

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('previewModal'));
        modal.show();
    }

    // Floating Peer Dropdown Menu Handlers
    function openPeerDropdownMenu(type) {
        const menu = document.getElementById(`${type}PeerDropdownMenu`);
        if (menu) menu.style.display = 'block';
    }

    function togglePeerDropdownMenu(type) {
        const menu = document.getElementById(`${type}PeerDropdownMenu`);
        if (menu) {
            menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
        }
    }

    function filterPeerDropdownLive(type, query) {
        const q = (query || '').toLowerCase().trim();
        const menu = document.getElementById(`${type}PeerDropdownMenu`);
        if (menu) menu.style.display = 'block';

        const listContainer = document.getElementById(`${type}PeerOptionsList`);
        const noNotice = document.getElementById(`${type}NoPeersNotice`);
        if (!listContainer) return;

        const items = listContainer.getElementsByClassName('peer-dropdown-item');
        let matchCount = 0;

        for (let i = 0; i < items.length; i++) {
            const item = items[i];
            const name = (item.getAttribute('data-name') || '').toLowerCase();
            const email = (item.getAttribute('data-email') || '').toLowerCase();
            const phone = (item.getAttribute('data-phone') || '').toLowerCase();
            const circle = (item.getAttribute('data-circle') || '').toLowerCase();

            if (q === '' || name.includes(q) || email.includes(q) || phone.includes(q) || circle.includes(q)) {
                item.style.display = 'flex';
                matchCount++;
            } else {
                item.style.display = 'none';
            }
        }

        if (noNotice) {
            noNotice.style.display = (matchCount === 0) ? 'block' : 'none';
        }
    }

    function selectPeerOption(type, peer) {
        // Set hidden input
        const hiddenInputId = (type === 'single') ? 'single_selected_user_id' : 'batch_selected_user_id';
        const hiddenInput = document.getElementById(hiddenInputId);
        if (hiddenInput) hiddenInput.value = peer.id;

        // Set search input
        const searchInput = document.getElementById(`${type}PeerSearchInput`);
        if (searchInput) searchInput.value = peer.name;

        // Populate card
        const nameEl = document.getElementById(type === 'single' ? 'selectedPeerName' : 'batchSelectedPeerName');
        const emailEl = document.getElementById(type === 'single' ? 'selectedPeerEmail' : 'batchSelectedPeerEmail');
        const circleEl = document.getElementById(type === 'single' ? 'selectedPeerCircle' : 'batchSelectedPeerCircle');
        const avatarEl = document.getElementById(type === 'single' ? 'selectedPeerAvatar' : 'batchSelectedPeerAvatar');
        const pushStatus = document.getElementById(type === 'single' ? 'selectedPeerPushStatus' : 'batchSelectedPeerPushStatus');
        const container = document.getElementById(type === 'single' ? 'selectedPeerContainer' : 'batchSelectedPeerContainer');

        if (nameEl) nameEl.innerText = peer.name;
        if (emailEl) emailEl.innerText = peer.email || peer.phone || 'No Contact';
        if (circleEl) circleEl.innerText = peer.circle || 'General';
        if (avatarEl) avatarEl.innerText = (peer.name || 'P').charAt(0).toUpperCase();

        if (pushStatus) {
            if (peer.push_ready) {
                pushStatus.className = 'badge bg-success text-white rounded-pill px-3 py-1';
                pushStatus.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> Push Ready (${peer.tokens_count} Tokens)`;
            } else {
                pushStatus.className = 'badge bg-warning text-dark rounded-pill px-3 py-1';
                pushStatus.innerHTML = `<i class="bi bi-exclamation-circle me-1"></i> In-App Only`;
            }
        }

        if (container) container.style.display = 'block';

        // Close dropdown
        const menu = document.getElementById(`${type}PeerDropdownMenu`);
        if (menu) menu.style.display = 'none';
    }

    function clearPeerSelection(type) {
        const hiddenInputId = (type === 'single') ? 'single_selected_user_id' : 'batch_selected_user_id';
        const hiddenInput = document.getElementById(hiddenInputId);
        if (hiddenInput) hiddenInput.value = '';

        const searchInput = document.getElementById(`${type}PeerSearchInput`);
        if (searchInput) {
            searchInput.value = '';
            filterPeerDropdownLive(type, '');
        }

        const container = document.getElementById(type === 'single' ? 'selectedPeerContainer' : 'batchSelectedPeerContainer');
        if (container) container.style.display = 'none';
    }

    // Close floating peer dropdown menus when clicking outside
    document.addEventListener('click', (e) => {
        const singleContainer = document.getElementById('singlePeerDropdownMenu');
        const singleInput = document.getElementById('singlePeerSearchInput');
        const batchContainer = document.getElementById('batchPeerDropdownMenu');
        const batchInput = document.getElementById('batchPeerSearchInput');

        if (singleContainer && !singleContainer.contains(e.target) && !e.target.closest('#singlePeerSearchInput') && !e.target.closest('button[onclick*="single"]')) {
            singleContainer.style.display = 'none';
        }
        if (batchContainer && !batchContainer.contains(e.target) && !e.target.closest('#batchPeerSearchInput') && !e.target.closest('button[onclick*="batch"]')) {
            batchContainer.style.display = 'none';
        }
    });

    window.openPeerDropdownMenu = openPeerDropdownMenu;
    window.togglePeerDropdownMenu = togglePeerDropdownMenu;
    window.filterPeerDropdownLive = filterPeerDropdownLive;
    window.selectPeerOption = selectPeerOption;
    window.clearPeerSelection = clearPeerSelection;
    window.filterNotifications = filterNotifications;
    window.clearNotificationSearch = clearNotificationSearch;

    // Open Send Modal prefilled
    function openSendModal(key) {
        const tpl = key ? notificationCatalog.find(n => n.key === key) : notificationCatalog[0];

        if (tpl) {
            document.getElementById('modal_notification_key').value = tpl.key;
            document.getElementById('modal_title').value = tpl.title;
            document.getElementById('modal_body').value = tpl.body;
            document.getElementById('modal_navigation_screen').value = tpl.navigation_screen || '/dashboard';
            document.getElementById('modal_payload_json').value = JSON.stringify(tpl.payload, null, 2);
        }

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('sendNotificationModal'));
        modal.show();
    }

    // Open Batch Send Modal
    function openBatchSendModal() {
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('batchSendModal'));
        modal.show();
    }

    window.openSendModal = openSendModal;
    window.openBatchSendModal = openBatchSendModal;

    // Submit single send with real-time feedback
    function submitSendNotification(e) {
        e.preventDefault();
        const userId = document.getElementById('single_selected_user_id')?.value;
        if (!userId) {
            alert('Please select a recipient peer first.');
            return;
        }

        const btn = document.getElementById('btnSubmitSend');
        const feedback = document.getElementById('modalSendFeedback');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Dispatching...';
        feedback.style.display = 'none';

        const formData = new FormData(document.getElementById('sendNotificationForm'));

        fetch(`{{ route('admin.app-notifications.send') }}`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send-fill me-1"></i> Dispatch Notification';

            if (data.success) {
                feedback.className = 'alert alert-success py-2 px-3 rounded-3 small mb-0';
                feedback.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> ${data.message}`;
                feedback.style.display = 'block';
                setTimeout(() => {
                    bootstrap.Modal.getInstance(document.getElementById('sendNotificationModal')).hide();
                    location.reload();
                }, 1400);
            } else {
                feedback.className = 'alert alert-danger py-2 px-3 rounded-3 small mb-0';
                feedback.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i> ${data.message || 'Delivery failed'}`;
                feedback.style.display = 'block';
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send-fill me-1"></i> Dispatch Notification';
            alert('An error occurred while sending notification.');
        });
    }

    // Submit batch send with real-time feedback
    function submitBatchSend(e) {
        e.preventDefault();
        const userId = document.getElementById('batch_selected_user_id')?.value;
        if (!userId) {
            alert('Please select a target peer for the batch test.');
            return;
        }

        const btn = document.getElementById('btnSubmitBatchSend');
        const feedback = document.getElementById('batchSendFeedback');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Sending All Notifications...';
        feedback.style.display = 'none';

        const formData = new FormData(document.getElementById('batchSendForm'));

        fetch(`{{ route('admin.app-notifications.send-all-to-peer') }}`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-rocket-takeoff-fill me-1"></i> Start Batch Push';

            if (data.success) {
                feedback.className = 'alert alert-success py-3 px-3 rounded-4 small mb-0';
                feedback.innerHTML = `
                    <div class="fw-bold mb-1"><i class="bi bi-check-circle-fill me-1"></i> ${data.message}</div>
                    <div class="text-muted">Total notifications created: ${data.total_sent} | Push Delivered: ${data.push_delivered_count}</div>
                `;
                feedback.style.display = 'block';
                setTimeout(() => {
                    bootstrap.Modal.getInstance(document.getElementById('batchSendModal')).hide();
                    location.reload();
                }, 2000);
            } else {
                feedback.className = 'alert alert-danger py-2 px-3 rounded-3 small mb-0';
                feedback.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i> ${data.message || 'Batch send failed'}`;
                feedback.style.display = 'block';
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-rocket-takeoff-fill me-1"></i> Start Batch Push';
            alert('An error occurred during batch notification push.');
        });
    }

        const btn = document.getElementById('btnSubmitBatchSend');
        const feedback = document.getElementById('batchSendFeedback');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span> Sending All Notifications...';
        feedback.style.display = 'none';

        const formData = new FormData(document.getElementById('batchSendForm'));

        fetch(`{{ route('admin.app-notifications.send-all-to-peer') }}`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-rocket-takeoff-fill me-1"></i> Start Batch Push';

            if (data.success) {
                feedback.className = 'alert alert-success py-3 px-3 rounded-4 small mb-0';
                feedback.innerHTML = `
                    <div class="fw-bold mb-1"><i class="bi bi-check-circle-fill me-1"></i> ${data.message}</div>
                    <div class="text-muted">Total notifications created: ${data.total_sent} | Push Delivered: ${data.push_delivered_count}</div>
                `;
                feedback.style.display = 'block';
                setTimeout(() => {
                    bootstrap.Modal.getInstance(document.getElementById('batchSendModal')).hide();
                    location.reload();
                }, 2000);
            } else {
                feedback.className = 'alert alert-danger py-2 px-3 rounded-3 small mb-0';
                feedback.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i> ${data.message || 'Batch send failed'}`;
                feedback.style.display = 'block';
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-rocket-takeoff-fill me-1"></i> Start Batch Push';
            alert('An error occurred during batch notification push.');
        });
    }
</script>
@endpush

@push('styles')
<style>
    .peer-dropdown-item {
        transition: background-color 0.15s ease;
    }
    .peer-dropdown-item:hover {
        background-color: #f3f0ff !important;
    }
    .hover-shadow {
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .hover-shadow:hover {
        transform: translateY(-4px);
        box-shadow: 0 0.85rem 2rem rgba(36, 14, 92, 0.1) !important;
    }
    .hover-elevate {
        transition: all 0.2s ease;
    }
    .hover-elevate:hover {
        transform: scale(1.02);
    }
    .transition {
        transition: all 0.25s ease;
    }
</style>
@endpush
@endsection
