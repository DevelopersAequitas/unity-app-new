<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Models\User;
use App\Models\WhatsappTemplate;
use App\Services\Creative\IntroducedPeerCreativeGenerator;
use App\Services\Creative\LifeImpactCreativeGenerator;
use App\Services\WhatsApp\AwardRecognitionWhatsappService;
use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AwardRecognitionPersonalizedCreativeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge();

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('display_name')->nullable();
                $table->string('phone')->nullable();
                $table->string('secondary_mobile')->nullable();
                $table->string('company')->nullable();
                $table->string('company_name')->nullable();
                $table->string('designation')->nullable();
                $table->string('city')->nullable();
                $table->string('email')->nullable();
                $table->string('membership_status')->nullable();
                $table->integer('life_impacted_count')->default(0);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('member_award_sends')) {
            Schema::create('member_award_sends', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->string('award_track', 50);
                $table->string('award_title', 100);
                $table->integer('milestone_count');
                $table->string('template_name', 150);
                $table->string('language', 20)->default('en_IN');
                $table->string('phone_number', 50);
                $table->text('creative_image_url')->nullable();
                $table->text('referral_link')->nullable();
                $table->string('status', 50)->default('pending');
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->string('provider_message_id', 255)->nullable();
                $table->json('payload_json')->nullable();
                $table->json('provider_response_json')->nullable();
                $table->text('error_message')->nullable();
                $table->boolean('is_test')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('whatsapp_templates')) {
            Schema::create('whatsapp_templates', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('template_name')->nullable();
                $table->string('template_key')->nullable();
                $table->string('language', 20)->default('en_IN');
                $table->json('components')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function test_handle_impact_award_uses_personalized_creative_instead_of_raw_template(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Pravin',
            'last_name' => 'Parmar',
            'display_name' => 'PRAVIN PARMAR',
            'phone' => '919876543210',
            'company' => 'Peers Global Business Media Pvt Ltd',
            'designation' => 'Founder',
            'city' => 'Ahmedabad',
            'life_impacted_count' => 50,
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'name' => 'change_maker_50',
            'language' => 'en_IN',
            'components' => [
                [
                    'type' => 'HEADER',
                    'format' => 'IMAGE',
                ],
                [
                    'type' => 'BODY',
                    'text' => 'Peer {{1}}, many congratulations! You are now a Peers Global CHANGE MAKER. Referral: {{2}}',
                ],
            ],
            'is_active' => true,
        ]);

        $personalizedUrl = 'https://peersunity.com/storage/uploads/2026/10/29/life_impact_creative_pravin.png';

        $impactGeneratorMock = $this->createMock(LifeImpactCreativeGenerator::class);
        $impactGeneratorMock->expects($this->once())
            ->method('generateOrGetUrl')
            ->with($user, 50, 50)
            ->willReturn($personalizedUrl);

        $growthGeneratorMock = $this->createMock(IntroducedPeerCreativeGenerator::class);

        $metaServiceMock = $this->createMock(MetaWhatsAppService::class);
        $metaServiceMock->expects($this->once())
            ->method('sendTemplateMessage')
            ->with(
                '919876543210',
                'change_maker_50',
                'en_IN',
                $this->callback(function (array $components) use ($personalizedUrl): bool {
                    $headerParam = $components[0]['parameters'][0] ?? null;
                    $this->assertNotNull($headerParam);
                    $this->assertSame('image', $headerParam['type']);

                    // It must NOT use an unrendered template or null
                    $image = $headerParam['image'] ?? [];
                    if (isset($image['link'])) {
                        $this->assertSame($personalizedUrl, $image['link']);
                    }

                    return true;
                }),
                (string) $user->id
            )
            ->willReturn([
                'meta_message_id' => 'wamid.HBgMOTE5ODc2NTQzMjEwFQIAERgSRjQyNDQ4ODAyNzExOTQ5MkEA',
            ]);

        $service = new AwardRecognitionWhatsappService(
            $metaServiceMock,
            $growthGeneratorMock,
            $impactGeneratorMock
        );

        $send = $service->handleImpactAward($user, 50);

        $this->assertNotNull($send);
        $this->assertSame('CHANGE MAKER', $send->award_title);
        $this->assertSame(50, $send->milestone_count);
        $this->assertSame('sent', $send->status);
        $this->assertSame($personalizedUrl, $send->creative_image_url);
        $this->assertStringNotContainsString('images/life_impact_creatives/Change Maker.png', (string) $send->creative_image_url);
        $this->assertSame('https://peersunity.com/share?type=badges&tab=lives_impacted', $send->referral_link);
    }

    public function test_send_award_auto_generates_creative_if_raw_template_path_passed(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Pravin',
            'last_name' => 'Parmar',
            'display_name' => 'PRAVIN PARMAR',
            'phone' => '919876543210',
        ]);

        $personalizedUrl = 'https://peersunity.com/storage/uploads/2026/10/29/personalized_50.png';

        $impactGeneratorMock = $this->createMock(LifeImpactCreativeGenerator::class);
        $impactGeneratorMock->expects($this->once())
            ->method('generateOrGetUrl')
            ->with($user, 50, 50)
            ->willReturn($personalizedUrl);

        $growthGeneratorMock = $this->createMock(IntroducedPeerCreativeGenerator::class);

        $metaServiceMock = $this->createMock(MetaWhatsAppService::class);
        $metaServiceMock->expects($this->once())
            ->method('sendTemplateMessage')
            ->willReturn(['meta_message_id' => 'wamid.12345']);

        $service = new AwardRecognitionWhatsappService(
            $metaServiceMock,
            $growthGeneratorMock,
            $impactGeneratorMock
        );

        // Pass raw unrendered template image path
        $send = $service->sendAward(
            $user,
            'LIFE_IMPACT',
            'CHANGE MAKER',
            50,
            'change_maker_50',
            'en_IN',
            'images/life_impact_creatives/Change Maker.png'
        );

        $this->assertNotNull($send);
        $this->assertSame($personalizedUrl, $send->creative_image_url);
    }

    public function test_handle_growth_award_uses_personalized_creative(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'display_name' => 'JOHN DOE',
            'phone' => '919876543211',
        ]);

        WhatsappTemplate::create([
            'id' => (string) Str::uuid(),
            'name' => 'milestone_connector_v2',
            'template_name' => 'milestone_connector_v2',
            'template_key' => 'milestone_connector_v2',
            'language' => 'en_IN',
            'components' => [
                [
                    'type' => 'HEADER',
                    'format' => 'IMAGE',
                ],
                [
                    'type' => 'BODY',
                    'text' => 'Congratulations {{1}}! Referral: {{2}}',
                ],
            ],
            'is_active' => true,
        ]);

        $personalizedUrl = 'https://peersunity.com/storage/uploads/2026/10/29/connector_john.png';

        $growthGeneratorMock = $this->createMock(IntroducedPeerCreativeGenerator::class);
        $growthGeneratorMock->expects($this->once())
            ->method('generateOrGetUrl')
            ->with($user, 1)
            ->willReturn($personalizedUrl);

        $impactGeneratorMock = $this->createMock(LifeImpactCreativeGenerator::class);

        $metaServiceMock = $this->createMock(MetaWhatsAppService::class);
        $metaServiceMock->expects($this->once())
            ->method('sendTemplateMessage')
            ->willReturn(['meta_message_id' => 'wamid.growth123']);

        $service = new AwardRecognitionWhatsappService(
            $metaServiceMock,
            $growthGeneratorMock,
            $impactGeneratorMock
        );

        $send = $service->handleGrowthAward($user, 1);

        $this->assertNotNull($send);
        $this->assertSame('CONNECTOR', $send->award_title);
        $this->assertSame(1, $send->milestone_count);
        $this->assertSame($personalizedUrl, $send->creative_image_url);
        $this->assertStringNotContainsString('images/member_introduce_creatives/Connector.png', (string) $send->creative_image_url);
        $this->assertStringStartsWith('https://peersunity.com/register?ref=', (string) $send->referral_link);
    }

    public function test_dev_server_links_for_life_impact_and_growth_referrals(): void
    {
        Config::set('app.url', 'https://dev.peersunity.com');
        Config::set('app.public_url', 'https://dev.peersunity.com');

        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Harsh',
            'last_name' => 'Chauhan',
            'display_name' => 'HARSH CHAUHAN',
            'phone' => '919876543299',
        ]);

        $metaServiceMock = $this->createMock(MetaWhatsAppService::class);
        $metaServiceMock->expects($this->exactly(2))
            ->method('sendTemplateMessage')
            ->willReturn(['meta_message_id' => 'wamid.dev123']);

        $impactGeneratorMock = $this->createMock(LifeImpactCreativeGenerator::class);
        $impactGeneratorMock->method('generateOrGetUrl')->willReturn('https://dev.peersunity.com/storage/uploads/impact.png');

        $growthGeneratorMock = $this->createMock(IntroducedPeerCreativeGenerator::class);
        $growthGeneratorMock->method('generateOrGetUrl')->willReturn('https://dev.peersunity.com/storage/uploads/growth.png');

        $service = new AwardRecognitionWhatsappService(
            $metaServiceMock,
            $growthGeneratorMock,
            $impactGeneratorMock
        );

        // 1. Life Impact Send (e.g. Impact Builder 250) -> dev badges tab link
        $sendImpact = $service->handleImpactAward($user, 250);
        $this->assertNotNull($sendImpact);
        $this->assertSame('https://dev.peersunity.com/share?type=badges&tab=lives_impacted', $sendImpact->referral_link);

        // 2. Growth Send (e.g. Connector 1) -> dev peer registration referral link (e.g. register?ref=HARSH...)
        $sendGrowth = $service->handleGrowthAward($user, 1);
        $this->assertNotNull($sendGrowth);
        $this->assertStringStartsWith('https://dev.peersunity.com/register?ref=HARSH', (string) $sendGrowth->referral_link);
    }

    public function test_send_award_uploads_physical_personalized_file_when_exists(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Pravin',
            'last_name' => 'Parmar',
            'display_name' => 'PRAVIN PARMAR',
            'phone' => '919876543210',
        ]);

        // Create a temporary dummy physical file simulating rendered creative
        $tempFile = tempnam(sys_get_temp_dir(), 'test_creative_');
        file_put_contents($tempFile, 'fake_png_data');

        $impactGeneratorMock = $this->createMock(LifeImpactCreativeGenerator::class);
        $growthGeneratorMock = $this->createMock(IntroducedPeerCreativeGenerator::class);

        $metaServiceMock = $this->createMock(MetaWhatsAppService::class);
        $metaServiceMock->expects($this->once())
            ->method('uploadMediaFromPath')
            ->with($tempFile)
            ->willReturn('meta_media_id_999');

        $metaServiceMock->expects($this->once())
            ->method('sendTemplateMessage')
            ->with(
                '919876543210',
                'change_maker_50',
                'en_IN',
                $this->callback(function (array $components): bool {
                    $headerParam = $components[0]['parameters'][0] ?? null;
                    $this->assertSame('meta_media_id_999', $headerParam['image']['id'] ?? null);

                    return true;
                }),
                (string) $user->id
            )
            ->willReturn(['meta_message_id' => 'wamid.media999']);

        $service = new AwardRecognitionWhatsappService(
            $metaServiceMock,
            $growthGeneratorMock,
            $impactGeneratorMock
        );

        $send = $service->sendAward(
            $user,
            'LIFE_IMPACT',
            'CHANGE MAKER',
            50,
            'change_maker_50',
            'en_IN',
            $tempFile
        );

        $this->assertNotNull($send);
        $this->assertSame('sent', $send->status);

        @unlink($tempFile);
    }

    public function test_post_creative_to_timeline_triggers_whatsapp_award(): void
    {
        if (! Schema::hasTable('posts')) {
            Schema::create('posts', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable();
                $table->uuid('circle_id')->nullable();
                $table->text('content_text')->nullable();
                $table->json('media')->nullable();
                $table->json('tags')->nullable();
                $table->string('visibility', 50)->default('public');
                $table->string('moderation_status', 50)->default('approved');
                $table->boolean('sponsored')->default(false);
                $table->boolean('is_deleted')->default(false);
                $table->string('source_type', 50)->nullable();
                $table->uuid('source_id')->nullable();
                $table->string('source_event', 100)->nullable();
                $table->string('post_type', 50)->nullable();
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->string('image')->nullable();
                $table->string('status', 50)->default('active');
                $table->boolean('active')->default(true);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('post_mentions')) {
            Schema::create('post_mentions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('post_id');
                $table->uuid('peer_id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name')->nullable();
                $table->string('email')->unique();
                $table->string('password')->nullable();
                $table->string('role', 50)->default('super_admin');
                $table->string('status', 50)->default('active');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name', 255);
                $table->string('key', 100);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('admin_user_roles')) {
            Schema::create('admin_user_roles', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id');
                $table->uuid('role_id');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('role_module_access')) {
            Schema::create('role_module_access', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('role_id');
                $table->string('module_key', 100);
                $table->timestamps();
            });
        }

        $superRole = \App\Models\Role::firstOrCreate(
            ['key' => 'super_admin'],
            ['id' => (string) Str::uuid(), 'name' => 'Super Admin']
        );

        $admin = \App\Models\AdminUser::create([
            'id' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin_'.Str::random(5).'@peersunity.com',
            'role' => 'super_admin',
            'status' => 'active',
            'is_active' => true,
        ]);

        DB::table('admin_user_roles')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'role_id' => $superRole->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $peer = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Harsh',
            'last_name' => 'Chauhan',
            'display_name' => 'HARSH CHAUHAN',
            'phone' => '919876543212',
            'life_impacted_count' => 500,
        ]);

        $mockAwardService = $this->createMock(AwardRecognitionWhatsappService::class);
        $mockAwardService->expects($this->once())
            ->method('handleImpactAward')
            ->with(
                $this->callback(fn ($u) => $u->id === $peer->id),
                500,
                $this->isType('string')
            );
        $this->app->instance(AwardRecognitionWhatsappService::class, $mockAwardService);

        $response = $this->actingAs($admin, 'admin')
            ->postJson(route('admin.life-impact-recognitions.post-creative', ['id' => $peer->id]), [
                'threshold' => 500,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('posts', [
            'source_type' => 'life_impact',
            'source_id' => $peer->id,
            'post_type' => 'life_impact_recognition',
        ]);
    }
}
