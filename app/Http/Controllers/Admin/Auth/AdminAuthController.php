<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\AdminRequestOtpRequest;
use App\Http\Requests\Admin\Auth\AdminVerifyOtpRequest;
use App\Models\AdminUser;
use App\Models\IndustryDirectorAssignment;
use App\Services\Admin\Auth\AdminAuthService;
use App\Support\AdminAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class AdminAuthController extends Controller
{
    public function __construct(
        protected AdminAuthService $adminAuthService
    ) {}

    public function showLogin(Request $request): RedirectResponse|View
    {
        if (Auth::guard('admin')->check()) {
            $adminUser = Auth::guard('admin')->user();

            if ($this->shouldRedirectToIndustryDirectorDashboard($adminUser)) {
                return redirect()->route('admin.industry-director.dashboard');
            }

            return redirect()->route(AdminAccess::isDed($adminUser) ? 'admin.ded.dashboard' : 'admin.dashboard');
        }

        // Clear temporary login session draft on fresh page load if not redirected with validation errors or active OTP flow
        if (! $request->session()->has('errors') && ! $request->session()->has('status')) {
            $request->session()->forget([
                'admin_login_identifier',
                'admin_login_mobile',
                'admin_login_email',
                'admin_login_channel',
                'admin_login_otp_length',
            ]);
        }

        $loginMethods = $this->adminAuthService->getAvailableLoginMethods();
        $enabledMethods = $this->adminAuthService->getEnabledLoginMethods();

        return view('admin.auth.login', [
            'loginMethods' => $loginMethods,
            'enabledMethods' => $enabledMethods,
        ]);
    }

    public function loginMethods(Request $request): JsonResponse
    {
        $methods = $this->adminAuthService->getAvailableLoginMethods();

        return response()->json([
            'success' => true,
            'message' => 'Available login methods retrieved successfully.',
            'data' => [
                'login_methods' => $methods,
            ],
        ]);
    }

    public function requestOtp(AdminRequestOtpRequest $request): JsonResponse|RedirectResponse
    {
        $identifier = $request->resolvedIdentifier();
        $channel = $request->resolvedChannel();

        try {
            $result = $this->adminAuthService->requestOtp($identifier);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => $result['success'],
                    'message' => $result['message'],
                    'data' => $result['data'],
                ], $result['status']);
            }

            if (! $result['success']) {
                return back()
                    ->withInput($request->all())
                    ->withErrors(['identifier' => $result['message']]);
            }

            // Direct login bypass handling
            if (! empty($result['data']['is_direct_login']) && isset($result['data']['admin_user'])) {
                /** @var AdminUser $bypassUser */
                $bypassUser = $result['data']['admin_user'];
                Auth::guard('admin')->login($bypassUser);
                $request->session()->put('admin_user_id', $bypassUser->id);
                $request->session()->put('admin_login_email', $bypassUser->email);
                $request->session()->put('admin_login_identifier', $identifier);
                $request->session()->regenerate();

                if ($this->shouldRedirectToIndustryDirectorDashboard($bypassUser)) {
                    return redirect()->route('admin.industry-director.dashboard');
                }

                return redirect()->route(AdminAccess::isDed($bypassUser) ? 'admin.ded.dashboard' : 'admin.dashboard');
            }

            // Special password user handling
            if (! empty($result['data']['is_password_user'])) {
                $request->session()->forget('errors');
                $request->session()->put('admin_login_email', $identifier);
                $request->session()->put('admin_login_identifier', $identifier);

                return redirect()
                    ->route('admin.login')
                    ->withInput(['identifier' => $identifier, 'email' => $identifier, 'channel' => 'email'])
                    ->with('otp_sent', true)
                    ->with('status', 'Enter password to login');
            }

            $detectedChannel = $result['data']['channel'] ?? $channel;
            $otpLength = (int) ($result['data']['otp_length'] ?? 4);

            $request->session()->forget('errors');
            $request->session()->put('admin_login_identifier', $identifier);
            $request->session()->put('admin_login_channel', $detectedChannel);
            $request->session()->put('admin_login_otp_length', $otpLength);
            if ($detectedChannel === 'whatsapp') {
                $request->session()->put('admin_login_mobile', $identifier);
            } else {
                $request->session()->put('admin_login_email', $identifier);
            }

            return redirect()
                ->route('admin.login')
                ->withInput([
                    'identifier' => $identifier,
                    'channel' => $detectedChannel,
                    'email' => $detectedChannel === 'email' ? $identifier : null,
                    'mobile' => $detectedChannel === 'whatsapp' ? $identifier : null,
                ])
                ->with('otp_sent', true)
                ->with('otp_length', $otpLength)
                ->with('channel', $detectedChannel)
                ->with('status', $result['message']);
        } catch (Throwable $e) {
            Log::error('admin.login.request_otp_exception', [
                'identifier' => $identifier,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to process login request: '.$e->getMessage(),
                    'data' => null,
                ], 500);
            }

            return back()
                ->withInput($request->all())
                ->withErrors(['identifier' => 'Failed to process login request: '.$e->getMessage()]);
        }
    }

    public function verifyOtp(AdminVerifyOtpRequest $request): JsonResponse|RedirectResponse
    {
        $identifier = $request->resolvedIdentifier();
        $channel = $request->resolvedChannel();
        $otp = $request->resolvedOtp();

        try {
            $result = $this->adminAuthService->verifyOtp($identifier, $otp);

            if (! $result['success']) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $result['message'],
                        'data' => null,
                    ], $result['status']);
                }

                return back()
                    ->withInput($request->only('identifier', 'email', 'mobile', 'phone', 'channel'))
                    ->withErrors(['otp' => $result['message']]);
            }

            /** @var AdminUser $adminUser */
            $adminUser = $result['data']['admin_user'];
            $resolvedChannel = $result['data']['channel'] ?? $channel;

            Auth::guard('admin')->login($adminUser);
            $request->session()->put('admin_user_id', $adminUser->id);
            $request->session()->put('admin_login_channel', $resolvedChannel);
            $request->session()->put('admin_login_identifier', $identifier);
            if ($resolvedChannel === 'whatsapp') {
                $request->session()->put('admin_login_mobile', $identifier);
            }
            if (! empty($adminUser->email)) {
                $request->session()->put('admin_login_email', $adminUser->email);
            }
            $request->session()->regenerate();

            if ($request->expectsJson()) {
                $token = $adminUser->createToken('admin_panel')->plainTextToken;

                return response()->json([
                    'success' => true,
                    'message' => 'Login successful',
                    'data' => [
                        'admin' => $adminUser,
                        'token' => $token,
                        'channel' => $resolvedChannel,
                    ],
                ], 200);
            }

            if ($this->shouldRedirectToIndustryDirectorDashboard($adminUser)) {
                return redirect()->route('admin.industry-director.dashboard');
            }

            return redirect()->route(AdminAccess::isDed($adminUser) ? 'admin.ded.dashboard' : 'admin.dashboard');
        } catch (Throwable $e) {
            Log::error('admin.login.verify_otp_exception', [
                'identifier' => $identifier,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Verification failed: '.$e->getMessage(),
                    'data' => null,
                ], 500);
            }

            return back()
                ->withInput($request->only('identifier', 'email', 'mobile', 'phone', 'channel'))
                ->withErrors(['otp' => 'Verification failed: '.$e->getMessage()]);
        }
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->forget([
            'admin_user_id',
            'admin_login_mobile',
            'admin_login_email',
            'admin_login_channel',
            'admin_login_identifier',
            'admin_login_otp_length',
        ]);
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function shouldRedirectToIndustryDirectorDashboard(?AdminUser $adminUser): bool
    {
        if (! $adminUser) {
            return false;
        }

        if (! AdminAccess::isIndustryScoped($adminUser)) {
            return false;
        }

        if (! Schema::hasTable('industry_director_assignments')) {
            return false;
        }

        return IndustryDirectorAssignment::query()
            ->where('admin_user_id', $adminUser->id)
            ->where('is_active', true)
            ->exists();
    }
}
