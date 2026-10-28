<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ActivityCreative;
use App\Models\Post;
use App\Models\PostMention;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BackfillPostMentionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'posts:backfill-mentions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill missing post_mentions for all creative, recognition, honour, and celebration timeline posts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! Schema::hasTable('posts') || ! Schema::hasTable('post_mentions') || ! Schema::hasTable('users')) {
            $this->warn('Required tables do not exist.');

            return self::FAILURE;
        }

        $this->info('Starting post mentions backfill...');

        $creativeTypes = [
            'life_impact',
            'member_introduction',
            'recognition',
            'growth_honour',
            'milestone_honour',
            'milestone_badge',
            'welcome',
            'birthday',
            'anniversary',
            'global_peer_certificate',
            'introduction',
            'life_impact_recognition',
        ];

        $posts = Post::query()
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->get();

        $countCreated = 0;

        foreach ($posts as $post) {
            $candidatePeerIds = [];

            $sourceType = (string) ($post->source_type ?? '');
            $postType = (string) ($post->post_type ?? '');
            $isCreative = in_array($sourceType, $creativeTypes, true) || in_array($postType, $creativeTypes, true);

            // 1. source_id if it's a creative/recognition post
            if ($isCreative && ! empty($post->source_id) && Str::isUuid((string) $post->source_id)) {
                $candidatePeerIds[] = (string) $post->source_id;
            }

            // 2. tags (e.g. growth_honour where user id is stored in tags)
            $tags = is_array($post->tags) ? $post->tags : (is_string($post->tags) ? (json_decode($post->tags, true) ?? []) : []);
            foreach ($tags as $t) {
                if (is_string($t) && Str::isUuid($t)) {
                    $candidatePeerIds[] = $t;
                }
            }

            // 3. ActivityCreative record
            if (Schema::hasTable('activity_creatives')) {
                $acUserIds = ActivityCreative::where('post_id', $post->id)
                    ->whereNotNull('user_id')
                    ->pluck('user_id')
                    ->all();
                foreach ($acUserIds as $acUid) {
                    $candidatePeerIds[] = (string) $acUid;
                }
            }

            // 4. Inline markdown mentions
            if (! empty($post->content_text) && preg_match_all('/@\[([^\]]+)\]\(([0-9a-fA-F-]{36})\)/', (string) $post->content_text, $inlineMatches)) {
                foreach ($inlineMatches[2] as $matchedUuid) {
                    $candidatePeerIds[] = $matchedUuid;
                }
            }

            $uniqueCandidates = array_values(array_unique(array_filter($candidatePeerIds)));
            if (empty($uniqueCandidates)) {
                continue;
            }

            // Filter to only IDs that exist in the users table
            $validUserIds = User::whereIn('id', $uniqueCandidates)->pluck('id')->map(fn ($id) => (string) $id)->all();

            foreach ($validUserIds as $validUserId) {
                $mention = PostMention::firstOrCreate([
                    'post_id' => $post->id,
                    'peer_id' => $validUserId,
                ]);

                if ($mention->wasRecentlyCreated) {
                    $countCreated++;
                }
            }
        }

        $this->info("Post mentions backfill completed. Created {$countCreated} new post mention records.");

        return self::SUCCESS;
    }
}
