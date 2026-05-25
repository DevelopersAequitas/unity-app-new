<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Models\ActivityCreative;
use App\Models\User;
use App\Services\ActivityCreativeService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PostBirthdayCreativesCommand extends Command
{
    protected $signature = 'birthdays:post-creatives';

    protected $description = 'Create birthday celebration creatives and timeline posts for users with birthdays today.';

    public function handle(ActivityCreativeService $activityCreativeService): int
    {
        $today = Carbon::today();

        $users = User::query()
            ->whereNotNull('dob')
            ->whereMonth('dob', $today->month)
            ->whereDay('dob', $today->day)
            ->where(function ($query): void {
                $query->whereNull('status')->orWhere('status', 'active');
            })
            ->get();

        foreach ($users as $user) {
            $existing = ActivityCreative::query()
                ->where('user_id', (string) $user->id)
                ->where('activity_type', 'birthday_celebration')
                ->whereBetween('created_at', [$today->copy()->startOfYear(), $today->copy()->endOfYear()])
                ->exists();

            if ($existing) {
                continue;
            }

            $activityCreativeService->createOrUpdateCreative(
                'birthday_celebration',
                (string) $user->id,
                (string) $user->id,
                $activityCreativeService->buildCreativePayload('birthday_celebration', $user)
            );

            Post::create([
                'user_id' => $user->id,
                'circle_id' => null,
                'content_text' => 'Happy Birthday ' . ($user->display_name ?: $user->first_name ?: 'Peer') . '! 🎉 Wishing you a successful and joyful year ahead from Peers Global Unity.',
                'media' => [],
                'tags' => ['birthday_celebration'],
                'visibility' => 'public',
                'moderation_status' => 'pending',
                'sponsored' => false,
                'is_deleted' => false,
                'source_type' => 'birthday_celebration',
                'source_id' => (string) $user->id,
                'source_event' => 'birthday_celebration_' . $today->year,
            ]);
        }

        $this->info('Birthday creatives processed: ' . $users->count());

        return self::SUCCESS;
    }
}
