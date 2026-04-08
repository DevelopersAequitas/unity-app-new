<?php

namespace App\Services\Leadership;

use App\Models\Circle;
use App\Models\LeadershipGroupMember;
use App\Models\LeadershipGroupMessage;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LeadershipGroupChatService
{
    private const CHAT_ALLOWED_ROLES = [
        'founder',
        'director',
        'chair',
        'vice_chair',
        'secretary',
    ];

    public function deleteForMe(Circle $circle, User $user, LeadershipGroupMessage $message): string
    {
        if (! $this->isActiveMember($circle, $user)) {
            throw new HttpException(403, 'Forbidden.');
        }

        if ((string) $message->circle_id !== (string) $circle->id) {
            throw new HttpException(404, 'Message not found.');
        }

        if ($message->deleted_at !== null) {
            throw new HttpException(422, 'Message already deleted for everyone.');
        }

        $now = now();

        DB::table('leadership_group_message_deletions')->upsert(
            [[
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'message_id' => $message->id,
                'user_id' => $user->id,
                'deleted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['message_id', 'user_id'],
            ['deleted_at', 'updated_at']
        );

        return (string) $message->id;
    }

    public function deleteForEveryone(Circle $circle, User $user, LeadershipGroupMessage $message): string
    {
        if (! $this->isActiveMember($circle, $user)) {
            throw new HttpException(403, 'Forbidden.');
        }

        if ((string) $message->circle_id !== (string) $circle->id) {
            throw new HttpException(404, 'Message not found.');
        }

        if ((string) $message->sender_user_id !== (string) $user->id) {
            throw new HttpException(403, 'Only sender can delete this message for everyone.');
        }

        $message->forceFill([
            'deleted_at' => now(),
            'updated_at' => now(),
        ])->save();

        return (string) $message->id;
    }

    public function markMessagesRead(Circle $circle, User $user, array $messageIds): ?int
    {
        if (! $this->isActiveMember($circle, $user)) {
            return null;
        }

        $validMessageIds = LeadershipGroupMessage::query()
            ->where('circle_id', $circle->id)
            ->whereNull('deleted_at')
            ->where('sender_user_id', '!=', $user->id)
            ->whereDoesntHave('deletions', function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            })
            ->whereIn('id', $messageIds)
            ->pluck('id')
            ->all();

        if (empty($validMessageIds)) {
            return 0;
        }

        $now = now();
        $rows = collect($validMessageIds)
            ->map(function (string $messageId) use ($user, $now): array {
                return [
                    'message_id' => $messageId,
                    'user_id' => $user->id,
                    'read_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->all();

        DB::table('leadership_group_message_reads')->upsert(
            $rows,
            ['message_id', 'user_id'],
            ['read_at', 'updated_at']
        );

        return count($validMessageIds);
    }

    public function getMessages(Circle $circle, User $user, int $perPage = 20): ?LengthAwarePaginator
    {
        if (! $this->isActiveMember($circle, $user)) {
            return null;
        }

        $perPage = max(1, min($perPage, 100));

        $paginator = LeadershipGroupMessage::query()
            ->where('circle_id', $circle->id)
            ->whereNull('deleted_at')
            ->whereDoesntHave('deletions', function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            })
            ->with([
                'sender',
                'reads' => function ($query) use ($user): void {
                    $query->where('user_id', $user->id);
                },
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
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

        if (! empty($data['reply_to_message_id'])) {
            $isValidReplyMessage = LeadershipGroupMessage::query()
                ->where('id', $data['reply_to_message_id'])
                ->where('circle_id', $circle->id)
                ->whereNull('deleted_at')
                ->exists();

            if (! $isValidReplyMessage) {
                throw new HttpException(422, 'The reply message must belong to this circle.');
            }
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
            ->whereIn('leader_role', self::CHAT_ALLOWED_ROLES)
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
            ->whereDoesntHave('deletions', function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            })
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
            ->whereIn('leader_role', self::CHAT_ALLOWED_ROLES)
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
