<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Profile\RequestEmailChangeOtpRequest;
use App\Http\Requests\Profile\RequestPhoneChangeOtpRequest;
use App\Http\Requests\Profile\VerifyEmailChangeOtpRequest;
use App\Http\Requests\Profile\VerifyPhoneChangeOtpRequest;
use App\Http\Resources\UserProfileResource;
use App\Models\User;
use App\Services\Profile\ContactChangeOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ContactChangeController extends BaseApiController
{
    public function __construct(
        protected ContactChangeOtpService $contactChangeOtpService
    ) {}

    public function requestCurrentEmailOtp(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $this->contactChangeOtpService->requestCurrentEmailOtp($user);

        if (! $result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        return $this->success($result['data'], $result['message']);
    }

    public function verifyCurrentEmailOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'string'],
            'email' => ['nullable', 'email'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $result = $this->contactChangeOtpService->verifyCurrentEmailOtp(
            $user,
            (string) $validated['otp'],
            isset($validated['email']) ? (string) $validated['email'] : null
        );

        if (! $result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        $updatedUser = $this->loadProfileRelations($result['user']);

        return $this->success(new UserProfileResource($updatedUser), $result['message']);
    }

    public function requestEmailOtp(RequestEmailChangeOtpRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $this->contactChangeOtpService->requestEmailChangeOtp(
            $user,
            (string) $request->validated('email')
        );

        if (! $result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        return $this->success($result['data'], $result['message']);
    }

    public function verifyEmailOtp(VerifyEmailChangeOtpRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $this->contactChangeOtpService->verifyEmailChangeOtp(
            $user,
            (string) $request->validated('email'),
            (string) $request->validated('otp')
        );

        if (! $result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        $updatedUser = $this->loadProfileRelations($result['user']);

        return $this->success(new UserProfileResource($updatedUser), $result['message']);
    }

    public function requestPhoneOtp(RequestPhoneChangeOtpRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $this->contactChangeOtpService->requestPhoneChangeOtp(
            $user,
            (string) $request->validated('phone'),
            $request->validated('country_code')
        );

        if (! $result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        return $this->success($result['data'], $result['message']);
    }

    public function verifyPhoneOtp(VerifyPhoneChangeOtpRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $this->contactChangeOtpService->verifyPhoneChangeOtp(
            $user,
            (string) $request->validated('phone'),
            (string) $request->validated('otp'),
            $request->validated('country_code')
        );

        if (! $result['success']) {
            return $this->error($result['message'], $result['status']);
        }

        $updatedUser = $this->loadProfileRelations($result['user']);

        return $this->success(new UserProfileResource($updatedUser), $result['message']);
    }

    private function loadProfileRelations(User $user): User
    {
        $user->load([
            'city',
            'activeCircle.cityRef',
            'mainBusinessCategory',
            'businessCategory',
            'level4Category',
            'profilePhotoFile',
            'coverPhotoFile',
            'userLinks',
            'introducedBy',
        ]);

        if (Schema::hasTable('user_follows')) {
            $user->loadCount([
                'followers as followers_count' => fn ($q) => $q->whereIn('status', ['accepted', 'pending']),
                'following as following_count' => fn ($q) => $q->whereIn('status', ['accepted', 'pending']),
            ]);
        }

        if (Schema::hasTable('posts')) {
            $user->loadCount(['posts as posts_count']);
        }

        if (Schema::hasTable('connections')) {
            $user->loadCount([
                'approvedSentConnections as approved_sent_count',
                'approvedReceivedConnections as approved_received_count',
            ]);
        }

        return $user;
    }
}
