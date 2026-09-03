<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FileModel;
use App\Models\User;
use App\Services\Creative\IntroducedPeerCreativeGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicStorageController extends Controller
{
    /**
     * Publicly serve static storage assets without authentication, session, or token requirements.
     */
    public function serve(Request $request, string $path): Response|BinaryFileResponse
    {
        $cleanPath = ltrim(preg_replace('#^(storage/|public/)+#i', '', $path), '/');

        // Prevent path traversal
        if (str_contains($cleanPath, '..')) {
            abort(404, 'File not found');
        }

        // Check potential file locations on disk
        $candidatePaths = [
            storage_path('app/public/'.$cleanPath),
            public_path('storage/'.$cleanPath),
            public_path($cleanPath),
            storage_path('app/'.$cleanPath),
        ];

        $foundFile = null;
        foreach ($candidatePaths as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                $foundFile = $candidate;
                break;
            }
        }

        // Self-healing fallback: If it's a creative file that hasn't been generated yet on this instance
        if (! $foundFile && (str_contains($cleanPath, 'uploads/') || str_contains($cleanPath, 'growth_creative') || str_contains($cleanPath, 'creative'))) {
            $filename = basename($cleanPath);
            $uuid = pathinfo($filename, PATHINFO_FILENAME);

            if (Str::isUuid($uuid)) {
                $user = User::query()
                    ->where('id', $uuid)
                    ->orWhere('welcome_creative_url', 'LIKE', '%'.$uuid.'%')
                    ->orWhere('profile_card_image_url', 'LIKE', '%'.$uuid.'%')
                    ->first();

                if ($user) {
                    try {
                        $generator = app(IntroducedPeerCreativeGenerator::class);
                        $fileModel = new FileModel;
                        $fileModel->id = (string) Str::uuid();
                        $fileModel->s3_key = $cleanPath;
                        $generator->generate($user, (int) ($user->members_introduced_count ?: 1), $fileModel);

                        $candidate = storage_path('app/public/'.$cleanPath);
                        if (is_file($candidate)) {
                            $foundFile = $candidate;
                        }
                    } catch (\Throwable $e) {
                        Log::warning("[PublicStorageController] Self-healing creative generation failed: {$e->getMessage()}");
                    }
                }
            }
        }

        if (! $foundFile || ! is_file($foundFile)) {
            abort(404, 'File not found');
        }

        $mime = $this->resolveMimeType($foundFile);
        $size = filesize($foundFile);

        $headers = [
            'Content-Type' => $mime,
            'Content-Length' => (string) $size,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Access-Control-Allow-Origin' => '*',
        ];

        return response()->file($foundFile, $headers);
    }

    private function resolveMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            default => mime_content_type($path) ?: 'application/octet-stream',
        };
    }
}
