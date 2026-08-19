<?php

namespace App\Http\Controllers\Admin\Users;

use App\Http\Controllers\Controller;
use App\Models\AdminCampaign;
use App\Models\BrandPartner;
use App\Models\Circle;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        if ($search === '') {
            $results = collect();
            $users = User::query()
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->with(['circleMembers' => function ($query) {
                    $query->where('status', 'approved')
                        ->whereNull('deleted_at')
                        ->orderByDesc('joined_at')
                        ->with(['circle:id,name']);
                }])
                ->orderByRaw("COALESCE(NULLIF(display_name,''), NULLIF(TRIM(CONCAT_WS(' ', first_name, last_name)),''), email) ASC")
                ->limit(50)
                ->get();

            foreach ($users as $user) {
                [$name, $company, $city, $circle] = $user->adminDisplayParts();
                $results->push([
                    'id' => $user->id,
                    'type' => 'member',
                    'section' => 'Members',
                    'section_icon' => 'bi-people-fill',
                    'name' => $name,
                    'company' => $company,
                    'city' => $city,
                    'circle' => $circle,
                    'subtext' => collect([$company, $city, $circle])->filter()->implode(' • ') ?: 'Member Profile',
                    'label' => $user->adminDisplayLabel(),
                    'label_inline' => $user->adminDisplayInlineLabel(),
                    'url' => route('admin.users.show', $user->id),
                ]);
            }

            return response()->json($results);
        }

        $words = array_filter(explode(' ', $search));
        $driver = DB::connection()->getDriverName();
        $likeOperator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';
        $results = collect();

        // 1. Search Members (Users)
        $users = User::query()
            ->whereNull('deleted_at')
            ->where(function ($query) use ($words, $driver, $likeOperator): void {
                foreach ($words as $word) {
                    $like = "%{$word}%";
                    $query->where(function ($sub) use ($like, $driver, $likeOperator): void {
                        $sub->where('display_name', $likeOperator, $like)
                            ->orWhere('first_name', $likeOperator, $like)
                            ->orWhere('last_name', $likeOperator, $like)
                            ->orWhere('email', $likeOperator, $like)
                            ->orWhere('company_name', $likeOperator, $like)
                            ->orWhere('city', $likeOperator, $like)
                            ->orWhere('phone', $likeOperator, $like);

                        if ($driver === 'sqlite') {
                            $sub->orWhereRaw("LOWER(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ?", [strtolower($like)]);
                        } else {
                            $sub->orWhereRaw("TRIM(CONCAT_WS(' ', COALESCE(first_name, ''), COALESCE(last_name, ''))) {$likeOperator} ?", [$like]);
                        }
                    });
                }
            })
            ->with(['circleMembers' => function ($query) {
                $query->where('status', 'approved')
                    ->whereNull('deleted_at')
                    ->orderByDesc('joined_at')
                    ->with(['circle:id,name']);
            }])
            ->orderByRaw("COALESCE(NULLIF(display_name,''), NULLIF(TRIM(CONCAT_WS(' ', first_name, last_name)),''), email) ASC")
            ->limit(50)
            ->get();

        foreach ($users as $user) {
            [$name, $company, $city, $circle] = $user->adminDisplayParts();
            $results->push([
                'id' => $user->id,
                'type' => 'member',
                'section' => 'Members',
                'section_icon' => 'bi-people-fill',
                'name' => $name,
                'company' => $company,
                'city' => $city,
                'circle' => $circle,
                'subtext' => collect([$company, $city, $circle])->filter()->implode(' • ') ?: 'Member Profile',
                'label' => $user->adminDisplayLabel(),
                'label_inline' => $user->adminDisplayInlineLabel(),
                'url' => route('admin.users.show', $user->id),
            ]);
        }

        // 2. Search Circles
        try {
            $circles = Circle::query()
                ->whereNull('deleted_at')
                ->where(function ($query) use ($words, $likeOperator): void {
                    foreach ($words as $word) {
                        $like = "%{$word}%";
                        $query->where(function ($sub) use ($like, $likeOperator): void {
                            $sub->where('name', $likeOperator, $like)
                                ->orWhere('city', $likeOperator, $like)
                                ->orWhere('code', $likeOperator, $like);
                        });
                    }
                })
                ->orderBy('name')
                ->limit(3)
                ->get();

            foreach ($circles as $c) {
                $results->push([
                    'id' => $c->id,
                    'type' => 'circle',
                    'section' => 'Circles',
                    'section_icon' => 'bi-pie-chart-fill',
                    'name' => $c->name,
                    'company' => null,
                    'city' => $c->city,
                    'circle' => null,
                    'subtext' => collect(['Circle', $c->city, $c->code])->filter()->implode(' • '),
                    'label' => $c->name,
                    'label_inline' => $c->name,
                    'url' => route('admin.circles.show', $c->id),
                ]);
            }
        } catch (\Throwable $e) {
            // Log fallback
        }

        // 3. Search Events
        try {
            $events = Event::query()
                ->whereNull('deleted_at')
                ->where(function ($query) use ($words, $likeOperator): void {
                    foreach ($words as $word) {
                        $like = "%{$word}%";
                        $query->where(function ($sub) use ($like, $likeOperator): void {
                            $sub->where('title', $likeOperator, $like)
                                ->orWhere('location_text', $likeOperator, $like)
                                ->orWhere('event_type', $likeOperator, $like);
                        });
                    }
                })
                ->orderByDesc('created_at')
                ->limit(3)
                ->get();

            foreach ($events as $ev) {
                $results->push([
                    'id' => $ev->id,
                    'type' => 'event',
                    'section' => 'Events',
                    'section_icon' => 'bi-calendar-event-fill',
                    'name' => $ev->title,
                    'company' => null,
                    'city' => $ev->location_text,
                    'circle' => null,
                    'subtext' => collect(['Event', $ev->event_type ?? $ev->location_text])->filter()->implode(' • '),
                    'label' => $ev->title,
                    'label_inline' => $ev->title,
                    'url' => route('admin.events.show', $ev->id),
                ]);
            }
        } catch (\Throwable $e) {
            // Log fallback
        }

        // 4. Search Brand Partners
        try {
            $brands = BrandPartner::query()
                ->where(function ($query) use ($words, $likeOperator): void {
                    foreach ($words as $word) {
                        $like = "%{$word}%";
                        $query->where(function ($sub) use ($like, $likeOperator): void {
                            $sub->where('name', $likeOperator, $like)
                                ->orWhere('contact_email', $likeOperator, $like)
                                ->orWhere('offer_title', $likeOperator, $like);
                        });
                    }
                })
                ->orderBy('name')
                ->limit(3)
                ->get();

            foreach ($brands as $b) {
                $results->push([
                    'id' => $b->id,
                    'type' => 'brand',
                    'section' => 'Brand Partners',
                    'section_icon' => 'bi-briefcase-fill',
                    'name' => $b->name,
                    'company' => null,
                    'city' => null,
                    'circle' => null,
                    'subtext' => collect(['Brand Partner', $b->contact_email])->filter()->implode(' • '),
                    'label' => $b->name,
                    'label_inline' => $b->name,
                    'url' => route('admin.brand-partners.show', $b->id),
                ]);
            }
        } catch (\Throwable $e) {
            // Log fallback
        }

        // 5. Search Campaigns
        try {
            $campaigns = AdminCampaign::query()
                ->whereNull('deleted_at')
                ->where(function ($query) use ($words, $likeOperator): void {
                    foreach ($words as $word) {
                        $like = "%{$word}%";
                        $query->where(function ($sub) use ($like, $likeOperator): void {
                            $sub->where('title', $likeOperator, $like)
                                ->orWhere('subject', $likeOperator, $like);
                        });
                    }
                })
                ->orderByDesc('created_at')
                ->limit(3)
                ->get();

            foreach ($campaigns as $camp) {
                $results->push([
                    'id' => $camp->id,
                    'type' => 'campaign',
                    'section' => 'Campaigns',
                    'section_icon' => 'bi-megaphone-fill',
                    'name' => $camp->title,
                    'company' => null,
                    'city' => null,
                    'circle' => null,
                    'subtext' => collect(['Campaign', ucfirst($camp->status)])->filter()->implode(' • '),
                    'label' => $camp->title,
                    'label_inline' => $camp->title,
                    'url' => route('admin.campaigns.show', $camp->id),
                ]);
            }
        } catch (\Throwable $e) {
            // Log fallback
        }

        if ($results->isEmpty()) {
            Log::info('admin.global.search.no_results', [
                'search' => $search,
            ]);
        }

        return response()->json($results->values());
    }
}
