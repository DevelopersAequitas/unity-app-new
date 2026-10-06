<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShareController extends Controller
{
    /**
     * Handle share redirection for Peers Global Unity, Greenpreneur Unity, and Fempreneur Unity.
     */
    public function handle(Request $request): View
    {
        $host = strtolower($request->getHost());
        $queryString = parse_url($request->getRequestUri(), PHP_URL_QUERY) ?? $request->server->get('QUERY_STRING') ?? $request->getQueryString();
        $querySuffix = $queryString ? "?{$queryString}" : '';

        // 1. Detect Product Instance dynamically
        $instance = config('app.instance');
        if (! $instance) {
            if (str_contains($host, 'fempreneur') || str_contains($host, 'fampreneur')) {
                $instance = 'fempreneur';
            } elseif (str_contains($host, 'greenpreneur')) {
                $instance = 'greenpreneur';
            } else {
                $instance = 'peers';
            }
        }

        // 2. Configure app stores and schemes per product
        switch ($instance) {
            case 'fempreneur':
                $scheme = 'fempreneur';
                $appId = '6799073359';
                $appStoreUrl = 'https://apps.apple.com/in/app/fempreneur-unity/id6799073359';
                $playStoreUrl = 'https://play.google.com/store/apps/details?id=com.unity.fempreneur';
                $appName = 'Fempreneur Unity';
                break;

            case 'greenpreneur':
                $scheme = 'greenpreneur';
                $appId = '6782311572';
                $appStoreUrl = 'https://apps.apple.com/in/app/greenpreneur-unity/id6782311572';
                $playStoreUrl = 'https://play.google.com/store/apps/details?id=com.unity.greenpreneur';
                $appName = 'Greenpreneur Unity';
                break;

            case 'peers':
            default:
                $scheme = 'peersunity';
                $appId = '6739198477';
                $appStoreUrl = 'https://apps.apple.com/in/app/peers-global-unity/id6739198477';
                $playStoreUrl = 'https://play.google.com/store/apps/details?id=com.peers.peersunity';
                $appName = 'Peers Global Unity';
                break;
        }

        // 3. Custom scheme URI preserving all query parameters (e.g. type=join_circle, id=..., etc.)
        $appScheme = "{$scheme}://share{$querySuffix}";

        // 4. Device detection
        $userAgent = $request->userAgent() ?? '';
        $isAndroid = (bool) preg_match('/Android/i', $userAgent);
        $isiOS = (bool) preg_match('/iPhone|iPad|iPod/i', $userAgent);
        $isMobile = $isAndroid || $isiOS;

        // Fallback store URL
        $storeUrl = $isiOS ? $appStoreUrl : $playStoreUrl;

        // 5. Handle Event Previews with Open Graph Metadata
        $type = $request->query('type');
        $id = $request->query('id');
        $occurrenceId = $request->query('occurrence_id');
        $ref = $request->query('ref');

        if ($type === 'event' && filled($id)) {
            $event = Event::query()->with('circle')->find($id);
            if ($event) {
                $title = $event->title;
                $description = strip_tags((string) ($event->description ?? 'Join this event on '.$appName));
                $image = $event->banner_url
                    ? (str_starts_with($event->banner_url, 'http') ? $event->banner_url : url('/api/v1/files/'.$event->banner_url))
                    : asset('images/og-default.jpg');

                $storeReferrer = http_build_query(array_filter([
                    'type' => 'event',
                    'id' => (string) $id,
                    'occurrence_id' => $occurrenceId ? (string) $occurrenceId : null,
                    'ref' => $ref ? (string) $ref : null,
                ]));

                $eventPlayStoreUrl = $playStoreUrl.'&referrer='.urlencode($storeReferrer);
                $eventStoreUrl = $isiOS ? $appStoreUrl : $eventPlayStoreUrl;

                return view('share.event_preview', [
                    'event' => $event,
                    'title' => $title,
                    'description' => $description,
                    'image' => $image,
                    'playStoreUrl' => $eventPlayStoreUrl,
                    'appStoreUrl' => $appStoreUrl,
                    'storeUrl' => $eventStoreUrl,
                    'appScheme' => $appScheme,
                    'appName' => $appName,
                    'appId' => $appId,
                    'ref' => $ref,
                    'occurrenceId' => $occurrenceId,
                    'isMobile' => $isMobile,
                    'isiOS' => $isiOS,
                    'isAndroid' => $isAndroid,
                ]);
            }
        }

        return view('share', [
            'appScheme' => $appScheme,
            'playStoreUrl' => $playStoreUrl,
            'appStoreUrl' => $appStoreUrl,
            'appName' => $appName,
            'appId' => $appId,
            'instance' => $instance,
            'isMobile' => $isMobile,
            'isiOS' => $isiOS,
            'isAndroid' => $isAndroid,
            'storeUrl' => $storeUrl,
        ]);
    }
}
