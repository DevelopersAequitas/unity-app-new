<?php

namespace Tests\Unit;

use App\Mail\ImpactApprovedMail;
use App\Models\Impact;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ImpactApprovedMailTest extends TestCase
{
    public function test_impact_approved_email_uses_branded_dynamic_template(): void
    {
        $impact = new Impact([
            'action' => 'mentorship_peer_guidance',
            'story_to_share' => 'Helped a peer refine their growth plan.',
            'life_impacted' => 3,
            'status' => 'approved',
        ]);
        $impact->impact_date = Carbon::parse('2026-06-08');

        $submitter = new User([
            'display_name' => 'Harsh Chauhan',
            'email' => 'harsh@example.com',
            'life_impacted_count' => 27,
        ]);

        $mailable = new ImpactApprovedMail($impact, $submitter);
        $html = $mailable->render();

        $this->assertSame('Impact Approved Successfully', $mailable->subject);
        $this->assertStringContainsString('background:#2d0d66', $html);
        $this->assertStringContainsString('background:#111111', $html);
        $this->assertStringContainsString('Dear <strong', $html);
        $this->assertStringContainsString('Harsh Chauhan', $html);
        $this->assertStringContainsString('Great news! Your Impact has been approved successfully.', $html);
        $this->assertStringContainsString('Mentorship Peer Guidance', $html);
        $this->assertStringContainsString('08 Jun 2026', $html);
        $this->assertStringContainsString('Helped a peer refine their growth plan.', $html);
        $this->assertStringContainsString('Status:</strong> Approved', $html);
        $this->assertStringContainsString('Life Impacted:</strong> 3', $html);
        $this->assertStringContainsString('Total Life Impacted:</strong> 27', $html);
        $this->assertStringContainsString('Peers are partners in business and friends in life.', $html);
    }
}
