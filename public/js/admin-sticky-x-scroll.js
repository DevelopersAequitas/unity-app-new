(function () {
    const CONTAINER_SELECTOR = '.admin-content .table-responsive, .admin-content .js-horizontal-scroll';
    const STATE = new WeakMap();

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
        state.inner.style.width = container.scrollWidth + 'px';

        if (hasOverflow) {
            container.classList.add('has-sticky-x-scroll');
            state.sticky.classList.remove('d-none');
            state.sticky.scrollLeft = container.scrollLeft;
            return;
        }

        container.classList.remove('has-sticky-x-scroll');
        state.sticky.classList.add('d-none');
        state.sticky.scrollLeft = 0;
    }

    function setupContainer(container) {
        if (STATE.has(container)) {
            return;
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
        };

        STATE.set(container, state);

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

    document.addEventListener('DOMContentLoaded', refreshAllStickyXScrollbars);
    window.addEventListener('load', refreshAllStickyXScrollbars, { passive: true });
    window.addEventListener('resize', refreshAllStickyXScrollbars, { passive: true });
    window.addEventListener('admin:refresh-sticky-x-scroll', refreshAllStickyXScrollbars);
})();
