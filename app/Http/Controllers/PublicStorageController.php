<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FileModel;
use App\Models\IntroductionCreative;
use App\Models\User;
use App\Services\Creative\IntroducedPeerCreativeGenerator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
            storage_path('app/private/'.$cleanPath),
            storage_path('app/'.$cleanPath),
            public_path('storage/'.$cleanPath),
            public_path($cleanPath),
        ];

        $foundFile = null;
        foreach ($candidatePaths as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                $foundFile = $candidate;
                break;
            }
        }

        // Self-healing fallback: If it's a creative/upload/badge file that hasn't been generated yet on this instance
        if (! $foundFile && (str_contains($cleanPath, 'uploads/') || str_contains($cleanPath, 'growth_creative') || str_contains($cleanPath, 'creative') || str_contains($cleanPath, 'badge') || str_contains($cleanPath, 'milestone'))) {
            $filename = basename($cleanPath);
            $uuid = pathinfo($filename, PATHINFO_FILENAME);

            $user = null;
            $introducedCount = $request->query('c') ? (int) $request->query('c') : ($request->query('count') ? (int) $request->query('count') : null);

            $hasUsersTable = Schema::hasTable('users');

            // 1. Check explicit uid / user_id in query parameters
            $queryUserId = $request->query('uid') ?? $request->query('user_id');
            if ($hasUsersTable && $queryUserId && Str::isUuid((string) $queryUserId)) {
                $user = User::find($queryUserId);
            }

            // 2. Check phone parameter in query parameters
            if ($hasUsersTable && ! $user && $request->filled('phone')) {
                $phone = preg_replace('/\D+/', '', (string) $request->query('phone'));
                if ($phone !== '') {
                    $user = User::where('phone', 'LIKE', '%'.$phone.'%')
                        ->orWhere('secondary_mobile', 'LIKE', '%'.$phone.'%')
                        ->first();
                }
            }

            // 3. Extract any UUID present in the path
            if ($hasUsersTable && ! $user && preg_match_all('/([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})/', $cleanPath, $matches)) {
                foreach ($matches[1] as $candidateUuid) {
                    $foundUser = User::find($candidateUuid);
                    if ($foundUser) {
                        $user = $foundUser;
                        break;
                    }
                }
            }

            // 4. Check user creative columns
            if ($hasUsersTable && ! $user && Str::isUuid($uuid)) {
                $userQuery = User::query()->where('id', $uuid);

                if (Schema::hasColumn('users', 'welcome_creative_url')) {
                    $userQuery->orWhere('welcome_creative_url', 'LIKE', '%'.$uuid.'%');
                }
                if (Schema::hasColumn('users', 'profile_card_image_url')) {
                    $userQuery->orWhere('profile_card_image_url', 'LIKE', '%'.$uuid.'%');
                }
                if (Schema::hasColumn('users', 'connector_creative_url')) {
                    $userQuery->orWhere('connector_creative_url', 'LIKE', '%'.$uuid.'%');
                }
                if (Schema::hasColumn('users', 'growth_creative_url')) {
                    $userQuery->orWhere('growth_creative_url', 'LIKE', '%'.$uuid.'%');
                }

                $user = $userQuery->first();
            }

            // 5. Check introduction_creatives table
            if (! $user && Schema::hasTable('introduction_creatives')) {
                $creative = IntroductionCreative::query()
                    ->where('image_url', 'LIKE', '%'.$uuid.'%')
                    ->orWhere('image_url', 'LIKE', '%'.$cleanPath.'%')
                    ->first();

                if ($creative) {
                    $user = $creative->introducer;
                    if ($introducedCount === null) {
                        $introducedCount = $creative->introduced_count;
                    }
                }
            }

            // 6. Check files table
            if (! $user && Str::isUuid($uuid) && Schema::hasTable('files')) {
                $fileRecord = FileModel::where('id', $uuid)
                    ->orWhere('s3_key', 'LIKE', '%'.$uuid.'%')
                    ->orWhere('s3_key', $cleanPath)
                    ->first();

                if ($fileRecord && $fileRecord->uploader_user_id) {
                    $user = User::find($fileRecord->uploader_user_id);
                }
            }

            // 7. If user is still not in DB, create virtual user instance only if explicit user params were provided
            if (! $user && ($request->filled('uid') || $request->filled('user_id') || $request->filled('phone') || $request->filled('name'))) {
                $rawName = $request->query('name') ?? $request->query('peer_name') ?? $request->query('member_name') ?? 'Peer Member';
                $virtualUser = new User;
                $virtualUser->id = (string) Str::uuid();
                $virtualUser->first_name = (string) $rawName;
                $virtualUser->display_name = (string) $rawName;
                $virtualUser->company_name = (string) $request->query('company', '');
                $virtualUser->city = (string) $request->query('city', '');
                $virtualUser->business_sub_category = (string) $request->query('category', '');
                $virtualUser->members_introduced_count = $introducedCount ?? (str_contains(strtolower($cleanPath), 'catalyst') ? 3 : 1);
                $user = $virtualUser;
            }

            if ($user) {
                if ($introducedCount === null) {
                    $introducedCount = (int) ($user->members_introduced_count ?: (str_contains(strtolower($cleanPath), 'catalyst') ? 3 : 1));
                }

                try {
                    $generator = app(IntroducedPeerCreativeGenerator::class);
                    $fileModel = new FileModel;
                    $fileModel->id = (string) Str::uuid();
                    $fileModel->s3_key = $cleanPath;
                    $generator->generate($user, (int) $introducedCount, $fileModel);

                    foreach ($candidatePaths as $candidate) {
                        if (is_file($candidate) && is_readable($candidate)) {
                            $foundFile = $candidate;
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning("[PublicStorageController] Self-healing creative generation failed: {$e->getMessage()}");
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
