<?php

namespace App\Providers;

use App\Models\AdminCampaign;
use App\Policies\AdminCampaignPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

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
        Gate::policy(AdminCampaign::class, AdminCampaignPolicy::class);


    }
}
