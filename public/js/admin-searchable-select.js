(function () {
    const AUTO_SEARCH_OPTION_THRESHOLD = 12;
    const ROOT_SELECTOR = '.admin-content';
    const FORCE_SELECTOR = '.js-searchable-select, [data-searchable="true"]';
    const SKIP_SELECTOR = '.js-no-searchable-select, .js-user-select, [data-searchable="false"]';

    function hasSelect2Support() {
        return typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.select2 === 'function';
    }

    function getSelectElements(root) {
        if (!root) {
            return [];
        }

        if (root.matches && root.matches('select')) {
            return [root];
        }

        return Array.from(root.querySelectorAll('select'));
    }

    function isEligible(select) {
        if (!(select instanceof HTMLSelectElement) || select.disabled) {
            return false;
        }

        if (!select.closest(ROOT_SELECTOR) || select.matches(SKIP_SELECTOR)) {
            return false;
        }

        if (select.classList.contains('select2-hidden-accessible')) {
            return false;
        }

        if (select.multiple && !select.matches(FORCE_SELECTOR)) {
            return false;
        }

        if (select.matches(FORCE_SELECTOR)) {
            return true;
        }

        return select.options.length >= AUTO_SEARCH_OPTION_THRESHOLD;
    }

    function resolvePlaceholder(select) {
        const firstOption = select.options[0];
        if (!firstOption || firstOption.value !== '') {
            return '';
        }

        return firstOption.textContent.trim();
    }

    function initializeSelect(select) {
        if (!isEligible(select)) {
            return;
        }

        const $select = window.jQuery(select);
        const placeholder = resolvePlaceholder(select);
        const modalParent = window.jQuery(select).closest('.modal');

        $select.select2({
            width: '100%',
            minimumResultsForSearch: 0,
            allowClear: !select.required && placeholder !== '',
            placeholder: placeholder || undefined,
            dropdownParent: modalParent.length ? modalParent : undefined,
        });
    }

    function initializeSearchableSelects(root) {
        if (!hasSelect2Support()) {
            return;
        }

        getSelectElements(root || document).forEach(initializeSelect);
    }

    function observeDynamicContent() {
        const adminRoot = document.querySelector(ROOT_SELECTOR);
        if (!adminRoot || typeof MutationObserver === 'undefined') {
            return;
        }

        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (!(node instanceof Element)) {
                        return;
                    }

                    initializeSearchableSelects(node);
                });
            });
        });

        observer.observe(adminRoot, { childList: true, subtree: true });
    }

    function boot() {
        initializeSearchableSelects(document);
        observeDynamicContent();
    }

    document.addEventListener('DOMContentLoaded', boot);
    window.addEventListener('load', function () {
        initializeSearchableSelects(document);
    }, { passive: true });

    document.addEventListener('shown.bs.modal', function (event) {
        initializeSearchableSelects(event.target);
    });

    window.addEventListener('admin:refresh-searchable-selects', function () {
        initializeSearchableSelects(document);
    });
})();
