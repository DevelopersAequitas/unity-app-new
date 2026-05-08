<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PeerMonthlyImpactScriptApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('referrals');
        Schema::dropIfExists('p2p_meetings');
        Schema::dropIfExists('business_deals');
        Schema::dropIfExists('life_impact_histories');
        Schema::dropIfExists('impacts');
    }

    public function test_user_without_referrals_returns_safe_defaults_and_successful_script(): void
    {
        $user = new User([
            'id' => (string) Str::uuid(),
            'first_name' => 'Demo1',
            'last_name' => 'Demo1',
            'display_name' => 'Demo1 Demo1',
            'company_name' => 'Aequitas Information Technology Pvt Ltd',
            'business_type' => null,
            'industry_tags' => [],
            'life_impacted_count' => 0,
        ]);
        $user->setAttribute('category', null);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/peer-monthly-impact-script');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.category', 'your category')
            ->assertJsonPath(
                'data.script.introduction_text',
                'My name is Demo1 Demo1. I run Aequitas Information Technology Pvt Ltd in your category.'
            )
            ->assertJsonPath('data.script.monthly_business_done_text', 'This month I did business worth ₹ 0.00 with Peers.')
            ->assertJsonPath('data.script.business_deals_text', 'I recorded 0 business deal(s) this month totalling ₹ 0.00.');

        $qualifiedReferrals = collect($response->json('data.checklist_items'))
            ->firstWhere('key', 'qualified_referrals_given');

        $this->assertSame(0, $qualifiedReferrals['count']);
        $this->assertSame([], $qualifiedReferrals['related_items']);
        $this->assertFalse($qualifiedReferrals['is_available']);
    }
}
