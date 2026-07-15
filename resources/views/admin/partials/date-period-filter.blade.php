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
    <label class="form-label small text-muted mb-1" for="{{ $inputId }}_btn">{{ $labelText }}</label>
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
