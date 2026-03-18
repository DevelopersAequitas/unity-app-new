<?php

namespace App\Http\Controllers\Api;

use App\Models\UserContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserContactController extends BaseApiController
{
    public function syncContacts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'integer'],
            'phone_user_name' => ['nullable', 'string', 'max:255'],
            'contacts' => ['required', 'array', 'min:1'],
            'contacts.*.contact_name' => ['required', 'string', 'max:255'],
            'contacts.*.mobile' => ['required', 'string', 'max:20'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $payload = $validator->validated();
        $received = count($payload['contacts']);
        $skipped = 0;
        $prepared = [];

        foreach ($payload['contacts'] as $contact) {
            $mobileNormalized = normalize_mobile_number($contact['mobile']);

            if ($mobileNormalized === '') {
                $skipped++;
                continue;
            }

            if (isset($prepared[$mobileNormalized])) {
                $skipped++;
                continue;
            }

            $prepared[$mobileNormalized] = [
                'user_id' => (int) $payload['user_id'],
                'phone_user_name' => $payload['phone_user_name'] ?? null,
                'contact_name' => $contact['contact_name'],
                'mobile' => $contact['mobile'],
                'mobile_normalized' => $mobileNormalized,
            ];
        }

        $uniqueProcessed = count($prepared);
        $updated = 0;
        $inserted = 0;

        if ($uniqueProcessed > 0) {
            DB::transaction(function () use ($payload, $prepared, &$updated, &$inserted): void {
                $now = Carbon::now();
                $mobileNormalizedValues = array_keys($prepared);

                $updated = UserContact::query()
                    ->where('user_id', (int) $payload['user_id'])
                    ->whereIn('mobile_normalized', $mobileNormalizedValues)
                    ->count();

                $rows = array_map(function (array $row) use ($now): array {
                    return [
                        ...$row,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, array_values($prepared));

                foreach (array_chunk($rows, 500) as $chunk) {
                    UserContact::query()->upsert(
                        $chunk,
                        ['user_id', 'mobile_normalized'],
                        ['phone_user_name', 'contact_name', 'mobile', 'updated_at']
                    );
                }

                $inserted = count($rows) - $updated;
            });
        }

        return $this->success([
            'received' => $received,
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'unique_processed' => $uniqueProcessed,
        ], 'Contacts synced successfully');
    }

    public function getContacts(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'data' => $validator->errors(),
            ], 422);
        }

        $contacts = UserContact::query()
            ->select([
                'id',
                'user_id',
                'phone_user_name',
                'contact_name',
                'mobile',
                'mobile_normalized',
                'created_at',
                'updated_at',
            ])
            ->where('user_id', (int) $request->input('user_id'))
            ->latest('id')
            ->get();

        return $this->success($contacts, 'Contacts fetched successfully');
    }
}
