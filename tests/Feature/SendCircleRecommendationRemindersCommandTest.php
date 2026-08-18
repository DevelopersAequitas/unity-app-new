<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendCircleRecommendationWhatsappJob;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\JoinedCircleCategory;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SendCircleRecommendationRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::dropIfExists('circles');
        Schema::dropIfExists('joined_circle_categories');
        Schema::dropIfExists('circle_members');
        Schema::dropIfExists('notification_delivery_logs');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('secondary_mobile', 20)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('slug', 150)->nullable();
            $table->string('status', 50)->default('active');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('joined_circle_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('user_id');
            $table->uuid('circle_id');
            $table->timestamps();
        });

        Schema::create('circle_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id');
            $table->uuid('user_id');
            $table->string('status', 50)->default('approved');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('notification_delivery_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('channel')->nullable();
            $table->string('provider')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_artisan_command_dispatches_whatsapp_job_for_eligible_users(): void
    {
        Queue::fake();

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Amit',
            'phone' => '9876543210',
        ]);

        $circle = Circle::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Real Estate Founders',
        ]);

        $selection = JoinedCircleCategory::query()->create([
            'user_id' => $user->id,
            'circle_id' => $circle->id,
        ]);
        $selection->timestamps = false;
        $selection->created_at = now()->subDays(4);
        $selection->save();

        $this->artisan('circle-recommendation:send-reminders')
            ->assertExitCode(0);

        Queue::assertPushed(SendCircleRecommendationWhatsappJob::class, function (SendCircleRecommendationWhatsappJob $job) use ($user): bool {
            return $job->userId === (string) $user->id && $job->circleName === 'Real Estate Founders';
        });
    }

    public function test_artisan_command_skips_users_who_have_already_joined_a_circle(): void
    {
        Queue::fake();

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Rahul',
            'phone' => '9876543210',
        ]);

        $circle = Circle::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Tech Founders',
        ]);

        $selection = JoinedCircleCategory::query()->create([
            'user_id' => $user->id,
            'circle_id' => $circle->id,
        ]);
        $selection->timestamps = false;
        $selection->created_at = now()->subDays(4);
        $selection->save();

        // User is ALREADY an approved member of a circle
        CircleMember::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'circle_id' => $circle->id,
            'status' => 'approved',
        ]);

        $this->artisan('circle-recommendation:send-reminders')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }
}
