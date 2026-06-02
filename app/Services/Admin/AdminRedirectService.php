<?php

namespace App\Services\Admin;

use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;

class AdminRedirectService
{
    public function redirectFor(AdminUser $adminUser): RedirectResponse
    {
        $adminUser->loadMissing('roles:id,key');
        $roleKeys = $adminUser->roles->pluck('key')->all();

        if (in_array('global_admin', $roleKeys, true)) {
            return redirect()->route('admin.dashboard');
        }

        if (in_array('ded', $roleKeys, true)) {
            return redirect()->route('admin.dashboard');
        }

        if (in_array('circle_leader', $roleKeys, true)) {
            return redirect()->route('admin.users.index');
        }

        if (in_array('industry_director', $roleKeys, true)) {
            return redirect()->route('admin.industry-director.dashboard');
        }

        abort(403, 'You do not have permission to access the admin panel.');
    }
}
