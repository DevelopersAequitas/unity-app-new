<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Advertiser;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\AdvertiserApplication;
use App\Models\AdvertiserApplicationAsset;
use App\Models\AdvertiserPackage;
use App\Services\Advertiser\AdvertiserApplicationService;
use App\Services\Media\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdvertiserPublicApiController extends BaseApiController
{
    public function __construct(
        protected AdvertiserApplicationService $applicationService,
        protected FileUploadService $fileUploadService
    ) {}

    /**
     * API 1: Get active advertiser packages
     * GET /api/v1/advertiser/packages
     */
    public function packages(Request $request): JsonResponse
    {
        $query = AdvertiserPackage::active()->ordered();

        if ($request->has('duration')) {
            $query->where('duration_months', (int) $request->query('duration'));
        }

        $perPage = (int) $request->query('per_page', 20);
        $packages = $query->paginate($perPage);

        return $this->success($packages, 'Active advertising packages retrieved successfully.');
    }

    /**
     * API 2: Submit advertiser application
     * POST /api/v1/advertiser/applications
     */
    public function submit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'package_id' => ['required', 'uuid', 'exists:advertiser_packages,id'],
            'applicant.full_name' => ['required', 'string', 'max:180'],
            'applicant.email' => ['required', 'email', 'max:254'],
            'applicant.mobile_number' => ['required', 'string', 'max:30'],
            'company.company_name' => ['required', 'string', 'max:200'],
            'company.legal_business_name' => ['nullable', 'string', 'max:250'],
            'company.business_type' => ['nullable', 'string', 'max:100'],
            'company.category_id' => ['nullable', 'string', 'max:120'],
            'company.industry' => ['nullable', 'string', 'max:120'],
            'company.website' => ['nullable', 'url', 'max:2048'],
            'company.official_email' => ['nullable', 'email', 'max:254'],
            'company.phone' => ['nullable', 'string', 'max:30'],
            'company.whatsapp' => ['nullable', 'string', 'max:30'],
            'company.address_line_1' => ['nullable', 'string', 'max:500'],
            'company.city' => ['nullable', 'string', 'max:120'],
            'company.state' => ['nullable', 'string', 'max:120'],
            'company.country' => ['nullable', 'string', 'max:120'],
            'company.postal_code' => ['nullable', 'string', 'max:20'],
            'company.contact_person_name' => ['nullable', 'string', 'max:180'],
            'company.contact_person_designation' => ['nullable', 'string', 'max:120'],
            'company.description' => ['required', 'string'],
            'campaign.advertising_objective' => ['nullable', 'string', 'max:100'],
            'campaign.target_audience' => ['nullable', 'string'],
            'campaign.target_locations' => ['nullable', 'array'],
            'campaign.preferred_start_date' => ['nullable', 'date'],
            'campaign.additional_requirements' => ['nullable', 'string'],
            'assets' => ['nullable', 'array'],
            'assets.*.asset_type' => ['required', 'string', 'in:logo,banner,cover_image,campaign_creative,document,other'],
            'assets.*.storage_key' => ['required', 'string'],
            'assets.*.original_file_name' => ['nullable', 'string', 'max:255'],
            'consents.terms_accepted' => ['required', 'boolean', 'accepted'],
            'consents.privacy_notice_accepted' => ['required', 'boolean', 'accepted'],
            'consents.marketing_consent' => ['nullable', 'boolean'],
            'source' => ['nullable', 'string', 'in:app,website,admin'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ]);

        $authenticatedUser = auth('sanctum')->user() ?? auth()->user();

        $application = $this->applicationService->submitApplication($validated, $authenticatedUser);

        // Auto-save direct multipart files sent during application submission
        $directFiles = [
            'logo' => $request->file('logo'),
            'banner' => $request->file('banner') ?? $request->file('cover_image'),
            'cover_image' => $request->file('cover_image'),
            'file' => $request->file('file'),
        ];
        $disk = config('filesystems.default', 'public');
        foreach ($directFiles as $assetKey => $fileObj) {
            if ($fileObj && $fileObj->isValid()) {
                $type = match ($assetKey) {
                    'logo' => AdvertiserApplicationAsset::TYPE_LOGO,
                    'banner' => AdvertiserApplicationAsset::TYPE_BANNER,
                    'cover_image' => AdvertiserApplicationAsset::TYPE_COVER,
                    default => AdvertiserApplicationAsset::TYPE_CREATIVE,
                };
                $path = $fileObj->store('advertiser/' . $application->id, $disk);
                AdvertiserApplicationAsset::create([
                    'application_id' => $application->id,
                    'asset_type' => $type,
                    'original_filename' => $fileObj->getClientOriginalName(),
                    'storage_disk' => $disk,
                    'storage_key' => $path,
                    'mime_type' => $fileObj->getClientMimeType() ?: 'image/jpeg',
                    'file_size_bytes' => $fileObj->getSize(),
                    'review_status' => AdvertiserApplicationAsset::STATUS_PENDING,
                ]);
            }
        }

        $nextAction = ($application->status === AdvertiserApplication::STATUS_PENDING_EMAIL_VERIFICATION)
            ? 'verify_email'
            : 'await_review';

        $data = [
            'application_id' => $application->id,
            'application_number' => $application->application_number,
            'status' => $application->status,
            'email_verification_required' => ($application->status === AdvertiserApplication::STATUS_PENDING_EMAIL_VERIFICATION),
            'next_action' => $nextAction,
        ];

        if (app()->environment('local', 'testing') && ($application->status === AdvertiserApplication::STATUS_PENDING_EMAIL_VERIFICATION)) {
            $data['debug_otp'] = '123456';
        }

        return $this->success($data, 'Your application has been received successfully.', 201);
    }

    /**
     * API 3: Request / Trigger email verification
     * POST /api/v1/advertiser/applications/{id}/email-verification
     */
    public function requestEmailVerification(Request $request, string $id): JsonResponse
    {
        $application = AdvertiserApplication::find($id);
        if (!$application) {
            return $this->error('Application not found.', 404);
        }

        if ($application->applicant_email_verified) {
            return $this->success([
                'verification_required' => false,
                'status' => $application->status,
            ], 'Email is already verified.');
        }

        $this->applicationService->createEmailVerificationChallenge($application);

        return $this->success([
            'verification_required' => true,
            'expires_in_seconds' => 600,
        ], 'If verification is available, instructions will be sent to the registered email.');
    }

    /**
     * API 4: Confirm email verification code
     * POST /api/v1/advertiser/applications/{id}/email-verification/confirm
     */
    public function confirmEmailVerification(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'verification_code' => ['required', 'string', 'min:4', 'max:10'],
        ]);

        $application = AdvertiserApplication::find($id);
        if (!$application) {
            return $this->error('Application not found.', 404);
        }

        $this->applicationService->confirmEmailVerification(
            $application,
            (string) $request->input('verification_code')
        );

        $application->refresh();

        return $this->success([
            'application_id' => $application->id,
            'email_verified' => true,
            'status' => $application->status,
            'next_action' => 'await_review',
        ], 'Email verified successfully.');
    }

    /**
     * API 5: Resend email verification
     * POST /api/v1/advertiser/applications/{id}/email-verification/resend
     */
    public function resendEmailVerification(Request $request, string $id): JsonResponse
    {
        $application = AdvertiserApplication::find($id);
        if (!$application) {
            return $this->error('Application not found.', 404);
        }

        if ($application->applicant_email_verified) {
            return $this->error('Email is already verified.', 400);
        }

        $this->applicationService->createEmailVerificationChallenge($application);

        return $this->success(null, 'If another verification message can be sent, it will be sent to the registered email.');
    }

    /**
     * API 6: Get secure upload instructions
     * POST /api/v1/advertiser/applications/{id}/assets/upload-url
     */
    public function uploadUrl(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'asset_type' => ['required', 'string', 'in:logo,banner,cover_image,campaign_creative,document,other'],
            'file_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:100'],
            'file_size' => ['required', 'integer', 'max:10485760'], // 10MB max
        ]);

        $application = AdvertiserApplication::find($id);
        if (!$application) {
            return $this->error('Application not found.', 404);
        }

        $ext = pathinfo($request->input('file_name'), PATHINFO_EXTENSION);
        $cleanExt = $ext ? '.' . strtolower($ext) : '.jpg';
        $storageKey = 'advertiser/' . $application->id . '/' . (string) Str::uuid() . $cleanExt;

        $disk = config('filesystems.default', 'public');
        $uploadUrl = url('/api/v1/advertiser/applications/' . $application->id . '/assets/direct-upload');

        return $this->success([
            'upload_url' => $uploadUrl,
            'storage_key' => $storageKey,
            'storage_disk' => $disk,
            'method' => 'POST',
            'required_headers' => [
                'Content-Type' => 'multipart/form-data',
            ],
            'expires_at' => now()->addMinutes(30)->toIso8601String(),
        ], 'Upload instructions generated successfully.');
    }

    /**
     * API 7: Confirm uploaded asset
     * POST /api/v1/advertiser/applications/{id}/assets
     */
    public function confirmAsset(Request $request, string $id): JsonResponse
    {
        $application = AdvertiserApplication::find($id);
        if (!$application) {
            return $this->error('Application not found.', 404);
        }

        // Support either multipart direct file upload or storage_key confirmation
        $uploadedFile = $request->file('file')
            ?? $request->file('logo')
            ?? $request->file('banner')
            ?? $request->file('cover_image')
            ?? $request->file('image')
            ?? $request->file('asset')
            ?? collect($request->allFiles())->first();

        if ($uploadedFile) {
            $assetType = $request->input('asset_type');
            if (empty($assetType)) {
                if ($request->hasFile('logo')) $assetType = AdvertiserApplicationAsset::TYPE_LOGO;
                elseif ($request->hasFile('banner')) $assetType = AdvertiserApplicationAsset::TYPE_BANNER;
                elseif ($request->hasFile('cover_image')) $assetType = AdvertiserApplicationAsset::TYPE_COVER;
                else $assetType = AdvertiserApplicationAsset::TYPE_LOGO;
            }

            $disk = config('filesystems.default', 'public');
            $path = $uploadedFile->store('advertiser/' . $application->id, $disk);

            $asset = AdvertiserApplicationAsset::create([
                'application_id' => $application->id,
                'asset_type' => $assetType,
                'original_filename' => $uploadedFile->getClientOriginalName(),
                'storage_disk' => $disk,
                'storage_key' => $path,
                'mime_type' => $uploadedFile->getClientMimeType() ?: 'image/jpeg',
                'file_size_bytes' => $uploadedFile->getSize(),
                'review_status' => AdvertiserApplicationAsset::STATUS_PENDING,
            ]);

            return $this->success([
                'asset_id' => $asset->id,
                'asset_type' => $asset->asset_type,
                'storage_key' => $asset->storage_key,
                'url' => $asset->url,
                'review_status' => $asset->review_status,
                'uploaded' => true,
            ], 'Asset registered successfully.', 201);
        }

        $validated = $request->validate([
            'asset_type' => ['required', 'string', 'in:logo,banner,cover_image,campaign_creative,document,other'],
            'storage_key' => ['required', 'string'],
            'original_file_name' => ['nullable', 'string', 'max:255'],
            'file_size' => ['nullable', 'integer'],
            'mime_type' => ['nullable', 'string'],
        ]);

        $asset = AdvertiserApplicationAsset::create([
            'application_id' => $application->id,
            'asset_type' => $validated['asset_type'],
            'original_filename' => $validated['original_file_name'] ?? basename($validated['storage_key']),
            'storage_disk' => 'public',
            'storage_key' => $validated['storage_key'],
            'mime_type' => $validated['mime_type'] ?? 'image/jpeg',
            'file_size_bytes' => (int) ($validated['file_size'] ?? 1024),
            'review_status' => AdvertiserApplicationAsset::STATUS_PENDING,
        ]);

        return $this->success([
            'asset_id' => $asset->id,
            'asset_type' => $asset->asset_type,
            'storage_key' => $asset->storage_key,
            'url' => $asset->url,
            'review_status' => $asset->review_status,
            'uploaded' => true,
        ], 'Asset registered successfully.', 201);
    }

    /**
     * API 34: Check next identity step
     * POST /api/v1/advertiser/identity/resolve
     */
    public function resolveIdentity(Request $request): JsonResponse
    {
        $request->validate([
            'application_id' => ['required', 'uuid', 'exists:advertiser_applications,id'],
        ]);

        $application = AdvertiserApplication::findOrFail($request->input('application_id'));

        if (!$application->applicant_email_verified) {
            $nextAction = 'verify_email';
        } elseif ($application->user_id === null) {
            $nextAction = 'sign_in_or_create_account';
        } else {
            $nextAction = 'continue_application';
        }

        return $this->success([
            'application_id' => $application->id,
            'next_action' => $nextAction,
            'email_verified' => (bool) $application->applicant_email_verified,
        ], 'Identity resolution completed.');
    }

    /**
     * API 37: Access application as website guest
     * GET /api/v1/advertiser/guest/applications/{id}
     */
    public function guestShow(Request $request, string $id): JsonResponse
    {
        $application = AdvertiserApplication::with(['package', 'assets'])->find($id);

        if (!$application) {
            return $this->error('Application not found.', 404);
        }

        return $this->success([
            'application_id' => $application->id,
            'application_number' => $application->application_number,
            'status' => $application->status,
            'source' => $application->source,
            'applicant' => [
                'full_name' => $application->applicant_name,
                'email' => $application->applicant_email,
                'email_verified' => (bool) $application->applicant_email_verified,
            ],
            'company' => [
                'company_name' => $application->company_name,
                'website' => $application->company_website,
                'description' => $application->brand_description,
            ],
            'package' => [
                'name' => $application->requested_package_name,
                'duration_months' => $application->requested_duration_months,
                'price' => $application->requested_package_price,
                'currency' => $application->requested_currency,
            ],
            'assets' => $application->assets->map(fn ($a) => [
                'asset_id' => $a->id,
                'asset_type' => $a->asset_type,
                'url' => $a->url,
                'review_status' => $a->review_status,
            ]),
            'feedback' => $application->applicant_response,
            'next_action' => match ($application->status) {
                AdvertiserApplication::STATUS_PENDING_EMAIL_VERIFICATION => 'verify_email',
                AdvertiserApplication::STATUS_CHANGES_REQUESTED => 'update_corrections',
                AdvertiserApplication::STATUS_APPROVED => 'complete_payment',
                default => 'await_review',
            },
            'submitted_at' => $application->submitted_at?->toIso8601String(),
        ], 'Application details retrieved successfully.');
    }

    /**
     * API 45: Get public onboarding banner configuration
     * GET /api/v1/advertiser/banner-config
     */
    public function bannerConfig(): JsonResponse
    {
        return $this->success([
            'enabled' => true,
            'title' => 'Become a Brand Partner',
            'description' => 'Promote your business to the Peers Global community.',
            'cta_text' => 'Apply Now',
            'image_url' => asset('images/brand-partner-banner.png'),
            'destination' => 'advertiser_packages',
        ], 'Banner configuration retrieved.');
    }
}
