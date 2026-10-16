@extends('admin.layouts.app')

@section('title', 'WhatsApp Live Chat & Inbox - Peers Unity')

@push('styles')
<style>
    /* Authentic WhatsApp Web Design Tokens & Component Styles */
    :root {
        --wa-bg-light: #efeae2;
        --wa-bubble-inbound: #ffffff;
        --wa-bubble-outbound: #d9fdd3;
        --wa-text-primary: #111b21;
        --wa-text-muted: #667781;
        --wa-brand-green: #00a884;
        --wa-brand-green-hover: #008f6f;
        --wa-blue-tick: #53bdeb;
        --wa-grey-tick: #8696a0;
        --wa-header-bg: #f0f2f5;
        --wa-border-color: #d1d7db;
    }

    .wa-chat-container {
        height: calc(100vh - 110px);
        min-height: 620px;
        background: #ffffff;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #d1d7db;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        display: flex;
    }

    /* Column 1: Conversations List (Left) */
    .wa-col-left {
        width: 350px;
        min-width: 350px;
        max-width: 350px;
        border-right: 1px solid #d1d7db;
        background: #ffffff;
        display: flex;
        flex-direction: column;
    }

    .wa-left-header {
        height: 60px;
        padding: 10px 16px;
        background-color: #f0f2f5;
        border-bottom: 1px solid #d1d7db;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .wa-search-bar {
        padding: 8px 12px;
        background: #ffffff;
        border-bottom: 1px solid #f0f2f5;
    }

    .wa-search-input {
        background-color: #f0f2f5 !important;
        border: none !important;
        border-radius: 8px !important;
        padding: 7px 12px 7px 34px !important;
        font-size: 13.5px !important;
        color: #111b21 !important;
    }

    .wa-search-input:focus {
        background-color: #ffffff !important;
        box-shadow: 0 0 0 1.5px #00a884 !important;
        outline: none !important;
    }

    .wa-conv-item {
        cursor: pointer;
        display: flex;
        align-items: center;
        padding: 11px 16px;
        gap: 13px;
        border-bottom: 1px solid #f2f4f5;
        background: #ffffff;
        transition: background 0.12s ease;
        position: relative;
    }

    .wa-conv-item:hover {
        background-color: #f5f6f6;
    }

    .wa-conv-item.active {
        background-color: #f0f2f5;
    }

    .wa-conv-avatar {
        width: 48px;
        height: 48px;
        min-width: 48px;
        border-radius: 50%;
        background: linear-gradient(135deg, #00a884 0%, #128c7e 100%);
        color: #ffffff;
        font-weight: 600;
        font-size: 1.15rem;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        position: relative;
    }

    .wa-avatar-status-dot {
        position: absolute;
        bottom: 1px;
        right: 1px;
        width: 12px;
        height: 12px;
        background-color: #25d366;
        border: 2px solid #ffffff;
        border-radius: 50%;
    }

    .wa-conv-content {
        flex: 1;
        min-width: 0;
    }

    .wa-conv-row-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 3px;
    }

    .wa-conv-title {
        font-size: 15px;
        font-weight: 600;
        color: #111b21;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 175px;
    }

    .wa-conv-time {
        font-size: 12px;
        color: #667781;
        white-space: nowrap;
    }

    .wa-conv-row-bottom {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 6px;
    }

    .wa-conv-snippet {
        font-size: 13.5px;
        color: #667781;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 4px;
        flex: 1;
        min-width: 0;
    }

    .wa-unread-pill {
        background-color: #25d366;
        color: #ffffff;
        font-size: 11px;
        font-weight: 700;
        padding: 1px 6px;
        border-radius: 10px;
        min-width: 19px;
        height: 19px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 1px 2px rgba(0,0,0,0.15);
    }

    /* Column 2: Center Chat Stream */
    .wa-col-center {
        background-color: #efeae2;
        background-image: 
            radial-gradient(rgba(0, 0, 0, 0.04) 1px, transparent 1px),
            radial-gradient(rgba(0, 0, 0, 0.04) 1px, #efeae2 1px);
        background-size: 24px 24px;
        background-position: 0 0, 12px 12px;
    }

    .wa-chat-header {
        height: 60px;
        padding: 10px 16px;
        background-color: #f0f2f5;
        border-bottom: 1px solid #d1d7db;
        z-index: 5;
    }

    .wa-header-avatar {
        width: 40px;
        height: 40px;
        min-width: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #00a884 0%, #128c7e 100%);
        color: #ffffff;
        font-weight: 600;
        font-size: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .wa-header-title {
        font-size: 16px;
        font-weight: 600;
        color: #111b21;
        line-height: 1.2;
        max-width: 320px;
    }

    .wa-header-sub {
        font-size: 12px;
        color: #667781;
        margin-top: 2px;
    }

    .wa-viewport {
        overflow-y: auto;
        scroll-behavior: smooth;
        padding: 18px 24px;
        flex: 1;
    }

    /* Message Bubbles - Standard WhatsApp Web Style */
    .wa-msg-row {
        display: flex;
        width: 100%;
        margin-bottom: 4px;
    }

    .wa-msg-row.inbound {
        justify-content: flex-start;
    }

    .wa-msg-row.outbound {
        justify-content: flex-end;
    }

    .wa-bubble-inbound {
        background-color: #ffffff;
        color: #111b21;
        border-radius: 8px;
        border-top-left-radius: 0;
        box-shadow: 0 1px 0.5px rgba(11, 20, 26, 0.13);
        max-width: 65%;
        min-width: 90px;
        width: fit-content;
        padding: 6px 9px 6px 9px;
        position: relative;
        font-size: 14.2px;
        line-height: 19px;
        word-break: break-word;
    }

    .wa-bubble-outbound {
        background-color: #d9fdd3;
        color: #111b21;
        border-radius: 8px;
        border-top-right-radius: 0;
        box-shadow: 0 1px 0.5px rgba(11, 20, 26, 0.13);
        max-width: 65%;
        min-width: 90px;
        width: fit-content;
        padding: 6px 9px 6px 9px;
        position: relative;
        font-size: 14.2px;
        line-height: 19px;
        word-break: break-word;
    }

    .wa-bubble-outbound.is-failed {
        border: 1px solid rgba(220, 53, 69, 0.35);
        background-color: #fff2f2;
    }

    .wa-msg-text {
        display: inline;
        vertical-align: top;
        font-size: 14.2px;
        line-height: 19px;
        color: #111b21;
        white-space: pre-wrap;
    }

    .wa-msg-meta {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        float: right;
        margin-left: 9px;
        margin-top: 4px;
        font-size: 11px;
        color: #667781;
        white-space: nowrap;
        user-select: none;
        position: relative;
        top: 2px;
    }

    .wa-meta-tick {
        display: inline-flex;
        align-items: center;
        margin-left: 2px;
        font-size: 14px;
        line-height: 1;
    }

    .wa-tick-blue {
        color: var(--wa-blue-tick) !important;
        font-weight: 800;
        filter: drop-shadow(0 0 1px rgba(83, 189, 235, 0.4));
    }

    .wa-tick-grey {
        color: var(--wa-grey-tick) !important;
    }

    .wa-date-pill {
        background-color: rgba(255, 255, 255, 0.95);
        color: #54656f;
        font-size: 11.5px;
        font-weight: 500;
        padding: 5px 12px;
        border-radius: 7.5px;
        box-shadow: 0 1px 0.5px rgba(11,20,26,0.13);
        margin: 14px auto;
        display: table;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        user-select: none;
    }

    /* Floating Scroll to Bottom Button */
    .wa-scroll-btn {
        position: absolute;
        bottom: 80px;
        right: 24px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: #ffffff;
        color: #54656f;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.16);
        border: 1px solid var(--wa-border-color);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 20;
        transition: transform 0.2s ease, opacity 0.2s ease;
    }

    .wa-scroll-btn:hover {
        transform: scale(1.08);
        color: var(--wa-brand-green);
    }

    /* Composer Bar */
    .wa-composer-container {
        padding: 9px 16px;
        background-color: #f0f2f5;
        border-top: 1px solid #d1d7db;
        z-index: 5;
    }

    .wa-composer-input {
        background-color: #ffffff !important;
        border-radius: 8px !important;
        border: 1px solid #ffffff !important;
        padding: 9px 14px !important;
        font-size: 14.5px !important;
        color: #111b21 !important;
        box-shadow: 0 1px 0.5px rgba(11,20,26,0.06) !important;
    }

    .wa-composer-input:focus {
        border-color: #00a884 !important;
        box-shadow: 0 0 0 2px rgba(0, 168, 132, 0.15) !important;
        outline: none !important;
    }

    .wa-btn-send {
        background-color: #00a884 !important;
        color: #ffffff !important;
        border: none !important;
        border-radius: 50% !important;
        width: 40px !important;
        height: 40px !important;
        min-width: 40px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-shadow: 0 2px 4px rgba(0, 168, 132, 0.3) !important;
        cursor: pointer !important;
        transition: transform 0.15s ease, background-color 0.15s ease !important;
    }

    .wa-btn-send:hover {
        background-color: #008f6f !important;
        transform: scale(1.05);
    }

    .wa-btn-send:active {
        transform: scale(0.95);
    }

    /* Column 3: CRM Details */
    .wa-col-right {
        width: 300px;
        min-width: 300px;
        max-width: 300px;
        border-left: 1px solid #d1d7db;
        background: #ffffff;
        overflow-y: auto;
        transition: margin-right 0.25s ease;
    }

    .wa-col-right.collapsed {
        display: none !important;
    }

    /* Badges */
    .wa-badge-window-active {
        background-color: #ecfdf5;
        color: #047857;
        border: 1px solid #a7f3d0;
        font-size: 11px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 12px;
    }

    .wa-badge-window-expired {
        background-color: #fffbeb;
        color: #b45309;
        border: 1px solid #fde68a;
        font-size: 11px;
        font-weight: 700;
        padding: 2px 7px;
        border-radius: 12px;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-3 py-2">
    <div class="wa-chat-container d-flex position-relative">
        
        {{-- ========================================================================= --}}
        {{-- COLUMN 1: LEFT CHAT LIST (350px)                                          --}}
        {{-- ========================================================================= --}}
        <div class="wa-col-left">
            {{-- Top Header --}}
            <div class="wa-left-header">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold fs-6 text-dark">Chats</span>
                    <span class="wa-unread-pill" id="totalChatsCountBadge" style="background: #25d366;">0</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-sm btn-white bg-white border shadow-xs rounded-pill px-2.5 py-1 text-success fw-semibold" id="btnOpenSimulator" title="Simulate Inbound Test Message" data-bs-toggle="modal" data-bs-target="#simulatorModal">
                        <i class="bi bi-robot me-1"></i>Test Sim
                    </button>
                    <button class="btn btn-sm btn-white bg-white border shadow-xs rounded-circle" id="btnRefreshChats" title="Refresh Chat List" style="width: 32px; height: 32px; padding: 0;">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>

            {{-- Search Bar --}}
            <div class="wa-search-bar">
                <div class="position-relative">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-2.5 text-muted small"></i>
                    <input type="text" id="chatSearchInput" class="form-control wa-search-input w-100" placeholder="Search chats or phone..." autocomplete="off">
                </div>
            </div>

            {{-- Conversations Scrollable List --}}
            <div class="flex-grow-1 overflow-y-auto" id="conversationsListContainer">
                <div class="text-center py-5 text-muted" id="conversationsLoader">
                    <div class="spinner-border spinner-border-sm text-success" role="status"></div>
                    <div class="small mt-2">Loading chats...</div>
                </div>
                <div id="conversationsEmptyState" class="d-none text-center p-4 text-muted small">
                    <i class="bi bi-chat-square-dots fs-3 d-block text-secondary mb-2"></i>
                    No WhatsApp conversations found.
                </div>
            </div>
        </div>

        {{-- ========================================================================= --}}
        {{-- COLUMN 2: CENTER ACTIVE CHAT STREAM & COMPOSER (Flex-1)                   --}}
        {{-- ========================================================================= --}}
        <div class="wa-col-center flex-grow-1 d-flex flex-column position-relative min-w-0" id="chatCenterColumn">
            
            {{-- Placeholder when no chat selected --}}
            <div id="noChatSelectedState" class="d-flex flex-column align-items-center justify-content-center h-100 text-center p-4">
                <div class="rounded-circle bg-white shadow-sm p-4 mb-3" style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-whatsapp fs-1 text-success"></i>
                </div>
                <h5 class="fw-bold text-dark mb-1">WhatsApp Live Chat &amp; Inbox</h5>
                <p class="text-muted small max-w-sm mb-3" style="max-width: 340px;">
                    Select a conversation from the left to start messaging in real time, view Meta blue ticks, or send approved WhatsApp templates.
                </p>
                <button class="btn btn-sm btn-success rounded-pill px-3 shadow-xs" data-bs-toggle="modal" data-bs-target="#simulatorModal">
                    <i class="bi bi-lightning-charge-fill me-1"></i> Send a Test Inbound Message
                </button>
            </div>

            {{-- Active Chat View (Initially hidden) --}}
            <div id="activeChatStreamWrapper" class="d-none flex-column h-100 position-relative">
                
                {{-- Header Bar --}}
                <div class="wa-chat-header d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="wa-header-avatar" id="activeChatAvatar">C</div>
                        <div>
                            <div class="wa-header-title text-truncate" id="activeChatName">Contact Name</div>
                            <div class="wa-header-sub d-flex align-items-center gap-2">
                                <span id="activeChatPhone">+910000000000</span>
                                <span>•</span>
                                <span id="activeChatWindowBadge" class="wa-badge-window-active">24h Window: Active</span>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button class="btn btn-sm btn-white bg-white border shadow-xs rounded-pill px-3 py-1.5 text-dark fw-semibold" data-bs-toggle="modal" data-bs-target="#templateModal" title="Send Approved Meta Template">
                            <i class="bi bi-file-earmark-code text-success me-1.5"></i>Template
                        </button>
                        <button class="btn btn-sm btn-white bg-white border shadow-xs rounded-pill px-3 py-1.5 text-dark fw-semibold" id="btnToggleCrm" title="Toggle CRM Details">
                            <i class="bi bi-person-lines-fill text-primary me-1.5"></i>Peer CRM
                        </button>
                    </div>
                </div>

                {{-- Scrollable Chat History Viewport --}}
                <div class="wa-viewport flex-grow-1 d-flex flex-column position-relative" id="chatViewport">
                    <div id="chatMessagesContainer" class="d-flex flex-column gap-1">
                        {{-- Messages rendered dynamically here --}}
                    </div>

                    {{-- Floating Scroll to Bottom Button --}}
                    <button id="btnFloatingScroll" class="wa-scroll-btn d-none shadow" title="Scroll to latest message">
                        <i class="bi bi-chevron-down fs-5"></i>
                    </button>
                </div>

                {{-- 24h Window Expired Warning Banner --}}
                <div id="windowExpiredBanner" class="p-2 px-3 bg-warning-subtle border-top border-warning d-none align-items-center justify-content-between text-dark" style="font-size: 0.82rem;">
                    <div>
                        <i class="bi bi-exclamation-triangle-fill text-warning me-1.5 fs-6"></i>
                        <strong>24h Care Window Expired.</strong> Freeform text cannot be delivered. Send a Meta Template to re-open.
                    </div>
                    <button class="btn btn-xs btn-dark rounded-pill px-2.5 py-1" data-bs-toggle="modal" data-bs-target="#templateModal">
                        <i class="bi bi-file-earmark-code me-1"></i>Choose Template
                    </button>
                </div>

                {{-- Composer Bar --}}
                <div class="wa-composer-container d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-link text-secondary p-1 fs-5 text-decoration-none" title="Quick Emoji">
                        <i class="bi bi-emoji-smile"></i>
                    </button>
                    <button type="button" class="btn btn-link text-secondary p-1 fs-5 text-decoration-none" id="btnOpenTemplateModal" title="Send WhatsApp Template" data-bs-toggle="modal" data-bs-target="#templateModal">
                        <i class="bi bi-file-earmark-code text-success"></i>
                    </button>

                    <div class="flex-grow-1 position-relative">
                        <input type="text" id="chatMessageInput" class="form-control wa-composer-input" placeholder="Type a message..." autocomplete="off">
                    </div>

                    {{-- Send Button (Vibrant Emerald, Always Clickable) --}}
                    <button type="button" id="btnSendMessage" class="wa-btn-send" title="Send WhatsApp Message">
                        <i class="bi bi-send-fill fs-6" id="btnSendIcon"></i>
                        <span class="spinner-border spinner-border-sm d-none" id="btnSendSpinner" role="status"></span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ========================================================================= --}}
        {{-- COLUMN 3: RIGHT CONTACT DETAILS CRM (288px)                               --}}
        {{-- ========================================================================= --}}
        <div class="wa-col-right p-3 d-flex flex-column" id="chatCrmColumn">
            <div class="d-flex align-items-center justify-content-between pb-2 mb-3 border-bottom">
                <span class="fw-bold fs-7 text-uppercase text-secondary" style="letter-spacing: 0.5px;">Contact Info</span>
                <button type="button" class="btn-close" id="btnCloseCrm" aria-label="Close"></button>
            </div>

            <div class="text-center mb-3">
                <div class="wa-conv-avatar mx-auto mb-2" id="crmAvatar" style="width: 64px; height: 64px; font-size: 1.5rem;">C</div>
                <h6 class="fw-bold mb-0 text-dark" id="crmName">Contact Name</h6>
                <div class="text-muted small" id="crmPhone">+910000000000</div>
            </div>

            <div class="p-2.5 rounded-3 bg-light border mb-3">
                <div class="d-flex align-items-center justify-content-between mb-1.5">
                    <span class="text-muted small">Care Window:</span>
                    <span id="crmWindowStatus" class="wa-badge-window-active">Active</span>
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <span class="text-muted small">Expires:</span>
                    <span id="crmWindowRemaining" class="fw-semibold text-dark small">24h 00m</span>
                </div>
            </div>

            <div class="mb-3">
                <span class="text-secondary fw-bold text-uppercase fs-8 mb-2 d-block" style="font-size: 0.72rem;">Peer Details</span>
                <div class="d-flex flex-column gap-2 text-muted small">
                    <div>
                        <span class="text-secondary d-block" style="font-size: 0.72rem;">Membership:</span>
                        <strong class="text-dark" id="crmMembership">Individual Peer</strong>
                    </div>
                    <div>
                        <span class="text-secondary d-block" style="font-size: 0.72rem;">Company / Chapter:</span>
                        <strong class="text-dark" id="crmCompany">N/A</strong>
                    </div>
                    <div>
                        <span class="text-secondary d-block" style="font-size: 0.72rem;">City:</span>
                        <strong class="text-dark" id="crmCity">N/A</strong>
                    </div>
                    <div>
                        <span class="text-secondary d-block" style="font-size: 0.72rem;">Peer Joined:</span>
                        <strong class="text-dark" id="crmJoinedAt">N/A</strong>
                    </div>
                </div>
            </div>

            <div class="mt-auto pt-3 border-top d-flex flex-column gap-2">
                <button class="btn btn-sm btn-outline-success w-100 rounded-pill" data-bs-toggle="modal" data-bs-target="#templateModal">
                    <i class="bi bi-file-earmark-code me-1"></i> Send Template
                </button>
                <button class="btn btn-sm btn-light border w-100 rounded-pill" id="btnCopyPhone">
                    <i class="bi bi-clipboard me-1"></i> Copy Phone Number
                </button>
            </div>
        </div>

    </div>
</div>

{{-- ========================================================================= --}}
{{-- MODAL 1: SELECT META TEMPLATE                                             --}}
{{-- ========================================================================= --}}
<div class="modal fade" id="templateModal" tabindex="-1" aria-labelledby="templateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold fs-6" id="templateModalLabel">
                    <i class="bi bi-file-earmark-code text-success me-1.5"></i>Send Meta Template Message
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="text-muted small mb-3">
                    Select a pre-approved WhatsApp template to send to this contact. This is guaranteed to deliver even if the 24h window is expired.
                </p>
                <div class="mb-3">
                    <label for="templateSelect" class="form-label fw-semibold small">Choose Template</label>
                    <select class="form-select" id="templateSelect">
                        <option value="">-- Choose Approved Template --</option>
                        @foreach($templates as $tpl)
                            <option value="{{ $tpl->name }}" data-language="{{ $tpl->language ?: 'en' }}" data-category="{{ $tpl->category }}">
                                {{ $tpl->name }} ({{ $tpl->language ?: 'en' }}) - {{ $tpl->category ?: 'UTILITY' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="p-3 bg-light rounded-3 border d-none" id="templatePreviewBox">
                    <div class="text-secondary small fw-bold mb-1">Template Note:</div>
                    <div class="small text-muted" id="templatePreviewText">Standard template dispatch without parameter variables.</div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success btn-sm rounded-pill px-3" id="btnDispatchTemplate">
                    <i class="bi bi-send-fill me-1"></i> Send Template Now
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ========================================================================= --}}
{{-- MODAL 2: WHATSAPP TEST SIMULATOR (INBOUND & DELIVERY STATUS)              --}}
{{-- ========================================================================= --}}
<div class="modal fade" id="simulatorModal" tabindex="-1" aria-labelledby="simulatorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold fs-6" id="simulatorModalLabel">
                    <i class="bi bi-robot text-success me-1.5"></i>WhatsApp Simulation & Testing Lab
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <ul class="nav nav-pills nav-fill mb-3" id="simTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active small py-1.5 fw-semibold" id="tab-inbound-btn" data-bs-toggle="pill" data-bs-target="#tab-inbound-pane" type="button" role="tab">
                            <i class="bi bi-chat-left-dots-fill me-1"></i>Inbound Customer Msg
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link small py-1.5 fw-semibold" id="tab-status-btn" data-bs-toggle="pill" data-bs-target="#tab-status-pane" type="button" role="tab">
                            <i class="bi bi-check2-all me-1"></i>Delivery / Failure Status
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="simTabsContent">
                    {{-- Tab 1: Inbound Customer Message --}}
                    <div class="tab-pane fade show active" id="tab-inbound-pane" role="tabpanel">
                        <div class="alert alert-info py-2 px-3 small mb-3">
                            Simulate a WhatsApp user sending a message. This triggers the webhook pipeline, updates contact profile names, turns read receipts blue, and activates the 24h care window.
                        </div>
                        <div class="mb-2.5">
                            <label for="simPhone" class="form-label fw-semibold small mb-1">Sender Phone Number (with Country Code)</label>
                            <input type="text" class="form-control form-control-sm" id="simPhone" value="916353025164" placeholder="e.g. 919664620704">
                        </div>
                        <div class="mb-2.5">
                            <label for="simName" class="form-label fw-semibold small mb-1">Sender Display Name</label>
                            <input type="text" class="form-control form-control-sm" id="simName" value="Doot WhatsApp Tester" placeholder="e.g. John Doe">
                        </div>
                        <div class="mb-3">
                            <label for="simBody" class="form-label fw-semibold small mb-1">Incoming Message Body</label>
                            <textarea class="form-control form-control-sm" id="simBody" rows="2" placeholder="Type test message here...">Hi Peers Unity Admin, I am testing the new live chat module!</textarea>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-success btn-sm rounded-pill px-3" id="btnDispatchSimulation">
                                <i class="bi bi-lightning-fill me-1"></i> Trigger Inbound Message
                            </button>
                        </div>
                    </div>

                    {{-- Tab 2: Delivery & Failure Status Simulation --}}
                    <div class="tab-pane fade" id="tab-status-pane" role="tabpanel">
                        <div class="alert alert-warning py-2 px-3 small mb-3">
                            Simulate Meta's asynchronous status callbacks (<strong>SENT</strong> &rarr; <strong>DELIVERED</strong> &rarr; <strong>READ</strong> &rarr; <strong>FAILED</strong>). Test grey ticks, blue ticks, or failure alerts with error reasons.
                        </div>
                        <div class="mb-2.5">
                            <label for="simStatusTarget" class="form-label fw-semibold small mb-1">Target Outbound Message</label>
                            <select class="form-select form-select-sm" id="simStatusTarget">
                                <option value="latest">Latest Sent Message (Active Chat)</option>
                            </select>
                        </div>
                        <div class="mb-2.5">
                            <label for="simStatusSelect" class="form-label fw-semibold small mb-1">Target Status</label>
                            <select class="form-select form-select-sm" id="simStatusSelect">
                                <option value="DELIVERED">DELIVERED (Double Grey Tick)</option>
                                <option value="READ">READ (Double Blue Tick #53bdeb)</option>
                                <option value="FAILED" selected>FAILED (Red Error Badge &amp; Reason)</option>
                                <option value="SENT">SENT (Single Grey Tick)</option>
                            </select>
                        </div>
                        <div id="simFailureFields">
                            <div class="mb-2.5">
                                <label for="simErrorPreset" class="form-label fw-semibold small mb-1">Common Meta Failure Reasons</label>
                                <select class="form-select form-select-sm" id="simErrorPreset">
                                    <option value="131047|24-Hour Customer Care Window Closed (Template message required)">131047: 24h Customer Care Window Closed</option>
                                    <option value="131026|Message Undeliverable (Recipient cannot receive message or inactive)">131026: Message Undeliverable</option>
                                    <option value="131042|Business Eligibility or Payment Issue">131042: Business Eligibility or Payment Issue</option>
                                    <option value="130429|Meta API Rate Limit Exceeded">130429: Rate Limit Exceeded</option>
                                    <option value="custom">Custom Error Code &amp; Message</option>
                                </select>
                            </div>
                            <div class="row g-2 mb-3" id="simCustomErrorFields" style="display: none;">
                                <div class="col-4">
                                    <label for="simErrorCode" class="form-label fw-semibold small mb-1">Error Code</label>
                                    <input type="text" class="form-control form-control-sm" id="simErrorCode" placeholder="e.g. 131047" value="131047">
                                </div>
                                <div class="col-8">
                                    <label for="simErrorMessage" class="form-label fw-semibold small mb-1">Error Description</label>
                                    <input type="text" class="form-control form-control-sm" id="simErrorMessage" placeholder="Describe failure..." value="24-Hour Customer Care Window Closed">
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-danger btn-sm rounded-pill px-3" id="btnDispatchSimulateStatus">
                                <i class="bi bi-arrow-repeat me-1"></i> Apply Status Update
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // -------------------------------------------------------------
    // State Management
    // -------------------------------------------------------------
    let currentConversationId = null;
    let currentConversationData = null;
    let lastRenderedMessageIds = new Set();
    let isSending = false;
    let pollInterval = null;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    
    // API Endpoints
    const CONVERSATIONS_URL = "{{ route('admin.whatsapp.chat.conversations') }}";
    const UNREAD_COUNT_URL = "{{ route('admin.whatsapp.chat.unread_count') }}";
    const SIMULATE_URL = "{{ route('admin.whatsapp.chat.simulate') }}";
    const SIMULATE_STATUS_URL = "{{ route('admin.whatsapp.chat.simulate_status') }}";

    // DOM Elements
    const simStatusTarget = document.getElementById('simStatusTarget');
    const simStatusSelect = document.getElementById('simStatusSelect');
    const simFailureFields = document.getElementById('simFailureFields');
    const simErrorPreset = document.getElementById('simErrorPreset');
    const simCustomErrorFields = document.getElementById('simCustomErrorFields');
    const simErrorCode = document.getElementById('simErrorCode');
    const simErrorMessage = document.getElementById('simErrorMessage');
    const btnDispatchSimulateStatus = document.getElementById('btnDispatchSimulateStatus');

    const conversationsContainer = document.getElementById('conversationsListContainer');
    const conversationsLoader = document.getElementById('conversationsLoader');
    const conversationsEmpty = document.getElementById('conversationsEmptyState');
    const totalChatsBadge = document.getElementById('totalChatsCountBadge');
    const searchInput = document.getElementById('chatSearchInput');
    const btnRefreshChats = document.getElementById('btnRefreshChats');

    const noChatSelectedState = document.getElementById('noChatSelectedState');
    const activeChatStreamWrapper = document.getElementById('activeChatStreamWrapper');
    const chatViewport = document.getElementById('chatViewport');
    const chatMessagesContainer = document.getElementById('chatMessagesContainer');
    const btnFloatingScroll = document.getElementById('btnFloatingScroll');

    const activeChatAvatar = document.getElementById('activeChatAvatar');
    const activeChatName = document.getElementById('activeChatName');
    const activeChatPhone = document.getElementById('activeChatPhone');
    const activeChatWindowBadge = document.getElementById('activeChatWindowBadge');

    const windowExpiredBanner = document.getElementById('windowExpiredBanner');
    const chatMessageInput = document.getElementById('chatMessageInput');
    const btnSendMessage = document.getElementById('btnSendMessage');
    const btnSendIcon = document.getElementById('btnSendIcon');
    const btnSendSpinner = document.getElementById('btnSendSpinner');

    const chatCrmColumn = document.getElementById('chatCrmColumn');
    const btnToggleCrm = document.getElementById('btnToggleCrm');
    const btnCloseCrm = document.getElementById('btnCloseCrm');
    const crmAvatar = document.getElementById('crmAvatar');
    const crmName = document.getElementById('crmName');
    const crmPhone = document.getElementById('crmPhone');
    const crmWindowStatus = document.getElementById('crmWindowStatus');
    const crmWindowRemaining = document.getElementById('crmWindowRemaining');
    const crmMembership = document.getElementById('crmMembership');
    const crmCompany = document.getElementById('crmCompany');
    const crmCity = document.getElementById('crmCity');
    const crmJoinedAt = document.getElementById('crmJoinedAt');
    const btnCopyPhone = document.getElementById('btnCopyPhone');

    const btnDispatchTemplate = document.getElementById('btnDispatchTemplate');
    const templateSelect = document.getElementById('templateSelect');
    const templatePreviewBox = document.getElementById('templatePreviewBox');

    const btnDispatchSimulation = document.getElementById('btnDispatchSimulation');
    const simPhone = document.getElementById('simPhone');
    const simName = document.getElementById('simName');
    const simBody = document.getElementById('simBody');

    // -------------------------------------------------------------
    // Fetch and Render Conversations List (Left Column)
    // -------------------------------------------------------------
    function fetchConversations(query = '') {
        const url = query ? `${CONVERSATIONS_URL}?q=${encodeURIComponent(query)}` : CONVERSATIONS_URL;

        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (conversationsLoader) conversationsLoader.classList.add('d-none');

            if (!data.success || !data.conversations || data.conversations.length === 0) {
                if (conversationsEmpty) conversationsEmpty.classList.remove('d-none');
                totalChatsBadge.textContent = '0';
                return;
            }

            if (conversationsEmpty) conversationsEmpty.classList.add('d-none');
            totalChatsBadge.textContent = data.conversations.length;

            renderConversationsList(data.conversations);

            // Update global layout badges
            if (window.updateWhatsAppGlobalBadge && typeof data.total_unread !== 'undefined') {
                window.updateWhatsAppGlobalBadge(data.total_unread);
            }
        })
        .catch(err => {
            console.error('Error fetching conversations:', err);
            if (conversationsLoader) conversationsLoader.classList.add('d-none');
        });
    }

    function renderConversationsList(conversations) {
        let html = '';
        conversations.forEach(c => {
            const isActive = (c.id === currentConversationId) ? 'active' : '';
            const unreadBadge = c.unread_count > 0 
                ? `<span class="wa-unread-pill ms-auto">${c.unread_count}</span>` 
                : '';
            
            const windowIndicator = c.is_window_active
                ? `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size: 9.5px; padding: 1px 6px;" title="24h Care Window Active: ${escapeHtml(c.window_remaining)}">24h active</span>`
                : `<span class="badge bg-secondary bg-opacity-10 text-muted" style="font-size: 9.5px; padding: 1px 6px;" title="Window Expired">expired</span>`;

            let previewTick = '';
            if (c.last_message_direction === 'OUTBOUND') {
                const mst = (c.last_message_status || '').toUpperCase();
                if (mst === 'READ') {
                    previewTick = '<i class="bi bi-check-all me-1 flex-shrink-0" style="color: #53bdeb; font-size: 15px;" title="Read"></i>';
                } else if (mst === 'DELIVERED') {
                    previewTick = '<i class="bi bi-check-all me-1 flex-shrink-0 text-muted" style="font-size: 15px;" title="Delivered"></i>';
                } else if (mst === 'SENT') {
                    previewTick = '<i class="bi bi-check me-1 flex-shrink-0 text-muted" style="font-size: 15px;" title="Sent"></i>';
                } else if (mst === 'FAILED') {
                    previewTick = '<i class="bi bi-exclamation-circle-fill me-1 flex-shrink-0 text-danger" style="font-size: 12px;" title="Failed"></i>';
                } else {
                    previewTick = '<i class="bi bi-clock me-1 flex-shrink-0 text-muted" style="font-size: 11px;" title="Pending"></i>';
                }
            }

            html += `
                <div class="wa-conv-item ${isActive}" data-id="${c.id}" onclick="window.selectWhatsAppChat('${c.id}')">
                    <div class="wa-conv-avatar">
                        ${escapeHtml(c.avatar_letter)}
                        ${c.is_window_active ? '<span class="wa-avatar-status-dot" title="24h Active"></span>' : ''}
                    </div>
                    <div class="wa-conv-content">
                        <div class="wa-conv-row-top">
                            <span class="wa-conv-title" title="${escapeHtml(c.contact_name)}">${escapeHtml(c.contact_name)}</span>
                            <span class="wa-conv-time ${c.unread_count > 0 ? 'text-success fw-semibold' : ''}">${escapeHtml(c.last_message_time_formatted || '')}</span>
                        </div>
                        <div class="wa-conv-row-bottom">
                            <div class="wa-conv-snippet">
                                ${previewTick}<span class="text-truncate">${escapeHtml(c.last_message_preview)}</span>
                            </div>
                            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                ${windowIndicator}
                                ${unreadBadge}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        conversationsContainer.innerHTML = html;
    }

    // -------------------------------------------------------------
    // Select Conversation & Load Messages (Center Column)
    // -------------------------------------------------------------
    window.selectWhatsAppChat = function (conversationId) {
        if (!conversationId) return;

        currentConversationId = conversationId;
        lastRenderedMessageIds.clear();

        // Highlight in list
        document.querySelectorAll('.wa-conv-item').forEach(el => {
            if (el.getAttribute('data-id') === conversationId) {
                el.classList.add('active');
                // Optimistically clear unread badge in DOM
                const pill = el.querySelector('.wa-unread-pill');
                if (pill) pill.remove();
            } else {
                el.classList.remove('active');
            }
        });

        // Switch visible view
        noChatSelectedState.classList.add('d-none');
        activeChatStreamWrapper.classList.remove('d-none');
        activeChatStreamWrapper.classList.add('d-flex');

        chatMessagesContainer.innerHTML = `
            <div class="text-center py-5 text-muted">
                <div class="spinner-border spinner-border-sm text-success" role="status"></div>
                <div class="small mt-2">Loading messages...</div>
            </div>
        `;

        loadMessages(conversationId, true);
    };

    function loadMessages(conversationId, shouldScrollToBottom = false) {
        const messagesUrl = `/admin/whatsapp/api/conversations/${conversationId}/messages`;

        fetch(messagesUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.conversation) return;

            currentConversationData = data.conversation;
            updateHeaderAndCrm(data.conversation);
            renderMessageStream(data.messages, shouldScrollToBottom);

            // Populate simulator target options if simulator dropdown exists
            if (simStatusTarget && data.messages) {
                const outbounds = data.messages.filter(m => m.direction === 'OUTBOUND');
                let targetHtml = '<option value="latest">Latest Sent Message (Active Chat)</option>';
                outbounds.forEach(m => {
                    const snippet = (m.body || '').substring(0, 30) + ((m.body || '').length > 30 ? '...' : '');
                    targetHtml += `<option value="${m.id}">[${m.status}] ${escapeHtml(snippet)} (${m.time_formatted})</option>`;
                });
                simStatusTarget.innerHTML = targetHtml;
            }

            // Re-sync global badge
            if (window.updateWhatsAppGlobalBadge) {
                fetch(UNREAD_COUNT_URL)
                    .then(r => r.json())
                    .then(res => {
                        if (res.total_unread !== undefined) window.updateWhatsAppGlobalBadge(res.total_unread);
                    });
            }
        })
        .catch(err => console.error('Error loading messages:', err));
    }

    function updateHeaderAndCrm(conv) {
        activeChatName.textContent = conv.contact_name;
        activeChatPhone.textContent = `+${conv.contact_phone}`;
        activeChatAvatar.textContent = (conv.contact_name || conv.contact_phone).substring(0, 1).toUpperCase();

        if (conv.is_window_active) {
            activeChatWindowBadge.className = 'wa-badge-window-active';
            activeChatWindowBadge.textContent = `24h Window: ${conv.window_remaining}`;
            windowExpiredBanner.classList.add('d-none');
            windowExpiredBanner.classList.remove('d-flex');
        } else {
            activeChatWindowBadge.className = 'wa-badge-window-expired';
            activeChatWindowBadge.textContent = 'Window Expired';
            windowExpiredBanner.classList.remove('d-none');
            windowExpiredBanner.classList.add('d-flex');
        }

        // Update Right CRM
        crmName.textContent = conv.contact_name;
        crmPhone.textContent = `+${conv.contact_phone}`;
        crmAvatar.textContent = (conv.contact_name || conv.contact_phone).substring(0, 1).toUpperCase();
        crmWindowStatus.className = conv.is_window_active ? 'wa-badge-window-active' : 'wa-badge-window-expired';
        crmWindowStatus.textContent = conv.is_window_active ? 'Active' : 'Expired';
        crmWindowRemaining.textContent = conv.is_window_active ? conv.window_remaining : 'Expired';

        if (conv.crm_user) {
            crmMembership.textContent = conv.crm_user.membership;
            crmCompany.textContent = conv.crm_user.company;
            crmCity.textContent = conv.crm_user.city;
            crmJoinedAt.textContent = conv.crm_user.joined_at;
        } else {
            crmMembership.textContent = 'Unregistered Lead';
            crmCompany.textContent = 'N/A';
            crmCity.textContent = 'N/A';
            crmJoinedAt.textContent = 'N/A';
        }
    }

    function renderMessageStream(messages, scrollToBottom = false) {
        if (!messages || messages.length === 0) {
            chatMessagesContainer.innerHTML = `
                <div class="text-center py-5 text-muted small">
                    <i class="bi bi-chat-heart fs-3 d-block text-secondary mb-2"></i>
                    No messages yet in this conversation.<br>Say hello to start!
                </div>
            `;
            return;
        }

        let html = '';
        let lastDate = null;

        messages.forEach(msg => {
            // Date Divider
            if (msg.date_formatted && msg.date_formatted !== lastDate) {
                lastDate = msg.date_formatted;
                html += `<div class="wa-date-pill">${formatDatePill(msg.date_formatted)}</div>`;
            }

            const isInbound = (msg.direction === 'INBOUND');

            if (isInbound) {
                html += `
                    <div class="wa-msg-row inbound">
                        <div class="wa-bubble-inbound">
                            <span class="wa-msg-text">${formatMessageBody(msg.body)}</span>
                            <span class="wa-msg-meta">
                                <span>${escapeHtml(msg.time_formatted || '')}</span>
                            </span>
                        </div>
                    </div>
                `;
            } else {
                // Outbound Checkmarks
                let tickIcon = '';
                const st = (msg.status || '').toUpperCase();
                
                if (st === 'READ') {
                    // Double vivid blue tick (#53bdeb)
                    tickIcon = `
                        <span class="wa-meta-tick wa-tick-blue" title="Read by Customer">
                            <i class="bi bi-check-all"></i>
                        </span>
                    `;
                } else if (st === 'DELIVERED') {
                    // Double grey tick
                    tickIcon = `
                        <span class="wa-meta-tick wa-tick-grey" title="Delivered to WhatsApp">
                            <i class="bi bi-check-all"></i>
                        </span>
                    `;
                } else if (st === 'SENT') {
                    // Single grey tick
                    tickIcon = `
                        <span class="wa-meta-tick wa-tick-grey" title="Sent from Peers Unity">
                            <i class="bi bi-check"></i>
                        </span>
                    `;
                } else if (st === 'FAILED') {
                    const failReason = msg.error_message || (msg.error_code ? 'Error code: ' + msg.error_code : 'Delivery failed');
                    tickIcon = `
                        <span class="wa-meta-tick text-danger" title="${escapeHtml(failReason)}">
                            <i class="bi bi-exclamation-circle-fill"></i>
                        </span>
                    `;
                } else {
                    tickIcon = `
                        <span class="wa-meta-tick text-muted" title="Pending">
                            <i class="bi bi-clock"></i>
                        </span>
                    `;
                }

                let failureAlert = '';
                if (st === 'FAILED') {
                    const reasonText = msg.error_message || 'Message could not be delivered';
                    const codeBadge = msg.error_code ? `<span class="badge bg-danger text-white ms-1" style="font-size: 9px; font-weight: 600;">Code ${escapeHtml(msg.error_code)}</span>` : '';
                    failureAlert = `
                        <div class="mt-1 pt-1 border-top border-danger border-opacity-25 text-danger d-flex align-items-start gap-1 text-start" style="font-size: 11px; line-height: 1.35;">
                            <i class="bi bi-exclamation-octagon-fill text-danger flex-shrink-0 mt-0.5" style="font-size: 12px;"></i>
                            <div>
                                <span class="fw-bold">Delivery Failed:</span> <span>${escapeHtml(reasonText)}</span>
                                ${codeBadge}
                            </div>
                        </div>
                    `;
                }

                html += `
                    <div class="wa-msg-row outbound">
                        <div class="wa-bubble-outbound ${st === 'FAILED' ? 'is-failed' : ''}">
                            <span class="wa-msg-text">${formatMessageBody(msg.body)}</span>
                            ${failureAlert}
                            <span class="wa-msg-meta">
                                <span>${escapeHtml(msg.time_formatted || '')}</span>
                                ${tickIcon}
                            </span>
                        </div>
                    </div>
                `;
            }
        });

        chatMessagesContainer.innerHTML = html;

        if (scrollToBottom) {
            scrollViewportToBottom();
        }
    }

    function scrollViewportToBottom() {
        chatViewport.scrollTop = chatViewport.scrollHeight;
    }

    // -------------------------------------------------------------
    // Send Outbound Message (Enter key or Send button click)
    // -------------------------------------------------------------
    function handleSendMessage() {
        if (!currentConversationId || isSending) return;

        const body = chatMessageInput.value.trim();
        if (!body) {
            // Pulse input focus if empty
            chatMessageInput.focus();
            chatMessageInput.classList.add('is-invalid');
            setTimeout(() => chatMessageInput.classList.remove('is-invalid'), 600);
            return;
        }

        // Check if 24h window is expired
        if (currentConversationData && !currentConversationData.is_window_active) {
            const modalEl = new bootstrap.Modal(document.getElementById('templateModal'));
            modalEl.show();
            return;
        }

        isSending = true;
        btnSendIcon.classList.add('d-none');
        btnSendSpinner.classList.remove('d-none');

        const sendUrl = `/admin/whatsapp/api/conversations/${currentConversationId}/messages`;

        fetch(sendUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ body: body })
        })
        .then(res => res.json().then(data => ({ status: res.status, body: data })))
        .then(({ status, body: data }) => {
            isSending = false;
            btnSendSpinner.classList.add('d-none');
            btnSendIcon.classList.remove('d-none');

            if (status === 200 && data.success) {
                chatMessageInput.value = '';
                loadMessages(currentConversationId, true);
                fetchConversations(); // refresh snippet in left col
            } else {
                alert(data.message || 'Failed to send message. Please verify WhatsApp credentials.');
            }
        })
        .catch(err => {
            isSending = false;
            btnSendSpinner.classList.add('d-none');
            btnSendIcon.classList.remove('d-none');
            console.error('Send error:', err);
            alert('Network error while dispatching message.');
        });
    }

    btnSendMessage.addEventListener('click', handleSendMessage);

    chatMessageInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSendMessage();
        }
    });

    // -------------------------------------------------------------
    // Floating Scroll Button Behavior
    // -------------------------------------------------------------
    chatViewport.addEventListener('scroll', function () {
        const distanceFromBottom = chatViewport.scrollHeight - chatViewport.scrollTop - chatViewport.clientHeight;
        if (distanceFromBottom > 180) {
            btnFloatingScroll.classList.remove('d-none');
        } else {
            btnFloatingScroll.classList.add('d-none');
        }
    });

    btnFloatingScroll.addEventListener('click', function () {
        scrollViewportToBottom();
    });

    // -------------------------------------------------------------
    // CRM Sidebar Toggle
    // -------------------------------------------------------------
    btnToggleCrm.addEventListener('click', function () {
        chatCrmColumn.classList.toggle('collapsed');
    });

    btnCloseCrm.addEventListener('click', function () {
        chatCrmColumn.classList.add('collapsed');
    });

    btnCopyPhone.addEventListener('click', function () {
        if (currentConversationData && currentConversationData.contact_phone) {
            navigator.clipboard.writeText('+' + currentConversationData.contact_phone).then(() => {
                alert('Phone number copied to clipboard!');
            });
        }
    });

    // -------------------------------------------------------------
    // Template Modal Dispatch
    // -------------------------------------------------------------
    templateSelect.addEventListener('change', function () {
        if (templateSelect.value) {
            templatePreviewBox.classList.remove('d-none');
        } else {
            templatePreviewBox.classList.add('d-none');
        }
    });

    btnDispatchTemplate.addEventListener('click', function () {
        if (!currentConversationId) {
            alert('Please select a conversation first.');
            return;
        }

        const templateName = templateSelect.value;
        if (!templateName) {
            alert('Please choose a template from the dropdown.');
            return;
        }

        const selectedOption = templateSelect.options[templateSelect.selectedIndex];
        const lang = selectedOption.getAttribute('data-language') || 'en';

        btnDispatchTemplate.disabled = true;
        btnDispatchTemplate.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Dispatching...';

        const sendUrl = `/admin/whatsapp/api/conversations/${currentConversationId}/messages`;

        fetch(sendUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                template_name: templateName,
                language_code: lang
            })
        })
        .then(res => res.json())
        .then(data => {
            btnDispatchTemplate.disabled = false;
            btnDispatchTemplate.innerHTML = '<i class="bi bi-send-fill me-1"></i> Send Template Now';

            if (data.success) {
                const modal = bootstrap.Modal.getInstance(document.getElementById('templateModal'));
                if (modal) modal.hide();
                loadMessages(currentConversationId, true);
                fetchConversations();
            } else {
                alert(data.message || 'Failed to dispatch Meta template.');
            }
        })
        .catch(err => {
            btnDispatchTemplate.disabled = false;
            btnDispatchTemplate.innerHTML = '<i class="bi bi-send-fill me-1"></i> Send Template Now';
            console.error('Template dispatch error:', err);
            alert('Error sending template message.');
        });
    });

    // -------------------------------------------------------------
    // Inbound Test Simulator
    // -------------------------------------------------------------
    btnDispatchSimulation.addEventListener('click', function () {
        const phone = simPhone.value.trim();
        const name = simName.value.trim();
        const body = simBody.value.trim();

        if (!phone || !body) {
            alert('Phone and message body are required.');
            return;
        }

        btnDispatchSimulation.disabled = true;
        btnDispatchSimulation.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Simulating...';

        fetch(SIMULATE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                phone: phone,
                name: name,
                body: body
            })
        })
        .then(res => res.json())
        .then(data => {
            btnDispatchSimulation.disabled = false;
            btnDispatchSimulation.innerHTML = '<i class="bi bi-lightning-fill me-1"></i> Trigger Inbound Message';

            if (data.success) {
                const modal = bootstrap.Modal.getInstance(document.getElementById('simulatorModal'));
                if (modal) modal.hide();

                fetchConversations();
                if (data.conversation_id) {
                    window.selectWhatsAppChat(data.conversation_id);
                }
            } else {
                alert(data.message || 'Simulation failed.');
            }
        })
        .catch(err => {
            btnDispatchSimulation.disabled = false;
            btnDispatchSimulation.innerHTML = '<i class="bi bi-lightning-fill me-1"></i> Trigger Inbound Message';
            console.error('Simulation error:', err);
            alert('Simulation error.');
        });
    });

    // -------------------------------------------------------------
    // Outbound Delivery Status Simulator
    // -------------------------------------------------------------
    if (simStatusSelect) {
        simStatusSelect.addEventListener('change', function () {
            if (simStatusSelect.value === 'FAILED') {
                simFailureFields.style.display = 'block';
                btnDispatchSimulateStatus.className = 'btn btn-danger btn-sm rounded-pill px-3';
            } else {
                simFailureFields.style.display = 'none';
                btnDispatchSimulateStatus.className = 'btn btn-primary btn-sm rounded-pill px-3';
            }
        });
    }

    if (simErrorPreset) {
        simErrorPreset.addEventListener('change', function () {
            if (simErrorPreset.value === 'custom') {
                simCustomErrorFields.style.display = 'flex';
            } else {
                simCustomErrorFields.style.display = 'none';
            }
        });
    }

    if (btnDispatchSimulateStatus) {
        btnDispatchSimulateStatus.addEventListener('click', function () {
            const status = simStatusSelect.value;
            const target = simStatusTarget.value;
            let errCode = null;
            let errMsg = null;

            if (status === 'FAILED') {
                if (simErrorPreset.value === 'custom') {
                    errCode = simErrorCode.value.trim() || '131047';
                    errMsg = simErrorMessage.value.trim() || 'Delivery failed';
                } else {
                    const parts = simErrorPreset.value.split('|');
                    errCode = parts[0] || '131047';
                    errMsg = parts[1] || 'Delivery failed';
                }
            }

            btnDispatchSimulateStatus.disabled = true;
            btnDispatchSimulateStatus.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Updating...';

            fetch(SIMULATE_STATUS_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    message_id: (target !== 'latest' ? target : null),
                    status: status,
                    error_code: errCode,
                    error_message: errMsg
                })
            })
            .then(res => res.json())
            .then(data => {
                btnDispatchSimulateStatus.disabled = false;
                btnDispatchSimulateStatus.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Apply Status Update';

                if (data.success) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('simulatorModal'));
                    if (modal) modal.hide();

                    fetchConversations();
                    if (currentConversationId) {
                        loadMessages(currentConversationId, false);
                    }
                } else {
                    alert(data.message || 'Status update simulation failed.');
                }
            })
            .catch(err => {
                btnDispatchSimulateStatus.disabled = false;
                btnDispatchSimulateStatus.innerHTML = '<i class="bi bi-arrow-repeat me-1"></i> Apply Status Update';
                console.error('Status simulation error:', err);
                alert('Status simulation error.');
            });
        });
    }

    // -------------------------------------------------------------
    // Search Filtering & Auto-Refresh
    // -------------------------------------------------------------
    let searchDebounceTimer = null;
    searchInput.addEventListener('input', function () {
        clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => {
            fetchConversations(searchInput.value.trim());
        }, 300);
    });

    btnRefreshChats.addEventListener('click', function () {
        fetchConversations(searchInput.value.trim());
        if (currentConversationId) {
            loadMessages(currentConversationId, false);
        }
    });

    // -------------------------------------------------------------
    // Real-Time Background Polling (Every 3 seconds)
    // -------------------------------------------------------------
    function startChatPoller() {
        pollInterval = setInterval(function () {
            // Background update conversation list
            fetchConversations(searchInput.value.trim());

            // If a chat is actively open, silently fetch latest messages
            if (currentConversationId) {
                const isNearBottom = (chatViewport.scrollHeight - chatViewport.scrollTop - chatViewport.clientHeight) < 100;
                loadMessages(currentConversationId, isNearBottom);
            }
        }, 3000);
    }

    // Helpers
    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.toString().replace(/[&<>"']/g, m => map[m]);
    }

    function formatMessageBody(body) {
        if (!body) return '';
        let escaped = escapeHtml(body);
        return escaped.replace(/\n/g, '<br>');
    }

    function formatDatePill(dateStr) {
        const today = new Date().toISOString().slice(0, 10);
        const yesterday = new Date(Date.now() - 86400000).toISOString().slice(0, 10);
        if (dateStr === today) return 'Today';
        if (dateStr === yesterday) return 'Yesterday';
        return dateStr;
    }

    // Initial Load
    fetchConversations();
    startChatPoller();
});
</script>
@endpush
