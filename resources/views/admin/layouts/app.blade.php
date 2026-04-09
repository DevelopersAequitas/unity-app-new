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
<body>
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
    @stack('scripts')
</body>
</html>
