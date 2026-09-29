/**
 * Peers Unity Admin Panel - Local Browser Time & UTC Conversion
 * 
 * Ensures all date/time inputs display in the admin user's local browser time,
 * and automatically convert to UTC before form submission so backend and mobile
 * apps always work with standard UTC timestamps.
 */
(function () {
    'use strict';

    function getUserTimezone() {
        try {
            return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
        } catch (e) {
            return 'UTC';
        }
    }

    /**
     * Converts a UTC ISO string (e.g. "2026-10-01T04:30:00.000Z") to local "YYYY-MM-DDTHH:mm" for datetime-local input
     */
    function utcToLocalInputString(utcStr) {
        if (!utcStr) return '';
        // If string lacks timezone indicator (Z or offset), treat as UTC
        let normalized = String(utcStr).trim();
        if (!normalized.endsWith('Z') && !normalized.endsWith('z') && !/[+-]\d{2}(:?\d{2})?$/.test(normalized)) {
            normalized = normalized.replace(' ', 'T') + 'Z';
        }
        const d = new Date(normalized);
        if (isNaN(d.getTime())) return '';

        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const h = String(d.getHours()).padStart(2, '0');
        const min = String(d.getMinutes()).padStart(2, '0');
        return `${y}-${m}-${day}T${h}:${min}`;
    }

    /**
     * Converts local "YYYY-MM-DDTHH:mm" from datetime-local input to UTC ISO string
     */
    function localInputToUtcIso(localVal) {
        if (!localVal) return '';
        const [datePart, timePart] = localVal.split('T');
        if (!datePart || !timePart) return '';

        const [year, month, day] = datePart.split('-').map(Number);
        const timeTokens = timePart.split(':').map(Number);
        const hours = timeTokens[0] || 0;
        const minutes = timeTokens[1] || 0;
        const seconds = timeTokens[2] || 0;

        const localDate = new Date(year, month - 1, day, hours, minutes, seconds);
        if (isNaN(localDate.getTime())) return '';
        return localDate.toISOString();
    }

    /**
     * Formats a UTC date into user's local readable string
     */
    function formatUtcToLocalReadable(utcStr, format) {
        if (!utcStr) return '-';
        let normalized = String(utcStr).trim();
        if (!normalized.endsWith('Z') && !normalized.endsWith('z') && !/[+-]\d{2}(:?\d{2})?$/.test(normalized)) {
            normalized = normalized.replace(' ', 'T') + 'Z';
        }
        const d = new Date(normalized);
        if (isNaN(d.getTime())) return utcStr;

        if (format === 'date') {
            return d.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
        }
        if (format === 'time') {
            return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', hour12: true });
        }
        return d.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' }) + ', ' +
               d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', hour12: true });
    }

    /**
     * Populate all input[type="datetime-local"] that have data-utc attribute
     */
    function initializeDatetimeInputs() {
        document.querySelectorAll('input[type="datetime-local"]').forEach(input => {
            const utcVal = input.getAttribute('data-utc');
            if (utcVal) {
                const localStr = utcToLocalInputString(utcVal);
                if (localStr) {
                    input.value = localStr;
                }
            }
        });
    }

    /**
     * Convert and format all .utc-to-local display elements
     */
    function initializeDisplayElements() {
        document.querySelectorAll('.utc-to-local[data-utc]').forEach(el => {
            const utc = el.getAttribute('data-utc');
            const fmt = el.getAttribute('data-format');
            if (utc) {
                el.textContent = formatUtcToLocalReadable(utc, fmt);
                el.title = `Local time: ${formatUtcToLocalReadable(utc)} (${getUserTimezone()})`;
            }
        });

        // Timezone badge labels
        const tzName = getUserTimezone();
        document.querySelectorAll('.user-local-tz, #userLocalTzDisplay').forEach(el => {
            el.textContent = tzName;
        });
    }

    /**
     * Form submission interceptor to convert datetime-local inputs to UTC before sending
     */
    function setupFormSubmitInterceptor() {
        document.addEventListener('submit', function (e) {
            const form = e.target;
            if (!(form instanceof HTMLFormElement)) return;

            // Ensure timezone field is sent with every form
            let tzInput = form.querySelector('input[name="_browser_timezone"]');
            if (!tzInput) {
                tzInput = document.createElement('input');
                tzInput.type = 'hidden';
                tzInput.name = '_browser_timezone';
                form.appendChild(tzInput);
            }
            tzInput.value = getUserTimezone();

            // Find all datetime-local inputs inside this form
            const dtInputs = form.querySelectorAll('input[type="datetime-local"]');
            if (dtInputs.length === 0) return;

            dtInputs.forEach(input => {
                const name = input.getAttribute('name');
                if (!name) return;

                const localVal = input.value;
                if (!localVal) return;

                const utcIso = localInputToUtcIso(localVal);
                if (!utcIso) return;

                // Create or reuse hidden UTC input for this field
                let hiddenInput = form.querySelector(`input[type="hidden"][data-utc-for="${name}"]`);
                if (!hiddenInput) {
                    hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = name;
                    hiddenInput.setAttribute('data-utc-for', name);
                    form.appendChild(hiddenInput);
                }
                hiddenInput.value = utcIso;

                // Temporarily disable the name on the visible datetime-local input so only the UTC ISO string submits
                input.dataset.originalName = name;
                input.removeAttribute('name');
            });

            // Restore input names after submission trigger in case submission is halted or client navigates back
            setTimeout(() => {
                restoreInputNames(form);
            }, 800);
        }, true);

        // Restore input names if returning via browser bfcache
        window.addEventListener('pageshow', function () {
            document.querySelectorAll('form').forEach(restoreInputNames);
        });
    }

    function restoreInputNames(form) {
        if (!form) return;
        form.querySelectorAll('input[type="datetime-local"][data-original-name]').forEach(input => {
            input.setAttribute('name', input.dataset.originalName);
            delete input.dataset.originalName;
        });
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initializeDatetimeInputs();
            initializeDisplayElements();
            setupFormSubmitInterceptor();
        });
    } else {
        initializeDatetimeInputs();
        initializeDisplayElements();
        setupFormSubmitInterceptor();
    }

    // Expose utility globally
    window.AdminDateTimeUtc = {
        getUserTimezone: getUserTimezone,
        utcToLocalInputString: utcToLocalInputString,
        localInputToUtcIso: localInputToUtcIso,
        formatUtcToLocalReadable: formatUtcToLocalReadable,
        refresh: function () {
            initializeDatetimeInputs();
            initializeDisplayElements();
        }
    };
})();
