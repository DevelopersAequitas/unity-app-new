@extends('admin.layouts.app')
@section('title', 'Circulars')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Circulars</h1>
        <a href="{{ route('admin.circulars.create') }}" class="btn btn-primary">Create Circular</a>
    </div>
    <form class="card p-3 mb-3" method="GET">
        <div class="row g-2">
            <div class="col-md-3"><input class="form-control" name="search" placeholder="Search title/summary" value="{{ $filters['search'] }}"></div>
            <div class="col-md-2"><select class="form-select" name="category"><option value="">Category</option>@foreach($categories as $i)<option value="{{ $i }}" @selected($filters['category']===$i)>{{ $i }}</option>@endforeach</select></div>
            <div class="col-md-2"><select class="form-select" name="priority"><option value="">Priority</option>@foreach($priorities as $i)<option value="{{ $i }}" @selected($filters['priority']===$i)>{{ $i }}</option>@endforeach</select></div>
            <div class="col-md-2"><select class="form-select" name="status"><option value="">Status</option>@foreach($statuses as $i)<option value="{{ $i }}" @selected($filters['status']===$i)>{{ $i }}</option>@endforeach</select></div>
            <div class="col-md-3"><select class="form-select" name="audience_type"><option value="">Audience</option>@foreach($audiences as $i)<option value="{{ $i }}" @selected($filters['audience_type']===$i)>{{ $i }}</option>@endforeach</select></div>
            <div class="col-md-3"><select class="form-select js-searchable-select" name="city_id"><option value="">City</option>@foreach($cities as $city)<option value="{{ $city->id }}" @selected($filters['city_id']===(string)$city->id)>{{ $city->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><select class="form-select js-searchable-select" name="circle_id"><option value="">Circle</option>@foreach($circles as $circle)<option value="{{ $circle->id }}" @selected($filters['circle_id']===(string)$circle->id)>{{ $circle->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><input type="date" class="form-control" name="publish_date_from" value="{{ $filters['publish_date_from'] }}"></div>
            <div class="col-md-2"><input type="date" class="form-control" name="publish_date_to" value="{{ $filters['publish_date_to'] }}"></div>
            <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary w-100">Apply</button><a href="{{ route('admin.circulars.index') }}" class="btn btn-outline-secondary">Reset</a></div>
        </div>
    </form>

    <div class="card p-3">
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead><tr><th>Image</th><th>Title</th><th>Category</th><th>Priority</th><th>Audience</th><th>Status</th><th>Publish</th><th>Expiry</th><th>Pinned</th><th>Push</th><th>Created</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse($circulars as $circular)
                    <tr>
                        <td>@if($circular->featured_image_url)<img src="{{ $circular->featured_image_url }}" style="width:48px;height:48px;object-fit:cover;border-radius:8px">@else — @endif</td>
                        <td>{{ $circular->title }}</td><td>{{ $circular->category }}</td><td>{{ $circular->priority }}</td><td>{{ $circular->audience_type }}</td><td>{{ $circular->status }}</td><td>{{ optional($circular->publish_date)->format('Y-m-d H:i') }}</td><td>{{ optional($circular->expiry_date)->format('Y-m-d H:i') }}</td><td>{{ $circular->is_pinned ? 'Yes' : 'No' }}</td><td>{{ $circular->send_push_notification ? 'Yes' : 'No' }}</td><td>{{ optional($circular->created_at)->format('Y-m-d H:i') }}</td>
                        <td class="d-flex gap-1"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.circulars.show', $circular) }}">View</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.circulars.edit', $circular) }}">Edit</a><form method="POST" action="{{ route('admin.circulars.destroy', $circular) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this circular?')">Delete</button></form></td>
                    </tr>
                @empty <tr><td colspan="12" class="text-center">No circulars found.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        {{ $circulars->links() }}
    </div>
</div>
@endsection
