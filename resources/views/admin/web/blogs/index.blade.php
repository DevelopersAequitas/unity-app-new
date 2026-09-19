@extends('admin.layouts.app')

@section('title', 'Publications & Blogs - Peers Global Web')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-indigo-subtle text-indigo rounded-pill px-2.5 py-1" style="background: rgba(244, 63, 94, 0.12); color: #f43f5e; font-weight: 600; font-size: 0.72rem;">
                    <i class="bi bi-file-earmark-richtext me-1"></i> Peers Global Website
                </span>
            </div>
            <h1 class="h4 fw-bold text-dark mb-0 tracking-tight">Publications & Editorial Blogs</h1>
            <p class="text-muted small mb-0 mt-0.5">Publish articles, promoter thought leadership, and research insights</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-primary btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-xs fw-semibold" data-bs-toggle="modal" data-bs-target="#newBlogModal">
                <i class="bi bi-plus-circle-fill"></i>
                <span>New Article</span>
            </button>
        </div>
    </div>

    {{-- Blog List Table --}}
    <div class="card border-0 shadow-xs rounded-4 p-4 bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.84rem;">
                <thead class="table-light text-uppercase tracking-wider text-muted" style="font-size: 0.7rem;">
                    <tr>
                        <th>Article Title</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th>Read Time</th>
                        <th>Status</th>
                        <th>Published Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($blogs as $blog)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">{{ $blog->title }}</div>
                                <small class="text-muted text-truncate d-block" style="max-width: 320px;">{{ $blog->excerpt }}</small>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $blog->author_name }}</div>
                                <small class="text-muted">{{ $blog->author_role }}</small>
                            </td>
                            <td><span class="badge bg-light text-secondary border">{{ $blog->category ?? 'General' }}</span></td>
                            <td class="text-muted">{{ $blog->read_time ?? '5 min' }}</td>
                            <td>
                                @if($blog->is_published)
                                    <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(16, 185, 129, 0.12); color: #059669;">Published</span>
                                @else
                                    <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(100, 116, 139, 0.12); color: #64748b;">Draft</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $blog->published_at ? $blog->published_at->format('M d, Y') : '—' }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.web.blogs.destroy', $blog->id) }}" onsubmit="return confirm('Delete this article?');" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-light border text-danger rounded-2 px-2.5">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No publication articles found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-center pt-3">
            {{ $blogs->links() }}
        </div>
    </div>
</div>

{{-- Create Modal --}}
<div class="modal fade" id="newBlogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="{{ route('admin.web.blogs.store') }}" class="modal-content rounded-4 border-0 shadow">
            @csrf
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">New Publication Article</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Article Title</label>
                    <input type="text" name="title" class="form-control form-control-sm" required placeholder="e.g. The Sovereign Promoter Network">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Author Name</label>
                        <input type="text" name="author_name" value="Dr. Pravin Parmar" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Author Role</label>
                        <input type="text" name="author_role" value="Founder & Global Chair" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Category</label>
                        <input type="text" name="category" value="Thought Leadership" class="form-control form-control-sm">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Estimated Read Time</label>
                        <input type="text" name="read_time" value="5 min read" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Short Excerpt / Summary</label>
                    <textarea name="excerpt" rows="2" class="form-control form-control-sm" placeholder="A brief teaser of the publication..."></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Article Body Content (HTML or Markdown)</label>
                    <textarea name="content" rows="6" class="form-control form-control-sm" placeholder="Write the full content of the article here..."></textarea>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_published" value="1" id="isPublishedCheck" checked>
                    <label class="form-check-label small" for="isPublishedCheck">Publish Immediately to Website</label>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm px-4 fw-semibold">Publish Article</button>
            </div>
        </form>
    </div>
</div>
@endsection
