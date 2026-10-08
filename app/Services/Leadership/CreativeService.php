<?php

declare(strict_types=1);

namespace App\Services\Leadership;

use App\Models\Leadership\LeadershipFinalDecision;
use App\Models\Leadership\LeadershipWinnerCreative;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CreativeService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * List available creative templates (K1).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listTemplates(): array
    {
        return [
            [
                'id' => 'template-classic-navy',
                'name' => 'Classic Navy Executive',
                'supported_fields' => ['headline', 'quote', 'role', 'scope', 'campaign_year'],
                'dimensions' => '1080x1080',
                'format' => 'png',
                'preview_url' => '/images/creatives/template-classic-navy.png',
                'is_active' => true,
            ],
            [
                'id' => 'template-gold-summit',
                'name' => 'Gold Summit Winner',
                'supported_fields' => ['headline', 'quote', 'role', 'scope', 'company_name'],
                'dimensions' => '1080x1080',
                'format' => 'png',
                'preview_url' => '/images/creatives/template-gold-summit.png',
                'is_active' => true,
            ],
        ];
    }

    /**
     * Queue/generate winner creative (K2).
     *
     * @param  array<string, mixed>  $data
     */
    public function generateCreative(string $decisionId, array $data, ?string $userId = null): LeadershipWinnerCreative
    {
        $decision = LeadershipFinalDecision::with(['nomination.campaign.role', 'nomination.scope'])->findOrFail($decisionId);

        if (! $decision->is_winner) {
            throw new RuntimeException('Creatives can only be generated for selected winners.');
        }

        $latestVersion = (int) LeadershipWinnerCreative::where('final_decision_id', $decision->id)->max('version');

        /** @var LeadershipWinnerCreative $creative */
        $creative = LeadershipWinnerCreative::create([
            'final_decision_id' => $decision->id,
            'template_name' => $data['template_id'] ?? 'template-classic-navy',
            'version' => $latestVersion + 1,
            'storage_disk' => 'local',
            'generation_status' => 'processing',
            'publication_status' => 'draft',
            'generated_by' => ($userId && \App\Models\User::where('id', $userId)->exists()) ? (string) $userId : null,
            'generated_at' => Carbon::now(),
        ]);

        // Asynchronous job or direct handler:
        // We set status to completed with a placeholder path for immediate availability
        $filePath = "creatives/{$decision->id}/winner_v{$creative->version}.png";
        $creative->update([
            'file_path' => $filePath,
            'preview_path' => $filePath,
            'generation_status' => 'completed',
        ]);

        $this->auditService->log(
            action: 'creative.generated',
            entityType: 'LeadershipWinnerCreative',
            entityId: $creative->id,
            campaignId: $decision->campaign_id,
            remarks: "Winner creative generated version {$creative->version}"
        );

        return $creative->fresh();
    }

    /**
     * List creative versions for decision (K3).
     *
     * @return Collection<int, LeadershipWinnerCreative>
     */
    public function listCreatives(string $decisionId): Collection
    {
        return LeadershipWinnerCreative::query()
            ->with('generator')
            ->where('final_decision_id', $decisionId)
            ->orderBy('version', 'desc')
            ->get();
    }

    /**
     * Download or retrieve short-lived link for creative (K4).
     */
    public function getDownloadUrl(string $creativeId): string
    {
        /** @var LeadershipWinnerCreative $creative */
        $creative = LeadershipWinnerCreative::findOrFail($creativeId);

        if (! $creative->file_path) {
            throw new RuntimeException('Creative file is still generating or unavailable.');
        }

        if (Storage::disk($creative->storage_disk)->exists($creative->file_path)) {
            return Storage::disk($creative->storage_disk)->temporaryUrl($creative->file_path, now()->addMinutes(15));
        }

        return url('/storage/'.$creative->file_path);
    }

    /**
     * Publish creative (K5).
     */
    public function publishCreative(string $creativeId, ?string $userId = null): LeadershipWinnerCreative
    {
        /** @var LeadershipWinnerCreative $creative */
        $creative = LeadershipWinnerCreative::with('finalDecision')->findOrFail($creativeId);

        $creative->update([
            'publication_status' => 'published',
            'published_at' => Carbon::now(),
        ]);

        $this->auditService->log(
            action: 'creative.published',
            entityType: 'LeadershipWinnerCreative',
            entityId: $creative->id,
            campaignId: $creative->finalDecision?->campaign_id,
            remarks: 'Winner creative published'
        );

        return $creative->fresh();
    }
}
