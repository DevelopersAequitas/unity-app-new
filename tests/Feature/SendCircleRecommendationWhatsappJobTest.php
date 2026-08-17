<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendCircleRecommendationWhatsappJob;
use App\Models\User;
use App\Models\WhatsappTemplate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SendCircleRecommendationWhatsappJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::dropIfExists('whatsapp_templates');
        Schema::dropIfExists('notification_delivery_logs');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email', 255)->unique();
            $table->string('phone', 20)->nullable()->unique();
            $table->string('secondary_mobile', 20)->nullable();
            $table->string('password_hash')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('whatsapp_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('template_key')->unique();
            $table->string('template_name');
            $table->string('webhook_url');
            $table->string('webhook_secret');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('notification_delivery_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('channel')->nullable();
            $table->string('provider')->nullable();
            $table->string('status')->nullable();
            $table->json('request_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_send_circle_recommendation_whatsapp_job_executes_successfully(): void
    {
        WhatsappTemplate::query()->create([
            'id' => (string) Str::uuid(),
            'template_key' => 'circle_calling_day3',
            'template_name' => 'MSG 8 - Your Circle is Calling',
            'webhook_url' => 'https://webhook.example.com/whatsapp/circle-calling',
            'webhook_secret' => 'secret-123',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Rahul',
            'email' => 'rahul@example.com',
            'phone' => '9876543210',
        ]);

        Http::fake([
            'https://webhook.example.com/whatsapp/circle-calling' => Http::response(['success' => true], 200),
        ]);

        SendCircleRecommendationWhatsappJob::dispatchSync((string) $user->id, 'Tech Founders');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://webhook.example.com/whatsapp/circle-calling' &&
                $request['phone'] === '919876543210' &&
                $request['first_name'] === 'Rahul' &&
                $request['circle_name'] === 'Tech Founders';
        });

        $this->assertDatabaseHas('notification_delivery_logs', [
            'user_id' => $user->id,
            'channel' => 'whatsapp',
            'provider' => 'circle_calling_day3',
            'status' => 'sent',
        ]);
    }
}
