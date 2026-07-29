@php
    $label = $titleLabel ?? 'Search created by';
    $filters = $filters ?? [];
    $q = $filters['q'] ?? request('q', '');
    $from = $filters['from'] ?? request('from', '');
    $to = $filters['to'] ?? request('to', '');
    $circleId = (string) ($filters['circle_id'] ?? request('circle_id', ''));
    $formId = $formId ?? null;
    $renderFormTag = $renderFormTag ?? true;
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-body py-2 px-3">
        @if ($renderFormTag)
            <form method="GET" action="{{ $actionUrl }}" class="row g-2 align-items-center admin-filter-form" @if($formId) id="{{ $formId }}" @endif>
        @else
            <div class="row g-2 align-items-center">
        @endif
            <div class="col-md-3">
                <input id="activityFilterQuery" type="text" name="q" value="{{ $q }}" class="form-control form-control-sm text-xs" placeholder="Name, company, or city" title="{{ $label }}" @if($formId) form="{{ $formId }}" @endif>
            </div>
            <div class="col-md-3">
                <select id="activityFilterCircle" name="circle_id" class="form-select form-select-sm text-xs js-searchable-select" @if($formId) form="{{ $formId }}" @endif>
                    <option value="">All Circles</option>
                    @foreach (($circles ?? collect()) as $circle)
                        <option value="{{ $circle->id }}" @selected($circleId !== '' && $circleId === (string) $circle->id)>{{ $circle->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input id="activityFilterFrom" type="date" name="from" value="{{ $from }}" class="form-control form-control-sm text-xs" placeholder="From" title="From Date" @if($formId) form="{{ $formId }}" @endif>
            </div>
            <div class="col-md-2">
                <input id="activityFilterTo" type="date" name="to" value="{{ $to }}" class="form-control form-control-sm text-xs" placeholder="To" title="To Date" @if($formId) form="{{ $formId }}" @endif>
            </div>
            <div class="col-md-2 d-flex gap-1.5 justify-content-end">
                <a href="{{ $resetUrl }}" class="btn btn-sm btn-outline-secondary px-2.5 py-1 text-xs">Clear</a>
                @if (!empty($showExport) && !empty($exportUrl))
                    <a href="{{ $exportUrl }}" class="btn btn-sm btn-outline-primary px-2.5 py-1 text-xs">Export</a>
                @endif
            </div>
        @if ($renderFormTag)
            </form>
        @else
            </div>
        @endif
    </div>
</div>
