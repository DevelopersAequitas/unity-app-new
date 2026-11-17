<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\Chat\MessageReactionUpdated;
use App\Models\Chat;
use App\Models\Circle;
use App\Models\CircleChatMessage;
use App\Models\CircleMember;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageReactionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createTestTables();
    }

    private function createTestTables(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('company_name')->nullable();
            $table->string('designation')->nullable();
            $table->string('city')->nullable();
            $table->uuid('city_id')->nullable();
            $table->string('profile_photo_url')->nullable();
            $table->uuid('profile_photo_file_id')->nullable();
            $table->string('membership_status')->default('free_trial_peer');
            $table->string('status')->default('active');
            $table->boolean('is_online')->default(false);
            $table->integer('coins_balance')->default(0);
            $table->integer('life_impacted_count')->default(0);
            $table->integer('members_introduced_count')->default(0);
            $table->string('coin_medal_rank')->nullable();
            $table->string('coin_milestone_title')->nullable();
            $table->string('coin_milestone_meaning')->nullable();
            $table->string('contribution_award_name')->nullable();
            $table->string('contribution_award_recognition')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('chats', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user1_id')->index();
            $table->uuid('user2_id')->index();
            $table->uuid('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('chat_id')->index();
            $table->uuid('sender_id')->index();
            $table->text('content')->nullable();
            $table->json('attachments')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('deleted_for_user1_at')->nullable();
            $table->timestamp('deleted_for_user2_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('status')->default('active');
            $table->json('calendar')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circle_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id')->index();
            $table->uuid('user_id')->index();
            $table->string('status')->default('approved');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('circle_chat_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id')->index();
            $table->uuid('sender_id')->index();
            $table->string('message_type', 32)->default('text');
            $table->text('message_text')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_mime')->nullable();
            $table->integer('file_size')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->json('deleted_for_users')->nullable();
            $table->uuid('reply_to_message_id')->nullable();
            $table->boolean('is_deleted_for_all')->default(false);
            $table->timestamp('deleted_for_all_at')->nullable();
            $table->timestamps();
        });

        Schema::create('circle_chat_message_reads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('message_id')->index();
            $table->uuid('user_id')->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('message_reactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('message_id')->index();
            $table->uuid('user_id')->index();
            $table->string('emoji', 32);
            $table->string('message_type', 32)->default('direct');
            $table->timestamps();
            $table->unique(['message_id', 'user_id'], 'uq_message_user');
        });
    }

    public function test_direct_chat_message_reaction_add_replace_remove_and_list_messages(): void
    {
        Event::fake([MessageReactionUpdated::class]);

        $user1 = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Rohan',
            'last_name' => 'Sharma',
            'display_name' => 'Rohan',
            'email' => 'rohan@example.com',
            'status' => 'active',
        ]);

        $user2 = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Priya',
            'last_name' => 'Patel',
            'display_name' => 'Priya',
            'email' => 'priya@example.com',
            'status' => 'active',
        ]);

        $chat = Chat::forceCreate([
            'id' => (string) Str::uuid(),
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
        ]);

        $message = Message::forceCreate([
            'id' => (string) Str::uuid(),
            'chat_id' => $chat->id,
            'sender_id' => $user2->id,
            'content' => 'Hello Rohan!',
            'is_read' => false,
        ]);

        Sanctum::actingAs($user1);

        // 1. Case C (First time): Add reaction ❤️
        $addResponse = $this->postJson("/api/v1/messages/{$message->id}/reactions", [
            'emoji' => '❤️',
        ]);

        $addResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('action', 'added')
            ->assertJsonPath('data.message_id', (string) $message->id)
            ->assertJsonPath('data.reactions.0.emoji', '❤️')
            ->assertJsonPath('data.reactions.0.user_id', (string) $user1->id)
            ->assertJsonPath('data.reactions.0.user_name', 'Rohan Sharma')
            ->assertJsonPath('data.reactions.0.is_mine', true);

        Event::assertDispatched(MessageReactionUpdated::class, function (MessageReactionUpdated $event) use ($message): bool {
            return $event->messageId === (string) $message->id && $event->action === 'added';
        });

        // 2. Case B (Different emoji): Replace ❤️ with 👍
        $replaceResponse = $this->postJson("/api/v1/messages/{$message->id}/reactions", [
            'emoji' => '👍',
        ]);

        $replaceResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('action', 'replaced')
            ->assertJsonCount(1, 'data.reactions')
            ->assertJsonPath('data.reactions.0.emoji', '👍')
            ->assertJsonPath('data.reactions.0.is_mine', true);

        // 3. Verify GET /api/v1/chats/{chatId}/messages includes reactions array with accurate is_mine
        $listAsUser1 = $this->getJson("/api/v1/chats/{$chat->id}/messages");
        $listAsUser1->assertOk()
            ->assertJsonPath('data.messages.0.id', (string) $message->id)
            ->assertJsonPath('data.messages.0.reactions.0.emoji', '👍')
            ->assertJsonPath('data.messages.0.reactions.0.user_name', 'Rohan Sharma')
            ->assertJsonPath('data.messages.0.reactions.0.is_mine', true);

        Sanctum::actingAs($user2);
        $listAsUser2 = $this->getJson("/api/v1/chats/{$chat->id}/messages");
        $listAsUser2->assertOk()
            ->assertJsonPath('data.messages.0.reactions.0.emoji', '👍')
            ->assertJsonPath('data.messages.0.reactions.0.is_mine', false);

        // 4. Case A (Same emoji): Toggle off 👍 -> removed
        Sanctum::actingAs($user1);
        $removeResponse = $this->postJson("/api/v1/messages/{$message->id}/reactions", [
            'emoji' => '👍',
        ]);

        $removeResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('action', 'removed')
            ->assertJsonCount(0, 'data.reactions');
    }

    public function test_circle_chat_message_reaction_toggle_and_access_control(): void
    {
        Event::fake([MessageReactionUpdated::class]);

        $member = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Rohan',
            'display_name' => 'Rohan',
            'email' => 'rohan@example.com',
            'status' => 'active',
        ]);

        $nonMember = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Outsider',
            'display_name' => 'Outsider',
            'email' => 'outsider@example.com',
            'status' => 'active',
        ]);

        $circle = Circle::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Ahmedabad Founders Circle',
            'status' => 'active',
        ]);

        CircleMember::forceCreate([
            'id' => (string) Str::uuid(),
            'circle_id' => $circle->id,
            'user_id' => $member->id,
            'status' => 'approved',
        ]);

        $circleMessage = CircleChatMessage::forceCreate([
            'id' => (string) Str::uuid(),
            'circle_id' => $circle->id,
            'sender_id' => $member->id,
            'message_type' => 'text',
            'message_text' => 'Hello Circle!',
            'is_deleted_for_all' => false,
        ]);

        // Non-member cannot react to circle message
        Sanctum::actingAs($nonMember);
        $forbiddenResponse = $this->postJson("/api/v1/messages/{$circleMessage->id}/reactions", [
            'emoji' => '🔥',
        ]);
        $forbiddenResponse->assertStatus(403);

        // Approved member reacts with 🔥
        Sanctum::actingAs($member);
        $addResponse = $this->postJson("/api/v1/messages/{$circleMessage->id}/reactions", [
            'emoji' => '🔥',
        ]);

        $addResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('action', 'added')
            ->assertJsonPath('data.circle_id', (string) $circle->id)
            ->assertJsonPath('data.reactions.0.emoji', '🔥')
            ->assertJsonPath('data.reactions.0.user_name', 'Rohan')
            ->assertJsonPath('data.reactions.0.is_mine', true);
    }
}
