<?php

namespace App\Providers;

use App\Models\BusinessDeal;
use App\Models\Referral;
use App\Models\Testimonial;
use App\Models\VisitorRegistration;
use App\Observers\BusinessDealObserver;
use App\Observers\ReferralObserver;
use App\Observers\TestimonialObserver;
use App\Observers\VisitorRegistrationObserver;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('Support/helpers.php');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        BusinessDeal::observe(BusinessDealObserver::class);
        Referral::observe(ReferralObserver::class);
        Testimonial::observe(TestimonialObserver::class);
        VisitorRegistration::observe(VisitorRegistrationObserver::class);
    }
}
