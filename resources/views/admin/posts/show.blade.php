@extends('admin.layouts.app')

@section('title', 'Post Details')

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    @php
        $owner = $post->user;
        $ownerName = $owner?->display_name ?: trim(($owner?->first_name ?? '') . ' ' . ($owner?->last_name ?? ''));
        $celebratedUser = $post->source_id ? \App\Models\User::find($post->source_id) : null;
        $celebratedName = $celebratedUser?->display_name ?: trim(($celebratedUser?->first_name ?? '') . ' ' . ($celebratedUser?->last_name ?? ''));
        $targetName = ! empty($deactivationPreview['user_name']) && $deactivationPreview['user_name'] !== 'Member'
            ? $deactivationPreview['user_name']
            : ($celebratedName ?: ($ownerName ?: 'Member'));
        $isActive = $post->deleted_at === null;
        $mediaItems = is_array($post->media) ? $post->media : [];
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-1 fw-bold">Post Details</h2>
            <div class="text-muted small">Post ID: {{ $post->id }}</div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <a href="{{ route('admin.posts.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Post Info</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Created At</dt>
                        <dd class="col-sm-8">{{ $post->created_at?->format('Y-m-d H:i') }}</dd>
                        <dt class="col-sm-4">Owner</dt>
                        <dd class="col-sm-8">
                            {{ $ownerName !== '' ? $ownerName : 'Unknown' }}
                            @if ($celebratedName && $celebratedName !== $ownerName)
                                <div class="small text-muted mt-0.5">Celebrated Peer: <span class="fw-semibold text-primary">{{ $celebratedName }}</span></div>
                            @endif
                        </dd>
                        <dt class="col-sm-4">Circle</dt>
                        <dd class="col-sm-8">{{ $post->circle?->name ?? '—' }}</dd>
                        <dt class="col-sm-4">Visibility</dt>
                        <dd class="col-sm-8">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-sky-50 text-sky-700 border-sky-200">
                                <i class="bi bi-globe2 text-sky-500 text-[11px]"></i>
                                <span>{{ ucfirst($post->visibility ?: 'Public') }}</span>
                            </span>
                        </dd>
                        <dt class="col-sm-4">Moderation Status</dt>
                        <dd class="col-sm-8">
                            @php $modStatus = strtolower((string)$post->moderation_status); @endphp
                            @if($modStatus === 'approved')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-emerald-50 text-emerald-700 border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Approved
                                </span>
                            @elseif($modStatus === 'pending')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-amber-50 text-amber-700 border-amber-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Pending
                                </span>
                            @elseif($modStatus === 'rejected')
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-rose-50 text-rose-700 border-rose-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Rejected
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-slate-100 text-slate-700 border-slate-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> {{ ucfirst($post->moderation_status ?: '—') }}
                                </span>
                            @endif
                        </dd>
                        <dt class="col-sm-4">Active?</dt>
                        <dd class="col-sm-8">
                            @if ($isActive)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-emerald-50 text-emerald-700 border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Yes
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border bg-rose-50 text-rose-700 border-rose-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Deactivated
                                </span>
                            @endif
                        </dd>
                        @if (!empty($post->deactivation_remarks))
                            <dt class="col-sm-4 text-danger">Deactivation Reason</dt>
                            <dd class="col-sm-8">
                                <div class="p-2.5 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 text-xs">
                                    <div class="fw-semibold mb-1"><i class="bi bi-info-circle-fill me-1"></i> Admin Remarks:</div>
                                    <div>{{ $post->deactivation_remarks }}</div>
                                    @if ($post->deactivated_at)
                                        <div class="text-muted mt-1.5 text-[11px]">
                                            Deactivated on: {{ $post->deactivated_at->format('M d, Y H:i:s') }}
                                        </div>
                                    @endif
                                </div>
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Content Preview</div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-muted small">Content</div>
                        <div class="border rounded p-2 bg-light">{{ $post->content_text ?: '—' }}</div>
                    </div>
                    @if ($mediaItems)
                        <div>
                            <div class="text-muted small mb-1">Media</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($mediaItems as $media)
                                    @php
                                        $mediaUrl = data_get($media, 'url');
                                        if ($mediaUrl && ! app()->isProduction() && str_contains($mediaUrl, 'peersunity.com/storage/')) {
                                            $mediaUrl = asset('storage/' . ltrim(explode('peersunity.com/storage/', $mediaUrl)[1] ?? '', '/'));
                                        }
                                        $fallbackBadge = asset('images/life_impact_badges/Legacy Maker.png');
                                    @endphp
                                    @if ($mediaUrl)
                                        <img src="{{ $mediaUrl }}" alt="Post media" class="img-thumbnail" style="width: 140px; height: auto; max-height: 180px; object-fit: contain;" onerror="this.onerror=null; this.src='{{ $fallbackBadge }}';">
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @elseif ($post->post_type === 'life_impact_recognition' || $post->source_type === 'life_impact')
                        <div>
                            <div class="text-muted small mb-1">Media</div>
                            <img src="{{ asset('images/life_impact_badges/Legacy Maker.png') }}" alt="Post media" class="img-thumbnail" style="width: 140px; height: auto;">
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-header bg-white fw-semibold">Actions</div>
        <div class="card-body d-flex flex-wrap gap-3 align-items-center">
            @if ($isActive)
                <button type="button" class="btn btn-outline-danger d-inline-flex align-items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#deactivateShowModal">
                    <i class="bi bi-shield-slash"></i> Deactivate Post
                </button>
            @else
                <span class="badge bg-secondary p-2">This post has been deactivated.</span>
                <form method="POST" action="{{ route('admin.posts.restore', $post->id) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('Are you sure you want to activate this post?')">
                        <i class="bi bi-arrow-counterclockwise"></i> Activate Post
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if ($isActive)
        @php
            $prevCoinsToRev = $deactivationPreview['coins_to_reverse'] ?? 0;
            $prevImpactToRev = $deactivationPreview['impact_to_reverse'] ?? 0;
            $prevCurrCoins = $deactivationPreview['current_coins'] ?? (int) ($post->user?->coins_balance ?? 0);
            $prevCurrImpact = $deactivationPreview['current_impact'] ?? (int) ($post->user?->life_impacted_count ?? 0);
        @endphp
        {{-- Deactivate Modal on Show Page --}}
        <div class="modal fade" id="deactivateShowModal" tabindex="-1" aria-labelledby="deactivateShowModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <form method="POST" action="{{ route('admin.posts.deactivate', $post) }}">
                        @csrf
                        <input type="hidden" name="has_options" value="1">
                        <div class="modal-header bg-white border-bottom py-3 px-4">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-3 bg-danger-subtle text-danger p-2 d-flex align-items-center justify-center">
                                    <i class="bi bi-shield-slash-fill fs-5"></i>
                                </div>
                                <div>
                                    <h5 class="modal-title h6 fw-bold text-dark mb-0" id="deactivateShowModalLabel">Deactivate Post</h5>
                                    <span class="text-muted small">Record reason, reverse awarded rewards & notify member</span>
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="p-3 bg-light rounded-3 border mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="fw-bold text-dark small">{{ $targetName }}</span>
                                    <span class="badge bg-white text-muted border small">{{ $post->created_at?->format('M d, Y') }}</span>
                                </div>
                                <div class="small text-secondary fst-italic border-start border-3 border-primary ps-2 py-1">
                                    "{{ Str::limit($post->content_text ?: 'Post content', 120) }}"
                                </div>
                            </div>

                            {{-- Option Cards --}}
                            <div class="row g-2 mb-3 text-center">
                                <div class="col-4">
                                    <label id="show_card_opt_coins" for="show_opt_reverse_coins" class="p-2.5 rounded-3 border w-100 d-block cursor-pointer select-none bg-warning-subtle border-warning text-warning-emphasis position-relative transition">
                                        <input type="checkbox" name="reverse_coins" id="show_opt_reverse_coins" value="1" checked class="d-none">
                                        <span id="show_check_coins" class="position-absolute top-0 end-0 translate-middle badge rounded-pill bg-warning text-dark"><i class="bi bi-check"></i></span>
                                        <div class="small fw-bold"><i class="bi bi-coin text-warning"></i> Reverse Coins</div>
                                        <div class="mt-1.5 d-flex align-items-center justify-content-center gap-1">
                                            <span class="small fw-bold text-warning-emphasis" style="font-size: 11px;">Deduct:</span>
                                            <input type="number" name="coins_amount" id="show_coins_amount_input" min="0" step="1"
                                                class="form-control form-control-sm text-center fw-bold p-0 text-dark bg-white"
                                                style="width: 65px; height: 26px; font-size: 12px;"
                                                value="{{ $prevCoinsToRev }}" onclick="event.stopPropagation();">
                                            <span class="small fw-bold text-warning-emphasis" style="font-size: 10px;">Coins</span>
                                        </div>
                                        <div id="show_preview_coins" class="text-muted mt-1" style="font-size: 10px;">
                                            Bal: {{ number_format($prevCurrCoins) }} → {{ number_format(max(0, $prevCurrCoins - $prevCoinsToRev)) }}
                                        </div>
                                    </label>
                                </div>
                                <div class="col-4">
                                    <label id="show_card_opt_impact" for="show_opt_reverse_impact" class="p-2.5 rounded-3 border w-100 d-block cursor-pointer select-none bg-info-subtle border-info text-info-emphasis position-relative transition">
                                        <input type="checkbox" name="reverse_impact" id="show_opt_reverse_impact" value="1" checked class="d-none">
                                        <span id="show_check_impact" class="position-absolute top-0 end-0 translate-middle badge rounded-pill bg-info text-dark"><i class="bi bi-check"></i></span>
                                        <div class="small fw-bold"><i class="bi bi-stars text-info"></i> Reverse Impact</div>
                                        <div class="mt-1.5 d-flex align-items-center justify-content-center gap-1">
                                            <span class="small fw-bold text-info-emphasis" style="font-size: 11px;">Deduct:</span>
                                            <input type="number" name="impact_amount" id="show_impact_amount_input" min="0" step="1"
                                                class="form-control form-control-sm text-center fw-bold p-0 text-dark bg-white"
                                                style="width: 70px; height: 26px; font-size: 12px;"
                                                value="{{ $prevImpactToRev }}" onclick="event.stopPropagation();">
                                            <span class="small fw-bold text-info-emphasis" style="font-size: 10px;">Impact</span>
                                        </div>
                                        <div id="show_preview_impact" class="text-muted mt-1" style="font-size: 10px;">
                                            Score: {{ number_format($prevCurrImpact) }} → {{ number_format(max(0, $prevCurrImpact - $prevImpactToRev)) }}
                                        </div>
                                    </label>
                                </div>
                                <div class="col-4">
                                    <label id="show_card_opt_notify" for="show_opt_notify_user" class="p-2.5 rounded-3 border w-100 d-block cursor-pointer select-none bg-primary-subtle border-primary text-primary-emphasis position-relative transition">
                                        <input type="checkbox" name="notify_user" id="show_opt_notify_user" value="1" checked class="d-none">
                                        <span id="show_check_notify" class="position-absolute top-0 end-0 translate-middle badge rounded-pill bg-primary text-white"><i class="bi bi-check"></i></span>
                                        <div class="small fw-bold"><i class="bi bi-bell-fill text-primary"></i> Notify User</div>
                                        <div id="show_badge_notify" class="badge bg-primary text-white fw-bold mt-1.5 d-block">Push & In-App</div>
                                        <div id="show_preview_notify" class="text-muted mt-1" style="font-size: 10px;">Alerts peer</div>
                                    </label>
                                </div>
                            </div>

                            {{-- Summary Box --}}
                            <div id="show_summary_box" class="p-2.5 bg-warning-subtle text-warning-emphasis border border-warning rounded-3 small mb-3 d-flex align-items-center gap-2">
                                <i class="bi bi-info-circle-fill text-warning fs-6"></i>
                                <div id="show_summary_text" class="flex-grow-1">
                                    Will deactivate post, reverse awarded rewards, and notify member.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="show_remarks" class="form-label small fw-bold text-dark">
                                    Remarks / Reason for Deactivation <span class="text-danger">*</span>
                                </label>
                                <textarea id="show_remarks" name="remarks" class="form-control" rows="3" required minlength="3" maxlength="1000" placeholder="Write a clear reason for the member (e.g. duplicate post or image)..."></textarea>
                            </div>

                            <div>
                                <span class="small text-muted fw-bold text-uppercase d-block mb-1.5" style="font-size: 11px;">
                                    <i class="bi bi-lightning-charge-fill text-warning me-1"></i> Quick Suggestions:
                                </span>
                                <div class="d-flex flex-wrap gap-1.5">
                                    <button type="button" class="btn btn-sm btn-light border rounded-pill py-1 px-2.5 text-xs" onclick="document.getElementById('show_remarks').value='Duplicate post or image uploaded.'">Duplicate post or image</button>
                                    <button type="button" class="btn btn-sm btn-light border rounded-pill py-1 px-2.5 text-xs" onclick="document.getElementById('show_remarks').value='Content does not comply with community guidelines.'">Community guidelines</button>
                                    <button type="button" class="btn btn-sm btn-light border rounded-pill py-1 px-2.5 text-xs" onclick="document.getElementById('show_remarks').value='Invalid activity submission.'">Invalid activity submission</button>
                                    <button type="button" class="btn btn-sm btn-light border rounded-pill py-1 px-2.5 text-xs" onclick="document.getElementById('show_remarks').value='Incorrect or misleading activity information.'">Misleading info</button>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light px-4 py-3 d-flex justify-content-between">
                            <span class="text-muted small"><i class="bi bi-shield-check text-success me-1"></i> Reversible anytime</span>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-light border btn-sm px-3 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger btn-sm px-4 fw-bold shadow-sm">
                                    <i class="bi bi-shield-slash-fill me-1"></i> Confirm Deactivation
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const coinsCheck = document.getElementById('show_opt_reverse_coins');
            const impactCheck = document.getElementById('show_opt_reverse_impact');
            const notifyCheck = document.getElementById('show_opt_notify_user');

            const coinsCard = document.getElementById('show_card_opt_coins');
            const impactCard = document.getElementById('show_card_opt_impact');
            const notifyCard = document.getElementById('show_card_opt_notify');

            const coinsAmountInput = document.getElementById('show_coins_amount_input');
            const impactAmountInput = document.getElementById('show_impact_amount_input');
            const coinsPreviewEl = document.getElementById('show_preview_coins');
            const impactPreviewEl = document.getElementById('show_preview_impact');

            const summaryText = document.getElementById('show_summary_text');

            let coinsToRev = {{ (int) $prevCoinsToRev }};
            let impactToRev = {{ (int) $prevImpactToRev }};
            const currCoins = {{ (int) $prevCurrCoins }};
            const currImpact = {{ (int) $prevCurrImpact }};
            const owner = @json($targetName ?: 'Member');

            function refreshShowModalUi() {
                const isCoinsActive = coinsCheck && coinsCheck.checked;
                const isImpactActive = impactCheck && impactCheck.checked;
                const isNotifyActive = notifyCheck && notifyCheck.checked;

                if (coinsAmountInput) {
                    coinsAmountInput.disabled = !isCoinsActive;
                    if (isCoinsActive) {
                        coinsToRev = Math.max(0, parseInt(coinsAmountInput.value || '0', 10));
                    }
                }

                if (impactAmountInput) {
                    impactAmountInput.disabled = !isImpactActive;
                    if (isImpactActive) {
                        impactToRev = Math.max(0, parseInt(impactAmountInput.value || '0', 10));
                    }
                }

                if (isCoinsActive) {
                    coinsCard.className = 'p-2.5 rounded-3 border w-100 d-block cursor-pointer select-none bg-warning-subtle border-warning text-warning-emphasis position-relative transition';
                    if (coinsCheckIcon) coinsCheckIcon.classList.remove('d-none');
                    if (coinsPreviewEl) coinsPreviewEl.textContent = 'Bal: ' + currCoins.toLocaleString() + ' → ' + Math.max(0, currCoins - coinsToRev).toLocaleString();
                } else if (coinsCard) {
                    coinsCard.className = 'p-2.5 rounded-3 border w-100 d-block cursor-pointer select-none bg-light border-secondary-subtle text-muted position-relative opacity-75';
                    if (coinsCheckIcon) coinsCheckIcon.classList.add('d-none');
                    if (coinsPreviewEl) coinsPreviewEl.textContent = 'Bal: ' + currCoins.toLocaleString() + ' (No change)';
                }

                if (isImpactActive) {
                    impactCard.className = 'p-2.5 rounded-3 border w-100 d-block cursor-pointer select-none bg-info-subtle border-info text-info-emphasis position-relative transition';
                    if (impactCheckIcon) impactCheckIcon.classList.remove('d-none');
                    if (impactPreviewEl) impactPreviewEl.textContent = 'Score: ' + currImpact.toLocaleString() + ' → ' + Math.max(0, currImpact - impactToRev).toLocaleString();
                } else if (impactCard) {
                    impactCard.className = 'p-2.5 rounded-3 border w-100 d-block cursor-pointer select-none bg-light border-secondary-subtle text-muted position-relative opacity-75';
                    if (impactCheckIcon) impactCheckIcon.classList.add('d-none');
                    if (impactPreviewEl) impactPreviewEl.textContent = 'Score: ' + currImpact.toLocaleString() + ' (No change)';
                }

                if (isNotifyActive) {
                    notifyCard.className = 'p-2.5 rounded-3 border w-100 d-block cursor-pointer select-none bg-primary-subtle border-primary text-primary-emphasis position-relative transition';
                    if (notifyCheckIcon) notifyCheckIcon.classList.remove('d-none');
                    if (notifyBadge) notifyBadge.textContent = 'Push & In-App';
                } else if (notifyCard) {
                    notifyCard.className = 'p-2.5 rounded-3 border w-100 d-block cursor-pointer select-none bg-light border-secondary-subtle text-muted position-relative opacity-75';
                    if (notifyCheckIcon) notifyCheckIcon.classList.add('d-none');
                    if (notifyBadge) notifyBadge.textContent = 'No Alert';
                }

                let actions = [];
                const effectiveCoins = isCoinsActive ? coinsToRev : 0;
                const effectiveImpact = isImpactActive ? impactToRev : 0;

                if (effectiveCoins > 0) {
                    actions.push(`deduct <strong>-${effectiveCoins.toLocaleString()} Coins</strong>`);
                }
                if (effectiveImpact > 0) {
                    actions.push(`deduct <strong>-${effectiveImpact.toLocaleString()} Life Impact</strong>`);
                }

                let msg = 'Will deactivate post';
                if (actions.length > 0) {
                    msg += `, ${actions.join(' and ')} from <strong>${owner}</strong>`;
                } else {
                    msg += ` for <strong>${owner}</strong> without deducting coins or life impact`;
                }

                if (isNotifyActive) {
                    msg += `, and send them a push & in-app notification.`;
                } else {
                    msg += `, without notifying member.`;
                }

                if (summaryText) summaryText.innerHTML = msg;
            }

            if (coinsCheck) coinsCheck.addEventListener('change', refreshShowModalUi);
            if (impactCheck) impactCheck.addEventListener('change', refreshShowModalUi);
            if (notifyCheck) notifyCheck.addEventListener('change', refreshShowModalUi);

            if (coinsAmountInput) {
                coinsAmountInput.addEventListener('input', function () {
                    coinsToRev = Math.max(0, parseInt(this.value || '0', 10));
                    refreshShowModalUi();
                });
            }
            if (impactAmountInput) {
                impactAmountInput.addEventListener('input', function () {
                    impactToRev = Math.max(0, parseInt(this.value || '0', 10));
                    refreshShowModalUi();
                });
            }

            if (coinsCheck) coinsCheck.addEventListener('change', refreshShowModalUi);
            if (impactCheck) impactCheck.addEventListener('change', refreshShowModalUi);
            if (notifyCheck) notifyCheck.addEventListener('change', refreshShowModalUi);

            refreshShowModalUi();
        });
        </script>
    @endif
@endsection
