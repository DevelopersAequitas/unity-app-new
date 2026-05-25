<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Activity Creative' }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f7fb; margin: 0; padding: 24px; color: #1f2937; }
        .card { max-width: 860px; margin: 0 auto; background: #fff; border-radius: 12px; border: 1px solid #dbe1ea; overflow: hidden; }
        .head { background: linear-gradient(135deg, #0a3d91, #0c6ef4); color: #fff; padding: 28px; }
        .head h1 { margin: 0; font-size: 28px; }
        .body { padding: 28px; }
        .meta div { margin: 6px 0; font-size: 14px; }
        .text { white-space: pre-line; line-height: 1.6; font-size: 16px; margin-top: 14px; }
        .photo { width: 84px; height: 84px; border-radius: 50%; object-fit: cover; border: 2px solid #e5e7eb; }
        .footer { border-top: 1px solid #e5e7eb; margin-top: 24px; padding-top: 16px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
<div class="card">
    <div class="head">
        <h1>{{ $title ?? 'Activity Creative' }}</h1>
    </div>

    <div class="body">
        {{-- birthday_celebration layout section (easy replacement with finalized template later) --}}
        @if(!empty($profilePhotoUrl))
            <img class="photo" src="{{ $profilePhotoUrl }}" alt="Profile Photo">
        @endif

        <div class="meta">
            <div><strong>Name:</strong> {{ $userName ?? 'N/A' }}</div>
            <div><strong>Company:</strong> {{ $companyName ?? 'N/A' }}</div>
            <div><strong>Designation:</strong> {{ $designation ?? 'N/A' }}</div>
            <div><strong>City:</strong> {{ $city ?? 'N/A' }}</div>
            <div><strong>Date:</strong> {{ $date ?? ($activityDate ?? now()->format('d M Y')) }}</div>
            <div><strong>Type:</strong> {{ $activityType ?? 'activity' }}</div>
        </div>

        <div class="text">{{ $creativeText ?? '' }}</div>

        <div class="footer">Powered by Peers Global Unity</div>
    </div>
</div>
</body>
</html>
