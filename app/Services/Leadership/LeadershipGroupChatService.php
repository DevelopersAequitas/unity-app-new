<?php

namespace App\Services\Leadership;

use App\Models\Circle;
use App\Models\LeadershipGroupMember;
use App\Models\LeadershipGroupMessage;
use App\Models\User;
use Illuminate\Database\Query\Builder;

class LeadershipGroupChatService
{
    public function getMembersPayload(Circle $circle, User $user): ?array
    {
        if (! $this->isActiveMember($circle, $user)) {
            return null;
        }

        $membersQuery = LeadershipGroupMember::query()
            ->where('circle_id', $circle->id)
            ->where('is_active', true)
            ->whereNull('deleted_at');

        $members = (clone $membersQuery)
            ->with('user')
            ->orderByRaw($this->roleOrderExpression())
            ->orderBy('created_at')
            ->get();

        $totalMessages = LeadershipGroupMessage::query()
            ->where('circle_id', $circle->id)
            ->whereNull('deleted_at')
            ->count();

        $unreadCount = LeadershipGroupMessage::query()
            ->where('circle_id', $circle->id)
            ->whereNull('deleted_at')
            ->where('sender_user_id', '!=', $user->id)
            ->whereNotExists(function (Builder $query) use ($user): void {
                $query->selectRaw('1')
                    ->from('leadership_group_message_reads')
                    ->whereColumn('leadership_group_message_reads.message_id', 'leadership_group_messages.id')
                    ->where('leadership_group_message_reads.user_id', $user->id);
            })
            ->count();

        return [
            'circle' => [
                'id' => $circle->id,
                'name' => $circle->name,
                'slug' => $circle->slug,
            ],
            'chat' => [
                'type' => 'leadership_group',
                'circle_id' => $circle->id,
                'total_members' => $members->count(),
                'total_messages' => $totalMessages,
                'unread_count' => $unreadCount,
            ],
            'current_user' => [
                'id' => $user->id,
                'is_leadership_member' => true,
                'can_send_message' => true,
            ],
            'members' => $members,
        ];
    }

    private function isActiveMember(Circle $circle, User $user): bool
    {
        return LeadershipGroupMember::query()
            ->where('circle_id', $circle->id)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->exists();
    }

    private function roleOrderExpression(): string
    {
        return "CASE leader_role
            WHEN 'founder' THEN 1
            WHEN 'director' THEN 2
            WHEN 'industry_director' THEN 3
            ELSE 4
        END";
    }
}
