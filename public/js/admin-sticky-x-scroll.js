(function () {
    const CONTAINER_SELECTOR = '.admin-content .table-responsive, .admin-content .js-horizontal-scroll';
    const STATE = new WeakMap();
    const REGISTERED_CONTAINERS = new Set();

    function syncFromContainer(container) {
        const state = STATE.get(container);
        if (!state || state.syncingFromSticky) {
            if (state) {
                state.syncingFromSticky = false;
            }
            return;
        }

        state.syncingFromContainer = true;
        state.sticky.scrollLeft = container.scrollLeft;
    }

    function syncFromSticky(container) {
        const state = STATE.get(container);
        if (!state || state.syncingFromContainer) {
            if (state) {
                state.syncingFromContainer = false;
            }
            return;
        }

        state.syncingFromSticky = true;
        container.scrollLeft = state.sticky.scrollLeft;
    }

    function updateContainer(container) {
        const state = STATE.get(container);
        if (!state) {
            return;
        }

        const hasOverflow = container.scrollWidth > container.clientWidth + 1;
        state.hasOverflow = hasOverflow;
        state.inner.style.width = container.scrollWidth + 'px';

        if (hasOverflow) {
            container.classList.add('has-sticky-x-scroll');
            state.sticky.classList.remove('d-none');
            state.sticky.scrollLeft = container.scrollLeft;
            updateStickyPosition(container);
            return;
        }

        container.classList.remove('has-sticky-x-scroll');
        state.sticky.classList.remove('is-floating');
        state.sticky.style.left = '';
        state.sticky.style.width = '';
        state.sticky.classList.add('d-none');
        state.sticky.scrollLeft = 0;
    }

    function updateStickyPosition(container) {
        const state = STATE.get(container);
        if (!state || !state.hasOverflow) {
            return;
        }

        const rect = container.getBoundingClientRect();
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
        const isVerticallyVisible = rect.bottom > 12 && rect.top < viewportHeight - 12;

        if (!isVerticallyVisible || rect.width <= 0) {
            state.sticky.classList.add('d-none');
            state.sticky.classList.remove('is-floating');
            state.sticky.style.left = '';
            state.sticky.style.width = '';
            return;
        }

        state.sticky.classList.remove('d-none');
        state.sticky.classList.add('is-floating');
        state.sticky.style.left = Math.max(rect.left, 0) + 'px';
        state.sticky.style.width = Math.min(rect.width, window.innerWidth) + 'px';
        state.sticky.scrollLeft = container.scrollLeft;
    }

    function setupContainer(container) {
        if (STATE.has(container)) {
            return;
        }

        if (container.parentElement) {
            container.parentElement.classList.add('sticky-x-scroll-scope');
        }

        const sticky = document.createElement('div');
        sticky.className = 'sticky-x-scrollbar d-none';
        sticky.setAttribute('aria-hidden', 'true');

        const inner = document.createElement('div');
        inner.className = 'sticky-x-scrollbar-inner';
        sticky.appendChild(inner);

        container.insertAdjacentElement('afterend', sticky);

        const state = {
            sticky,
            inner,
            syncingFromContainer: false,
            syncingFromSticky: false,
            hasOverflow: false,
        };

        STATE.set(container, state);
        REGISTERED_CONTAINERS.add(container);

        container.addEventListener('scroll', function () {
            syncFromContainer(container);
        }, { passive: true });

        sticky.addEventListener('scroll', function () {
            syncFromSticky(container);
        }, { passive: true });

        if ('ResizeObserver' in window) {
            const resizeObserver = new ResizeObserver(function () {
                updateContainer(container);
            });
            resizeObserver.observe(container);
            if (container.firstElementChild) {
                resizeObserver.observe(container.firstElementChild);
            }
            state.resizeObserver = resizeObserver;
        }

        updateContainer(container);
    }

    function refreshAllStickyXScrollbars() {
        document.querySelectorAll(CONTAINER_SELECTOR).forEach(function (container) {
            setupContainer(container);
            updateContainer(container);
        });
    }

    function refreshStickyPositions() {
        REGISTERED_CONTAINERS.forEach(function (container) {
            updateStickyPosition(container);
        });
    }

    function observeDynamicContent() {
        const adminRoot = document.querySelector('.admin-content');
        if (!adminRoot || typeof MutationObserver === 'undefined') {
            return;
        }

        const observer = new MutationObserver(function () {
            refreshAllStickyXScrollbars();
            refreshStickyPositions();
        });

        observer.observe(adminRoot, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class', 'style'],
        });
    }

    function schedulePostLoadRefreshes() {
        [150, 500, 1200].forEach(function (delay) {
            window.setTimeout(function () {
                refreshAllStickyXScrollbars();
                refreshStickyPositions();
            }, delay);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        refreshAllStickyXScrollbars();
        refreshStickyPositions();
        observeDynamicContent();
    });
    window.addEventListener('load', function () {
        refreshAllStickyXScrollbars();
        refreshStickyPositions();
        schedulePostLoadRefreshes();
    }, { passive: true });
    window.addEventListener('resize', function () {
        refreshAllStickyXScrollbars();
        refreshStickyPositions();
    }, { passive: true });
    window.addEventListener('scroll', refreshStickyPositions, { passive: true });
    window.addEventListener('admin:refresh-sticky-x-scroll', function () {
        refreshAllStickyXScrollbars();
        refreshStickyPositions();
    });
})();
