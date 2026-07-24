<?php

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
        $path = $dir.'/'.$filename;

        // 1. Return physical disk file if present
        if (is_file($path)) {
            $contentType = str_ends_with(strtolower($path), '.svg') ? 'image/svg+xml' : 'image/png';

            return response()->file($path, ['Content-Type' => $contentType]);
        }

        $base = pathinfo($filename, PATHINFO_FILENAME);
        $altExt = str_ends_with(strtolower($filename), '.png') ? '.svg' : '.png';
        $altPath = $dir.'/'.$base.$altExt;

        if (is_file($altPath)) {
            $contentType = str_ends_with(strtolower($altPath), '.svg') ? 'image/svg+xml' : 'image/png';

            return response()->file($altPath, ['Content-Type' => $contentType]);
        }

        // 2. Try looking up registration
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

        // 3. Try disk generation
        if ($registration) {
            try {
                app(EventQrService::class)->generateAndStore($registration);
                $registration->refresh();
                if (! empty($registration->qr_code_path)) {
                    $genPath = storage_path('app/public/'.$registration->qr_code_path);
                    if (is_file($genPath)) {
                        $contentType = str_ends_with(strtolower($genPath), '.svg') ? 'image/svg+xml' : 'image/png';

                        return response()->file($genPath, ['Content-Type' => $contentType]);
                    }
                }
            } catch (Throwable $e) {
                Log::error('dynamic_qr_generation_on_controller_failed', ['error' => $e->getMessage()]);
            }
        }

        // 4. Guaranteed Fail-Safe Stream: Generate and return SVG directly to browser
        try {
            $payload = app(EventQrService::class)->payload($token);
            $reflection = new \ReflectionClass(app(EventQrService::class));
            $method = $reflection->getMethod('makeSvg');
            $method->setAccessible(true);
            $svgContent = $method->invoke(app(EventQrService::class), $payload);

            return response($svgContent, 200, [
                'Content-Type' => 'image/svg+xml',
                'Cache-Control' => 'no-cache, private',
            ]);
        } catch (Throwable $e) {
            Log::error('direct_svg_streaming_failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => false,
            'message' => 'QR code image not found.',
        ], 404);
    }
}
