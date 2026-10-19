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
                        <dd class="col-sm-8">{{ $ownerName !== '' ? $ownerName : 'Unknown' }}</dd>
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
                            <div class="text-muted small">Media</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($mediaItems as $media)
                                    @php
                                        $mediaUrl = data_get($media, 'url');
                                    @endphp
                                    @if ($mediaUrl)
                                        <img src="{{ $mediaUrl }}" alt="Post media" class="img-thumbnail" style="width: 120px; height: auto;">
                                    @endif
                                @endforeach
                            </div>
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
        {{-- Deactivate Modal on Show Page --}}
        <div class="modal fade" id="deactivateShowModal" tabindex="-1" aria-labelledby="deactivateShowModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                    <form method="POST" action="{{ route('admin.posts.deactivate', $post) }}">
                        @csrf
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
                                    <span class="fw-bold text-dark small">{{ $ownerName ?: 'Member' }}</span>
                                    <span class="badge bg-white text-muted border small">{{ $post->created_at?->format('M d, Y') }}</span>
                                </div>
                                <div class="small text-secondary fst-italic border-start border-3 border-primary ps-2 py-1">
                                    "{{ Str::limit($post->content_text ?: 'Post content', 120) }}"
                                </div>
                            </div>

                            <div class="row g-2 mb-3 text-center">
                                <div class="col-4">
                                    <div class="p-2 rounded-3 border bg-warning-subtle text-warning-emphasis">
                                        <div class="text-uppercase fw-bold" style="font-size: 10px;">Coins</div>
                                        <div class="small fw-bold"><i class="bi bi-coin"></i> Reversal</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 rounded-3 border bg-info-subtle text-info-emphasis">
                                        <div class="text-uppercase fw-bold" style="font-size: 10px;">Life Impact</div>
                                        <div class="small fw-bold"><i class="bi bi-stars"></i> Reversal</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 rounded-3 border bg-primary-subtle text-primary-emphasis">
                                        <div class="text-uppercase fw-bold" style="font-size: 10px;">Alert</div>
                                        <div class="small fw-bold"><i class="bi bi-bell-fill"></i> In-App</div>
                                    </div>
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
    @endif
@endsection
