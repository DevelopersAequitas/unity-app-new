(function () {
    'use strict';

    // Intercept Select2 initialization to automatically style and customize filter dropdowns
    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
        const originalSelect2 = window.jQuery.fn.select2;
        window.jQuery.fn.select2 = function (options) {
            let isFilterCollection = false;
            this.each(function () {
                const select = this;
                const $select = window.jQuery(select);
                const isFilter = $select.hasClass('admin-filter-dropdown') 
                    || $select.closest('form').hasClass('admin-filter-form')
                    || (select.getAttribute('form') && window.jQuery('#' + select.getAttribute('form')).hasClass('admin-filter-form'));
                if (isFilter) {
                    isFilterCollection = true;
                }
            });

            if (isFilterCollection && options !== 'destroy' && options !== 'open' && options !== 'close' && typeof options === 'object') {
                // Clone options to avoid mutating shared user config
                options = window.jQuery.extend(true, {}, options);
                
                options.containerCssClass = (options.containerCssClass || '') + ' admin-filter-dropdown-container';
                options.dropdownCssClass = (options.dropdownCssClass || '') + ' admin-filter-dropdown-menu';
                
                const firstSelect = this[0];
                if (firstSelect) {
                    const $select = window.jQuery(firstSelect);
                    let emptyMessage = $select.data('empty-message') || firstSelect.dataset.emptyMessage || '';
                    if (!emptyMessage) {
                        const dropdownType = $select.data('dropdown-type') || firstSelect.dataset.dropdownType || firstSelect.name || firstSelect.id || '';
                        const typeLower = dropdownType.toLowerCase();
                        if (typeLower.includes('peer')) {
                            emptyMessage = 'No peers found.';
                        } else if (typeLower.includes('user') || typeLower.includes('member')) {
                            emptyMessage = 'No users found.';
                        } else if (typeLower.includes('circle')) {
                            emptyMessage = 'No circles found.';
                        } else if (typeLower.includes('city')) {
                            emptyMessage = 'No cities found.';
                        } else if (typeLower.includes('state')) {
                            emptyMessage = 'No states found.';
                        } else if (typeLower.includes('country')) {
                            emptyMessage = 'No countries found.';
                        } else if (typeLower.includes('company')) {
                            emptyMessage = 'No companies found.';
                        } else if (typeLower.includes('event')) {
                            emptyMessage = 'No events found.';
                        } else if (typeLower.includes('status')) {
                            emptyMessage = 'No statuses found.';
                        } else if (typeLower.includes('category')) {
                            emptyMessage = 'No categories found.';
                        } else if (typeLower.includes('priority')) {
                            emptyMessage = 'No priorities found.';
                        } else {
                            emptyMessage = 'No results found.';
                        }
                    }
                    
                    options.language = options.language || {};
                    if (!options.language.noResults) {
                        options.language.noResults = function () {
                            return emptyMessage;
                        };
                    }
                }
            } else if (options !== 'destroy' && options !== 'open' && options !== 'close' && typeof options === 'object') {
                // Non-filter admin selects: inject styling classes so they match the design
                options = window.jQuery.extend(true, {}, options);
                if (!options.containerCssClass || options.containerCssClass.indexOf('admin-') === -1) {
                    options.containerCssClass = (options.containerCssClass || '') + ' admin-select-container';
                }
                if (!options.dropdownCssClass || options.dropdownCssClass.indexOf('admin-') === -1) {
                    options.dropdownCssClass = (options.dropdownCssClass || '') + ' admin-select-dropdown';
                }
            }
            
            const res = originalSelect2.call(this, options);
            
            if (options !== 'destroy' && options !== 'open' && options !== 'close' && typeof options === 'object') {
                this.each(function () {
                    const select = this;
                    if (select instanceof HTMLSelectElement && !select.dataset.nativeChangeBound) {
                        select.dataset.nativeChangeBound = 'true';
                        let isDispatching = false;
                        window.jQuery(select).on('change.select2-native', function (e) {
                            if (isDispatching) return;
                            if (e.originalEvent) return;
                            isDispatching = true;
                            select.dispatchEvent(new Event('change', { bubbles: true }));
                            isDispatching = false;
                        });

                        window.jQuery(select).on('select2:unselecting select2:clear', function (e) {
                            const self = this;
                            setTimeout(function () {
                                self.value = '';
                                self.selectedIndex = 0;
                                window.jQuery(self).val('').trigger('change.select2-native');
                                self.dispatchEvent(new Event('change', { bubbles: true }));
                                try { window.jQuery(self).select2('close'); } catch (err) {}

                                const form = self.form || self.closest('form');
                                if (form) {
                                    if (typeof window.triggerFilterRefresh === 'function') {
                                        window.triggerFilterRefresh(form);
                                    } else if (typeof window.ajaxRefreshGrid === 'function') {
                                        window.ajaxRefreshGrid(form, 1);
                                    } else if (typeof form.requestSubmit === 'function') {
                                        form.requestSubmit();
                                    } else {
                                        form.submit();
                                    }
                                }
                            }, 10);
                        });
                    }
                });
            }
            
            return res;
        };
    }

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

    function injectGlobalOverflowFix() {
        if (document.getElementById('admin-global-overflow-fix')) {
            return;
        }

        const style = document.createElement('style');
        style.id = 'admin-global-overflow-fix';
        style.textContent = `
            html, body {
                max-width: 100%;
                overflow-x: hidden !important;
            }

            .app,
            .wrapper,
            .main-wrapper,
            .main-content,
            .content-wrapper,
            .page-wrapper,
            .page-content,
            .container-fluid,
            .container {
                max-width: 100%;
                overflow-x: hidden;
                box-sizing: border-box;
            }

            .table-responsive,
            .custom-table-scroll,
            .data-table-wrapper,
            .table-scroll-wrapper {
                max-width: 100%;
                overflow-x: auto !important;
                overflow-y: hidden;
            }

            .select2-container {
                max-width: 100% !important;
            }
        `;
        document.head.appendChild(style);
    }

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
        if (firstOption && (firstOption.value === '' || firstOption.value === 'any' || firstOption.value === 'all')) {
            return (firstOption.textContent || '').trim();
        }

        const nameOrId = select.name || select.id || '';
        if (nameOrId.toLowerCase().includes('peer')) {
            return 'Peer, company, city';
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

        if (select.classList.contains('select2-hidden-accessible')) {
            return false;
        }

        if (select.name === 'per_page' || select.id === 'perPage' || select.classList.contains('per-page-select')) {
            return false;
        }

        if (select.classList.contains('js-no-searchable-select') || select.classList.contains('js-no-select2')) {
            return false;
        }

        // Inside table filter rows, small selects (< 6 options) should stay clean native selects
        if (select.closest('tr.filter-row, th.px-2, th.px-3, thead') && select.options.length < 6) {
            if (!select.classList.contains('js-searchable-select') && !select.classList.contains('admin-filter-dropdown')) {
                return false;
            }
        }

        if (select.classList.contains('admin-filter-dropdown') || select.classList.contains(SEARCHABLE_SELECT_CLASS)) {
            return true;
        }

        const form = select.form || select.closest('form') || (select.getAttribute('form') ? document.getElementById(select.getAttribute('form')) : null);
        if (form && form.classList.contains(FILTER_FORM_CLASS)) {
            return select.options.length >= 6;
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

        document.querySelectorAll(`form.${FILTER_FORM_CLASS} select, select.admin-filter-dropdown`).forEach(function (select) {
            if (!shouldEnableSearchableSelect(select)) {
                return;
            }

            const placeholder = resolvePlaceholder(select);
            const isNoSearch = select.classList.contains('js-no-searchable-select');
            
            let emptyMessage = select.dataset.emptyMessage || '';
            if (!emptyMessage) {
                const dropdownType = select.dataset.dropdownType || select.name || select.id || '';
                const typeLower = dropdownType.toLowerCase();
                if (typeLower.includes('peer')) {
                    emptyMessage = 'No peers found.';
                } else if (typeLower.includes('user') || typeLower.includes('member')) {
                    emptyMessage = 'No users found.';
                } else if (typeLower.includes('circle')) {
                    emptyMessage = 'No circles found.';
                } else if (typeLower.includes('city')) {
                    emptyMessage = 'No cities found.';
                } else if (typeLower.includes('state')) {
                    emptyMessage = 'No states found.';
                } else if (typeLower.includes('country')) {
                    emptyMessage = 'No countries found.';
                } else if (typeLower.includes('company')) {
                    emptyMessage = 'No companies found.';
                } else if (typeLower.includes('event')) {
                    emptyMessage = 'No events found.';
                } else if (typeLower.includes('status')) {
                    emptyMessage = 'No statuses found.';
                } else if (typeLower.includes('category')) {
                    emptyMessage = 'No categories found.';
                } else if (typeLower.includes('priority')) {
                    emptyMessage = 'No priorities found.';
                } else {
                    emptyMessage = 'No results found.';
                }
            }

            const config = {
                width: '100%',
                dropdownAutoWidth: false,
                minimumResultsForSearch: isNoSearch ? Infinity : 0,
                containerCssClass: 'admin-filter-dropdown-container',
                dropdownCssClass: 'admin-filter-dropdown-menu',
                language: {
                    noResults: function () {
                        return emptyMessage;
                    }
                }
            };

            if (placeholder) {
                config.placeholder = placeholder;
                config.allowClear = true;
            }

            window.jQuery(select).select2(config);
        });
    }

    /**
     * Initialize Select2 on all admin selects that are NOT inside a filter form.
     * Covers edit pages, create pages, and dashboard selects.
     */
    function resolveAdminEmptyMessage(select) {
        if (select.dataset.emptyMessage) return select.dataset.emptyMessage;
        const type = (select.dataset.dropdownType || select.name || select.id || '').toLowerCase();
        if (type.includes('peer')) return 'No peers found.';
        if (type.includes('user') || type.includes('member') || type.includes('director')) return 'No users found.';
        if (type.includes('circle')) return 'No circles found.';
        if (type.includes('city')) return 'No cities found.';
        if (type.includes('state')) return 'No states found.';
        if (type.includes('country')) return 'No countries found.';
        if (type.includes('company')) return 'No companies found.';
        if (type.includes('event')) return 'No events found.';
        if (type.includes('status')) return 'No statuses found.';
        if (type.includes('category')) return 'No categories found.';
        if (type.includes('priority')) return 'No priorities found.';
        return 'No results found.';
    }

    function initAdminSelects() {
        if (!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2)) {
            return;
        }

        document.querySelectorAll('select').forEach(function (select) {
            if (!(select instanceof HTMLSelectElement)) return;

            // Skip disabled, multiple, or list-box selects
            if (select.disabled || select.multiple || select.size > 1) return;

            // Skip already initialized by Select2
            if (select.classList.contains('select2-hidden-accessible')) return;

            // Skip rows-per-page and explicitly excluded selects
            if (select.name === 'per_page' || select.id === 'perPage') return;
            if (select.classList.contains('per-page-select')) return;
            if (select.classList.contains('js-no-select2')) return;
            if (select.classList.contains('js-no-searchable-select')) return;

            // Skip selects in filter forms – those are handled by initFilterSelects
            const form = select.form || select.closest('form');
            if (form && form.classList.contains(FILTER_FORM_CLASS)) return;

            const placeholder = select.dataset.placeholder ||
                (select.options[0] && select.options[0].value === '' ? (select.options[0].textContent || '').trim() : '');

            const optionCount = select.options.length;
            const emptyMessage = resolveAdminEmptyMessage(select);

            const config = {
                width: '100%',
                dropdownAutoWidth: false,
                minimumResultsForSearch: optionCount >= 8 ? 0 : Infinity,
                language: {
                    noResults: function () { return emptyMessage; }
                }
            };

            if (placeholder) {
                config.placeholder = placeholder;
                config.allowClear = true;
            }

            window.jQuery(select).select2(config);
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

    function isClosedSelect2SelectionTarget(target) {
        if (!(target instanceof HTMLElement)) {
            return false;
        }

        if (!target.closest('.select2-selection')) {
            return false;
        }

        return !target.closest('.select2-container--open');
    }

    function resolveSelectFromSelect2Target(target) {
        if (!(target instanceof HTMLElement)) {
            return null;
        }

        const container = target.closest('.select2-container');
        if (!container) {
            return null;
        }

        const previous = container.previousElementSibling;
        if (previous instanceof HTMLSelectElement) {
            return previous;
        }

        const next = container.nextElementSibling;
        if (next instanceof HTMLSelectElement) {
            return next;
        }

        const containerId = container.id || '';
        if (containerId.startsWith('select2-') && containerId.endsWith('-container')) {
            const selectId = containerId.slice(8, -10);
            const byId = document.getElementById(selectId);
            if (byId instanceof HTMLSelectElement) {
                return byId;
            }
        }

        return null;
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

            if (isClosedSelect2SelectionTarget(target)) {
                const select = resolveSelectFromSelect2Target(target);
                const form = resolveFilterFormFromTarget(select);
                if (!form) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                    return;
                }

                form.submit();
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
            event.stopPropagation();

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
                return;
            }

            form.submit();
        }, true);
    }

    function observeDynamicFilters() {
        if (!('MutationObserver' in window)) {
            return;
        }

        let debounceTimer = null;

        const observer = new MutationObserver(function (mutations) {
            let hasNewNativeSelect = false;
            mutations.forEach(function (mutation) {
                if (hasNewNativeSelect) return;
                if (!mutation.addedNodes || !mutation.addedNodes.length) return;
                for (let i = 0; i < mutation.addedNodes.length; i++) {
                    const node = mutation.addedNodes[i];
                    // Only react to real <select> elements being added — NOT Select2 container spans
                    if (node instanceof HTMLElement
                        && !node.classList.contains('select2-container')
                        && !node.classList.contains('select2-search')
                        && !node.classList.contains('select2-dropdown')) {
                        const newSelects = node.tagName === 'SELECT'
                            ? [node]
                            : Array.from(node.querySelectorAll('select'));
                        // Only trigger if the new selects are not already Select2-initialized
                        const hasUninitializedSelect = newSelects.some(function (s) {
                            return !s.classList.contains('select2-hidden-accessible');
                        });
                        if (hasUninitializedSelect) {
                            hasNewNativeSelect = true;
                            break;
                        }
                    }
                }
            });

            if (hasNewNativeSelect) {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    markAdminFilterForms();
                    initFilterSelects();
                    initAdminSelects();
                }, 150);
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    function bindSelect2ClearHandler() {
        // Prevent Select2 from capturing mousedown and popping open the dropdown menu
        document.addEventListener('mousedown', function (e) {
            const clearBtn = e.target && e.target.closest ? e.target.closest('.select2-selection__clear') : null;
            if (!clearBtn) return;
            e.preventDefault();
            e.stopPropagation();
        }, true);

        // Execute clear action on click
        document.addEventListener('click', function (e) {
            const clearBtn = e.target && e.target.closest ? e.target.closest('.select2-selection__clear') : null;
            if (!clearBtn) return;

            e.preventDefault();
            e.stopPropagation();

            const container = clearBtn.closest('.select2-container');
            if (!container) return;

            let select = container.previousElementSibling;
            if (!(select instanceof HTMLSelectElement)) {
                select = container.nextElementSibling;
            }
            if (!(select instanceof HTMLSelectElement) && window.jQuery) {
                select = window.jQuery(container).prev('select')[0] || window.jQuery(container).next('select')[0];
            }
            if (!(select instanceof HTMLSelectElement)) {
                const containerId = container.id || '';
                if (containerId.startsWith('select2-') && containerId.endsWith('-container')) {
                    const selectId = containerId.slice(8, -10);
                    const byId = document.getElementById(selectId);
                    if (byId instanceof HTMLSelectElement) select = byId;
                }
            }

            if (select instanceof HTMLSelectElement) {
                select.value = '';
                select.selectedIndex = 0;
                if (window.jQuery) {
                    try {
                        window.jQuery(select).val('').trigger('change');
                        window.jQuery(select).select2('close');
                    } catch (err) {}
                }
                select.dispatchEvent(new Event('change', { bubbles: true }));

                const form = select.form || select.closest('form');
                if (form) {
                    if (typeof window.triggerFilterRefresh === 'function') {
                        window.triggerFilterRefresh(form);
                    } else if (typeof window.ajaxRefreshGrid === 'function') {
                        window.ajaxRefreshGrid(form, 1);
                    } else if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                }
            }
        }, true);
    }

    function initGlobalTableTopScrollbars() {
        document.querySelectorAll('.overflow-x-auto, .table-responsive').forEach(function(container) {
            if (container.dataset.topScrollbarInit) return;
            const table = container.querySelector('table');
            if (!table) return;

            const prev = container.previousElementSibling;
            if (prev && (prev.id === 'top-scroll-wrapper' || prev.classList.contains('table-top-scroll-wrapper'))) {
                container.dataset.topScrollbarInit = 'true';
                return;
            }

            container.dataset.topScrollbarInit = 'true';

            const topWrapper = document.createElement('div');
            topWrapper.className = 'table-top-scroll-wrapper overflow-x-auto overflow-y-hidden rounded-t-lg border-t border-l border-r bs surface-2';
            topWrapper.style.cssText = 'height: 10px; margin-bottom: 0px; display: none;';
            const topContent = document.createElement('div');
            topContent.style.cssText = 'height: 1px;';
            topWrapper.appendChild(topContent);

            container.parentNode.insertBefore(topWrapper, container);

            function updateScrollWidth() {
                const scrollW = table.scrollWidth;
                const clientW = container.clientWidth;
                topContent.style.width = scrollW + 'px';
                if (scrollW > clientW + 15) {
                    topWrapper.style.display = 'block';
                } else {
                    topWrapper.style.display = 'none';
                }
            }

            updateScrollWidth();
            window.addEventListener('resize', updateScrollWidth);
            if (window.ResizeObserver) {
                new ResizeObserver(updateScrollWidth).observe(table);
            }

            let isSyncingTop = false;
            let isSyncingContainer = false;

            topWrapper.addEventListener('scroll', function() {
                if (isSyncingTop) {
                    isSyncingTop = false;
                    return;
                }
                isSyncingContainer = true;
                container.scrollLeft = topWrapper.scrollLeft;
            });

            container.addEventListener('scroll', function() {
                if (isSyncingContainer) {
                    isSyncingContainer = false;
                    return;
                }
                isSyncingTop = true;
                topWrapper.scrollLeft = container.scrollLeft;
            });
        });
    }

    function boot() {
        injectGlobalOverflowFix();
        markAdminFilterForms();
        initFilterSelects();
        initAdminSelects();
        bindEnterSubmit();
        bindSelect2ClearHandler();
        observeDynamicFilters();
        initGlobalTableTopScrollbars();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    // Livewire and dynamic reload support
    document.addEventListener('livewire:load', boot);
    document.addEventListener('livewire:navigated', boot);
})();