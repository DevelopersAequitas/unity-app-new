<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\Api\AuthController;
use App\Http\Middleware\AllowFixedMembersToken;
use App\Leader\Services\LeaderPermissionService;
use App\Shared\Services\UserRoleResolverService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AuthHardeningUnitTest extends TestCase
{
    public function test_fixed_members_token_rejects_old_hardcoded_token_when_env_unset(): void
    {
        config(['services.members_with_circles.fixed_token' => null]);

        $middleware = new AllowFixedMembersToken;
        $request = Request::create('/api/v1/members-with-circles', 'GET');
        $request->headers->set('Authorization', 'Bearer 302|cO0VMR2dmr9j8c3JtIU9dfkuZfSfvzaCCF1GVxJAdc6fdd2d');

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true], 200));

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function test_fixed_members_token_accepts_configured_env_token(): void
    {
        config(['services.members_with_circles.fixed_token' => 'test-rotated-secret-token-value']);

        $middleware = new AllowFixedMembersToken;
        $request = Request::create('/api/v1/members-with-circles', 'GET');
        $request->headers->set('Authorization', 'Bearer test-rotated-secret-token-value');

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true], 200));

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function test_direct_login_rejects_former_gmail_bypass_account(): void
    {
        /** @var AuthController $controller */
        $controller = app(AuthController::class);

        $request = Request::create('/api/v1/auth/direct-login', 'POST', [
            'email' => 'harsh@gmail.com',
            'bypass_otp' => true,
        ]);
        $request->headers->set('X-Environment', 'local');

        $response = $controller->directLogin($request);

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function test_default_role_capabilities_deny_privileged_actions_to_secretary_and_vice_chair(): void
    {
        $service = new LeaderPermissionService(new UserRoleResolverService);
        $defaults = $service->getDefaultRoleCapabilities();

        foreach (['secretary', 'viceChair'] as $role) {
            $caps = $defaults[$role];
            $this->assertNotContains('manage_roles', $caps);
            $this->assertNotContains('system_configs', $caps);
            $this->assertNotContains('manage_finance', $caps);
            $this->assertNotContains('access_finance', $caps);
            $this->assertNotContains('manage_peers', $caps);
        }

        $this->assertContains('manage_roles', $defaults['superAdmin']);
        $this->assertContains('system_configs', $defaults['superAdmin']);
    }

    public function test_batch_2a_files_contain_no_hardcoded_123456_host_bypass_or_plaintext_otp_logs(): void
    {
        $targets = [
            base_path('app/Services/Leadership/VerificationService.php'),
            base_path('app/Services/Advertiser/AdvertiserApplicationService.php'),
            base_path('app/Http/Controllers/LeadershipCandidateLedgerController.php'),
            base_path('app/Http/Controllers/LeadershipForm2Controller.php'),
        ];

        foreach ($targets as $path) {
            $src = (string) file_get_contents($path);
            $this->assertStringNotContainsString("'123456'", $src, "Found hardcoded 123456 in {$path}");
            $this->assertStringNotContainsString('otp_plain', $src, "Found otp_plain in {$path}");
            $this->assertStringNotContainsString('getHost()', $src, "Found getHost() trust in {$path}");
            $this->assertStringNotContainsString('{$plainOtp}', $src, "Found plaintext OTP logging in {$path}");
        }
    }
}
