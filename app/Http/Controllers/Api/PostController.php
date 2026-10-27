<?php

namespace App\Http\Controllers\Api;

use App\Events\PostCreatedEvent;
use App\Http\Requests\Post\StorePostCommentRequest;
use App\Http\Requests\Post\StorePostRequest;
use App\Http\Resources\Ask\AskPreviewResource;
use App\Http\Resources\PostCommentResource;
use App\Http\Resources\PostResource;
use App\Models\ActivityCreative;
use App\Models\Ask\Ask;
use App\Models\Ask\AskTimelineLink;
use App\Models\BusinessDeal;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\File;
use App\Models\FileModel;
use App\Models\P2pMeeting;
use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostLike;
use App\Models\PostMention;
use App\Models\Referral;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\AdFeedService;
use App\Services\Ask\AskService;
use App\Services\Notifications\NotificationDispatchService;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\NotifyUserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class PostController extends BaseApiController
{
    public function feed(Request $request, AdFeedService $adFeedService)
    {
        $user = $request->user();
        $perPage = max(1, min((int) $request->integer('per_page', 20), 50));
        $page = LengthAwarePaginator::resolveCurrentPage();

        $userCircleIds = [];
        if (Schema::hasTable('circle_members')) {
            $userCircleIds = DB::table('circle_members')
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->pluck('circle_id')
                ->filter()
                ->all();
        }

        $userDistrictIds = [];
        if (! empty($userCircleIds) && Schema::hasTable('circles') && Schema::hasColumn('circles', 'district_id')) {
            $userDistrictIds = DB::table('circles')
                ->whereIn('id', $userCircleIds)
                ->whereNotNull('district_id')
                ->pluck('district_id')
                ->filter()
                ->all();
        }

        if (Schema::hasColumn('users', 'district_id') && ! empty($user->district_id)) {
            $userDistrictIds[] = (string) $user->district_id;
        }

        if ($user->city_id && Schema::hasTable('cities') && Schema::hasColumn('cities', 'district_id')) {
            $cityDistrictId = DB::table('cities')->where('id', $user->city_id)->value('district_id');
            if ($cityDistrictId) {
                $userDistrictIds[] = (string) $cityDistrictId;
            }
        }

        $cityName = trim((string) ($user->city_of_residence ?: (is_string($user->city) ? $user->city : '')));
        if ($cityName !== '' && Schema::hasTable('districts')) {
            $districtId = DB::table('districts')->where('name', 'ILIKE', "%{$cityName}%")->value('id');
            if ($districtId) {
                $userDistrictIds[] = (string) $districtId;
            }
        }

        $userDistrictIds = array_values(array_unique(array_filter($userDistrictIds)));
        $cityId = $user->city_id;

        try {
            if (Schema::hasTable('asks') && Schema::hasTable('ask_timeline_links')) {
                $unlinkedAsks = Ask::query()
                    ->where('status', Ask::STATUS_PUBLISHED)
                    ->where(function ($q) {
                        $q->whereNull('publish_to_timeline')
                            ->orWhere('publish_to_timeline', true);
                    })
                    ->whereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('ask_timeline_links')
                            ->whereColumn('ask_timeline_links.ask_id', 'asks.id');
                    })
                    ->whereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('posts')
                            ->where('posts.source_type', 'ask')
                            ->whereColumn('posts.source_id', 'asks.id');
                    })
                    ->limit(10)
                    ->get();

                if ($unlinkedAsks->isNotEmpty()) {
                    $askService = app(AskService::class);
                    foreach ($unlinkedAsks as $unlinkedAsk) {
                        $askService->ensureTimelinePost($unlinkedAsk);
                    }
                }
            }
        } catch (Throwable $e) {
            Log::warning('Failed auto-healing unlinked asks in feed: '.$e->getMessage());
        }

        $postRows = DB::table('posts')
            ->leftJoin('collaboration_posts as feed_collaboration_posts', function ($join): void {
                $join->on('feed_collaboration_posts.id', '=', 'posts.source_id')
                    ->where('posts.source_type', 'collaboration_post')
                    ->where('posts.source_event', 'completed');
            })
            ->leftJoin('users as accepted_peers', 'accepted_peers.id', '=', 'feed_collaboration_posts.accepted_by_user_id')
            ->selectRaw('posts.id as id')
            ->selectRaw('posts.user_id as author_id')
            ->selectRaw('posts.circle_id as circle_id')
            ->selectRaw('posts.content_text as content_text')
            ->selectRaw('posts.media as media')
            ->selectRaw('posts.tags as tags')
            ->selectRaw('posts.visibility as visibility')
            ->selectRaw('posts.moderation_status as moderation_status')
            ->selectRaw('(SELECT COUNT(*) FROM post_likes WHERE post_likes.post_id = posts.id) as likes_count')
            ->selectRaw('(SELECT COUNT(*) FROM post_comments WHERE post_comments.post_id = posts.id AND post_comments.deleted_at IS NULL) as comments_count')
            ->selectRaw('(SELECT COUNT(*) FROM post_saves WHERE post_saves.post_id = posts.id) as saves_count')
            ->selectRaw('EXISTS(SELECT 1 FROM post_likes WHERE post_likes.post_id = posts.id AND post_likes.user_id = ?) as is_liked_by_me', [$user->id])
            ->selectRaw('EXISTS(SELECT 1 FROM post_saves WHERE post_saves.post_id = posts.id AND post_saves.user_id = ?) as is_saved_by_me', [$user->id])
            ->selectRaw('posts.created_at as created_at')
            ->selectRaw('posts.updated_at as updated_at')
            ->selectRaw('posts.created_at as sort_at')
            ->selectRaw("'post' as source_type")
            ->selectRaw('posts.source_type as post_source_type')
            ->selectRaw('posts.source_id as post_source_id')
            ->selectRaw('posts.source_event as post_source_event')
            ->selectRaw('accepted_peers.id as accepted_by_id')
            ->selectRaw('accepted_peers.display_name as accepted_by_display_name')
            ->selectRaw('accepted_peers.first_name as accepted_by_first_name')
            ->selectRaw('accepted_peers.last_name as accepted_by_last_name')
            ->selectRaw('accepted_peers.company_name as accepted_by_company_name')
            ->selectRaw('accepted_peers.city as accepted_by_city')
            ->selectRaw('NULL::uuid as impacted_peer_id')
            ->selectRaw('NULL::date as impact_date')
            ->selectRaw('NULL::text as impact_action')
            ->selectRaw('NULL::integer as life_impacted')
            ->selectRaw('posts.post_type as post_type')
            ->where(function ($q) use ($user, $userCircleIds, $userDistrictIds, $cityId, $cityName): void {
                $visibilityCol = DB::raw('CAST(posts.visibility AS TEXT)');

                $q->where($visibilityCol, 'public')
                    ->orWhere('posts.user_id', $user->id);

                if (! empty($userCircleIds)) {
                    $q->orWhere(function ($cq) use ($visibilityCol, $userCircleIds): void {
                        $cq->where($visibilityCol, 'circle')
                            ->whereIn('posts.circle_id', $userCircleIds);
                    });
                }

                $q->orWhere(function ($dq) use ($visibilityCol, $userDistrictIds, $cityId, $cityName): void {
                    $dq->where($visibilityCol, 'district');
                    $dq->where(function ($subDq) use ($userDistrictIds, $cityId, $cityName): void {
                        $hasDistrictCondition = false;
                        if (! empty($userDistrictIds) && Schema::hasTable('asks')) {
                            $subDq->whereExists(function ($askQuery) use ($userDistrictIds): void {
                                $askQuery->select(DB::raw(1))
                                    ->from('asks')
                                    ->whereColumn('asks.id', 'posts.source_id')
                                    ->where('posts.source_type', 'ask')
                                    ->whereIn('asks.visibility_district_id', $userDistrictIds);
                            });
                            $hasDistrictCondition = true;
                        }
                        if ($cityId) {
                            if ($hasDistrictCondition) {
                                $subDq->orWhereExists(function ($authorQuery) use ($cityId): void {
                                    $authorQuery->select(DB::raw(1))
                                        ->from('users')
                                        ->whereColumn('users.id', 'posts.user_id')
                                        ->where('users.city_id', $cityId);
                                });
                            } else {
                                $subDq->whereExists(function ($authorQuery) use ($cityId): void {
                                    $authorQuery->select(DB::raw(1))
                                        ->from('users')
                                        ->whereColumn('users.id', 'posts.user_id')
                                        ->where('users.city_id', $cityId);
                                });
                                $hasDistrictCondition = true;
                            }
                        }
                        if ($cityName !== '') {
                            if ($hasDistrictCondition) {
                                $subDq->orWhereExists(function ($authorQuery) use ($cityName): void {
                                    $authorQuery->select(DB::raw(1))
                                        ->from('users')
                                        ->whereColumn('users.id', 'posts.user_id')
                                        ->where('users.city', 'ILIKE', "%{$cityName}%");
                                });
                            } else {
                                $subDq->whereExists(function ($authorQuery) use ($cityName): void {
                                    $authorQuery->select(DB::raw(1))
                                        ->from('users')
                                        ->whereColumn('users.id', 'posts.user_id')
                                        ->where('users.city', 'ILIKE', "%{$cityName}%");
                                });
                                $hasDistrictCondition = true;
                            }
                        }
                        if (! $hasDistrictCondition) {
                            $subDq->whereRaw('1 = 0');
                        }
                    });
                });
            })
            ->where('posts.status', 'active')
            ->where('posts.is_deleted', false)
            ->whereNull('posts.deleted_at')
            ->whereExists(function ($authorQuery): void {
                $authorQuery->select(DB::raw(1))
                    ->from('users as author_filter')
                    ->whereColumn('author_filter.id', 'posts.user_id')
                    ->where(function ($sq): void {
                        $sq->whereNull('author_filter.status')->orWhere('author_filter.status', 'active');
                    })
                    ->where('author_filter.status', '!=', 'inactive')
                    ->whereNull('author_filter.deleted_at');
            });

        $union = $postRows;

        if (Schema::hasTable('impacts')) {
            $impactRows = DB::table('impacts')
                ->selectRaw('impacts.id as id')
                ->selectRaw('impacts.user_id as author_id')
                ->selectRaw('NULL::uuid as circle_id')
                ->selectRaw('impacts.story_to_share as content_text')
                ->selectRaw("'[]'::jsonb as media")
                ->selectRaw("'[]'::jsonb as tags")
                ->selectRaw("'public' as visibility")
                ->selectRaw("'approved' as moderation_status")
                ->selectRaw('0 as likes_count')
                ->selectRaw('0 as comments_count')
                ->selectRaw('0 as saves_count')
                ->selectRaw('false as is_liked_by_me')
                ->selectRaw('false as is_saved_by_me')
                ->selectRaw('impacts.created_at as created_at')
                ->selectRaw('impacts.updated_at as updated_at')
                ->selectRaw('impacts.timeline_posted_at as sort_at')
                ->selectRaw("'impact' as source_type")
                ->selectRaw('NULL::text as post_source_type')
                ->selectRaw('NULL::uuid as post_source_id')
                ->selectRaw('NULL::text as post_source_event')
                ->selectRaw('NULL::uuid as accepted_by_id')
                ->selectRaw('NULL::text as accepted_by_display_name')
                ->selectRaw('NULL::text as accepted_by_first_name')
                ->selectRaw('NULL::text as accepted_by_last_name')
                ->selectRaw('NULL::text as accepted_by_company_name')
                ->selectRaw('NULL::text as accepted_by_city')
                ->selectRaw('impacts.impacted_peer_id as impacted_peer_id')
                ->selectRaw('impacts.impact_date as impact_date')
                ->selectRaw('impacts.action as impact_action')
                ->selectRaw('COALESCE(impacts.life_impacted::integer, 1) as life_impacted')
                ->selectRaw('NULL::text as post_type')
                ->where('impacts.status', 'approved')
                ->whereNotNull('impacts.timeline_posted_at')
                ->whereExists(function ($authorQuery): void {
                    $authorQuery->select(DB::raw(1))
                        ->from('users as author_filter')
                        ->whereColumn('author_filter.id', 'impacts.user_id')
                        ->where(function ($sq): void {
                            $sq->whereNull('author_filter.status')->orWhere('author_filter.status', 'active');
                        })
                        ->where('author_filter.status', '!=', 'inactive')
                        ->whereNull('author_filter.deleted_at');
                });

            $union = $union->unionAll($impactRows);
        }

        if (Schema::hasTable('referrals')) {
            $referralRows = DB::table('referrals')
                ->leftJoin('users as to_users', 'to_users.id', '=', 'referrals.to_user_id')
                ->selectRaw('referrals.id as id')
                ->selectRaw('referrals.from_user_id as author_id')
                ->selectRaw('NULL::uuid as circle_id')
                ->selectRaw("COALESCE(referrals.remarks, 'Shared a referral for ' || referrals.referral_of) as content_text")
                ->selectRaw("'[]'::jsonb as media")
                ->selectRaw("'[\"referral\"]'::jsonb as tags")
                ->selectRaw("'public' as visibility")
                ->selectRaw("'approved' as moderation_status")
                ->selectRaw('0 as likes_count')
                ->selectRaw('0 as comments_count')
                ->selectRaw('0 as saves_count')
                ->selectRaw('false as is_liked_by_me')
                ->selectRaw('false as is_saved_by_me')
                ->selectRaw('referrals.created_at as created_at')
                ->selectRaw('referrals.updated_at as updated_at')
                ->selectRaw('referrals.created_at as sort_at')
                ->selectRaw("'post' as source_type")
                ->selectRaw("'referral' as post_source_type")
                ->selectRaw('referrals.id as post_source_id')
                ->selectRaw("'referral_created' as post_source_event")
                ->selectRaw('to_users.id as accepted_by_id')
                ->selectRaw('to_users.display_name as accepted_by_display_name')
                ->selectRaw('to_users.first_name as accepted_by_first_name')
                ->selectRaw('to_users.last_name as accepted_by_last_name')
                ->selectRaw('to_users.company_name as accepted_by_company_name')
                ->selectRaw('to_users.city as accepted_by_city')
                ->selectRaw('referrals.to_user_id as impacted_peer_id')
                ->selectRaw('referrals.referral_date as impact_date')
                ->selectRaw('referrals.referral_of as impact_action')
                ->selectRaw('1 as life_impacted')
                ->selectRaw("'referral' as post_type")
                ->where('referrals.is_deleted', false)
                ->whereNull('referrals.deleted_at')
                ->whereNotExists(function ($q): void {
                    $q->select(DB::raw(1))
                        ->from('posts')
                        ->whereColumn('posts.source_id', 'referrals.id')
                        ->where('posts.source_type', 'referral');
                })
                ->whereExists(function ($authorQuery): void {
                    $authorQuery->select(DB::raw(1))
                        ->from('users as author_filter')
                        ->whereColumn('author_filter.id', 'referrals.from_user_id')
                        ->where(function ($sq): void {
                            $sq->whereNull('author_filter.status')->orWhere('author_filter.status', 'active');
                        })
                        ->where('author_filter.status', '!=', 'inactive')
                        ->whereNull('author_filter.deleted_at');
                });

            $union = $union->unionAll($referralRows);
        }

        $orderedRows = DB::query()->fromSub($union, 'feed_rows')->orderByDesc('sort_at');

        $total = (clone $orderedRows)->count();
        $pageRows = collect((clone $orderedRows)->forPage($page, $perPage)->get());

        $authorIds = $pageRows->pluck('author_id')->filter()->unique()->values()->all();
        $circleIds = $pageRows->pluck('circle_id')->filter()->unique()->values()->all();
        $impactedPeerIds = $pageRows->pluck('impacted_peer_id')->filter()->unique()->values()->all();
        $postIds = $pageRows
            ->filter(fn ($row) => (string) ($row->source_type ?? '') === 'post')
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        $activityCreativesByPostId = ActivityCreative::query()
            ->whereIn('post_id', $postIds)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->latest('created_at')
            ->get(['id', 'post_id', 'user_id', 'activity_type', 'activity_id', 'title', 'description', 'creative_file_id', 'creative_url'])
            ->groupBy(fn (ActivityCreative $creative): string => (string) $creative->post_id)
            ->map(fn ($creatives) => $creatives->first());

        $authors = User::query()
            ->whereIn('id', $authorIds)
            ->with(['level4Category', 'businessCategory', 'mainBusinessCategory'])
            ->get([
                'id',
                'display_name',
                'first_name',
                'last_name',
                'company_name',
                'designation',
                'email',
                'profile_photo_file_id',
                'profile_photo_url',
                'business_category_id',
                'main_business_category_id',
                'business_sub_category',
            ])
            ->keyBy(fn (User $author) => (string) $author->id);

        $circles = Circle::query()
            ->whereIn('id', $circleIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        $impactedPeers = User::query()
            ->whereIn('id', $impactedPeerIds)
            ->get(['id', 'display_name', 'first_name', 'last_name'])
            ->keyBy(fn (User $peer) => (string) $peer->id);

        $acceptedByIds = $pageRows->pluck('accepted_by_id')->filter()->unique()->values()->all();

        $creativeSourceTypes = [
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
        ];
        $creativePostTypes = [
            'life_impact_recognition',
            'growth_honour',
            'milestone_honour',
            'welcome',
            'birthday',
            'anniversary',
            'global_peer_certificate',
            'introduction',
        ];

        // Pre-resolve Activity Models (P2P Meetings, Business Deals, Testimonials, Referrals)
        $p2pMeetingsById = collect();
        $fallbackP2pMeetingIdByPostId = [];
        $p2pMeetingSourceIds = $pageRows
            ->filter(fn ($row) => (string) ($row->source_type ?? '') === 'post' && $this->isP2pMeetingPostRow($row))
            ->pluck('post_source_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        foreach ($pageRows as $r) {
            $ac = $activityCreativesByPostId->get((string) $r->id);
            if ($ac && in_array(strtolower((string) $ac->activity_type), ['p2p_meeting', 'p2pmeeting'], true) && ! empty($ac->activity_id)) {
                $p2pMeetingSourceIds[] = (string) $ac->activity_id;
            }
        }
        $p2pMeetingSourceIds = array_values(array_unique(array_filter($p2pMeetingSourceIds)));

        if ($p2pMeetingSourceIds !== []) {
            $p2pMeetingsById = P2pMeeting::query()
                ->whereIn('id', $p2pMeetingSourceIds)
                ->get(['id', 'initiator_user_id', 'peer_user_id', 'media', 'created_at'])
                ->keyBy('id');
        }

        $p2pPostsWithoutSource = $pageRows->filter(function ($row): bool {
            return (string) ($row->source_type ?? '') === 'post'
                && $this->isP2pMeetingPostRow($row)
                && empty($row->post_source_id);
        })->values();

        if ($p2pPostsWithoutSource->isNotEmpty()) {
            $fallbackAuthorIds = $p2pPostsWithoutSource->pluck('author_id')->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();
            $postCreatedAt = $p2pPostsWithoutSource
                ->map(fn ($row) => Carbon::parse((string) $row->created_at))
                ->sortBy(fn (Carbon $value) => $value->getTimestamp())
                ->values();

            if ($fallbackAuthorIds !== [] && $postCreatedAt->isNotEmpty()) {
                $windowStart = $postCreatedAt->first()->copy()->subMinutes(5);
                $windowEnd = $postCreatedAt->last()->copy()->addMinutes(5);

                $candidateMeetings = P2pMeeting::query()
                    ->where(function ($q) use ($fallbackAuthorIds) {
                        $q->whereIn('initiator_user_id', $fallbackAuthorIds)
                            ->orWhereIn('peer_user_id', $fallbackAuthorIds);
                    })
                    ->whereBetween('created_at', [$windowStart, $windowEnd])
                    ->get(['id', 'initiator_user_id', 'peer_user_id', 'created_at', 'media']);

                foreach ($p2pPostsWithoutSource as $row) {
                    $authorId = (string) ($row->author_id ?? '');
                    $rowCreatedAt = Carbon::parse((string) $row->created_at);

                    $bestMeeting = $candidateMeetings
                        ->filter(function (P2pMeeting $meeting) use ($authorId, $rowCreatedAt): bool {
                            $isParticipant = (string) $meeting->initiator_user_id === $authorId || (string) $meeting->peer_user_id === $authorId;
                            $diff = abs($meeting->created_at->getTimestamp() - $rowCreatedAt->getTimestamp());

                            return $isParticipant && $diff <= 300;
                        })
                        ->sortBy(function (P2pMeeting $meeting) use ($rowCreatedAt): int {
                            return abs($meeting->created_at->getTimestamp() - $rowCreatedAt->getTimestamp());
                        })
                        ->first();

                    if ($bestMeeting) {
                        $fallbackP2pMeetingIdByPostId[(string) $row->id] = (string) $bestMeeting->id;
                        $p2pMeetingsById->put((string) $bestMeeting->id, $bestMeeting);
                    }
                }
            }
        }

        // Business Deals
        $businessDealsById = collect();
        $fallbackBusinessDealIdByPostId = [];
        $businessDealSourceIds = $pageRows
            ->filter(fn ($row) => $this->isBusinessDealPostRow($row))
            ->pluck('post_source_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        foreach ($pageRows as $r) {
            $ac = $activityCreativesByPostId->get((string) $r->id);
            if ($ac && strtolower((string) $ac->activity_type) === 'business_deal' && ! empty($ac->activity_id)) {
                $businessDealSourceIds[] = (string) $ac->activity_id;
            }
        }
        $businessDealSourceIds = array_values(array_unique(array_filter($businessDealSourceIds)));

        if ($businessDealSourceIds !== []) {
            $businessDealsById = BusinessDeal::query()
                ->whereIn('id', $businessDealSourceIds)
                ->get(['id', 'from_user_id', 'to_user_id', 'deal_amount', 'created_at'])
                ->keyBy('id');
        }

        $dealPostsWithoutSource = $pageRows->filter(function ($row) use ($activityCreativesByPostId): bool {
            $ac = $activityCreativesByPostId->get((string) $row->id);

            return $this->isBusinessDealPostRow($row)
                && empty($row->post_source_id)
                && empty($ac?->activity_id);
        })->values();

        if ($dealPostsWithoutSource->isNotEmpty()) {
            $dealAuthorIds = $dealPostsWithoutSource->pluck('author_id')->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();
            $postCreatedAt = $dealPostsWithoutSource
                ->map(fn ($row) => Carbon::parse((string) $row->created_at))
                ->sortBy(fn (Carbon $value) => $value->getTimestamp())
                ->values();

            if ($dealAuthorIds !== [] && $postCreatedAt->isNotEmpty()) {
                $windowStart = $postCreatedAt->first()->copy()->subMinutes(5);
                $windowEnd = $postCreatedAt->last()->copy()->addMinutes(5);

                $candidateDeals = BusinessDeal::query()
                    ->where(function ($q) use ($dealAuthorIds) {
                        $q->whereIn('from_user_id', $dealAuthorIds)->orWhereIn('to_user_id', $dealAuthorIds);
                    })
                    ->whereBetween('created_at', [$windowStart, $windowEnd])
                    ->get(['id', 'from_user_id', 'to_user_id', 'deal_amount', 'created_at']);

                foreach ($dealPostsWithoutSource as $row) {
                    $authorId = (string) ($row->author_id ?? '');
                    $rowCreatedAt = Carbon::parse((string) $row->created_at);

                    $bestDeal = $candidateDeals
                        ->filter(function (BusinessDeal $deal) use ($authorId, $rowCreatedAt): bool {
                            $isParticipant = (string) $deal->from_user_id === $authorId || (string) $deal->to_user_id === $authorId;
                            $diff = abs($deal->created_at->getTimestamp() - $rowCreatedAt->getTimestamp());

                            return $isParticipant && $diff <= 300;
                        })
                        ->sortBy(function (BusinessDeal $deal) use ($rowCreatedAt): int {
                            return abs($deal->created_at->getTimestamp() - $rowCreatedAt->getTimestamp());
                        })
                        ->first();

                    if ($bestDeal) {
                        $fallbackBusinessDealIdByPostId[(string) $row->id] = (string) $bestDeal->id;
                        $businessDealsById->put((string) $bestDeal->id, $bestDeal);
                    }
                }
            }
        }

        // Testimonials
        $testimonialsById = collect();
        $fallbackTestimonialIdByPostId = [];
        $testimonialSourceIds = $pageRows
            ->filter(fn ($row) => $this->isTestimonialPostRow($row))
            ->pluck('post_source_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        foreach ($pageRows as $r) {
            $ac = $activityCreativesByPostId->get((string) $r->id);
            if ($ac && strtolower((string) $ac->activity_type) === 'testimonial' && ! empty($ac->activity_id)) {
                $testimonialSourceIds[] = (string) $ac->activity_id;
            }
        }
        $testimonialSourceIds = array_values(array_unique(array_filter($testimonialSourceIds)));

        if ($testimonialSourceIds !== []) {
            $testimonialsById = Testimonial::query()
                ->whereIn('id', $testimonialSourceIds)
                ->get(['id', 'from_user_id', 'to_user_id', 'content', 'created_at'])
                ->keyBy('id');
        }

        $testimonialPostsWithoutSource = $pageRows->filter(function ($row) use ($activityCreativesByPostId): bool {
            $ac = $activityCreativesByPostId->get((string) $row->id);

            return $this->isTestimonialPostRow($row)
                && empty($row->post_source_id)
                && empty($ac?->activity_id);
        })->values();

        if ($testimonialPostsWithoutSource->isNotEmpty()) {
            $testAuthorIds = $testimonialPostsWithoutSource->pluck('author_id')->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();
            $postCreatedAt = $testimonialPostsWithoutSource
                ->map(fn ($row) => Carbon::parse((string) $row->created_at))
                ->sortBy(fn (Carbon $value) => $value->getTimestamp())
                ->values();

            if ($testAuthorIds !== [] && $postCreatedAt->isNotEmpty()) {
                $windowStart = $postCreatedAt->first()->copy()->subMinutes(5);
                $windowEnd = $postCreatedAt->last()->copy()->addMinutes(5);

                $candidateTests = Testimonial::query()
                    ->where(function ($q) use ($testAuthorIds) {
                        $q->whereIn('from_user_id', $testAuthorIds)->orWhereIn('to_user_id', $testAuthorIds);
                    })
                    ->whereBetween('created_at', [$windowStart, $windowEnd])
                    ->get(['id', 'from_user_id', 'to_user_id', 'content', 'created_at']);

                foreach ($testimonialPostsWithoutSource as $row) {
                    $authorId = (string) ($row->author_id ?? '');
                    $rowCreatedAt = Carbon::parse((string) $row->created_at);

                    $bestTest = $candidateTests
                        ->filter(function (Testimonial $test) use ($authorId, $rowCreatedAt): bool {
                            $isParticipant = (string) $test->from_user_id === $authorId || (string) $test->to_user_id === $authorId;
                            $diff = abs($test->created_at->getTimestamp() - $rowCreatedAt->getTimestamp());

                            return $isParticipant && $diff <= 300;
                        })
                        ->sortBy(function (Testimonial $test) use ($rowCreatedAt): int {
                            return abs($test->created_at->getTimestamp() - $rowCreatedAt->getTimestamp());
                        })
                        ->first();

                    if ($bestTest) {
                        $fallbackTestimonialIdByPostId[(string) $row->id] = (string) $bestTest->id;
                        $testimonialsById->put((string) $bestTest->id, $bestTest);
                    }
                }
            }
        }

        // Referrals
        $referralSourceIds = $pageRows
            ->filter(fn ($row) => (string) ($row->post_source_type ?? '') === 'referral' || (string) ($row->source_type ?? '') === 'referral')
            ->map(fn ($row) => (string) ($row->post_source_id ?? $row->id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $referralsById = $referralSourceIds !== []
            ? Referral::query()
                ->whereIn('id', $referralSourceIds)
                ->with(['status', 'toUser'])
                ->get()
                ->keyBy(fn (Referral $r) => (string) $r->id)
            : collect();

        // Text Regex extraction fallback for activity posts
        $extractedNames = [];
        $postToExtractedName = [];
        foreach ($pageRows as $row) {
            $text = (string) ($row->content_text ?? '');
            if ($text === '') {
                continue;
            }
            $extracted = null;
            if (preg_match('/sharing a moment of gratitude to\s+([^.]+?)(?:\.|$)/i', $text, $m)) {
                $extracted = trim($m[1]);
            } elseif (preg_match('/made a deal with\s+(.+?)\s+of amount/i', $text, $m)) {
                $extracted = trim($m[1]);
            } elseif (preg_match('/connected with\s+([^,]+?),/i', $text, $m)) {
                $extracted = trim($m[1]);
            } elseif (preg_match('/referral for\s+.+?\s+to\s+([^.\n]+?)(?:\.|\n|$)/i', $text, $m)) {
                $extracted = trim($m[1]);
            }

            if ($extracted !== null && $extracted !== '' && strtolower($extracted) !== 'peer') {
                $extractedNames[] = $extracted;
                $postToExtractedName[(string) $row->id] = $extracted;
            }
        }

        $userByNameMap = [];
        if ($extractedNames !== []) {
            $matchedUsers = User::query()
                ->where(function ($q) use ($extractedNames) {
                    $q->whereIn('display_name', $extractedNames);
                    foreach ($extractedNames as $nm) {
                        $q->orWhereRaw("CONCAT(first_name, ' ', last_name) = ?", [$nm]);
                    }
                })
                ->get([
                    'id',
                    'display_name',
                    'first_name',
                    'last_name',
                    'profile_photo_file_id',
                    'profile_photo_url',
                    'is_online',
                ]);

            foreach ($matchedUsers as $u) {
                $dName = strtolower(trim((string) $u->display_name));
                $fName = strtolower(trim(($u->first_name ?? '').' '.($u->last_name ?? '')));
                if ($dName !== '') {
                    $userByNameMap[$dName] = $u;
                }
                if ($fName !== '') {
                    $userByNameMap[$fName] = $u;
                }
            }
        }

        // Pre-resolve counterpart peer ID for each post
        $counterpartPeerIdByPostId = [];
        foreach ($pageRows as $row) {
            $postId = (string) $row->id;
            $authorId = (string) ($row->author_id ?? '');
            $ac = $activityCreativesByPostId->get($postId);
            $counterpartId = null;

            // P2P Meeting
            if ($this->isP2pMeetingPostRow($row) || ($ac && in_array(strtolower((string) $ac->activity_type), ['p2p_meeting', 'p2pmeeting'], true))) {
                $meetingId = (string) ($row->post_source_id ?? $ac?->activity_id ?? $fallbackP2pMeetingIdByPostId[$postId] ?? '');
                if ($meetingId !== '' && $p2pMeetingsById->has($meetingId)) {
                    $m = $p2pMeetingsById->get($meetingId);
                    $counterpartId = (string) $m->initiator_user_id === $authorId ? (string) $m->peer_user_id : (string) $m->initiator_user_id;
                }
            }

            // Business Deal
            if (! $counterpartId && ($this->isBusinessDealPostRow($row) || ($ac && strtolower((string) $ac->activity_type) === 'business_deal'))) {
                $dealId = (string) ($row->post_source_id ?? $ac?->activity_id ?? $fallbackBusinessDealIdByPostId[$postId] ?? '');
                if ($dealId !== '' && $businessDealsById->has($dealId)) {
                    $d = $businessDealsById->get($dealId);
                    $counterpartId = (string) $d->from_user_id === $authorId ? (string) $d->to_user_id : (string) $d->from_user_id;
                }
            }

            // Testimonial
            if (! $counterpartId && ($this->isTestimonialPostRow($row) || ($ac && strtolower((string) $ac->activity_type) === 'testimonial'))) {
                $testId = (string) ($row->post_source_id ?? $ac?->activity_id ?? $fallbackTestimonialIdByPostId[$postId] ?? '');
                if ($testId !== '' && $testimonialsById->has($testId)) {
                    $t = $testimonialsById->get($testId);
                    $counterpartId = (string) $t->from_user_id === $authorId ? (string) $t->to_user_id : (string) $t->from_user_id;
                }
            }

            // Referral
            if (! $counterpartId && ((string) ($row->post_source_type ?? '') === 'referral' || (string) ($row->source_type ?? '') === 'referral' || (string) ($row->post_type ?? '') === 'referral')) {
                $refId = (string) ($row->post_source_id ?? $row->id);
                if ($referralsById->has($refId)) {
                    $ref = $referralsById->get($refId);
                    $counterpartId = (string) ($ref->to_user_id ?? '');
                }
            }

            // Text regex name match fallback
            if (! $counterpartId && isset($postToExtractedName[$postId])) {
                $matchedNameLower = strtolower($postToExtractedName[$postId]);
                if (isset($userByNameMap[$matchedNameLower])) {
                    $matchedUser = $userByNameMap[$matchedNameLower];
                    if ((string) $matchedUser->id !== $authorId) {
                        $counterpartId = (string) $matchedUser->id;
                    }
                }
            }

            if ($counterpartId !== null && $counterpartId !== '' && $counterpartId !== $authorId) {
                $counterpartPeerIdByPostId[$postId] = $counterpartId;
            }
        }

        $postMentionsByPostId = $postIds !== []
            ? DB::table('post_mentions')
                ->join('users', 'users.id', '=', 'post_mentions.peer_id')
                ->whereIn('post_mentions.post_id', $postIds)
                ->select([
                    'post_mentions.post_id',
                    'users.id as peer_id',
                    'users.display_name',
                    'users.first_name',
                    'users.last_name',
                    'users.profile_photo_file_id',
                    'users.profile_photo_url',
                    'users.is_online',
                ])
                ->get()
                ->groupBy(fn ($pm): string => (string) $pm->post_id)
            : collect();

        $creativePeerIds = [];
        foreach ($pageRows as $row) {
            $authorId = (string) ($row->author_id ?? '');
            $sourceType = (string) ($row->post_source_type ?? '');
            $postType = (string) ($row->post_type ?? '');
            $isCreative = in_array($sourceType, $creativeSourceTypes, true)
                || in_array($postType, $creativePostTypes, true);

            if (! empty($row->post_source_id) && $isCreative && (string) $row->post_source_id !== $authorId) {
                $creativePeerIds[] = (string) $row->post_source_id;
            }

            $decodedTags = $this->decodeJsonColumn($row->tags ?? null);
            foreach ($decodedTags as $t) {
                if (is_string($t) && preg_match('/^[0-9a-fA-F-]{36}$/', $t) && (string) $t !== $authorId) {
                    $creativePeerIds[] = $t;
                }
            }

            $ac = $activityCreativesByPostId->get((string) $row->id);
            if ($ac && ! empty($ac->user_id) && (string) $ac->user_id !== $authorId) {
                $actType = strtolower((string) $ac->activity_type);
                if (! in_array($actType, ['p2p_meeting', 'p2pmeeting', 'business_deal', 'testimonial', 'referral'], true)) {
                    $creativePeerIds[] = (string) $ac->user_id;
                }
            }

            if (! empty($row->content_text) && preg_match_all('/@\[([^\]]+)\]\(([0-9a-fA-F-]{36})\)/', (string) $row->content_text, $inlineMatches)) {
                foreach ($inlineMatches[2] as $matchedUuid) {
                    if ((string) $matchedUuid !== $authorId) {
                        $creativePeerIds[] = $matchedUuid;
                    }
                }
            }
        }

        $askSourceIds = $pageRows
            ->filter(fn ($row) => (string) ($row->source_type ?? '') === 'post' && ((string) ($row->post_source_type ?? '') === 'ask' || (string) ($row->post_type ?? '') === 'ask'))
            ->pluck('post_source_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        $askPostIds = $pageRows
            ->filter(fn ($row) => (string) ($row->source_type ?? '') === 'post' && ((string) ($row->post_source_type ?? '') === 'ask' || (string) ($row->post_type ?? '') === 'ask'))
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        $asksByPostId = collect();
        $asksById = collect();
        $askPeerIdsByPostId = [];

        if (! empty($askPostIds) || ! empty($askSourceIds)) {
            $timelineLinks = AskTimelineLink::query()
                ->whereIn('post_id', $askPostIds)
                ->get(['ask_id', 'post_id'])
                ->keyBy('post_id');

            $allAskIds = collect($askSourceIds)
                ->merge($timelineLinks->pluck('ask_id'))
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (! empty($allAskIds)) {
                $asks = Ask::query()
                    ->whereIn('id', $allAskIds)
                    ->with([
                        'flow',
                        'type',
                        'answers.option',
                        'district',
                        'circle',
                        'user',
                        'matches:id,ask_id,matched_user_id',
                        'responses:id,ask_id,responder_user_id,introduced_user_id,status',
                    ])
                    ->withCount(['responses', 'matches'])
                    ->get();

                $asksById = $asks->keyBy(fn (Ask $ask) => (string) $ask->id);

                foreach ($timelineLinks as $postId => $link) {
                    if ($asksById->has((string) $link->ask_id)) {
                        $asksByPostId->put((string) $postId, $asksById->get((string) $link->ask_id));
                    }
                }

                foreach ($pageRows as $row) {
                    $postId = (string) $row->id;
                    $authorId = (string) ($row->author_id ?? '');
                    $ask = (! empty($row->post_source_id) ? $asksById->get((string) $row->post_source_id) : null)
                        ?? $asksByPostId->get($postId);

                    if ($ask) {
                        $askPeers = [];
                        if ($ask->relationLoaded('responses') && $ask->responses) {
                            foreach ($ask->responses as $resp) {
                                if (! empty($resp->responder_user_id) && (string) $resp->responder_user_id !== $authorId) {
                                    $askPeers[] = (string) $resp->responder_user_id;
                                }
                                if (! empty($resp->introduced_user_id) && (string) $resp->introduced_user_id !== $authorId) {
                                    $askPeers[] = (string) $resp->introduced_user_id;
                                }
                            }
                        }
                        if ($ask->relationLoaded('matches') && $ask->matches) {
                            foreach ($ask->matches as $match) {
                                if (! empty($match->matched_user_id) && (string) $match->matched_user_id !== $authorId) {
                                    $askPeers[] = (string) $match->matched_user_id;
                                }
                            }
                        }
                        if ($askPeers !== []) {
                            $askPeerIdsByPostId[$postId] = array_values(array_unique($askPeers));
                        }
                    }
                }
            }
        }

        $allAskPeerIds = [];
        foreach ($askPeerIdsByPostId as $pIds) {
            foreach ($pIds as $pId) {
                $allAskPeerIds[] = $pId;
            }
        }

        $storedMentionPeerIds = [];
        foreach ($postMentionsByPostId as $pms) {
            foreach ($pms as $pm) {
                $storedMentionPeerIds[] = (string) $pm->peer_id;
            }
        }

        $allMentionPeerIds = collect(array_merge(
            $creativePeerIds,
            $impactedPeerIds,
            $acceptedByIds,
            array_values($counterpartPeerIdByPostId),
            $allAskPeerIds,
            $storedMentionPeerIds
        ))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $mentionedPeers = $allMentionPeerIds !== []
            ? User::query()
                ->whereIn('id', $allMentionPeerIds)
                ->get([
                    'id',
                    'display_name',
                    'first_name',
                    'last_name',
                    'profile_photo_file_id',
                    'profile_photo_url',
                    'is_online',
                ])
                ->keyBy(fn (User $u) => (string) $u->id)
            : collect();

        $isDownloadable = CircleMember::where('user_id', $user->id)
            ->where('status', 'approved')
            ->exists();

        $verifiedAuthorIds = CircleMember::whereIn('user_id', $authorIds)
            ->where('status', 'approved')
            ->pluck('user_id')
            ->unique()
            ->map(fn ($id) => (string) $id)
            ->toArray();

        $postItems = $pageRows->map(function ($row) use ($authors, $circles, $impactedPeers, $mentionedPeers, $postMentionsByPostId, $p2pMeetingsById, $fallbackP2pMeetingIdByPostId, $counterpartPeerIdByPostId, $askPeerIdsByPostId, $activityCreativesByPostId, $isDownloadable, $verifiedAuthorIds, $asksById, $asksByPostId, $referralsById, $request, $creativeSourceTypes, $creativePostTypes) {
            $author = $authors->get((string) $row->author_id);
            $circle = $row->circle_id ? $circles->get((string) $row->circle_id) : null;
            $activityCreative = (string) ($row->source_type ?? '') === 'post'
                ? $activityCreativesByPostId->get((string) $row->id)
                : null;

            $authorId = (string) ($row->author_id ?? '');
            $isVerified = $authorId !== '' ? (
                in_array($authorId, $verifiedAuthorIds, true)
                || (bool) ($author->is_verified ?? false)
            ) : false;

            $mentions = [];

            // 1. Stored post mentions from post_mentions table (excluding author)
            $dbMentions = $postMentionsByPostId->get((string) $row->id);
            if ($dbMentions && $dbMentions->isNotEmpty()) {
                foreach ($dbMentions as $dm) {
                    $peerId = (string) $dm->peer_id;
                    if ($peerId === $authorId) {
                        continue;
                    }
                    $mName = $dm->display_name ?: trim((($dm->first_name ?? '').' '.($dm->last_name ?? '')));
                    $mentions[] = [
                        'id' => $peerId,
                        'name' => $mName !== '' ? $mName : 'Peer Member',
                        'profile_photo_url' => $dm->profile_photo_file_id
                            ? url('/api/v1/files/'.$dm->profile_photo_file_id)
                            : ($dm->profile_photo_url ?: null),
                        'is_online' => (bool) ($dm->is_online ?? false),
                    ];
                }
            }

            // 2. Candidate peers: counterpart peer, ask matched peers/responders, creative, recognition, tags, inline mentions
            $candidatePeerIds = [];

            $counterpartId = $counterpartPeerIdByPostId[(string) $row->id] ?? null;
            if ($counterpartId && (string) $counterpartId !== $authorId) {
                $candidatePeerIds[] = (string) $counterpartId;
            }

            if (isset($askPeerIdsByPostId[(string) $row->id])) {
                foreach ($askPeerIdsByPostId[(string) $row->id] as $askPeerId) {
                    if ($askPeerId !== $authorId) {
                        $candidatePeerIds[] = $askPeerId;
                    }
                }
            }

            $postSourceType = (string) ($row->post_source_type ?? '');
            $postType = (string) ($row->post_type ?? '');
            $isCreative = in_array($postSourceType, $creativeSourceTypes, true)
                || in_array($postType, $creativePostTypes, true);

            if ($isCreative && ! empty($row->post_source_id) && (string) $row->post_source_id !== $authorId) {
                $candidatePeerIds[] = (string) $row->post_source_id;
            }

            $decodedRowTags = $this->decodeJsonColumn($row->tags ?? null);
            foreach ($decodedRowTags as $t) {
                if (is_string($t) && preg_match('/^[0-9a-fA-F-]{36}$/', $t) && (string) $t !== $authorId) {
                    $candidatePeerIds[] = $t;
                }
            }

            if ($activityCreative && ! empty($activityCreative->user_id) && (string) $activityCreative->user_id !== $authorId) {
                $actType = strtolower((string) $activityCreative->activity_type);
                if (! in_array($actType, ['p2p_meeting', 'p2pmeeting', 'business_deal', 'testimonial', 'referral'], true)) {
                    $candidatePeerIds[] = (string) $activityCreative->user_id;
                }
            }

            if ((string) $row->source_type === 'impact' && ! empty($row->impacted_peer_id) && (string) $row->impacted_peer_id !== $authorId) {
                $candidatePeerIds[] = (string) $row->impacted_peer_id;
            }

            if (! empty($row->accepted_by_id) && (string) $row->accepted_by_id !== $authorId) {
                $candidatePeerIds[] = (string) $row->accepted_by_id;
            }

            // 3. Inline markdown mentions @[Name](uuid)
            $inlineNamedMatches = [];
            if (! empty($row->content_text) && preg_match_all('/@\[([^\]]+)\]\(([0-9a-fA-F-]{36})\)/', (string) $row->content_text, $inlineMatches)) {
                foreach ($inlineMatches[2] as $idx => $matchedUuid) {
                    if ((string) $matchedUuid !== $authorId) {
                        $candidatePeerIds[] = $matchedUuid;
                        $inlineNamedMatches[$matchedUuid] = $inlineMatches[1][$idx] ?? null;
                    }
                }
            }

            foreach (array_unique($candidatePeerIds) as $cPeerId) {
                if ((string) $cPeerId === $authorId) {
                    continue;
                }
                if (! collect($mentions)->contains('id', $cPeerId)) {
                    $recPeer = $mentionedPeers->get($cPeerId);
                    if ($recPeer) {
                        $fallbackName = $inlineNamedMatches[$cPeerId] ?? null;
                        if ($cPeerId === (string) ($row->accepted_by_id ?? '')) {
                            $fallbackName = trim((string) ($row->accepted_by_display_name
                                ?: trim(((string) ($row->accepted_by_first_name ?? '')).' '.((string) ($row->accepted_by_last_name ?? '')))));
                        }
                        $recName = $recPeer->display_name ?: trim(($recPeer->first_name ?? '').' '.($recPeer->last_name ?? ''));
                        $finalName = $recName !== '' ? $recName : ($fallbackName ?: 'Peer Member');
                        $mentions[] = [
                            'id' => (string) $recPeer->id,
                            'name' => $finalName,
                            'profile_photo_url' => $recPeer->profile_photo_file_id
                                ? url('/api/v1/files/'.$recPeer->profile_photo_file_id)
                                : ($recPeer->profile_photo_url ?: null),
                            'is_online' => (bool) ($recPeer->is_online ?? false),
                        ];
                    }
                }
            }

            // Safety filter: author must NEVER be in mentions
            $mentions = array_values(array_filter($mentions, function ($m) use ($authorId) {
                return ! empty($m['id']) && (string) $m['id'] !== $authorId;
            }));

            $isSystemCreative = Post::isSystemPostRow(
                $row->post_source_type ?? $row->source_type ?? null,
                $row->post_type ?? null,
                $row->tags ?? null,
                $author?->email ?? null,
                $author?->display_name ?? null
            );

            $authorPhoto = $author?->profile_photo_file_id
                ? url('/api/v1/files/'.$author->profile_photo_file_id)
                : ($author?->profile_photo_url ?? null);

            $systemPhoto = ($author && $author->isSystemUser() && $authorPhoto)
                ? $authorPhoto
                : User::getSystemUserPhotoUrl();

            $systemAuthorId = ($author && $author->isSystemUser())
                ? (string) $author->id
                : (string) ($author?->id ?? User::getSystemUserId());

            $systemAuthor = [
                'id' => $systemAuthorId,
                'display_name' => 'Peers Global Genie',
                'first_name' => 'Peers Global',
                'last_name' => 'Genie',
                'company_name' => 'Peers Global',
                'designation' => 'Peers Global Genie',
                'level4_category' => null,
                'business_sub_category' => null,
                'profile_photo_url' => $systemPhoto,
                'profile_photo_image' => $systemPhoto,
                'is_online' => true,
            ];

            $item = [
                'type' => (string) $row->source_type,
                'id' => (string) $row->id,
                'content_text' => (string) ($row->content_text ?? ''),
                'post_type' => isset($row->post_type) ? (string) $row->post_type : null,
                'is_verified' => $isVerified,
                'media' => $this->buildFeedMedia($row, $p2pMeetingsById, $fallbackP2pMeetingIdByPostId),
                'tags' => $this->decodeJsonColumn($row->tags),
                'mentions' => $mentions,
                'visibility' => (string) $row->visibility,
                'is_system_announcement' => $isSystemCreative,
                'activity_creative' => $this->formatActivityCreative($activityCreative),
                'author' => $isSystemCreative ? $systemAuthor : ($author ? [
                    'id' => (string) $author->id,
                    'display_name' => $author->display_name,
                    'first_name' => $author->first_name,
                    'last_name' => $author->last_name,
                    'company_name' => $author->company_name ?: null,
                    'designation' => $author->designation ?? null,
                    'level4_category' => $author->level4Category?->name
                        ?? $author->business_sub_category
                        ?? $author->businessCategory?->name
                        ?? $author->mainBusinessCategory?->name
                        ?? null,
                    'business_sub_category' => $author->level4Category?->name
                        ?? $author->business_sub_category
                        ?? null,
                    'profile_photo_url' => $authorPhoto,
                    'profile_photo_image' => $authorPhoto,
                ] : null),
                'user' => $isSystemCreative ? $systemAuthor : ($author ? [
                    'id' => (string) $author->id,
                    'display_name' => $author->display_name,
                    'first_name' => $author->first_name,
                    'last_name' => $author->last_name,
                    'company_name' => $author->company_name ?: null,
                    'designation' => $author->designation ?? null,
                    'profile_photo_url' => $authorPhoto,
                    'profile_photo_image' => $authorPhoto,
                ] : null),
                'circle' => $circle ? [
                    'id' => (string) $circle->id,
                    'name' => $circle->name,
                ] : null,
                'likes_count' => (int) $row->likes_count,
                'comments_count' => (int) $row->comments_count,
                'is_liked_by_me' => (bool) $row->is_liked_by_me,
                'saves_count' => (int) $row->saves_count,
                'is_saved' => (bool) $row->is_saved_by_me,
                'created_at' => $this->formatToDefaultDateTime($row->created_at),
                'updated_at' => $this->formatToDefaultDateTime($row->updated_at),
            ];

            if (isset($row->post_type) && (string) $row->post_type === 'global_peer_certificate') {
                $item['is_downloadable'] = $isDownloadable;
                $item['is_verified_peer'] = in_array((string) $row->author_id, $verifiedAuthorIds, true);
            }

            if (
                (string) $row->source_type === 'post'
                && (string) ($row->post_source_type ?? '') === 'collaboration_post'
                && (string) ($row->post_source_event ?? '') === 'completed'
            ) {
                $acceptedByName = trim((string) ($row->accepted_by_display_name
                    ?: trim(((string) ($row->accepted_by_first_name ?? '')).' '.((string) ($row->accepted_by_last_name ?? '')))));

                $item['accepted_by'] = $row->accepted_by_id ? [
                    'id' => (string) $row->accepted_by_id,
                    'name' => $acceptedByName !== '' ? $acceptedByName : null,
                    'company_name' => $row->accepted_by_company_name,
                    'city' => $row->accepted_by_city,
                ] : null;
            }

            if ((string) $row->source_type === 'impact') {
                $impactedPeer = $row->impacted_peer_id ? $impactedPeers->get((string) $row->impacted_peer_id) : null;

                $item['impact'] = [
                    'action' => $row->impact_action,
                    'impact_date' => $row->impact_date,
                    'life_impacted' => (int) ($row->life_impacted ?? 1),
                    'impacted_peer' => $impactedPeer ? [
                        'id' => (string) $impactedPeer->id,
                        'display_name' => $impactedPeer->display_name,
                        'first_name' => $impactedPeer->first_name,
                        'last_name' => $impactedPeer->last_name,
                    ] : null,
                ];
            }

            if (
                (string) $row->source_type === 'post'
                && ((string) ($row->post_source_type ?? '') === 'ask' || (string) ($row->post_type ?? '') === 'ask')
            ) {
                /** @var Ask|null $ask */
                $ask = (! empty($row->post_source_id) ? $asksById->get((string) $row->post_source_id) : null)
                    ?? $asksByPostId->get((string) $row->id);

                if ($ask) {
                    $item['ask'] = (new AskPreviewResource($ask))->toArray($request);
                }
            }

            if (
                (string) ($row->post_source_type ?? '') === 'referral'
                || (string) ($row->source_type ?? '') === 'referral'
                || (string) ($row->post_type ?? '') === 'referral'
            ) {
                if (empty($item['post_type']) || $item['post_type'] === 'standard') {
                    $item['post_type'] = 'referral';
                }

                $ref = $referralsById->get((string) ($row->post_source_id ?? $row->id));
                $toUser = $ref?->toUser;
                $recipientId = (string) ($row->accepted_by_id ?? $ref?->to_user_id ?? '');
                $accPeer = $recipientId !== '' ? $mentionedPeers->get($recipientId) : null;

                $acceptedByName = trim((string) ($row->accepted_by_display_name
                    ?: trim(((string) ($row->accepted_by_first_name ?? '')).' '.((string) ($row->accepted_by_last_name ?? '')))));
                if ($acceptedByName === '' && $toUser) {
                    $acceptedByName = $toUser->display_name ?: trim(($toUser->first_name ?? '').' '.($toUser->last_name ?? ''));
                }
                if ($acceptedByName === '' && $accPeer) {
                    $acceptedByName = $accPeer->display_name ?: trim(($accPeer->first_name ?? '').' '.($accPeer->last_name ?? ''));
                }
                $photoFileId = $toUser?->profile_photo_file_id ?? $accPeer?->profile_photo_file_id;

                $item['referral'] = [
                    'id' => (string) ($row->post_source_id ?? $row->id),
                    'referral_of' => (string) ($ref?->referral_of ?? $row->impact_action ?? 'Business Referral'),
                    'referral_type' => (string) ($ref?->referral_type ?? 'b2b_referral'),
                    'referral_date' => $row->impact_date ?? $ref?->referral_date,
                    'remarks' => (string) ($ref?->remarks ?? $row->content_text ?? ''),
                    'status_id' => (int) ($ref?->status_id ?? 1),
                    'status_label' => (string) ($ref?->status?->name ?? 'Contacted'),
                    'recipient' => $recipientId !== '' ? [
                        'id' => $recipientId,
                        'name' => $acceptedByName !== '' ? $acceptedByName : 'Peer Member',
                        'company_name' => (string) ($row->accepted_by_company_name ?? $toUser?->company_name ?? ''),
                        'city' => (string) ($row->accepted_by_city ?? $toUser?->city_of_residence ?? $toUser?->city ?? ''),
                        'profile_photo_url' => $photoFileId
                            ? url('/api/v1/files/'.$photoFileId)
                            : null,
                    ] : null,
                ];
            }

            return $item;
        })->values();

        $posts = new LengthAwarePaginator(
            $postItems,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        $timelineAds = $adFeedService->timelineAds();
        $items = $adFeedService->mergeTimelineFeed($postItems, $timelineAds, (int) $posts->currentPage());

        return $this->success([
            'items' => $items,
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    private function formatActivityCreative(?ActivityCreative $creative): ?array
    {
        if (! $creative) {
            return null;
        }

        return [
            'id' => (string) $creative->id,
            'activity_type' => $creative->activity_type,
            'activity_id' => $creative->activity_id,
            'title' => $creative->title,
            'description' => $creative->description,
            'creative_file_id' => $creative->creative_file_id,
            'creative_url' => $creative->creative_url,
        ];
    }

    private function formatToDefaultDateTime(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return Carbon::parse((string) $value)
            ->timezone(config('app.timezone', 'UTC'))
            ->format('Y-m-d H:i:s');
    }

    private function buildFeedMedia(object $row, $p2pMeetingsById, array $fallbackP2pMeetingIdByPostId): array
    {
        $isP2pPost = (string) ($row->source_type ?? '') === 'post' && $this->isP2pMeetingPostRow($row);

        if ($isP2pPost && ! empty($row->post_source_id)) {
            $meeting = $p2pMeetingsById->get((string) $row->post_source_id);
            if ($meeting) {
                return $this->expandP2pMedia($meeting->media);
            }
        }

        if ($isP2pPost) {
            $fallbackMeetingId = $fallbackP2pMeetingIdByPostId[(string) $row->id] ?? null;
            if ($fallbackMeetingId) {
                $meeting = $p2pMeetingsById->get($fallbackMeetingId);
                if ($meeting) {
                    return $this->expandP2pMedia($meeting->media);
                }
            }

            return [];
        }

        return $this->decodeJsonColumn($row->media);
    }

    private function isP2pMeetingPostRow(object $row): bool
    {
        $sourceType = (string) ($row->post_source_type ?? $row->source_type ?? '');
        if ($sourceType === 'p2p_meeting') {
            return true;
        }

        $tags = $this->decodeJsonColumn($row->tags ?? null);

        return in_array('p2p_meeting', $tags, true);
    }

    private function isBusinessDealPostRow(object $row): bool
    {
        $sourceType = (string) ($row->post_source_type ?? $row->source_type ?? '');
        if ($sourceType === 'business_deal') {
            return true;
        }

        $tags = $this->decodeJsonColumn($row->tags ?? null);

        return in_array('business_deal', $tags, true);
    }

    private function isTestimonialPostRow(object $row): bool
    {
        $sourceType = (string) ($row->post_source_type ?? $row->source_type ?? '');
        if ($sourceType === 'testimonial') {
            return true;
        }

        $tags = $this->decodeJsonColumn($row->tags ?? null);

        return in_array('testimonial', $tags, true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function expandP2pMedia(mixed $rawMedia): array
    {
        $media = $this->normalizeMediaPayload($rawMedia);
        if ($media === []) {
            return [];
        }

        $fileIds = collect($media)
            ->map(fn ($item): ?string => is_array($item) ? ($item['file_id'] ?? null) : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $files = FileModel::query()->whereIn('id', $fileIds)->get()->keyBy('id');

        return collect($media)
            ->map(function ($item) use ($files): array {
                $fileId = is_array($item) ? ($item['file_id'] ?? null) : null;
                $mediaType = is_array($item) ? ($item['media_type'] ?? null) : null;
                $file = is_string($fileId) && $fileId !== '' ? $files->get($fileId) : null;

                return [
                    'file_id' => $fileId,
                    'media_type' => $mediaType,
                    'url' => is_string($fileId) && $fileId !== '' ? url('/api/v1/files/'.$fileId) : null,
                    'mime_type' => $file->mime_type ?? $file->mime ?? $file->type ?? null,
                    'original_name' => $file->original_name ?? $file->original_filename ?? $file->name ?? null,
                    'size' => $file->size ?? $file->size_bytes ?? null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeMediaPayload(mixed $rawMedia): array
    {
        if (is_string($rawMedia)) {
            $decoded = json_decode($rawMedia, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($rawMedia) ? $rawMedia : [];
    }

    public function userPosts(Request $request, string $userId)
    {
        if (! Str::isUuid($userId)) {
            return $this->error('User not found', 404);
        }

        $user = User::query()->find($userId);

        if (! $user || $user->status === 'inactive') {
            return $this->error('User not found', 404);
        }

        $authUser = $request->user();
        $perPage = max(1, min((int) $request->integer('per_page', 10), 50));

        $posts = Post::query()
            ->where('user_id', $user->id)
            ->where('posts.status', 'active')
            ->where('posts.is_deleted', false)
            ->whereNull('posts.deleted_at')
            ->with([
                'author:id,display_name,first_name,last_name,profile_photo_file_id',
                'circle:id,name',
            ])
            ->withCount(['likes', 'comments', 'saves'])
            ->when($authUser, function ($query) use ($authUser): void {
                $query->withExists([
                    'likes as is_liked_by_me' => fn ($likeQuery) => $likeQuery->where('user_id', $authUser->id),
                    'saves as is_saved_by_me' => fn ($saveQuery) => $saveQuery->where('user_id', $authUser->id),
                ]);
            })
            ->latest('created_at')
            ->paginate($perPage);

        return $this->success([
            'user_id' => (string) $user->id,
            'total' => $posts->total(),
            'current_page' => $posts->currentPage(),
            'per_page' => $posts->perPage(),
            'last_page' => $posts->lastPage(),
            'items' => PostResource::collection($posts->items()),
        ], 'User posts fetched successfully.');
    }

    private function decodeJsonColumn(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    public function store(StorePostRequest $request, NotificationDispatchService $notifications, NotificationService $notificationService)
    {
        $user = Auth::user();

        if ($user && $user->status === 'inactive') {
            return $this->error('Inactive peers cannot create posts. Your account is in view-only mode.', 403);
        }

        $data = $request->validate([
            'content_text' => ['nullable', 'string', 'max:5000'],
            'media' => ['nullable', 'array'],
            'media.*.id' => ['required_with:media', 'uuid', 'exists:files,id'],
            'media.*.type' => ['required_with:media', 'string', 'max:50'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'visibility' => ['required', 'in:public,connections,members,circle,private,district,global'],
            'circle_id' => ['nullable', 'uuid'],
        ]);

        $mediaItems = [];

        if (! empty($data['media'])) {
            $fileIds = collect($data['media'])->pluck('id')->all();

            $files = File::whereIn('id', $fileIds)->get()->keyBy('id');

            foreach ($data['media'] as $item) {
                $file = $files->get($item['id']);
                if (! $file) {
                    continue;
                }

                $mediaItems[] = [
                    'id' => $file->id,
                    'type' => $item['type'],
                    'url' => url("/api/v1/files/{$file->id}"),
                ];
            }
        }

        $mentionedPeerIds = $this->extractMentionedPeerIds($request, $data['content_text'] ?? null);

        $post = Post::create([
            'user_id' => $user->id,
            'circle_id' => $data['circle_id'] ?? null,
            'content_text' => $data['content_text'] ?? null,
            'media' => $mediaItems ?: [],
            'tags' => $data['tags'] ?? [],
            'visibility' => $data['visibility'],
            'moderation_status' => 'approved',
            'sponsored' => false,
            'is_deleted' => false,
            'status' => 'active',
            'active' => true,
            'is_active' => true,
        ]);

        if (! empty($mentionedPeerIds)) {
            $this->syncPostMentions($post, $mentionedPeerIds);
        }

        $this->dispatchNewPostNotifications($notificationService, $post);
        $this->dispatchMentionNotifications($notifications, $post, $user, $post->content_text, null, $mentionedPeerIds);

        try {
            broadcast(new PostCreatedEvent($post))->toOthers();
        } catch (Throwable $e) {
            Log::warning('Failed broadcasting PostCreatedEvent: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Post created successfully',
            'data' => [
                'id' => $post->id,
                'user_id' => $post->user_id,
                'circle_id' => $post->circle_id,
                'content_text' => $post->content_text,
                'media' => $post->media ?? [],
                'tags' => $post->tags ?? [],
                'mentions' => $this->formatPostMentions($post),
                'visibility' => $post->visibility,
                'sponsored' => $post->sponsored,
                'is_deleted' => $post->is_deleted,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
            ],
        ]);
    }

    public function update(Request $request, string $id, NotificationDispatchService $notifications)
    {
        $user = $request->user();

        if (! $user) {
            return $this->error('Unauthorized', 401);
        }

        $post = Post::where('id', $id)
            ->where('status', 'active')
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->first();

        if (! $post) {
            return $this->error('This post is managed by the system and cannot be modified.', 404);
        }

        if ($post->user_id !== $user->id) {
            return $this->error('You are not authorized to edit this post.', 403);
        }

        if (! empty($post->source_type) || (! empty($post->post_type) && $post->post_type !== 'standard') || ! empty($post->template_id)) {
            return $this->error('Generated template posts cannot be edited.', 403);
        }

        $data = $request->validate([
            'content_text' => ['nullable', 'string', 'max:5000'],
            'media' => ['nullable', 'array'],
            'media.*.id' => ['required_with:media', 'uuid', 'exists:files,id'],
            'media.*.type' => ['required_with:media', 'string', 'max:50'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:100'],
            'visibility' => ['sometimes', 'required', 'in:public,connections,members,circle,private,district,global'],
            'circle_id' => ['nullable', 'uuid'],
        ]);

        if (array_key_exists('content_text', $data)) {
            $post->content_text = $data['content_text'];
        }

        if (array_key_exists('circle_id', $data)) {
            $post->circle_id = $data['circle_id'];
        }

        if (array_key_exists('visibility', $data)) {
            $post->visibility = $data['visibility'];
        }

        if (array_key_exists('tags', $data)) {
            $post->tags = $data['tags'] ?? [];
        }

        if (array_key_exists('media', $data)) {
            $mediaItems = [];
            if (! empty($data['media'])) {
                $fileIds = collect($data['media'])->pluck('id')->all();
                $files = File::whereIn('id', $fileIds)->get()->keyBy('id');

                foreach ($data['media'] as $item) {
                    $file = $files->get($item['id']);
                    if (! $file) {
                        continue;
                    }

                    $mediaItems[] = [
                        'id' => $file->id,
                        'type' => $item['type'],
                        'url' => url("/api/v1/files/{$file->id}"),
                    ];
                }
            }
            $post->media = $mediaItems;
        }

        $post->save();

        if (array_key_exists('content_text', $data) || $request->has('mentions') || $request->has('tagged_peer_ids')) {
            $mentionedPeerIds = $this->extractMentionedPeerIds($request, $post->content_text);
            $this->syncPostMentions($post, $mentionedPeerIds);
            $this->dispatchMentionNotifications($notifications, $post, $user, $post->content_text, null, $mentionedPeerIds);
        }

        return response()->json([
            'success' => true,
            'message' => 'Post updated successfully',
            'data' => [
                'id' => $post->id,
                'user_id' => $post->user_id,
                'circle_id' => $post->circle_id,
                'content_text' => $post->content_text,
                'media' => $post->media ?? [],
                'tags' => $post->tags ?? [],
                'mentions' => $this->formatPostMentions($post),
                'visibility' => $post->visibility,
                'sponsored' => $post->sponsored,
                'is_deleted' => $post->is_deleted,
                'created_at' => $post->created_at,
                'updated_at' => $post->updated_at,
            ],
        ]);
    }

    public function show(Request $request, string $id)
    {
        $post = Post::with(['user.level4Category', 'user.businessCategory', 'user.mainBusinessCategory', 'circle'])
            ->withCount(['likes', 'comments', 'saves'])
            ->withExists([
                'likes as is_liked_by_me' => fn ($query) => $query->where('user_id', $request->user()->id),
                'saves as is_saved_by_me' => fn ($query) => $query->where('user_id', $request->user()->id),
            ])
            ->where('id', $id)
            ->where('posts.status', 'active')
            ->where('posts.is_deleted', false)
            ->whereNull('posts.deleted_at')
            ->first();

        if (! $post) {
            return $this->error('Post not found', 404);
        }

        $isSystemPost = $post->isSystemCreativePost();
        $systemPostPhoto = ($post->relationLoaded('user') && $post->user?->isSystemUser() && $post->user?->profile_photo_url)
            ? $post->user->profile_photo_url
            : User::getSystemUserPhotoUrl();
        $systemPostAuthorId = (string) (
            ($post->relationLoaded('user') && $post->user?->isSystemUser())
                ? $post->user->id
                : ($post->user_id ?? User::getSystemUserId())
        );

        $systemPostAuthor = [
            'id' => $systemPostAuthorId,
            'display_name' => 'Peers Global Genie',
            'first_name' => 'Peers Global',
            'last_name' => 'Genie',
            'company_name' => 'Peers Global',
            'designation' => 'Peers Global Genie',
            'level4_category' => null,
            'business_sub_category' => null,
            'profile_photo_url' => $systemPostPhoto,
            'profile_photo_image' => $systemPostPhoto,
            'is_online' => true,
        ];

        $systemPostUser = [
            'id' => $systemPostAuthorId,
            'display_name' => 'Peers Global Genie',
            'first_name' => 'Peers Global',
            'last_name' => 'Genie',
            'company_name' => 'Peers Global',
            'designation' => 'Peers Global Genie',
            'profile_photo_url' => $systemPostPhoto,
            'profile_photo_image' => $systemPostPhoto,
            'is_online' => true,
        ];

        $responseData = [
            'id' => $post->id,
            'content_text' => $post->content_text,
            'media' => $post->media ?? [],
            'tags' => $post->tags ?? [],
            'mentions' => $this->formatPostMentions($post),
            'visibility' => $post->visibility,
            'is_system_announcement' => $isSystemPost,
            'author' => $isSystemPost ? $systemPostAuthor : ($post->relationLoaded('user') && $post->user ? [
                'id' => $post->user->id,
                'display_name' => $post->user->display_name,
                'first_name' => $post->user->first_name,
                'last_name' => $post->user->last_name,
                'company_name' => $post->user->company_name ?: null,
                'designation' => $post->user->designation ?? null,
                'level4_category' => $post->user->level4Category?->name
                    ?? $post->user->business_sub_category
                    ?? $post->user->businessCategory?->name
                    ?? $post->user->mainBusinessCategory?->name
                    ?? null,
                'business_sub_category' => $post->user->level4Category?->name
                    ?? $post->user->business_sub_category
                    ?? null,
                'profile_photo_url' => $post->user->profile_photo_url,
                'profile_photo_image' => $post->user->profile_photo_url,
            ] : null),
            'user' => $isSystemPost ? $systemPostUser : ($post->relationLoaded('user') && $post->user ? [
                'id' => $post->user->id,
                'display_name' => $post->user->display_name,
                'first_name' => $post->user->first_name,
                'last_name' => $post->user->last_name,
                'company_name' => $post->user->company_name ?: null,
                'designation' => $post->user->designation ?? null,
                'profile_photo_url' => $post->user->profile_photo_url,
                'profile_photo_image' => $post->user->profile_photo_url,
            ] : null),
            'circle' => $post->relationLoaded('circle') && $post->circle ? [
                'id' => $post->circle->id,
                'name' => $post->circle->name,
            ] : null,
            'likes_count' => isset($post->likes_count) ? (int) $post->likes_count : 0,
            'comments_count' => isset($post->comments_count) ? (int) $post->comments_count : 0,
            'is_liked_by_me' => (bool) ($post->is_liked_by_me ?? false),
            'saves_count' => isset($post->saves_count) ? (int) $post->saves_count : 0,
            'is_saved' => (bool) ($post->is_saved_by_me ?? false),
            'created_at' => $post->created_at,
            'updated_at' => $post->updated_at,
        ];

        if ($post->source_type === 'ask' || $post->post_type === 'ask') {
            $askId = $post->source_id;
            if (! $askId) {
                $link = AskTimelineLink::query()->where('post_id', $post->id)->first();
                $askId = $link?->ask_id;
            }
            if ($askId) {
                $ask = Ask::query()
                    ->with(['flow', 'type', 'answers.option', 'district', 'circle', 'user'])
                    ->withCount(['responses', 'matches'])
                    ->find($askId);
                if ($ask) {
                    $responseData['ask'] = (new AskPreviewResource($ask))->toArray($request);
                }
            }
        }

        return $this->success($responseData);
    }

    public function destroy(Request $request, string $id)
    {
        $user = $request->user();

        // User can delete ONLY their own posts
        $post = Post::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $post) {
            return $this->error('Post not found or you are not allowed to delete it', 404);
        }

        $post->is_deleted = true;
        $post->status = 'inactive';
        $post->active = false;
        $post->is_active = false;
        $post->save();
        $post->delete(); // respects SoftDeletes if used on the model

        return $this->success(null, 'Post deleted successfully');
    }

    public function like(Request $request, string $id, NotifyUserService $notifyUserService, NotificationService $notifications)
    {
        $authUser = $request->user();

        if ($authUser && $authUser->status === 'inactive') {
            return $this->error('Inactive peers cannot like posts. Your account is in view-only mode.', 403);
        }

        $post = Post::where('id', $id)
            ->where('status', 'active')
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->first();

        if (! $post) {
            return $this->error('Post not found', 404);
        }

        $like = PostLike::firstOrCreate([
            'post_id' => $post->id,
            'user_id' => $authUser->id,
        ]);

        if ($like->wasRecentlyCreated) {
            $this->sendPostLikeNotification($post, $like, $authUser, $notifications);
        }

        $likeCount = PostLike::where('post_id', $post->id)->count();

        return $this->success(['like_count' => $likeCount], 'Post liked');
    }

    public function unlike(Request $request, string $id)
    {
        $authUser = $request->user();

        $post = Post::where('id', $id)
            ->where('status', 'active')
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->first();

        if (! $post) {
            return $this->error('Post not found', 404);
        }

        PostLike::where('post_id', $post->id)
            ->where('user_id', $authUser->id)
            ->delete();

        $likeCount = PostLike::where('post_id', $post->id)->count();

        return $this->success(['like_count' => $likeCount], 'Post unliked');
    }

    public function storeComment(StorePostCommentRequest $request, string $id, NotifyUserService $notifyUserService, NotificationService $notifications, NotificationDispatchService $mentionNotifications)
    {
        $authUser = $request->user();

        if ($authUser && $authUser->status === 'inactive') {
            return $this->error('Inactive peers cannot comment on posts. Your account is in view-only mode.', 403);
        }

        $post = Post::where('id', $id)
            ->where('status', 'active')
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->first();

        if (! $post) {
            return $this->error('Post not found', 404);
        }

        $data = $request->validated();

        $comment = new PostComment;
        $comment->post_id = $post->id;
        $comment->user_id = $authUser->id;
        $comment->content = $data['content'];
        $comment->parent_id = $data['parent_id'] ?? null;
        $comment->save();

        $this->sendPostCommentNotification($post, $comment, $authUser, $notifications);

        $this->dispatchMentionNotifications($mentionNotifications, $post, $authUser, $comment->content, $comment);

        $comment->load('user.level4Category');

        return $this->success(new PostCommentResource($comment), 'Comment added', 201);
    }

    public function listComments(Request $request, string $id)
    {
        $post = Post::where('id', $id)
            ->where('status', 'active')
            ->where('is_deleted', false)
            ->whereNull('deleted_at')
            ->first();

        if (! $post) {
            return $this->error('Post not found', 404);
        }

        $perPage = (int) $request->input('per_page', 50);
        $perPage = max(1, min($perPage, 100));

        $paginator = PostComment::with('user.level4Category')
            ->where('post_id', $post->id)
            ->orderBy('created_at', 'asc')
            ->paginate($perPage);

        $data = [
            'items' => PostCommentResource::collection($paginator),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];

        return $this->success($data);
    }

    private function dispatchNewPostNotifications(NotificationService $notifications, Post $post): void
    {
        try {
            $notifications->sendPostPublishedNotification($post);
        } catch (Throwable $e) {
            Log::warning('New post notification failed', ['post_id' => (string) $post->id, 'error' => $e->getMessage()]);
        }
    }

    private function dispatchMentionNotifications(NotificationDispatchService $notifications, Post $post, User $actor, ?string $text, ?PostComment $comment, array $mentionedPeerIds = []): void
    {
        $text = $text ?? '';
        $mentionedUsers = $this->mentionedUsers($text, $mentionedPeerIds)->reject(fn (User $user) => (string) $user->id === (string) $actor->id)->values();
        if ($mentionedUsers->isEmpty()) {
            return;
        }
        try {
            $notifications->sendCampaignNotification(
                'user_mention_notification',
                $mentionedUsers,
                ['person' => $this->displayName($actor), 'post_preview_content' => Str::limit($text, 120), 'comment_preview_content' => Str::limit($text, 120)],
                ['screen' => 'post_details', 'post_id' => (string) $post->id, 'comment_id' => $comment?->id, 'mentioned_by' => (string) $actor->id, 'type' => 'mention'],
                $actor,
                $comment ?: $post,
                ['type' => 'mention', 'reference_type' => $comment ? 'post_comment' : 'post', 'reference_id' => (string) ($comment?->id ?? $post->id), 'dedupe_key' => 'mention:'.($comment?->id ?? $post->id)]
            );
        } catch (Throwable $e) {
            Log::warning('Mention notification failed', ['post_id' => (string) $post->id, 'error' => $e->getMessage()]);
        }
    }

    private function mentionedUsers(string $text, array $additionalPeerIds = []): Collection
    {
        $peerIds = [];
        if (preg_match_all('/@\[([^\]]+)\]\(([0-9a-fA-F-]{36})\)/', $text, $matches)) {
            $peerIds = array_merge($peerIds, $matches[2]);
        }
        if (preg_match_all('/@\{([0-9a-fA-F-]{36}):([^\}]+)\}/', $text, $matches)) {
            $peerIds = array_merge($peerIds, $matches[1]);
        }
        $peerIds = array_values(array_unique(array_filter(array_merge($peerIds, $additionalPeerIds))));

        $users = collect();
        if ($peerIds !== []) {
            $users = User::query()->whereIn('id', $peerIds)->get();
        }

        preg_match_all('/@([A-Za-z0-9_.-]{2,50})/', $text, $legacyMatches);
        $handles = collect($legacyMatches[1] ?? [])
            ->filter(fn ($h) => ! in_array($h, $peerIds, true))
            ->unique()
            ->values();

        if ($handles->isNotEmpty()) {
            $legacyUsers = User::query()->where(function ($query) use ($handles): void {
                foreach ($handles as $handle) {
                    $query->orWhere('display_name', 'ilike', $handle)->orWhere('name', 'ilike', $handle)->orWhere('email', 'ilike', $handle.'@%');
                }
            })->get();
            $users = $users->concat($legacyUsers)->unique('id')->values();
        }

        return $users;
    }

    /**
     * Extract and normalize peer UUIDs from mentions array, tagged_peer_ids, and content_text markdown.
     *
     * @return array<int, string>
     */
    private function extractMentionedPeerIds(Request $request, ?string $contentText): array
    {
        $peerIds = [];

        // 1. From payload mentions array: [{"id": "uuid", "name": "..."}]
        $rawMentions = $request->input('mentions');
        if (is_array($rawMentions)) {
            foreach ($rawMentions as $item) {
                $id = is_array($item) ? ($item['id'] ?? null) : (is_string($item) ? $item : null);
                if (is_string($id) && Str::isUuid($id)) {
                    $peerIds[] = $id;
                }
            }
        }

        // 2. From payload tagged_peer_ids array
        $tagged = $request->input('tagged_peer_ids');
        if (is_array($tagged)) {
            foreach ($tagged as $id) {
                if (is_string($id) && Str::isUuid($id)) {
                    $peerIds[] = $id;
                }
            }
        }

        // 3. From markdown format: @[Peer Name](uuid)
        if (! empty($contentText)) {
            if (preg_match_all('/@\[([^\]]+)\]\(([0-9a-fA-F-]{36})\)/', $contentText, $matches)) {
                foreach ($matches[2] as $id) {
                    if (Str::isUuid($id)) {
                        $peerIds[] = $id;
                    }
                }
            }

            // 4. From tag format: @{uuid:Peer Name}
            if (preg_match_all('/@\{([0-9a-fA-F-]{36}):([^\}]+)\}/', $contentText, $matches)) {
                foreach ($matches[1] as $id) {
                    if (Str::isUuid($id)) {
                        $peerIds[] = $id;
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($peerIds)));
    }

    private function syncPostMentions(Post $post, array $peerIds): void
    {
        PostMention::where('post_id', $post->id)->delete();

        foreach ($peerIds as $peerId) {
            PostMention::create([
                'post_id' => $post->id,
                'peer_id' => $peerId,
            ]);
        }
    }

    private function formatPostMentions(Post $post): array
    {
        $authorId = (string) ($post->user_id ?? '');
        $mentions = [];

        // 1. Check post_mentions table (excluding author)
        $postMentions = PostMention::where('post_id', $post->id)->with('peer')->get();
        foreach ($postMentions as $pm) {
            $peer = $pm->peer;
            if ($peer && (string) $peer->id !== $authorId) {
                $name = $peer->display_name ?: trim(($peer->first_name ?? '').' '.($peer->last_name ?? ''));
                $mentions[] = [
                    'id' => (string) $peer->id,
                    'name' => $name !== '' ? $name : 'Peer Member',
                    'profile_photo_url' => $peer->profile_photo_file_id
                        ? url('/api/v1/files/'.$peer->profile_photo_file_id)
                        : ($peer->profile_photo_url ?: null),
                    'is_online' => (bool) ($peer->is_online ?? false),
                ];
            }
        }

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

        $candidatePeerIds = [];
        $inlineNamedMatches = [];

        $sourceType = (string) ($post->source_type ?? '');
        $postType = (string) ($post->post_type ?? '');
        $tags = is_array($post->tags) ? $post->tags : $this->decodeJsonColumn($post->tags ?? null);
        $activityCreative = ActivityCreative::where('post_id', $post->id)->where('status', 'active')->first();

        // Resolve counterpart peer for P2P Meeting
        if ($sourceType === 'p2p_meeting' || in_array('p2p_meeting', $tags, true) || ($activityCreative && in_array(strtolower((string) $activityCreative->activity_type), ['p2p_meeting', 'p2pmeeting'], true))) {
            $meetingId = $post->source_id ?? $activityCreative?->activity_id;
            $meeting = $meetingId ? P2pMeeting::find($meetingId) : null;
            if (! $meeting && $authorId !== '' && $post->created_at) {
                $meeting = P2pMeeting::where(function ($q) use ($authorId) {
                    $q->where('initiator_user_id', $authorId)->orWhere('peer_user_id', $authorId);
                })
                ->whereBetween('created_at', [$post->created_at->copy()->subMinutes(5), $post->created_at->copy()->addMinutes(5)])
                ->latest('created_at')
                ->first();
            }
            if ($meeting) {
                $counterpart = (string) $meeting->initiator_user_id === $authorId ? (string) $meeting->peer_user_id : (string) $meeting->initiator_user_id;
                if ($counterpart !== '' && $counterpart !== $authorId) {
                    $candidatePeerIds[] = $counterpart;
                }
            }
        }

        // Resolve counterpart peer for Business Deal
        if ($sourceType === 'business_deal' || in_array('business_deal', $tags, true) || ($activityCreative && strtolower((string) $activityCreative->activity_type) === 'business_deal')) {
            $dealId = $post->source_id ?? $activityCreative?->activity_id;
            $deal = $dealId ? BusinessDeal::find($dealId) : null;
            if (! $deal && $authorId !== '' && $post->created_at) {
                $deal = BusinessDeal::where(function ($q) use ($authorId) {
                    $q->where('from_user_id', $authorId)->orWhere('to_user_id', $authorId);
                })
                ->whereBetween('created_at', [$post->created_at->copy()->subMinutes(5), $post->created_at->copy()->addMinutes(5)])
                ->latest('created_at')
                ->first();
            }
            if ($deal) {
                $counterpart = (string) $deal->from_user_id === $authorId ? (string) $deal->to_user_id : (string) $deal->from_user_id;
                if ($counterpart !== '' && $counterpart !== $authorId) {
                    $candidatePeerIds[] = $counterpart;
                }
            }
        }

        // Resolve counterpart peer for Testimonial
        if ($sourceType === 'testimonial' || in_array('testimonial', $tags, true) || ($activityCreative && strtolower((string) $activityCreative->activity_type) === 'testimonial')) {
            $testId = $post->source_id ?? $activityCreative?->activity_id;
            $test = $testId ? Testimonial::find($testId) : null;
            if (! $test && $authorId !== '' && $post->created_at) {
                $test = Testimonial::where(function ($q) use ($authorId) {
                    $q->where('from_user_id', $authorId)->orWhere('to_user_id', $authorId);
                })
                ->whereBetween('created_at', [$post->created_at->copy()->subMinutes(5), $post->created_at->copy()->addMinutes(5)])
                ->latest('created_at')
                ->first();
            }
            if ($test) {
                $counterpart = (string) $test->from_user_id === $authorId ? (string) $test->to_user_id : (string) $test->from_user_id;
                if ($counterpart !== '' && $counterpart !== $authorId) {
                    $candidatePeerIds[] = $counterpart;
                }
            }
        }

        // Resolve counterpart peer for Referral
        if ($sourceType === 'referral' || in_array('referral', $tags, true) || $postType === 'referral') {
            $refId = $post->source_id ?? $post->id;
            $ref = $refId ? Referral::find($refId) : null;
            if ($ref && ! empty($ref->to_user_id) && (string) $ref->to_user_id !== $authorId) {
                $candidatePeerIds[] = (string) $ref->to_user_id;
            }
        }

        // Resolve matched peers and responders for Ask
        if ($sourceType === 'ask' || in_array('ask', $tags, true) || $postType === 'ask') {
            $askId = $post->source_id;
            if (! $askId) {
                $link = AskTimelineLink::query()->where('post_id', $post->id)->first();
                $askId = $link?->ask_id;
            }
            if ($askId) {
                $ask = Ask::query()
                    ->with([
                        'matches:id,ask_id,matched_user_id',
                        'responses:id,ask_id,responder_user_id,introduced_user_id,status',
                    ])
                    ->find($askId);

                if ($ask) {
                    if ($ask->relationLoaded('responses') && $ask->responses) {
                        foreach ($ask->responses as $resp) {
                            if (! empty($resp->responder_user_id) && (string) $resp->responder_user_id !== $authorId) {
                                $candidatePeerIds[] = (string) $resp->responder_user_id;
                            }
                            if (! empty($resp->introduced_user_id) && (string) $resp->introduced_user_id !== $authorId) {
                                $candidatePeerIds[] = (string) $resp->introduced_user_id;
                            }
                        }
                    }
                    if ($ask->relationLoaded('matches') && $ask->matches) {
                        foreach ($ask->matches as $match) {
                            if (! empty($match->matched_user_id) && (string) $match->matched_user_id !== $authorId) {
                                $candidatePeerIds[] = (string) $match->matched_user_id;
                            }
                        }
                    }
                }
            }
        }

        // Text regex name match fallback
        if (! empty($post->content_text)) {
            $text = (string) $post->content_text;
            $matchedName = null;
            if (preg_match('/sharing a moment of gratitude to\s+([^.]+?)(?:\.|$)/i', $text, $m)) {
                $matchedName = trim($m[1]);
            } elseif (preg_match('/made a deal with\s+(.+?)\s+of amount/i', $text, $m)) {
                $matchedName = trim($m[1]);
            } elseif (preg_match('/connected with\s+([^,]+?),/i', $text, $m)) {
                $matchedName = trim($m[1]);
            } elseif (preg_match('/referral for\s+.+?\s+to\s+([^.\n]+?)(?:\.|\n|$)/i', $text, $m)) {
                $matchedName = trim($m[1]);
            }

            if ($matchedName !== null && $matchedName !== '' && strtolower($matchedName) !== 'peer') {
                $matchedUser = User::query()
                    ->where(function ($q) use ($matchedName) {
                        $q->where('display_name', $matchedName)
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) = ?", [$matchedName]);
                    })
                    ->first(['id', 'display_name', 'first_name', 'last_name', 'profile_photo_file_id', 'profile_photo_url', 'is_online']);

                if ($matchedUser && (string) $matchedUser->id !== $authorId) {
                    $candidatePeerIds[] = (string) $matchedUser->id;
                }
            }
        }

        if ((in_array($sourceType, $creativeTypes, true) || in_array($postType, $creativeTypes, true)) && ! empty($post->source_id) && (string) $post->source_id !== $authorId) {
            $candidatePeerIds[] = (string) $post->source_id;
        }

        foreach ($tags as $t) {
            if (is_string($t) && preg_match('/^[0-9a-fA-F-]{36}$/', $t) && (string) $t !== $authorId) {
                $candidatePeerIds[] = $t;
            }
        }

        if ($activityCreative && ! empty($activityCreative->user_id) && (string) $activityCreative->user_id !== $authorId) {
            $actType = strtolower((string) $activityCreative->activity_type);
            if (! in_array($actType, ['p2p_meeting', 'p2pmeeting', 'business_deal', 'testimonial', 'referral'], true)) {
                $candidatePeerIds[] = (string) $activityCreative->user_id;
            }
        }

        // Inline markdown mentions @[Name](uuid)
        if (! empty($post->content_text) && preg_match_all('/@\[([^\]]+)\]\(([0-9a-fA-F-]{36})\)/', (string) $post->content_text, $inlineMatches)) {
            foreach ($inlineMatches[2] as $idx => $matchedUuid) {
                if ((string) $matchedUuid !== $authorId) {
                    $candidatePeerIds[] = $matchedUuid;
                    $inlineNamedMatches[$matchedUuid] = $inlineMatches[1][$idx] ?? null;
                }
            }
        }

        $alreadyMentionedIds = collect($mentions)->pluck('id')->all();
        $unresolvedPeerIds = array_values(array_diff(array_unique($candidatePeerIds), $alreadyMentionedIds));

        if (! empty($unresolvedPeerIds)) {
            $users = User::whereIn('id', $unresolvedPeerIds)->get();
            foreach ($users as $peer) {
                if ((string) $peer->id === $authorId) {
                    continue;
                }
                $name = $peer->display_name ?: trim(($peer->first_name ?? '').' '.($peer->last_name ?? ''));
                $fallbackName = $inlineNamedMatches[(string) $peer->id] ?? null;
                $finalName = $name !== '' ? $name : ($fallbackName ?: 'Peer Member');
                $mentions[] = [
                    'id' => (string) $peer->id,
                    'name' => $finalName,
                    'profile_photo_url' => $peer->profile_photo_file_id
                        ? url('/api/v1/files/'.$peer->profile_photo_file_id)
                        : ($peer->profile_photo_url ?: null),
                    'is_online' => (bool) ($peer->is_online ?? false),
                ];
            }
        }

        return array_values(array_filter($mentions, fn ($m) => (string) ($m['id'] ?? '') !== $authorId));
    }

    private function postPreview(Post $post): string
    {
        return Str::limit(trim((string) $post->content_text), 120) ?: 'New post';
    }

    private function displayName(User $user): string
    {
        return trim((string) ($user->display_name ?? '')) ?: trim(((string) ($user->first_name ?? '')).' '.((string) ($user->last_name ?? ''))) ?: (string) ($user->name ?? 'A member');
    }

    private function sendPostLikeNotification(Post $post, PostLike $like, User $authUser, NotificationService $notifications): void
    {
        $targets = [];
        $likerName = $this->displayName($authUser);

        if (in_array($post->post_type, ['introduction', 'birthday', 'anniversary', 'global_peer_certificate'], true)) {
            $subjectUser = User::find($post->source_id);
            if ($subjectUser) {
                $subjectName = $this->displayName($subjectUser);
                if ($post->post_type === 'introduction') {
                    if ((string) $subjectUser->id !== (string) $authUser->id) {
                        $targets[] = [
                            'user' => $subjectUser,
                            'title' => 'New Like on Introduction',
                            'body' => $likerName.' liked your introduction post',
                        ];
                    }
                    if ($subjectUser->introduced_by) {
                        $introducerUser = User::find($subjectUser->introduced_by);
                        if ($introducerUser && (string) $introducerUser->id !== (string) $authUser->id) {
                            $targets[] = [
                                'user' => $introducerUser,
                                'title' => 'New Like on Introduction',
                                'body' => $likerName.' liked the introduction of '.$subjectName,
                            ];
                        }
                    }
                } else {
                    if ((string) $subjectUser->id !== (string) $authUser->id) {
                        $title = match ($post->post_type) {
                            'birthday' => 'Birthday Wish Liked',
                            'anniversary' => 'Anniversary Wish Liked',
                            'global_peer_certificate' => 'Certificate Liked',
                            default => 'Post Liked',
                        };
                        $body = match ($post->post_type) {
                            'birthday' => $likerName.' liked your birthday post',
                            'anniversary' => $likerName.' liked your anniversary post',
                            'global_peer_certificate' => $likerName.' liked your Global Peer Certificate post',
                            default => $likerName.' liked your post',
                        };
                        $targets[] = [
                            'user' => $subjectUser,
                            'title' => $title,
                            'body' => $body,
                        ];
                    }
                }
            }
        } else {
            if ((string) $post->user_id !== (string) $authUser->id) {
                $postOwner = User::find($post->user_id);
                if ($postOwner) {
                    $targets[] = [
                        'user' => $postOwner,
                        'title' => 'Post Liked',
                        'body' => $likerName.' liked your post',
                    ];
                }
            }
        }

        foreach ($targets as $target) {
            try {
                $notifications->sendToUser(
                    $target['user'],
                    'post_like',
                    $target['title'],
                    $target['body'],
                    [
                        'post_id' => (string) $post->id,
                        'like_id' => (string) $like->id,
                        'actor_id' => (string) $authUser->id,
                        'screen' => 'post_detail',
                        'tap_destination' => 'post_detail',
                        'reference_type' => 'post',
                        'reference_id' => (string) $post->id,
                    ],
                    [
                        'actor_id' => (string) $authUser->id,
                        'channel' => 'push',
                        'reference_type' => 'post',
                        'reference_id' => (string) $post->id,
                        'dedupe_key' => 'post_like:'.$post->id.':'.$authUser->id.':'.$target['user']->id,
                    ]
                );
            } catch (Throwable $e) {
                Log::warning('Post like notification failed', [
                    'post_id' => (string) $post->id,
                    'liker_user_id' => (string) $authUser->id,
                    'recipient_user_id' => (string) $target['user']->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function sendPostCommentNotification(Post $post, PostComment $comment, User $authUser, NotificationService $notifications): void
    {
        $targets = [];
        $commenterName = $this->displayName($authUser);
        $preview = Str::limit(trim((string) $comment->content), 120);

        if (in_array($post->post_type, ['introduction', 'birthday', 'anniversary', 'global_peer_certificate'], true)) {
            $subjectUser = User::find($post->source_id);
            if ($subjectUser) {
                $subjectName = $this->displayName($subjectUser);
                if ($post->post_type === 'introduction') {
                    if ((string) $subjectUser->id !== (string) $authUser->id) {
                        $targets[] = [
                            'user' => $subjectUser,
                            'title' => 'New Comment on Introduction',
                            'body' => $commenterName.' commented on your introduction post',
                        ];
                    }
                    if ($subjectUser->introduced_by) {
                        $introducerUser = User::find($subjectUser->introduced_by);
                        if ($introducerUser && (string) $introducerUser->id !== (string) $authUser->id) {
                            $targets[] = [
                                'user' => $introducerUser,
                                'title' => 'New Comment on Introduction',
                                'body' => $commenterName.' commented on the introduction of '.$subjectName,
                            ];
                        }
                    }
                } else {
                    if ((string) $subjectUser->id !== (string) $authUser->id) {
                        $title = match ($post->post_type) {
                            'birthday' => 'New Birthday Comment',
                            'anniversary' => 'New Anniversary Comment',
                            'global_peer_certificate' => 'New Comment on Certificate',
                            default => 'New Comment on Post',
                        };
                        $body = match ($post->post_type) {
                            'birthday' => $commenterName.' commented on your birthday post',
                            'anniversary' => $commenterName.' commented on your anniversary post',
                            'global_peer_certificate' => $commenterName.' commented on your Global Peer Certificate post',
                            default => $commenterName.' commented on your post',
                        };
                        $targets[] = [
                            'user' => $subjectUser,
                            'title' => $title,
                            'body' => $body,
                        ];
                    }
                }
            }
        } else {
            if ((string) $post->user_id !== (string) $authUser->id) {
                $postOwner = User::find($post->user_id);
                if ($postOwner) {
                    $targets[] = [
                        'user' => $postOwner,
                        'title' => 'New Comment on Post',
                        'body' => $commenterName.' commented on your post',
                    ];
                }
            }
        }

        foreach ($targets as $target) {
            try {
                $notifications->sendToUser(
                    $target['user'],
                    'post_comment',
                    $target['title'],
                    $preview ?: $target['body'],
                    [
                        'post_id' => (string) $post->id,
                        'comment_id' => (string) $comment->id,
                        'actor_id' => (string) $authUser->id,
                        'screen' => 'post_detail',
                        'tap_destination' => 'post_detail',
                        'reference_type' => 'post',
                        'reference_id' => (string) $post->id,
                    ],
                    [
                        'actor_id' => (string) $authUser->id,
                        'channel' => 'push',
                        'reference_type' => 'post',
                        'reference_id' => (string) $post->id,
                        'dedupe_key' => 'post_comment:'.$comment->id.':'.$target['user']->id,
                    ]
                );
            } catch (Throwable $e) {
                Log::warning('Post comment notification failed', [
                    'post_id' => (string) $post->id,
                    'comment_id' => (string) $comment->id,
                    'recipient_user_id' => (string) $target['user']->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
