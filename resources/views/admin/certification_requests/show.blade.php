@extends('admin.layouts.app')

@section('title', $resource['title'].' Details')

@push('styles')
    <style>
        .answer-card {
            border: 1px solid #e5e7eb;
            border-radius: .75rem;
            padding: .9rem 1rem;
            min-height: 100%;
            background: #f8fafc;
        }

        .answer-card-correct {
            background: #ecfdf3;
            border-color: #bbf7d0;
            color: #14532d;
        }

        .answer-card-incorrect {
            background: #fef2f2;
            border-color: #fecaca;
            color: #7f1d1d;
        }

        .answer-status-badge {
            border-radius: 999px;
            font-size: .72rem;
            font-weight: 700;
            padding: .25rem .6rem;
        }

        .answer-status-correct {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .answer-status-incorrect {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .answer-correct-helper {
            color: #991b1b;
            font-size: .82rem;
            margin-top: .55rem;
        }

        .certification-confirm-modal .modal-dialog {
            max-width: 480px;
            width: calc(100% - 2rem);
            margin-left: auto;
            margin-right: auto;
        }

        .certification-confirm-modal .modal-content {
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 1.5rem 4rem rgba(15, 23, 42, 0.28);
            overflow: hidden;
        }

        .certification-confirm-modal .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #eef2f7;
            align-items: flex-start;
        }

        .certification-confirm-modal .modal-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.35;
        }

        .certification-confirm-modal .btn-close {
            margin: 0;
            padding: .5rem;
            border-radius: 999px;
        }

        .certification-confirm-modal .modal-body {
            padding: 1.5rem;
            color: #475569;
            line-height: 1.55;
        }

        .certification-confirm-modal .modal-footer {
            padding: 1rem 1.5rem 1.25rem;
            border-top: 0;
            background: #f8fafc;
            gap: .5rem;
        }

        .certification-confirm-modal .modal-footer .btn {
            min-height: 40px;
            border-radius: .55rem;
            padding-left: 1.1rem;
            padding-right: 1.1rem;
            font-weight: 600;
        }

        .modal {
            z-index: 1055;
        }

        .modal-backdrop {
            position: fixed;
            inset: 0;
            width: 100vw;
            height: 100vh;
            background-color: #0f172a;
            z-index: 1050;
        }

        .modal-backdrop.show {
            opacity: .58;
        }
    </style>
@endpush

@section('content')
@php
    $statusClasses = [
        'new' => 'warning text-dark',
        'approved' => 'success',
        'rejected' => 'danger',
    ];

    $statusLabels = [
        'new' => 'Pending / New',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    $formatLabel = static fn (string $column) => \Illuminate\Support\Str::title(str_replace('_', ' ', $column));
    $formatValue = static function ($value, string $column): string {
        if ($value === null || $value === '') {
            return '—';
        }

        if (in_array($column, ['created_at', 'updated_at'], true)) {
            return \Illuminate\Support\Carbon::parse($value)->format('d M Y h:i A');
        }

        if ($column === 'percentage') {
            return rtrim(rtrim(number_format((float) $value, 2), '0'), '.').'%';
        }

        return (string) $value;
    };

    $status = (string) $certificationRequest->status;
    $statusClass = $statusClasses[$status] ?? 'secondary';
    $statusLabel = $statusLabels[$status] ?? ucfirst($status ?: 'Unknown');
@endphp

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start mb-3 gap-2">
        <div>
            <h1 class="h4 mb-1">{{ $resource['title'] }} Details</h1>
            <p class="text-muted mb-0">{{ $certificationRequest->full_name }} · {{ $certificationRequest->business_name }}</p>
        </div>
        <a href="{{ route($resource['index_route']) }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <div class="small text-muted">Current Status</div>
                <span class="badge bg-{{ $statusClass }}">{{ $statusLabel }}</span>
            </div>
            @if($status === 'new')
                <div class="btn-group">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveCertificationRequest">Approve</button>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectCertificationRequest">Reject</button>
                </div>
            @endif
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="row g-3">
                @foreach($resource['detail_columns'] as $column)
                    @continue(in_array($column, $certificationRequest::QUIZ_FIELDS, true))
                    @php $value = data_get($certificationRequest, $column); @endphp
                    <div class="col-md-6">
                        <div class="small text-muted mb-1">{{ $formatLabel($column) }}</div>
                        @if($column === 'status')
                            <span class="badge bg-{{ $statusClass }}">{{ $statusLabel }}</span>
                        @elseif($column === 'notes')
                            <div class="border rounded p-2 bg-light" style="white-space: pre-wrap;">{{ $formatValue($value, $column) }}</div>
                        @else
                            <div>{{ $formatValue($value, $column) }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    @if(! empty($answerEvaluations))
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white">
                <h2 class="h6 mb-0">Submitted Answer Review</h2>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach($answerEvaluations as $answer)
                        @php
                            $isCorrect = $answer['is_correct'];
                            $answerCardClass = $isCorrect === true
                                ? 'answer-card-correct'
                                : ($isCorrect === false ? 'answer-card-incorrect' : '');
                        @endphp
                        <div class="col-md-6">
                            <div class="answer-card {{ $answerCardClass }}">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                    <div class="small fw-semibold">{{ $answer['question_label'] }}</div>
                                    @if($isCorrect === true)
                                        <span class="answer-status-badge answer-status-correct">Correct</span>
                                    @elseif($isCorrect === false)
                                        <span class="answer-status-badge answer-status-incorrect">Incorrect</span>
                                    @endif
                                </div>
                                <div style="white-space: pre-wrap;">{{ $formatValue($answer['submitted_answer'], $answer['field']) }}</div>
                                @if($isCorrect === false && $answer['correct_answer'])
                                    <div class="answer-correct-helper">
                                        <span class="fw-semibold">Correct answer:</span> {{ $answer['correct_answer'] }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>

@if($status === 'new')
    <div class="modal fade certification-confirm-modal" id="approveCertificationRequest" tabindex="-1" aria-labelledby="approveCertificationRequestLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route($resource['approve_route'], $certificationRequest->id) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="approveCertificationRequestLabel">Approve {{ $resource['singular_title'] }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to approve this certification request?</p>
                </div>
                <div class="modal-footer justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade certification-confirm-modal" id="rejectCertificationRequest" tabindex="-1" aria-labelledby="rejectCertificationRequestLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route($resource['reject_route'], $certificationRequest->id) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="rejectCertificationRequestLabel">Reject {{ $resource['singular_title'] }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Are you sure you want to reject this certification request?</p>
                </div>
                <div class="modal-footer justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
@endif
@endsection
