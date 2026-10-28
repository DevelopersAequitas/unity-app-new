<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LinkedInCallbackRequest;
use App\Http\Requests\Auth\LinkedInProfileRequest;
use App\Http\Requests\Auth\LinkedInRedirectUrlRequest;
use App\Http\Requests\Auth\LinkedInRegisterRequest;
use App\Services\Auth\LinkedInAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class LinkedInAuthController extends Controller
{
    public function __construct(
        protected LinkedInAuthService $linkedInAuthService
    ) {}

    /**
     * Get LinkedIn OAuth authorization URL or redirect directly.
     */
    public function redirectUrl(LinkedInRedirectUrlRequest $request): JsonResponse|RedirectResponse
    {
        $redirectUri = $request->input('redirect_uri');
        $state = $request->input('state');

        $url = $this->linkedInAuthService->getAuthorizationUrl(
            $redirectUri ? (string) $redirectUri : null,
            $state ? (string) $state : null
        );

        if ($request->boolean('redirect') || ! $request->wantsJson()) {
            return redirect()->away($url);
        }

        return response()->json([
            'success' => true,
            'message' => 'LinkedIn authorization URL generated successfully.',
            'data' => [
                'url' => $url,
            ],
        ]);
    }

    /**
     * Inspect LinkedIn profile and check existing registration status.
     */
    public function profile(LinkedInProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $codeOrToken = (string) ($validated['code'] ?? $validated['token']);
        $redirectUri = isset($validated['redirect_uri']) ? (string) $validated['redirect_uri'] : null;

        $result = $this->linkedInAuthService->getProfile($codeOrToken, $redirectUri);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'],
        ], $result['status_code']);
    }

    /**
     * Register a new user with LinkedIn profile details and required phone number.
     */
    public function register(LinkedInRegisterRequest $request): JsonResponse
    {
        $result = $this->linkedInAuthService->registerWithLinkedIn($request->validated());

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'],
        ], $result['status_code']);
    }

    /**
     * Handle LinkedIn OAuth code / token callback and issue login session.
     */
    public function callback(LinkedInCallbackRequest $request): JsonResponse|Response
    {
        // If loaded via a web browser / Custom Tab redirect (GET), return deep link to resume Flutter app
        if ($request->isMethod('get') && (! $request->wantsJson() || ! $request->ajax())) {
            $code = (string) $request->query('code');
            $state = (string) $request->query('state');
            $error = (string) ($request->query('error_description') ?: $request->query('error'));

            $targetUrl = 'peersunity://auth/linkedin/callback?'.http_build_query(array_filter([
                'code' => $code,
                'state' => $state,
                'error' => $error,
            ]));

            return response(<<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connecting to Peers Global Unity...</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #0f172a; color: #fff; text-align: center; }
        .card { padding: 32px; border-radius: 16px; background: #1e293b; max-width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .spinner { width: 40px; height: 40px; border: 4px solid #334155; border-top-color: #0ea5e9; border-radius: 50%; animation: spin 1s infinite linear; margin: 0 auto 20px; }
        @keyframes spin { 100% { transform: rotate(360deg); } }
        a.btn { display: inline-block; margin-top: 20px; padding: 12px 24px; background: #0a66c2; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="card">
        <div class="spinner"></div>
        <h2>Returning to Unity App...</h2>
        <p>Authorization complete. Opening Peers Unity...</p>
        <a class="btn" href="{$targetUrl}">Tap to Open App</a>
    </div>
    <script>
        window.location.href = "{$targetUrl}";
    </script>
</body>
</html>
HTML, 200, ['Content-Type' => 'text/html']);
        }

        $validated = $request->validated();
        $codeOrToken = (string) ($validated['code'] ?? $validated['token']);
        $redirectUri = isset($validated['redirect_uri']) ? (string) $validated['redirect_uri'] : null;
        $timezone = isset($validated['timezone']) ? (string) $validated['timezone'] : null;

        $result = $this->linkedInAuthService->authenticateAndLogin(
            $codeOrToken,
            $redirectUri,
            $timezone,
            $validated
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'],
        ], $result['status_code']);
    }
}
