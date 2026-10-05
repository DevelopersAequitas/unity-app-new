<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Store\StoreCheckoutQuoteRequest;
use App\Http\Requests\Store\StoreOtpSendRequest;
use App\Http\Requests\Store\StoreOtpVerifyRequest;
use App\Services\Store\CheckoutQuoteService;
use App\Services\Store\StoreOtpService;
use Exception;
use Illuminate\Http\JsonResponse;

class CheckoutController extends BaseApiController
{
    protected CheckoutQuoteService $quoteService;

    protected StoreOtpService $otpService;

    public function __construct(CheckoutQuoteService $quoteService, StoreOtpService $otpService)
    {
        $this->quoteService = $quoteService;
        $this->otpService = $otpService;
    }

    public function createQuote(StoreCheckoutQuoteRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $quote = $this->quoteService->createQuote($user, $request->validated());

            return $this->success($quote, 'Checkout quote generated successfully');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function sendOtp(StoreOtpSendRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->validated();
            $result = $this->otpService->sendChallenge($user, $data['purpose'], 'QUOTE', $data['quote_id'] ?? null);

            return $this->success($result, 'OTP challenge sent successfully');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function verifyOtp(StoreOtpVerifyRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $data = $request->validated();
            $result = $this->otpService->verifyChallenge($user, $data['challenge_id'], $data['otp']);

            return $this->success($result, 'OTP verified successfully');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}
