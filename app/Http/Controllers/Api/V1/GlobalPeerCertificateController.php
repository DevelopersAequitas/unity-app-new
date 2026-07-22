<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Creative\GlobalPeerCertificateImageGenerator;
use App\Services\Creative\GlobalPeerCertificateImageGenerator as CertGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GlobalPeerCertificateController extends Controller
{
    public function __construct(
        private readonly CertGenerator $generator
    ) {}

    /**
     * GET /api/v1/my/global-peer-certificate
     *
     * Returns the certificate image URL for the authenticated paid-tier peer.
     * Generates and caches the image on first call; subsequent calls return
     * the already-stored file URL instantly.
     *
     * Response (200):
     * {
     *   "certificate_url": "https://...",
     *   "file_id": "uuid",
     *   "peer_name": "John Doe",
     *   "membership_status": "Only Unity Peer",
     *   "generated_at": "2026-07-21T12:00:00.000000Z"
     * }
     *
     * Response (403):
     * { "message": "Certificate is only available for paid Global Peers." }
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Guard: only paid-tier peers may access the certificate
        if (! $this->isPaidPeer($user)) {
            return response()->json([
                'message' => 'Certificate is only available for paid Global Peers.',
            ], 403);
        }

        // Return cached file if already generated
        if (filled($user->global_peer_certificate_file_id)) {
            $fileId = $user->global_peer_certificate_file_id;
            $url = url("/api/v1/files/{$fileId}");

            return response()->json([
                'certificate_url' => $url,
                'file_id' => $fileId,
                'peer_name' => $this->displayName($user),
                'membership_status' => $user->membership_status,
                'is_downloadable' => $this->isDownloadable($user),
                'generated_at' => $user->global_peer_certificate_sent_at,
            ]);
        }

        // Generate fresh certificate
        try {
            $fileModel = $this->generator->generate($user);
            $url = url("/api/v1/files/{$fileModel->id}");

            // Persist the file ID and timestamp on the user row
            $user->forceFill([
                'global_peer_certificate_file_id' => $fileModel->id,
                'global_peer_certificate_sent_at' => now(),
            ])->save();

            Log::info('GlobalPeerCertificate: Generated certificate for user '.$user->id, [
                'file_id' => $fileModel->id,
            ]);

            return response()->json([
                'certificate_url' => $url,
                'file_id' => $fileModel->id,
                'peer_name' => $this->displayName($user),
                'membership_status' => $user->membership_status,
                'is_downloadable' => $this->isDownloadable($user),
                'generated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('GlobalPeerCertificate: Failed to generate certificate for user '.$user->id.': '.$e->getMessage());

            return response()->json([
                'message' => 'Failed to generate certificate. Please try again.',
            ], 500);
        }
    }

    /**
     * POST /api/v1/my/global-peer-certificate/regenerate
     *
     * Force-regenerates the certificate (e.g., after a name change).
     */
    public function regenerate(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $this->isPaidPeer($user)) {
            return response()->json([
                'message' => 'Certificate is only available for paid Global Peers.',
            ], 403);
        }

        try {
            $fileModel = $this->generator->generate($user);
            $url = url("/api/v1/files/{$fileModel->id}");

            $user->forceFill([
                'global_peer_certificate_file_id' => $fileModel->id,
                'global_peer_certificate_sent_at' => now(),
            ])->save();

            Log::info('GlobalPeerCertificate: Regenerated certificate for user '.$user->id, [
                'file_id' => $fileModel->id,
            ]);

            return response()->json([
                'certificate_url' => $url,
                'file_id' => $fileModel->id,
                'peer_name' => $this->displayName($user),
                'membership_status' => $user->membership_status,
                'is_downloadable' => $this->isDownloadable($user),
                'generated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('GlobalPeerCertificate: Regenerate failed for user '.$user->id.': '.$e->getMessage());

            return response()->json([
                'message' => 'Failed to regenerate certificate. Please try again.',
            ], 500);
        }
    }

    private function isPaidPeer(User $user): bool
    {
        return in_array($user->membership_status, GlobalPeerCertificateImageGenerator::PAID_STATUSES, true);
    }

    private function isDownloadable(User $user): bool
    {
        return \App\Models\CircleMember::where('user_id', $user->id)
            ->where('status', 'approved')
            ->exists();
    }

    private function displayName(User $user): string
    {
        return trim($user->display_name ?: (($user->first_name ?? '').' '.($user->last_name ?? '')));
    }
}
