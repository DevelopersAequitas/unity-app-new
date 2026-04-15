<?php

namespace App\Observers;

use App\Models\Referral;
use App\Services\LifeImpact\LifeImpactService;

class ReferralObserver
{
    public function deleted(Referral $referral): void
    {
        app(LifeImpactService::class)->removeImpactBySource(
            (string) $referral->from_user_id,
            'referral',
            (string) $referral->id,
        );
    }

    public function restored(Referral $referral): void
    {
        app(LifeImpactService::class)->addImpact(
            (string) $referral->from_user_id,
            (string) $referral->from_user_id,
            'referral',
            (string) $referral->id,
            1,
            'Gave a qualified business referral',
            'Life impact restored for referral activity.',
            [
                'referral_type' => $referral->referral_type,
                'referral_date' => $referral->referral_date,
                'referral_of' => $referral->referral_of,
                'phone' => $referral->phone,
                'email' => $referral->email,
                'address' => $referral->address,
                'hot_value' => $referral->hot_value,
                'remarks' => $referral->remarks,
                'to_user_id' => $referral->to_user_id ? (string) $referral->to_user_id : null,
            ],
        );
    }
}
