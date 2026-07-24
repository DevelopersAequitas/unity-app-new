@php
    /**
     * Admin Date-Period Filter Dropdown
     *
     * Required props:
     *   $filterName    — the <input name=""> attribute (e.g. 'joined_filter')
     *   $selectedValue — the currently active value (e.g. 'this_month')
     *
     * Optional props:
     *   $labelText     — label shown above the dropdown (default: 'Date Filter')
     *   $inputId       — id="" for the trigger button (default: based on filterName)
     */
    $labelText     = $labelText     ?? 'Date Filter';
    $inputId       = $inputId       ?? 'datePeriodFilter_' . str_replace([' ', '[', ']'], '_', $filterName ?? 'filter');
    $selectedValue = $selectedValue ?? '';

    $datePeriodOptions = [
        'this_fiscal_year'  => 'This Fiscal Year',
        'this_quarter'      => 'This Quarter',
        'this_month'        => 'This Month',
        'prev_fiscal_year'  => 'Previous Fiscal Year',
        'prev_quarter'      => 'Previous Quarter',
        'prev_month'        => 'Previous Month',
        'last_6_months'     => 'Last 6 Months',
        'last_12_months'    => 'Last 12 Months',
    ];

    $selectedLabel = $datePeriodOptions[$selectedValue] ?? 'All Dates';
@endphp

{{-- Hidden native input so the value is submitted with the form --}}
<input
    type="hidden"
    name="{{ $filterName }}"
    id="{{ $inputId }}_hidden"
    value="{{ $selectedValue }}"
>

<div class="admin-date-period-wrap" data-period-filter-name="{{ $filterName }}">
    <label class="form-label small text-muted" for="{{ $inputId }}_btn">{{ $labelText }}</label>
    <div class="admin-date-period-dropdown" id="{{ $inputId }}_dropdown">
        {{-- Trigger --}}
        <button
            type="button"
            class="admin-date-period-trigger"
            id="{{ $inputId }}_btn"
            aria-haspopup="listbox"
            aria-expanded="false"
            aria-controls="{{ $inputId }}_menu"
        >
            <span class="admin-date-period-label" id="{{ $inputId }}_label">{{ $selectedLabel }}</span>
            <span class="admin-date-period-arrow" aria-hidden="true">
                <i class="bi bi-chevron-down"></i>
            </span>
        </button>

        {{-- Menu --}}
        <ul
            class="admin-date-period-menu"
            id="{{ $inputId }}_menu"
            role="listbox"
            aria-label="{{ $labelText }}"
        >
            @foreach ($datePeriodOptions as $value => $label)
                <li
                    class="admin-date-period-option @if($selectedValue === $value) is-selected @endif"
                    role="option"
                    aria-selected="{{ $selectedValue === $value ? 'true' : 'false' }}"
                    data-value="{{ $value }}"
                    tabindex="0"
                >{{ $label }}</li>
            @endforeach
        </ul>
    </div>
</div>

@once
@push('styles')
<style>
.admin-date-period-wrap {
    position: relative;
    width: 100%;
}

/* Trigger button — matches other bootstrap/select2 filter styles */
.admin-date-period-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    height: 38px;
    padding: 0 12px;
    background-color: #ffffff;
    border: 1px solid #dee2e6; /* Match Bootstrap 5 standard border */
    border-radius: 8px;
    font-size: 0.875rem;
    color: var(--text-primary, #1e293b);
    cursor: pointer;
    gap: 8px;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
    white-space: nowrap;
    overflow: hidden;
}

.admin-date-period-trigger:hover,
.admin-date-period-trigger:focus {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    outline: none;
}

.admin-date-period-dropdown.is-open .admin-date-period-trigger {
    border-color: #6366f1;
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.admin-date-period-label {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    text-align: left;
}

.admin-date-period-arrow {
    flex-shrink: 0;
    color: #94a3b8; /* slate-400 */
    font-size: 0.75rem;
    line-height: 1;
    transition: transform 0.18s ease, color 0.18s ease;
    display: flex;
    align-items: center;
}

.admin-date-period-trigger:hover .admin-date-period-arrow,
.admin-date-period-trigger:focus .admin-date-period-arrow,
.admin-date-period-dropdown.is-open .admin-date-period-arrow {
    color: #6366f1;
}

.admin-date-period-dropdown.is-open .admin-date-period-arrow {
    transform: rotate(180deg);
}

/* Dropdown menu */
.admin-date-period-dropdown {
    position: relative;
    width: 100%;
}

.admin-date-period-menu {
    display: none;
    position: absolute;
    top: calc(100% + 4px); /* detached menu with a 4px gap */
    left: 0;
    right: 0;
    z-index: 9999;
    margin: 0;
    padding: 6px;
    list-style: none;
    background-color: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 8px; /* rounded corners all around */
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.06), 0 4px 6px -4px rgba(0, 0, 0, 0.04);
    max-height: 280px;
    overflow-y: auto;
    /* Scrollbar styling */
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}

.admin-date-period-menu::-webkit-scrollbar {
    width: 4px;
}

