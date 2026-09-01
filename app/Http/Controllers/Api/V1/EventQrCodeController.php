<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EventRegistration;
use App\Services\Events\EventQrService;
use App\Services\Events\EventRegistrationQrService;
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

        // 1. Try looking up registration first to ensure we use exact DB qr_token
        $registration = EventRegistration::query()->find($base)
            ?? EventRegistration::query()->where('event_id', $eventId)->where('id', $base)->first()
            ?? EventRegistration::query()->where('qr_token', $base)->first();

        if ($registration) {
            // Ensure registration has valid QR token and generated files synchronized with DB qr_token
            $registration = app(EventRegistrationQrService::class)->ensureQrGenerated($registration);
            $token = (string) $registration->qr_token;

            if ($isExplicitSvg && ! empty($registration->qr_code_svg)) {
                return response((string) $registration->qr_code_svg, 200, [
                    'Content-Type' => 'image/svg+xml',
                    'Cache-Control' => 'no-cache, private',
                ]);
            }

            $genPath = storage_path('app/public/'.($registration->qr_code_path ?: ('event-qrcodes/'.$eventId.'/'.$registration->id.'.png')));
            if (is_file($genPath)) {
                $header = file_get_contents($genPath, false, null, 0, 8);
                if ($header !== false && str_starts_with($header, "\x89PNG")) {
                    if ($genPath !== $pngPath && ! is_file($pngPath)) {
                        @copy($genPath, $pngPath);
                    }

                    return response()->file($genPath, ['Content-Type' => 'image/png']);
                }
            }
        } else {
            $token = $base;
            if ($isExplicitSvg && is_file($svgPath)) {
                return response()->file($svgPath, ['Content-Type' => 'image/svg+xml']);
            }
            if (is_file($pngPath)) {
                $header = file_get_contents($pngPath, false, null, 0, 8);
                if ($header !== false && str_starts_with($header, "\x89PNG")) {
                    return response()->file($pngPath, ['Content-Type' => 'image/png']);
                }
                @unlink($pngPath);
            }
        }

        // 2. Fail-safe stream generation using verified token
        try {
            $qrService = app(EventQrService::class);
            $payload = $qrService->payload($token);

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
