@extends('admin.layouts.app')

@section('title', 'Circle Peer Referral Details')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.peer-referrals.index') }}" class="btn btn-link text-decoration-none p-0 d-inline-flex align-items-center fw-semibold text-primary" style="font-size: 0.85rem;">
            <i class="bi bi-arrow-left me-1"></i> Back to Circle Referral List
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success border-0 shadow-sm mb-4 alert-dismissible fade show" role="alert" style="font-size: 0.85rem; border-radius: 8px;">
            <i class="bi bi-check-circle-fill me-2 text-success"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        <!-- Main Details (Left/Center Column) -->
        <div class="col-lg-8">
            <!-- Referral Info Card -->
            <div class="card mb-4 border-0 shadow-sm rounded-3">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                        <div>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 mb-2 text-uppercase tracking-wider font-semibold" style="font-size: 0.65rem; border-radius: 4px;">
                                <i class="bi bi-person-badge-fill me-1"></i> Peer Referral Details
                            </span>
                            <h2 class="h4 font-display font-bold text-slate-800 my-1">{{ $peerReferral->referred_name }}</h2>
                            
                            @php
                                $statusBadges = [
                                    'pending' => 'bg-warning-subtle text-warning border-warning',
                                    'contacted' => 'bg-info-subtle text-info border-info',
                                    'accepted' => 'bg-success-subtle text-success border-success',
                                    'rejected' => 'bg-danger-subtle text-danger border-danger',
                                    'converted' => 'bg-purple-subtle text-purple border-purple',
                                ];
                                $badgeStyle = $statusBadges[$peerReferral->status] ?? 'bg-secondary-subtle text-secondary border-secondary';
                            @endphp
                            <span class="badge border {{ $badgeStyle }} px-3 py-1.5 rounded-pill font-bold" style="font-size: 0.72rem;">
                                <i class="bi bi-circle-fill me-1" style="font-size: 6px; vertical-align: middle;"></i> {{ ucfirst($peerReferral->status) }}
                            </span>
                        </div>
                        <div class="text-sm-end">
                            <div class="text-uppercase tracking-wider text-muted font-semibold" style="font-size: 0.65rem;">Submitted On</div>
                            <div class="text-slate-700 fw-medium mt-1" style="font-size: 0.85rem;"><i class="bi bi-calendar3 me-1 text-primary"></i>{{ $peerReferral->created_at?->format('d M Y, h:i A') }}</div>
                        </div>
                    </div>

                    <hr class="my-4" style="border-color: #f1f5f9;">

                    <!-- Information Grid -->
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-light rounded-3 p-3 text-primary d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                    <i class="bi bi-telephone fs-5"></i>
                                </div>
                                <div>
                                    <div class="text-uppercase tracking-wider text-muted font-semibold mb-0.5" style="font-size: 0.65rem;">Phone Number</div>
                                    <a href="tel:{{ $peerReferral->referred_phone }}" class="text-slate-800 text-decoration-none fw-bold mb-0" style="font-size: 0.85rem;">{{ $peerReferral->referred_phone }}</a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-light rounded-3 p-3 text-primary d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                    <i class="bi bi-envelope fs-5"></i>
                                </div>
                                <div>
                                    <div class="text-uppercase tracking-wider text-muted font-semibold mb-0.5" style="font-size: 0.65rem;">Email Address</div>
                                    @if($peerReferral->referred_email)
                                        <a href="mailto:{{ $peerReferral->referred_email }}" class="text-slate-800 text-decoration-none fw-bold mb-0" style="font-size: 0.85rem;">{{ $peerReferral->referred_email }}</a>
                                    @else
                                        <span class="text-muted mb-0" style="font-size: 0.85rem;">—</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-light rounded-3 p-3 text-primary d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                    <i class="bi bi-building fs-5"></i>
                                </div>
                                <div>
                                    <div class="text-uppercase tracking-wider text-muted font-semibold mb-0.5" style="font-size: 0.65rem;">Company / Business</div>
                                    <span class="text-slate-800 fw-bold mb-0" style="font-size: 0.85rem;">{{ $peerReferral->referred_company_name ?? '—' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-light rounded-3 p-3 text-primary d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                    <i class="bi bi-briefcase fs-5"></i>
                                </div>
                                <div>
                                    <div class="text-uppercase tracking-wider text-muted font-semibold mb-0.5" style="font-size: 0.65rem;">Designation / Role</div>
                                    <span class="text-slate-800 fw-bold mb-0" style="font-size: 0.85rem;">{{ $peerReferral->referred_designation ?? '—' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($peerReferral->message)
                        <div class="mt-4 pt-4 border-top" style="border-color: #f1f5f9;">
                            <div class="text-uppercase tracking-wider text-muted font-semibold mb-2" style="font-size: 0.65rem;">
                                <i class="bi bi-chat-left-quote-fill me-1 text-primary"></i> Referral Note / Message
                            </div>
                            <div class="p-3 bg-light rounded-3 border-start border-primary border-3 text-slate-700 italic" style="font-size: 0.85rem;">
                                "{{ $peerReferral->message }}"
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Referral Context Details -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-4">
                    <h3 class="h6 font-display font-bold mb-3 text-slate-800">
                        <i class="bi bi-info-circle-fill me-2 text-primary"></i> Referral Context Details
                    </h3>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="p-3 rounded-3 bg-light border border-light h-100">
                                <div class="text-uppercase tracking-wider text-muted font-semibold mb-1" style="font-size: 0.6rem;">Parent Circle</div>
                                <span class="fw-bold text-slate-800" style="font-size: 0.8rem;">{{ $peerReferral->mainCircle?->name ?? '—' }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded-3 bg-light border border-light h-100">
                                <div class="text-uppercase tracking-wider text-muted font-semibold mb-1" style="font-size: 0.6rem;">Specific Circle</div>
                                <span class="fw-bold text-slate-800" style="font-size: 0.8rem;">{{ $peerReferral->circle?->name ?? 'Main Circle Referral' }}</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded-3 bg-light border border-light h-100">
                                <div class="text-uppercase tracking-wider text-muted font-semibold mb-1" style="font-size: 0.6rem;">Open Category</div>
                                <span class="fw-bold text-slate-800" style="font-size: 0.8rem;">{{ $peerReferral->category?->name ?? '—' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar / Action Panel (Right Column) -->
        <div class="col-lg-4">
            <!-- Referrer Details Card -->
            <div class="card mb-4 border-0 shadow-sm rounded-3">
                <div class="card-body p-4">
                    <h3 class="h6 font-display font-bold mb-4 text-slate-800">
                        <i class="bi bi-people-fill me-2 text-primary"></i> Referrer Details
                    </h3>
                    @if($peerReferral->referrer)
                        @php
                            $refName = $peerReferral->referrer->display_name ?: trim(($peerReferral->referrer->first_name ?? '') . ' ' . ($peerReferral->referrer->last_name ?? ''));
                            $words = explode(' ', trim($refName));
                            $initials = '';
                            foreach ($words as $w) {
                                if (! empty($w)) $initials .= strtoupper(substr($w, 0, 1));
                            }
                            $initials = substr($initials, 0, 2) ?: 'P';
                            $colors = ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#3b82f6'];
                            $hash = crc32($refName);
                            $avatarBg = $colors[abs($hash) % count($colors)];
                        @endphp
                        
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold shadow-sm" style="background-color: {{ $avatarBg }}; width: 44px; height: 44px; font-size: 1rem;">
                                {{ $initials }}
                            </div>
                            <div class="overflow-hidden">
                                <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $peerReferral->referrer->id }}', event);" class="fw-bold text-primary text-decoration-none block" style="font-size: 0.85rem;">
                                    {{ $refName }}
                                </a>
                                <div class="text-muted text-truncate" style="font-size: 0.75rem;">{{ $peerReferral->referrer->email }}</div>
                                <div class="text-muted font-monospace" style="font-size: 0.75rem;">{{ $peerReferral->referrer->phone ?? 'No phone' }}</div>
                            </div>
                        </div>

                        <hr class="my-3" style="border-color: #f1f5f9;">

                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-geo-alt-fill text-primary fs-5 mt-0.5"></i>
                                <div>
                                    <span class="text-uppercase tracking-wider text-muted font-semibold d-block" style="font-size: 0.6rem;">City</span>
                                    <span class="fw-bold text-slate-800" style="font-size: 0.8rem;">{{ $peerReferral->referrer->cityRelation?->name ?? $peerReferral->referrer->city ?? '—' }}</span>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-circle-half text-primary fs-5 mt-0.5"></i>
                                <div>
                                    <span class="text-uppercase tracking-wider text-muted font-semibold d-block" style="font-size: 0.6rem;">Active Circle</span>
                                    <span class="fw-bold text-slate-800" style="font-size: 0.8rem;">{{ $peerReferral->referrer->activeCircle?->name ?? '—' }}</span>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-start gap-2">
                                <i class="bi bi-tag-fill text-primary fs-5 mt-0.5"></i>
                                <div>
                                    <span class="text-uppercase tracking-wider text-muted font-semibold d-block" style="font-size: 0.6rem;">Business Category</span>
                                    <span class="fw-bold text-slate-800" style="font-size: 0.8rem;">{{ $peerReferral->referrer->mainBusinessCategory?->name ?? $peerReferral->referrer->business_sub_category ?? '—' }}</span>
                                </div>
                            </div>
                        </div>
                    @else
                        <span class="text-muted d-block py-3 text-center" style="font-size: 0.8rem;">
                            <i class="bi bi-person-x-fill me-1"></i> No referrer record found.
                        </span>
                    @endif
                </div>
            </div>

            <!-- Action Panel: Status Update -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-4">
                    <h3 class="h6 font-display font-bold mb-4 text-slate-800">
                        <i class="bi bi-pencil-square me-2 text-primary"></i> Update Referral Status
                    </h3>
                    <form method="POST" action="{{ route('admin.peer-referrals.status-update', $peerReferral->id) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label text-uppercase tracking-wider text-muted font-semibold" style="font-size: 0.65rem;">Referral Status</label>
                            <select name="status" class="form-select border-slate-200 text-slate-800" style="font-size: 0.85rem; padding: 8px 12px; border-radius: 6px;">
                                @foreach(['pending', 'contacted', 'accepted', 'rejected', 'converted'] as $opt)
                                    <option value="{{ $opt }}" {{ $peerReferral->status === $opt ? 'selected' : '' }}>{{ ucfirst($opt) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2" style="font-size: 0.85rem; border-radius: 6px;">
                            <i class="bi bi-arrow-repeat"></i> Update Status
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
