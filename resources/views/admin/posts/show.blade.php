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
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> No
                                </span>
                            @endif
                        </dd>
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
        <div class="card-body d-flex flex-wrap gap-3">
            @if ($isActive)
                <form method="POST" action="{{ route('admin.posts.destroy', $post) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to remove this post?')">Deactivate</button>
                </form>
            @else
                <span class="text-muted">This post has been removed.</span>
            @endif
        </div>
    </div>
@endsection
