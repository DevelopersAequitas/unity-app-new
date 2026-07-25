<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Middleware\EnsureSingleActiveSession;
use App\Models\User;
use App\Services\UserSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ConcurrentSessionTest extends TestCase
{
    protected UserSessionService $sessionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sessionService = app(UserSessionService::class);
    }

    public function test_user_session_service_stores_and_retrieves_active_session(): void
    {
        Cache::shouldReceive('put')
            ->once()
            ->with('user:session:user-123', 'session-abc-123', 2592000);

        Cache::shouldReceive('get')
            ->once()
            ->with('user:session:user-123')
            ->andReturn('session-abc-123');

        $this->sessionService->setActiveSession('user-123', 'session-abc-123');
        $active = $this->sessionService->getActiveSession('user-123');

        $this->assertEquals('session-abc-123', $active);
    }

    public function test_middleware_blocks_superseded_session_with_401_code(): void
    {
        Cache::shouldReceive('get')
            ->with('user:session:user-test-uuid')
            ->andReturn('new-session-id-456');

        $user = new User;
        $user->id = 'user-test-uuid';

        $request = Request::create('/api/v1/auth/me', 'GET');
        $request->setUserResolver(fn () => $user);

        $mockToken = new \stdClass;
        $mockToken->name = 'session:old-session-id-123';
        $user->withAccessToken($mockToken);

        $middleware = new EnsureSingleActiveSession($this->sessionService);
        $response = $middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(401, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('SESSION_SUPERSEDED', $content['code']);
        $this->assertStringContainsString('accessed on another device', $content['message']);
    }

    public function test_middleware_allows_bypassed_user_email_session(): void
    {
        $user = new User;
        $user->id = 'user-bypassed-uuid';
        $user->email = 'harshchauhan29626@gmail.com';

        $request = Request::create('/api/v1/members/123', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsureSingleActiveSession($this->sessionService);
        $response = $middleware->handle($request, function () {
            return response()->json(['success' => true]);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }
}