.admin-date-period-menu::-webkit-scrollbar-track {
    background: transparent;
}

.admin-date-period-menu::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.admin-date-period-dropdown.is-open .admin-date-period-menu {
    display: block;
}

/* Individual options */
.admin-date-period-option {
    padding: 8px 12px;
    font-size: 0.875rem;
    color: var(--text-secondary, #475569);
    border-radius: 6px;
    cursor: pointer;
    transition: background-color 0.12s ease, color 0.12s ease;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    outline: none;
    user-select: none;
    margin-bottom: 2px;
}

.admin-date-period-option:last-child {
    margin-bottom: 0;
}

/* Hover state */
.admin-date-period-option:hover,
.admin-date-period-option:focus {
    background-color: rgba(99, 102, 241, 0.08);
    color: #6366f1;
}

/* Selected state — blue highlight, white text */
.admin-date-period-option.is-selected {
    background-color: #6366f1;
    color: #ffffff;
    font-weight: 500;
}

/* Selected + hover — slightly darker */
.admin-date-period-option.is-selected:hover,
.admin-date-period-option.is-selected:focus {
    background-color: #4f46e5;
    color: #ffffff;
}

/* Responsive: full width on mobile */
@media (max-width: 575.98px) {
    .admin-date-period-trigger {
        height: 36px;
        font-size: 0.8125rem;
    }

    .admin-date-period-option {
        padding: 7px 10px;
        font-size: 0.8125rem;
    }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    function initDatePeriodFilter(wrap) {
        var filterName = wrap.dataset.periodFilterName;
        var dropdown   = wrap.querySelector('.admin-date-period-dropdown');
        var trigger    = wrap.querySelector('.admin-date-period-trigger');
        var labelEl    = wrap.querySelector('.admin-date-period-label');
        var menu       = wrap.querySelector('.admin-date-period-menu');
        var arrowIcon  = wrap.querySelector('.admin-date-period-arrow i');
        var hidden     = wrap.parentElement
            ? wrap.parentElement.querySelector('input[name="' + filterName + '"]')
            : null;

        if (!dropdown || !trigger || !menu || !hidden) return;

        function open() {
            dropdown.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            if (arrowIcon) {
                arrowIcon.className = 'bi bi-chevron-up';
            }
        }

        function close() {
            dropdown.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            if (arrowIcon) {
                arrowIcon.className = 'bi bi-chevron-down';
            }
        }

        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            if (dropdown.classList.contains('is-open')) {
                close();
            } else {
                // Close all other open period dropdowns first
                document.querySelectorAll('.admin-date-period-dropdown.is-open').forEach(function (other) {
                    if (other !== dropdown) {
                        other.classList.remove('is-open');
                        var otherTrigger = other.querySelector('.admin-date-period-trigger');
                        var otherArrow   = other.querySelector('.admin-date-period-arrow i');
                        if (otherTrigger) otherTrigger.setAttribute('aria-expanded', 'false');
                        if (otherArrow)   otherArrow.className = 'bi bi-chevron-down';
                    }
                });
                open();
            }
        });

        menu.addEventListener('click', function (e) {
            var option = e.target.closest('.admin-date-period-option');
            if (!option) return;

            var value = option.dataset.value;
            var label = option.textContent.trim();

            // Update hidden input
            hidden.value = value;

            // Update label text
            if (labelEl) labelEl.textContent = label;

            // Update selected state in menu
            menu.querySelectorAll('.admin-date-period-option').forEach(function (opt) {
                var isNowSelected = opt.dataset.value === value;
                opt.classList.toggle('is-selected', isNowSelected);
                opt.setAttribute('aria-selected', isNowSelected ? 'true' : 'false');
            });

            close();

            // Fire a native change event on the hidden input so admin-filters.js
            // can pick it up if needed
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // Close when clicking outside
        document.addEventListener('click', function () {
            close();
        });

        // Prevent close when clicking inside the dropdown itself
        dropdown.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        // Keyboard navigation
        trigger.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                trigger.click();
            } else if (e.key === 'Escape') {
                close();
            }
        });

        menu.addEventListener('keydown', function (e) {
            var options = Array.from(menu.querySelectorAll('.admin-date-period-option'));
            var focused = document.activeElement;
            var idx     = options.indexOf(focused);

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                var next = options[idx + 1] || options[0];
                if (next) next.focus();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                var prev = options[idx - 1] || options[options.length - 1];
                if (prev) prev.focus();
            } else if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (focused && focused.classList.contains('admin-date-period-option')) {
                    focused.click();
                }
            } else if (e.key === 'Escape') {
                close();
                trigger.focus();
            } else if (e.key === 'Tab') {
                close();
            }
        });
    }

    function initAll() {
        document.querySelectorAll('.admin-date-period-wrap').forEach(function (wrap) {
            if (!wrap.dataset.periodFilterInit) {
                wrap.dataset.periodFilterInit = 'true';
                initDatePeriodFilter(wrap);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
    document.addEventListener('livewire:load', initAll);
    document.addEventListener('livewire:navigated', initAll);
}());
</script>
@endpush
@endonce
