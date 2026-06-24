<?php

namespace Tests\Unit;

use App\Models\FileModel;
use App\Services\Membership\MembershipWelcomeEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;

class MembershipWelcomeEmailServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_two_membership_welcome_uploaded_file_attachments(): void
    {
        config(['filesystems.default' => 'public']);
        Storage::fake('public');

        $first = $this->makeStoredFile('uploads/welcome/first.pdf', 'first');
        $second = $this->makeStoredFile('uploads/welcome/second.pdf', 'second');

        config([
            'membership_welcome.attachment_1_file_id' => (string) $first->id,
            'membership_welcome.attachment_2_file_id' => (string) $second->id,
        ]);

        $attachments = $this->resolveAttachments();

        $this->assertCount(2, $attachments);
        $this->assertSame('public', $attachments[0]['disk']);
        $this->assertSame('uploads/welcome/first.pdf', $attachments[0]['storage_path']);
        $this->assertSame('first.pdf', $attachments[0]['name']);
        $this->assertSame('uploads/welcome/second.pdf', $attachments[1]['storage_path']);
    }

    public function test_skips_missing_membership_welcome_uploaded_file_attachment(): void
    {
        config(['filesystems.default' => 'public']);
        Storage::fake('public');

        $available = $this->makeStoredFile('uploads/welcome/available.pdf', 'available');
        $missing = FileModel::create([
            's3_key' => 'uploads/welcome/missing.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
        ]);

        config([
            'membership_welcome.attachment_1_file_id' => (string) $available->id,
            'membership_welcome.attachment_2_file_id' => (string) $missing->id,
        ]);

        $attachments = $this->resolveAttachments();

        $this->assertCount(1, $attachments);
        $this->assertSame('uploads/welcome/available.pdf', $attachments[0]['storage_path']);
        $this->assertTrue($missing->refresh()->is_orphaned);
    }

    private function makeStoredFile(string $path, string $contents): FileModel
    {
        Storage::disk('public')->put($path, $contents);

        return FileModel::create([
            's3_key' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($contents),
        ]);
    }

    private function resolveAttachments(): array
    {
        $service = app(MembershipWelcomeEmailService::class);
        $method = new ReflectionMethod($service, 'resolveAttachments');
        $method->setAccessible(true);

        return $method->invoke($service);
    }
}
