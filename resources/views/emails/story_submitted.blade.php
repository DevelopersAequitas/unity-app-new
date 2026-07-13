<p>Hello Admin,</p>
<p>A new story submission has been received.</p>
<p><strong>User:</strong> {{ $story->user?->display_name ?? trim(($story->user?->first_name ?? '') . ' ' . ($story->user?->last_name ?? '')) }}</p>
<p><strong>Title:</strong> {{ $story->title }}</p>
<p>Please review and approve or reject it in the admin panel.</p>
