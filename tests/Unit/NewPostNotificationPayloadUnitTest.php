<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Notifications\AppNotification;
use Tests\TestCase;

class NewPostNotificationPayloadUnitTest extends TestCase
{
    public function test_data_payload_includes_navigation_screen_member_id_post_id_and_type_for_new_post(): void
    {
        $notification = new AppNotification([
            'id' => 'notif-uuid-12345',
            'user_id' => 'recipient-user-001',
            'type' => 'new_post',
            'title' => 'New Post from John',
            'body' => 'John published a new post: Check out our latest updates!',
            'reference_type' => 'post',
            'reference_id' => 'post-uuid-9999',
            'screen' => '/member-profile',
            'data' => [
                'navigation_screen' => '/member-profile',
                'member_id' => 'member-user-7777',
                'post_id' => 'post-uuid-9999',
                'type' => 'new_post',
            ],
        ]);

        $payload = $notification->dataPayload();

        $this->assertEquals('/member-profile', $payload['navigation_screen']);
        $this->assertEquals('member-user-7777', $payload['member_id']);
        $this->assertEquals('post-uuid-9999', $payload['post_id']);
        $this->assertEquals('new_post', $payload['type']);
    }

    public function test_data_payload_derives_member_id_from_user_id_if_member_id_not_explicitly_set(): void
    {
        $notification = new AppNotification([
            'id' => 'notif-uuid-12346',
            'user_id' => 'recipient-user-002',
            'type' => 'new_post',
            'title' => 'New Post from Sarah',
            'body' => 'Sarah published a new post.',
            'reference_type' => 'post',
            'reference_id' => 'post-uuid-8888',
            'data' => [
                'user_id' => 'author-user-5555',
                'post_id' => 'post-uuid-8888',
                'type' => 'new_post',
            ],
        ]);

        $payload = $notification->dataPayload();

        $this->assertEquals('/member-profile', $payload['navigation_screen']);
        $this->assertEquals('author-user-5555', $payload['member_id']);
        $this->assertEquals('author-user-5555', $payload['user_id']);
        $this->assertEquals('post-uuid-8888', $payload['post_id']);
        $this->assertEquals('new_post', $payload['type']);
    }
}
