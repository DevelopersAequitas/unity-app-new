<?php

namespace App\Mail;

use App\Models\SmeBusinessStorySubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StoryApprovedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public SmeBusinessStorySubmission $story) {}

    public function build(): self
    {
        return $this->subject('Your Story Submission Has Been Approved!')
            ->view('emails.story_approved');
    }
}
