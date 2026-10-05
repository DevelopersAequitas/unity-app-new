@extends('admin.layouts.app')

@section('title', 'View Circle Category')

@push('styles')
<style>
.tree-category-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.tree-category-item.search-hidden,
.tree-section.search-hidden,
.tree-direct-l4-group.search-hidden,
.tree-level3-section.search-hidden,
.tree-category-item.d-none {
    display: none !important;
}
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">View Circle Category</h1>
        <div class="text-muted small">{{ $category->name }}</div>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-pencil-square"></i> Edit
        </a>
        <a href="{{ route('admin.categories.export', ['category_id' => $category->id]) }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-download"></i> Export
        </a>
        <button type="button" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 shadow-sm" data-bs-toggle="modal" data-bs-target="#importCategoriesModal">
            <i class="bi bi-upload"></i> Import
        </button>
        <a href="{{ route('admin.categories.index') }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm d-flex align-items-center mb-3" role="alert">
        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
        <div>{{ session('success') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show shadow-sm d-flex align-items-center mb-3" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <div>{{ session('error') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(isset($errors) && $errors->any())
    <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-3" role="alert">
        <div class="d-flex align-items-center mb-1">
            <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
            <strong>Please check the following errors:</strong>
        </div>
        <ul class="mb-0 ps-3">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
                <span>Main Category Details</span>
                <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-6"><strong>ID:</strong> {{ $category->id }}</div>
                    <div class="col-md-6"><strong>Name:</strong> {{ $category->name }}</div>
                    <div class="col-md-6"><strong>Slug:</strong> {{ $category->slug ?: '—' }}</div>
                    <div class="col-md-6"><strong>Circle Key:</strong> {{ $category->circle_key ?: '—' }}</div>
                    <div class="col-md-6"><strong>Level:</strong> {{ $category->level }}</div>
                    <div class="col-md-6"><strong>Sort Order:</strong> {{ $category->sort_order }}</div>
                    <div class="col-md-6"><strong>Active:</strong> {{ $category->is_active ? 'Yes' : 'No' }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header fw-semibold">Child Category Summary</div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span>Level 2 count</span>
                        <span class="fw-semibold">{{ $level2Count }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span>Level 3 count</span>
                        <span class="fw-semibold">{{ $level3Count }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span>Level 4 count</span>
                        <span class="fw-semibold">{{ $level4Count }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span>Total child categories</span>
                        <span class="fw-bold">{{ $totalChildren }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">Add Child Categories</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-lg-4">
                <h6 class="mb-2">Add Level 2 Category</h6>
                <form method="POST" action="{{ route('admin.categories.level2.store', $category) }}" class="d-grid gap-2">
                    @csrf
                    <input type="text" name="name" class="form-control" placeholder="Level 2 Category Name" required>
                    <button type="submit" class="btn btn-primary btn-sm">Add Level 2</button>
                </form>
            </div>

            <div class="col-lg-4">
                <h6 class="mb-2">Add Level 3 Category</h6>
                <form method="POST" action="{{ route('admin.categories.level3.store', $category) }}" class="d-grid gap-2">
                    @csrf
                    <select name="level2_id" class="form-select" required>
                        <option value="">Select Level 2 Parent</option>
                        @foreach($level2Options as $option)
                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="name" class="form-control" placeholder="Level 3 Category Name" required>
                    <button type="submit" class="btn btn-primary btn-sm">Add Level 3</button>
                </form>
            </div>

            <div class="col-lg-4">
                <h6 class="mb-2">Add Level 4 Category</h6>
                <form method="POST" action="{{ route('admin.categories.level4.store', $category) }}" class="d-grid gap-2" id="add-level4-form">
                    @csrf
                    <div class="small text-muted mb-1">
                        Parent: <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">{{ $category->name }}</span>
                    </div>
                    <input type="text" name="name" class="form-control" placeholder="Level 4 Category Name" required>
                    
                    <div class="accordion accordion-flush" id="accL4Parent">
                        <div class="accordion-item bg-transparent">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed py-1 px-0 text-muted small bg-transparent shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#l4AdvancedParent">
                                    + Assign to Level 2 / 3 (Optional)
                                </button>
                            </h2>
                            <div id="l4AdvancedParent" class="accordion-collapse collapse">
                                <div class="d-grid gap-2 pt-2">
                                    <select name="level2_id" class="form-select form-select-sm" id="level4-level2">
                                        <option value="">Select Level 2 Parent (Optional)</option>
                                        @foreach($level2Options as $option)
                                            <option value="{{ $option->id }}">{{ $option->name }}</option>
                                        @endforeach
                                    </select>
                                    <select name="level3_id" class="form-select form-select-sm" id="level4-level3" disabled>
                                        <option value="">Select Level 3 Parent (Optional)</option>
                                        @foreach($level3Options as $option)
                                            <option value="{{ $option->id }}" data-level2-id="{{ $option->level2_id }}">{{ $option->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm">Add Level 4</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-semibold d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>Hierarchical Category Tree</span>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button" id="btnSelectAllCategories" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-check2-square"></i> <span id="selectAllBtnText">Select All</span>
            </button>
            <button type="button" id="btnBulkDeleteCategories" class="btn btn-sm btn-danger d-none align-items-center gap-1 shadow-sm">
                <i class="bi bi-trash"></i> Delete Selected (<span id="headerSelectedCount">0</span>)
            </button>
            <div class="input-group input-group-sm" style="max-width: 300px;">
                <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                <input type="text" id="categoryTreeSearch" class="form-control" placeholder="Search subcategories..." autocomplete="off">
                <button class="btn btn-primary" type="button" id="categoryTreeSearchBtn">
                    Search
                </button>
                <button class="btn btn-outline-secondary" type="button" id="categoryTreeClearBtn" style="display: none;" title="Clear search">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
    </div>
    <div class="card-body position-relative">
        {{-- Sticky Bulk Actions Notification Bar --}}
        <div id="bulkActionBar" class="alert alert-primary py-2 px-3 small mb-3 d-none align-items-center justify-content-between shadow-sm border-primary-subtle" style="position: sticky; top: 10px; z-index: 100;">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="fw-semibold text-primary">
                    <i class="bi bi-check-circle-fill me-1"></i>
                    <span id="bulkSelectedText">0 categories selected</span>
                </span>
                <button type="button" class="btn btn-outline-primary py-0 px-2" id="bulkSelectAllVisibleBtn" style="font-size: 0.75rem;">
                    Select All Visible
                </button>
                <button type="button" class="btn btn-outline-secondary py-0 px-2" id="bulkClearSelectionBtn" style="font-size: 0.75rem;">
                    Clear Selection
                </button>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-danger d-inline-flex align-items-center gap-1 shadow-sm" id="bulkActionBarDeleteBtn">
                    <i class="bi bi-trash-fill"></i> Delete Selected (<span id="bulkBarCount">0</span>)
                </button>
            </div>
        </div>

        <div id="searchResultsCount" class="alert alert-info py-2 px-3 small mb-3 align-items-center justify-content-between" style="display: none !important;">
            <span><i class="bi bi-info-circle me-1"></i> <span id="searchResultsCountText"></span></span>
            <button type="button" class="btn-close btn-close-sm" id="searchCountDismiss" aria-label="Close"></button>
        </div>
        <div id="noSearchResults" class="alert alert-warning py-2 px-3 small mb-3" style="display: none;">
            <i class="bi bi-exclamation-triangle me-1"></i> No subcategories found matching "<strong id="noSearchQuery"></strong>".
        </div>

        @php
            $hasDirectL4 = ($directLevel4Total ?? (method_exists($directLevel4Categories, 'total') ? $directLevel4Categories->total() : count($directLevel4Categories))) > 0;
            $directL4BadgeCount = $directLevel4Total ?? (method_exists($directLevel4Categories, 'total') ? $directLevel4Categories->total() : count($directLevel4Categories));
        @endphp

        @if(empty($children) && ! $hasDirectL4)
            <p class="text-muted mb-0">No child categories found for this main category.</p>
        @else
            <div class="small text-muted mb-2">Main Category: <strong class="text-dark">{{ $category->name }}</strong></div>

            @if($hasDirectL4)
                <div class="border rounded p-3 mb-3 bg-light tree-section" id="directL4Section">
                    <div class="fw-semibold text-dark mb-2 pb-2 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <input class="form-check-input section-select-all m-0" type="checkbox" id="selectAllDirectL4" data-target="#directL4Section" title="Select all Direct Level 4 categories on this page">
                            <label for="selectAllDirectL4" class="mb-0 cursor-pointer user-select-none">Direct Subcategories (Level 4)</label>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-link p-0 text-decoration-none select-section-toggle-btn small text-muted" data-target="#directL4Section" style="font-size: 0.75rem;">
                                Select Section
                            </button>
                            <span class="badge bg-secondary-subtle text-secondary border section-count-badge" id="directL4TotalBadge" data-total-count="{{ $directL4BadgeCount }}">{{ number_format($directL4BadgeCount) }}</span>
                        </div>
                    </div>
                    
                    <div class="position-relative" id="directL4Wrapper">
                        <div id="directL4Loading" class="position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 d-none align-items-center justify-content-center rounded" style="z-index: 5; min-height: 100px;">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                            <span class="small text-muted fw-medium">Loading subcategories...</span>
                        </div>
                        <ul class="list-unstyled mb-0" id="directL4List">
                            @include('admin.categories.partials.direct_level4_items', ['directLevel4Categories' => $directLevel4Categories])
                        </ul>
                    </div>

                    <div id="directL4Pagination" class="mt-3 pt-2 border-top">
                        @include('admin.categories.partials.direct_level4_pagination', ['paginator' => $directLevel4Categories])
                    </div>
                </div>
            @endif

            @foreach($children as $level2Node)
                <div class="border rounded p-3 mb-3 tree-section tree-level2-section" id="l2Section_{{ $level2Node['category']->id }}" data-name="{{ strtolower($level2Node['category']->name) }}">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <input type="checkbox" class="form-check-input category-select-checkbox m-0" name="level2_ids[]" value="{{ $level2Node['category']->id }}" id="cat_l2_{{ $level2Node['category']->id }}" form="bulkDeleteCategoriesForm">
                            <label class="fw-semibold text-dark item-name mb-0 cursor-pointer user-select-none" for="cat_l2_{{ $level2Node['category']->id }}">
                                Level 2: <span class="category-name-text">{{ $level2Node['category']->name }}</span>
                            </label>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary border-0 p-1 edit-category-btn" 
                                data-name="{{ $level2Node['category']->name }}" 
                                data-level="Level 2" 
                                data-url="{{ route('admin.categories.level2.update', $level2Node['category']) }}" 
                                title="Edit Level 2 Category">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="POST" action="{{ route('admin.categories.level2.destroy', $level2Node['category']) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this Level 2 category and all its children?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger border-0 p-1" title="Delete Level 2 Category">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>

                    @if(!empty($level2Node['direct_level4']))
                        <div class="ms-3 border-start ps-3 mb-2 tree-direct-l4-group">
                            <div class="small fw-semibold text-muted mb-1">Direct Level 4 Subcategories:</div>
                            <ul class="list-unstyled mb-0">
                                @foreach($level2Node['direct_level4'] as $level4Category)
                                    <li class="justify-content-between align-items-center py-1 tree-category-item" data-name="{{ strtolower($level4Category->name) }}">
                                        <div class="d-flex align-items-center gap-2 flex-grow-1 text-truncate pe-2">
                                            <input type="checkbox" class="form-check-input category-select-checkbox m-0" name="level4_ids[]" value="{{ $level4Category->id }}" id="cat_l4_{{ $level4Category->id }}" form="bulkDeleteCategoriesForm">
                                            <label class="text-muted item-name mb-0 cursor-pointer user-select-none text-truncate" for="cat_l4_{{ $level4Category->id }}">
                                                • Level 4: <span class="category-name-text">{{ $level4Category->name }}</span>
                                            </label>
                                        </div>
                                        <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                            <button type="button" class="btn btn-sm btn-outline-primary border-0 p-1 edit-category-btn" 
                                                data-name="{{ $level4Category->name }}" 
                                                data-level="Level 4" 
                                                data-url="{{ route('admin.categories.level4.update', $level4Category) }}" 
                                                title="Edit Level 4 Category">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" action="{{ route('admin.categories.level4.destroy', $level4Category) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this Level 4 category?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger border-0 p-1" title="Delete Level 4 Category">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(empty($level2Node['children']))
                        @if(empty($level2Node['direct_level4']))
                            <div class="text-muted ms-2 no-subs-note">No subcategories.</div>
                        @endif
                    @else
                        @foreach($level2Node['children'] as $level3Node)
                            <div class="ms-3 border-start ps-3 mb-2 tree-level3-section" id="l3Section_{{ $level3Node['category']->id }}" data-name="{{ strtolower($level3Node['category']->name) }}">
                                <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <input type="checkbox" class="form-check-input category-select-checkbox m-0" name="level3_ids[]" value="{{ $level3Node['category']->id }}" id="cat_l3_{{ $level3Node['category']->id }}" form="bulkDeleteCategoriesForm">
                                        <label class="fw-medium text-secondary item-name mb-0 cursor-pointer user-select-none" for="cat_l3_{{ $level3Node['category']->id }}">
                                            Level 3: <span class="category-name-text">{{ $level3Node['category']->name }}</span>
                                        </label>
                                    </div>
                                    <div class="d-flex align-items-center gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-primary border-0 p-1 edit-category-btn" 
                                            data-name="{{ $level3Node['category']->name }}" 
                                            data-level="Level 3" 
                                            data-url="{{ route('admin.categories.level3.update', $level3Node['category']) }}" 
                                            title="Edit Level 3 Category">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" action="{{ route('admin.categories.level3.destroy', $level3Node['category']) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this Level 3 category and all its children?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger border-0 p-1" title="Delete Level 3 Category">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                @if(empty($level3Node['children']))
                                    <div class="text-muted ms-2 no-subs-note">No level 4 categories.</div>
                                @else
                                    <ul class="list-unstyled mb-0 mt-1">
                                        @foreach($level3Node['children'] as $level4Category)
                                            <li class="justify-content-between align-items-center py-1 tree-category-item" data-name="{{ strtolower($level4Category->name) }}">
                                                <div class="d-flex align-items-center gap-2 flex-grow-1 text-truncate pe-2">
                                                    <input type="checkbox" class="form-check-input category-select-checkbox m-0" name="level4_ids[]" value="{{ $level4Category->id }}" id="cat_l4_{{ $level4Category->id }}" form="bulkDeleteCategoriesForm">
                                                    <label class="text-muted item-name mb-0 cursor-pointer user-select-none text-truncate" for="cat_l4_{{ $level4Category->id }}">
                                                        • Level 4: <span class="category-name-text">{{ $level4Category->name }}</span>
                                                    </label>
                                                </div>
                                                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                                    <button type="button" class="btn btn-sm btn-outline-primary border-0 p-1 edit-category-btn" 
                                                        data-name="{{ $level4Category->name }}" 
                                                        data-level="Level 4" 
                                                        data-url="{{ route('admin.categories.level4.update', $level4Category) }}" 
                                                        title="Edit Level 4 Category">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <form method="POST" action="{{ route('admin.categories.level4.destroy', $level4Category) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this Level 4 category?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-outline-danger border-0 p-1" title="Delete Level 4 Category">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endforeach
                    @endif
                </div>
            @endforeach
        @endif
    </div>
</div>

{{-- Edit Child Category Modal --}}
<div class="modal fade" id="editChildCategoryModal" tabindex="-1" aria-labelledby="editChildCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <form id="editChildCategoryForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title h6 mb-0" id="editChildCategoryModalLabel">
                        <i class="bi bi-pencil-square text-primary me-1"></i> Edit <span id="editChildCategoryLevelBadge"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="editChildCategoryNameInput" class="form-label small fw-semibold">Category Name</label>
                        <input type="text" name="name" id="editChildCategoryNameInput" class="form-control" required autocomplete="off">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Import Categories Modal --}}
<div class="modal fade" id="importCategoriesModal" tabindex="-1" aria-labelledby="importCategoriesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.categories.import') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title h6 mb-0" id="importCategoriesModalLabel">
                        <i class="bi bi-upload text-primary me-1"></i> Import Categories
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Upload a CSV or XLSX spreadsheet containing categories to import into the platform.</p>
                    <div class="mb-3">
                        <label for="importCategoriesFile" class="form-label small fw-semibold">Select File (.csv, .xlsx, .txt)</label>
                        <input type="file" name="file" id="importCategoriesFile" class="form-control form-control-sm" required accept=".csv,.xlsx,.txt">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bi bi-upload me-1"></i> Upload & Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Bulk Delete Categories Modal --}}
<div class="modal fade" id="bulkDeleteCategoriesModal" tabindex="-1" aria-labelledby="bulkDeleteCategoriesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow border-danger-subtle">
            <div class="modal-header border-bottom">
                <h5 class="modal-title h6 mb-0 text-danger" id="bulkDeleteCategoriesModalLabel">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Confirm Bulk Delete
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Are you sure you want to delete the selected <strong id="bulkModalCount" class="text-danger">0</strong> categories?</p>
                <div class="alert alert-warning py-2 px-3 small mb-0">
                    <i class="bi bi-info-circle me-1"></i> Any child subcategories under the selected categories will also be deactivated.
                </div>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-danger" id="bulkModalSubmitBtn">
                    <i class="bi bi-trash-fill me-1"></i> Yes, Delete Selected
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Standalone Bulk Delete Form --}}
<form id="bulkDeleteCategoriesForm" method="POST" action="{{ route('admin.categories.bulk-destroy', $category) }}" class="d-none">
    @csrf
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // -------------------------------------------------------------
    // Dynamic Level 2 -> Level 3 dropdown dependent filter in form
    // -------------------------------------------------------------
    const level2Select = document.getElementById('level4-level2');
    const level3Select = document.getElementById('level4-level3');

    if (level2Select && level3Select) {
        const originalOptions = Array.from(level3Select.querySelectorAll('option')).filter((opt) => opt.value !== '');

        const renderLevel3Options = () => {
            const selectedLevel2 = level2Select.value;
            level3Select.innerHTML = '';

            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Select Level 3 Parent (Optional)';
            level3Select.appendChild(defaultOption);

            if (!selectedLevel2) {
                level3Select.disabled = true;
                return;
            }

            level3Select.disabled = false;
            let count = 0;
            originalOptions.forEach((option) => {
                if (option.dataset.level2Id === selectedLevel2) {
                    level3Select.appendChild(option.cloneNode(true));
                    count++;
                }
            });

            if (count === 0) {
                defaultOption.textContent = 'No Level 3 categories (will attach directly to Level 2)';
            }
        };

        level2Select.addEventListener('change', renderLevel3Options);
        renderLevel3Options();
    }

    // -------------------------------------------------------------
    // State & Selection Map (Persists selected items across pages)
    // -------------------------------------------------------------
    const selectedCategoryMap = new Map(); // id -> { name: 'level4_ids[]', value: id }
    let currentPage = {{ method_exists($directLevel4Categories, 'currentPage') ? $directLevel4Categories->currentPage() : 1 }};
    let currentPerPage = {{ method_exists($directLevel4Categories, 'perPage') ? $directLevel4Categories->perPage() : 50 }};
    let currentSearchQuery = '';
    const viewUrl = "{{ route('admin.categories.view', $category) }}";

    const directL4Section = document.getElementById('directL4Section');
    const directL4List = document.getElementById('directL4List');
    const directL4Pagination = document.getElementById('directL4Pagination');
    const directL4Loading = document.getElementById('directL4Loading');
    const directL4TotalBadge = document.getElementById('directL4TotalBadge');

    const searchInput = document.getElementById('categoryTreeSearch');
    const searchBtn = document.getElementById('categoryTreeSearchBtn');
    const clearBtn = document.getElementById('categoryTreeClearBtn');
    const resultsCountBox = document.getElementById('searchResultsCount');
    const resultsCountText = document.getElementById('searchResultsCountText');
    const noResultsBox = document.getElementById('noSearchResults');
    const noSearchQueryText = document.getElementById('noSearchQuery');
    const dismissCountBtn = document.getElementById('searchCountDismiss');

    // -------------------------------------------------------------
    // Helper Display Utilities
    // -------------------------------------------------------------
    const hideTreeElement = (el) => {
        if (!el) return;
        el.classList.add('search-hidden', 'd-none');
        el.style.setProperty('display', 'none', 'important');
    };

    const showTreeElement = (el, displayType = 'block') => {
        if (!el) return;
        el.classList.remove('search-hidden', 'd-none');
        el.style.setProperty('display', displayType, 'important');
    };

    const resetTreeElement = (el) => {
        if (!el) return;
        el.classList.remove('search-hidden', 'd-none');
        el.style.removeProperty('display');
    };

    function isItemVisible(el) {
        if (!el) return false;
        if (el.classList.contains('search-hidden') || el.classList.contains('d-none')) return false;
        if (el.style.display === 'none') return false;
        const hiddenAncestor = el.closest('.search-hidden, .d-none, [style*="display: none"]');
        if (hiddenAncestor) return false;
        return el.offsetParent !== null;
    }

    const escapeRegex = (string) => {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    };

    const highlightText = (el, text, term) => {
        if (!el || !text) return;
        const regex = new RegExp(`(${escapeRegex(term)})`, 'gi');
        el.innerHTML = text.replace(regex, '<mark class="bg-warning-subtle text-dark px-1 rounded">$1</mark>');
    };

    // Store original text content of Level 2 / Level 3 nodes for highlights
    const storeOriginalTexts = () => {
        document.querySelectorAll('.category-name-text').forEach((node) => {
            if (!node.dataset.originalText) {
                node.dataset.originalText = node.textContent.trim();
            }
        });
    };
    storeOriginalTexts();

    // -------------------------------------------------------------
    // Non-Reloadable (AJAX) Direct Level 4 Pagination Loader
    // -------------------------------------------------------------
    function loadDirectL4Data(page = 1, perPage = currentPerPage, searchQuery = currentSearchQuery, callback = null) {
        if (!directL4List) {
            if (typeof callback === 'function') callback(0);
            return;
        }

        if (directL4Loading) {
            directL4Loading.classList.remove('d-none');
            directL4Loading.classList.add('d-flex');
        }

        const url = new URL(viewUrl, window.location.origin);
        url.searchParams.set('page', page);
        url.searchParams.set('per_page', perPage);
        url.searchParams.set('ajax', '1');
        if (searchQuery) {
            url.searchParams.set('search', searchQuery);
        }

        fetch(url.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) throw new Error('Network error loading categories');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                currentPage = data.current_page || page;
                currentPerPage = data.per_page || perPage;

                // Update items list and pagination container
                directL4List.innerHTML = data.items_html;
                if (directL4Pagination) {
                    directL4Pagination.innerHTML = data.pagination_html;
                }

                // Update total count badge
                if (directL4TotalBadge) {
                    const displayTotal = searchQuery ? (data.total || 0) : (data.direct_total || data.total || 0);
                    directL4TotalBadge.textContent = Number(displayTotal).toLocaleString();
                }

                // If searching and there are items, highlight search terms in direct L4
                if (searchQuery) {
                    directL4List.querySelectorAll('.category-name-text').forEach(node => {
                        node.dataset.originalText = node.textContent.trim();
                        highlightText(node, node.dataset.originalText, searchQuery);
                    });
                } else {
                    storeOriginalTexts();
                }

                // Restore checked state for any checkboxes matching selectedCategoryMap
                directL4List.querySelectorAll('.category-select-checkbox').forEach(cb => {
                    if (selectedCategoryMap.has(cb.value)) {
                        cb.checked = true;
                    }
                });

                // Show or hide direct L4 section based on total matches
                if (searchQuery && (data.total === 0)) {
                    hideTreeElement(directL4Section);
                } else {
                    showTreeElement(directL4Section, 'block');
                }

                updateBulkUi();

                if (typeof callback === 'function') {
                    callback(data.total || 0);
                }
            }
        })
        .catch(err => {
            console.error('Failed to load subcategories:', err);
        })
        .finally(() => {
            if (directL4Loading) {
                directL4Loading.classList.add('d-none');
                directL4Loading.classList.remove('d-flex');
            }
        });
    }

    // -------------------------------------------------------------
    // Pagination & Per-Page Event Handlers (No Page Reload)
    // -------------------------------------------------------------
    document.addEventListener('click', function (e) {
        const pageLink = e.target.closest('.ajax-page-link');
        if (!pageLink) return;

        e.preventDefault();
        const targetPage = parseInt(pageLink.dataset.page, 10);
        if (isNaN(targetPage) || targetPage < 1) return;
        if (pageLink.closest('.disabled') || pageLink.parentElement.classList.contains('active')) return;

        loadDirectL4Data(targetPage, currentPerPage, currentSearchQuery);
    });

    document.addEventListener('change', function (e) {
        if (e.target && e.target.id === 'directL4PerPage') {
            currentPerPage = parseInt(e.target.value, 10) || 50;
            currentPage = 1;
            loadDirectL4Data(1, currentPerPage, currentSearchQuery);
        }
    });

    // -------------------------------------------------------------
    // Category Tree Live Search & Server Filter
    // -------------------------------------------------------------
    if (searchInput) {
        const performSearch = () => {
            const query = (searchInput.value || '').trim();
            currentSearchQuery = query;

            if (!query) {
                resetSearch();
                return;
            }

            clearBtn.style.display = 'inline-block';
            let treeMatchCount = 0;
            const lowerQuery = query.toLowerCase();

            // Filter Level 2 and Level 3 sections in DOM
            const level2Sections = document.querySelectorAll('.tree-level2-section');
            level2Sections.forEach((l2Sec) => {
                const l2TextSpan = l2Sec.querySelector('label[for^="cat_l2_"] .category-name-text') || l2Sec.querySelector('.category-name-text');
                const l2OriginalText = l2TextSpan ? (l2TextSpan.dataset.originalText || l2TextSpan.textContent.trim()) : l2Sec.dataset.name;
                const l2Matches = (l2OriginalText || '').toLowerCase().includes(lowerQuery);
                let l2HasChildMatch = false;

                if (l2Matches) {
                    highlightText(l2TextSpan, l2OriginalText, query);
                    treeMatchCount++;
                } else if (l2TextSpan) {
                    l2TextSpan.textContent = l2OriginalText;
                }

                // Check direct Level 4 inside Level 2
                const directL4Group = l2Sec.querySelector('.tree-direct-l4-group');
                if (directL4Group) {
                    let directL4GroupMatch = 0;
                    const items = directL4Group.querySelectorAll('.tree-category-item');
                    items.forEach((item) => {
                        const span = item.querySelector('.category-name-text');
                        const orig = span ? (span.dataset.originalText || span.textContent.trim()) : item.dataset.name;
                        const itemMatches = (orig || '').toLowerCase().includes(lowerQuery);
                        if (itemMatches) {
                            showTreeElement(item, 'flex');
                            highlightText(span, orig, query);
                            treeMatchCount++;
                            directL4GroupMatch++;
                            l2HasChildMatch = true;
                        } else {
                            hideTreeElement(item);
                            if (span) span.textContent = orig;
                        }
                    });

                    if (directL4GroupMatch > 0) {
                        showTreeElement(directL4Group, 'block');
                    } else {
                        hideTreeElement(directL4Group);
                    }
                }

                // Check Level 3 sections inside Level 2
                const level3Sections = l2Sec.querySelectorAll('.tree-level3-section');
                level3Sections.forEach((l3Sec) => {
                    const l3TextSpan = l3Sec.querySelector('label[for^="cat_l3_"] .category-name-text') || l3Sec.querySelector('.category-name-text');
                    const l3OriginalText = l3TextSpan ? (l3TextSpan.dataset.originalText || l3TextSpan.textContent.trim()) : l3Sec.dataset.name;
                    const l3Matches = (l3OriginalText || '').toLowerCase().includes(lowerQuery);
                    let l4MatchesInL3 = 0;

                    if (l3Matches) {
                        highlightText(l3TextSpan, l3OriginalText, query);
                        treeMatchCount++;
                    } else if (l3TextSpan) {
                        l3TextSpan.textContent = l3OriginalText;
                    }

                    const l4Items = l3Sec.querySelectorAll('.tree-category-item');
                    l4Items.forEach((l4Item) => {
                        const l4Span = l4Item.querySelector('.category-name-text');
                        const l4Orig = l4Span ? (l4Span.dataset.originalText || l4Span.textContent.trim()) : l4Item.dataset.name;
                        const l4Matches = (l4Orig || '').toLowerCase().includes(lowerQuery);
                        if (l4Matches) {
                            showTreeElement(l4Item, 'flex');
                            highlightText(l4Span, l4Orig, query);
                            treeMatchCount++;
                            l4MatchesInL3++;
                        } else {
                            hideTreeElement(l4Item);
                            if (l4Span) l4Span.textContent = l4Orig;
                        }
                    });

                    const noSubsNote = l3Sec.querySelector('.no-subs-note');
                    if (noSubsNote) {
                        noSubsNote.style.display = (l3Matches && l4Items.length === 0) ? '' : 'none';
                    }

                    if (l3Matches || l4MatchesInL3 > 0) {
                        showTreeElement(l3Sec, 'block');
                        l2HasChildMatch = true;
                    } else {
                        hideTreeElement(l3Sec);
                    }
                });

                if (l2Matches || l2HasChildMatch) {
                    showTreeElement(l2Sec, 'block');
                } else {
                    hideTreeElement(l2Sec);
                }
            });

            // Perform non-reloadable AJAX search on Direct Level 4 categories
            currentPage = 1;
            loadDirectL4Data(1, currentPerPage, query, (directTotal) => {
                const totalMatches = treeMatchCount + directTotal;
                if (totalMatches > 0) {
                    noResultsBox.style.display = 'none';
                    resultsCountText.textContent = `Found ${totalMatches} matching category item${totalMatches === 1 ? '' : 's'}.`;
                    resultsCountBox.style.setProperty('display', 'flex', 'important');
                } else {
                    resultsCountBox.style.setProperty('display', 'none', 'important');
                    noSearchQueryText.textContent = query;
                    noResultsBox.style.display = 'block';
                }
            });
        };

        const resetSearch = () => {
            searchInput.value = '';
            currentSearchQuery = '';
            clearBtn.style.display = 'none';
            resultsCountBox.style.setProperty('display', 'none', 'important');
            noResultsBox.style.display = 'none';

            // Restore Level 2 / Level 3 sections
            document.querySelectorAll('.tree-level2-section').forEach(resetTreeElement);
            document.querySelectorAll('.tree-direct-l4-group').forEach(resetTreeElement);
            document.querySelectorAll('.tree-level3-section').forEach(resetTreeElement);
            document.querySelectorAll('.tree-category-item').forEach(resetTreeElement);
            document.querySelectorAll('.no-subs-note').forEach(el => el.style.display = '');

            // Restore text labels
            document.querySelectorAll('.category-name-text').forEach((node) => {
                if (node.dataset.originalText) {
                    node.textContent = node.dataset.originalText;
                }
            });

            // Reset Direct Level 4 categories via AJAX without page reload
            currentPage = 1;
            loadDirectL4Data(1, currentPerPage, '');
        };

        // Debounce search input for silky-smooth experience
        let searchTimeout = null;
        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(performSearch, 350);
        });

        searchBtn.addEventListener('click', performSearch);
        clearBtn.addEventListener('click', resetSearch);
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(searchTimeout);
                performSearch();
            }
        });

        if (dismissCountBtn) {
            dismissCountBtn.addEventListener('click', function () {
                resultsCountBox.style.setProperty('display', 'none', 'important');
            });
        }
    }

    // -------------------------------------------------------------
    // Edit Child Category Modal Handler (Event Delegation)
    // -------------------------------------------------------------
    const editModalEl = document.getElementById('editChildCategoryModal');
    const editForm = document.getElementById('editChildCategoryForm');
    const editNameInput = document.getElementById('editChildCategoryNameInput');
    const editLevelBadge = document.getElementById('editChildCategoryLevelBadge');

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.edit-category-btn');
        if (!btn) return;

        const url = btn.dataset.url;
        const name = btn.dataset.name;
        const level = btn.dataset.level || 'Category';

        if (editForm && editNameInput && editLevelBadge && editModalEl) {
            editForm.action = url;
            editNameInput.value = name;
            editLevelBadge.textContent = level;

            if (window.bootstrap && typeof bootstrap.Modal !== 'undefined') {
                const modal = bootstrap.Modal.getOrCreateInstance(editModalEl);
                modal.show();
            } else if (typeof $ !== 'undefined' && typeof $.fn.modal !== 'undefined') {
                $(editModalEl).modal('show');
            } else {
                editModalEl.classList.add('show');
                editModalEl.style.display = 'block';
            }

            setTimeout(() => editNameInput.focus(), 400);
        }
    });

    // -------------------------------------------------------------
    // Bulk Select & Delete Categories Handler
    // -------------------------------------------------------------
    const selectAllBtn = document.getElementById('btnSelectAllCategories');
    const selectAllBtnText = document.getElementById('selectAllBtnText');
    const headerDeleteBtn = document.getElementById('btnBulkDeleteCategories');
    const headerSelectedCount = document.getElementById('headerSelectedCount');
    
    const bulkActionBar = document.getElementById('bulkActionBar');
    const bulkSelectedText = document.getElementById('bulkSelectedText');
    const bulkBarCount = document.getElementById('bulkBarCount');
    const bulkActionBarDeleteBtn = document.getElementById('bulkActionBarDeleteBtn');
    const bulkSelectAllVisibleBtn = document.getElementById('bulkSelectAllVisibleBtn');
    const bulkClearSelectionBtn = document.getElementById('bulkClearSelectionBtn');
    
    const bulkDeleteModalEl = document.getElementById('bulkDeleteCategoriesModal');
    const bulkModalCount = document.getElementById('bulkModalCount');
    const bulkModalSubmitBtn = document.getElementById('bulkModalSubmitBtn');
    const bulkForm = document.getElementById('bulkDeleteCategoriesForm');

    function getVisibleCategoryCheckboxes() {
        const checkboxes = Array.from(document.querySelectorAll('.category-select-checkbox'));
        return checkboxes.filter(cb => {
            const item = cb.closest('.tree-category-item, .tree-level2-section, .tree-level3-section');
            if (!item) return true;
            return isItemVisible(item) && isItemVisible(cb);
        });
    }

    function getAllCategoryCheckboxes() {
        return Array.from(document.querySelectorAll('.category-select-checkbox'));
    }

    function updateBulkUi() {
        const count = selectedCategoryMap.size;
        const visibleCheckboxes = getVisibleCategoryCheckboxes();
        const allVisibleChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.every(cb => selectedCategoryMap.has(cb.value));

        // Update header button text
        if (selectAllBtnText) {
            selectAllBtnText.textContent = allVisibleChecked ? 'Deselect All Visible' : 'Select All Visible';
        }

        if (headerSelectedCount) {
            headerSelectedCount.textContent = count;
        }

        if (headerDeleteBtn) {
            if (count > 0) {
                headerDeleteBtn.classList.remove('d-none');
                headerDeleteBtn.classList.add('d-inline-flex');
            } else {
                headerDeleteBtn.classList.add('d-none');
                headerDeleteBtn.classList.remove('d-inline-flex');
            }
        }

        // Update sticky action bar
        if (bulkActionBar) {
            if (count > 0) {
                bulkActionBar.classList.remove('d-none');
                bulkActionBar.classList.add('d-flex');
                if (bulkSelectedText) {
                    bulkSelectedText.textContent = `${count} categor${count === 1 ? 'y' : 'ies'} selected`;
                }
                if (bulkBarCount) {
                    bulkBarCount.textContent = count;
                }
            } else {
                bulkActionBar.classList.add('d-none');
                bulkActionBar.classList.remove('d-flex');
            }
        }

        // Update section-level select all checkboxes
        document.querySelectorAll('.section-select-all').forEach(sectionCb => {
            const targetSelector = sectionCb.dataset.target;
            if (!targetSelector) return;
            const container = document.querySelector(targetSelector);
            if (!container) return;

            const sectionCheckboxes = Array.from(container.querySelectorAll('.category-select-checkbox'));
            const visibleSectionCheckboxes = sectionCheckboxes.filter(cb => {
                const item = cb.closest('.tree-category-item, .tree-level2-section, .tree-level3-section');
                return !item || (isItemVisible(item) && isItemVisible(cb));
            });

            if (visibleSectionCheckboxes.length === 0) {
                sectionCb.checked = false;
                sectionCb.indeterminate = false;
                return;
            }

            const checkedInSection = visibleSectionCheckboxes.filter(cb => selectedCategoryMap.has(cb.value)).length;
            sectionCb.checked = checkedInSection === visibleSectionCheckboxes.length;
            sectionCb.indeterminate = checkedInSection > 0 && checkedInSection < visibleSectionCheckboxes.length;
        });
    }

    // Individual checkbox change using event delegation
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('category-select-checkbox')) {
            const cb = e.target;
            if (cb.checked) {
                selectedCategoryMap.set(cb.value, { name: cb.name, value: cb.value });
            } else {
                selectedCategoryMap.delete(cb.value);
            }
            updateBulkUi();
        }
    });

    // Toggle Select All Visible
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            const visible = getVisibleCategoryCheckboxes();
            const allVisibleChecked = visible.length > 0 && visible.every(cb => selectedCategoryMap.has(cb.value));
            visible.forEach(cb => {
                cb.checked = !allVisibleChecked;
                if (!allVisibleChecked) {
                    selectedCategoryMap.set(cb.value, { name: cb.name, value: cb.value });
                } else {
                    selectedCategoryMap.delete(cb.value);
                }
            });
            updateBulkUi();
        });
    }

    if (bulkSelectAllVisibleBtn) {
        bulkSelectAllVisibleBtn.addEventListener('click', function () {
            const visible = getVisibleCategoryCheckboxes();
            visible.forEach(cb => {
                cb.checked = true;
                selectedCategoryMap.set(cb.value, { name: cb.name, value: cb.value });
            });
            updateBulkUi();
        });
    }

    if (bulkClearSelectionBtn) {
        bulkClearSelectionBtn.addEventListener('click', function () {
            selectedCategoryMap.clear();
            getAllCategoryCheckboxes().forEach(cb => {
                cb.checked = false;
            });
            updateBulkUi();
        });
    }

    // Section checkboxes and buttons using event delegation
    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('section-select-all')) {
            const sectionCb = e.target;
            const targetSelector = sectionCb.dataset.target;
            if (!targetSelector) return;
            const container = document.querySelector(targetSelector);
            if (!container) return;

            const isChecked = sectionCb.checked;
            container.querySelectorAll('.category-select-checkbox').forEach(cb => {
                const item = cb.closest('.tree-category-item, .tree-level2-section, .tree-level3-section');
                if (!item || (isItemVisible(item) && isItemVisible(cb))) {
                    cb.checked = isChecked;
                    if (isChecked) {
                        selectedCategoryMap.set(cb.value, { name: cb.name, value: cb.value });
                    } else {
                        selectedCategoryMap.delete(cb.value);
                    }
                }
            });
            updateBulkUi();
        }
    });

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.select-section-toggle-btn');
        if (!btn) return;

        e.preventDefault();
        const targetSelector = btn.dataset.target;
        if (!targetSelector) return;
        const container = document.querySelector(targetSelector);
        if (!container) return;

        const checkboxes = Array.from(container.querySelectorAll('.category-select-checkbox')).filter(cb => {
            const item = cb.closest('.tree-category-item, .tree-level2-section, .tree-level3-section');
            return !item || (isItemVisible(item) && isItemVisible(cb));
        });

        const allChecked = checkboxes.length > 0 && checkboxes.every(cb => selectedCategoryMap.has(cb.value));
        checkboxes.forEach(cb => {
            cb.checked = !allChecked;
            if (!allChecked) {
                selectedCategoryMap.set(cb.value, { name: cb.name, value: cb.value });
            } else {
                selectedCategoryMap.delete(cb.value);
            }
        });
        updateBulkUi();
    });

    // Open confirmation modal
    const openBulkDeleteModal = () => {
        const count = selectedCategoryMap.size;
        if (count === 0) {
            alert('Please select at least one category to delete.');
            return;
        }

        if (bulkModalCount) {
            bulkModalCount.textContent = count;
        }

        if (bulkDeleteModalEl) {
            if (window.bootstrap && typeof bootstrap.Modal !== 'undefined') {
                const modal = bootstrap.Modal.getOrCreateInstance(bulkDeleteModalEl);
                modal.show();
            } else if (typeof $ !== 'undefined' && typeof $.fn.modal !== 'undefined') {
                $(bulkDeleteModalEl).modal('show');
            } else {
                if (confirm(`Are you sure you want to delete the ${count} selected categories?`)) {
                    submitBulkDelete();
                }
            }
        } else {
            if (confirm(`Are you sure you want to delete the ${count} selected categories?`)) {
                submitBulkDelete();
            }
        }
    };

    if (headerDeleteBtn) {
        headerDeleteBtn.addEventListener('click', openBulkDeleteModal);
    }

    if (bulkActionBarDeleteBtn) {
        bulkActionBarDeleteBtn.addEventListener('click', openBulkDeleteModal);
    }

    function submitBulkDelete() {
        if (!bulkForm) return;

        // Clear existing dynamically generated inputs in form
        bulkForm.querySelectorAll('input[type="hidden"]:not([name="_token"])').forEach(el => el.remove());

        // Append inputs for all checked items stored in selectedCategoryMap
        selectedCategoryMap.forEach(item => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = item.name;
            input.value = item.value;
            bulkForm.appendChild(input);
        });

        if (bulkModalSubmitBtn) {
            bulkModalSubmitBtn.disabled = true;
            bulkModalSubmitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Deleting...';
        }

        bulkForm.submit();
    }

    if (bulkModalSubmitBtn) {
        bulkModalSubmitBtn.addEventListener('click', submitBulkDelete);
    }
});
</script>
@endpush
