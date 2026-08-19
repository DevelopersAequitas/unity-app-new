<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\MediaProcessingException;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Models\FileModel;
use App\Models\Post;
use App\Models\User;
use App\Services\Creative\IntroducedPeerCreativeGenerator;
use App\Services\Creative\WearTheBadgeImageGenerator;
use App\Services\Media\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileController extends BaseApiController
{
    public function __construct(
        private readonly FileUploadService $fileUploadService
    ) {}

    /**
     * Serve a file by its UUID.
     */
    public function show(Request $request, string $id)
    {
        try {
            $file = Str::isUuid($id) ? File::find($id) : null;

            if (! $file) {
                $cleanId = ltrim(preg_replace('#^(storage/|public/)+#i', '', $id), '/');
                $baseName = basename($cleanId);
                $candidatePaths = array_values(array_unique([
                    $id,
                    $cleanId,
                    'milestone-badges/'.$cleanId,
                    'ads/'.$cleanId,
                    $baseName,
                    'milestone-badges/'.$baseName,
                    'ads/'.$baseName,
                    'uploads/'.$baseName,
                ]));

                $disks = array_unique([config('filesystems.default', 'public'), 'public', 'local']);
                $foundDisk = null;
                $foundPath = null;
                $absolutePath = null;

                foreach ($disks as $diskName) {
                    foreach ($candidatePaths as $candidate) {
                        try {
                            if (Storage::disk($diskName)->exists($candidate)) {
                                $foundDisk = $diskName;
                                $foundPath = $candidate;
                                break 2;
                            }
                        } catch (\Throwable $e) {
                        }
                    }
                }

                if (! $foundPath) {
                    foreach ($candidatePaths as $candidate) {
                        $checkPaths = [
                            public_path($candidate),
                            public_path('storage/'.$candidate),
                            storage_path('app/'.$candidate),
                            storage_path('app/public/'.$candidate),
                            storage_path('app/public/milestone-badges/'.$candidate),
                            storage_path('app/public/ads/'.$candidate),
                        ];
                        foreach ($checkPaths as $absPath) {
                            if (is_file($absPath)) {
                                $absolutePath = $absPath;
                                break 2;
                            }
                        }
                    }
                }

                if ($foundDisk && $foundPath) {
                    $rawMime = Storage::disk($foundDisk)->mimeType($foundPath);
                    $mime = $this->resolveMimeType($foundPath, $rawMime);
                    $size = Storage::disk($foundDisk)->size($foundPath);
                    $stream = Storage::disk($foundDisk)->readStream($foundPath);

                    return $this->buildStreamResponse($request, $stream, $size, $mime);
                }

                if ($absolutePath && is_file($absolutePath)) {
                    $rawMime = mime_content_type($absolutePath) ?: null;
                    $mime = $this->resolveMimeType($absolutePath, $rawMime);
                    $size = filesize($absolutePath);
                    $stream = fopen($absolutePath, 'rb');

                    return $this->buildStreamResponse($request, $stream, $size, $mime);
                }

                Log::warning("File API lookup failed: Database record and physical file not found for: {$id}", [
                    'id' => $id,
                    'ip' => $request->ip(),
                    'user_id' => auth()->id() ?? auth('admin')->id(),
                ]);
                abort(404, 'File not found');
            }

            $disk = config('filesystems.default', 'public');

            if (! $file->s3_key || ! Storage::disk($disk)->exists($file->s3_key)) {
                if ($file->s3_key && Storage::disk('public')->exists($file->s3_key)) {
                    $disk = 'public';
                } else {
                    // Try self-healing: check if this file is a timeline creative post that can be regenerated on-the-fly
                    $regenerated = false;
                    try {
                        $post = Post::where('media', 'LIKE', '%"id":"'.$file->id.'"%')
                            ->orWhere('image', 'LIKE', '%'.$file->id.'%')
                            ->first();

                        if ($post) {
                            if ($post->post_type === 'growth_honour' && $post->source_id) {
                                $introducer = User::find($post->source_id);
                                if ($introducer) {
                                    $count = User::where('introduced_by', $introducer->id)->count();
                                    if ($count === 0) {
                                        $count = 1;
                                    }
                                    $fileModel = FileModel::find($file->id);
                                    if ($fileModel) {
                                        $generator = app(IntroducedPeerCreativeGenerator::class);
                                        $generator->generate($introducer, $count, $fileModel);
                                        $regenerated = true;
                                    }
                                }
                            } elseif ($post->post_type === 'welcome' && $post->source_id) {
                                $user = User::find($post->source_id);
                                if ($user) {
                                    $fileModel = FileModel::find($file->id);
                                    if ($fileModel) {
                                        $generator = app(WearTheBadgeImageGenerator::class);
                                        $generator->generate($user, $fileModel);
                                        $regenerated = true;
                                    }
                                }
                            }
                        }
                    } catch (\Throwable $regenEx) {
                        Log::error("File API Self-Healing failed for UUID {$id}: ".$regenEx->getMessage());
                    }

                    if ($regenerated && Storage::disk($disk)->exists($file->s3_key)) {
                        Log::info("File API Self-Healing succeeded: Regenerated missing physical file for UUID: {$id}");
                    } else {
                        try {
                            if (Schema::hasColumn('files', 'is_orphaned') && ! $file->is_orphaned) {
                                $file->is_orphaned = true;
                                $file->save();
                            }
                        } catch (\Throwable $dbEx) {
                            Log::warning("Could not mark file {$id} as orphaned: ".$dbEx->getMessage());
                        }

                        Log::warning("File API lookup failed: Physical file missing in storage for UUID: {$id}", [
                            'uuid' => $id,
                            's3_key' => $file->s3_key,
                            'disk' => $disk,
                            'ip' => $request->ip(),
                            'user_id' => auth()->id() ?? auth('admin')->id(),
                        ]);
                        abort(404, 'File not found');
                    }
                }
            }

            $rawMime = $file->mime_type ?: Storage::disk($disk)->mimeType($file->s3_key);
            $mime = $this->resolveMimeType($file->s3_key, $rawMime);
            $size = $file->size_bytes ?: Storage::disk($disk)->size($file->s3_key);
            $stream = Storage::disk($disk)->readStream($file->s3_key);

            return $this->buildStreamResponse($request, $stream, $size, $mime);
        } catch (\Throwable $e) {
            Log::error("File API error for UUID {$id}: ".$e->getMessage(), [
                'uuid' => $id,
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    public function upload(Request $request)
    {
        $filesInput = $request->file('file');

        if (is_array($filesInput)) {
            $request->validate([
                'file' => ['required', 'array'],
                'file.*' => ['file', 'max:51200'],
            ]);

            $uploaded = [];

            foreach ($filesInput as $file) {
                if (! $file instanceof UploadedFile || ! $file->isValid()) {
                    continue;
                }

                $result = $this->processSingleUpload($file, $request);

                if ($result instanceof JsonResponse) {
                    return $result;
                }

                $uploaded[] = $result;
            }

            return $this->success($uploaded, 'Files uploaded successfully.', 201);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:51200'],
        ]);

        if (! $filesInput instanceof UploadedFile) {
            return $this->error('Invalid file uploaded.', 422);
        }

        $resource = $this->processSingleUpload($filesInput, $request);

        if ($resource instanceof JsonResponse) {
            return $resource;
        }

        return $this->success($resource, 'File uploaded successfully', 201);
    }

    private function processSingleUpload(UploadedFile $file, Request $request)
    {
        try {
            $model = $this->fileUploadService->store($file, $request->user());
        } catch (MediaProcessingException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('File upload failed', ['error' => $e->getMessage()]);

            return $this->error('File upload failed. Please try again.', 500);
        }

        return new FileResource($model);
    }

    private function resolveMimeType(string $pathOrName, ?string $fallbackMime): string
    {
        $extension = strtolower(pathinfo($pathOrName, PATHINFO_EXTENSION));

        $extensionMap = [
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            'm4v' => 'video/x-m4v',
            'ogv' => 'video/ogg',
            'avi' => 'video/x-msvideo',
            'mkv' => 'video/x-matroska',
            '3gp' => 'video/3gpp',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'aac' => 'audio/aac',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];

        if (isset($extensionMap[$extension])) {
            return $extensionMap[$extension];
        }

        if ($fallbackMime && $fallbackMime !== 'application/octet-stream' && $fallbackMime !== 'text/html') {
            return $fallbackMime;
        }

        return 'application/octet-stream';
    }

    private function buildStreamResponse(Request $request, $stream, int $fileSize, string $mime)
    {
        if (! is_resource($stream)) {
            abort(404, 'File resource unreadable');
        }

        $rangeHeader = $request->header('Range');

        if ($rangeHeader && preg_match('/bytes=\s*(\d*)\s*-\s*(\d*)/i', (string) $rangeHeader, $matches)) {
            $rawStart = $matches[1];
            $rawEnd = $matches[2];

            if ($rawStart === '' && $rawEnd !== '') {
                $suffixLength = (int) $rawEnd;
                $start = max(0, $fileSize - $suffixLength);
                $end = $fileSize - 1;
            } elseif ($rawStart !== '' && $rawEnd === '') {
                $start = (int) $rawStart;
                $end = $fileSize - 1;
            } else {
                $start = (int) $rawStart;
                $end = (int) $rawEnd;
            }

            if ($start > $end || $start >= $fileSize) {
                if (is_resource($stream)) {
                    fclose($stream);
                }

                return response('', 416, [
                    'Content-Range' => "bytes */{$fileSize}",
                    'Accept-Ranges' => 'bytes',
                ]);
            }

            $end = min($end, $fileSize - 1);
            $length = $end - $start + 1;

            $headers = [
                'Content-Type' => $mime,
                'Accept-Ranges' => 'bytes',
                'Content-Range' => "bytes {$start}-{$end}/{$fileSize}",
                'Content-Length' => (string) $length,
                'Cache-Control' => 'no-cache, must-revalidate',
            ];

            if ($request->isMethod('HEAD')) {
                if (is_resource($stream)) {
                    fclose($stream);
                }

                return response('', 206, $headers);
            }

            return response()->stream(function () use ($stream, $start, $length) {
                try {
                    if ($start > 0) {
                        if (@fseek($stream, $start) !== 0) {
                            $discardLeft = $start;
                            while ($discardLeft > 0 && ! feof($stream)) {
                                $discardSize = min(64 * 1024, $discardLeft);
                                $read = fread($stream, $discardSize);
                                if ($read === false || $read === '') {
                                    break;
                                }
                                $discardLeft -= strlen($read);
                            }
                        }
                    }

                    $bytesLeft = $length;
                    $bufferSize = 64 * 1024;

                    while ($bytesLeft > 0 && ! feof($stream)) {
                        $readSize = min($bufferSize, $bytesLeft);
                        $buffer = fread($stream, $readSize);
                        if ($buffer === false || $buffer === '') {
                            break;
                        }
                        echo $buffer;
                        flush();
                        $bytesLeft -= strlen($buffer);
                    }
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }, 206, $headers);
        }

        $headers = [
            'Content-Type' => $mime,
            'Accept-Ranges' => 'bytes',
            'Content-Length' => (string) $fileSize,
            'Cache-Control' => 'no-cache, must-revalidate',
        ];

        if ($request->isMethod('HEAD')) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            return response('', 200, $headers);
        }

        return response()->stream(function () use ($stream) {
            try {
                fpassthru($stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }, 200, $headers);
    }
}
