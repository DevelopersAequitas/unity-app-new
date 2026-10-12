<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leadership;

use App\Services\Leadership\CreativeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CreativeController extends LeadershipBaseController
{
    public function __construct(
        protected CreativeService $creativeService
    ) {}

    /**
     * K1. List available creative templates.
     */
    public function templates(): JsonResponse
    {
        $templates = $this->creativeService->listTemplates();

        return $this->success($templates, 'Creative templates fetched successfully.');
    }

    /**
     * K2. Generate winner creative.
     */
    public function generate(Request $request, string $id): JsonResponse
    {
        try {
            $creative = $this->creativeService->generateCreative($id, $request->all(), Auth::id());

            return $this->success([
                'creative_id' => $creative->id,
                'generation_status' => $creative->generation_status,
            ], 'Creative generation queued.', 202);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * K3. List creative versions.
     */
    public function index(string $id): JsonResponse
    {
        $creatives = $this->creativeService->listCreatives($id);

        return $this->success($creatives, 'Creatives fetched successfully.');
    }

    /**
     * K4. Download creative.
     */
    public function download(string $id): JsonResponse
    {
        try {
            $url = $this->creativeService->getDownloadUrl($id);

            return $this->success(['download_url' => $url], 'Download URL generated successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * K5. Publish creative.
     */
    public function publish(string $id): JsonResponse
    {
        try {
            $creative = $this->creativeService->publishCreative($id, Auth::id());

            return $this->success($creative, 'Creative published successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
