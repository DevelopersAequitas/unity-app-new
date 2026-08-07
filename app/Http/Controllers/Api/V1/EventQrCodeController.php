<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EventRegistration;
use App\Services\Events\EventQrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class EventQrCodeController extends Controller
{
    public function show(string $eventId, string $filename): BinaryFileResponse|JsonResponse|Response
    {
        $dir = storage_path('app/public/event-qrcodes/'.$eventId);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $isExplicitSvg = $ext === 'svg';
        $pngPath = $dir.'/'.$base.'.png';
        $svgPath = $dir.'/'.$base.'.svg';

        // 1. If explicit SVG is requested and SVG file exists
        if ($isExplicitSvg && is_file($svgPath)) {
            return response()->file($svgPath, ['Content-Type' => 'image/svg+xml']);
        }

        // 2. Return physical PNG disk file if present and valid
        if (is_file($pngPath)) {
            $header = file_get_contents($pngPath, false, null, 0, 8);
            if ($header !== false && str_starts_with($header, "\x89PNG")) {
                return response()->file($pngPath, ['Content-Type' => 'image/png']);
            }
            @unlink($pngPath);
        }

        // 3. Try looking up registration
        $registration = EventRegistration::query()->find($base)
            ?? EventRegistration::query()->where('event_id', $eventId)->where('id', $base)->first()
            ?? EventRegistration::query()->where('qr_token', $base)->first();

        $token = $registration?->qr_token;

        if (empty($token)) {
            $token = app(EventQrService::class)->generateToken();
            if ($registration) {
                try {
                    $registration->forceFill(['qr_token' => $token])->save();
                } catch (Throwable $e) {
                    Log::error('qr_token_save_failed', ['error' => $e->getMessage()]);
                }
            }
        }

        // 4. Try disk generation of PNG
        if ($registration) {
            try {
                app(EventQrService::class)->generateAndStore($registration);
                $registration->refresh();
                if (! empty($registration->qr_code_path)) {
                    $genPath = storage_path('app/public/'.$registration->qr_code_path);
                    if (is_file($genPath)) {
                        $genHeader = file_get_contents($genPath, false, null, 0, 8);
                        if ($genHeader !== false && str_starts_with($genHeader, "\x89PNG")) {
                            if ($genPath !== $pngPath && ! is_file($pngPath)) {
                                @copy($genPath, $pngPath);
                            }

                            return response()->file($genPath, ['Content-Type' => 'image/png']);
                        }
                    }
                }
            } catch (Throwable $e) {
                Log::error('dynamic_qr_generation_on_controller_failed', ['error' => $e->getMessage()]);
            }
        }

        // 5. Guaranteed Fail-Safe Stream: Generate and return PNG directly
        try {
            $qrService = app(EventQrService::class);
            $payload = $qrService->payload($token ?: $base);

            if ($isExplicitSvg) {
                $svgContent = $qrService->makeSvg($payload);

                return response($svgContent, 200, [
                    'Content-Type' => 'image/svg+xml',
                    'Cache-Control' => 'no-cache, private',
                ]);
            }

            $pngContent = null;
            try {
                $pngContent = $qrService->makePng($payload);
            } catch (Throwable $pngException) {
                Log::error('make_png_failed_trying_imagick_fallback', ['error' => $pngException->getMessage()]);
                $svgContent = is_file($svgPath) ? file_get_contents($svgPath) : ($registration?->qr_code_svg ?? null);
                if (! empty($svgContent) && (extension_loaded('imagick') || class_exists('\Imagick'))) {
                    $imagick = new \Imagick;
                    $imagick->readImageBlob($svgContent);
                    $imagick->setImageFormat('png');
                    $pngContent = $imagick->getImageBlob();
                } else {
                    throw $pngException;
                }
            }

            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            @file_put_contents($pngPath, $pngContent);

            return response($pngContent, 200, [
                'Content-Type' => 'image/png',
                'Cache-Control' => 'no-cache, private',
            ]);
        } catch (Throwable $e) {
            Log::error('direct_qr_streaming_failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => false,
            'message' => 'QR code image not found.',
        ], 404);
    }
}
