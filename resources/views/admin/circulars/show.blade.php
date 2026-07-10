@extends('admin.layouts.app')
@section('title', 'Circular Details')
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0 fw-bold">Circular Details</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.circulars.index') }}" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> Back
            </a>
            <a href="{{ route('admin.circulars.edit', $circular) }}" class="btn btn-primary">Edit</a>
        </div>
    </div>
    <div class="card p-3">
        <dl class="row mb-0">
            @foreach([
                'ID'=>$circular->id,'Title'=>$circular->title,'Summary'=>$circular->summary,'Category'=>$circular->category,'Priority'=>$circular->priority,
                'Publish Date'=>optional($circular->publish_date)?->format('Y-m-d H:i'),'Expiry Date'=>optional($circular->expiry_date)?->format('Y-m-d H:i'),'Audience Type'=>$circular->audience_type,
                'Status'=>$circular->status,'Allow Comments'=>$circular->allow_comments ? 'Yes':'No','Pinned'=>$circular->is_pinned ? 'Yes':'No','Send Push'=>$circular->send_push_notification ? 'Yes':'No',
                'City'=>optional($circular->city)->name ?? '-', 'Circle'=>optional($circular->circle)->name ?? '-','Video URL'=>$circular->video_url,'CTA Label'=>$circular->cta_label,'CTA URL'=>$circular->cta_url,
                'Featured Image URL'=>$circular->featured_image_url,'Attachment URL'=>$circular->attachment_url
            ] as $label => $value)
            <dt class="col-sm-3">{{ $label }}</dt><dd class="col-sm-9">{{ $value ?: '—' }}</dd>
            @endforeach
            <dt class="col-sm-3">Content</dt><dd class="col-sm-9">{!! nl2br(e($circular->content)) !!}</dd>
        </dl>
    </div>
</div>
@endsection
