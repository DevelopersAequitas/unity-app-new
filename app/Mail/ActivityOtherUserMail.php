<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ActivityOtherUserMail extends Mailable
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

        $this->actorName = $data['actorName'] ?? 'Someone';
        $this->otherName = $data['otherName'] ?? null;
        $this->meetingDate = $data['meetingDate'] ?? null;
        $this->meetingPlace = $data['meetingPlace'] ?? null;
        $this->referralOf = $data['referralOf'] ?? null;
        $this->requirementSubject = $data['requirementSubject'] ?? null;
        $this->testimonialContent = $data['testimonialContent'] ?? null;
        $this->dealAmountInr = $data['dealAmountInr'] ?? null;

        $this->subjectLine = sprintf(
            'Peers Global Unity: %s created a %s with you',
            $this->actorName ?: 'Someone',
            $this->activityTypeLabel
        );
    }

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view($this->viewName);
    }

    protected function resolveView(): string
    {
        return match ($this->activityTypeNormalized) {
            'p2p_meeting' => 'emails.p2p_receiver',
            'referral' => 'emails.referral_receiver',
            'testimonial' => 'emails.testimonial_receiver',
            'business_deal' => 'emails.business_deal_receiver',
            default => 'emails.p2p_receiver',
        };
    }
}
