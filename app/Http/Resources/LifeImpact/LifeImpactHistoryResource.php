<?php

namespace App\Http\Resources\LifeImpact;

use App\Models\BusinessDeal;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class LifeImpactHistoryResource extends JsonResource
{
    public function toArray($request): array
    {
        $performedBy = $this->triggeredByUser ?: $this->user;
        $activityDetails = $this->resolveActivityDetailsFromSnapshotOrModel();
        $affectedUserId = (string) ($activityDetails['to_user_id'] ?? $activityDetails['affected_user_id'] ?? '');
        $affectedUser = $affectedUserId !== ''
            ? User::query()
                ->select(['id', 'first_name', 'last_name', 'display_name', 'email'])
                ->find($affectedUserId)
            : null;

        return [
            'id' => (string) $this->id,
            'activity_type' => (string) $this->activity_type,
            'impact_value' => $this->resolveImpactValue(),
            'title' => (string) $this->title,
            'description' => $this->description,
            'status' => (string) ($this->status ?? 'active'),
            'impact_direction' => (string) ($this->impact_direction ?? ($this->resolveImpactValue() < 0 ? 'debit' : 'credit')),
            'performed_by' => $performedBy ? [
                'id' => (string) $performedBy->id,
                'first_name' => $performedBy->first_name,
                'last_name' => $performedBy->last_name,
                'email' => $performedBy->email,
                'life_impacted_count' => (int) ($performedBy->life_impacted_count ?? 0),
            ] : null,
            'affected_user' => $affectedUser ? [
                'id' => (string) $affectedUser->id,
                'first_name' => $affectedUser->first_name,
                'last_name' => $affectedUser->last_name,
                'email' => $affectedUser->email,
            ] : null,
            'activity_details' => $activityDetails,
            'activity_id' => $this->activity_id ? (string) $this->activity_id : null,
            'reversed_from_history_id' => $this->reversed_from_history_id ? (string) $this->reversed_from_history_id : null,
            'triggered_by_user' => $this->whenLoaded('triggeredByUser', function () {
                return [
                    'id' => (string) $this->triggeredByUser->id,
                    'first_name' => $this->triggeredByUser->first_name,
                    'last_name' => $this->triggeredByUser->last_name,
                    'display_name' => $this->triggeredByUser->display_name,
                ];
            }),
            'created_at' => $this->created_at,
        ];
    }

    private function resolveActivityDetailsFromSnapshotOrModel(): array
    {
        $snapshot = is_array($this->activity_snapshot)
            ? $this->activity_snapshot
            : (is_array($this->meta) ? $this->meta : []);

        if ((string) $this->activity_type === 'business_deal' || (string) $this->activity_type === 'business_deal_deleted') {
            $live = null;
            if (! empty($this->activity_id)) {
                $live = BusinessDeal::query()
                    ->withTrashed()
                    ->find((string) $this->activity_id);
            }

            if ($live) {
                $snapshot = array_merge([
                    'deal_id' => (string) $live->id,
                    'deal_date' => $live->deal_date,
                    'deal_amount' => $live->deal_amount,
                    'business_type' => $live->business_type,
                    'comment' => $live->comment,
                    'to_user_id' => $live->to_user_id ? (string) $live->to_user_id : null,
                    'deleted' => (bool) ($live->is_deleted || $live->deleted_at !== null),
                    'deleted_at' => $live->deleted_at?->toISOString(),
                ], $snapshot);
            }
        }

        return $snapshot;
    }
}
