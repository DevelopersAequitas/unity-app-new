(function () {
    'use strict';

    const FILTER_FORM_CLASS = 'admin-filter-form';
    const SEARCHABLE_SELECT_CLASS = 'js-searchable-select';
    const FILTER_FIELD_SELECTOR = [
        'input[type="text"]',
        'input[type="search"]',
        'input[type="date"]',
        'input[type="number"]',
        'input[type="email"]',
        'input[type="tel"]',
        'select'
    ].join(',');

    function isGetForm(form) {
        return form instanceof HTMLFormElement
            && (form.getAttribute('method') || 'GET').toUpperCase() === 'GET';
    }

    function getAssociatedFields(form) {
        const inlineFields = Array.from(form.querySelectorAll(FILTER_FIELD_SELECTOR));

        if (!form.id) {
            return inlineFields;
        }

        const linkedFields = Array.from(document.querySelectorAll(`[form="${form.id}"]`)).filter(function (field) {
            return field.matches(FILTER_FIELD_SELECTOR);
        });

        return inlineFields.concat(linkedFields.filter(function (field) {
            return !inlineFields.includes(field);
        }));
    }

    function isAdminFilterForm(form) {
        if (!isGetForm(form)) {
            return false;
        }

        if (form.dataset.filterForm === 'false' || form.dataset.enterSubmit === 'off') {
            return false;
        }

        if (form.closest('.modal')) {
            return false;
        }

        if (form.classList.contains(FILTER_FORM_CLASS)) {
            return true;
        }

        const idNameAction = [form.id, form.getAttribute('name'), form.getAttribute('action')].join(' ').toLowerCase();
        if (idNameAction.includes('export')) {
            return false;
        }

        const fields = getAssociatedFields(form);
        if (!fields.length) {
            return false;
        }

        return true;
    }

    function markAdminFilterForms() {
        document.querySelectorAll('form').forEach(function (form) {
            if (isAdminFilterForm(form)) {
                form.classList.add(FILTER_FORM_CLASS);
            }
        });
    }

    function resolvePlaceholder(select) {
        if (select.dataset.placeholder) {
            return select.dataset.placeholder;
        }

        const firstOption = select.options[0];
        if (firstOption && firstOption.value === '') {
            return (firstOption.textContent || '').trim();
        }

        return '';
    }

    function shouldEnableSearchableSelect(select) {
        if (!(select instanceof HTMLSelectElement)) {
            return false;
        }

        if (select.disabled || select.multiple || select.size > 1) {
            return false;
        }

        if (select.classList.contains('select2-hidden-accessible') || select.classList.contains('js-no-searchable-select')) {
            return false;
        }

        if (select.classList.contains(SEARCHABLE_SELECT_CLASS)) {
            return true;
        }

        return select.options.length >= 8;
    }

    function getFilterSelectsForForm(form) {
        return getAssociatedFields(form).filter(function (field) {
            return field instanceof HTMLSelectElement;
        });
    }

    function initFilterSelects() {
        if (!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2)) {
            return;
        }

        document.querySelectorAll(`form.${FILTER_FORM_CLASS}`).forEach(function (form) {
            getFilterSelectsForForm(form).forEach(function (select) {
                if (!shouldEnableSearchableSelect(select)) {
                    return;
                }

                const placeholder = resolvePlaceholder(select);
                const config = {
                    width: '100%',
                    minimumResultsForSearch: 0,
                };

                if (placeholder) {
                    config.placeholder = placeholder;
                    config.allowClear = true;
                }

                window.jQuery(select).select2(config);
            });
        });
    }

    function isInteractiveTypingField(target) {
        if (!(target instanceof HTMLElement)) {
            return false;
        }

        if (target.tagName === 'TEXTAREA' || target.isContentEditable) {
            return true;
        }

        if (target.classList.contains('select2-search__field')) {
            return true;
        }

        if (target.closest('.select2-container--open')) {
            return true;
        }

        return false;
    }

    function resolveFilterFormFromTarget(target) {
        if (!(target instanceof HTMLElement)) {
            return null;
        }

        const form = target.form
            || target.closest('form')
            || (target.getAttribute('form') ? document.getElementById(target.getAttribute('form')) : null);

        if (!isAdminFilterForm(form)) {
            return null;
        }

        return form;
    }

    function bindEnterSubmit() {
        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') {
                return;
            }

            const target = event.target;
            if (isInteractiveTypingField(target)) {
                return;
            }

            if (!(target instanceof HTMLElement) || !target.matches(FILTER_FIELD_SELECTOR)) {
                return;
            }

            const form = resolveFilterFormFromTarget(target);
            if (!form) {
                return;
            }

            event.preventDefault();

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
                return;
            }

            form.submit();
        });
    }

    function boot() {
        markAdminFilterForms();
        initFilterSelects();
        bindEnterSubmit();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
