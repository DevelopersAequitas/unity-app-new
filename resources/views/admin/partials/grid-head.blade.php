@push('styles')
<script src="https://cdn.jsdelivr.net/highlight.js/9.9.0/highlight.min.js" async></script>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  if (typeof tailwind !== 'undefined') {
    tailwind.config = {
      corePlugins: {
        preflight: false,
      }
    }
  }
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.adminAutoFilterInitialized) return;
    window.adminAutoFilterInitialized = true;

    /* ── AbortController for cancelling stale requests ── */
    var _currentAbort = null;

    /* ── Debounce timers keyed by form ── */
    var filterDebounceTimers = typeof WeakMap !== 'undefined' ? new WeakMap() : {};

    /* ── Loading overlay ── */
    function showGridLoading(gridBody) {
        if (!gridBody) return;
        gridBody.style.opacity = '0.4';
        gridBody.style.pointerEvents = 'none';
        if (!gridBody.querySelector('._grid-loading')) {
            var tr = document.createElement('tr');
            tr.className = '_grid-loading';
            tr.innerHTML = '<td colspan="100" style="text-align:center;padding:12px;background:transparent"><span style="opacity:.6;font-size:11px">Loading\u2026</span></td>';
            gridBody.prepend(tr);
        }
    }
    function hideGridLoading(gridBody) {
        if (!gridBody) return;
        gridBody.style.opacity = '';
        gridBody.style.pointerEvents = '';
        var el = gridBody.querySelector('._grid-loading');
        if (el) el.remove();
    }

    /* ── Build URL from form + extra form-associated inputs ── */
    function buildFetchUrl(form, page) {
        var data = new FormData(form);
        data.set('page', page !== undefined ? String(page) : '1');

        /* Include form-associated inputs (inputs with form="<formId>") */
        if (form.id) {
            var linked = document.querySelectorAll('[form="' + form.id + '"]');
            linked.forEach(function(el) {
                if (!el.name) return;
                if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
                if (!data.has(el.name)) data.append(el.name, el.value);
            });
        }

        var params = new URLSearchParams();
        for (var pair of data.entries()) {
            if (typeof pair[1] === 'string') params.append(pair[0], pair[1]);
        }

        var base = form.action || window.location.pathname;
        return base + (base.indexOf('?') === -1 ? '?' : '&') + params.toString();
    }

    /* ── Core AJAX refresh ── */
    function ajaxRefreshGrid(form, page) {
        var gridBody       = document.getElementById('grid-body');
        var gridPagination = document.getElementById('grid-pagination');
        if (!gridBody && !gridPagination) {
            /* No identifiable grid — fall back to full-page submit */
            fallbackSubmit(form);
            return;
        }

        var url = buildFetchUrl(form, page !== undefined ? page : 1);

        if (_currentAbort) { try { _currentAbort.abort(); } catch(e){} }
        var ctrl = new AbortController();
        _currentAbort = ctrl;

        if (gridBody) showGridLoading(gridBody);

        fetch(url, {
            signal : ctrl.signal,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.text(); })
        .then(function(html) {
            var parser = new DOMParser();
            var doc    = parser.parseFromString(html, 'text/html');

            var newBody = doc.getElementById('grid-body');
            if (gridBody) {
                hideGridLoading(gridBody);
                if (newBody) gridBody.innerHTML = newBody.innerHTML;
            }

            var newPag = doc.getElementById('grid-pagination');
            if (gridPagination && newPag) {
                gridPagination.innerHTML = newPag.innerHTML;
                bindPaginationLinks();
            }

            history.pushState({}, '', url);
        })
        .catch(function(err) {
            if (err.name === 'AbortError') return;
            if (gridBody) hideGridLoading(gridBody);
            window.location.href = url;
        });
    }

    /* ── Fallback: normal form submission ── */
    function fallbackSubmit(form) {
        if (!form || form.getAttribute('data-submitting') === 'true') return;
        var pi = form.querySelector('input[name="page"]');
        if (pi) pi.value = '1';
        form.setAttribute('data-submitting', 'true');
        if (typeof form.requestSubmit === 'function') form.requestSubmit();
        else form.submit();
    }

    /* ── Trigger filter (AJAX if possible, else fallback) ── */
    function triggerFilterRefresh(form) {
        if (!form) return;
        if (document.getElementById('grid-body') || document.getElementById('grid-pagination')) {
            ajaxRefreshGrid(form, 1);
        } else {
            fallbackSubmit(form);
        }
    }

    /* ── Bind pagination links in #grid-pagination ── */
    function onPaginationClick(e) {
        var href = this.getAttribute('href');
        if (!href || href === '#') return;
        e.preventDefault();
        var url  = new URL(href, window.location.href);
        var page = url.searchParams.get('page') || '1';
        var form = findFilterForm();
        if (!form) { window.location.href = href; return; }
        ajaxRefreshGrid(form, page);
    }
    function bindPaginationLinks() {
        var pag = document.getElementById('grid-pagination');
        if (!pag) return;
        pag.querySelectorAll('a[href]').forEach(function(a) {
            a.removeEventListener('click', onPaginationClick);
            a.addEventListener('click', onPaginationClick);
        });
    }

    /* ── Global Clear Filters Handler ── */
    window.clearAdminFilters = function(e, formId) {
        if (e && e.preventDefault) e.preventDefault();
        var form = formId ? document.getElementById(formId) : findFilterForm();
        if (!form) return;
        var inputs = form.querySelectorAll('input, select');
        inputs.forEach(function(el) {
            if (el.type === 'hidden' || el.type === 'submit' || el.type === 'button') return;
            if (el.tagName.toLowerCase() === 'select') {
                el.selectedIndex = 0;
            } else if (el.type === 'checkbox' || el.type === 'radio') {
                el.checked = false;
            } else {
                el.value = '';
            }
        });
        if (form.id) {
            document.querySelectorAll('[form="' + form.id + '"]').forEach(function(el) {
                if (el.tagName.toLowerCase() === 'select') el.selectedIndex = 0;
                else if (el.type === 'checkbox' || el.type === 'radio') el.checked = false;
                else el.value = '';
            });
        }
        ajaxRefreshGrid(form, 1);
    };

    /* ── Find the active filter form ── */
    function findFilterForm() {
        return document.querySelector('form.admin-filter-form, form.js-auto-filter')
            || document.querySelector('[id$="FiltersForm"][method="GET"]')
            || document.querySelector('form[method="GET"]:not([data-no-ajax])');
    }

    /* ── Input/change handler ── */
    function handleFilterInput(e) {
        var target = e.target;
        if (!target) return;

        var form = target.form || target.closest('form');
        if (!form) return;

        var method = (form.getAttribute('method') || 'GET').toUpperCase();
        if (method !== 'GET'
            && !form.classList.contains('admin-filter-form')
            && !form.classList.contains('js-auto-filter')) return;

        if (target.type === 'hidden'
            || target.id === 'selectAll'
            || target.id === 'select-all'
            || target.classList.contains('no-auto-filter')
            || target.classList.contains('member-checkbox')) return;

        /* Skip invisible / non-filter forms */
        if (form.style.display === 'none' || form.getAttribute('data-no-ajax') === 'true') return;

        var tag  = target.tagName.toLowerCase();
        var type = (target.type || '').toLowerCase();

        if (tag === 'select' || type === 'date' || type === 'datetime-local'
            || type === 'checkbox' || type === 'radio') {
            if (e.type === 'change') triggerFilterRefresh(form);
        } else if (tag === 'input'
            && (type === 'text' || type === 'search' || type === 'email' || type === 'number' || !type)) {
            if (e.type === 'input') {
                var timer = filterDebounceTimers.get ? filterDebounceTimers.get(form) : form._debounceTimer;
                if (timer) clearTimeout(timer);
                timer = setTimeout(function() { triggerFilterRefresh(form); }, 150);
                if (filterDebounceTimers.set) filterDebounceTimers.set(form, timer);
                else form._debounceTimer = timer;
            }
        }
    }

    document.addEventListener('change', handleFilterInput, true);
    document.addEventListener('input',  handleFilterInput, true);

    /* ── Intercept native form GET submissions ── */
    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (!form) return;
        if ((form.getAttribute('method') || 'GET').toUpperCase() !== 'GET') return;
        if (form.getAttribute('data-no-ajax') === 'true') return;
        if (!document.getElementById('grid-body') && !document.getElementById('grid-pagination')) return;
        e.preventDefault();
        ajaxRefreshGrid(form, 1);
    }, true);

    /* ── Initial pagination binding ── */
    bindPaginationLinks();
});
</script>
@endpush
