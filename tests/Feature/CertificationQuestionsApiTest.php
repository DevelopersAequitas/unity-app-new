<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\EntrepreneurCertificationSubmission;
use App\Models\LeadershipCertificationSubmission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CertificationQuestionsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('display_name')->nullable();
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->string('company_name')->nullable();
                $table->string('membership_status')->nullable();
                $table->integer('coins_balance')->default(0);
                $table->string('password_hash')->nullable();
                $table->string('public_profile_slug')->nullable();
                $table->string('status')->default('active');
                $table->text('bookmarks')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('personal_access_tokens')) {
            Schema::create('personal_access_tokens', function (Blueprint $table): void {
                $table->id();
                $table->string('tokenable_type');
                $table->string('tokenable_id');
                $table->string('name');
                $table->string('token', 64)->unique();
                $table->text('abilities')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_can_fetch_leadership_certification_questions(): void
    {
        $response = $this->getJson('/api/v1/leadership-certification/questions');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Leadership certification questions fetched successfully.')
            ->assertJsonPath('data.total_questions', 25)
            ->assertJsonCount(25, 'data.questions');

        $questions = $response->json('data.questions');

        foreach ($questions as $q) {
            $this->assertArrayHasKey('id', $q);
            $this->assertArrayHasKey('key', $q);
            $this->assertArrayHasKey('question', $q);
            $this->assertArrayHasKey('options', $q);
            $this->assertIsArray($q['options']);
            $this->assertCount(4, $q['options']);

            $this->assertContains($q['key'], LeadershipCertificationSubmission::QUIZ_FIELDS);

            $correctAnswer = LeadershipCertificationSubmission::CORRECT_ANSWERS[$q['key']];
            $this->assertContains($correctAnswer, $q['options'], "Correct answer for key '{$q['key']}' must be in options.");
        }
    }

    public function test_can_fetch_entrepreneur_certification_questions(): void
    {
        $response = $this->getJson('/api/v1/entrepreneur-certification/questions');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Entrepreneur certification questions fetched successfully.')
            ->assertJsonPath('data.total_questions', 25)
            ->assertJsonCount(25, 'data.questions');

        $questions = $response->json('data.questions');

        foreach ($questions as $q) {
            $this->assertArrayHasKey('id', $q);
            $this->assertArrayHasKey('key', $q);
            $this->assertArrayHasKey('question', $q);
            $this->assertArrayHasKey('options', $q);
            $this->assertIsArray($q['options']);
            $this->assertCount(4, $q['options']);

            $this->assertContains($q['key'], EntrepreneurCertificationSubmission::QUIZ_FIELDS);

            $correctAnswer = EntrepreneurCertificationSubmission::CORRECT_ANSWERS[$q['key']];
            $this->assertContains($correctAnswer, $q['options'], "Correct answer for key '{$q['key']}' must be in options.");
        }
    }

    public function test_authenticated_users_can_also_fetch_certification_questions(): void
    {
        $user = User::factory()->create([
            'email' => 'peer.user@example.com',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/leadership-certification/questions')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.total_questions', 25);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/entrepreneur-certification/questions')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.total_questions', 25);
    }

    public function test_returns_questions_from_database_when_table_populated(): void
    {
        Schema::create('certification_questions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('certification_type');
            $table->integer('sort_order');
            $table->string('key');
            $table->text('question');
            $table->json('options');
            $table->text('correct_answer');
            $table->integer('points')->default(4);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('certification_questions')->insert([
            'id' => (string) Str::uuid(),
            'certification_type' => 'leadership',
            'sort_order' => 1,
            'key' => 'team_struggling_action',
            'question' => 'Custom DB Question: How do you help struggling peers?',
            'options' => json_encode(['Custom Option 1', 'Custom Option 2']),
            'correct_answer' => 'Custom Option 1',
            'points' => 4,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/leadership-certification/questions');

        $response->assertOk()
            ->assertJsonPath('data.total_questions', 1)
            ->assertJsonPath('data.questions.0.question', 'Custom DB Question: How do you help struggling peers?')
            ->assertJsonPath('data.questions.0.options.0', 'Custom Option 1');
    }
}
