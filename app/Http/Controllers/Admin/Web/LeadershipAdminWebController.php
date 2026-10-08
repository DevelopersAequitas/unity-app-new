<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LeadershipAdminWebController extends Controller
{
    public function index(Request $request): View
    {
        $admin = Auth::guard('admin')->user();

        $adminContext = [
            'id' => $admin?->id,
            'name' => $admin?->name ?? 'Admin',
            'email' => $admin?->email,
            'roles' => $admin?->roles?->pluck('key')->toArray() ?? ['global_admin'],
            'is_super' => true,
        ];

        return view('admin.web.leadership.index', [
            'adminContext' => $adminContext,
        ]);
    }
}
