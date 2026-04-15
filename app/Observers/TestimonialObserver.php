<?php

namespace App\Observers;

use App\Models\Testimonial;
use App\Services\LifeImpact\LifeImpactService;

class TestimonialObserver
{
    public function deleted(Testimonial $testimonial): void
    {
        app(LifeImpactService::class)->removeImpactBySource(
            (string) $testimonial->from_user_id,
            'testimonial',
            (string) $testimonial->id,
        );
    }

    public function restored(Testimonial $testimonial): void
    {
        app(LifeImpactService::class)->addImpact(
            (string) $testimonial->from_user_id,
            (string) $testimonial->from_user_id,
            'testimonial',
            (string) $testimonial->id,
            5,
            'Received a testimonial / review',
            'Life impact restored for testimonial activity.',
            [
                'content' => $testimonial->content,
                'media_ids' => collect($testimonial->media ?? [])->pluck('id')->filter()->values()->all(),
                'from_user_id' => $testimonial->from_user_id ? (string) $testimonial->from_user_id : null,
                'to_user_id' => $testimonial->to_user_id ? (string) $testimonial->to_user_id : null,
            ],
        );
    }
}
