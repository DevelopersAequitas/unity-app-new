<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\CoinClaims\StoreCoinClaimRequest;
use App\Http\Resources\CoinClaimActivityResource;
use App\Http\Resources\CoinClaimRequestResource;
use App\Models\CoinClaimRequest;
use App\Models\FileModel;
use App\Services\CoinClaims\CoinClaimEmailService;
use App\Support\CoinClaims\CoinClaimActivityRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CoinClaimController extends BaseApiController
{
    public function __construct(
        private readonly CoinClaimActivityRegistry $registry,
        private readonly CoinClaimEmailService $emailService,
    ) {}

    public function activities(): JsonResponse
    {
        $items = CoinClaimActivityResource::collection(collect($this->registry->listForApi()));

        return $this->success(['items' => $items], null);
    }

    public function store(StoreCoinClaimRequest $request): JsonResponse
    {
        $requestId = (string) Str::uuid();
        $user = $request->user();

        Log::info('Coin claim store request received', [
            'request_id' => $requestId,
            'user_id' => $user?->id,
            'activity_code' => $request->input('activity_code'),
        ]);

        try {
            $activityCode = (string) $request->input('activity_code');
            $fieldMap = $this->registry->fieldMap($activityCode);

            $rawInputs = $request->all();
            $payloadInput = is_array($request->input('payload')) ? $request->input('payload') : [];
            $nestedFields = is_array($request->input('fields')) ? $request->input('fields') : [];

            $rootFields = [];
            foreach ($rawInputs as $k => $v) {
                if (! in_array($k, ['_token', '_method', 'activity_code', 'payload', 'fields', 'files'], true)) {
                    $rootFields[$k] = $v;
                }
            }

            $fields = array_merge($rootFields, $payloadInput, $nestedFields);

            $uploaded = is_array($request->file('files')) ? $request->file('files') : [];
            foreach ($request->allFiles() as $fileKey => $uploadedFile) {
                if ($fileKey !== 'files' && ! isset($uploaded[$fileKey])) {
                    $uploaded[$fileKey] = $uploadedFile;
                }
            }

            $normalizedFields = $fields;
            $fileIds = [];

            foreach ($fieldMap as $fieldKey => $fieldDefinition) {
                if (($fieldDefinition['type'] ?? null) === 'phone' && isset($normalizedFields[$fieldKey])) {
                    $normalizedFields[$fieldKey.'_normalized'] = preg_replace('/\D+/', '', (string) $normalizedFields[$fieldKey]);
                }

                $file = $uploaded[$fieldKey] ?? ($uploaded['file'] ?? null);
                if ($file instanceof UploadedFile) {
                    $fileIds[$fieldKey] = $this->storeClaimFile($file, (string) $user?->id);
                } elseif (! empty($normalizedFields[$fieldKey]) && ($fieldDefinition['type'] ?? null) === 'file') {
                    $fileIds[$fieldKey] = (string) $normalizedFields[$fieldKey];
                }
            }

            // Also check if feedback_video file was uploaded directly under uploaded array
            if ($activityCode === 'peers_global_feedback_video' && ! isset($fileIds['feedback_video'])) {
                $videoFile = $uploaded['feedback_video'] ?? ($uploaded['file'] ?? null);
                if ($videoFile instanceof UploadedFile) {
                    $fileIds['feedback_video'] = $this->storeClaimFile($videoFile, (string) $user?->id);
                }
            }

            $claimPayload = [
                'fields' => $normalizedFields,
                'files' => $fileIds,
            ];

            if ($activityCode === 'peers_global_feedback_video') {
                $claimPayload['feedback_video'] = $fileIds['feedback_video']
                    ?? ($normalizedFields['feedback_video'] ?? ($normalizedFields['feedback_video_url'] ?? ($payloadInput['feedback_video'] ?? null)));
            }

            $claim = CoinClaimRequest::create([
                'user_id' => $user->id,
                'activity_code' => $activityCode,
                'payload' => $claimPayload,
                'status' => 'pending',
                'coins_awarded' => null,
            ]);

            $claim->load('user:id,display_name,first_name,last_name,email,phone');

            $this->emailService->sendSubmitted($claim);

            return $this->success(new CoinClaimRequestResource($claim), 'Coin claim submitted successfully.', 201);
        } catch (ValidationException $exception) {
            Log::error('Coin claim validation failed', [
                'request_id' => $requestId,
                'user_id' => $user?->id,
                'errors' => $exception->errors(),
            ]);

            return $this->error('Validation failed.', 422, $exception->errors());
        } catch (\Throwable $exception) {
            Log::error('Coin claim submission failed', [
                'request_id' => $requestId,
                'user_id' => $user?->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error($exception->getMessage(), 500);
        }
    }

    public function myRequests(Request $request): JsonResponse
    {
        $requestId = (string) Str::uuid();
        $user = $request->user();

        Log::info('Coin claim my-requests fetch received', [
            'request_id' => $requestId,
            'user_id' => $user?->id,
            'status' => $request->query('status'),
            'per_page' => $request->query('per_page'),
        ]);

        try {
            $validator = Validator::make($request->query(), [
                'status' => ['nullable', 'in:pending,approved,rejected'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            ]);

            if ($validator->fails()) {
                return $this->error('Validation failed.', 422, $validator->errors());
            }

            $validated = $validator->validated();
            $perPage = (int) ($validated['per_page'] ?? 20);
            $status = $validated['status'] ?? null;

            $paginator = CoinClaimRequest::query()
                ->where('user_id', $user->id)
                ->when($status, fn ($query) => $query->where('status', $status))
                ->orderByDesc('created_at')
                ->paginate($perPage);

            return $this->success([
                'items' => CoinClaimRequestResource::collection($paginator->getCollection())->resolve(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ], null);
        } catch (\Throwable $exception) {
            Log::error('Coin claim my-requests fetch failed', [
                'request_id' => $requestId,
                'user_id' => $user?->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->error($exception->getMessage(), 500);
        }
    }

    private function storeClaimFile(UploadedFile $file, string $userId): string
    {
        $disk = config('filesystems.default', 'public');
        $path = $file->store('uploads/'.now()->format('Y/m/d'), $disk);

        $record = FileModel::create([
            'uploader_user_id' => $userId,
            's3_key' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        return (string) $record->id;
    }

    private function resolveCoinsAwarded(string $activityCode): int
    {
        $activity = $this->registry->get($activityCode);

        if (! is_array($activity)) {
            return 0;
        }

        $configuredCoins = $activity['coins'] ?? 0;

        if (is_numeric($configuredCoins)) {
            return max(0, (int) $configuredCoins);
        }

        return 0;
    }
}
