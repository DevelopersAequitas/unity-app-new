<p>Hello {{ $story->user?->display_name ?? $story->user?->first_name ?? 'Peer' }},</p>
<p>Your story submission "<strong>{{ $story->title }}</strong>" has been rejected.</p>
@if($story->rejected_reason)
<p><strong>Reason for rejection:</strong> {{ $story->rejected_reason }}</p>
@endif
<p>You can update and submit a new story through the app.</p>
