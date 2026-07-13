<p>Hello {{ $story->user?->display_name ?? $story->user?->first_name ?? 'Peer' }},</p>
<p>Congratulations! Your story submission "<strong>{{ $story->title }}</strong>" has been approved.</p>
<p>Thank you for sharing your story with the community!</p>
