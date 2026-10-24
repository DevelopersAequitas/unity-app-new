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
    public function callback(LinkedInCallbackRequest $request): JsonResponse
    {
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
