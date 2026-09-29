@extends('admin.layouts.app')

@section('title', 'View Circle Category')

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

        @if(empty($children) && empty($directLevel4Categories))
            <p class="text-muted mb-0">No child categories found for this main category.</p>
        @else
            <div class="small text-muted mb-2">Main Category: <strong class="text-dark">{{ $category->name }}</strong></div>

            @if(!empty($directLevel4Categories))
                <div class="border rounded p-3 mb-3 bg-light tree-section" id="directL4Section">
                    <div class="fw-semibold text-dark mb-2 pb-2 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <input class="form-check-input section-select-all m-0" type="checkbox" id="selectAllDirectL4" data-target="#directL4Section" title="Select all Direct Level 4 categories">
                            <label for="selectAllDirectL4" class="mb-0 cursor-pointer user-select-none">Direct Subcategories (Level 4)</label>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-link p-0 text-decoration-none select-section-toggle-btn small text-muted" data-target="#directL4Section" style="font-size: 0.75rem;">
                                Select Section
                            </button>
                            <span class="badge bg-secondary-subtle text-secondary border">{{ count($directLevel4Categories) }}</span>
                        </div>
                    </div>
                    <ul class="list-unstyled mb-0">
                        @foreach($directLevel4Categories as $level4Category)
                            <li class="d-flex justify-content-between align-items-center py-1 tree-category-item" data-name="{{ strtolower($level4Category->name) }}">
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
                                    <li class="d-flex justify-content-between align-items-center py-1 tree-category-item" data-name="{{ strtolower($level4Category->name) }}">
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
                                            <li class="d-flex justify-content-between align-items-center py-1 tree-category-item" data-name="{{ strtolower($level4Category->name) }}">
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
    // Category Tree Live Search & Filter
    // -------------------------------------------------------------
    const searchInput = document.getElementById('categoryTreeSearch');
    const searchBtn = document.getElementById('categoryTreeSearchBtn');
    const clearBtn = document.getElementById('categoryTreeClearBtn');
    const resultsCountBox = document.getElementById('searchResultsCount');
    const resultsCountText = document.getElementById('searchResultsCountText');
    const noResultsBox = document.getElementById('noSearchResults');
    const noSearchQueryText = document.getElementById('noSearchQuery');
    const dismissCountBtn = document.getElementById('searchCountDismiss');

    if (searchInput) {
        // Store original text content for highlights
        const textNodes = document.querySelectorAll('.category-name-text');
        textNodes.forEach((node) => {
            node.dataset.originalText = node.textContent;
        });

        const performSearch = () => {
            const query = (searchInput.value || '').trim().toLowerCase();

            if (!query) {
                resetSearch();
                return;
            }

            clearBtn.style.display = 'inline-block';
            let matchCount = 0;

            // 1. Filter direct Level 4 section
            const directSection = document.getElementById('directL4Section');
            if (directSection) {
                let directMatchCount = 0;
                const directItems = directSection.querySelectorAll('.tree-category-item');
                directItems.forEach((item) => {
                    const textSpan = item.querySelector('.category-name-text');
                    const originalText = textSpan ? textSpan.dataset.originalText : item.dataset.name;
                    if (originalText.toLowerCase().includes(query)) {
                        item.style.display = 'flex';
                        highlightText(textSpan, originalText, query);
                        directMatchCount++;
                        matchCount++;
                    } else {
                        item.style.display = 'none';
                        if (textSpan) textSpan.textContent = originalText;
                    }
                });
                directSection.style.display = directMatchCount > 0 ? 'block' : 'none';
            }

            // 2. Filter Level 2 sections
            const level2Sections = document.querySelectorAll('.tree-level2-section');
            level2Sections.forEach((l2Sec) => {
                const l2TextSpan = l2Sec.querySelector('.category-name-text');
                const l2OriginalText = l2TextSpan ? l2TextSpan.dataset.originalText : l2Sec.dataset.name;
                const l2Matches = l2OriginalText.toLowerCase().includes(query);
                let l2HasChildMatch = false;

                if (l2Matches) {
                    highlightText(l2TextSpan, l2OriginalText, query);
                    matchCount++;
                } else if (l2TextSpan) {
                    l2TextSpan.textContent = l2OriginalText;
                }

                // Check direct Level 4 items inside this Level 2
                const directL4Group = l2Sec.querySelector('.tree-direct-l4-group');
                if (directL4Group) {
                    let directL4GroupMatch = 0;
                    const items = directL4Group.querySelectorAll('.tree-category-item');
                    items.forEach((item) => {
                        const span = item.querySelector('.category-name-text');
                        const orig = span ? span.dataset.originalText : item.dataset.name;
                        if (l2Matches || orig.toLowerCase().includes(query)) {
                            item.style.display = 'flex';
                            if (orig.toLowerCase().includes(query)) {
                                highlightText(span, orig, query);
                                matchCount++;
                            }
                            directL4GroupMatch++;
                            l2HasChildMatch = true;
                        } else {
                            item.style.display = 'none';
                            if (span) span.textContent = orig;
                        }
                    });
                    directL4Group.style.display = (l2Matches || directL4GroupMatch > 0) ? 'block' : 'none';
                }

                // Check Level 3 sections
                const level3Sections = l2Sec.querySelectorAll('.tree-level3-section');
                let l3AnyMatch = false;
                level3Sections.forEach((l3Sec) => {
                    const l3TextSpan = l3Sec.querySelector('.category-name-text');
                    const l3OriginalText = l3TextSpan ? l3TextSpan.dataset.originalText : l3Sec.dataset.name;
                    const l3Matches = l3OriginalText.toLowerCase().includes(query);
                    let l4MatchesInL3 = 0;

                    if (l3Matches) {
                        highlightText(l3TextSpan, l3OriginalText, query);
                        matchCount++;
                    } else if (l3TextSpan) {
                        l3TextSpan.textContent = l3OriginalText;
                    }

                    const l4Items = l3Sec.querySelectorAll('.tree-category-item');
                    l4Items.forEach((l4Item) => {
                        const l4Span = l4Item.querySelector('.category-name-text');
                        const l4Orig = l4Span ? l4Span.dataset.originalText : l4Item.dataset.name;
                        if (l2Matches || l3Matches || l4Orig.toLowerCase().includes(query)) {
                            l4Item.style.display = 'flex';
                            if (l4Orig.toLowerCase().includes(query)) {
                                highlightText(l4Span, l4Orig, query);
                                matchCount++;
                            }
                            l4MatchesInL3++;
                        } else {
                            l4Item.style.display = 'none';
                            if (l4Span) l4Span.textContent = l4Orig;
                        }
                    });

                    if (l2Matches || l3Matches || l4MatchesInL3 > 0) {
                        l3Sec.style.display = 'block';
                        l3AnyMatch = true;
                        l2HasChildMatch = true;
                    } else {
                        l3Sec.style.display = 'none';
                    }
                });

                if (l2Matches || l2HasChildMatch) {
                    l2Sec.style.display = 'block';
                } else {
                    l2Sec.style.display = 'none';
                }
            });

            // Summary notification
            if (matchCount > 0) {
                noResultsBox.style.display = 'none';
                resultsCountText.textContent = `Found ${matchCount} matching category item${matchCount === 1 ? '' : 's'}.`;
                resultsCountBox.style.setProperty('display', 'flex', 'important');
            } else {
                resultsCountBox.style.setProperty('display', 'none', 'important');
                noSearchQueryText.textContent = searchInput.value.trim();
                noResultsBox.style.display = 'block';
            }
        };

        const resetSearch = () => {
            searchInput.value = '';
            clearBtn.style.display = 'none';
            resultsCountBox.style.setProperty('display', 'none', 'important');
            noResultsBox.style.display = 'none';

            // Restore all items
            document.querySelectorAll('.tree-section').forEach((sec) => sec.style.display = 'block');
            document.querySelectorAll('.tree-direct-l4-group').forEach((g) => g.style.display = 'block');
            document.querySelectorAll('.tree-level3-section').forEach((s) => s.style.display = 'block');
            document.querySelectorAll('.tree-category-item').forEach((i) => i.style.display = 'flex');

            // Restore text
            textNodes.forEach((node) => {
                if (node.dataset.originalText) {
                    node.textContent = node.dataset.originalText;
                }
            });
        };

        const highlightText = (el, text, term) => {
            if (!el || !text) return;
            const regex = new RegExp(`(${escapeRegex(term)})`, 'gi');
            el.innerHTML = text.replace(regex, '<mark class="bg-warning-subtle text-dark px-1 rounded">$1</mark>');
        };

        const escapeRegex = (string) => {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        };

        // Event listeners
        searchInput.addEventListener('input', performSearch);
        searchBtn.addEventListener('click', performSearch);
        clearBtn.addEventListener('click', resetSearch);
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
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
    // Edit Child Category Modal Handler
    // -------------------------------------------------------------
    const editModalEl = document.getElementById('editChildCategoryModal');
    const editForm = document.getElementById('editChildCategoryForm');
    const editNameInput = document.getElementById('editChildCategoryNameInput');
    const editLevelBadge = document.getElementById('editChildCategoryLevelBadge');

    document.querySelectorAll('.edit-category-btn').forEach((btn) => {
        btn.addEventListener('click', function () {
            const url = this.dataset.url;
            const name = this.dataset.name;
            const level = this.dataset.level || 'Category';

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
            return item.offsetParent !== null && item.style.display !== 'none';
        });
    }

    function getAllCategoryCheckboxes() {
        return Array.from(document.querySelectorAll('.category-select-checkbox'));
    }

    function getCheckedCategoryCheckboxes() {
        return Array.from(document.querySelectorAll('.category-select-checkbox:checked'));
    }

    function updateBulkUi() {
        const checkedList = getCheckedCategoryCheckboxes();
        const count = checkedList.length;
        const visibleCheckboxes = getVisibleCategoryCheckboxes();
        const allVisibleChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.every(cb => cb.checked);

        // Update header button text
        if (selectAllBtnText) {
            selectAllBtnText.textContent = allVisibleChecked ? 'Deselect All' : 'Select All';
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
            if (sectionCheckboxes.length === 0) {
                sectionCb.checked = false;
                sectionCb.indeterminate = false;
                return;
            }

            const checkedInSection = sectionCheckboxes.filter(cb => cb.checked).length;
            sectionCb.checked = checkedInSection === sectionCheckboxes.length;
            sectionCb.indeterminate = checkedInSection > 0 && checkedInSection < sectionCheckboxes.length;
        });
    }

    // Individual checkbox change
    document.querySelectorAll('.category-select-checkbox').forEach(cb => {
        cb.addEventListener('change', updateBulkUi);
    });

    // Toggle Select All Visible
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            const visible = getVisibleCategoryCheckboxes();
            const allVisibleChecked = visible.length > 0 && visible.every(cb => cb.checked);
            visible.forEach(cb => {
                cb.checked = !allVisibleChecked;
            });
            updateBulkUi();
        });
    }

    if (bulkSelectAllVisibleBtn) {
        bulkSelectAllVisibleBtn.addEventListener('click', function () {
            const visible = getVisibleCategoryCheckboxes();
            visible.forEach(cb => {
                cb.checked = true;
            });
            updateBulkUi();
        });
    }

    if (bulkClearSelectionBtn) {
        bulkClearSelectionBtn.addEventListener('click', function () {
            getAllCategoryCheckboxes().forEach(cb => {
                cb.checked = false;
            });
            updateBulkUi();
        });
    }

    // Section checkboxes and buttons
    document.querySelectorAll('.section-select-all').forEach(sectionCb => {
        sectionCb.addEventListener('change', function () {
            const targetSelector = this.dataset.target;
            if (!targetSelector) return;
            const container = document.querySelector(targetSelector);
            if (!container) return;

            const isChecked = this.checked;
            container.querySelectorAll('.category-select-checkbox').forEach(cb => {
                const item = cb.closest('.tree-category-item, .tree-level2-section, .tree-level3-section');
                if (!item || (item.offsetParent !== null && item.style.display !== 'none')) {
                    cb.checked = isChecked;
                }
            });
            updateBulkUi();
        });
    });

    document.querySelectorAll('.select-section-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const targetSelector = this.dataset.target;
            if (!targetSelector) return;
            const container = document.querySelector(targetSelector);
            if (!container) return;

            const checkboxes = Array.from(container.querySelectorAll('.category-select-checkbox')).filter(cb => {
                const item = cb.closest('.tree-category-item, .tree-level2-section, .tree-level3-section');
                return !item || (item.offsetParent !== null && item.style.display !== 'none');
            });

            const allChecked = checkboxes.length > 0 && checkboxes.every(cb => cb.checked);
            checkboxes.forEach(cb => {
                cb.checked = !allChecked;
            });
            updateBulkUi();
        });
    });

    // Open confirmation modal
    const openBulkDeleteModal = () => {
        const checkedList = getCheckedCategoryCheckboxes();
        if (checkedList.length === 0) {
            alert('Please select at least one category to delete.');
            return;
        }

        if (bulkModalCount) {
            bulkModalCount.textContent = checkedList.length;
        }

        if (bulkDeleteModalEl) {
            if (window.bootstrap && typeof bootstrap.Modal !== 'undefined') {
                const modal = bootstrap.Modal.getOrCreateInstance(bulkDeleteModalEl);
                modal.show();
            } else if (typeof $ !== 'undefined' && typeof $.fn.modal !== 'undefined') {
                $(bulkDeleteModalEl).modal('show');
            } else {
                if (confirm(`Are you sure you want to delete the ${checkedList.length} selected categories?`)) {
                    submitBulkDelete();
                }
            }
        } else {
            if (confirm(`Are you sure you want to delete the ${checkedList.length} selected categories?`)) {
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

        // Append inputs for all checked items
        getCheckedCategoryCheckboxes().forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = cb.name;
            input.value = cb.value;
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
