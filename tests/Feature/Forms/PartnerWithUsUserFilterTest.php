<?php

declare(strict_types=1);

namespace Tests\Feature\Forms;

use App\Models\PartnerWithUsSubmission;
use App\Models\User;
use Tests\TestCase;

class PartnerWithUsUserFilterTest extends TestCase
{
    public function test_user_only_sees_their_own_partner_with_us_submissions(): void
    {
        $userA = User::factory()->create([
            'email' => 'user.a.'.uniqid().'@example.com',
            'phone' => '98765'.rand(10000, 99999),
        ]);

        $userB = User::factory()->create([
            'email' => 'user.b.'.uniqid().'@example.com',
            'phone' => '98764'.rand(10000, 99999),
        ]);

        // Submission for User A
        $subA = PartnerWithUsSubmission::create([
            'first_name' => 'User',
            'last_name' => 'A',
            'email_id' => $userA->email,
            'mobile_number' => $userA->phone,
            'city' => 'Ahmedabad',
            'brand_or_company_name' => 'Company A',
            'industry' => 'Tech',
            'about_your_business' => 'Business A',
            'partnership_goal' => 'Goal A',
            'why_partner_with_peers_global' => 'Reason A',
            'status' => 'new',
        ]);

        // Submission for User B
        $subB = PartnerWithUsSubmission::create([
            'first_name' => 'User',
            'last_name' => 'B',
            'email_id' => $userB->email,
            'mobile_number' => $userB->phone,
            'city' => 'Mumbai',
            'brand_or_company_name' => 'Company B',
            'industry' => 'Finance',
            'about_your_business' => 'Business B',
            'partnership_goal' => 'Goal B',
            'why_partner_with_peers_global' => 'Reason B',
            'status' => 'new',
        ]);

        // Request as User A
        $responseA = $this->actingAs($userA, 'sanctum')->getJson('/api/v1/partner-with-us');

        $responseA->assertStatus(200);
        $dataA = collect($responseA->json('data'));

        // Should contain subA, but NOT subB
        $this->assertTrue($dataA->contains('id', (string) $subA->id));
        $this->assertFalse($dataA->contains('id', (string) $subB->id));

        // Show single submission should allow User A to see subA but 404 for subB
        $showA = $this->actingAs($userA, 'sanctum')->getJson('/api/v1/partner-with-us/'.$subA->id);
        $showA->assertStatus(200);

        $showB = $this->actingAs($userA, 'sanctum')->getJson('/api/v1/partner-with-us/'.$subB->id);
        $showB->assertStatus(404);
    }
}
