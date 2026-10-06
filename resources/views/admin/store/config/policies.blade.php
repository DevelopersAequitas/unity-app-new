@extends('admin.layouts.app')

@section('title', 'Peers Store — Store Terms & Policies')

@section('content')
<style>
    /* Premium Glass & Card Styling */
    .policy-hero-banner {
        background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);
        border-radius: 20px;
        color: #ffffff;
        position: relative;
        overflow: hidden;
    }
    .policy-hero-banner::after {
        content: '';
        position: absolute;
        top: -40%;
        right: -10%;
        width: 300px;
        height: 300px;
        background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 70%);
        border-radius: 50%;
        pointer-events: none;
    }

    .policy-stat-card {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid rgba(0, 0, 0, 0.06);
        padding: 18px 22px;
        transition: all 0.25s ease;
    }
    .policy-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.06);
        border-color: rgba(99, 102, 241, 0.25);
    }

    .policy-grid-card {
        background: #ffffff;
        border-radius: 18px;
        border: 1px solid rgba(0, 0, 0, 0.08);
        transition: all 0.25s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        position: relative;
    }
    .policy-grid-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 14px 30px rgba(0, 0, 0, 0.08);
        border-color: rgba(99, 102, 241, 0.35);
    }
    .policy-grid-card .card-top-bar {
        height: 5px;
        width: 100%;
    }

    .policy-icon-box {
        width: 48px;
        height: 48px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
    }

    /* Gradients for Policy Types */
    .grad-indigo { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; }
    .grad-emerald { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
    .grad-sky { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: white; }
    .grad-amber { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; }
    .grad-purple { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; }
    .grad-rose { background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%); color: white; }

    /* Horizontal Tab Styling */
    .policy-pill-nav {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding-bottom: 8px;
        scrollbar-width: thin;
    }
    .policy-pill-btn {
        border-radius: 12px;
        padding: 10px 18px;
        font-weight: 600;
        font-size: 0.9rem;
        color: #4b5563;
        background: #ffffff;
        border: 1px solid #e5e7eb;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        white-space: nowrap;
        cursor: pointer;
    }
    .policy-pill-btn:hover {
        background-color: #f8fafc;
        color: #1e293b;
        border-color: #cbd5e1;
    }
    .policy-pill-btn.active {
        background: #4f46e5 !important;
        color: #ffffff !important;
        border-color: #4f46e5 !important;
        box-shadow: 0 4px 14px rgba(79, 70, 229, 0.3);
    }
    .policy-pill-btn.active i {
        color: #ffffff !important;
    }

    /* Document Reader View Styling */
    .policy-doc-container {
        background: #ffffff;
        border-radius: 20px;
        border: 1px solid rgba(0, 0, 0, 0.08);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.03);
    }
    .policy-doc-body {
        font-size: 0.96rem;
        line-height: 1.8;
        color: #334155;
    }
    .policy-doc-body h3 {
        font-size: 1.2rem;
        font-weight: 700;
        color: #0f172a;
        margin-top: 2rem;
        margin-bottom: 0.85rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .policy-doc-body h3:first-child {
        margin-top: 0;
    }
    .policy-doc-body ul {
        padding-left: 0;
        list-style: none;
        margin-bottom: 1.5rem;
    }
    .policy-doc-body li {
        position: relative;
        padding-left: 1.6rem;
        margin-bottom: 0.55rem;
    }
    .policy-doc-body li::before {
        content: '•';
        position: absolute;
        left: 0.4rem;
        top: -0.1rem;
        color: #4f46e5;
        font-size: 1.4rem;
        font-weight: bold;
    }
    .policy-doc-body p {
        margin-bottom: 1.15rem;
    }

    /* Markdown Editor Toolbar */
    .editor-toolbar-btn {
        padding: 4px 10px;
        font-size: 0.82rem;
        font-weight: 600;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        color: #475569;
        transition: all 0.15s;
    }
    .editor-toolbar-btn:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    /* Toast Notification */
    #copyToast {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 1080;
        display: none;
    }
</style>

