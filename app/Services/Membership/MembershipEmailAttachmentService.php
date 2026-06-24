<?php

namespace App\Services\Membership;

use App\Models\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MembershipEmailAttachmentService
{
    /**
     * @return array<int, array{file_id:string,disk:string,path:string,name:string,mime:string|null,resolved_path:string|null}>
     */
    public function resolve(string $emailType): array
    {
        $fileIds = $this->configuredFileIds();

        if ($fileIds === []) {
            return [];
        }

        $disk = config('filesystems.default', 'public');
        $files = File::query()->whereIn('id', $fileIds)->get()->keyBy('id');
        $attachments = [];

        foreach ($fileIds as $fileId) {
            /** @var File|null $file */
            $file = $files->get($fileId);
            $fileName = $file?->s3_key ? basename((string) $file->s3_key) : null;
            $resolvedPath = $this->resolvedPath($disk, $file?->s3_key);

            if (! $file || $file->is_orphaned || blank($file->s3_key)) {
                Log::warning('Membership email attachment file unavailable', [
                    'attachment_file_id' => $fileId,
                    'file_name' => $fileName,
                    'resolved_path' => $resolvedPath,
                    'email_type' => $emailType,
                    'send_status' => 'skipped_missing_file_record',
                ]);

                continue;
            }

            if (! Storage::disk($disk)->exists($file->s3_key)) {
                Log::warning('Membership email attachment storage object missing', [
                    'attachment_file_id' => (string) $file->id,
                    'file_name' => $fileName,
                    'resolved_path' => $resolvedPath,
                    'email_type' => $emailType,
                    'send_status' => 'skipped_missing_storage_object',
                ]);

                continue;
            }

            $attachments[] = [
                'file_id' => (string) $file->id,
                'disk' => $disk,
                'path' => (string) $file->s3_key,
                'name' => $fileName ?: ((string) $file->id),
                'mime' => $file->mime_type,
                'resolved_path' => $resolvedPath,
            ];

            Log::info('Membership email attachment resolved', [
                'attachment_file_id' => (string) $file->id,
                'file_name' => $fileName,
                'resolved_path' => $resolvedPath,
                'email_type' => $emailType,
                'send_status' => 'resolved',
            ]);
        }

        return $attachments;
    }

    /**
     * @return array<int, string>
     */
    private function configuredFileIds(): array
    {
        return collect((array) config('membership_email_attachments.file_ids', []))
            ->map(static fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function resolvedPath(string $disk, ?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        try {
            return Storage::disk($disk)->path($path);
        } catch (\Throwable) {
            return $path;
        }
    }
}
