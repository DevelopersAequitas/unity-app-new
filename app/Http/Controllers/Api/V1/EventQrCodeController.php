<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EventQrCodeController extends Controller
{
    public function show(string $eventId, string $filename): BinaryFileResponse|JsonResponse
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
                // Try dynamically generating the missing QR image on demand for the registration
                $registration = \App\Models\EventRegistration::query()->find($base);
                if ($registration && ! empty($registration->qr_token)) {
                    try {
                        app(\App\Services\Events\EventQrService::class)->generateAndStore($registration);
                        $registration->refresh();
                        $genPath = storage_path('app/public/'.$registration->qr_code_path);
                        if (is_file($genPath)) {
                            $path = $genPath;
                        }
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error('dynamic_qr_generation_on_controller_failed', [
                            'registration_id' => $base,
                            'error' => $e->getMessage(),
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
