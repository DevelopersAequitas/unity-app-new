<?php

namespace App\Services\Leadership;

use App\Models\Circle;
use App\Models\LeadershipGroupMember;
use App\Models\LeadershipGroupMessage;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LeadershipGroupChatService
{
    public function getMessages(Circle $circle, User $user, int $perPage = 20): ?LengthAwarePaginator
    {
        if (! $this->isActiveMember($circle, $user)) {
            return null;
        }

        $perPage = max(1, min($perPage, 100));

        $paginator = LeadershipGroupMessage::query()
            ->where('circle_id', $circle->id)
            ->whereNull('deleted_at')
            ->with([
                'sender',
                'reads' => function ($query) use ($user): void {
                    $query->where('user_id', $user->id);
                },
            ])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $replyIds = collect($paginator->items())
            ->pluck('reply_to_message_id')
            ->filter()
            ->unique()
            ->values();

        $replyMessages = $replyIds->isEmpty()
            ? collect()
            : LeadershipGroupMessage::query()
                ->where('circle_id', $circle->id)
                ->whereNull('deleted_at')
                ->whereIn('id', $replyIds)
                ->with('sender')
                ->get()
                ->keyBy('id');

        foreach ($paginator->items() as $message) {
            $message->setRelation('replyTo', $replyMessages->get($message->reply_to_message_id));
        }

        return $paginator;
    }

    public function sendMessage(Circle $circle, User $user, array $data): ?LeadershipGroupMessage
    {
        if (! $this->isActiveMember($circle, $user)) {
            return null;
        }

        $message = DB::transaction(function () use ($circle, $user, $data): LeadershipGroupMessage {
            return LeadershipGroupMessage::query()->create([
                'circle_id' => $circle->id,
                'sender_user_id' => $user->id,
                'message_type' => $data['message_type'],
                'message_text' => $data['message_text'],
                'reply_to_message_id' => $data['reply_to_message_id'] ?? null,
                'meta' => null,
            ]);
        });

        return $message->load('sender');
    }

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
