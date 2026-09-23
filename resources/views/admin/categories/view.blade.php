@extends('admin.layouts.app')

@section('title', 'View Circle Category')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">View Circle Category</h1>
    <a href="{{ route('admin.categories.index') }}" class="btn btn-sm btn-outline-secondary">Back to List</a>
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
            <div class="card-header fw-semibold">Main Category Details</div>
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
    <div class="card-header fw-semibold">Hierarchical Category Tree</div>
    <div class="card-body">
        @if(empty($children) && empty($directLevel4Categories))
            <p class="text-muted mb-0">No child categories found for this main category.</p>
        @else
            <div class="small text-muted mb-2">Main Category: <strong class="text-dark">{{ $category->name }}</strong></div>

            @if(!empty($directLevel4Categories))
                <div class="border rounded p-3 mb-3 bg-light">
                    <div class="fw-semibold text-dark mb-2 pb-2 border-bottom d-flex justify-content-between align-items-center">
                        <span>Direct Subcategories (Level 4)</span>
                        <span class="badge bg-secondary-subtle text-secondary border">{{ count($directLevel4Categories) }}</span>
                    </div>
                    <ul class="list-unstyled mb-0">
                        @foreach($directLevel4Categories as $level4Category)
                            <li class="d-flex justify-content-between align-items-center py-1">
                                <span class="text-muted">• Level 4: {{ $level4Category->name }}</span>
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
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <span class="fw-semibold text-dark">Level 2: {{ $level2Node['category']->name }}</span>
                        <form method="POST" action="{{ route('admin.categories.level2.destroy', $level2Node['category']) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this Level 2 category and all its children?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger border-0 p-1" title="Delete Level 2 Category">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>

                    @if(!empty($level2Node['direct_level4']))
                        <div class="ms-3 border-start ps-3 mb-2">
                            <div class="small fw-semibold text-muted mb-1">Direct Level 4 Subcategories:</div>
                            <ul class="list-unstyled mb-0">
                                @foreach($level2Node['direct_level4'] as $level4Category)
                                    <li class="d-flex justify-content-between align-items-center py-1">
                                        <span class="text-muted">• Level 4: {{ $level4Category->name }}</span>
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
                            <div class="text-muted ms-2">No subcategories.</div>
                        @endif
                    @else
                        @foreach($level2Node['children'] as $level3Node)
                            <div class="ms-3 border-start ps-3 mb-2">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-medium text-secondary">Level 3: {{ $level3Node['category']->name }}</span>
                                    <form method="POST" action="{{ route('admin.categories.level3.destroy', $level3Node['category']) }}" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this Level 3 category and all its children?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger border-0 p-1" title="Delete Level 3 Category">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>

                                @if(empty($level3Node['children']))
                                    <div class="text-muted ms-2">No level 4 categories.</div>
                                @else
                                    <ul class="list-unstyled mb-0 mt-1">
                                        @foreach($level3Node['children'] as $level4Category)
                                            <li class="d-flex justify-content-between align-items-center py-1">
                                                <span class="text-muted">• Level 4: {{ $level4Category->name }}</span>
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
@endsection


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const level2Select = document.getElementById('level4-level2');
    const level3Select = document.getElementById('level4-level3');

    if (!level2Select || !level3Select) {
        return;
    }

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
});
</script>
@endpush
