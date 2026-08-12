@extends('admin.layouts.app')

@section('title', 'Access Denied - 403')

@push('styles')
<style>
    .access-denied-wrapper {
        min-height: calc(78vh - 80px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem;
    }

    .access-card {
        background: #ffffff;
        border: 1px solid rgba(226, 232, 240, 0.9);
        border-radius: 24px;
        box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.08), 0 0 0 1px rgba(241, 245, 249, 0.8);
        max-width: 560px;
        width: 100%;
        overflow: hidden;
        position: relative;
        transition: all 0.3s ease;
    }

    .access-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #ef4444 0%, #f97316 50%, #6366f1 100%);
    }

    .icon-aura {
        width: 84px;
        height: 84px;
        border-radius: 50%;
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.12) 0%, rgba(249, 115, 22, 0.12) 100%);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1.25rem;
        position: relative;
        animation: pulseAura 3s infinite ease-in-out;
    }

    .icon-badge {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 10px 20px -5px rgba(239, 68, 68, 0.4);
        font-size: 1.6rem;
    }

    @keyframes pulseAura {
        0%, 100% {
            transform: scale(1);
            box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.2);
        }
        50% {
            transform: scale(1.05);
            box-shadow: 0 0 0 12px rgba(239, 68, 68, 0);
        }
    }

    .code-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(239, 68, 68, 0.08);
        color: #dc2626;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        padding: 5px 14px;
        border-radius: 50px;
        border: 1px solid rgba(239, 68, 68, 0.18);
        margin-bottom: 0.75rem;
    }

    .code-pill-dot {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background-color: #ef4444;
        display: inline-block;
    }

    .btn-action-primary {
        background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
        color: #ffffff;
        border: none;
        padding: 11px 24px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.925rem;
        box-shadow: 0 8px 16px -4px rgba(79, 70, 229, 0.35);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }

    .btn-action-primary:hover {
        background: linear-gradient(135deg, #4338ca 0%, #4f46e5 100%);
        color: #ffffff;
        transform: translateY(-2px);
        box-shadow: 0 12px 20px -4px rgba(79, 70, 229, 0.45);
    }

    .btn-action-secondary {
        background: #ffffff;
        color: #334155;
        border: 1.5px solid #cbd5e1;
        padding: 11px 22px;
        border-radius: 12px;
        font-weight: 600;
        font-size: 0.925rem;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        cursor: pointer;
    }

    .btn-action-secondary:hover {
        background: #f8fafc;
        color: #0f172a;
        border-color: #94a3b8;
        transform: translateY(-2px);
        box-shadow: 0 6px 14px -3px rgba(0, 0, 0, 0.06);
    }

    .info-callout {
        background: rgba(241, 245, 249, 0.7);
        border-radius: 14px;
        border: 1px dashed #cbd5e1;
        padding: 12px 16px;
        margin-top: 1.75rem;
        font-size: 0.85rem;
        color: #64748b;
    }
</style>
@endpush

@section('content')
<div class="access-denied-wrapper">
    <div class="access-card text-center p-4 p-sm-5">
        <div class="icon-aura">
            <div class="icon-badge">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
        </div>

        <div>
            <span class="code-pill">
                <span class="code-pill-dot"></span> HTTP 403 • ACCESS RESTRICTED
            </span>
        </div>

        <h2 class="fw-bold text-dark mb-2" style="letter-spacing: -0.02em;">Access Restricted</h2>
        <p class="text-secondary fs-6 mb-4 px-sm-2" style="max-width: 440px; margin-left: auto; margin-right: auto;">
            {{ $message ?? 'You do not have permission to access or modify this page with your current role.' }}
        </p>

        <div class="d-flex flex-wrap align-items-center justify-content-center gap-3">
            <button type="button" onclick="window.history.back()" class="btn-action-secondary">
                <i class="bi bi-arrow-left"></i> Go Back
            </button>
            <a href="{{ route('admin.home') }}" class="btn-action-primary">
                <i class="bi bi-grid-fill"></i> Return to Dashboard
            </a>
        </div>

        <div class="info-callout d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-info-circle text-primary"></i>
            <span>Need access? Please contact your <strong>System Administrator</strong> to request permissions.</span>
        </div>
    </div>
</div>
@endsection
