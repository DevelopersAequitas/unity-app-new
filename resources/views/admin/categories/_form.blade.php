<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Category Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $category->name) }}" required maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label">Slug</label>
        <input type="text" name="slug" class="form-control" value="{{ old('slug', $category->slug) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label">Circle Key</label>
        <input type="text" name="circle_key" class="form-control" value="{{ old('circle_key', $category->circle_key) }}" maxlength="255">
    </div>
    <div class="col-md-6">
        <label class="form-label">Sort Order</label>
        <input type="number" name="sort_order" class="form-control" min="0" value="{{ old('sort_order', $category->sort_order) }}">
    </div>
    <div class="col-12">
        <label class="form-label">Remarks</label>
        <textarea name="remarks" class="form-control" rows="3">{{ old('remarks', $category->remarks) }}</textarea>
    </div>
    <div class="col-md-4">
        <label class="form-label">Status</label>
        <select name="is_active" class="form-control">
            <option value="1" @selected((string) old('is_active', (int) ($category->is_active ?? true)) === '1')>Active</option>
            <option value="0" @selected((string) old('is_active', (int) ($category->is_active ?? true)) === '0')>Inactive</option>
        </select>
    </div>
</div>
