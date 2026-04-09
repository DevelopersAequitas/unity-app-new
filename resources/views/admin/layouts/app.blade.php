<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Panel')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    @stack('styles')
</head>
<body class="admin-body">
    <div class="admin-shell d-flex">
        @include('admin.partials.sidebar')
        <div class="admin-main flex-grow-1">
            @include('admin.partials.topbar')
            <main class="admin-content container-fluid py-4">
                @yield('content')
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        (function () {
            const ADMIN_SELECT_SELECTOR = '.admin-content select.form-select';
            let rescanQueued = false;

            function buildSelect2Config(selectEl) {
                const firstOption = selectEl.options.length > 0 ? selectEl.options[0] : null;
                const hasEmptyOption = !!firstOption && firstOption.value === '';
                const placeholderText = selectEl.dataset.placeholder
                    || (hasEmptyOption ? firstOption.text.trim() : '');
                const isRequired = selectEl.required || selectEl.dataset.required === 'true';
                const modalParent = selectEl.closest('.modal');

                return {
                    width: '100%',
                    placeholder: placeholderText || undefined,
                    allowClear: hasEmptyOption && !isRequired,
                    minimumResultsForSearch: 0,
                    dropdownAutoWidth: true,
                    dropdownParent: modalParent ? $(modalParent) : undefined
                };
            }

            function shouldSkipSelect(selectEl) {
                if (!selectEl.matches(ADMIN_SELECT_SELECTOR)) {
                    return true;
                }

                if (selectEl.dataset.select2 === 'off' || selectEl.dataset.select2Manual === 'true') {
                    return true;
                }

                return selectEl.classList.contains('select2-hidden-accessible');
            }

            function enhanceSelects(root) {
                if (!window.$ || !$.fn.select2) {
                    return;
                }

                const scope = root instanceof Element ? root : document;
                const selects = scope.matches?.(ADMIN_SELECT_SELECTOR)
                    ? [scope]
                    : scope.querySelectorAll(ADMIN_SELECT_SELECTOR);

                selects.forEach((selectEl) => {
                    if (shouldSkipSelect(selectEl)) {
                        return;
                    }

                    $(selectEl).select2(buildSelect2Config(selectEl));
                });
            }

            function queueRescan(root) {
                if (rescanQueued) {
                    return;
                }

                rescanQueued = true;
                requestAnimationFrame(() => {
                    rescanQueued = false;
                    enhanceSelects(root || document);
                });
            }

            window.AdminSelectEnhancer = {
                refresh(root) {
                    enhanceSelects(root || document);
                }
            };

            document.addEventListener('DOMContentLoaded', () => {
                enhanceSelects(document);

                const observer = new MutationObserver((mutations) => {
                    for (const mutation of mutations) {
                        if (mutation.type !== 'childList' || mutation.addedNodes.length === 0) {
                            continue;
                        }

                        for (const node of mutation.addedNodes) {
                            if (!(node instanceof Element)) {
                                continue;
                            }

                            if (node.matches?.(ADMIN_SELECT_SELECTOR) || node.querySelector?.(ADMIN_SELECT_SELECTOR)) {
                                queueRescan(node);
                                return;
                            }
                        }
                    }
                });

                observer.observe(document.body, { childList: true, subtree: true });

                if (window.bootstrap?.Modal) {
                    document.addEventListener('shown.bs.modal', (event) => {
                        enhanceSelects(event.target);
                    });
                }
            });
        })();
    </script>
    <script>
        (function () {
            const AREA_SELECTOR = '.admin-content .admin-sticky-scroll-area';
            const FLOATING_BOTTOM = 12;

            function resetStickyBarPosition(stickyBar) {
                stickyBar.classList.remove('is-floating', 'is-anchored');
                stickyBar.style.left = '';
                stickyBar.style.width = '';
                stickyBar.style.bottom = '';
            }

            function updateStickyBarPosition(area) {
                const content = area.querySelector('.admin-sticky-scroll-content');
                const stickyBar = area.querySelector('.admin-sticky-scrollbar');

                if (!content || !stickyBar || stickyBar.style.display === 'none') {
                    if (stickyBar) {
                        resetStickyBarPosition(stickyBar);
                    }
                    return;
                }

                const areaRect = area.getBoundingClientRect();
                const contentRect = content.getBoundingClientRect();
                const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
                const barHeight = stickyBar.offsetHeight || 16;
                const floatingTop = viewportHeight - FLOATING_BOTTOM - barHeight;
                const areaBottomTop = areaRect.bottom - barHeight;

                const isAreaVisible = areaRect.top < viewportHeight && areaRect.bottom > 0;
                if (!isAreaVisible) {
                    resetStickyBarPosition(stickyBar);
                    return;
                }

                if (floatingTop >= areaBottomTop) {
                    stickyBar.classList.remove('is-floating');
                    stickyBar.classList.add('is-anchored');
                    stickyBar.style.left = '';
                    stickyBar.style.width = '';
                    stickyBar.style.bottom = '';
                    return;
                }

                stickyBar.classList.add('is-floating');
                stickyBar.classList.remove('is-anchored');
                stickyBar.style.left = `${contentRect.left}px`;
                stickyBar.style.width = `${contentRect.width}px`;
                stickyBar.style.bottom = `${FLOATING_BOTTOM}px`;
            }

            function syncArea(area) {
                const content = area.querySelector('.admin-sticky-scroll-content');
                const stickyBar = area.querySelector('.admin-sticky-scrollbar');
                const stickyInner = area.querySelector('.admin-sticky-scrollbar-inner');

                if (!content || !stickyBar || !stickyInner) {
                    return;
                }

                stickyInner.style.width = `${content.scrollWidth}px`;
                stickyBar.style.display = content.scrollWidth > content.clientWidth ? 'block' : 'none';
                stickyBar.scrollLeft = content.scrollLeft;
                updateStickyBarPosition(area);
            }

            function initArea(area) {
                if (!(area instanceof Element) || area.dataset.stickyScrollInitialized === 'true') {
                    return;
                }

                const content = area.querySelector('.admin-sticky-scroll-content');
                const stickyBar = area.querySelector('.admin-sticky-scrollbar');

                if (!content || !stickyBar) {
                    return;
                }

                let isSyncing = false;

                content.addEventListener('scroll', () => {
                    if (isSyncing) {
                        return;
                    }

                    isSyncing = true;
                    stickyBar.scrollLeft = content.scrollLeft;
                    isSyncing = false;
                });

                stickyBar.addEventListener('scroll', () => {
                    if (isSyncing) {
                        return;
                    }

                    isSyncing = true;
                    content.scrollLeft = stickyBar.scrollLeft;
                    isSyncing = false;
                });

                const queueSync = () => requestAnimationFrame(() => syncArea(area));

                window.addEventListener('resize', queueSync);

                if (window.ResizeObserver) {
                    const resizeObserver = new ResizeObserver(queueSync);
                    resizeObserver.observe(content);
                    const table = content.querySelector('table');
                    if (table) {
                        resizeObserver.observe(table);
                    }
                }

                area.dataset.stickyScrollInitialized = 'true';
                area.__adminStickyScrollSync = queueSync;
                syncArea(area);
            }

            function initAllAreas(root) {
                const scope = root instanceof Element ? root : document;
                const areas = scope.matches?.(AREA_SELECTOR)
                    ? [scope]
                    : scope.querySelectorAll(AREA_SELECTOR);

                areas.forEach((area) => initArea(area));
            }

            function updateAllStickyPositions() {
                document.querySelectorAll(AREA_SELECTOR).forEach((area) => updateStickyBarPosition(area));
            }

            window.AdminStickyScrollbar = {
                refresh(root) {
                    initAllAreas(root || document);
                    document.querySelectorAll(AREA_SELECTOR).forEach((area) => {
                        if (typeof area.__adminStickyScrollSync === 'function') {
                            area.__adminStickyScrollSync();
                        }
                    });
                    updateAllStickyPositions();
                }
            };

            document.addEventListener('DOMContentLoaded', () => {
                initAllAreas(document);

                const observer = new MutationObserver((mutations) => {
                    for (const mutation of mutations) {
                        if (mutation.type !== 'childList' || mutation.addedNodes.length === 0) {
                            continue;
                        }

                        for (const node of mutation.addedNodes) {
                            if (!(node instanceof Element)) {
                                continue;
                            }

                            if (node.matches?.(AREA_SELECTOR) || node.querySelector?.(AREA_SELECTOR)) {
                                initAllAreas(node);
                            }
                        }
                    }
                });

                observer.observe(document.body, { childList: true, subtree: true });

                document.addEventListener('shown.bs.tab', () => window.AdminStickyScrollbar.refresh());
                document.addEventListener('shown.bs.collapse', () => window.AdminStickyScrollbar.refresh());
                document.addEventListener('shown.bs.modal', () => window.AdminStickyScrollbar.refresh());
                window.addEventListener('load', () => window.AdminStickyScrollbar.refresh());
                window.addEventListener('scroll', updateAllStickyPositions, { passive: true });
            });
        })();
    </script>
    @stack('scripts')
</body>
</html>
