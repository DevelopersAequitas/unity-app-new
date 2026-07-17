<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShareRedirectRequest;
use Illuminate\View\View;

class ShareController extends Controller
{
    /**
     * Handle the share redirection logic.
     */
    public function handle(ShareRedirectRequest $request): View
    {
        $type = (string) $request->input('type', '');
        $id = (string) $request->input('id', '');

        // Detect Greenpreneur vs Peers automatically based on the domain name or config
        $instance = config('app.instance');
        if (! $instance) {
            $host = $request->getHost();
            $instance = str_contains($host, 'greenpreneur') ? 'greenpreneur' : 'peers';
        }

        $isGreenpreneur = ($instance === 'greenpreneur');

        if ($isGreenpreneur) {
            $appScheme = "greenpreneur://share?type={$type}&id={$id}";
            $playStoreUrl = 'https://play.google.com/store/apps/details?id=com.unity.greenpreneur';
            $appStoreUrl = 'https://apps.apple.com/us/app/greenpreneur/id1234567890';
            $appName = 'Greenpreneur';
        } else {
            $appScheme = "peersunity://share?type={$type}&id={$id}";
            $playStoreUrl = 'https://play.google.com/store/apps/details?id=com.peers.peersunity';
            $appStoreUrl = 'https://apps.apple.com/us/app/peers-global-unity/id6739198477';
            $appName = 'Peers Global Unity';
        }

        // Detect client device type
        $userAgent = $request->userAgent() ?? '';
        $isAndroid = (bool) preg_match('/Android/i', $userAgent);
        $isiOS = (bool) preg_match('/iPhone|iPad|iPod/i', $userAgent);
        $isMobile = $isAndroid || $isiOS;

        // Pick appropriate store fallback link
        $storeUrl = $isiOS ? $appStoreUrl : $playStoreUrl;

        return view('share', [
            'appScheme' => $appScheme,
            'playStoreUrl' => $playStoreUrl,
            'appStoreUrl' => $appStoreUrl,
            'appName' => $appName,
            'isGreenpreneur' => $isGreenpreneur,
            'isMobile' => $isMobile,
            'storeUrl' => $storeUrl,
        ]);
    }
}
