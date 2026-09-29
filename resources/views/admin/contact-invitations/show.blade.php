@extends('admin.layouts.app')

@section('title', 'Invitation Detail')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card">

    {{-- Header --}}
    <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">
                <i class="bi bi-whatsapp me-1 text-emerald-400"></i> Referral Invitation Detail
            </h2>
            <p class="text-xs t3 m-0 mt-0.5">Full detail record for invitation ID: <span class="font-mono text-[11px] t2">{{ $invitation->id }}</span></p>
        </div>
        <a href="{{ route('admin.contact-invitations.index') }}" class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
    </div>

    {{-- Status Banner --}}
    @php
        $isCompleted = $invitation->whatsapp_status === 'completed';
    @endphp
    <div class="mb-4 p-3 rounded-xl border {{ $isCompleted ? 'border-emerald-500/40 bg-emerald-500/10' : 'border-rose-500/40 bg-rose-500/10' }} d-flex align-items-center gap-3">
        <div class="text-2xl {{ $isCompleted ? 'text-emerald-400' : 'text-rose-400' }}">
            <i class="bi {{ $isCompleted ? 'bi-check-circle-fill' : 'bi-x-circle-fill' }}"></i>
        </div>
        <div>
            <div class="text-sm font-semibold {{ $isCompleted ? 'text-emerald-300' : 'text-rose-300' }}">
                WhatsApp Status: {{ $isCompleted ? 'Completed — Message Sent' : 'Not Completed' }}
            </div>
            <div class="text-xs {{ $isCompleted ? 'text-emerald-400/70' : 'text-rose-400/70' }} mt-0.5">
                {{ $isCompleted ? 'The referral invitation WhatsApp message was successfully dispatched.' : 'The WhatsApp message was not delivered or encountered an error.' }}
            </div>
        </div>
    </div>

    <div class="row g-4">

        {{-- Sender Info --}}
        <div class="col-12 col-md-6">
            <div class="p-4 rounded-xl border bs surface-2 h-100">
                <div class="text-[11px] text-indigo-400 font-semibold uppercase tracking-wider mb-3">
                    <i class="bi bi-person-badge me-1"></i> Sender Peer
                </div>
                @if($invitation->user)
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div>
                        <div class="font-semibold t1 text-sm">{{ $invitation->user->adminDisplayName() }}</div>
                        <div class="text-xs t3 mt-0.5">
                            <i class="bi bi-telephone me-1"></i>{{ $invitation->user->phone ?? '—' }}
                        </div>
                        <div class="text-xs t3 mt-0.5">
                            <i class="bi bi-envelope me-1"></i>{{ $invitation->user->email ?? '—' }}
                        </div>
                    </div>
                </div>
                <div class="mt-2">
                    <a href="{{ route('admin.users.show', $invitation->user_id) }}" class="btn btn-xs btn-outline-indigo px-2 py-1 text-xs rounded-lg">
                        <i class="bi bi-eye me-1"></i> View Peer Profile
                    </a>
                </div>
                @else
                <p class="text-xs t3">User not found (ID: {{ $invitation->user_id }})</p>
                @endif
            </div>
        </div>

        {{-- Invited Contact Info --}}
        <div class="col-12 col-md-6">
            <div class="p-4 rounded-xl border bs surface-2 h-100">
                <div class="text-[11px] text-indigo-400 font-semibold uppercase tracking-wider mb-3">
                    <i class="bi bi-person-lines-fill me-1"></i> Invited Contact
                </div>
                <div class="font-semibold t1 text-sm">{{ $invitation->contact_name ?? '—' }}</div>
                <div class="text-xs t2 mt-1"><i class="bi bi-phone me-1 text-muted"></i>{{ $invitation->contact_phone ?? '—' }}</div>
                <div class="text-xs t2 mt-0.5"><i class="bi bi-globe me-1 text-muted"></i>Normalized: {{ $invitation->mobile_normalized ?? '—' }}</div>
                @if($invitation->contact_email)
                <div class="text-xs t2 mt-0.5"><i class="bi bi-envelope me-1 text-muted"></i>{{ $invitation->contact_email }}</div>
                @endif
                @if($invitation->contactPost)
                <div class="text-xs t3 mt-2">
                    <i class="bi bi-link-45deg me-1"></i>Linked Contact Post ID:
                    <span class="font-mono">{{ $invitation->contact_post_id }}</span>
                </div>
                @endif
            </div>
        </div>

        {{-- Delivery & Timeline --}}
        <div class="col-12">
            <div class="p-4 rounded-xl border bs surface-2">
                <div class="text-[11px] text-indigo-400 font-semibold uppercase tracking-wider mb-3">
                    <i class="bi bi-clock-history me-1"></i> Delivery & Timeline
                </div>
                <div class="row g-3">
                    <div class="col-6 col-md-3">
                        <div class="text-[11px] t3 uppercase tracking-wide font-medium">Created At</div>
                        <div class="text-xs t1 font-semibold mt-0.5">{{ $invitation->created_at?->format('d M Y, h:i A') ?? '—' }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-[11px] t3 uppercase tracking-wide font-medium">WhatsApp Sent At</div>
                        <div class="text-xs t1 font-semibold mt-0.5">{{ $invitation->whatsapp_sent_at?->format('d M Y, h:i A') ?? '—' }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-[11px] t3 uppercase tracking-wide font-medium">Invitation Status</div>
                        <div class="mt-0.5">
                            <span class="badge text-[11px] px-2 py-1 rounded-md
                                {{ $invitation->status === 'sent' ? 'bg-emerald-500/20 text-emerald-400' : ($invitation->status === 'failed' ? 'bg-rose-500/20 text-rose-400' : 'bg-amber-500/20 text-amber-400') }}">
                                {{ ucfirst($invitation->status ?? 'pending') }}
                            </span>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-[11px] t3 uppercase tracking-wide font-medium">WhatsApp Provider ID</div>
                        <div class="text-xs t1 font-mono mt-0.5">{{ $invitation->whatsapp_provider_id ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Invitation Message --}}
        @if($invitation->invitation_message)
        <div class="col-12">
            <div class="p-4 rounded-xl border bs surface-2">
                <div class="text-[11px] text-indigo-400 font-semibold uppercase tracking-wider mb-2">
                    <i class="bi bi-chat-left-text me-1"></i> Invitation Message Sent
                </div>
                <div class="p-3 rounded-lg border bs surface text-xs t1 leading-relaxed whitespace-pre-wrap">{{ $invitation->invitation_message }}</div>
            </div>
        </div>
        @endif

        {{-- Error / Log --}}
        @if($invitation->error_message)
        <div class="col-12">
            <div class="p-4 rounded-xl border border-rose-500/30 bg-rose-500/10">
                <div class="text-[11px] text-rose-400 font-semibold uppercase tracking-wider mb-2">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Error / Delivery Log
                </div>
                <div class="p-3 rounded-lg border border-rose-500/20 text-xs text-rose-300 font-mono whitespace-pre-wrap">{{ $invitation->error_message }}</div>
            </div>
        </div>
        @endif

        {{-- WhatsApp Raw Response --}}
        @if($invitation->whatsapp_response_payload)
        <div class="col-12">
            <div class="p-4 rounded-xl border bs surface-2">
                <div class="text-[11px] text-indigo-400 font-semibold uppercase tracking-wider mb-2">
                    <i class="bi bi-braces me-1"></i> WhatsApp API Response Payload
                </div>
                <pre class="p-3 rounded-lg border bs surface text-[11px] t2 font-mono overflow-auto" style="max-height:200px;">{{ json_encode($invitation->whatsapp_response_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </div>
        @endif

    </div>

</div>
@endsection
