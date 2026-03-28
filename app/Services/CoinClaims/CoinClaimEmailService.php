<?php

namespace App\Services\CoinClaims;

use App\Mail\CoinClaimApprovedMail;
use App\Mail\CoinClaimRejectedMail;
use App\Mail\CoinClaimSubmittedMail;
use App\Models\CoinClaimRequest;
use App\Services\EmailLogs\EmailLogService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CoinClaimEmailService
{
    public function __construct(private readonly EmailLogService $emailLogService)
    {
    }

    public function sendSubmitted(CoinClaimRequest $claim): void
    {
        $this->safeSend($claim, new CoinClaimSubmittedMail($claim), 'submitted');
    }

    public function sendApproved(CoinClaimRequest $claim): void
    {
        $this->safeSend($claim, new CoinClaimApprovedMail($claim), 'approved');
    }

    public function sendRejected(CoinClaimRequest $claim): void
    {
        $this->safeSend($claim, new CoinClaimRejectedMail($claim), 'rejected');
    }

    private function safeSend(CoinClaimRequest $claim, object $mailable, string $type): void
    {
        if (! $mailable instanceof \Illuminate\Mail\Mailable) {
            return;
        }

        try {
            $email = $claim->user?->email;
            if (! $email) {
                return;
            }

            Mail::to($email)->send($mailable);
            $this->emailLogService->logMailableSent($mailable, [
                'user_id' => $claim->user?->id,
                'to_email' => $email,
                'to_name' => $claim->user?->display_name ?: trim(($claim->user?->first_name ?? '') . ' ' . ($claim->user?->last_name ?? '')),
                'template_key' => 'coin_claim_' . $type,
                'source_module' => 'CoinClaims',
                'related_type' => CoinClaimRequest::class,
                'related_id' => (string) $claim->id,
                'payload' => [
                    'claim_status' => $claim->status,
                    'activity_code' => $claim->activity_code,
                ],
            ]);
        } catch (\Throwable $exception) {
            $this->emailLogService->logMailableFailed($mailable, [
                'user_id' => $claim->user?->id,
                'to_email' => (string) ($claim->user?->email ?? ''),
                'to_name' => $claim->user?->display_name ?: trim(($claim->user?->first_name ?? '') . ' ' . ($claim->user?->last_name ?? '')),
                'template_key' => 'coin_claim_' . $type,
                'source_module' => 'CoinClaims',
                'related_type' => CoinClaimRequest::class,
                'related_id' => (string) $claim->id,
                'payload' => [
                    'claim_status' => $claim->status,
                    'activity_code' => $claim->activity_code,
                ],
            ], $exception);

            Log::warning('Coin claim email send failed', [
                'claim_id' => (string) $claim->id,
                'type' => $type,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
