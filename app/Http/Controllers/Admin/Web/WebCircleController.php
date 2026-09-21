<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class WebCircleController extends Controller
{
    public function index(Request $request): View
    {
        $circles = Schema::hasTable('circles')
            ? Circle::withCount('members')->latest()->paginate(15)
            : collect();

        return view('admin.web.circles.index', [
            'circles' => $circles,
        ]);
    }
}
