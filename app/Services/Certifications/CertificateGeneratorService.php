<?php

declare(strict_types=1);

namespace App\Services\Certifications;

use App\Mail\CertificationApprovedMail;
use App\Models\CertificationSubmission;
use App\Models\EntrepreneurCertificationSubmission;
use App\Models\LeadershipCertificationSubmission;
use App\Models\User;
use App\Services\EmailLogs\EmailLogService;
use App\Services\PushNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CertificateGeneratorService
{
    public function __construct(
        private readonly EmailLogService $emailLogService,
        private readonly PushNotificationService $pushNotificationService,
    ) {}

    public function approveSubmission(CertificationSubmission $submission, ?string $adminNote, ?string $adminId): CertificationSubmission
    {
        $approvedSubmission = DB::transaction(function () use ($submission, $adminNote, $adminId) {
            /** @var CertificationSubmission $submission */
            $submission = CertificationSubmission::query()
                ->whereKey($submission->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $now = now();

            $submission->forceFill([
                'status' => CertificationSubmission::STATUS_APPROVED,
                'admin_note' => $adminNote,
                'approved_by' => $adminId,
                'approved_at' => $now,
                'rejected_by' => null,
                'rejected_at' => null,
            ]);

            $this->populateCertificateMetadata($submission, $now);
            $submission->save();

            $this->syncLegacyStatus($submission, CertificationSubmission::STATUS_APPROVED);

            return $submission->refresh();
        });

        $this->notifyUserOnApproval($approvedSubmission);

        return $approvedSubmission;
    }

    public function ensureCertificate(CertificationSubmission $submission): CertificationSubmission
    {
        return DB::transaction(function () use ($submission) {
            /** @var CertificationSubmission $submission */
            $submission = CertificationSubmission::query()
                ->whereKey($submission->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->populateCertificateMetadata($submission, now());
            $submission->save();

            return $submission->refresh();
        });
    }

    public function regeneratePdf(CertificationSubmission $submission): CertificationSubmission
    {
        return $this->ensureCertificate($submission);
    }

    private function populateCertificateMetadata(CertificationSubmission $submission, $generatedAt): void
    {
        if (! $submission->certificate_number) {
            $submission->certificate_number = $this->nextCertificateNumber($submission);
        }

        if (! $submission->issued_at) {
            $submission->issued_at = $generatedAt;
        }

        $submission->forceFill([
            'certificate_file_path' => null,
            'certificate_download_url' => $this->downloadUrl($submission),
            'certificate_generated_at' => $generatedAt,
        ]);
    }

    private function notifyUserOnApproval(CertificationSubmission $submission): void
    {
        $user = $submission->user_id
            ? User::find($submission->user_id)
            : User::where('email', $submission->email)->first();

        if ($user && ! $submission->user_id) {
            $submission->forceFill(['user_id' => $user->id])->save();
        }

        // 1. Send Email Notification
        try {
            if ($submission->email) {
                $mailable = new CertificationApprovedMail($submission);
                Mail::to($submission->email)->send($mailable);

                $this->emailLogService->logMailableSent($mailable, [
                    'user_id' => $user?->id,
                    'to_email' => $submission->email,
                    'to_name' => $submission->full_name,
                    'template_key' => 'certification_approved',
                    'source_module' => 'CertificationSubmissions',
                    'related_type' => CertificationSubmission::class,
                    'related_id' => (string) $submission->id,
                    'payload' => [
                        'certification_type' => $submission->certification_type,
                        'certificate_number' => $submission->certificate_number,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send certification approved email', [
                'submission_id' => $submission->id,
                'email' => $submission->email,
                'error' => $e->getMessage(),
            ]);
        }

        // 2. Send Push / In-App Notification
        if ($user) {
            try {
                $typeLabel = ucfirst($submission->certification_type);
                $title = 'Certification Approved! 🎉';
                $body = "Congratulations! Your {$typeLabel} Certification has been approved. Certificate #: {$submission->certificate_number}";

                $this->pushNotificationService->storeAndSend(
                    $user,
                    $title,
                    $body,
                    [
                        'notification_type' => 'certification_approved',
                        'submission_id' => (string) $submission->id,
                        'certification_type' => $submission->certification_type,
                        'certificate_download_url' => $submission->certificate_download_url,
                        'screen' => 'certification',
                    ],
                    [
                        'type' => 'certification_approved',
                        'submission_id' => (string) $submission->id,
                        'certification_type' => $submission->certification_type,
                        'certificate_download_url' => $submission->certificate_download_url,
                    ]
                );
            } catch (\Throwable $e) {
                Log::error('Failed to send certification approved notification', [
                    'user_id' => $user->id,
                    'submission_id' => $submission->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function syncLegacyStatus(CertificationSubmission $submission, string $status): void
    {
        if ($submission->certification_type === CertificationSubmission::TYPE_LEADERSHIP) {
            LeadershipCertificationSubmission::query()
                ->where('id', $submission->id)
                ->update(['status' => $status]);
        } elseif ($submission->certification_type === CertificationSubmission::TYPE_ENTREPRENEUR) {
            EntrepreneurCertificationSubmission::query()
                ->where('id', $submission->id)
                ->update(['status' => $status]);
        }
    }

    private function nextCertificateNumber(CertificationSubmission $submission): string
    {
        $typePrefix = $submission->certification_type === CertificationSubmission::TYPE_LEADERSHIP ? 'LEAD' : 'ENT';
        $year = now()->format('Y');
        $prefix = $typePrefix.'-'.$year.'-';

        $numbers = CertificationSubmission::query()
            ->where('certificate_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->pluck('certificate_number')
            ->all();

        $max = 0;
        foreach ($numbers as $number) {
            $suffix = (int) Str::afterLast((string) $number, '-');
            $max = max($max, $suffix);
        }

        do {
            $candidate = $prefix.str_pad((string) (++$max), 6, '0', STR_PAD_LEFT);
        } while (CertificationSubmission::query()->where('certificate_number', $candidate)->exists());

        return $candidate;
    }

    private function downloadUrl(CertificationSubmission $submission): string
    {
        return url('/admin/certificates/'.$submission->id.'/view');
    }
}
