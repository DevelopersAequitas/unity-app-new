<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Event;
use App\Models\ScanAppUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class EventScanCredentialAdminController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $credentials = ScanAppUser::query()
            ->with('event')
            ->when($request->search, function ($q, $search): void {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('name', 'ilike', $like)
                        ->orWhere('username', 'ilike', $like)
                        ->orWhere('hotel_name', 'ilike', $like)
                        ->orWhere('event_name', 'ilike', $like);
                });
            })
            ->latest('created_at')
            ->paginate((int) ($request->per_page ?? 20));

        return $this->success($credentials);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('scan_app_users', 'username')],
            'password' => ['required', 'string', 'min:6'],
            'hotel_name' => ['nullable', 'string', 'max:255'],
            'event_id' => ['nullable', 'string'],
            'event_ids' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $eventIds = [];
        if (! empty($validated['event_ids'])) {
            $eventIds = array_values(array_unique($validated['event_ids']));
        } elseif (! empty($validated['event_id'])) {
            $eventIds = [$validated['event_id']];
        }

        $firstEvent = ! empty($eventIds) ? Event::query()->find($eventIds[0]) : null;

        $attributes = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'password_hash' => Hash::make($validated['password']),
            'hotel_name' => $validated['hotel_name'] ?? 'Main Entrance',
            'event_id' => $firstEvent?->id ?? ($eventIds[0] ?? null),
            'event_ids' => $eventIds,
            'event_name' => $firstEvent?->title ?? '',
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];

        if (Schema::hasColumn('scan_app_users', 'plain_password')) {
            $attributes['plain_password'] = $validated['password'];
        }

        $credential = ScanAppUser::query()->create($attributes);

        return $this->success($credential, 'Scan credential created successfully.', 201);
    }

    public function show(string $id): JsonResponse
    {
        $credential = ScanAppUser::query()->with('event')->find($id);
        if (! $credential) {
            return $this->error('Scan credential not found', 404);
        }

        return $this->success($credential);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $credential = ScanAppUser::query()->find($id);
        if (! $credential) {
            return $this->error('Scan credential not found', 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['sometimes', 'string', 'max:255', Rule::unique('scan_app_users', 'username')->ignore($credential->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'hotel_name' => ['nullable', 'string', 'max:255'],
            'event_id' => ['nullable', 'string'],
            'event_ids' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $updates = [];
        if (isset($validated['name'])) $updates['name'] = $validated['name'];
        if (isset($validated['username'])) $updates['username'] = $validated['username'];
        if (isset($validated['hotel_name'])) $updates['hotel_name'] = $validated['hotel_name'];
        if (isset($validated['is_active'])) $updates['is_active'] = (bool) $validated['is_active'];

        if (! empty($validated['event_ids'])) {
            $eventIds = array_values(array_unique($validated['event_ids']));
            $firstEvent = Event::query()->find($eventIds[0]);
            $updates['event_ids'] = $eventIds;
            $updates['event_id'] = $firstEvent?->id ?? $eventIds[0];
            $updates['event_name'] = $firstEvent?->title ?? '';
        } elseif (! empty($validated['event_id'])) {
            $firstEvent = Event::query()->find($validated['event_id']);
            $updates['event_id'] = $firstEvent?->id ?? $validated['event_id'];
            $updates['event_ids'] = [$updates['event_id']];
            $updates['event_name'] = $firstEvent?->title ?? '';
        }

        if (! empty($validated['password'])) {
            $updates['password_hash'] = Hash::make($validated['password']);
            if (Schema::hasColumn('scan_app_users', 'plain_password')) {
                $updates['plain_password'] = $validated['password'];
            }
        }

        $credential->update($updates);

        return $this->success($credential, 'Scan credential updated successfully.');
    }

    public function destroy(string $id): JsonResponse
    {
        $credential = ScanAppUser::query()->find($id);
        if (! $credential) {
            return $this->error('Scan credential not found', 404);
        }

        $credential->delete();

        return $this->success(null, 'Scan credential deleted successfully.');
    }
}