<?php

namespace App\Support\Media;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class Probe
{
    public function ffmpegAvailable(): bool
    {
        // Prefer `which` on *nix, fall back to running ffmpeg directly.
        if ($this->binaryAvailable('which', ['ffmpeg'])) {
            return true;
        }

        return $this->binaryAvailable('ffmpeg', ['-version']);
    }

    public function ffprobeAvailable(): bool
    {
        return $this->binaryAvailable('ffprobe');
    }

    public function imagickAvailable(): bool
    {
        return extension_loaded('imagick');
    }

    public function gdAvailable(): bool
    {
        return extension_loaded('gd');
    }

    public function mimeType(string $path): ?string
    {
        $info = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $info->file($path) ?: null;

        return $mime ?: (mime_content_type($path) ?: null);
    }

    public function imageDimensions(string $path): array
    {
        $size = @getimagesize($path);

        return [
            'width' => $size[0] ?? null,
            'height' => $size[1] ?? null,
        ];
    }

    public function videoMetadata(string $path): array
    {
        if (! $this->ffprobeAvailable()) {
            return [
                'width' => null,
                'height' => null,
                'duration' => null,
            ];
        }

        $process = new Process([
            'ffprobe',
            '-v',
            'quiet',
            '-print_format',
            'json',
            '-show_streams',
            '-show_format',
            $path,
        ]);

        $process->run();

        if (! $process->isSuccessful()) {
            return [
                'width' => null,
                'height' => null,
                'duration' => null,
            ];
        }

        $data = json_decode($process->getOutput(), true);

        $videoStream = collect($data['streams'] ?? [])
            ->first(function ($stream): bool {
                return ($stream['codec_type'] ?? null) === 'video';
            }) ?? [];

        $width = $videoStream['width'] ?? null;
        $height = $videoStream['height'] ?? null;

        $duration = $data['format']['duration'] ?? ($videoStream['duration'] ?? null);
        $duration = $duration ? (int) round((float) $duration) : null;

        return [
            'width' => $width,
            'height' => $height,
            'duration' => $duration,
        ];
    }

    public function isImageMime(?string $mime): bool
    {
        return $mime ? Str::startsWith($mime, 'image/') : false;
    }

    public function isVideoMime(?string $mime): bool
    {
        return $mime ? Str::startsWith($mime, 'video/') : false;
    }

    public function isPdfMime(?string $mime): bool
    {
        return $mime === 'application/pdf';
    }

    private function binaryAvailable(string $binary, array $arguments = []): bool
    {
        try {
            $process = new Process(array_filter([$binary, ...$arguments]));
            $process->setTimeout(5);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable) {
            return false;
        }
    }
}
