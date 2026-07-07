<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ActivityAdminMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $activityType;

    public string $activityTitle;

    public ?User $actor;

    public array $activityAttributes;

    public function __construct(string $activityType, string $activityTitle, ?User $actor, array $activityAttributes)
    {
        $this->activityType = $activityType;
        $this->activityTitle = $activityTitle;
        $this->actor = $actor;
        $this->activityAttributes = $activityAttributes;
    }

    public function build(): self
    {
        $subject = 'Peers Global Unity: New Requirement submitted';

        return $this->subject($subject)
            ->view('emails.activity_admin');
    }
}
