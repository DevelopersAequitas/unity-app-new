@extends('admin.layouts.app')

@section('title', 'Birthday Creative Configuration')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h4 mb-0"><i class="bi bi-gift me-2 text-primary"></i>Birthday Creative Configuration</h1>
    <span class="badge bg-light text-dark border">Today's Birthdays: {{ $birthdayUsers->count() }}</span>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="row g-4">
    <!-- Left Panel: Configuration Form -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0"><i class="bi bi-sliders me-2 text-secondary"></i>Settings</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('admin.birthday-creative.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <!-- Feature Switch -->
                    <div class="mb-4">
                        <label class="form-label d-block fw-semibold">Feature Status</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="isEnabledSwitch" name="is_enabled_checkbox" {{ $config->is_enabled ? 'checked' : '' }} onchange="document.getElementById('isEnabledValue').value = this.checked ? '1' : '0'">
                            <input type="hidden" name="is_enabled" id="isEnabledValue" value="{{ $config->is_enabled ? '1' : '0' }}">
                            <label class="form-check-label" for="isEnabledSwitch">Automatically generate & post birthday wishes on the timeline</label>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Custom Template Upload -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Custom Template Image</label>
                        <p class="text-muted small">Upload an image template to use as background. Ideal size: 1080x1080px. Placeholders 'WELCOME!' and names will be overlaid dynamically.</p>
                        
                        @if($config->template_file_id)
                            <div class="d-flex align-items-center mb-3 p-3 bg-light rounded border">
                                <div class="flex-shrink-0">
                                    <i class="bi bi-image-fill fs-2 text-primary"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <div class="fw-semibold text-truncate small">Template Active</div>
                                    <div class="text-muted small">ID: {{ $config->template_file_id }}</div>
                                </div>
                                <div class="ms-auto">
                                    <a href="{{ url('/api/v1/files/' . $config->template_file_id) }}" class="btn btn-sm btn-outline-secondary me-2" target="_blank">
                                        <i class="bi bi-eye"></i> View Original
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteActiveTemplate()">
                                        <i class="bi bi-trash"></i> Remove
                                    </button>
                                </div>
                            </div>
                        @endif

                        <input type="file" class="form-control" name="template_image" accept="image/*">
                        <input type="hidden" name="delete_template" id="deleteTemplateInput" value="0">
                    </div>

                    <hr class="my-4">

                    <!-- Gradient Background Colors -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Gradient Style (Default Background)</label>
                        <p class="text-muted small">Used if no custom template is uploaded.</p>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label text-muted small">Gradient Start</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="gradientStartPicker" value="{{ $config->background_gradient_start }}" onchange="document.getElementById('gradientStartText').value = this.value">
                                    <input type="text" class="form-control" name="background_gradient_start" id="gradientStartText" value="{{ $config->background_gradient_start }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small">Gradient End</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="gradientEndPicker" value="{{ $config->background_gradient_end }}" onchange="document.getElementById('gradientEndText').value = this.value">
                                    <input type="text" class="form-control" name="background_gradient_end" id="gradientEndText" value="{{ $config->background_gradient_end }}">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label text-muted small">Text Color</label>
                                <div class="input-group">
                                    <input type="color" class="form-control form-control-color" id="textColorPicker" value="{{ $config->text_color }}" onchange="document.getElementById('textColorText').value = this.value">
                                    <input type="text" class="form-control" name="text_color" id="textColorText" value="{{ $config->text_color }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i>Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Panel: Creative Live Preview -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0"><i class="bi bi-eye me-2 text-secondary"></i>Creative Preview</h5>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center text-center">
                <div class="mb-3 w-100">
                    <label class="form-label text-muted small d-block">Select Peer to generate live preview</label>
                    <select class="form-select w-75 mx-auto" id="previewUserSelect" onchange="updatePreviewImage()">
                        @foreach($previewUsers as $pUser)
                            <option value="{{ $pUser->id }}">{{ $pUser->display_name ?: ($pUser->first_name . ' ' . $pUser->last_name) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="preview-canvas-wrapper border rounded p-2 bg-light shadow-inner mb-3" style="max-width: 380px; width: 100%; aspect-ratio: 1/1;">
                    <div id="previewLoader" class="d-none w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-white rounded">
                        <div class="spinner-border text-primary mb-2" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="text-muted small">Generating Creative...</div>
                    </div>
                    <img id="creativePreviewImg" src="" class="img-fluid rounded border w-100 h-100 d-block" alt="Birthday Creative Preview" style="object-fit: contain;">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Panel: Today's Birthday Users -->
<div class="card shadow-sm mt-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0"><i class="bi bi-cake2 me-2 text-danger"></i>Today's Birthdays ({{ now()->format('d M') }})</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-striped mb-0 align-middle">
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Date of Birth</th>
                    <th>Designation</th>
                    <th>Company</th>
                </tr>
            </thead>
            <tbody>
                @forelse($birthdayUsers as $bUser)
                    <tr>
                        <td>
                            @if($bUser->profile_photo_file_id)
                                <img src="{{ url('/api/v1/files/' . $bUser->profile_photo_file_id) }}" style="width: 40px; height: 40px; object-fit: cover;" class="rounded-circle border">
                            @elseif($bUser->profile_photo_url)
                                <img src="{{ $bUser->profile_photo_url }}" style="width: 40px; height: 40px; object-fit: cover;" class="rounded-circle border">
                            @else
                                <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px; font-size: 16px;">
                                    {{ strtoupper(substr($bUser->display_name ?: $bUser->first_name ?: 'U', 0, 1)) }}
                                </div>
                            @endif
                        </td>
                        <td><strong>{{ $bUser->display_name ?: ($bUser->first_name . ' ' . $bUser->last_name) }}</strong></td>
                        <td>{{ $bUser->email }}</td>
                        <td>{{ $bUser->dob ? \Carbon\Carbon::parse($bUser->dob)->format('d M Y') : '—' }}</td>
                        <td>{{ $bUser->designation ?: '—' }}</td>
                        <td>{{ $bUser->company_name ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No users celebrating a birthday today.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
    function updatePreviewImage() {
        const userId = document.getElementById('previewUserSelect').value;
        if (!userId) return;

        const img = document.getElementById('creativePreviewImg');
        const loader = document.getElementById('previewLoader');

        // Show loader, hide image
        img.classList.add('d-none');
        loader.classList.remove('d-none');

        // Generate cache-busting timestamp
        const t = new Date().getTime();
        const srcUrl = `/admin/birthday-creative/preview/${userId}?t=${t}`;

        // Load image in background
        const tempImg = new Image();
        tempImg.onload = function() {
            img.src = srcUrl;
            loader.classList.add('d-none');
            img.classList.remove('d-none');
        };
        tempImg.onerror = function() {
            loader.classList.add('d-none');
            img.classList.remove('d-none');
            alert('Failed to generate creative preview.');
        };
        tempImg.src = srcUrl;
    }

    function deleteActiveTemplate() {
        if (confirm('Are you sure you want to remove the current background template?')) {
            document.getElementById('deleteTemplateInput').value = '1';
            alert('Template marked for removal. Please submit settings form to save changes.');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        updatePreviewImage();
    });
</script>
@endsection
