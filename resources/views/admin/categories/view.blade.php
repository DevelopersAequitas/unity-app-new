@extends('admin.layouts.app')

@section('title', 'View Circle Category')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-1">View Circle Category</h1>
        <div class="text-muted small">Circle Categories &gt; View</div>
    </div>
    <a href="{{ route('admin.categories.index') }}" class="btn btn-sm btn-outline-secondary">Back</a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white">
        <h2 class="h6 mb-0">Main Category Info</h2>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="text-muted small">Category Name</div>
                <div class="fw-semibold">{{ $category->category_name }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Sector</div>
                <div>{{ $category->sector ?: '—' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Remarks</div>
                <div>{{ $category->remarks ?: '—' }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Level</div>
                <div>{{ $category->level ?? '—' }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0">Category Hierarchy Explorer</h2>
        <div class="small text-muted">
            Level 2: <strong>{{ $counts['level2'] }}</strong> |
            Level 3: <strong>{{ $counts['level3'] }}</strong> |
            Level 4: <strong>{{ $counts['level4'] }}</strong>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="d-flex justify-content-between align-items-center">
                    <label for="level2Select" class="form-label mb-1">Select Level 2 Category</label>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-add-level="2">Add Level 2</button>
                </div>
                <select id="level2Select" class="form-select">
                    <option value="">Select Level 2 Category</option>
                    @foreach($level2Categories as $item)
                        <option value="{{ $item->id }}" data-circle-id="{{ $item->circle_category_id ?? $item->id }}">{{ $item->category_name }}</option>
                    @endforeach
                </select>
                <div id="level2Message" class="form-text text-muted mt-2">
                    @if($level2Categories->isEmpty()) No Level 2 categories found. @endif
                </div>
            </div>
            <div class="col-md-4">
                <div class="d-flex justify-content-between align-items-center">
                    <label for="level3Select" class="form-label mb-1">Select Level 3 Category</label>
                </div>
                <select id="level3Select" class="form-select" disabled>
                    <option value="">Select Level 3 Category</option>
                </select>
                <div id="level3Message" class="form-text text-muted mt-2">Select a Level 2 category.</div>
            </div>
            <div class="col-md-4">
                <div class="d-flex justify-content-between align-items-center">
                    <label for="level4Select" class="form-label mb-1">Select Level 4 Category</label>
                </div>
                <select id="level4Select" class="form-select" disabled>
                    <option value="">Select Level 4 Category</option>
                </select>
                <div id="level4Message" class="form-text text-muted mt-2">Select a Level 3 category.</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h2 class="h6 mb-0">Selected Branch Details</h2>
        <div class="d-flex flex-column align-items-end">
            <button type="button" id="contextAddCategoryBtn" class="btn btn-sm btn-primary" disabled>Add Category</button>
            <small id="contextAddCategoryHint" class="text-muted mt-1">Please select a Level 2 category first.</small>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Category Name</th>
                    <th>Level</th>
                    <th>Parent Category</th>
                    <th>Sector</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody id="branchTableBody">
                <tr>
                    <td colspan="6" class="text-center text-muted py-3">Select a category from the dropdowns to view details.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addHierarchyCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addHierarchyModalTitle">Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addHierarchyCategoryForm">
                @csrf
                <div class="modal-body">
                    <input type="hidden" id="modalLevel" name="level">
                    <input type="hidden" id="modalParentId" name="parent_id">
                    <input type="hidden" id="modalCircleParentId" name="circle_parent_id">
                    <div id="modalErrorBox" class="alert alert-danger d-none"></div>
                    <div class="mb-3 p-2 bg-light border rounded">
                        <div class="small text-muted">Parent Category</div>
                        <div class="fw-semibold" id="modalParentCategoryLabel">—</div>
                        <div class="small text-muted mt-2">Main Sector</div>
                        <div class="fw-semibold" id="modalMainSectorLabel">{{ $category->category_name }}</div>
                        <div class="small text-muted mt-2">Creating Level</div>
                        <div class="fw-semibold" id="modalCreatingLevelLabel">—</div>
                    </div>
                    <div class="mb-3">
                        <label for="modalCategoryName" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modalCategoryName" name="category_name" required>
                    </div>
                    <div>
                        <label for="modalRemarks" class="form-label">Remarks</label>
                        <textarea class="form-control" id="modalRemarks" name="remarks" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const level2Select = document.getElementById('level2Select');
    const level3Select = document.getElementById('level3Select');
    const level4Select = document.getElementById('level4Select');
    const level3Message = document.getElementById('level3Message');
    const level4Message = document.getElementById('level4Message');
    const branchTableBody = document.getElementById('branchTableBody');
    const rootSectorName = @json($category->category_name);
    const childrenUrlTemplate = @json(route('admin.categories.children', ['category' => '__ID__']));
    const hierarchyStoreUrl = @json(route('admin.categories.hierarchy.store', $category));
    const addButtons = document.querySelectorAll('[data-add-level]');
    const contextAddCategoryBtn = document.getElementById('contextAddCategoryBtn');
    const contextAddCategoryHint = document.getElementById('contextAddCategoryHint');
    const modalEl = document.getElementById('addHierarchyCategoryModal');
    const modalForm = document.getElementById('addHierarchyCategoryForm');
    const modalTitle = document.getElementById('addHierarchyModalTitle');
    const modalLevel = document.getElementById('modalLevel');
    const modalParentId = document.getElementById('modalParentId');
    const modalCircleParentId = document.getElementById('modalCircleParentId');
    const modalErrorBox = document.getElementById('modalErrorBox');
    const modalParentCategoryLabel = document.getElementById('modalParentCategoryLabel');
    const modalMainSectorLabel = document.getElementById('modalMainSectorLabel');
    const modalCreatingLevelLabel = document.getElementById('modalCreatingLevelLabel');
    const modalCategoryName = document.getElementById('modalCategoryName');
    const modalRemarks = document.getElementById('modalRemarks');
    const addModal = window.bootstrap ? new window.bootstrap.Modal(modalEl) : null;

    const clearSelect = (select, placeholder, disable = true) => {
        select.innerHTML = `<option value="">${placeholder}</option>`;
        select.disabled = disable;
    };

    const setTableRows = (rows) => {
        if (!rows.length) {
            branchTableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No categories available for selected branch.</td></tr>';
            return;
        }

        branchTableBody.innerHTML = rows.map((row) => `
            <tr>
                <td>${row.id}</td>
                <td>${row.name}</td>
                <td>${row.level ?? '—'}</td>
                <td>${row.parent_name ?? '—'}</td>
                <td>${rootSectorName ?? '—'}</td>
                <td>${row.remarks ?? '—'}</td>
            </tr>
        `).join('');
    };

    const fetchChildren = async (parentId, targetSelect, placeholder, messageEl, emptyMessage) => {
        clearSelect(targetSelect, placeholder);
        const url = childrenUrlTemplate.replace('__ID__', parentId);
        const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const payload = await response.json();
        const data = payload?.data ?? [];

        if (!data.length) {
            messageEl.textContent = emptyMessage;
            setTableRows([]);
            return [];
        }

        data.forEach((item) => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            option.dataset.circleId = item.circle_category_id || item.id;
            targetSelect.appendChild(option);
        });

        targetSelect.disabled = false;
        messageEl.textContent = '';
        setTableRows(data);

        return data;
    };

    const selectedOptionText = (select) => select.options[select.selectedIndex]?.text || null;

    const resolveParentForLevel = (level) => {
        if (level === 2) {
            return {
                categoryParentId: {{ (int) $category->id }},
                circleParentId: {{ (int) ($category->circle_category_id ?? $category->id) }},
                parentName: @json($category->category_name),
            };
        }

        if (level === 3) {
            if (! level2Select.value) {
                return null;
            }

            return {
                categoryParentId: parseInt(level2Select.value, 10),
                circleParentId: parseInt(level2Select.options[level2Select.selectedIndex]?.dataset.circleId || level2Select.value, 10),
                parentName: selectedOptionText(level2Select),
            };
        }

        if (level === 4) {
            if (! level3Select.value) {
                return null;
            }

            return {
                categoryParentId: parseInt(level3Select.value, 10),
                circleParentId: parseInt(level3Select.options[level3Select.selectedIndex]?.dataset.circleId || level3Select.value, 10),
                parentName: selectedOptionText(level3Select),
            };
        }

        return null;
    };

    const showInlineError = (message) => {
        modalErrorBox.textContent = message;
        modalErrorBox.classList.remove('d-none');
    };

    const openAddModal = (level, parentPayload) => {
        if (! parentPayload) {
            showInlineError('Please select the required parent category first.');
            return;
        }

        modalErrorBox.classList.add('d-none');
        modalErrorBox.textContent = '';
        modalForm.reset();
        modalLevel.value = level;
        modalParentId.value = parentPayload.categoryParentId;
        modalCircleParentId.value = parentPayload.circleParentId ?? '';
        modalParentCategoryLabel.textContent = parentPayload.parentName ?? '—';
        modalMainSectorLabel.textContent = rootSectorName ?? '—';
        modalCreatingLevelLabel.textContent = `Level ${level}`;
        modalTitle.textContent = `Add Level ${level} Category`;

        if (addModal) {
            addModal.show();
        }
    };

    const updateContextAddButton = () => {
        if (!level2Select.value) {
            contextAddCategoryBtn.disabled = true;
            contextAddCategoryBtn.textContent = 'Add Category';
            contextAddCategoryHint.textContent = 'Please select a Level 2 category first.';
            return;
        }

        if (level4Select.value) {
            contextAddCategoryBtn.disabled = true;
            contextAddCategoryBtn.textContent = 'Add Category';
            contextAddCategoryHint.textContent = 'Maximum hierarchy level reached.';
            return;
        }

        if (level3Select.value) {
            contextAddCategoryBtn.disabled = false;
            contextAddCategoryBtn.textContent = 'Add Level 4 Category';
            contextAddCategoryHint.textContent = 'Add a child under selected Level 3 category.';
            return;
        }

        contextAddCategoryBtn.disabled = false;
        contextAddCategoryBtn.textContent = 'Add Level 3 Category';
        contextAddCategoryHint.textContent = 'Add a child under selected Level 2 category.';
    };

    addButtons.forEach((button) => {
        button.addEventListener('click', function () {
            const level = parseInt(this.dataset.addLevel, 10);
            const parentPayload = resolveParentForLevel(level);
            openAddModal(level, parentPayload);
        });
    });

    contextAddCategoryBtn.addEventListener('click', function () {
        if (!level2Select.value) {
            level3Message.textContent = 'Please select a Level 2 category first.';
            return;
        }

        if (level4Select.value) {
            contextAddCategoryHint.textContent = 'Maximum hierarchy level reached.';
            return;
        }

        if (level3Select.value) {
            openAddModal(4, resolveParentForLevel(4));
            return;
        }

        openAddModal(3, resolveParentForLevel(3));
    });

    modalForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        modalErrorBox.classList.add('d-none');

        const formData = new FormData(modalForm);

        try {
            const response = await fetch(hierarchyStoreUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': formData.get('_token'),
                    'Accept': 'application/json',
                },
                body: formData,
            });

            const payload = await response.json();

            if (!response.ok || !payload.success) {
                showInlineError(payload.message || 'Unable to save category.');
                return;
            }

            const created = payload.data;

            if (created.level === 2) {
                const option = document.createElement('option');
                option.value = created.id;
                option.textContent = created.name;
                option.dataset.circleId = created.circle_category_id || created.id;
                level2Select.appendChild(option);
                level2Select.value = String(created.id);
                level2Select.dispatchEvent(new Event('change'));
            } else if (created.level === 3) {
                const option = document.createElement('option');
                option.value = created.id;
                option.textContent = created.name;
                option.dataset.circleId = created.circle_category_id || created.id;
                level3Select.appendChild(option);
                level3Select.disabled = false;
                level3Select.value = String(created.id);
                level3Select.dispatchEvent(new Event('change'));
            } else if (created.level === 4) {
                const option = document.createElement('option');
                option.value = created.id;
                option.textContent = created.name;
                option.dataset.circleId = created.circle_category_id || created.id;
                level4Select.appendChild(option);
                level4Select.disabled = false;
                level4Select.value = String(created.id);
                level4Select.dispatchEvent(new Event('change'));
            }

            if (addModal) {
                addModal.hide();
            }

            updateContextAddButton();
        } catch (error) {
            showInlineError('Unable to save category.');
        }
    });

    level2Select.addEventListener('change', async function () {
        clearSelect(level3Select, 'Select Level 3 Category');
        clearSelect(level4Select, 'Select Level 4 Category');
        level4Message.textContent = 'Select a Level 3 category.';

        if (!this.value) {
            level3Message.textContent = 'Select a Level 2 category.';
            setTableRows([]);
            updateContextAddButton();
            return;
        }

        await fetchChildren(this.value, level3Select, 'Select Level 3 Category', level3Message, 'No Level 3 categories found.');
        updateContextAddButton();
    });

    level3Select.addEventListener('change', async function () {
        clearSelect(level4Select, 'Select Level 4 Category');

        if (!this.value) {
            level4Message.textContent = 'Select a Level 3 category.';
            updateContextAddButton();
            return;
        }

        await fetchChildren(this.value, level4Select, 'Select Level 4 Category', level4Message, 'No Level 4 categories found.');
        updateContextAddButton();
    });

    level4Select.addEventListener('change', function () {
        if (!this.value) {
            return;
        }

        setTableRows([{
            id: this.value,
            name: this.options[this.selectedIndex].text,
            level: 4,
            parent_name: level3Select.options[level3Select.selectedIndex]?.text || null,
            sector: rootSectorName,
            remarks: null,
        }]);

        updateContextAddButton();
    });

    updateContextAddButton();
});
</script>
@endsection
