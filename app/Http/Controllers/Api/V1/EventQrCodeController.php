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

        if (! is_file($path)) {
            $base = pathinfo($filename, PATHINFO_FILENAME);
            $altExt = str_ends_with(strtolower($filename), '.png') ? '.svg' : '.png';
            $altPath = $dir.'/'.$base.$altExt;

            if (is_file($altPath)) {
                $path = $altPath;
            } else {
                // Find registration by ID or qr_token
                $registration = EventRegistration::query()->find($base)
                    ?? EventRegistration::query()->where('qr_token', $base)->first();

                if ($registration) {
                    if (empty($registration->qr_token)) {
                        $registration->forceFill(['qr_token' => app(EventQrService::class)->generateToken()])->save();
                        $registration->refresh();
                    }

                    try {
                        app(EventQrService::class)->generateAndStore($registration);
                        $registration->refresh();
                        $genPath = storage_path('app/public/'.$registration->qr_code_path);
                        if (is_file($genPath)) {
                            $path = $genPath;
                        }
                    } catch (Throwable $e) {
                        Log::error('dynamic_qr_generation_on_controller_failed', [
                            'registration_id' => $base,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    // Fallback to inline DB SVG string if file not created on disk
                    if (! is_file($path) && ! empty($registration->qr_code_svg)) {
                        return response($registration->qr_code_svg, 200, [
                            'Content-Type' => 'image/svg+xml',
                        ]);
                    }
                }
            }

            if (! is_file($path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR code image not found.',
                ], 404);
            }
        }

        $contentType = str_ends_with(strtolower($path), '.svg') ? 'image/svg+xml' : 'image/png';

        return response()->file($path, [
            'Content-Type' => $contentType,
        ]);
    }
}
