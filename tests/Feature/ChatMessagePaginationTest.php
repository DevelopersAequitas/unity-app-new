<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Chat;
use App\Models\Circle;
use App\Models\CircleChatMessage;
use App\Models\CircleMember;
use App\Models\LeadershipGroupMessage;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatMessagePaginationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    private function setUpDatabase(): void
    {
        Schema::dropIfExists('leadership_group_message_reads');
        Schema::dropIfExists('leadership_group_message_deletions');
        Schema::dropIfExists('leadership_group_messages');
        Schema::dropIfExists('circle_chat_message_reads');
        Schema::dropIfExists('circle_chat_messages');
        Schema::dropIfExists('circle_members');
        Schema::dropIfExists('circles');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('chats');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('display_name', 150)->nullable();
            $table->string('email', 255)->unique();
            $table->string('company_name', 255)->nullable();
            $table->string('profile_photo_url', 500)->nullable();
            $table->string('status', 50)->default('active');
            $table->timestamps();
        });

        Schema::create('chats', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user1_id');
            $table->uuid('user2_id');
            $table->uuid('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('chat_id');
            $table->uuid('sender_id');
            $table->text('content')->nullable();
            $table->json('attachments')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('deleted_for_user1_at')->nullable();
            $table->timestamp('deleted_for_user2_at')->nullable();
            $table->timestamps();
        });

        Schema::create('circles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('circle_members', function (Blueprint $table): void {
            $table->id();
            $table->uuid('circle_id');
            $table->uuid('user_id');
            $table->string('status')->default('active');
            $table->string('role')->default('member');
            $table->timestamps();
        });

        Schema::create('circle_chat_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id');
            $table->uuid('sender_id');
            $table->string('message_type', 30)->default('text');
            $table->text('message_text')->nullable();
            $table->uuid('reply_to_message_id')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_mime')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->boolean('is_deleted_for_all')->default(false);
            $table->json('deleted_for_users')->nullable();
            $table->timestamps();
        });

        Schema::create('circle_chat_message_reads', function (Blueprint $table): void {
            $table->id();
            $table->uuid('message_id');
            $table->uuid('user_id');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('leadership_group_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('circle_id');
            $table->uuid('sender_user_id');
            $table->string('message_type', 30)->default('text');
            $table->text('message_text')->nullable();
            $table->uuid('reply_to_message_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('leadership_group_message_reads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('message_id');
            $table->uuid('user_id');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('leadership_group_message_deletions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('message_id');
            $table->uuid('user_id');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_direct_chat_messages_are_returned_newest_first_and_support_cursor_pagination(): void
    {
        $user1 = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'display_name' => 'Alice Smith',
            'email' => 'alice@example.com',
        ]);

        $user2 = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Bob',
            'last_name' => 'Jones',
            'display_name' => 'Bob Jones',
            'email' => 'bob@example.com',
        ]);

        $chat = Chat::create([
            'id' => (string) Str::uuid(),
            'user1_id' => $user1->id,
            'user2_id' => $user2->id,
        ]);

        $msg1 = Message::create([
            'id' => (string) Str::uuid(),
            'chat_id' => $chat->id,
            'sender_id' => $user1->id,
            'content' => 'Oldest message 1',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        $msg2 = Message::create([
            'id' => (string) Str::uuid(),
            'chat_id' => $chat->id,
            'sender_id' => $user2->id,
            'content' => 'Middle message 2',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        $msg3 = Message::create([
            'id' => (string) Str::uuid(),
            'chat_id' => $chat->id,
            'sender_id' => $user1->id,
            'content' => 'Newest message 3',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user1);

        $response = $this->getJson("/api/chats/{$chat->id}/messages?per_page=2");
        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'data' => [
                'items',
                'messages',
                'pagination' => ['current_page', 'last_page', 'per_page', 'total', 'has_more', 'next_cursor'],
            ],
        ]);

        $items = $response->json('data.items');
        $this->assertCount(2, $items);
        $this->assertSame((string) $msg3->id, $items[0]['id']);
        $this->assertSame((string) $msg2->id, $items[1]['id']);
        $this->assertTrue($response->json('data.pagination.has_more'));
        $this->assertSame((string) $msg2->id, $response->json('data.pagination.next_cursor'));

        $cursor = $response->json('data.pagination.next_cursor');
        $nextResponse = $this->getJson("/api/chats/{$chat->id}/messages?per_page=2&before_message_id={$cursor}");
        $nextResponse->assertOk();
        $nextItems = $nextResponse->json('data.items');
        $this->assertCount(1, $nextItems);
        $this->assertSame((string) $msg1->id, $nextItems[0]['id']);
        $this->assertFalse($nextResponse->json('data.pagination.has_more'));
    }

    public function test_circle_chat_messages_are_returned_newest_first_and_support_cursor(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Charlie',
            'last_name' => 'Brown',
            'display_name' => 'Charlie Brown',
            'email' => 'charlie@example.com',
        ]);

        $circle = Circle::create([
            'id' => (string) Str::uuid(),
            'name' => 'Tech Circle',
        ]);

        CircleMember::create([
            'circle_id' => $circle->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        $cmsg1 = CircleChatMessage::create([
            'id' => (string) Str::uuid(),
            'circle_id' => $circle->id,
            'sender_id' => $user->id,
            'message_text' => 'Circle msg 1',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        $cmsg2 = CircleChatMessage::create([
            'id' => (string) Str::uuid(),
            'circle_id' => $circle->id,
            'sender_id' => $user->id,
            'message_text' => 'Circle msg 2',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        $cmsg3 = CircleChatMessage::create([
            'id' => (string) Str::uuid(),
            'circle_id' => $circle->id,
            'sender_id' => $user->id,
            'message_text' => 'Circle msg 3',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/circles/{$circle->id}/chat/messages?per_page=2");
        $response->assertOk();

        $items = $response->json('data.items');
        $this->assertCount(2, $items);
        $this->assertSame((string) $cmsg3->id, $items[0]['id']);
        $this->assertSame((string) $cmsg2->id, $items[1]['id']);
        $this->assertTrue($response->json('data.pagination.has_more'));

        $cursor = $response->json('data.pagination.next_cursor');
        $nextResponse = $this->getJson("/api/circles/{$circle->id}/chat/messages?per_page=2&before_message_id={$cursor}");
        $nextResponse->assertOk();
        $nextItems = $nextResponse->json('data.items');
        $this->assertCount(1, $nextItems);
        $this->assertSame((string) $cmsg1->id, $nextItems[0]['id']);
    }

    public function test_leadership_chat_messages_are_returned_newest_first_and_support_cursor(): void
    {
        $user = User::create([
            'id' => (string) Str::uuid(),
            'first_name' => 'Leader',
            'last_name' => 'One',
            'display_name' => 'Leader One',
            'email' => 'leader@example.com',
        ]);

        $circle = Circle::create([
            'id' => (string) Str::uuid(),
            'name' => 'Leadership Circle',
        ]);

        CircleMember::create([
            'circle_id' => $circle->id,
            'user_id' => $user->id,
            'role' => 'chair',
            'status' => 'active',
        ]);

        $lmsg1 = LeadershipGroupMessage::create([
            'id' => (string) Str::uuid(),
            'circle_id' => $circle->id,
            'sender_user_id' => $user->id,
            'message_text' => 'Lead msg 1',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        $lmsg2 = LeadershipGroupMessage::create([
            'id' => (string) Str::uuid(),
            'circle_id' => $circle->id,
            'sender_user_id' => $user->id,
            'message_text' => 'Lead msg 2',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/circles/{$circle->id}/leadership-chat/messages?per_page=1");
        $response->assertOk();

        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertSame((string) $lmsg2->id, $items[0]['id']);
        $this->assertTrue($response->json('data.pagination.has_more'));

        $cursor = $response->json('data.pagination.next_cursor');
        $nextResponse = $this->getJson("/api/circles/{$circle->id}/leadership-chat/messages?per_page=1&before_message_id={$cursor}");
        $nextResponse->assertOk();
        $nextItems = $nextResponse->json('data.items');
        $this->assertCount(1, $nextItems);
        $this->assertSame((string) $lmsg1->id, $nextItems[0]['id']);
    }
}
