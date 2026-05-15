<?php

namespace App\Mail;

use App\Models\AdminCampaign;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminCampaignMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    public string $subjectLine;

    public function __construct(public AdminCampaign $campaign, public User $recipient)
    {
        $this->subjectLine = (string) $campaign->subject;
    }

    public function build(): self
    {
        return $this->subject($this->subjectLine)
            ->view('emails.admin_campaigns.campaign')
            ->with([
                'campaign' => $this->campaign,
                'recipient' => $this->recipient,
                'bodyHtml' => $this->campaign->email_body,
            ]);
    }
}
