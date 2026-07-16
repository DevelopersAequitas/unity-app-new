<p>Hello {{ $story->user?->display_name ?? $story->user?->first_name ?? 'Peer' }},</p>
<p>Congratulations! Your story has been published in vyaparjagat.</p>
@if($story->story_link)
<p>You can view your story here: <a href="{{ $story->story_link }}" target="_blank">{{ $story->story_link }}</a></p>
@endif
<p>Thank you for sharing your story with the community!</p>
