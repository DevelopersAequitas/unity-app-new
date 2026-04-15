<?php

namespace App\Observers;

use App\Models\BusinessDeal;
use App\Services\LifeImpact\LifeImpactService;

class BusinessDealObserver
{
    public function deleted(BusinessDeal $businessDeal): void
    {
        app(LifeImpactService::class)->removeImpactBySource(
            (string) $businessDeal->from_user_id,
            'business_deal',
            (string) $businessDeal->id,
        );
    }

    public function restored(BusinessDeal $businessDeal): void
    {
        app(LifeImpactService::class)->addImpact(
            (string) $businessDeal->from_user_id,
            (string) $businessDeal->from_user_id,
            'business_deal',
            (string) $businessDeal->id,
            5,
            'Closed a business deal',
            'Life impact restored for business deal activity.',
            [
                'deal_date' => $businessDeal->deal_date,
                'deal_amount' => $businessDeal->deal_amount,
                'business_type' => $businessDeal->business_type,
                'comment' => $businessDeal->comment,
                'to_user_id' => $businessDeal->to_user_id ? (string) $businessDeal->to_user_id : null,
            ],
        );
    }
}
