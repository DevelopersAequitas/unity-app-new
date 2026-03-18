const categoryPicker = document.getElementById('categoryPicker');
const addCategoryBtn = document.getElementById('addCategoryBtn');
const categoryCheckboxes = document.getElementById('categoryCheckboxList');
const selectedCategoryPreview = document.getElementById('selectedCategoryPreview');

const categoryInputs = () => categoryCheckboxes
    ? Array.from(categoryCheckboxes.querySelectorAll('input[name="categories[]"]'))
    : [];

const renderSelectedCategoryPreview = () => {
    if (!selectedCategoryPreview) {
        return;
    }

    const selected = categoryInputs().filter((checkbox) => checkbox.checked);

    if (selected.length === 0) {
        selectedCategoryPreview.innerHTML = '<span class="text-muted small">No categories selected</span>';
        return;
    }

    selectedCategoryPreview.innerHTML = '';

    selected.forEach((checkbox) => {
        const badge = document.createElement('span');
        badge.className = 'badge bg-light text-dark border';
        badge.textContent = checkbox.dataset.categoryName || 'Category';
        selectedCategoryPreview.appendChild(badge);
    });
};

const addCategoryFromPicker = () => {
    const selectedId = categoryPicker?.value;
    if (!selectedId) {
        return;
    }

    const targetCheckbox = categoryInputs().find((checkbox) => checkbox.value === selectedId);

    if (!targetCheckbox) {
        return;
    }

    targetCheckbox.checked = true;
    targetCheckbox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    if (categoryPicker) {
        categoryPicker.value = '';
    }

    renderSelectedCategoryPreview();
};

categoryInputs().forEach((checkbox) => {
    checkbox.addEventListener('change', renderSelectedCategoryPreview);
});

addCategoryBtn?.addEventListener('click', addCategoryFromPicker);
renderSelectedCategoryPreview();
