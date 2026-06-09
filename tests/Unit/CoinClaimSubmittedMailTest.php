<?php

namespace Tests\Unit;

use App\Mail\CoinClaimSubmittedMail;
use App\Models\CoinClaimRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CoinClaimSubmittedMailTest extends TestCase
{
    public function test_coin_claim_submitted_email_uses_professional_dynamic_content(): void
    {
        $claim = new CoinClaimRequest([
            'id' => 'claim-123',
            'activity_code' => 'attend_circle_meeting',
            'coins_awarded' => 10,
            'status' => 'pending',
        ]);
        $claim->exists = true;
        $claim->created_at = Carbon::parse('2026-06-09 10:15:00');
        $claim->setRelation('user', new User([
            'display_name' => 'Avinash Vaghela',
            'email' => 'avinash@example.com',
        ]));

        $mailable = new CoinClaimSubmittedMail($claim);
        $html = $mailable->render();

        $this->assertSame('Coin Claim Received for Attend Circle Meeting – Pending Review', $mailable->subject);
        $this->assertStringContainsString('Hello Avinash Vaghela,', $html);
        $this->assertStringContainsString('Thank you for submitting your coin claim.', $html);
        $this->assertStringContainsString('Claim Details', $html);
        $this->assertStringContainsString('claim-123', $html);
        $this->assertStringContainsString('Attend Circle Meeting', $html);
        $this->assertStringContainsString('10', $html);
        $this->assertStringContainsString('09 Jun 2026, 10:15 AM', $html);
        $this->assertStringContainsString('Pending Review', $html);
        $this->assertStringContainsString('Peers Global Unity', $html);
    }
}
