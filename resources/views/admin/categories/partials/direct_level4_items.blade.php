@forelse($directLevel4Categories as $level4Category)
    <li class="justify-content-between align-items-center py-1 tree-category-item" data-id="{{ $level4Category->id }}" data-name="{{ strtolower($level4Category->name) }}">
        <div class="d-flex align-items-center gap-2 flex-grow-1 text-truncate pe-2">
            <input type="checkbox" class="form-check-input category-select-checkbox m-0" name="level4_ids[]" value="{{ $level4Category->id }}" id="cat_l4_{{ $level4Category->id }}" form="bulkDeleteCategoriesForm" data-name="{{ $level4Category->name }}" data-level="Level 4">
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
@empty
    <li class="py-4 text-center text-muted small">
        <i class="bi bi-inbox text-secondary fs-4 d-block mb-1"></i>
        No subcategories found.
    </li>
@endforelse
