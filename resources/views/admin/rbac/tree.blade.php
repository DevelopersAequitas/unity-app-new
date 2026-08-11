@extends('admin.layouts.app')

@section('title', 'Role Hierarchy & Profiles')
@push('styles')
<style>
    /* Neumorphic Soft Depth Design Tokens */
    :root {
        --neu-bg: #f0f2f7;
        --neu-ink: #0f172a;
        --neu-sub: #64748b;
        --neu-accent: #6366f1;
        --neu-shadow-d: #d2d6df;
        --neu-shadow-l: #ffffff;
        --neu-connector: rgba(99, 102, 241, 0.25);
    }

    /* Soft Depth Container Cards */
    .glass-card {
        background: var(--neu-bg) !important;
        border: none !important;
        border-radius: 20px !important;
        box-shadow: 8px 8px 16px var(--neu-shadow-d), -8px -8px 16px var(--neu-shadow-l) !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .glass-card:hover {
        box-shadow: 10px 10px 20px var(--neu-shadow-d), -10px -10px 20px var(--neu-shadow-l) !important;
    }

    /* Buttons */
    .btn-light {
        background: var(--neu-bg) !important;
        border: none !important;
        border-radius: 12px !important;
        box-shadow: 4px 4px 8px var(--neu-shadow-d), -4px -4px 8px var(--neu-shadow-l) !important;
        color: var(--neu-ink) !important;
        font-weight: 700 !important;
        transition: all 0.2s !important;
    }
    .btn-light:hover {
        box-shadow: 2px 2px 4px var(--neu-shadow-d), -2px -2px 4px var(--neu-shadow-l) !important;
        transform: translateY(1px);
    }
    .btn-primary {
        background: linear-gradient(135deg, var(--neu-accent), #818cf8) !important;
        border: none !important;
        border-radius: 12px !important;
        box-shadow: 5px 5px 12px rgba(99, 102, 241, 0.35), -4px -4px 8px var(--neu-shadow-l) !important;
        color: #fff !important;
        font-weight: 700 !important;
        transition: all 0.2s !important;
    }
    .btn-primary:hover {
        box-shadow: 2px 2px 6px rgba(99, 102, 241, 0.35), -2px -2px 4px var(--neu-shadow-l) !important;
        transform: translateY(1px);
    }

    /* Forms & Inset Shadows */
    .form-select, .form-control, .select2-container--default .select2-selection--single {
        border: none !important;
        border-radius: 12px !important;
        background-color: var(--neu-bg) !important;
        color: var(--neu-ink) !important;
        box-shadow: inset 4px 4px 8px var(--neu-shadow-d), inset -4px -4px 8px var(--neu-shadow-l) !important;
        padding: 10px 14px !important;
        height: auto !important;
        transition: all 0.2s;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: var(--neu-ink) !important;
        line-height: inherit !important;
        padding-left: 0 !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        top: 50% !important;
        transform: translateY(-50%) !important;
    }

    /* Multi-select box style (Relocate Settings Parents Selection) */
    select[multiple] {
        min-height: 150px;
        box-shadow: inset 4px 4px 8px var(--neu-shadow-d), inset -4px -4px 8px var(--neu-shadow-l) !important;
        border: none !important;
        border-radius: 14px !important;
        background: var(--neu-bg) !important;
        padding: 12px !important;
    }

    /* Tree Styling & Scrollable Canvas Container */
    .hierarchy-container {
        width: 100%;
        max-height: 70vh;
        min-height: 350px;
        overflow: auto;
        scrollbar-width: thin;
        position: relative;
    }
    .hierarchy-tree {
        display: flex;
        justify-content: center;
        width: max-content;
        min-width: 100%;
        padding: 24px 30px 40px 30px;
    }
    .hierarchy-tree ul {
        padding-top: 16px; 
        position: relative;
        transition: all 0.5s;
        list-style-type: none;
        display: flex;
        justify-content: center;
        margin: 0 auto;
    }
    .hierarchy-tree li {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        list-style-type: none;
        position: relative;
        padding: 16px 6px 0;
        transition: all 0.5s;
    }
    
    /* Connectors */
    .hierarchy-tree li::before, .hierarchy-tree li::after {
        content: '';
        position: absolute; 
        top: 0; 
        right: 50%;
        border-top: 2px solid var(--neu-connector);
        width: 50%; 
        height: 16px;
    }
    .hierarchy-tree li::after {
        right: auto; 
        left: 50%;
        border-left: 2px solid var(--neu-connector);
    }
    .hierarchy-tree li:only-child::after, .hierarchy-tree li:only-child::before {
        display: none;
    }
    .hierarchy-tree li:only-child { 
        padding-top: 0;
    }
    .hierarchy-tree li:first-child::before, .hierarchy-tree li:last-child::after {
        border: 0 none;
    }
    .hierarchy-tree li:last-child::before {
        border-right: 2px solid var(--neu-connector);
        border-radius: 0 8px 0 0;
    }
    .hierarchy-tree li:first-child::after {
        border-radius: 8px 0 0 0;
    }
    .hierarchy-tree ul ul::before {
        content: '';
        position: absolute; 
        top: 0; 
        left: 50%;
        border-left: 2px solid var(--neu-connector);
        width: 0; 
        height: 16px;
    }

    /* Neumorphic Node Boxes */
    .node-box {
        display: inline-block;
        padding: 10px 16px;
        min-width: 130px;
        border-radius: 14px;
        background: var(--neu-bg);
        border: none;
        box-shadow: 4px 4px 10px var(--neu-shadow-d), -4px -4px 10px var(--neu-shadow-l);
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        z-index: 10;
        color: var(--neu-ink);
    }
    .node-box:hover {
        transform: translateY(-2px);
        box-shadow: 6px 6px 14px var(--neu-shadow-d), -6px -6px 14px var(--neu-shadow-l), 0 0 0 2px var(--neu-accent) inset;
    }
    .node-box.node-root {
        background: linear-gradient(135deg, var(--neu-accent), #818cf8);
        box-shadow: 4px 4px 12px var(--neu-shadow-d), -3px -3px 8px var(--neu-shadow-l);
        color: #fff;
    }

    /* Node Icon */
    .node-icon {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        margin: 0 auto 5px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
        background: var(--neu-bg);
        box-shadow: inset 2px 2px 5px var(--neu-shadow-d), inset -2px -2px 5px var(--neu-shadow-l);
        color: var(--neu-accent);
        transition: all 0.3s;
    }
    .node-box:hover .node-icon {
        color: #818cf8;
    }
    .node-root .node-icon {
        background: rgba(255, 255, 255, 0.16) !important;
        box-shadow: none !important;
        color: #fff !important;
    }

    .node-title {
        font-family: 'Outfit', sans-serif;
        font-weight: 700;
        font-size: 0.85rem;
    }
    .node-meta {
        font-size: 0.68rem;
        color: var(--neu-sub);
        margin-top: 1px;
    }
    .node-root .node-meta {
        color: #e0e7ff;
    }

    .node-badge {
        display: inline-block;
        margin-top: 5px;
        font-size: 0.58rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 12px;
        text-transform: uppercase;
    }
    .badge-system { background-color: #fee2e2; color: #ef4444; }
    .badge-admin { background-color: #e0e7ff; color: var(--neu-accent); }
    .badge-user { background-color: #dcfce7; color: #15803d; }

    /* Action buttons popup */
    .node-box-interactive {
        position: relative;
    }
    .node-actions {
        position: absolute;
        top: -10px;
        right: -10px;
        display: flex;
        gap: 6px;
        opacity: 0;
        transform: scale(0.8);
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        pointer-events: none;
    }
    .node-box-interactive:hover .node-actions {
        opacity: 1;
        transform: scale(1);
        pointer-events: auto;
    }
    .node-action-btn {
        width: 26px;
        height: 26px;
        border-radius: 8px;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        cursor: pointer;
        transition: all 0.15s;
        box-shadow: 3px 3px 6px var(--neu-shadow-d), -3px -3px 6px var(--neu-shadow-l);
    }
    .node-edit-btn { background: var(--neu-accent); color: white; }
    .node-edit-btn:hover { background: #4f46e5; }
    .node-delete-btn { background: #ef4444; color: white; }
    .node-delete-btn:hover { background: #dc2626; }
</style>
@endpush

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1 fw-bold" style="font-family: 'Outfit', sans-serif;">Role Hierarchy & Profiles</h1>
            <p class="text-muted small mb-0">Manage dynamic role hierarchies, clone profiles and coordinate scopes cascade mappings.</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            @include('admin.rbac.partials.header_nav')
            <button class="btn btn-light border d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#cloneProfileModal">
                <i class="bi bi-copy"></i> Clone Profile
            </button>
            <button class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createRoleModal">
                <i class="bi bi-plus-circle"></i> Create New Role
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4 rounded-3 border-0 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
    @endif

    <!-- Top Row: Role Assignment -->
    <div class="row mb-4">
        <!-- Assign Role to Peer Card -->
        <div class="col-md-6 col-lg-5">
            <div class="card glass-card h-100">
                <div class="card-header bg-transparent border-0 pt-4 px-4">
                    <h5 class="card-title fw-bold mb-0"><i class="bi bi-person-check me-2"></i>Assign Role to Peer</h5>
                </div>
                <div class="card-body p-4">
                    <form action="{{ route('admin.rbac.roles.assign') }}" method="POST" id="assignRoleForm">
                        @csrf
                        <div class="mb-3">
                            <label for="assign_user_id" class="form-label fw-semibold fs-7">Select Peer (Admin)</label>
                            <select id="assign_user_id" name="admin_user_id" class="form-select rounded-3" required>
                                <option value="">-- Choose Peer --</option>
                                @foreach($peers as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->email }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="assign_role_id" class="form-label fw-semibold fs-7">Select Role</label>
                            <select id="assign_role_id" name="role_id" class="form-select rounded-3" required onchange="onAssignRoleChange()">
                                <option value="">-- Choose Role --</option>
                                @foreach($roles as $r)
                                    <option value="{{ $r->id }}" data-key="{{ $r->key }}">{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Dynamic Scope Selectors -->
                        <div id="scope_container" style="display: none;">
                            <!-- District Selector (for DED) -->
                            <div class="mb-3 scope-selector" id="district_selector" style="display: none;">
                                <label for="assign_district_id" class="form-label fw-semibold fs-7">Select District</label>
                                <select id="assign_district_id" name="scope_id" class="form-select rounded-3">
                                    <option value="">-- Choose District --</option>
                                    @foreach($districts as $d)
                                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Industry Selector (for ID/IED) -->
                            <div class="mb-3 scope-selector" id="industry_selector" style="display: none;">
                                <label for="assign_industry_id" class="form-label fw-semibold fs-7">Select Industry</label>
                                <select id="assign_industry_id" name="scope_id" class="form-select rounded-3">
                                    <option value="">-- Choose Industry --</option>
                                    @foreach($industries as $i)
                                        <option value="{{ $i->id }}">{{ $i->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Circle Selector (for CD/CF/Chair/Vice Chair/Secretary) -->
                            <div class="mb-3 scope-selector" id="circle_selector" style="display: none;">
                                <label for="assign_circle_id" class="form-label fw-semibold fs-7">Select Circle</label>
                                <select id="assign_circle_id" name="scope_id" class="form-select rounded-3">
                                    <option value="">-- Choose Circle --</option>
                                    @foreach($circles as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>


                        <button type="submit" class="btn btn-success w-100 rounded-3 py-2 mt-2">
                            <i class="bi bi-person-check me-1"></i> Assign Role & Scope
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Row: Hierarchy Map (Full Width) -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card glass-card">
                <div class="card-header bg-transparent border-bottom pt-4 pb-3 px-4 d-flex justify-content-between align-items-center" style="border-color: rgba(0,0,0,0.08) !important;">
                    <h5 class="card-title fw-bold mb-0"><i class="bi bi-diagram-3 me-2"></i>Hierarchy Map</h5>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted fs-7">Role hierarchy tree</span>
                        <a href="{{ route('admin.rbac.hierarchy.fullmap') }}" target="_blank"
                           class="btn btn-sm btn-light border d-flex align-items-center gap-1"
                           style="font-size:0.78rem;white-space:nowrap;"
                           title="Open full-screen map in new tab">
                            <i class="bi bi-box-arrow-up-right"></i> Full Map
                        </a>
                    </div>
                </div>
                <div class="card-body p-0 hierarchy-container">
                    <div class="hierarchy-tree">
                        @if(empty($roots))
                            <div class="text-center text-muted my-5">
                                <i class="bi bi-diagram-3 fs-1 d-block mb-3"></i>
                                No active role mappings found. Create a root role first.
                            </div>
                        @else
                            @php
                                $GLOBALS['rendered_subtrees'] = [];
                            @endphp
                            <ul>
                                @foreach($roots as $root)
                                    @include('admin.rbac.tree_node', ['role' => $root])
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Create New Role -->
<div class="modal fade" id="createRoleModal" tabindex="-1" aria-labelledby="createRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0" style="border-radius: 16px;">
            <form action="{{ route('admin.rbac.roles.store') }}" method="POST">
                @csrf
                <div class="modal-header border-0 pt-4 px-4 pb-0">
                    <h5 class="modal-title fw-bold" id="createRoleModalLabel">Create New Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="role_name" class="form-label fw-semibold fs-7">Role Name</label>
                        <input type="text" id="role_name" name="name" class="form-control rounded-3" placeholder="e.g. Area Director" required>
                    </div>
                    <div class="mb-3">
                        <label for="role_key" class="form-label fw-semibold fs-7">Unique Key <span class="text-muted fs-8">(auto-generated)</span></label>
                        <input type="text" id="role_key" name="key" class="form-control rounded-3" placeholder="e.g. area_director" readonly style="background:#f0f2f7;cursor:not-allowed;" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-7">Parent Roles (Optional)</label>
                        <div class="border rounded-3 p-3 bg-light" style="max-height: 140px; overflow-y: auto;">
                            @foreach($roles as $r)
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" name="parent_role_ids[]" value="{{ $r->id }}" id="parent_role_{{ $r->id }}">
                                    <label class="form-check-label fs-7" for="parent_role_{{ $r->id }}">
                                        {{ $r->name }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-text fs-8">Select one or more parent roles this role reports to.</div>
                    </div>
                    <div class="mb-3">
                        <label for="role_description" class="form-label fw-semibold fs-7">Description</label>
                        <textarea id="role_description" name="description" class="form-control rounded-3" rows="3" placeholder="Brief outline of the role..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4">Save Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Clone Profile -->
<div class="modal fade" id="cloneProfileModal" tabindex="-1" aria-labelledby="cloneProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0" style="border-radius: 16px;">
            <form action="{{ route('admin.rbac.roles.clone') }}" method="POST">
                @csrf
                <div class="modal-header border-0 pt-4 px-4 pb-0">
                    <h5 class="modal-title fw-bold" id="cloneProfileModalLabel">Clone Profile / Create Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="clone_name" class="form-label fw-semibold fs-7">New Profile Name</label>
                        <input type="text" id="clone_name" name="name" class="form-control rounded-3" placeholder="e.g. Circle Director - Trial" required>
                    </div>
                    <div class="mb-3">
                        <label for="clone_from" class="form-label fw-semibold fs-7">Clone Permissions From</label>
                        <select id="clone_from" name="clone_from" class="form-select rounded-3" required>
                            <option value="">-- Choose Profile to Clone --</option>
                            @foreach($roles as $r)
                                <option value="{{ $r->id }}">{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold fs-7">Attach Under Parent(s)</label>
                        <div class="border rounded-3 p-3 bg-light" style="max-height: 140px; overflow-y: auto;">
                            @foreach($roles as $r)
                                <div class="form-check mb-1">
                                    <input class="form-check-input" type="checkbox" name="parent_role_ids[]" value="{{ $r->id }}" id="clone_parent_role_{{ $r->id }}">
                                    <label class="form-check-label fs-7" for="clone_parent_role_{{ $r->id }}">
                                        {{ $r->name }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-text fs-8">Select parent role(s) to place this cloned role under.</div>
                    </div>
                    <div class="mb-3">
                        <label for="clone_description" class="form-label fw-semibold fs-7">Description</label>
                        <textarea id="clone_description" name="description" class="form-control rounded-3" rows="3" placeholder="Brief details about this cloned role profile..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4">Clone & Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Auto-generate Unique Key from Role Name
        const roleNameInput  = document.getElementById('role_name');
        const roleKeyInput   = document.getElementById('role_key');

        if (roleNameInput && roleKeyInput) {
            roleNameInput.addEventListener('input', function () {
                roleKeyInput.value = this.value
                    .toLowerCase()
                    .trim()
                    .replace(/[^a-z0-9\s_]/g, '')   // keep alphanumeric, spaces, underscores
                    .replace(/\s+/g, '_');            // spaces → underscores
            });
        }
    });
</script>
@endpush

{{-- ============================= Edit Role Modal ============================= --}}
<div class="modal fade" id="editRoleModal" tabindex="-1" aria-labelledby="editRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow border-0" style="border-radius: 16px;">
            <form id="editRoleForm" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-header border-0 pt-4 px-4 pb-0">
                    <h5 class="modal-title fw-bold" id="editRoleModalLabel">
                        <i class="bi bi-pencil-square me-2 text-primary"></i>Edit Role
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-7">Role Name</label>
                            <input type="text" id="edit_role_name" name="name" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-7">Unique Key</label>
                            <input type="text" id="edit_role_key" name="key" class="form-control rounded-3" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-7">Role Type</label>
                            <select id="edit_role_type" name="role_type" class="form-select rounded-3" required>
                                <option value="user">User (Chairs, Members)</option>
                                <option value="admin">Admin (DED, ID, IED, CD, CF)</option>
                                <option value="system">System (Super Admin)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold fs-7">Scope Rule</label>
                            <select id="edit_scope_rule" name="scope_rule" class="form-select rounded-3" required>
                                <option value="mandatory">Mandatory</option>
                                <option value="optional">Optional</option>
                                <option value="not_applicable">Not Applicable</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold fs-7">Parent Roles (Reports to)</label>
                            <div class="border rounded-3 p-3 bg-light" style="max-height: 140px; overflow-y: auto;">
                                @foreach($roles as $r)
                                    <div class="form-check mb-1">
                                        <input class="form-check-input edit-parent-checkbox" type="checkbox" name="parent_role_ids[]" value="{{ $r->id }}" id="edit_parent_role_{{ $r->id }}">
                                        <label class="form-check-label fs-7" for="edit_parent_role_{{ $r->id }}">
                                            {{ $r->name }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <div class="form-text fs-8">Select parent role(s) this role reports to. Leave empty for root role.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold fs-7">Description</label>
                            <textarea id="edit_description" name="description" class="form-control rounded-3" rows="2" placeholder="Brief outline..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary rounded-3 px-4">
                        <i class="bi bi-check2 me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ============================= Delete Role Modal ============================= --}}
<div class="modal fade" id="deleteRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-0" style="border-radius: 16px;">
            <form id="deleteRoleForm" method="POST">
                @csrf
                @method('DELETE')
                <div class="modal-header border-0 pt-4 px-4 pb-0">
                    <h5 class="modal-title fw-bold text-danger">
                        <i class="bi bi-trash3 me-2"></i>Delete Role
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body px-4 py-3">
                    <div class="p-3 rounded-3" style="background:#fff5f5;border:1px solid #fecaca;">
                        <p class="mb-1 fw-semibold text-danger">Are you sure you want to delete:</p>
                        <p class="mb-0 fs-5 fw-bold" id="deleteRoleName">—</p>
                    </div>
                    <p class="text-muted small mt-3 mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        This will also remove all parent/child hierarchy links for this role. This action cannot be undone.
                    </p>
                </div>
                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger rounded-3 px-4">
                        <i class="bi bi-trash3 me-1"></i>Yes, Delete
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openEditModal(id, name, key, roleType, scopeRule, description, parentIds) {
    const form = document.getElementById('editRoleForm');
    form.action = `/admin/rbac/roles/${id}`;

    document.getElementById('edit_role_name').value   = name        || '';
    document.getElementById('edit_role_key').value    = key         || '';
    document.getElementById('edit_description').value = description || '';

    const typeSelect  = document.getElementById('edit_role_type');
    const scopeSelect = document.getElementById('edit_scope_rule');
    for (let opt of typeSelect.options)  opt.selected = (opt.value === roleType);
    for (let opt of scopeSelect.options) opt.selected = (opt.value === scopeRule);

    document.querySelectorAll('.edit-parent-checkbox').forEach(cb => cb.checked = false);
    if (parentIds) {
        parentIds.forEach(pId => {
            const cb = document.getElementById(`edit_parent_role_${pId}`);
            if (cb) cb.checked = true;
        });
    }

    const modal = new bootstrap.Modal(document.getElementById('editRoleModal'));
    modal.show();
}

function openDeleteModal(id, name) {
    document.getElementById('deleteRoleName').textContent = name;
    document.getElementById('deleteRoleForm').action = `/admin/rbac/roles/${id}`;
    const modal = new bootstrap.Modal(document.getElementById('deleteRoleModal'));
    modal.show();
}
function onAssignRoleChange() {
    const roleSelect = document.getElementById('assign_role_id');
    const selectedOption = roleSelect.options[roleSelect.selectedIndex];
    const key = selectedOption ? (selectedOption.getAttribute('data-key') || '').toLowerCase().replace(/_/g, ' ').replace(/-/g, ' ').trim() : '';

    const container = document.getElementById('scope_container');
    const districtSel = document.getElementById('district_selector');
    const industrySel = document.getElementById('industry_selector');
    const circleSel = document.getElementById('circle_selector');

    // Reset inputs
    document.getElementById('assign_district_id').value = '';
    document.getElementById('assign_industry_id').value = '';
    document.getElementById('assign_circle_id').value = '';

    // Hide all
    container.style.display = 'none';
    districtSel.style.display = 'none';
    industrySel.style.display = 'none';
    circleSel.style.display = 'none';

    // Remove required flags
    document.getElementById('assign_district_id').required = false;
    document.getElementById('assign_industry_id').required = false;
    document.getElementById('assign_circle_id').required = false;

    const isDed = key === 'ded' || key.includes('ded') || key.includes('district');
    const isId = key === 'id' || key === 'ied' || key.includes('industry');
    const isCircle = ['cd', 'cf', 'chair', 'vice chair', 'secretary', 'circle leader'].includes(key) || 
                     key.includes('circle') || 
                     key.includes('chair') || 
                     key.includes('secretary');

    if (isDed) {
        container.style.display = 'block';
        districtSel.style.display = 'block';
        document.getElementById('assign_district_id').required = true;
    } else if (isId) {
        container.style.display = 'block';
        industrySel.style.display = 'block';
        document.getElementById('assign_industry_id').required = true;
    } else if (isCircle) {
        container.style.display = 'block';
        circleSel.style.display = 'block';
        document.getElementById('assign_circle_id').required = true;
    }

    const permissionsContainer = document.getElementById('permissions_container');
    if (roleSelect && roleSelect.value) {
        permissionsContainer.style.display = 'block';
    } else {
        permissionsContainer.style.display = 'none';
    }
}

// Auto-select scope rules as user types key in Create / Edit role modals
document.addEventListener('DOMContentLoaded', function() {
    function autoSelectScopeRule(keyInputId, scopeSelectId) {
        const input = document.getElementById(keyInputId);
        const select = document.getElementById(scopeSelectId);
        if (!input || !select) return;

        input.addEventListener('input', function(e) {
            const val = (e.target.value || '').toLowerCase().trim();
            if (val === 'ded') {
                select.value = 'mandatory';
            } else if (val === 'global_admin' || val === 'global_founder') {
                select.value = 'not_applicable';
            } else if (['id', 'ied', 'cd', 'cf', 'chair', 'vice_chair', 'secretary', 'circle_leader'].includes(val)) {
                select.value = 'mandatory';
            } else if (val !== '') {
                select.value = 'optional';
            }
        });
    }

    autoSelectScopeRule('role_key', 'scope_rule');
    autoSelectScopeRule('edit_role_key', 'edit_scope_rule');
});
</script>
@endpush

{{-- ============================= Role Assignments Modal ============================= --}}
<div class="modal fade" id="roleAssignmentsModal" tabindex="-1" aria-labelledby="roleAssignmentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow border-0" style="border-radius: 16px;">
            <div class="modal-header border-0 pt-4 px-4 pb-0">
                <div>
                    <h5 class="modal-title fw-bold text-primary" id="roleAssignmentsModalLabel">—</h5>
                    <span class="text-muted fs-7" id="roleAssignmentsModalSubtext">—</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <!-- Left: Current Assignments list -->
                    <div class="col-md-7 border-end">
                        <h6 class="fw-bold mb-3 d-flex justify-content-between align-items-center">
                            <span>Currently Assigned Peers</span>
                            <span class="badge bg-primary rounded-pill fs-9" id="assignmentCount">0</span>
                        </h6>
                        <div id="assignmentsList" style="max-height: 300px; overflow-y: auto;">
                            <!-- Dynamically loaded -->
                        </div>
                    </div>
                    
                    <!-- Right: Quick Assign form -->
                    <div class="col-md-5">
                        <h6 class="fw-bold mb-3" id="quickAssignFormTitle">Quick Assign Peer</h6>
                        <form id="quickAssignForm">
                            @csrf
                            <input type="hidden" id="quick_assign_role_id" name="role_id">
                            
                            <div class="mb-3">
                                <label for="quick_assign_user" class="form-label fw-semibold fs-8">Select Peer</label>
                                <select id="quick_assign_user" name="admin_user_id" class="form-select rounded-3 select-sm" required>
                                    <!-- Dynamically loaded -->
                                </select>
                            </div>

                            <div class="mb-3" id="quick_scope_container" style="display:none;">
                                <label for="quick_scope_id" class="form-label fw-semibold fs-8" id="quick_scope_label">Select Scope</label>
                                <select id="quick_scope_id" name="scope_id" class="form-select rounded-3 select-sm">
                                    <!-- Dynamically loaded -->
                                </select>
                            </div>


                            <div id="quickAssignButtons" class="mt-2">
                                <button type="submit" id="quickAssignSubmitBtn" class="btn btn-success btn-sm w-100 rounded-3 py-2">
                                    <i id="quickAssignSubmitIcon" class="bi bi-person-plus-fill me-1"></i> <span id="quickAssignSubmitText">Assign Peer</span>
                                </button>
                                <button type="button" id="quickAssignCancelBtn" class="btn btn-outline-secondary btn-sm w-100 rounded-3 py-2 mt-2" style="display: none;" onclick="cancelEditAssignment()">
                                    Cancel Edit
                                </button>
                            </div>
                        </form>
                        <div id="quickAssignMessage" class="mt-3 text-center fs-8 text-muted" style="display:none;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
let currentOpenRoleId = '';
let activeRoleKey = '';
let activeScopeRule = '';
let availablePeersData = [];

const globalDistricts = @json($districts);
const globalIndustries = @json($industries);
const globalCircles = @json($circles);

document.addEventListener('DOMContentLoaded', function() {
    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
        $('#assign_user_id').select2({ width: '100%' });
        $('#assign_role_id').select2({ width: '100%' }).on('change', function() {
            onAssignRoleChange();
        });
        $('#assign_district_id').select2({ width: '100%' });
        $('#assign_industry_id').select2({ width: '100%' });
        $('#assign_circle_id').select2({ width: '100%' });

        $('#quick_assign_user').select2({
            dropdownParent: $('#roleAssignmentsModal'),
            width: '100%'
        }).on('change', function() {
            onQuickAssignUserChange();
        });
        $('#quick_scope_id').select2({
            dropdownParent: $('#roleAssignmentsModal'),
            width: '100%'
        });
    } else {
        const peerSelect = document.getElementById('quick_assign_user');
        if (peerSelect) {
            peerSelect.addEventListener('change', onQuickAssignUserChange);
        }
    }
});

function openAssignmentsModal(roleId, roleName, roleKey, scopeRule) {
    currentOpenRoleId = roleId;
    activeRoleKey = roleKey.toLowerCase().replace(/\s+/g, '_');
    activeScopeRule = scopeRule;

    document.getElementById('roleAssignmentsModalLabel').textContent = roleName + ' Assignments';
    document.getElementById('roleAssignmentsModalSubtext').textContent = 'Key: ' + roleKey + ' • Rule: ' + scopeRule;
    document.getElementById('quick_assign_role_id').value = roleId;
    
    cancelEditAssignment();

    fetchAssignments();

    const modal = new bootstrap.Modal(document.getElementById('roleAssignmentsModal'));
    modal.show();
}

function startEditAssignment(userId, userName, scopeId, permissionType, allowedSections) {
    const peerSelect = document.getElementById('quick_assign_user');
    
    // Check if option exists in select, if not append it
    let opt = peerSelect.querySelector(`option[value="${userId}"]`);
    if (!opt) {
        opt = document.createElement('option');
        opt.value = userId;
        opt.textContent = userName;
        peerSelect.appendChild(opt);
    }
    
    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
        $(peerSelect).val(userId).trigger('change');
    } else {
        peerSelect.value = userId;
    }
    peerSelect.disabled = true;

    document.getElementById('quickAssignFormTitle').textContent = 'Edit Assigned Peer';
    document.getElementById('quickAssignCancelBtn').style.display = 'block';
    document.getElementById('quickAssignSubmitText').textContent = 'Update Assignment';
    document.getElementById('quickAssignSubmitIcon').className = 'bi bi-pencil-square me-1';

    if (scopeId) {
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
            $('#quick_scope_id').val(scopeId).trigger('change');
        } else {
            document.getElementById('quick_scope_id').value = scopeId;
        }
    } else {
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
            $('#quick_scope_id').val('').trigger('change');
        } else {
            document.getElementById('quick_scope_id').value = '';
        }
    }

}

function cancelEditAssignment() {
    const peerSelect = document.getElementById('quick_assign_user');
    peerSelect.disabled = false;
    
    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
        $(peerSelect).val('').trigger('change');
    } else {
        peerSelect.value = '';
    }

    document.getElementById('quickAssignFormTitle').textContent = 'Quick Assign Peer';
    document.getElementById('quickAssignCancelBtn').style.display = 'none';
    document.getElementById('quickAssignSubmitText').textContent = 'Assign Peer';
    document.getElementById('quickAssignSubmitIcon').className = 'bi bi-person-plus-fill me-1';

    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
        $('#quick_scope_id').val('').trigger('change');
    } else {
        document.getElementById('quick_scope_id').value = '';
    }
}

function fetchAssignments() {
    const listContainer = document.getElementById('assignmentsList');
    const peersSelect = document.getElementById('quick_assign_user');
    const countBadge = document.getElementById('assignmentCount');

    listContainer.innerHTML = '<div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-1"></span> Loading...</div>';
    peersSelect.innerHTML = '<option value="">-- Choose Peer --</option>';

    fetch(`/admin/rbac/roles/${currentOpenRoleId}/assignments`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                countBadge.textContent = data.assignments.length;
                availablePeersData = data.available_peers || [];

                if (data.assignments.length === 0) {
                    listContainer.innerHTML = '<div class="text-center py-4 text-muted fs-8">No peers assigned to this role yet.</div>';
                } else {
                    let html = '<div class="list-group list-group-flush border rounded-3">';
                    data.assignments.forEach(assign => {
                        const sectionsJson = JSON.stringify(assign.allowed_sections).replace(/"/g, '&quot;');
                        const safeName = assign.name.replace(/'/g, "\\'");
                        html += `
                            <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3 peer-assignment-card" 
                                 style="cursor: pointer;"
                                 data-user-id="${assign.user_id}"
                                 data-name="${safeName}"
                                 data-scope-id="${assign.scope_id || ''}"
                                 data-permission-type="${assign.permission_type}"
                                 data-allowed-sections="${sectionsJson}">
                                <div class="flex-grow-1 pe-3" style="min-width: 0;">
                                    <div class="fw-bold fs-7 text-truncate text-primary">${assign.name}</div>
                                    <div class="text-muted small text-truncate">${assign.email}</div>
                                    <div class="d-flex flex-wrap gap-1 mt-1">
                                        <span class="badge bg-light text-secondary border fs-9 text-wrap text-start">${assign.scope_name}</span>
                                    </div>
                                </div>
                                <div class="flex-shrink-0">
                                    <button type="button" class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1 rounded-3 px-2 py-1" 
                                        title="Revoke Role" onclick="event.stopPropagation(); removePeerAssignment('${assign.user_id}', '${safeName}')">
                                        <i class="bi bi-trash3-fill fs-8"></i>
                                        <span class="fs-8 fw-semibold">Remove</span>
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    listContainer.innerHTML = html;
                    
                    // Bind click event to peer cards
                    $(listContainer).off('click', '.peer-assignment-card').on('click', '.peer-assignment-card', function(e) {
                        if ($(e.target).closest('.btn-outline-danger').length) {
                            return;
                        }
                        const userId = $(this).attr('data-user-id');
                        const name = $(this).attr('data-name');
                        const scopeId = $(this).attr('data-scope-id');
                        const permissionType = $(this).attr('data-permission-type');
                        const allowedSections = JSON.parse($(this).attr('data-allowed-sections') || '[]');
                        startEditAssignment(userId, name, scopeId, permissionType, allowedSections);
                    });
                }

                if (data.available_peers.length === 0) {
                    peersSelect.innerHTML = '<option value="">All peers assigned</option>';
                } else {
                    data.available_peers.forEach(peer => {
                        const opt = document.createElement('option');
                        opt.value = peer.id;
                        opt.textContent = peer.name + ' (' + peer.email + ')';
                        peersSelect.appendChild(opt);
                    });
                }

                if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                    $(peersSelect).trigger('change');
                }

                setupModalScopeSelect();
            }
        })
        .catch(err => {
            console.error('Error loading assignments:', err);
            listContainer.innerHTML = '<div class="text-center py-4 text-danger fs-8">Failed to load assigned peers.</div>';
        });
}

function setupModalScopeSelect() {
    const scopeContainer = document.getElementById('quick_scope_container');
    const scopeLabel = document.getElementById('quick_scope_label');
    const scopeSelect = document.getElementById('quick_scope_id');

    scopeContainer.style.display = 'none';
    scopeSelect.innerHTML = '';
    scopeSelect.required = false;

    const normalizedKey = (activeRoleKey || '').toLowerCase().replace(/_/g, ' ').replace(/-/g, ' ').trim();
    const isDed = normalizedKey === 'ded' || normalizedKey.includes('ded') || normalizedKey.includes('district');
    const isId = normalizedKey === 'id' || normalizedKey === 'ied' || normalizedKey.includes('industry');
    const isCircle = ['cd', 'cf', 'chair', 'vice chair', 'secretary', 'circle leader'].includes(normalizedKey) || 
                     normalizedKey.includes('circle') || 
                     normalizedKey.includes('chair') || 
                     normalizedKey.includes('secretary');

    if (isDed) {
        scopeContainer.style.display = 'block';
        scopeLabel.textContent = 'Select District';
        scopeSelect.required = true;
        
        scopeSelect.innerHTML = '<option value="">-- Choose District --</option>';
        globalDistricts.forEach(d => {
            const opt = document.createElement('option');
            opt.value = d.id;
            opt.textContent = d.name;
            scopeSelect.appendChild(opt);
        });
    } else if (isId) {
        scopeContainer.style.display = 'block';
        scopeLabel.textContent = 'Select Industry';
        scopeSelect.required = true;
        
        scopeSelect.innerHTML = '<option value="">-- Choose Industry --</option>';
        globalIndustries.forEach(i => {
            const opt = document.createElement('option');
            opt.value = i.id;
            opt.textContent = i.name;
            scopeSelect.appendChild(opt);
        });
    } else if (isCircle) {
        scopeContainer.style.display = 'block';
        scopeLabel.textContent = 'Select Circle';
        scopeSelect.required = true;
        
        scopeSelect.innerHTML = '<option value="">-- Choose Circle --</option>';
        globalCircles.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.name;
            scopeSelect.appendChild(opt);
        });
    }

    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
        $(scopeSelect).trigger('change');
    }
}

function onQuickAssignUserChange() {
    const peerSelect = document.getElementById('quick_assign_user');
    const selectedUserId = peerSelect ? peerSelect.value : '';
    if (!selectedUserId) {
        setupModalScopeSelect();
        return;
    }

    const peer = availablePeersData.find(p => String(p.id) === String(selectedUserId));
    if (!peer) {
        return;
    }

    const normalizedKey = (activeRoleKey || '').toLowerCase().replace(/_/g, ' ').replace(/-/g, ' ').trim();
    const isDed = normalizedKey === 'ded' || normalizedKey.includes('ded') || normalizedKey.includes('district');
    const isId = normalizedKey === 'id' || normalizedKey === 'ied' || normalizedKey.includes('industry');
    const isCircle = ['cd', 'cf', 'chair', 'vice chair', 'secretary', 'circle leader'].includes(normalizedKey) || 
                     normalizedKey.includes('circle') || 
                     normalizedKey.includes('chair') || 
                     normalizedKey.includes('secretary');

    const scopeSelect = $('#quick_scope_id');

    if (isDed) {
        if (peer.district_id) {
            scopeSelect.val(peer.district_id).trigger('change');
        } else {
            scopeSelect.val('').trigger('change');
        }
    } else if (isId) {
        if (peer.industry_id) {
            scopeSelect.val(peer.industry_id).trigger('change');
        } else {
            scopeSelect.val('').trigger('change');
        }
    } else if (isCircle) {
        const userCircles = (peer.circle_ids || []).map(id => String(id).toLowerCase());
        const scopeSelect = $('#quick_scope_id');

        scopeSelect.empty();
        scopeSelect.append(new Option('-- Choose Circle --', ''));

        // Filter globalCircles to only include circles joined by this peer
        let matchedCircles = [];
        if (userCircles.length > 0) {
            matchedCircles = globalCircles.filter(c => userCircles.includes(String(c.id).toLowerCase()));
        }

        const circlesToDisplay = matchedCircles.length > 0 ? matchedCircles : globalCircles;

        circlesToDisplay.forEach(c => {
            scopeSelect.append(new Option(c.name, c.id));
        });

        // Automatically select the peer's circle if available
        if (matchedCircles.length > 0) {
            scopeSelect.val(matchedCircles[0].id).trigger('change');
        } else {
            scopeSelect.val('').trigger('change');
        }
    }
}

document.getElementById('quickAssignForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const msg = document.getElementById('quickAssignMessage');
    msg.style.display = 'block';
    msg.className = 'mt-3 text-center fs-8 text-muted';
    const isEditMode = (document.getElementById('quickAssignSubmitText').textContent === 'Update Assignment');
    msg.textContent = isEditMode ? 'Updating...' : 'Assigning...';

    const formData = new FormData(this);
    if (!formData.has('admin_user_id')) {
        const peerSelect = document.getElementById('quick_assign_user');
        if (peerSelect) {
            formData.append('admin_user_id', peerSelect.value);
        }
    }

    fetch(`/admin/rbac/roles/${currentOpenRoleId}/assignments`, {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            msg.className = 'mt-3 text-center fs-8 text-success';
            msg.textContent = isEditMode ? 'Assignment updated successfully!' : 'Assigned successfully!';
            setTimeout(() => {
                msg.style.display = 'none';
                cancelEditAssignment();
                fetchAssignments();
            }, 1000);
        } else {
            msg.className = 'mt-3 text-center fs-8 text-danger';
            msg.textContent = data.message || 'Error occurred.';
        }
    });
});

function removePeerAssignment(userId, userName) {
    if (!confirm('Are you sure you want to remove ' + userName + ' from this role?')) {
        return;
    }

    fetch(`/admin/rbac/roles/${currentOpenRoleId}/assignments/${userId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            cancelEditAssignment();
            fetchAssignments();
        } else {
            alert(data.message || 'Failed to remove assignment.');
        }
    });
}
</script>
@endpush
@endsection
