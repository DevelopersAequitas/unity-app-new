<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
        $queryString = $request->getQueryString();
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