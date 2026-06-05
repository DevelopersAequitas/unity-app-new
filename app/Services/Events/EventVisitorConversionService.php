<?php

namespace App\Services\Events;

use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EventVisitorConversionService
{
    public function convertPaidVisitor(EventRegistration $registration): EventRegistration
    {
        if (! in_array((string) ($registration->registration_type ?? ''), ['visitor', 'app_user_visitor'], true)) {
            return $registration;
        }

        if ((string) ($registration->payment_status ?? '') !== 'paid') {
            return $registration;
        }

        $user = $registration->user ?: $this->findUserByEmailOrPhone($registration->visitor_email, $registration->visitor_phone);
        $user = $user ? $this->updateFreePeerUser($user, $registration) : $this->createFreePeerUser($registration);

        $registration->forceFill($this->registrationFilter([
            'user_id' => $user->id,
            'status' => in_array((string) ($registration->status ?? ''), ['approved', 'registered'], true) ? $registration->status : 'registered',
            'payment_status' => 'paid',
            'payment_completed_at' => $registration->payment_completed_at ?: now(),
            'paid_at' => $registration->payment_completed_at ?: now(),
            'metadata' => array_merge((array) ($registration->metadata ?? []), [
                'converted_to_free_peer_user_id' => (string) $user->id,
                'converted_to_free_peer_at' => now()->toIso8601String(),
            ]),
        ]))->save();

        Log::info('event_visitor_converted_to_free_peer', [
            'registration_id' => (string) $registration->id,
            'user_id' => (string) $user->id,
        ]);

        return $registration->fresh(['event', 'occurrence', 'user', 'invitedByUser', 'businessCategoryMain', 'businessCategorySub']);
    }

    private function findUserByEmailOrPhone(?string $email, ?string $phone): ?User
    {
        if (blank($email) && blank($phone)) {
            return null;
        }

        if (filled($email)) {
            $user = User::query()->whereRaw('LOWER(email) = ?', [strtolower((string) $email)])->first();
            if ($user) {
                return $user;
            }
        }

        return filled($phone) ? User::query()->where('phone', $phone)->first() : null;
    }

    private function createFreePeerUser(EventRegistration $registration): User
    {
        $name = $this->visitorName($registration);
        [$firstName, $lastName] = array_pad(preg_split('/\s+/', $name, 2), 2, null);

        return User::query()->create($this->userFilter($this->userAttributes($registration) + [
            'first_name' => $firstName ?: $name,
            'last_name' => $lastName,
            'password_hash' => Hash::make(Str::random(32)),
            'password' => Hash::make(Str::random(32)),
        ]));
    }

    private function updateFreePeerUser(User $user, EventRegistration $registration): User
    {
        $user->forceFill($this->userFilter($this->userAttributes($registration, $user)))->save();

        return $user->refresh();
    }

    private function userAttributes(EventRegistration $registration, ?User $user = null): array
    {
        $name = $this->visitorName($registration);
        [$firstName, $lastName] = array_pad(preg_split('/\s+/', $name, 2), 2, null);

        return [
            'first_name' => $user?->first_name ?: ($firstName ?: $name),
            'last_name' => $user?->last_name ?: $lastName,
            'display_name' => $name,
            'name' => $name,
            'email' => $this->safeUniqueUserValue('email', $registration->visitor_email, $user),
            'phone' => $this->safeUniqueUserValue('phone', $registration->visitor_phone, $user),
            'company_name' => $registration->visitor_company ?: $user?->company_name,
            'city' => $registration->visitor_city ?: $user?->city,
            'city_of_residence' => $registration->visitor_city ?: $user?->city_of_residence,
            'designation' => $registration->visitor_designation ?: $user?->designation,
            'main_business_category_id' => $registration->visitor_business_category_main_id ?: $user?->main_business_category_id,
            'business_category_main_id' => $registration->visitor_business_category_main_id ?: ($user?->business_category_main_id ?? null),
            'business_category_id' => $registration->visitor_business_category_sub_id ?: $user?->business_category_id,
            'business_category_sub_id' => $registration->visitor_business_category_sub_id ?: ($user?->business_category_sub_id ?? null),
            'business_website' => $registration->visitor_business_website ?: $user?->business_website,
            'short_bio' => $registration->visitor_business_brief ?: $user?->short_bio,
            'business_brief' => $registration->visitor_business_brief ?: ($user?->business_brief ?? null),
            'membership_status' => User::freePeerMembershipStatus(),
        ];
    }

    private function safeUniqueUserValue(string $column, ?string $value, ?User $user): ?string
    {
        $value = filled($value) ? (string) $value : null;
        if ($value === null) {
            return $user?->{$column};
        }

        $exists = User::query()
            ->where($column, $value)
            ->when($user, fn ($query) => $query->where('id', '!=', $user->id))
            ->exists();

        return $exists ? $user?->{$column} : $value;
    }

    private function visitorName(EventRegistration $registration): string
    {
        return trim((string) ($registration->visitor_name ?: $registration->user?->display_name ?: $registration->visitor_email ?: $registration->visitor_phone ?: 'Event Visitor')) ?: 'Event Visitor';
    }

    private function userFilter(array $data): array
    {
        return array_filter($data, fn ($value, $key) => $value !== null && Schema::hasColumn('users', $key), ARRAY_FILTER_USE_BOTH);
    }

    private function registrationFilter(array $data): array
    {
        return array_filter($data, fn ($value, $key) => $value !== null && Schema::hasColumn('event_registrations', $key), ARRAY_FILTER_USE_BOTH);
    }
}
