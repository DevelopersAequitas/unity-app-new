/**
 * PEERS GLOBAL UNITY — ADMIN GRID TEXT CLAMP & HOVER TOOLTIP ENGINE
 * Automatically manages 2-line text truncation and full-text hover tooltips
 * across all Admin Panel tables and grids.
 */
document.addEventListener('DOMContentLoaded', function () {
    function getTargetElement(e) {
        if (!e.target || !e.target.closest) return null;
        return e.target.closest('.admin-grid-text-clamp, .admin-grid-text-single, [data-clamp-tooltip]');
    }

    function checkIsTruncated(el) {
        if (!el) return false;
        // Check if content exceeds container height or width
        const hasVerticalOverflow = el.scrollHeight > el.clientHeight + 1;
        const hasHorizontalOverflow = el.scrollWidth > el.clientWidth + 1;
        return hasVerticalOverflow || hasHorizontalOverflow;
    }

    function getFullTextContent(el) {
        return (
            el.getAttribute('data-full-text') ||
            el.getAttribute('data-original-title') ||
            el.getAttribute('title') ||
            el.innerText ||
            el.textContent ||
            ''
        ).trim();
    }

    // Delegate mouseover on document to support static & AJAX/JS rendered cells
    document.addEventListener('mouseover', function (e) {
        const el = getTargetElement(e);
        if (!el) return;

        const isTruncated = checkIsTruncated(el);

        if (isTruncated) {
            const text = getFullTextContent(el);
            if (!text) return;

            // Remove native title attribute to prevent dual browser tooltip
            if (el.hasAttribute('title')) {
                el.setAttribute('data-original-title', el.getAttribute('title'));
                el.removeAttribute('title');
            }

            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                let instance = bootstrap.Tooltip.getInstance(el);
                if (!instance) {
                    instance = new bootstrap.Tooltip(el, {
                        title: text,
                        container: 'body',
                        trigger: 'hover',
                        placement: 'top',
                        fallbackPlacements: ['bottom', 'right', 'left'],
                        boundary: 'window',
                        customClass: 'admin-grid-tooltip'
                    });
                } else if (typeof instance.setContent === 'function') {
                    instance.setContent({ '.tooltip-inner': text });
                }
                instance.show();
            }
        } else {
            // Dispose tooltip if content is not truncated
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const instance = bootstrap.Tooltip.getInstance(el);
                if (instance) {
                    instance.dispose();
                }
            }
            if (el.hasAttribute('title')) {
                el.removeAttribute('title');
            }
        }
    }, { passive: true });

    document.addEventListener('mouseleave', function (e) {
        if (!e.target || !e.target.closest) return;
        const el = e.target.closest('.admin-grid-text-clamp, .admin-grid-text-single, [data-clamp-tooltip]');
        if (el && typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const instance = bootstrap.Tooltip.getInstance(el);
            if (instance) {
                instance.hide();
            }
        }
    }, true);
});