<div class="container-fluid px-4 py-4">
    {{-- Breadcrumb & Title --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 text-muted small">
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.dashboard') }}" class="text-decoration-none text-muted">Peers Store</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.store.config.index') }}" class="text-decoration-none text-muted">Store Configuration</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Store Terms &amp; Policies</li>
                </ol>
            </nav>
            <h1 class="h3 mb-0 fw-bold text-dark d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-ruled-fill text-primary"></i> Store Terms, Guidelines &amp; Policies
            </h1>
            <p class="text-muted small mb-0">Official customer-facing policies governing order fulfillment, coin redemptions, returns, and warranties.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary d-flex align-items-center gap-2 rounded-3 px-3 py-2 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#createPolicyModal">
                <i class="bi bi-plus-lg"></i> Add Custom Policy
            </button>
            <a href="{{ route('admin.store.config.index') }}" class="btn btn-outline-secondary d-flex align-items-center gap-2 rounded-3 px-3 py-2 fw-semibold">
                <i class="bi bi-arrow-left"></i> Configuration
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm rounded-4 mb-4 p-3" role="alert">
            <i class="bi bi-check-circle-fill fs-4 text-success"></i>
            <div>
                <strong>Success!</strong> {{ session('success') }}
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Executive Stats Summary Row --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="policy-stat-card shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted extra-small text-uppercase fw-bold">Active Policies</span>
                    <span class="p-2 rounded-3 bg-primary bg-opacity-10 text-primary fs-6"><i class="bi bi-file-earmark-check"></i></span>
                </div>
                <div class="h3 fw-bold text-dark mb-0">{{ $policies->count() }}</div>
                <small class="text-success extra-small fw-semibold"><i class="bi bi-check-circle-fill me-1"></i>All Live in App</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="policy-stat-card shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted extra-small text-uppercase fw-bold">Return Window</span>
                    <span class="p-2 rounded-3 bg-success bg-opacity-10 text-success fs-6"><i class="bi bi-arrow-counterclockwise"></i></span>
                </div>
                <div class="h3 fw-bold text-dark mb-0">7 Days</div>
                <small class="text-muted extra-small">QC Verified Coin Refund</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="policy-stat-card shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted extra-small text-uppercase fw-bold">Max Bonus Cap</span>
                    <span class="p-2 rounded-3 bg-warning bg-opacity-10 text-warning fs-6"><i class="bi bi-percent"></i></span>
                </div>
                <div class="h3 fw-bold text-dark mb-0">50% Max</div>
                <small class="text-muted extra-small">Per Order Checkout Limit</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="policy-stat-card shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="text-muted extra-small text-uppercase fw-bold">Compliance Status</span>
                    <span class="p-2 rounded-3 bg-info bg-opacity-10 text-info fs-6"><i class="bi bi-shield-check"></i></span>
                </div>
                <div class="h3 fw-bold text-dark mb-0">100% Verified</div>
                <small class="text-info extra-small fw-semibold">Maker-Checker Synced</small>
            </div>
        </div>
    </div>

    {{-- Filter, Search & View Switcher Bar --}}
    <div class="card border-0 shadow-sm rounded-4 bg-white p-3 mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3 flex-grow-1" style="max-width: 480px;">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="policySearchInput" class="form-control bg-light border-start-0 ps-0" placeholder="Search policies by title or keyword..." onkeyup="filterPolicies()">
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="btn-group p-1 bg-light rounded-3" role="group">
                    <button type="button" class="btn btn-sm px-3 rounded-2 fw-semibold btn-primary" id="btnViewTabs" onclick="switchView('tabs')">
                        <i class="bi bi-layout-text-window-reverse me-1"></i> Document View
                    </button>
                    <button type="button" class="btn btn-sm px-3 rounded-2 fw-semibold btn-light text-muted" id="btnViewGrid" onclick="switchView('grid')">
                        <i class="bi bi-grid-3x3-gap-fill me-1"></i> Overview Grid
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- VIEW 1: DOCUMENT TABS VIEW (DEFAULT) --}}
    <div id="viewTabsContainer">
        {{-- Policy Navigation Horizontal Pills --}}
        <div class="policy-pill-nav mb-3" role="tablist">
            @foreach($policies as $index => $p)
                @php
                    $key = str_replace('-', '_', $p->key);
                    $icon = match(true) {
                        str_contains($key, 'term') => 'bi-file-earmark-text',
                        str_contains($key, 'return') || str_contains($key, 'refund') => 'bi-arrow-repeat',
                        str_contains($key, 'ship') || str_contains($key, 'delivery') => 'bi-truck',
                        str_contains($key, 'coin') || str_contains($key, 'redemption') => 'bi-coin',
                        str_contains($key, 'warranty') || str_contains($key, 'quality') => 'bi-shield-check',
                        str_contains($key, 'cancel') => 'bi-x-circle',
                        default => 'bi-file-text'
                    };
                @endphp
                <button class="policy-pill-btn {{ $index === 0 ? 'active' : '' }}" 
                        id="tab-btn-{{ $p->id }}" 
                        data-bs-toggle="pill" 
                        data-bs-target="#tab-pane-{{ $p->id }}" 
                        type="button" role="tab">
                    <i class="bi {{ $icon }} fs-5"></i>
                    <span>{{ $p->title }}</span>
                    <span class="badge bg-white bg-opacity-25 text-white rounded-pill extra-small ms-1">v{{ $p->version ?? 1 }}</span>
                </button>
            @endforeach
        </div>

        {{-- Policy Panes --}}
        <div class="tab-content" id="policyPanesContent">
            @foreach($policies as $index => $p)
                @php
                    $key = str_replace('-', '_', $p->key);
                    $gradClass = match(true) {
                        str_contains($key, 'term') => 'grad-indigo',
                        str_contains($key, 'return') || str_contains($key, 'refund') => 'grad-emerald',
                        str_contains($key, 'ship') || str_contains($key, 'delivery') => 'grad-sky',
                        str_contains($key, 'coin') || str_contains($key, 'redemption') => 'grad-amber',
                        str_contains($key, 'warranty') || str_contains($key, 'quality') => 'grad-purple',
                        str_contains($key, 'cancel') => 'grad-rose',
                        default => 'grad-indigo'
                    };
                    $bodyText = $p->body ?: $p->content;
                @endphp
                <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}" id="tab-pane-{{ $p->id }}" role="tabpanel">
                    <div class="policy-doc-container overflow-hidden">
                        {{-- Policy Header --}}
                        <div class="card-header bg-white py-4 px-4 d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom">
                            <div class="d-flex align-items-center gap-3">
                                <div class="policy-icon-box {{ $gradClass }} shadow-sm">
                                    <i class="bi {{ $icon }}"></i>
                                </div>
                                <div>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <h4 class="fw-bold text-dark mb-0">{{ $p->title }}</h4>
                                        <span class="badge {{ ($p->status ?? 'PUBLISHED') === 'PUBLISHED' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning border border-warning-subtle' }} rounded-pill px-2.5 py-1 extra-small">
                                            <i class="bi bi-circle-fill extra-small me-1"></i>{{ $p->status ?? 'PUBLISHED' }}
                                        </span>
                                    </div>
                                    <div class="text-muted small">
                                        <span class="me-3"><i class="bi bi-tag-fill me-1 text-primary"></i>Key: <code>{{ $p->key }}</code></span>
                                        <span class="me-3"><i class="bi bi-layers-fill me-1 text-info"></i>Version: <strong>v{{ $p->version ?? 1 }}.0</strong></span>
                                        <span><i class="bi bi-calendar-check me-1 text-muted"></i>Last Updated: {{ $p->updated_at ? $p->updated_at->format('d M Y, h:i A') : 'Recently' }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm px-3 py-2 rounded-3 fw-semibold" onclick="copyPolicyText('{{ $p->id }}')">
                                    <i class="bi bi-clipboard me-1"></i> Copy Text
                                </button>
                                <button type="button" class="btn btn-primary btn-sm px-3.5 py-2 rounded-3 fw-semibold shadow-sm" onclick="toggleEditMode('{{ $p->id }}')">
                                    <i class="bi bi-pencil-square me-1"></i> Edit Policy
                                </button>
                            </div>
                        </div>

                        <div class="card-body p-4 p-md-5">
                            {{-- 1. Formatted Reader View --}}
                            <div id="reader-view-{{ $p->id }}">
                                <div class="p-4 p-md-4.5 rounded-4 bg-light border border-light-subtle mb-4">
                                    <div class="policy-doc-body" id="policy-raw-{{ $p->id }}">
                                        @php
                                            $lines = explode("\n", $bodyText);
                                            $inList = false;
                                        @endphp
                                        @foreach($lines as $line)
                                            @php $trimmed = trim($line); @endphp
                                            @if(str_starts_with($trimmed, '### '))
                                                @if($inList) </ul> @php $inList = false; @endphp @endif
                                                <h3><i class="bi bi-patch-check-fill text-primary"></i> {{ substr($trimmed, 4) }}</h3>
                                            @elseif(str_starts_with($trimmed, '- ') || str_starts_with($trimmed, '* '))
                                                @if(!$inList) <ul> @php $inList = true; @endphp @endif
                                                <li>{!! preg_replace('/\*\*(.*?)\*\*/', '<strong class="text-dark">$1</strong>', substr($trimmed, 2)) !!}</li>
                                            @elseif(!empty($trimmed))
                                                @if($inList) </ul> @php $inList = false; @endphp @endif
                                                <p>{!! preg_replace('/\*\*(.*?)\*\*/', '<strong class="text-dark">$1</strong>', $trimmed) !!}</p>
                                            @endif
                                        @endforeach
                                        @if($inList) </ul> @endif
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center border-top pt-3 text-muted small">
                                    <span><i class="bi bi-shield-lock-fill text-success me-1"></i> Synchronized with Mobile App API</span>
                                    <button type="button" class="btn btn-outline-primary px-4 py-2 rounded-3 fw-semibold" onclick="toggleEditMode('{{ $p->id }}')">
                                        <i class="bi bi-pencil me-1"></i> Edit Policy Body
                                    </button>
                                </div>
                            </div>

                            {{-- 2. Modern Interactive Editor View --}}
                            <div id="edit-view-{{ $p->id }}" style="display: none;">
                                <form method="POST" action="{{ route('admin.store.config.policies.update') }}">
                                    @csrf
                                    <input type="hidden" name="policy_id" value="{{ $p->id }}">

                                    <div class="row g-3 mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold small text-dark">Policy Title</label>
                                            <input type="text" name="title" class="form-control form-control-lg fs-6" value="{{ $p->title }}" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold small text-dark">System Key (Slug)</label>
                                            <input type="text" class="form-control form-control-lg fs-6 bg-light" value="{{ $p->key }}" readonly>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold small text-dark">Status</label>
                                            <select name="status" class="form-select form-select-lg fs-6">
                                                <option value="PUBLISHED" {{ ($p->status ?? 'PUBLISHED') === 'PUBLISHED' ? 'selected' : '' }}>PUBLISHED (Live in App)</option>
                                                <option value="DRAFT" {{ ($p->status ?? '') === 'DRAFT' ? 'selected' : '' }}>DRAFT (Hidden)</option>
                                                <option value="ARCHIVED" {{ ($p->status ?? '') === 'ARCHIVED' ? 'selected' : '' }}>ARCHIVED</option>
                                            </select>
                                        </div>
                                    </div>

                                    {{-- Editor Formatting Toolbar --}}
                                    <div class="mb-2 d-flex flex-wrap align-items-center gap-1.5 p-2 bg-light rounded-3 border">
                                        <span class="text-muted extra-small fw-bold text-uppercase me-2">Formatting:</span>
                                        <button type="button" class="editor-toolbar-btn" onclick="insertFormatting('{{ $p->id }}', '### ', '')"><i class="bi bi-type-h3"></i> Heading</button>
                                        <button type="button" class="editor-toolbar-btn" onclick="insertFormatting('{{ $p->id }}', '**', '**')"><i class="bi bi-type-bold"></i> Bold</button>
                                        <button type="button" class="editor-toolbar-btn" onclick="insertFormatting('{{ $p->id }}', '- ', '')"><i class="bi bi-list-ul"></i> Bullet List</button>
                                        <button type="button" class="editor-toolbar-btn" onclick="insertFormatting('{{ $p->id }}', '> ', '')"><i class="bi bi-quote"></i> Quote</button>
                                        <button type="button" class="editor-toolbar-btn" onclick="insertFormatting('{{ $p->id }}', '\n---\n', '')"><i class="bi bi-hr"></i> Divider</button>
                                    </div>

                                    <div class="mb-4">
                                        <label class="form-label fw-bold small text-dark">Policy Guidelines (Markdown Body)</label>
                                        <textarea id="textarea-{{ $p->id }}" name="body" class="form-control font-monospace p-3 rounded-3" rows="14" required style="font-size: 0.92rem; line-height: 1.65; border-color: #cbd5e1;">{{ $bodyText }}</textarea>
                                        <div class="form-text mt-1 text-muted small">Saving will automatically increment the document version to <strong>v{{ ($p->version ?? 1) + 1 }}.0</strong> and publish updates immediately.</div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center border-top pt-3">
                                        <button type="button" class="btn btn-light px-3.5 py-2 rounded-3 fw-semibold text-muted" onclick="toggleEditMode('{{ $p->id }}')">
                                            <i class="bi bi-x-lg me-1"></i> Cancel Editing
                                        </button>
                                        <button type="submit" class="btn btn-primary px-4 py-2.5 rounded-3 fw-bold d-flex align-items-center gap-2 shadow-sm">
                                            <i class="bi bi-cloud-arrow-up-fill fs-5"></i> Save &amp; Publish Version {{ ($p->version ?? 1) + 1 }}.0
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- VIEW 2: GRID OVERVIEW (CARDS) --}}
    <div id="viewGridContainer" style="display: none;">
        <div class="row g-4" id="policyGridRow">
            @foreach($policies as $p)
                @php
                    $key = str_replace('-', '_', $p->key);
                    $icon = match(true) {
                        str_contains($key, 'term') => 'bi-file-earmark-text',
                        str_contains($key, 'return') || str_contains($key, 'refund') => 'bi-arrow-repeat',
                        str_contains($key, 'ship') || str_contains($key, 'delivery') => 'bi-truck',
                        str_contains($key, 'coin') || str_contains($key, 'redemption') => 'bi-coin',
                        str_contains($key, 'warranty') || str_contains($key, 'quality') => 'bi-shield-check',
                        str_contains($key, 'cancel') => 'bi-x-circle',
                        default => 'bi-file-text'
                    };
                    $gradClass = match(true) {
                        str_contains($key, 'term') => 'grad-indigo',
                        str_contains($key, 'return') || str_contains($key, 'refund') => 'grad-emerald',
                        str_contains($key, 'ship') || str_contains($key, 'delivery') => 'grad-sky',
                        str_contains($key, 'coin') || str_contains($key, 'redemption') => 'grad-amber',
                        str_contains($key, 'warranty') || str_contains($key, 'quality') => 'grad-purple',
                        str_contains($key, 'cancel') => 'grad-rose',
                        default => 'grad-indigo'
                    };
                    $cleanBody = strip_tags(preg_replace('/### /', '', $p->body ?: $p->content));
                @endphp
                <div class="col-md-6 col-xl-4 policy-card-item" data-title="{{ strtolower($p->title) }}" data-key="{{ strtolower($p->key) }}">
                    <div class="policy-grid-card shadow-sm p-4">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div class="policy-icon-box {{ $gradClass }} shadow-sm">
                                <i class="bi {{ $icon }}"></i>
                            </div>
                            <span class="badge {{ ($p->status ?? 'PUBLISHED') === 'PUBLISHED' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning' }} rounded-pill px-2.5 py-1 extra-small">
                                {{ $p->status ?? 'PUBLISHED' }}
                            </span>
                        </div>

                        <h5 class="fw-bold text-dark mb-1">{{ $p->title }}</h5>
                        <div class="text-muted extra-small mb-3">
                            <code>{{ $p->key }}</code> &bull; Version <strong>v{{ $p->version ?? 1 }}.0</strong>
                        </div>

                        <p class="text-muted small flex-grow-1 mb-4" style="display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.6;">
                            {{ $cleanBody }}
                        </p>

                        <div class="d-flex align-items-center justify-content-between border-top pt-3 mt-auto">
                            <small class="text-muted extra-small"><i class="bi bi-clock me-1"></i>{{ $p->updated_at ? $p->updated_at->format('d M Y') : 'Active' }}</small>
                            <button type="button" class="btn btn-sm btn-primary rounded-3 px-3 fw-semibold" onclick="selectAndShowDoc('{{ $p->id }}')">
                                Read &amp; Edit &rarr;
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Create Policy Modal --}}
<div class="modal fade" id="createPolicyModal" tabindex="-1" aria-labelledby="createPolicyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-light border-0 py-3.5 px-4">
                <h5 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="createPolicyModalLabel">
                    <i class="bi bi-file-earmark-plus text-primary"></i> Create New Store Policy
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('admin.store.config.policies.update') }}">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Policy Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control form-control-lg fs-6" placeholder="e.g. VIP Member Exclusive Perks Policy" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Policy Key (Unique Slug)</label>
                            <input type="text" name="key" class="form-control" placeholder="e.g. vip_member_policy">
                            <div class="form-text">Leave blank to auto-generate from title.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Status</label>
                            <select name="status" class="form-select">
                                <option value="PUBLISHED" selected>PUBLISHED (Live in App)</option>
                                <option value="DRAFT">DRAFT (Hidden)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Policy Guidelines (Markdown Body) <span class="text-danger">*</span></label>
                        <textarea name="body" class="form-control font-monospace p-3" rows="10" placeholder="### 1. Guideline Header&#10;Enter policy details here..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0 py-3 px-4">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold px-4">
                        <i class="bi bi-check2-circle me-1"></i> Create Policy
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Toast Notification --}}
<div id="copyToast" class="bg-dark text-white px-4 py-2.5 rounded-4 shadow-lg d-flex align-items-center gap-2">
    <i class="bi bi-check-circle-fill text-success fs-5"></i>
    <span>Policy text copied to clipboard!</span>
