<?php

namespace App\Mail;

use App\Models\SmeBusinessStorySubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StoryRejectedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public SmeBusinessStorySubmission $story) {}

    public function build(): self
    {
        return $this->subject('Update on Your Story Submission')
            ->view('emails.story_rejected');
    }
}
