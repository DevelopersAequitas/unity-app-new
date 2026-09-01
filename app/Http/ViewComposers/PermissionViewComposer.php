<?php

declare(strict_types=1);

namespace App\Http\ViewComposers;

use App\Services\Admin\PermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PermissionViewComposer
{
    public function __construct(
        private readonly PermissionService $permissionService,
    ) {}

    public function compose(View $view): void
    {
        $admin = Auth::guard('admin')->user();

        if (! $admin) {
            $view->with('dynamicModules', collect());
            $view->with('hasDynamicRbac', false);
            $view->with('permissionService', $this->permissionService);

            return;
        }

        $modules = $this->permissionService->visibleModules($admin);
        $hasDynamicRbac = $modules->isNotEmpty();

        $view->with('dynamicModules', $modules);
        $view->with('hasDynamicRbac', $hasDynamicRbac);
        $view->with('permissionService', $this->permissionService);
    }
}
