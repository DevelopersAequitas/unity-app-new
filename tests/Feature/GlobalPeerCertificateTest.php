<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileModel;
use App\Models\Post;
use App\Models\User;
use App\Services\Creative\GlobalPeerCertificateImageGenerator;
use App\Services\Notifications\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GlobalPeerCertificateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper: create a base user with the given membership_status.
     */
    private function makeUser(string $membershipStatus, ?string $certSentAt = null): User
    {
        return User::factory()->create([
            'id' => (string) Str::uuid(),
            'status' => 'active',
            'membership_status' => $membershipStatus,
            'global_peer_certificate_sent_at' => $certSentAt,
        ]);
    }

    /**
     * Helper: build a fake FileModel for the mock generator.
     */
    private function makeFakeFileModel(): FileModel
    {
        return FileModel::factory()->create([
            'id' => (string) Str::uuid(),
            's3_key' => 'uploads/global_peer_cert_test.webp',
            'mime_type' => 'image/webp',
        ]);
    }

    public function test_paid_user_without_certificate_receives_certificate(): void
    {
        $user = $this->makeUser('Only Unity Peer');
        $fakeFile = $this->makeFakeFileModel();

        // Mock the image generator so we don't need a real template file
        $generatorMock = $this->mock(GlobalPeerCertificateImageGenerator::class);
        $generatorMock->shouldReceive('generate')
            ->once()
            ->with(\Mockery::on(fn (User $u) => $u->id === $user->id))
            ->andReturn($fakeFile);

        // Mock the notification service to avoid real FCM calls
        $notificationMock = $this->mock(NotificationService::class);
        $notificationMock->shouldReceive('sendToUser')->once()->andReturn(null);

        $this->artisan('certificates:send-global-peer', ['--user-id' => $user->id])
            ->assertSuccessful();

        // Certificate sent timestamp must be stamped
        $this->assertNotNull($user->fresh()->global_peer_certificate_sent_at);

        // A timeline post must have been created
        $this->assertDatabaseHas('posts', [
            'source_type' => 'global_peer_certificate',
            'source_id' => $user->id,
            'post_type' => 'global_peer_certificate',
        ]);
    }

    public function test_free_peer_user_does_not_receive_certificate(): void
    {
        $user = $this->makeUser('free_peer');

        $generatorMock = $this->mock(GlobalPeerCertificateImageGenerator::class);
        $generatorMock->shouldNotReceive('generate');

        $this->artisan('certificates:send-global-peer')
            ->assertSuccessful();

        $this->assertNull($user->fresh()->global_peer_certificate_sent_at);
        $this->assertDatabaseMissing('posts', ['source_id' => $user->id, 'post_type' => 'global_peer_certificate']);
    }

    public function test_free_trial_peer_user_does_not_receive_certificate(): void
    {
        $user = $this->makeUser('free_trial_peer');

        $generatorMock = $this->mock(GlobalPeerCertificateImageGenerator::class);
        $generatorMock->shouldNotReceive('generate');

        $this->artisan('certificates:send-global-peer')
            ->assertSuccessful();

        $this->assertNull($user->fresh()->global_peer_certificate_sent_at);
    }

    public function test_already_stamped_paid_user_is_skipped(): void
    {
        $alreadyStampedAt = now()->subDay()->toDateTimeString();
        $user = $this->makeUser('Only Unity Peer', $alreadyStampedAt);

        $generatorMock = $this->mock(GlobalPeerCertificateImageGenerator::class);
        $generatorMock->shouldNotReceive('generate');

        $notificationMock = $this->mock(NotificationService::class);
        $notificationMock->shouldNotReceive('sendToUser');

        $this->artisan('certificates:send-global-peer')
            ->assertSuccessful();

        // Timestamp should remain the original value (not re-stamped)
        $this->assertEquals(
            $alreadyStampedAt,
            $user->fresh()->global_peer_certificate_sent_at->toDateTimeString()
        );
    }

    public function test_command_is_idempotent_for_multiple_paid_tiers(): void
    {
        $paidStatuses = ['Circle Peer', 'Multi Circle Peer', 'Charter Peer'];
        $users = [];

        foreach ($paidStatuses as $status) {
            $users[] = $this->makeUser($status);
        }

        $fakeFile = $this->makeFakeFileModel();

        $generatorMock = $this->mock(GlobalPeerCertificateImageGenerator::class);
        $generatorMock->shouldReceive('generate')
            ->times(count($users))
            ->andReturn($fakeFile);

        $notificationMock = $this->mock(NotificationService::class);
        $notificationMock->shouldReceive('sendToUser')->times(count($users))->andReturn(null);

        $this->artisan('certificates:send-global-peer')->assertSuccessful();

        foreach ($users as $user) {
            $this->assertNotNull($user->fresh()->global_peer_certificate_sent_at);
        }
    }
}