</div>

<script>
    function toggleEditMode(id) {
        const reader = document.getElementById('reader-view-' + id);
        const editor = document.getElementById('edit-view-' + id);
        if (editor.style.display === 'none') {
            reader.style.display = 'none';
            editor.style.display = 'block';
        } else {
            editor.style.display = 'none';
            reader.style.display = 'block';
        }
    }

    function switchView(mode) {
        const tabsContainer = document.getElementById('viewTabsContainer');
        const gridContainer = document.getElementById('viewGridContainer');
        const btnTabs = document.getElementById('btnViewTabs');
        const btnGrid = document.getElementById('btnViewGrid');

        if (mode === 'grid') {
            tabsContainer.style.display = 'none';
            gridContainer.style.display = 'block';
            btnGrid.className = 'btn btn-sm px-3 rounded-2 fw-semibold btn-primary';
            btnTabs.className = 'btn btn-sm px-3 rounded-2 fw-semibold btn-light text-muted';
        } else {
            gridContainer.style.display = 'none';
            tabsContainer.style.display = 'block';
            btnTabs.className = 'btn btn-sm px-3 rounded-2 fw-semibold btn-primary';
            btnGrid.className = 'btn btn-sm px-3 rounded-2 fw-semibold btn-light text-muted';
        }
    }

    function selectAndShowDoc(id) {
        switchView('tabs');
        const tabBtn = document.getElementById('tab-btn-' + id);
        if (tabBtn) {
            tabBtn.click();
            tabBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    }

    function insertFormatting(id, startTag, endTag) {
        const textarea = document.getElementById('textarea-' + id);
        if (!textarea) return;
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        const selected = text.substring(start, end) || 'Sample Text';
        const replacement = startTag + selected + endTag;
        textarea.value = text.substring(0, start) + replacement + text.substring(end);
        textarea.focus();
        textarea.selectionStart = start + startTag.length;
        textarea.selectionEnd = start + startTag.length + selected.length;
    }

    function copyPolicyText(id) {
        const textarea = document.getElementById('textarea-' + id);
        if (textarea) {
            navigator.clipboard.writeText(textarea.value).then(() => {
                const toast = document.getElementById('copyToast');
                toast.style.display = 'flex';
                setTimeout(() => { toast.style.display = 'none'; }, 2500);
            });
        }
    }

    function filterPolicies() {
        const query = document.getElementById('policySearchInput').value.toLowerCase();
        const cards = document.querySelectorAll('.policy-card-item');
        cards.forEach(card => {
            const title = card.getAttribute('data-title');
            const key = card.getAttribute('data-key');
            if (title.includes(query) || key.includes(query)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    }
</script>
@endsection
