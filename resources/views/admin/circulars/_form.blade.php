@csrf
<div class="card mb-3">
    <div class="card-header"><strong>Section A – Basic Information</strong></div>
    <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label">Circular Title *</label><input name="title" class="form-control" value="{{ old('title', $circular->title) }}" required></div>
        <div class="col-md-6"><label class="form-label">Circular Category *</label><select name="category" class="form-select" required>@foreach($categories as $item)<option value="{{ $item }}" @selected(old('category', $circular->category)===$item)>{{ ucfirst(str_replace('_',' ',$item)) }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">Priority *</label><select name="priority" class="form-select" required>@foreach($priorities as $item)<option value="{{ $item }}" @selected(old('priority', $circular->priority ?? 'normal')===$item)>{{ ucfirst($item) }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">Publish Date *</label><input type="datetime-local" name="publish_date" class="form-control" value="{{ old('publish_date', optional($circular->publish_date)->format('Y-m-d\TH:i')) }}" required></div>
        <div class="col-md-6"><label class="form-label">Expiry Date</label><input type="datetime-local" name="expiry_date" class="form-control" value="{{ old('expiry_date', optional($circular->expiry_date)->format('Y-m-d\TH:i')) }}"></div>
        <div class="col-12"><label class="form-label">Short Summary</label><textarea name="summary" class="form-control" maxlength="500" rows="2">{{ old('summary', $circular->summary) }}</textarea></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><strong>Section B – Content</strong></div>
    <div class="card-body row g-3">
        <div class="col-md-6">
            <label class="form-label">Featured Image</label>
            <input type="hidden" name="featured_image_file_id" id="featuredImageFileId" value="{{ old('featured_image_file_id') }}">
            <input type="file" class="form-control js-upload" data-target="featuredImageFileId" accept="image/*">
            @if($circular->featured_image_url)<a href="{{ $circular->featured_image_url }}" target="_blank" class="btn btn-sm btn-outline-primary mt-2">View current</a>@endif
        </div>
        <div class="col-md-6">
            <label class="form-label">Attachment</label>
            <input type="hidden" name="attachment_file_id" id="attachmentFileId" value="{{ old('attachment_file_id') }}">
            <input type="file" class="form-control js-upload" data-target="attachmentFileId">
            @if($circular->attachment_url)<a href="{{ $circular->attachment_url }}" target="_blank" class="btn btn-sm btn-outline-primary mt-2">View current</a>@endif
        </div>
        <div class="col-12"><label class="form-label">Detailed Description</label><textarea name="content" class="form-control" rows="6">{{ old('content', $circular->content) }}</textarea></div>
        <div class="col-md-6"><label class="form-label">Video Link</label><input name="video_url" class="form-control" value="{{ old('video_url', $circular->video_url) }}"></div>
        <div class="col-md-3"><label class="form-label">CTA Label</label><input name="cta_label" class="form-control" value="{{ old('cta_label', $circular->cta_label) }}"></div>
        <div class="col-md-3"><label class="form-label">CTA URL</label><input name="cta_url" class="form-control" value="{{ old('cta_url', $circular->cta_url) }}"></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><strong>Section C – Target Audience</strong></div>
    <div class="card-body row g-3">
        <div class="col-md-3"><label class="form-label">Audience Type *</label><select name="audience_type" class="form-select" required>@foreach($audiences as $item)<option value="{{ $item }}" @selected(old('audience_type', $circular->audience_type ?? 'all_members')===$item)>{{ ucfirst(str_replace('_',' ',$item)) }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">City</label><select name="city_id" class="form-select"><option value="">Select City</option>@foreach($cities as $city)<option value="{{ $city->id }}" @selected(old('city_id', $circular->city_id)===(string)$city->id)>{{ $city->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Circle</label><select name="circle_id" class="form-select"><option value="">Select Circle</option>@foreach($circles as $circle)<option value="{{ $circle->id }}" @selected(old('circle_id', $circular->circle_id)===(string)$circle->id)>{{ $circle->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Status *</label><select name="status" class="form-select" required>@foreach($statuses as $item)<option value="{{ $item }}" @selected(old('status', $circular->status ?? 'draft')===$item)>{{ ucfirst($item) }}</option>@endforeach</select></div>
        <div class="col-md-4 form-check"><input class="form-check-input" type="checkbox" value="1" name="send_push_notification" id="send_push_notification" @checked(old('send_push_notification', $circular->send_push_notification ?? true))><label class="form-check-label" for="send_push_notification">Send Push Notification</label></div>
        <div class="col-md-4 form-check"><input class="form-check-input" type="checkbox" value="1" name="allow_comments" id="allow_comments" @checked(old('allow_comments', $circular->allow_comments ?? false))><label class="form-check-label" for="allow_comments">Allow Comments</label></div>
        <div class="col-md-4 form-check"><input class="form-check-input" type="checkbox" value="1" name="is_pinned" id="is_pinned" @checked(old('is_pinned', $circular->is_pinned ?? false))><label class="form-check-label" for="is_pinned">Pin Circular</label></div>
    </div>
</div>

<button type="submit" class="btn btn-primary">Save Circular</button>

@push('scripts')
<script>
document.querySelectorAll('.js-upload').forEach((input) => {
    input.addEventListener('change', async (event) => {
        const file = event.target.files[0];
        if (!file) return;
        const fd = new FormData();
        fd.append('file', file);
        const response = await fetch("{{ route('admin.files.upload') }}", {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            body: fd,
        });
        const payload = await response.json();
        const fileId = payload?.data?.id ?? null;
        if (fileId) {
            document.getElementById(event.target.dataset.target).value = fileId;
        }
    });
});
</script>
@endpush
