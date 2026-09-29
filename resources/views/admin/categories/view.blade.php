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
        <div class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm" style="max-width: 320px;">
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
    <div class="card-body">
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
                    <div class="fw-semibold text-dark mb-2 pb-2 border-bottom d-flex justify-content-between align-items-center">
                        <span>Direct Subcategories (Level 4)</span>
                        <span class="badge bg-secondary-subtle text-secondary border">{{ count($directLevel4Categories) }}</span>
                    </div>
                    <ul class="list-unstyled mb-0">
                        @foreach($directLevel4Categories as $level4Category)
                            <li class="d-flex justify-content-between align-items-center py-1 tree-category-item" data-name="{{ strtolower($level4Category->name) }}">
                                <span class="text-muted item-name">• Level 4: <span class="category-name-text">{{ $level4Category->name }}</span></span>
                                <form method="POST" action="{{ route('admin.categories.level4.destroy', $level4Category) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this Level 4 category?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger border-0 p-1" title="Delete Level 4 Category">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @foreach($children as $level2Node)
                <div class="border rounded p-3 mb-3 tree-section tree-level2-section" data-name="{{ strtolower($level2Node['category']->name) }}">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <span class="fw-semibold text-dark item-name">Level 2: <span class="category-name-text">{{ $level2Node['category']->name }}</span></span>
                        <form method="POST" action="{{ route('admin.categories.level2.destroy', $level2Node['category']) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this Level 2 category and all its children?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger border-0 p-1" title="Delete Level 2 Category">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>

                    @if(!empty($level2Node['direct_level4']))
                        <div class="ms-3 border-start ps-3 mb-2 tree-direct-l4-group">
                            <div class="small fw-semibold text-muted mb-1">Direct Level 4 Subcategories:</div>
                            <ul class="list-unstyled mb-0">
                                @foreach($level2Node['direct_level4'] as $level4Category)
                                    <li class="d-flex justify-content-between align-items-center py-1 tree-category-item" data-name="{{ strtolower($level4Category->name) }}">
                                        <span class="text-muted item-name">• Level 4: <span class="category-name-text">{{ $level4Category->name }}</span></span>
                                        <form method="POST" action="{{ route('admin.categories.level4.destroy', $level4Category) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this Level 4 category?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger border-0 p-1" title="Delete Level 4 Category">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
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
                            <div class="ms-3 border-start ps-3 mb-2 tree-level3-section" data-name="{{ strtolower($level3Node['category']->name) }}">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-medium text-secondary item-name">Level 3: <span class="category-name-text">{{ $level3Node['category']->name }}</span></span>
                                    <form method="POST" action="{{ route('admin.categories.level3.destroy', $level3Node['category']) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this Level 3 category and all its children?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger border-0 p-1" title="Delete Level 3 Category">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>

                                @if(empty($level3Node['children']))
                                    <div class="text-muted ms-2 no-subs-note">No level 4 categories.</div>
                                @else
                                    <ul class="list-unstyled mb-0 mt-1">
                                        @foreach($level3Node['children'] as $level4Category)
                                            <li class="d-flex justify-content-between align-items-center py-1 tree-category-item" data-name="{{ strtolower($level4Category->name) }}">
                                                <span class="text-muted item-name">• Level 4: <span class="category-name-text">{{ $level4Category->name }}</span></span>
                                                <form method="POST" action="{{ route('admin.categories.level4.destroy', $level4Category) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this Level 4 category?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger border-0 p-1" title="Delete Level 4 Category">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
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
});
</script>
@endpush
