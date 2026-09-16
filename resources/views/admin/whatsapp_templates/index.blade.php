@extends('admin.layouts.app')

@section('title', 'WhatsApp Templates — Communication Center')

@section('content')
<div class="container-fluid py-3 px-3 px-md-4">
    <!-- Header & Contextual Status Ribbon -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="text-uppercase fw-bold text-muted" style="font-size: 11px; letter-spacing: 0.8px;">
                    COMMUNICATION CENTER
                </span>
            </div>
            <h3 class="mb-1 fw-bold text-dark tracking-tight" style="letter-spacing: -0.02em;">
                WhatsApp Templates
            </h3>
            <p class="text-secondary small mb-0">
                Manage WhatsApp delivery configurations, triggers and communication workflows.
            </p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <span class="d-inline-flex align-items-center gap-2 bg-white border rounded-pill px-3 py-1.5 shadow-2xs text-secondary small fw-medium" style="font-size: 12px;">
                <span class="status-pulse-dot bg-success"></span>
                <span>Database synchronized</span>
            </span>
        </div>
    </div>

    <!-- Live Flash Feedback Alerts -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill fs-5 me-2 text-success"></i>
                <div>{{ session('success') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2 text-danger"></i>
                <div>{{ session('error') }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Enterprise Dashboard KPI Summary Row (Strictly Database-Driven) -->
    <div class="row g-3 mb-4">
        <!-- 1. TOTAL TEMPLATES -->
        <div class="col-6 col-xl-3">
            <div class="card border rounded-3 p-3.5 bg-white h-100 shadow-2xs metric-card kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-eyebrow text-muted fw-semibold">TOTAL TEMPLATES</span>
                    <div class="kpi-icon-box">
                        <i class="bi bi-grid-1x2"></i>
                    </div>
                </div>
                <div class="mb-2">
                    <h2 class="mb-0 fw-bold text-dark font-mono-num kpi-value" id="kpiTotal">{{ $stats['total'] }}</h2>
                </div>
                <div class="kpi-footer text-muted pt-2 border-top d-flex align-items-center gap-1.5">
                    <i class="bi bi-database text-muted" style="font-size: 11px;"></i>
                    <span>All database records</span>
                </div>
            </div>
        </div>

        <!-- 2. ACTIVE -->
        <div class="col-6 col-xl-3">
            <div class="card border rounded-3 p-3.5 bg-white h-100 shadow-2xs metric-card kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-eyebrow text-muted fw-semibold">ACTIVE</span>
                    <div class="kpi-icon-box">
                        <i class="bi bi-activity"></i>
                    </div>
                </div>
                <div class="mb-2">
                    <h2 class="mb-0 fw-bold text-dark font-mono-num kpi-value" id="kpiActive">{{ $stats['active'] }}</h2>
                </div>
                <div class="kpi-footer text-muted pt-2 border-top">
                    <span>Delivery enabled</span>
                </div>
            </div>
        </div>

        <!-- 3. INACTIVE -->
        <div class="col-6 col-xl-3">
            <div class="card border rounded-3 p-3.5 bg-white h-100 shadow-2xs metric-card kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-eyebrow text-muted fw-semibold">INACTIVE</span>
                    <div class="kpi-icon-box">
                        <i class="bi bi-pause-circle"></i>
                    </div>
                </div>
                <div class="mb-2">
                    <h2 class="mb-0 fw-bold text-dark font-mono-num kpi-value" id="kpiInactive">{{ $stats['inactive'] }}</h2>
                </div>
                <div class="kpi-footer text-muted pt-2 border-top">
                    <span>Delivery disabled</span>
                </div>
            </div>
        </div>

        <!-- 4. CATEGORIES -->
        <div class="col-6 col-xl-3">
            <div class="card border rounded-3 p-3.5 bg-white h-100 shadow-2xs metric-card kpi-card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="kpi-eyebrow text-muted fw-semibold">CATEGORIES</span>
                    <div class="kpi-icon-box">
                        <i class="bi bi-layers"></i>
                    </div>
                </div>
                <div class="mb-2">
                    <h2 class="mb-0 fw-bold text-dark font-mono-num kpi-value" id="kpiCategories">{{ $stats['category_count'] ?? 0 }}</h2>
                </div>
                <div class="kpi-footer text-muted pt-2 border-top d-flex align-items-center gap-1.5">
                    <i class="bi bi-diagram-2 text-muted" style="font-size: 11px;"></i>
                    <span>Across configured groups</span>
                </div>
            </div>
        </div>
    </div>

    @if(empty($templates) || count($templates) === 0)
        <!-- Clean Empty State -->
        <div class="card border rounded-3 p-5 text-center my-4 bg-white shadow-2xs">
            <div class="py-5">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-light text-muted mb-3" style="width: 56px; height: 56px;">
                    <i class="bi bi-chat-left-dots fs-3 text-secondary"></i>
                </div>
                <h5 class="fw-bold text-dark">No WhatsApp templates configured</h5>
                <p class="text-muted small mb-0" style="max-width: 480px; margin: 0 auto;">
                    No WhatsApp template records are currently available in this environment.<br>
                    Templates are managed via the WhatsApp integration and will appear here automatically once records exist in the database.
                </p>
            </div>
        </div>
    @else
        <!-- Professional Enterprise Data-Management Toolbar -->
        <div class="card border rounded-3 bg-white mb-3 shadow-2xs toolbar-card">
            <div class="p-3">
                <div class="filter-controls-row">
                    <!-- 1. Search Field -->
                    <div class="filter-col-search">
                        <div class="input-group toolbar-input-group">
                            <span class="input-group-text bg-transparent border-0 ps-3 text-muted">
                                <i class="bi bi-search" style="font-size: 13px;"></i>
                            </span>
                            <input type="text" id="templateSearch" class="form-control bg-transparent border-0 ps-1" 
                                   placeholder="Search templates by name, key, event..." 
                                   autocomplete="off"
                                   oninput="handleSearchInput(this)" onkeyup="handleSearchInput(this)"
                                   aria-label="Search templates">
                            <button type="button" class="btn filter-search-clear d-none pe-3" 
                                    id="searchClearBtn" onclick="clearSearchInput()" title="Clear search" aria-label="Clear search">
                                <i class="bi bi-x-circle-fill" style="font-size: 13px;"></i>
                            </button>
                        </div>
                    </div>

                    <!-- 2. Category Filter Dropdown -->
                    <div class="filter-col-category">
                        <select id="categoryFilter" class="form-select toolbar-select" onchange="filterTemplates()" aria-label="Filter by category">
                            <option value="all">All Categories ({{ $stats['category_count'] ?? 0 }})</option>
                            @if(!empty($stats['categories']))
                                @foreach($stats['categories'] as $catName => $catCount)
                                    @if($catCount > 0)
                                        <option value="{{ $catName }}">{{ $catName }} ({{ $catCount }})</option>
                                    @endif
                                @endforeach
                            @endif
                        </select>
                    </div>

                    <!-- 3. Trigger / Event Dropdown -->
                    @php
                        $triggerTypes = collect($templates)->pluck('trigger_type')->filter()->unique()->sort()->values();
                    @endphp
                    <div class="filter-col-trigger">
                        <select id="triggerFilter" class="form-select toolbar-select" onchange="filterTemplates()" aria-label="Filter by trigger event">
                            <option value="all">All Triggers ({{ $triggerTypes->count() }})</option>
                            @foreach($triggerTypes as $trig)
                                <option value="{{ $trig }}">{{ $trig }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- 4. Status Dropdown -->
                    <div class="filter-col-status">
                        <select id="statusFilter" class="form-select toolbar-select" onchange="filterTemplates()" aria-label="Filter by delivery status">
                            <option value="all">All Statuses</option>
                            <option value="active">Active Only ({{ $stats['active'] }})</option>
                            <option value="inactive">Inactive Only ({{ $stats['inactive'] }})</option>
                        </select>
                    </div>

                    <!-- 5. Clear Filters Action -->
                    <div class="filter-col-clear">
                        <button type="button" class="btn w-100 d-flex align-items-center justify-content-center gap-1.5 toolbar-btn btn-light text-muted opacity-60" 
                                id="resetFiltersBtn" onclick="resetFilters()" title="Clear all active filters" disabled>
                            <i class="bi bi-arrow-counterclockwise" style="font-size: 13px;"></i>
                            <span>Clear Filters</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Integrated Sub-Toolbar: Dynamic Count, Filter Chips & Webhook Protocol Badge -->
            <div class="px-3 py-2.5 bg-light-subtle border-top d-flex flex-wrap justify-content-between align-items-center gap-2" style="font-size: 12px;">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div id="filterResultCountText" class="text-secondary small">
                        Showing all <strong class="text-dark">{{ count($templates) }}</strong> templates
                    </div>
                    <!-- Removable Filter Chips Container -->
                    <div id="activeFilterChips" class="d-inline-flex flex-wrap gap-1.5 align-items-center"></div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <span id="activeFiltersSummary" class="badge filter-active-badge d-none">
                        <i class="bi bi-funnel-fill me-1"></i>0 filters active
                    </span>
                    <span class="badge bg-white text-secondary border font-monospace px-2.5 py-1 shadow-2xs" style="font-size: 11px;">
                        <i class="bi bi-link-45deg me-1 text-success"></i>FlexiMSG Webhook
                    </span>
                </div>
            </div>
        </div>

        <!-- Empty Search Filter State (Hidden by default) -->
        <div id="noResultsState" class="card border rounded-3 p-5 text-center d-none mb-4 bg-white shadow-2xs">
            <div class="py-4">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center bg-light text-muted mb-3" style="width: 52px; height: 52px;">
                    <i class="bi bi-search fs-4 text-secondary opacity-75"></i>
                </div>
                <h6 class="fw-bold text-dark mb-1">No templates found</h6>
                <p class="text-muted small mb-3" style="max-width: 380px; margin: 0 auto;">
                    Try adjusting your search query or removing filter criteria to find templates.
                </p>
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 shadow-2xs" onclick="resetFilters()">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Clear Filters
                </button>
            </div>
        </div>

        <!-- Templates Data Table Card -->
        <div class="card border rounded-3 overflow-hidden mb-4 bg-white shadow-2xs" id="templatesTableCard">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="templatesTable">
                    <thead class="bg-light text-uppercase text-secondary" style="font-size: 11px; letter-spacing: 0.6px; font-weight: 600;">
                        <tr>
                            <th class="ps-3 ps-md-4 py-3" style="width: 26%;">Template</th>
                            <th class="py-3" style="width: 15%;">Key</th>
                            <th class="py-3" style="width: 13%;">Category</th>
                            <th class="py-3" style="width: 21%;">Trigger / When Sent</th>
                            <th class="py-3 text-center" style="width: 9%;">Status</th>
                            <th class="py-3" style="width: 8%;">Updated</th>
                            <th class="pe-3 pe-md-4 py-3 text-end" style="width: 8%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($templates as $tpl)
                            <tr class="template-row-item" 
                                data-id="{{ $tpl['id'] }}"
                                data-key="{{ strtolower($tpl['key']) }}"
                                data-name="{{ strtolower($tpl['name']) }}"
                                data-desc="{{ strtolower($tpl['description']) }}"
                                data-category="{{ $tpl['category'] }}"
                                data-trigger="{{ strtolower($tpl['trigger_type'] ?? '') }}"
                                data-status="{{ $tpl['is_active'] ? 'active' : 'inactive' }}">
                                
                                <!-- Template Name + Subtitle -->
                                <td class="ps-3 ps-md-4 py-3">
                                    <div class="d-flex align-items-center gap-2.5">
                                        <div class="rounded-2 d-flex align-items-center justify-content-center flex-shrink-0"
                                             style="width: 34px; height: 34px; background-color: rgba(7, 94, 84, 0.08);">
                                            <i class="{{ $tpl['icon'] }} text-success" style="color: #075e54; font-size: 15px;"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="fw-bold text-dark text-truncate" style="font-size: 13.5px;" title="{{ $tpl['name'] }}">
                                                {{ $tpl['name'] }}
                                            </div>
                                            <div class="text-muted small font-monospace text-truncate" style="font-size: 11px;">
                                                {{ $tpl['key'] }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Key Badge -->
                                <td class="py-3">
                                    <div class="d-inline-flex align-items-center gap-1 bg-light border rounded px-2 py-0.5">
                                        <code class="text-secondary font-monospace" style="font-size: 11px;">{{ $tpl['key'] }}</code>
                                        <button type="button" class="btn btn-link p-0 text-muted ms-1 copy-btn" 
                                                onclick="copyToClipboard('{{ $tpl['key'] }}', this)" title="Copy Key">
                                            <i class="bi bi-clipboard" style="font-size: 11px;"></i>
                                        </button>
                                    </div>
                                </td>

                                <!-- Category Badge -->
                                <td class="py-3">
                                    @php
                                        $badgeClass = match($tpl['category']) {
                                            'Authentication' => 'bg-primary-subtle text-primary border-primary-subtle',
                                            'Onboarding' => 'bg-info-subtle text-info border-info-subtle',
                                            'Engagement' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                                            'Growth / Referral' => 'bg-success-subtle text-success border-success-subtle',
                                            'Impact Recognition' => 'bg-danger-subtle text-danger border-danger-subtle',
                                            default => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                                        };
                                    @endphp
                                    <span class="badge {{ $badgeClass }} border rounded px-2 py-0.5" style="font-size: 11px; font-weight: 500;">
                                        {{ $tpl['category'] }}
                                    </span>
                                </td>

                                <!-- Trigger / When Sent -->
                                <td class="py-3">
                                    <div class="d-flex flex-column">
                                        <span class="text-dark fw-semibold" style="font-size: 12.5px;">
                                            {{ $tpl['trigger_type'] }}
                                        </span>
                                        <span class="text-muted" style="font-size: 11.5px; line-height: 1.3;" title="{{ $tpl['when_sent'] }}">
                                            {{ Str::limit($tpl['when_sent'], 65) }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Status (Toggle + Badge) -->
                                <td class="py-3 text-center">
                                    <div class="form-check form-switch d-inline-block mb-0.5">
                                        <input class="form-check-input status-toggle-input cursor-pointer" type="checkbox" role="switch"
                                               id="switch-{{ $tpl['key'] }}"
                                               {{ $tpl['is_active'] ? 'checked' : '' }}
                                               onchange="toggleTemplateStatus('{{ $tpl['id'] }}', this, '{{ $tpl['key'] }}')">
                                    </div>
                                    <div class="status-label-{{ $tpl['key'] }} fw-semibold {{ $tpl['is_active'] ? 'text-success' : 'text-muted' }}" style="font-size: 10px;">
                                        {{ $tpl['is_active'] ? 'Active' : 'Inactive' }}
                                    </div>
                                </td>

                                <!-- Updated At -->
                                <td class="py-3">
                                    <span class="text-muted small" style="font-size: 11.5px;" title="{{ $tpl['updated_at'] ?? '' }}">
                                        {{ $tpl['updated_at_formatted'] ?? 'Recently' }}
                                    </span>
                                </td>

                                <!-- Actions (View as Primary + Edit) -->
                                <td class="pe-3 pe-md-4 py-3 text-end">
                                    <div class="d-inline-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-secondary rounded px-2 py-0.5" 
                                                onclick="openDetailDrawer('{{ $tpl['key'] }}')" title="View Workflow &amp; Details" style="font-size: 11.5px;">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded px-2 py-0.5" 
                                                onclick="openEditModal('{{ $tpl['key'] }}')" title="Edit Configuration" style="font-size: 11.5px; color: #075e54; border-color: #075e54;">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<!-- ======================================================== -->
<!-- 1. TEMPLATE DETAIL RIGHT-SIDE DRAWER (OFFCANVAS) -->
<!-- ======================================================== -->
<div class="offcanvas offcanvas-end shadow-lg border-0" tabindex="-1" id="templateDetailDrawer" 
     aria-labelledby="templateDetailDrawerLabel" style="width: 580px; max-width: 92vw; z-index: 1055;">
    
    <!-- Drawer Header -->
    <div class="offcanvas-header text-white p-4 border-0" style="background: linear-gradient(135deg, #075e54, #128c7e);">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-3 p-2 bg-white text-success d-flex align-items-center justify-content-center shadow-sm" style="width: 44px; height: 44px;">
                <i id="drawerIcon" class="bi bi-whatsapp fs-4" style="color: #075e54;"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h5 class="offcanvas-title fw-bold text-white mb-0" id="drawerTitle">Template Details</h5>
                    <span id="drawerStatusBadge" class="badge bg-success rounded-pill px-2 py-1 small">Active</span>
                </div>
                <div class="d-flex align-items-center gap-2 mt-1">
                    <span id="drawerCategoryBadge" class="badge bg-white-50 text-white rounded-pill px-2 py-0" style="font-size: 10px;">Category</span>
                    <span class="text-white-50 font-monospace small" id="drawerKey">template_key</span>
                </div>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <!-- Drawer Body -->
    <div class="offcanvas-body p-4 bg-light">
        <!-- Inactive Delivery Warning Alert (Visible only when inactive) -->
        <div id="drawerInactiveWarning" class="alert alert-warning border-0 rounded-3 shadow-sm p-3 mb-3 d-none">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill text-warning-emphasis fs-5 me-2"></i>
                <div class="small fw-semibold text-warning-emphasis">
                    WhatsApp delivery is currently disabled for this template.
                </div>
            </div>
        </div>

        <!-- Section 1: "WHEN IS THIS MESSAGE SENT?" (PROMINENT TRIGGER CALLOUT) -->
        <div class="card border rounded-3 mb-3 border-start border-4 border-success bg-white shadow-2xs">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2 text-success fw-bold text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">
                    <i class="bi bi-clock-history fs-5"></i>
                    <span>When is this message sent?</span>
                </div>
                <p class="mb-0 text-dark fw-medium" id="drawerWhenSent" style="font-size: 13.5px; line-height: 1.5;">
                    Trigger information is not configured for this template.
                </p>
            </div>
        </div>

        <!-- Section 2: MESSAGE PURPOSE -->
        <div class="card border rounded-3 mb-3 bg-white shadow-2xs">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2 text-muted fw-bold text-uppercase" style="font-size: 11px; letter-spacing: 0.5px;">
                    <i class="bi bi-info-circle fs-6"></i>
                    <span>Message Purpose</span>
                </div>
                <p class="mb-0 text-secondary" id="drawerDescription" style="font-size: 13px; line-height: 1.5;">
                    No description configured.
                </p>
            </div>
        </div>

        <!-- Section 3: WORKFLOW PIPELINE -->
        <div class="card border rounded-3 mb-3 bg-white shadow-2xs">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2 text-dark fw-bold" style="font-size: 13px;">
                        <i class="bi bi-diagram-3-fill text-primary"></i>
                        <span>End-to-End Workflow</span>
                    </div>
                    <span class="badge bg-light text-secondary border font-monospace" style="font-size: 10px;">Automated Pipeline</span>
                </div>
                
                <!-- Numbered Stepper -->
                <div class="workflow-stepper position-relative" id="drawerWorkflowList">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>
        </div>

        <!-- Section 4: RECIPIENT & MERGE VARIABLES -->
        <div class="row g-3 mb-3">
            <div class="col-md-5">
                <div class="card border rounded-3 h-100 bg-white shadow-2xs">
                    <div class="card-body p-3">
                        <div class="text-muted fw-bold text-uppercase mb-2" style="font-size: 10.5px; letter-spacing: 0.5px;">
                            <i class="bi bi-person-fill me-1"></i> Target Recipient
                        </div>
                        <div class="fw-semibold text-dark" id="drawerRecipient" style="font-size: 13px;">
                            Registered Member
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card border rounded-3 h-100 bg-white shadow-2xs">
                    <div class="card-body p-3">
                        <div class="text-muted fw-bold text-uppercase mb-2" style="font-size: 10.5px; letter-spacing: 0.5px;">
                            <i class="bi bi-code-square me-1"></i> Dynamic Merge Keys
                        </div>
                        <div class="d-flex flex-wrap gap-1" id="drawerVariables">
                            <!-- Dynamic variable badges -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 5: DELIVERY CONFIGURATION & WEBHOOK SECRET -->
        <div class="card border rounded-3 bg-white mb-3 shadow-2xs">
            <div class="card-body p-4">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2 text-dark fw-bold" style="font-size: 13px;">
                        <i class="bi bi-plug-fill text-success"></i>
                        <span>Delivery Configuration</span>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-monospace" style="font-size: 10px;">
                        FlexiMSG Channel
                    </span>
                </div>

                <!-- Webhook URL -->
                <div class="mb-3">
                    <label class="form-label text-muted small fw-semibold mb-1">Webhook Endpoint URL</label>
                    <div class="input-group">
                        <input type="text" class="form-control bg-light font-monospace small" id="drawerWebhookUrl" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyDrawerWebhookUrl()" title="Copy Webhook URL">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                </div>

                <!-- Webhook Secret / API Token (Masked with Reveal) -->
                <div>
                    <label class="form-label text-muted small fw-semibold mb-1">Webhook Secret / API Token</label>
                    <div class="input-group">
                        <input type="password" class="form-control bg-light font-monospace small" id="drawerWebhookSecret" value="••••••••••••••••" readonly>
                        <button class="btn btn-outline-primary" type="button" id="drawerRevealSecretBtn" onclick="revealDrawerSecret()">
                            <i class="bi bi-eye"></i> Reveal
                        </button>
                        <button class="btn btn-outline-secondary" type="button" id="drawerCopySecretBtn" onclick="copyDrawerSecret()" title="Copy Secret">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                    <div class="form-text text-muted small" id="drawerSecretHelpText">
                        Secret remains masked for security. Click Reveal to inspect.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Drawer Footer Actions -->
    <div class="offcanvas-footer p-3 bg-white border-top d-flex justify-content-between align-items-center">
        <button type="button" class="btn btn-light rounded px-3" data-bs-dismiss="offcanvas">
            Close
        </button>
        <button type="button" class="btn text-white rounded px-4 shadow-2xs" id="drawerEditBtn"
                style="background: linear-gradient(135deg, #075e54, #128c7e);">
            <i class="bi bi-pencil me-1"></i> Edit Configuration
        </button>
    </div>
</div>

<!-- ======================================================== -->
<!-- 2. EDIT TEMPLATE CONFIGURATION MODAL (ENTERPRISE ADMIN UI) -->
<!-- ======================================================== -->
<div class="modal fade" id="editTemplateModal" tabindex="-1" aria-labelledby="editTemplateModalLabel" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg edit-modal-dialog">
        <div class="modal-content edit-modal-card border-0 shadow-lg">
            <form id="editTemplateForm" onsubmit="submitEditTemplate(event)">
                @csrf
                <input type="hidden" id="editTemplateId" name="id">

                <!-- 1. Enterprise Executive Header -->
                <div class="modal-header edit-header px-4 py-3 border-bottom bg-white">
                    <div class="d-flex align-items-center justify-content-between w-100 pe-1">
                        <div class="d-flex align-items-center gap-3">
                            <div class="edit-header-icon rounded-3 d-flex align-items-center justify-content-center flex-shrink-0">
                                <i class="bi bi-sliders2-vertical"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-bold text-dark mb-1 tracking-tight" id="editTemplateModalLabel" style="font-size: 15.5px;">
                                    Edit WhatsApp Template
                                </h5>
                                <div class="d-flex align-items-center flex-wrap gap-2 text-muted" style="font-size: 12px;">
                                    <span class="fw-semibold text-dark" id="editModalNameTitle">Template Name</span>
                                    <span class="text-muted opacity-50">&bull;</span>
                                    <code class="edit-system-key-badge font-monospace" id="editModalKeyTitle">template_key</code>
                                    <span class="text-muted opacity-50">&bull;</span>
                                    <span id="editModalCategoryBadge" class="badge edit-category-pill">Category</span>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn-close edit-close-btn shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>

                <!-- 2. Modal Body with Balanced 2-Column Layout -->
                <div class="modal-body p-3.5 bg-slate-50">
                    <!-- Inline Modal Error Alert -->
                    <div id="editModalAlert" class="alert alert-danger border-0 rounded-2 shadow-2xs py-2 px-3 small d-none mb-3">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-exclamation-octagon-fill fs-6 me-2 text-danger"></i>
                            <div id="editModalAlertText">Please fill in all required fields.</div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- LEFT COLUMN: Template Identity & WhatsApp Delivery -->
                        <div class="col-12 col-md-6">
                            <div class="card edit-card h-100 p-3 d-flex flex-column justify-content-between">
                                <!-- Sub-section 1: Template Identity -->
                                <div>
                                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="edit-icon-pill" style="background: rgba(13, 110, 253, 0.08); color: #0d6efd;">
                                                <i class="bi bi-person-badge"></i>
                                            </div>
                                            <div>
                                                <span class="edit-card-title d-block">Template Identity</span>
                                            </div>
                                        </div>
                                        <span class="badge bg-light text-secondary border font-monospace" style="font-size: 10px;">Identity</span>
                                    </div>

                                    <!-- Template Name -->
                                    <div class="mb-3">
                                        <label class="form-label edit-label" for="editTemplateName">
                                            Template Name <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control edit-input" id="editTemplateName" name="template_name" 
                                               placeholder="Enter template name" required>
                                        <div class="edit-hint">Human-readable display name across the admin console.</div>
                                    </div>

                                    <!-- Template Key & Category -->
                                    <div class="row g-2.5 mb-3">
                                        <div class="col-7">
                                            <label class="form-label edit-label d-flex align-items-center gap-1">
                                                <span>Template Key <span class="text-danger">*</span></span>
                                                <i class="bi bi-lock-fill text-muted" style="font-size: 10px;" title="Read only system identifier"></i>
                                            </label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-slate-100 border-end-0 text-muted px-2"><i class="bi bi-shield-lock" style="font-size: 11px;"></i></span>
                                                <input type="text" class="form-control edit-input edit-input-readonly font-monospace border-start-0" 
                                                       id="editTemplateKey" readonly disabled aria-label="Template Key">
                                            </div>
                                        </div>
                                        <div class="col-5">
                                            <label class="form-label edit-label" for="editTemplateCategory">
                                                Category
                                            </label>
                                            <input type="text" class="form-control edit-input edit-input-readonly" 
                                                   id="editTemplateCategory" readonly disabled>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sub-section 2: WhatsApp Delivery -->
                                <div class="pt-2.5 border-top">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="edit-icon-pill" style="background: rgba(25, 135, 84, 0.08); color: #198754;">
                                                <i class="bi bi-broadcast-pin"></i>
                                            </div>
                                            <span class="edit-card-title">WhatsApp Delivery</span>
                                        </div>
                                        <span id="editActiveStatusBadge" class="badge edit-status-pill-active rounded-pill px-2.5 py-1 d-inline-flex align-items-center gap-1.5" style="font-size: 10.5px;">
                                            <span class="status-pulse-dot bg-success" style="width: 6px; height: 6px;"></span> Active
                                        </span>
                                    </div>

                                    <div class="edit-status-box p-2.5">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div class="pe-2">
                                                <span class="fw-semibold text-dark" style="font-size: 12.5px;">Outbound Message Delivery</span>
                                                <p class="edit-hint mb-0 mt-0.5" id="editActiveDesc">
                                                    Messages using this template are currently enabled.
                                                </p>
                                            </div>
                                            <div class="form-check form-switch ms-2 m-0 flex-shrink-0">
                                                <input class="form-check-input cursor-pointer fs-5" type="checkbox" role="switch" 
                                                       id="editIsActive" name="is_active" value="1" checked onchange="updateEditActiveDescription(this)">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT COLUMN: Delivery Configuration & Context -->
                        <div class="col-12 col-md-6">
                            <div class="card edit-card h-100 p-3 d-flex flex-column justify-content-between">
                                <!-- Sub-section 3: Delivery Configuration -->
                                <div>
                                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="edit-icon-pill" style="background: rgba(7, 94, 84, 0.08); color: #075e54;">
                                                <i class="bi bi-hdd-network"></i>
                                            </div>
                                            <div>
                                                <span class="edit-card-title d-block">Delivery Configuration</span>
                                            </div>
                                        </div>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-monospace" style="font-size: 10px;">
                                            FlexiMSG Channel
                                        </span>
                                    </div>

                                    <!-- Webhook Endpoint URL -->
                                    <div class="mb-3">
                                        <label class="form-label edit-label" for="editWebhookUrl">
                                            Webhook Endpoint URL <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-white border-end-0 text-muted ps-2.5"><i class="bi bi-link-45deg"></i></span>
                                            <input type="url" class="form-control edit-input font-monospace border-start-0" 
                                                   id="editWebhookUrl" name="webhook_url" placeholder="https://fleximsg.com/api/webhooks/..." required>
                                        </div>
                                        <div class="edit-hint">Direct webhook URL dispatched on triggered events.</div>
                                    </div>

                                    <!-- Webhook Secret / API Token (MANDATORY & MASKED) -->
                                    <div class="mb-3">
                                        <label class="form-label edit-label" for="editWebhookSecret">
                                            Webhook Secret / API Token <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text bg-white border-end-0 text-muted ps-2.5"><i class="bi bi-key-fill"></i></span>
                                            <input type="password" class="form-control edit-input font-monospace border-start-0 border-end-0" id="editWebhookSecret" name="webhook_secret" 
                                                   placeholder="Enter webhook secret/token" required autocomplete="off" aria-label="Webhook Secret / API Token">
                                            <button class="btn btn-outline-secondary border-start-0 pe-2.5" type="button" id="editWebhookSecretToggleBtn" onclick="togglePasswordVisibility('editWebhookSecret', this)" title="Show Secret" aria-label="Show Secret">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                        </div>
                                        <div class="edit-hint">Secret remains masked for payload signature verification.</div>
                                    </div>
                                </div>

                                <!-- Sub-section 4: Communication Context -->
                                <div class="pt-2.5 border-top">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="edit-icon-pill" style="background: rgba(13, 202, 240, 0.08); color: #0dcaf0;">
                                                <i class="bi bi-chat-text"></i>
                                            </div>
                                            <span class="edit-card-title">Purpose &amp; Description</span>
                                        </div>
                                        <span class="text-muted small" style="font-size: 10.5px;">Context</span>
                                    </div>

                                    <!-- Description -->
                                    <div class="mb-0">
                                        <textarea class="form-control edit-textarea" id="editDescription" name="description" rows="2" 
                                                  placeholder="Explain what this template communicates to members..."></textarea>
                                        <div class="edit-hint mt-1">Operational notes describing the member journey trigger.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Sticky Action Footer -->
                <div class="modal-footer edit-modal-footer px-3.5 py-2.5 bg-white border-top d-flex justify-content-between align-items-center">
                    <div>
                        <button type="button" class="btn btn-light border edit-btn-cancel rounded-2 px-3 py-1.5 text-secondary" data-bs-dismiss="modal">
                            Cancel
                        </button>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div id="editChangeRequirementHint" class="text-muted small" style="font-size: 11.5px;">
                            <i class="bi bi-info-circle me-1"></i>Complete all 3 required fields
                        </div>
                        <button type="submit" class="btn edit-btn-save text-white rounded-2 px-3.5 py-1.5 shadow-2xs" id="editSubmitBtn" disabled>
                            <span id="editBtnSpinner" class="spinner-border spinner-border-sm d-none me-1" role="status"></span>
                            <i class="bi bi-check2-circle me-1" id="editBtnIcon"></i> <span id="editSubmitBtnText">Save Changes</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- TOAST NOTIFICATION CONTAINER -->
<!-- ======================================================== -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1090;">
    <div id="actionToast" class="toast align-items-center text-white border-0 shadow-lg rounded-3" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2" id="toastMessage">
                <i class="bi bi-check-circle-fill"></i> Action completed successfully.
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // In-memory templates cache from server rendering for instantaneous zero-lag drawer views
    const templatesData = @json($templates ?? []);
    const templatesMap = {};
    templatesData.forEach(t => {
        templatesMap[t.key] = t;
        templatesMap[t.id] = t;
    });

    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    let currentDrawerTemplate = null;
    let currentDrawerSecretRevealed = false;
    let editOriginalValues = null;

    /**
     * Show Toast Notification
     */
    function showToast(message, isError = false) {
        const toastEl = document.getElementById('actionToast');
        const toastMsg = document.getElementById('toastMessage');
        
        toastEl.className = `toast align-items-center text-white border-0 shadow-lg rounded-3 ${isError ? 'bg-danger' : 'bg-dark'}`;
        toastMsg.innerHTML = `<i class="bi ${isError ? 'bi-exclamation-circle-fill' : 'bi-check-circle-fill'}"></i> ${message}`;
        
        const toast = new bootstrap.Toast(toastEl, { delay: 3500 });
        toast.show();
    }

    /**
     * 1-Click Clipboard Copy
     */
    function copyToClipboard(text, btnElement) {
        if (!text) return;
        navigator.clipboard.writeText(text).then(() => {
            showToast(`Copied: "${text}"`);
            if (btnElement) {
                const icon = btnElement.querySelector('i');
                if (icon) {
                    icon.className = 'bi bi-check-lg text-success';
                    setTimeout(() => {
                        icon.className = 'bi bi-clipboard';
                    }, 2000);
                }
            }
        }).catch(err => {
            console.error('Failed to copy text: ', err);
            showToast('Failed to copy text.', true);
        });
    }

    /**
     * Open Template Detail Drawer (Offcanvas)
     */
    function openDetailDrawer(idOrKey) {
        const tpl = templatesMap[idOrKey];
        if (!tpl) {
            showToast('Template details not found.', true);
            return;
        }

        currentDrawerTemplate = tpl;
        currentDrawerSecretRevealed = false;

        // Populate drawer elements immediately with zero loading lag
        document.getElementById('drawerTitle').innerText = tpl.name || 'WhatsApp Template';
        document.getElementById('drawerKey').innerText = tpl.key;
        document.getElementById('drawerCategoryBadge').innerText = tpl.category || 'Other';
        document.getElementById('drawerWhenSent').innerText = tpl.when_sent || 'Trigger details are not configured for this template.';
        document.getElementById('drawerDescription').innerText = tpl.description || 'No description configured.';
        document.getElementById('drawerRecipient').innerText = tpl.recipient || 'Registered Member';
        document.getElementById('drawerWebhookUrl').value = tpl.webhook_url || '';
        document.getElementById('drawerWebhookSecret').value = tpl.has_secret ? '••••••••••••••••' : 'No webhook secret configured';
        document.getElementById('drawerWebhookSecret').type = 'password';
        document.getElementById('drawerRevealSecretBtn').innerHTML = '<i class="bi bi-eye"></i> Reveal';
        document.getElementById('drawerSecretHelpText').innerText = tpl.has_secret 
            ? 'Secret remains masked for security. Click Reveal to inspect.' 
            : 'No webhook secret is currently stored for this template.';

        // Inactive Delivery Warning banner
        const inactiveWarning = document.getElementById('drawerInactiveWarning');
        if (inactiveWarning) {
            if (tpl.is_active) {
                inactiveWarning.classList.add('d-none');
            } else {
                inactiveWarning.classList.remove('d-none');
            }
        }

        // Status badge in drawer header
        const statusBadge = document.getElementById('drawerStatusBadge');
        if (tpl.is_active) {
            statusBadge.className = 'badge bg-success rounded-pill px-2 py-1 small';
            statusBadge.innerText = 'Active';
        } else {
            statusBadge.className = 'badge bg-danger rounded-pill px-2 py-1 small';
            statusBadge.innerText = 'Inactive';
        }

        // Merge Variables List
        const varContainer = document.getElementById('drawerVariables');
        varContainer.innerHTML = '';
        if (tpl.variables && Object.keys(tpl.variables).length > 0) {
            for (const [vKey, vDesc] of Object.entries(tpl.variables)) {
                const span = document.createElement('span');
                span.className = 'badge bg-light text-dark border font-monospace';
                span.style.fontSize = '11px';
                span.style.padding = '4px 8px';
                span.title = vDesc;
                span.innerText = vKey.startsWith('@') ? vKey : `@${vKey}`;
                varContainer.appendChild(span);
            }
        } else {
            varContainer.innerHTML = '<span class="text-muted small">No documented merge variables available.</span>';
        }

        // Workflow Stepper
        const workflowContainer = document.getElementById('drawerWorkflowList');
        workflowContainer.innerHTML = '';
        if (tpl.workflow_steps && tpl.workflow_steps.length > 0) {
            tpl.workflow_steps.forEach((step, idx) => {
                const stepEl = document.createElement('div');
                stepEl.className = 'd-flex align-items-start gap-3 mb-3 position-relative';
                stepEl.innerHTML = `
                    <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center flex-shrink-0 fw-bold shadow-sm"
                         style="width: 26px; height: 26px; font-size: 11px; z-index: 2;">
                        ${idx + 1}
                    </div>
                    <div class="flex-grow-1 min-w-0 bg-light rounded-3 p-2.5 border">
                        <div class="fw-bold text-dark" style="font-size: 12.5px;">${step.title}</div>
                        <div class="text-muted small" style="font-size: 11.5px;">${step.desc}</div>
                    </div>
                `;
                workflowContainer.appendChild(stepEl);
            });
        }

        // Wireup drawer edit button
        const drawerEl = document.getElementById('templateDetailDrawer');
        const offcanvas = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);

        document.getElementById('drawerEditBtn').onclick = () => {
            offcanvas.hide();
            setTimeout(() => openEditModal(tpl.key), 300);
        };

        offcanvas.show();
    }

    /**
     * Reveal Webhook Secret in Drawer
     */
    function revealDrawerSecret() {
        if (!currentDrawerTemplate) return;

        const secretInput = document.getElementById('drawerWebhookSecret');
        const revealBtn = document.getElementById('drawerRevealSecretBtn');

        if (currentDrawerSecretRevealed) {
            secretInput.type = 'password';
            secretInput.value = currentDrawerTemplate.has_secret ? '••••••••••••••••' : 'No webhook secret configured';
            revealBtn.innerHTML = '<i class="bi bi-eye"></i> Reveal';
            currentDrawerSecretRevealed = false;
            return;
        }

        revealBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>';

        fetch(`/admin/whatsapp-templates/${encodeURIComponent(currentDrawerTemplate.id)}/reveal-secret`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server returned HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(res => {
            if (res.success) {
                if (res.has_secret && res.secret) {
                    secretInput.type = 'text';
                    secretInput.value = res.secret;
                    revealBtn.innerHTML = '<i class="bi bi-eye-slash"></i> Hide';
                    currentDrawerSecretRevealed = true;
                    showToast('Webhook secret revealed.');
                } else {
                    secretInput.type = 'text';
                    secretInput.value = 'No webhook secret configured';
                    revealBtn.innerHTML = '<i class="bi bi-eye"></i> Reveal';
                    showToast('No webhook secret configured for this template.');
                }
            } else {
                showToast(res.message || 'Could not reveal webhook secret.', true);
                revealBtn.innerHTML = '<i class="bi bi-eye"></i> Reveal';
            }
        })
        .catch(err => {
            console.error('Error revealing secret:', err);
            revealBtn.innerHTML = '<i class="bi bi-eye"></i> Reveal';
            showToast('Failed to reveal secret. Please verify permissions.', true);
        });
    }

    function copyDrawerWebhookUrl() {
        const val = document.getElementById('drawerWebhookUrl').value;
        copyToClipboard(val);
    }

    function copyDrawerSecret() {
        if (!currentDrawerTemplate) return;

        const secretInput = document.getElementById('drawerWebhookSecret');
        if (currentDrawerSecretRevealed && secretInput.value && secretInput.value !== 'No webhook secret configured') {
            copyToClipboard(secretInput.value);
            return;
        }
        
        fetch(`/admin/whatsapp-templates/${encodeURIComponent(currentDrawerTemplate.id)}/reveal-secret`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(res => {
            if (res.success && res.has_secret && res.secret) {
                copyToClipboard(res.secret);
            } else {
                showToast('No webhook secret configured to copy.', true);
            }
        })
        .catch(() => {
            showToast('Could not retrieve secret for copying.', true);
        });
    }

    /**
     * Update active description and badge in Edit modal on switch change
     */
    function updateEditActiveDescription(switchEl) {
        const descEl = document.getElementById('editActiveDesc');
        const badgeEl = document.getElementById('editActiveStatusBadge');
        if (descEl) {
            descEl.innerText = switchEl.checked 
                ? 'Messages using this template are currently enabled.' 
                : 'Outgoing WhatsApp notifications for this template will be skipped.';
        }
        if (badgeEl) {
            if (switchEl.checked) {
                badgeEl.className = 'badge edit-status-pill-active rounded-pill px-2.5 py-1 d-inline-flex align-items-center gap-1.5';
                badgeEl.innerHTML = '<span class="status-pulse-dot bg-success" style="width: 6px; height: 6px;"></span> Active';
            } else {
                badgeEl.className = 'badge edit-status-pill-inactive rounded-pill px-2.5 py-1 d-inline-flex align-items-center gap-1.5';
                badgeEl.innerHTML = '<span class="status-dot-inactive"></span> Inactive';
            }
        }
    }

    /**
     * Check if all 3 required configuration fields have changed from their original database values
     */
    function updateSaveButtonState() {
        if (!editOriginalValues) return false;

        const nameInput = document.getElementById('editTemplateName');
        const urlInput = document.getElementById('editWebhookUrl');
        const secretInput = document.getElementById('editWebhookSecret');
        const submitBtn = document.getElementById('editSubmitBtn');
        const hintEl = document.getElementById('editChangeRequirementHint');

        const curName = (nameInput?.value || '').trim();
        const curUrl = (urlInput?.value || '').trim();
        const curSecret = (secretInput?.value || '').trim();

        const origName = (editOriginalValues.template_name || '').trim();
        const origUrl = (editOriginalValues.webhook_url || '').trim();
        const origSecret = (editOriginalValues.webhook_secret || '').trim();

        const templateNameChanged = (curName !== '' && curName !== origName);
        const webhookUrlChanged = (curUrl !== '' && curUrl !== origUrl);
        const webhookSecretChanged = (curSecret !== '' && curSecret !== origSecret);

        let changedCount = 0;
        if (templateNameChanged) changedCount++;
        if (webhookUrlChanged) changedCount++;
        if (webhookSecretChanged) changedCount++;

        const allThreeChanged = (changedCount === 3);

        if (submitBtn) {
            submitBtn.disabled = !allThreeChanged;
            submitBtn.title = allThreeChanged ? 'Save Changes' : 'Update all three configuration fields to enable saving.';
        }

        if (hintEl) {
            if (allThreeChanged) {
                hintEl.className = 'text-success small mb-0 fw-semibold';
                hintEl.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i>3 of 3 required fields ready';
            } else if (changedCount > 0) {
                hintEl.className = 'text-warning-emphasis small mb-0 fw-semibold';
                hintEl.innerHTML = `<i class="bi bi-exclamation-circle-fill me-1"></i>Complete all 3 required fields (${changedCount} of 3 modified)`;
            } else {
                hintEl.className = 'text-muted small mb-0';
                hintEl.innerHTML = '<i class="bi bi-info-circle me-1"></i>Complete all 3 required fields';
            }
        }

        return allThreeChanged;
    }

    /**
     * Open Edit Template Modal
     */
    function openEditModal(idOrKey) {
        const tpl = templatesMap[idOrKey];
        if (!tpl) {
            showToast('Template not found.', true);
            return;
        }

        // Reset inline alert
        const alertEl = document.getElementById('editModalAlert');
        if (alertEl) alertEl.classList.add('d-none');

        document.getElementById('editTemplateId').value = tpl.id;
        if (document.getElementById('editModalNameTitle')) {
            document.getElementById('editModalNameTitle').innerText = tpl.name || 'WhatsApp Template';
        }
        document.getElementById('editModalKeyTitle').innerText = tpl.key;
        document.getElementById('editModalCategoryBadge').innerText = tpl.category || 'General';
        document.getElementById('editTemplateKey').value = tpl.key;
        document.getElementById('editTemplateName').value = tpl.name || '';
        document.getElementById('editTemplateCategory').value = tpl.category || 'General';
        document.getElementById('editDescription').value = tpl.description || '';
        document.getElementById('editWebhookUrl').value = tpl.webhook_url || '';
        
        // Reset secret input to masked password by default
        const secretInput = document.getElementById('editWebhookSecret');
        secretInput.value = '';
        secretInput.type = 'password';
        secretInput.placeholder = 'Loading current secret from database...';

        // Reset toggle button to eye icon (Hidden state)
        const toggleBtn = document.getElementById('editWebhookSecretToggleBtn');
        if (toggleBtn) {
            toggleBtn.title = 'Show Secret';
            toggleBtn.setAttribute('aria-label', 'Show Secret');
            const icon = toggleBtn.querySelector('i');
            if (icon) icon.className = 'bi bi-eye';
        }
        
        const activeSwitch = document.getElementById('editIsActive');
        activeSwitch.checked = !!tpl.is_active;
        updateEditActiveDescription(activeSwitch);

        editOriginalValues = {
            template_name: (tpl.name || '').trim(),
            webhook_url: (tpl.webhook_url || '').trim(),
            webhook_secret: '',
            description: (tpl.description || '').trim(),
            is_active: !!tpl.is_active
        };

        const submitBtn = document.getElementById('editSubmitBtn');
        if (submitBtn) submitBtn.disabled = true;

        const hintEl = document.getElementById('editChangeRequirementHint');
        if (hintEl) {
            hintEl.className = 'text-muted small mb-0';
            hintEl.innerHTML = '<i class="bi bi-info-circle me-1"></i>Complete all 3 required fields';
        }

        // Fetch current database secret and details via authenticated edit endpoint
        fetch(`/admin/whatsapp-templates/${encodeURIComponent(tpl.id)}/edit`, {
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response.json();
        })
        .then(res => {
            secretInput.placeholder = 'Enter webhook secret/token';
            if (res.success && res.webhook_secret !== undefined) {
                secretInput.value = res.webhook_secret || '';
                if (editOriginalValues) {
                    editOriginalValues.webhook_secret = (res.webhook_secret || '').trim();
                }
            }
            updateSaveButtonState();
        })
        .catch(err => {
            console.error('Failed to load webhook secret from database:', err);
            secretInput.placeholder = 'Enter webhook secret/token';
            updateSaveButtonState();
        });

        const modal = new bootstrap.Modal(document.getElementById('editTemplateModal'));
        modal.show();
    }

    /**
     * Submit Template Update via AJAX
     */
    function submitEditTemplate(event) {
        event.preventDefault();

        const form = document.getElementById('editTemplateForm');
        const templateId = document.getElementById('editTemplateId').value;
        const submitBtn = document.getElementById('editSubmitBtn');
        const submitText = document.getElementById('editSubmitBtnText');
        const spinner = document.getElementById('editBtnSpinner');
        const icon = document.getElementById('editBtnIcon');
        const alertEl = document.getElementById('editModalAlert');
        const alertText = document.getElementById('editModalAlertText');

        // Reset alert
        if (alertEl) alertEl.classList.add('d-none');

        const nameInput = document.getElementById('editTemplateName');
        const urlInput = document.getElementById('editWebhookUrl');
        const secretInput = document.getElementById('editWebhookSecret');
        const descInput = document.getElementById('editDescription');
        const activeInput = document.getElementById('editIsActive');

        const nameVal = (nameInput.value || '').trim();
        const urlVal = (urlInput.value || '').trim();
        const secretVal = (secretInput.value || '').trim();
        const descVal = (descInput?.value || '').trim();
        const isActiveVal = !!activeInput?.checked;

        // 1. Validate Template Name
        if (nameVal === '') {
            if (alertEl) {
                alertText.innerText = 'Template Name is required.';
                alertEl.classList.remove('d-none');
            }
            nameInput.focus();
            return;
        }

        // 2. Validate Webhook URL
        if (urlVal === '') {
            if (alertEl) {
                alertText.innerText = 'Webhook Endpoint URL is required.';
                alertEl.classList.remove('d-none');
            }
            urlInput.focus();
            return;
        }

        try {
            const parsedUrl = new URL(urlVal);
            if (!['http:', 'https:'].includes(parsedUrl.protocol)) {
                throw new Error('Invalid URL protocol');
            }
        } catch (e) {
            if (alertEl) {
                alertText.innerText = 'Please provide a valid URL format for the webhook endpoint (e.g. https://fleximsg.com/api/webhooks/...).';
                alertEl.classList.remove('d-none');
            }
            urlInput.focus();
            return;
        }

        // 3. Validate Webhook Secret
        if (secretVal === '') {
            if (alertEl) {
                alertText.innerText = 'Webhook Secret / API Token is required.';
                alertEl.classList.remove('d-none');
            }
            secretInput.focus();
            return;
        }

        // 4. Enforce that ALL THREE required fields have changed from original DB values
        const origName = (editOriginalValues?.template_name || '').trim();
        const origUrl = (editOriginalValues?.webhook_url || '').trim();
        const origSecret = (editOriginalValues?.webhook_secret || '').trim();

        const templateNameChanged = (nameVal !== origName);
        const webhookUrlChanged = (urlVal !== origUrl);
        const webhookSecretChanged = (secretVal !== origSecret);

        if (! (templateNameChanged && webhookUrlChanged && webhookSecretChanged)) {
            if (alertEl) {
                alertText.innerText = 'Please update all three fields before saving.';
                alertEl.classList.remove('d-none');
            }
            showToast('Please update all three fields before saving.', true);
            return;
        }

        submitBtn.disabled = true;
        if (submitText) submitText.innerText = 'Saving...';
        spinner.classList.remove('d-none');
        icon.classList.add('d-none');

        const formData = new FormData(form);
        formData.set('template_name', nameVal);
        formData.set('webhook_url', urlVal);
        formData.set('webhook_secret', secretVal);
        formData.set('is_active', document.getElementById('editIsActive').checked ? '1' : '0');

        fetch(`/admin/whatsapp-templates/${encodeURIComponent(templateId)}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-HTTP-Method-Override': 'PUT'
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(errData => {
                    const validationErrors = errData.errors ? Object.values(errData.errors).flat().join(' ') : null;
                    throw new Error(validationErrors || errData.message || `HTTP ${response.status}`);
                });
            }
            return response.json();
        })
        .then(res => {
            submitBtn.disabled = false;
            if (submitText) submitText.innerText = 'Save Changes';
            spinner.classList.add('d-none');
            icon.classList.remove('d-none');

            if (res.success) {
                showToast(res.message || 'WhatsApp template updated successfully.');
                
                // Hide Modal
                const modalEl = document.getElementById('editTemplateModal');
                const modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) modalInstance.hide();

                // Update in-memory template details
                if (res.template) {
                    const updated = res.template;
                    templatesMap[updated.id] = updated;
                    templatesMap[updated.key] = updated;

                    // Update corresponding table row DOM in place
                    const row = document.querySelector(`.template-row-item[data-id="${updated.id}"]`);
                    if (row) {
                        row.setAttribute('data-name', (updated.name || '').toLowerCase());
                        row.setAttribute('data-desc', (updated.description || '').toLowerCase());
                        row.setAttribute('data-status', updated.is_active ? 'active' : 'inactive');

                        const nameTitleEl = row.querySelector('.fw-bold.text-dark.text-truncate');
                        if (nameTitleEl) {
                            nameTitleEl.innerText = updated.name;
                            nameTitleEl.title = updated.name;
                        }

                        const statusSwitch = row.querySelector('.status-toggle-input');
                        if (statusSwitch) statusSwitch.checked = !!updated.is_active;

                        const statusLabel = row.querySelector(`.status-label-${updated.key}`);
                        if (statusLabel) {
                            statusLabel.innerText = updated.is_active ? 'Active' : 'Inactive';
                            statusLabel.className = `status-label-${updated.key} fw-semibold ${updated.is_active ? 'text-success' : 'text-muted'}`;
                        }
                    }

                    // Re-calculate KPI Active/Inactive counts dynamically
                    let totalActive = 0;
                    let totalInactive = 0;
                    const distinctIds = new Set();
                    Object.values(templatesMap).forEach(t => {
                        if (!distinctIds.has(t.id)) {
                            distinctIds.add(t.id);
                            if (t.is_active) totalActive++;
                            else totalInactive++;
                        }
                    });

                    const kpiActive = document.getElementById('kpiActive');
                    if (kpiActive) kpiActive.innerText = totalActive;

                    const kpiInactive = document.getElementById('kpiInactive');
                    if (kpiInactive) kpiInactive.innerText = totalInactive;
                }
            } else {
                if (alertEl) {
                    alertText.innerText = res.message || 'Failed to update template.';
                    alertEl.classList.remove('d-none');
                }
                showToast(res.message || 'Failed to update template.', true);
            }
        })
        .catch(err => {
            submitBtn.disabled = false;
            if (submitText) submitText.innerText = 'Save Changes';
            spinner.classList.add('d-none');
            icon.classList.remove('d-none');
            console.error(err);
            if (alertEl) {
                alertText.innerText = err.message || 'Error updating template.';
                alertEl.classList.remove('d-none');
            }
            showToast(err.message || 'Error updating template.', true);
        });
    }

    /**
     * Toggle Active/Inactive Status Switch
     */
    function toggleTemplateStatus(id, checkboxEl, key) {
        const isChecked = checkboxEl.checked;
        const labelEl = document.querySelector(`.status-label-${key}`);

        fetch(`/admin/whatsapp-templates/${encodeURIComponent(id)}/toggle-status`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF_TOKEN,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                showToast(res.message);
                if (labelEl) {
                    labelEl.innerText = res.status_label;
                    labelEl.className = `status-label-${key} fw-semibold ${res.is_active ? 'text-success' : 'text-muted'}`;
                }
                const row = checkboxEl.closest('.template-row-item');
                if (row) {
                    row.setAttribute('data-status', res.is_active ? 'active' : 'inactive');
                }
                // Update local cache
                if (templatesMap[id]) templatesMap[id].is_active = res.is_active;
                if (templatesMap[key]) templatesMap[key].is_active = res.is_active;

                // Dynamically refresh KPI counts
                let totalActive = 0;
                let totalInactive = 0;
                const distinctIds = new Set();
                Object.values(templatesMap).forEach(t => {
                    if (!distinctIds.has(t.id)) {
                        distinctIds.add(t.id);
                        if (t.is_active) totalActive++;
                        else totalInactive++;
                    }
                });

                const kpiActive = document.getElementById('kpiActive');
                if (kpiActive) kpiActive.innerText = totalActive;

                const kpiInactive = document.getElementById('kpiInactive');
                if (kpiInactive) kpiInactive.innerText = totalInactive;
            } else {
                checkboxEl.checked = !isChecked;
                showToast('Failed to toggle status.', true);
            }
        })
        .catch(err => {
            checkboxEl.checked = !isChecked;
            console.error(err);
            showToast('Error updating status.', true);
        });
    }

    /**
     * Handle real-time Search Input
     */
    function handleSearchInput(inputEl) {
        const clearBtn = document.getElementById('searchClearBtn');
        if (clearBtn) {
            if (inputEl.value && inputEl.value.trim().length > 0) {
                clearBtn.classList.remove('d-none');
            } else {
                clearBtn.classList.add('d-none');
            }
        }
        filterTemplates();
    }

    /**
     * Clear Search Input
     */
    function clearSearchInput() {
        const searchInput = document.getElementById('templateSearch');
        if (searchInput) {
            searchInput.value = '';
            searchInput.focus();
        }
        const clearBtn = document.getElementById('searchClearBtn');
        if (clearBtn) clearBtn.classList.add('d-none');
        filterTemplates();
    }

    /**
     * Individual Filter Clears for Chips
     */
    function clearCategoryFilter() {
        const el = document.getElementById('categoryFilter');
        if (el) el.value = 'all';
        filterTemplates();
    }

    function clearTriggerFilter() {
        const el = document.getElementById('triggerFilter');
        if (el) el.value = 'all';
        filterTemplates();
    }

    function clearStatusFilter() {
        const el = document.getElementById('statusFilter');
        if (el) el.value = 'all';
        filterTemplates();
    }

    /**
     * Client-Side Search & Multi-Filter Logic
     */
    function filterTemplates() {
        const searchInput = document.getElementById('templateSearch');
        const q = (searchInput?.value || '').toLowerCase().trim();
        const statusSelect = document.getElementById('statusFilter');
        const categorySelect = document.getElementById('categoryFilter');
        const triggerSelect = document.getElementById('triggerFilter');

        const status = statusSelect?.value || 'all';
        const category = categorySelect?.value || 'all';
        const trigger = triggerSelect?.value || 'all';

        // Visual active indicator on select dropdowns
        if (categorySelect) {
            if (category !== 'all') categorySelect.classList.add('toolbar-select-active');
            else categorySelect.classList.remove('toolbar-select-active');
        }
        if (triggerSelect) {
            if (trigger !== 'all') triggerSelect.classList.add('toolbar-select-active');
            else triggerSelect.classList.remove('toolbar-select-active');
        }
        if (statusSelect) {
            if (status !== 'all') statusSelect.classList.add('toolbar-select-active');
            else statusSelect.classList.remove('toolbar-select-active');
        }

        const rows = document.querySelectorAll('.template-row-item');
        const totalCount = rows.length;
        let visibleCount = 0;

        rows.forEach(row => {
            const name = row.getAttribute('data-name') || '';
            const key = row.getAttribute('data-key') || '';
            const desc = row.getAttribute('data-desc') || '';
            const rowCategory = row.getAttribute('data-category') || '';
            const rowTrigger = (row.getAttribute('data-trigger') || '').toLowerCase();
            const rowStatus = row.getAttribute('data-status') || '';

            let matchesSearch = true;
            if (q) {
                matchesSearch = name.includes(q) || key.includes(q) || desc.includes(q);
            }

            let matchesStatus = true;
            if (status !== 'all') {
                matchesStatus = (rowStatus === status);
            }

            let matchesCategory = true;
            if (category !== 'all') {
                matchesCategory = (rowCategory.toLowerCase() === category.toLowerCase());
            }

            let matchesTrigger = true;
            if (trigger !== 'all') {
                matchesTrigger = (rowTrigger === trigger.toLowerCase());
            }

            if (matchesSearch && matchesStatus && matchesCategory && matchesTrigger) {
                row.classList.remove('d-none');
                visibleCount++;
            } else {
                row.classList.add('d-none');
            }
        });

        // Dynamic Result Count Display
        const countContainer = document.getElementById('filterResultCountText');
        const isFiltered = (q !== '' || category !== 'all' || trigger !== 'all' || status !== 'all');

        if (countContainer) {
            if (!isFiltered) {
                countContainer.innerHTML = `Showing all <strong class="text-dark">${totalCount}</strong> templates`;
            } else if (visibleCount === 0) {
                countContainer.innerHTML = `<span class="text-danger fw-semibold">No templates match current filters</span> <span class="text-muted">(0 of ${totalCount})</span>`;
            } else {
                countContainer.innerHTML = `Showing <strong class="text-dark" id="visibleTemplateCount">${visibleCount}</strong> of <strong class="text-dark">${totalCount}</strong> templates`;
            }
        }

        const tableCard = document.getElementById('templatesTableCard');
        const emptyState = document.getElementById('noResultsState');

        if (visibleCount === 0 && totalCount > 0) {
            tableCard?.classList.add('d-none');
            emptyState?.classList.remove('d-none');
        } else {
            tableCard?.classList.remove('d-none');
            emptyState?.classList.add('d-none');
        }

        // Render Active Filter Chips & Update Reset Button
        renderActiveFilterChips(q, category, trigger, status);
    }

    /**
     * Render Active Filter Chips
     */
    function renderActiveFilterChips(q, category, trigger, status) {
        const chipsContainer = document.getElementById('activeFilterChips');
        const summaryEl = document.getElementById('activeFiltersSummary');
        const resetBtn = document.getElementById('resetFiltersBtn');
        if (!chipsContainer) return;

        chipsContainer.innerHTML = '';
        let activeCount = 0;

        if (q) {
            activeCount++;
            const chip = document.createElement('span');
            chip.className = 'filter-chip d-inline-flex align-items-center gap-1.5';
            chip.innerHTML = `<span class="text-muted">Search:</span> <strong class="text-dark">"${q}"</strong> <i class="bi bi-x-circle-fill ms-1 filter-chip-remove" onclick="clearSearchInput()" title="Remove search filter" role="button" aria-label="Remove search filter"></i>`;
            chipsContainer.appendChild(chip);
        }

        if (category !== 'all') {
            activeCount++;
            const chip = document.createElement('span');
            chip.className = 'filter-chip d-inline-flex align-items-center gap-1.5';
            chip.innerHTML = `<span class="text-muted">Category:</span> <strong class="text-dark">${category}</strong> <i class="bi bi-x-circle-fill ms-1 filter-chip-remove" onclick="clearCategoryFilter()" title="Remove category filter" role="button" aria-label="Remove category filter"></i>`;
            chipsContainer.appendChild(chip);
        }

        if (trigger !== 'all') {
            activeCount++;
            const chip = document.createElement('span');
            chip.className = 'filter-chip d-inline-flex align-items-center gap-1.5';
            chip.innerHTML = `<span class="text-muted">Trigger:</span> <strong class="text-dark">${trigger}</strong> <i class="bi bi-x-circle-fill ms-1 filter-chip-remove" onclick="clearTriggerFilter()" title="Remove trigger filter" role="button" aria-label="Remove trigger filter"></i>`;
            chipsContainer.appendChild(chip);
        }

        if (status !== 'all') {
            activeCount++;
            const statusLabel = status === 'active' ? 'Active' : 'Inactive';
            const chip = document.createElement('span');
            chip.className = 'filter-chip d-inline-flex align-items-center gap-1.5';
            chip.innerHTML = `<span class="text-muted">Status:</span> <strong class="text-dark">${statusLabel}</strong> <i class="bi bi-x-circle-fill ms-1 filter-chip-remove" onclick="clearStatusFilter()" title="Remove status filter" role="button" aria-label="Remove status filter"></i>`;
            chipsContainer.appendChild(chip);
        }

        if (summaryEl) {
            if (activeCount > 0) {
                summaryEl.innerHTML = `<i class="bi bi-funnel-fill me-1"></i>${activeCount} ${activeCount === 1 ? 'filter' : 'filters'} active`;
                summaryEl.classList.remove('d-none');
            } else {
                summaryEl.classList.add('d-none');
            }
        }

        if (resetBtn) {
            if (activeCount > 0) {
                resetBtn.disabled = false;
                resetBtn.classList.remove('btn-light', 'text-muted', 'opacity-60');
                resetBtn.classList.add('btn-outline-secondary', 'text-dark', 'fw-medium');
                resetBtn.innerHTML = `<i class="bi bi-arrow-counterclockwise me-1 text-danger"></i> Clear Filters (${activeCount})`;
            } else {
                resetBtn.disabled = true;
                resetBtn.classList.add('btn-light', 'text-muted', 'opacity-60');
                resetBtn.classList.remove('btn-outline-secondary', 'text-dark', 'fw-medium');
                resetBtn.innerHTML = `<i class="bi bi-arrow-counterclockwise me-1"></i> Clear Filters`;
            }
        }
    }

    function resetFilters() {
        if (document.getElementById('templateSearch')) document.getElementById('templateSearch').value = '';
        const searchClearBtn = document.getElementById('searchClearBtn');
        if (searchClearBtn) searchClearBtn.classList.add('d-none');

        if (document.getElementById('statusFilter')) document.getElementById('statusFilter').value = 'all';
        if (document.getElementById('categoryFilter')) document.getElementById('categoryFilter').value = 'all';
        if (document.getElementById('triggerFilter')) document.getElementById('triggerFilter').value = 'all';
        filterTemplates();
    }

    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) icon.className = 'bi bi-eye-slash';
            btn.title = 'Hide Secret';
            btn.setAttribute('aria-label', 'Hide Secret');
        } else {
            input.type = 'password';
            if (icon) icon.className = 'bi bi-eye';
            btn.title = 'Show Secret';
            btn.setAttribute('aria-label', 'Show Secret');
        }
    }

    // Ensure Edit modal always resets secret visibility to hidden on close and binds real-time change detection
    document.addEventListener('DOMContentLoaded', () => {
        const editModalEl = document.getElementById('editTemplateModal');
        if (editModalEl) {
            editModalEl.addEventListener('hidden.bs.modal', () => {
                const secretInput = document.getElementById('editWebhookSecret');
                if (secretInput) {
                    secretInput.type = 'password';
                    secretInput.value = '';
                }
                const toggleBtn = document.getElementById('editWebhookSecretToggleBtn');
                if (toggleBtn) {
                    toggleBtn.title = 'Show Secret';
                    toggleBtn.setAttribute('aria-label', 'Show Secret');
                    const icon = toggleBtn.querySelector('i');
                    if (icon) icon.className = 'bi bi-eye';
                }
                const alertEl = document.getElementById('editModalAlert');
                if (alertEl) alertEl.classList.add('d-none');
            });
        }

        ['editTemplateName', 'editWebhookUrl', 'editWebhookSecret'].forEach(id => {
            const input = document.getElementById(id);
            if (input) {
                input.addEventListener('input', updateSaveButtonState);
                input.addEventListener('keyup', updateSaveButtonState);
                input.addEventListener('change', updateSaveButtonState);
            }
        });
    });
</script>

<style>
    .cursor-pointer {
        cursor: pointer;
    }
    .shadow-2xs {
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    }
    .status-pulse-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        box-shadow: 0 0 0 2px rgba(21, 128, 61, 0.2);
    }
    
    /* ======================================================== */
    /* ENTERPRISE DASHBOARD KPI SUMMARY CARDS */
    /* ======================================================== */
    .kpi-card {
        border: 1px solid #e2e8f0 !important;
        border-radius: 10px !important;
        background-color: #ffffff !important;
        transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 14px -2px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.02);
        border-color: #cbd5e1 !important;
    }
    .kpi-eyebrow {
        font-size: 11px;
        letter-spacing: 0.6px;
        text-transform: uppercase;
        color: #64748b;
    }
    .kpi-icon-box {
        width: 30px;
        height: 30px;
        border-radius: 7px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 13.5px;
        background-color: #f8fafc;
        color: #64748b;
        border: 1px solid #e2e8f0;
        transition: border-color 0.15s ease, color 0.15s ease;
    }
    .kpi-card:hover .kpi-icon-box {
        border-color: #cbd5e1;
        color: #334155;
    }
    .kpi-value {
        font-size: 28px;
        font-weight: 700;
        line-height: 1.1;
        letter-spacing: -0.5px;
        color: #0f172a;
    }
    .kpi-footer {
        font-size: 11.5px;
        color: #64748b;
        border-color: #f1f5f9 !important;
        padding-top: 6px;
    }
    
    /* Premium Filter Toolbar & Anti-Truncation Layout */
    .toolbar-card {
        border-color: rgba(0, 0, 0, 0.08) !important;
        border-radius: 10px;
        background-color: #ffffff;
    }
    .filter-controls-row {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        width: 100%;
    }
    .filter-col-search {
        flex: 1 1 260px;
        min-width: 220px;
    }
    .filter-col-category {
        flex: 0 0 auto;
        min-width: 220px;
        max-width: 100%;
    }
    .filter-col-trigger {
        flex: 1 1 280px;
        min-width: 280px;
        max-width: 100%;
    }
    .filter-col-status {
        flex: 0 0 auto;
        min-width: 175px;
        max-width: 100%;
    }
    .filter-col-clear {
        flex: 0 0 auto;
        min-width: 140px;
    }
    
    @media (max-width: 767.98px) {
        .filter-col-search,
        .filter-col-category,
        .filter-col-trigger,
        .filter-col-status,
        .filter-col-clear {
            flex: 1 1 100%;
            min-width: 100%;
        }
    }

    .toolbar-input-group {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background-color: #f8fafc;
        height: 38px;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
    }
    .toolbar-input-group:focus-within {
        border-color: #075e54 !important;
        background-color: #ffffff;
        box-shadow: 0 0 0 3px rgba(7, 94, 84, 0.12) !important;
    }
    .toolbar-input-group .form-control {
        font-size: 13px;
        color: #1e293b;
    }
    .toolbar-input-group .form-control::placeholder {
        color: #94a3b8;
        font-size: 13px;
    }
    .filter-search-clear {
        color: #94a3b8;
        border: none;
        background: transparent;
        transition: color 0.15s ease;
    }
    .filter-search-clear:hover {
        color: #dc2626 !important;
    }
    .toolbar-select {
        height: 38px;
        font-size: 13px;
        border-radius: 8px;
        border-color: #e2e8f0;
        background-color: #f8fafc;
        color: #334155;
        width: 100%;
        padding-right: 2.25rem;
        text-overflow: clip; /* Explicitly prevent text truncation with ellipsis */
        white-space: normal;
        overflow: visible;
        transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
    }
    .toolbar-select option {
        padding: 6px 10px;
        white-space: normal;
        font-size: 13px;
    }
    .toolbar-select:hover {
        border-color: #cbd5e1;
    }
    .toolbar-select:focus {
        border-color: #075e54 !important;
        background-color: #ffffff;
        box-shadow: 0 0 0 3px rgba(7, 94, 84, 0.12) !important;
    }
    .toolbar-select-active {
        border-color: #075e54 !important;
        background-color: #f0fdf4 !important;
        color: #064e3b !important;
        font-weight: 600;
    }
    .toolbar-btn {
        height: 38px;
        font-size: 12.5px;
        border-radius: 8px;
        transition: all 0.15s ease;
        white-space: nowrap;
    }
    
    /* Filter Subbar & Active Chips - Never Truncate */
    .filter-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        font-size: 12px;
        line-height: 1.4;
        background: #f1f5f9;
        color: #1e293b;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        white-space: normal; /* Never truncate chips */
        word-break: break-word;
        max-width: 100%;
        transition: background-color 0.15s ease, border-color 0.15s ease;
    }
    .filter-chip:hover {
        background: #e2e8f0;
    }
    .filter-chip-remove {
        cursor: pointer;
        color: #94a3b8;
        font-size: 13px;
        flex-shrink: 0;
        transition: color 0.15s ease;
    }
    .filter-chip-remove:hover {
        color: #dc2626 !important;
    }
    .filter-active-badge {
        background: #ecfdf5;
        color: #065f46;
        border: 1px solid #a7f3d0;
        font-size: 11px;
        padding: 4px 10px;
        border-radius: 20px;
        white-space: nowrap;
    }

    /* ======================================================== */
    /* ENTERPRISE EDIT MODAL STYLING */
    /* ======================================================== */
    .edit-modal-dialog {
        max-width: 880px;
        width: 95vw;
        margin: 1.25rem auto;
    }
    .edit-modal-card {
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        background-color: #ffffff;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
    }
    .edit-header {
        background-color: #ffffff;
        border-bottom: 1px solid #e2e8f0 !important;
    }
    .edit-header-icon {
        width: 36px;
        height: 36px;
        background-color: rgba(7, 94, 84, 0.08);
        color: #075e54;
        border: 1px solid rgba(7, 94, 84, 0.15);
        font-size: 16px;
    }
    .edit-category-pill {
        background-color: #f1f5f9;
        color: #334155;
        border: 1px solid #cbd5e1;
        font-weight: 500;
        font-size: 11px;
        padding: 2px 8px;
        border-radius: 6px;
        font-family: inherit;
    }
    .edit-system-key-badge {
        background-color: #f8fafc;
        color: #075e54;
        border: 1px solid #e2e8f0;
        font-size: 11.5px;
        font-weight: 600;
        padding: 1px 6px;
        border-radius: 4px;
    }
    .edit-close-btn {
        font-size: 11px;
        opacity: 0.6;
        transition: opacity 0.15s ease;
    }
    .edit-close-btn:hover {
        opacity: 1;
    }
    .bg-slate-50 {
        background-color: #f8fafc !important;
    }
    .bg-slate-100 {
        background-color: #f1f5f9 !important;
    }
    .edit-card {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background-color: #ffffff;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }
    .edit-card-title {
        font-size: 12.5px;
        font-weight: 700;
        color: #1e293b;
    }
    .edit-icon-pill {
        width: 24px;
        height: 24px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
    }
    .edit-label {
        font-size: 11.5px;
        font-weight: 600;
        color: #475569;
        margin-bottom: 4px;
    }
    .edit-input {
        height: 35px;
        font-size: 12.5px;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        background-color: #ffffff;
        color: #1e293b;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .edit-input:focus {
        border-color: #075e54 !important;
        box-shadow: 0 0 0 3px rgba(7, 94, 84, 0.12) !important;
        background-color: #ffffff;
    }
    .edit-input-readonly {
        background-color: #f8fafc !important;
        border-color: #e2e8f0 !important;
        color: #334155 !important;
    }
    .edit-textarea {
        font-size: 12px;
        line-height: 1.4;
        border-radius: 6px;
        border: 1px solid #cbd5e1;
        height: 52px;
        min-height: 52px;
        resize: none;
        padding: 6px 10px;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }
    .edit-textarea:focus {
        border-color: #075e54 !important;
        box-shadow: 0 0 0 3px rgba(7, 94, 84, 0.12) !important;
    }
    .edit-hint {
        font-size: 11px;
        color: #64748b;
        margin-top: 3px;
        line-height: 1.35;
    }
    .edit-status-box {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    }
    .edit-status-pill-active {
        background-color: #ecfdf5;
        color: #065f46;
        border: 1px solid #a7f3d0;
        font-size: 10.5px;
        font-weight: 600;
    }
    .edit-status-pill-inactive {
        background-color: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
        font-size: 10.5px;
        font-weight: 600;
    }
    .status-dot-inactive {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        display: inline-block;
        background-color: #94a3b8;
    }
    .edit-modal-footer {
        position: sticky;
        bottom: 0;
        z-index: 10;
        background-color: #ffffff;
        border-top: 1px solid #e2e8f0 !important;
    }
    .edit-btn-cancel {
        height: 35px;
        font-size: 12.5px;
        font-weight: 500;
        color: #475569;
        background-color: #ffffff;
        border-color: #cbd5e1;
        transition: all 0.15s ease;
    }
    .edit-btn-cancel:hover {
        background-color: #f1f5f9;
        color: #1e293b;
        border-color: #94a3b8;
    }
    .edit-btn-save {
        height: 35px;
        font-size: 12.5px;
        font-weight: 600;
        background: linear-gradient(135deg, #075e54 0%, #128c7e 100%);
        border: 1px solid #075e54;
        transition: all 0.15s ease;
    }
    .edit-btn-save:hover:not(:disabled) {
        background: linear-gradient(135deg, #064e43 0%, #0e7467 100%);
        box-shadow: 0 2px 6px rgba(7, 94, 84, 0.3);
    }
    .edit-btn-save:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }

    .workflow-stepper::before {
        content: '';
        position: absolute;
        top: 13px;
        bottom: 13px;
        left: 12px;
        width: 2px;
        background-color: #dee2e6;
        z-index: 1;
    }
    .table-hover tbody tr:hover {
        background-color: #fcfdfd !important;
    }
</style>
@endpush
