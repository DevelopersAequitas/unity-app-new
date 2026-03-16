@extends('admin.layouts.app')

@section('title', 'Circulars')

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card shadow-sm">
    <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
        <h6 class="mb-0">Circulars</h6>
        <a href="{{ route('admin.circulars.create') }}" class="btn btn-primary btn-sm">Create Circular</a>
    </div>
    <div class="p-3 border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Title</label>
                <input type="text" name="title" value="{{ $filters['title'] }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label">Category</label>
                <select name="category" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($categoryOptions as $option)
                        <option value="{{ $option }}" @selected($filters['category'] === $option)>{{ ucfirst($option) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($priorityOptions as $option)
                        <option value="{{ $option }}" @selected($filters['priority'] === $option)>{{ ucfirst($option) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($statusOptions as $option)
                        <option value="{{ $option }}" @selected($filters['status'] === $option)>{{ ucfirst(str_replace('_',' ', $option)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Audience Type</label>
                <select name="audience_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($audienceOptions as $option)
                        <option value="{{ $option }}" @selected($filters['audience_type'] === $option)>{{ ucfirst(str_replace('_',' ', $option)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Chapter / City</label>
                <select name="city_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($cities as $city)
                        <option value="{{ $city->id }}" @selected((string)$filters['city_id']===(string)$city->id)>{{ $city->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Circle</label>
                <select name="circle_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach($circles as $circle)
                        <option value="{{ $circle->id }}" @selected((string)$filters['circle_id']===(string)$circle->id)>{{ $circle->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="submit">Apply</button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.circulars.index') }}">Reset</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Title</th><th>Category</th><th>Priority</th><th>Audience Type</th><th>City</th><th>Circle</th><th>Status</th><th>Publish Date</th><th>Expiry Date</th><th>Pinned</th><th>Views</th><th>Created By</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($circulars as $circular)
                    <tr>
                        <td>{{ $circular->title }}</td>
                        <td>{{ ucfirst($circular->category) }}</td>
                        <td><span class="badge bg-{{ $circular->priority === 'urgent' ? 'danger' : ($circular->priority === 'important' ? 'warning text-dark' : 'secondary') }}">{{ ucfirst($circular->priority) }}</span></td>
                        <td>{{ ucfirst(str_replace('_',' ', $circular->audience_type)) }}</td>
                        <td>{{ $circular->city?->name ?? '—' }}</td>
                        <td>{{ $circular->circle?->name ?? '—' }}</td>
                        <td><span class="badge bg-{{ $circular->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst(str_replace('_',' ', $circular->status)) }}</span></td>
                        <td>{{ optional($circular->publish_date)->format('d M Y, h:i A') }}</td>
                        <td>{{ optional($circular->expiry_date)->format('d M Y, h:i A') ?: '—' }}</td>
                        <td><span class="badge bg-{{ $circular->is_pinned ? 'primary' : 'light text-dark' }}">{{ $circular->is_pinned ? 'Pinned' : 'No' }}</span></td>
                        <td>{{ (int)$circular->view_count }}</td>
                        <td>{{ $circular->creator?->display_name ?? trim(($circular->creator?->first_name ?? '').' '.($circular->creator?->last_name ?? '')) ?: '—' }}</td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.circulars.show', $circular->getKey()) }}">View</a>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.circulars.edit', $circular->getKey()) }}">Edit</a>
                            <form action="{{ route('admin.circulars.destroy', $circular->getKey()) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this circular?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="13" class="text-center text-muted py-4">No circulars found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $circulars->links() }}</div>
</div>
@endsection
