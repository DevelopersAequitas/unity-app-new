<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ActivityActorMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $activityTypeNormalized;

    public string $activityTypeLabel;

    public string $viewName;

    public string $subjectLine;

    public string $actorName;

    public ?string $otherName;

    public ?string $meetingDate;

    public ?string $meetingPlace;

    public ?string $referralOf;

    public ?string $requirementSubject;

    public ?string $testimonialContent;

    public ?string $dealAmountInr;

    public function __construct(string $activityTypeNormalized, string $activityTypeLabel, array $data)
    {
        $this->activityTypeNormalized = $activityTypeNormalized;
        $this->activityTypeLabel = $activityTypeLabel;
        $this->viewName = $this->resolveView();
        $this->subjectLine = sprintf('Peers Global Unity: Your %s has been recorded', $this->activityTypeLabel);

        $this->actorName = $data['actorName'] ?? '';
        $this->otherName = $data['otherName'] ?? null;
        $this->meetingDate = $data['meetingDate'] ?? null;
        $this->meetingPlace = $data['meetingPlace'] ?? null;
        $this->referralOf = $data['referralOf'] ?? null;
        $this->requirementSubject = $data['requirementSubject'] ?? null;
        $this->testimonialContent = $data['testimonialContent'] ?? null;
        $this->dealAmountInr = $data['dealAmountInr'] ?? null;
    }

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view($this->viewName);
    }

    protected function resolveView(): string
    {
        return match ($this->activityTypeNormalized) {
            'p2p_meeting' => 'emails.p2p_sender',
            'referral' => 'emails.referral_sender',
            'requirement' => 'emails.requirement_sender',
            'testimonial' => 'emails.testimonial_sender',
            'business_deal' => 'emails.business_deal_sender',
            default => 'emails.p2p_sender',
        };
    }
}
