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
