<?php

declare(strict_types=1);

namespace Tests\Feature\Forms;

use App\Models\BecomeMentorSubmission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BecomeMentorSubmissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        if (! Schema::hasTable('become_mentor_submissions')) {
            Schema::create('become_mentor_submissions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('first_name', 100);
                $table->string('last_name', 100);
                $table->string('email', 255);
                $table->string('phone', 30);
                $table->string('city', 150);
                $table->string('linkedin_profile', 500)->nullable();
                $table->text('remarks')->nullable();
                $table->string('status', 30)->default('new');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('become_mentor_submissions', 'remarks')) {
            Schema::table('become_mentor_submissions', function (Blueprint $table) {
                $table->text('remarks')->nullable();
            });
        }
    }

    public function test_can_submit_become_a_mentor_with_remarks(): void
    {
        $payload = [
            'first_name' => 'Rahul',
            'last_name' => 'Sharma',
            'email' => 'mentor.test.'.uniqid().'@example.com',
            'phone' => '98765'.rand(10000, 99999),
            'city' => 'Mumbai, Maharashtra',
            'linkedin_profile' => 'https://linkedin.com/in/rahulsharma',
            'remarks' => 'Over 10 years of experience in FinTech & scaling B2B SaaS startups.',
        ];

        $response = $this->postJson('/api/v1/become-a-mentor', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.first_name', 'Rahul')
            ->assertJsonPath('data.remarks', 'Over 10 years of experience in FinTech & scaling B2B SaaS startups.');

        $this->assertDatabaseHas('become_mentor_submissions', [
            'email' => $payload['email'],
            'remarks' => 'Over 10 years of experience in FinTech & scaling B2B SaaS startups.',
        ]);
    }

    public function test_can_submit_become_a_mentor_with_remark_alias(): void
    {
        $payload = [
            'first_name' => 'Amit',
            'last_name' => 'Patel',
            'email' => 'amit.test.'.uniqid().'@example.com',
            'phone' => '98764'.rand(10000, 99999),
            'city' => 'Ahmedabad, Gujarat',
            'remark' => 'Experienced business mentor.',
        ];

        $response = $this->postJson('/api/v1/become-a-mentor', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.remarks', 'Experienced business mentor.');
    }

    public function test_get_become_a_mentor_returns_remarks_in_list(): void
    {
        $user = User::factory()->create([
            'email' => 'priya.'.uniqid().'@example.com',
            'phone' => '98763'.rand(10000, 99999),
        ]);

        $submission = BecomeMentorSubmission::create([
            'first_name' => 'Priya',
            'last_name' => 'Verma',
            'email' => $user->email,
            'phone' => $user->phone,
            'city' => 'Delhi',
            'remarks' => 'Serial entrepreneur and advisor.',
            'status' => 'new',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/become-a-mentor');

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $data = collect($response->json('data'));
        $item = $data->firstWhere('id', (string) $submission->id);

        $this->assertNotNull($item);
        $this->assertEquals('Serial entrepreneur and advisor.', $item['remarks']);
    }
}
