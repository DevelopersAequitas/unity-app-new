<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class WebEventController extends Controller
{
    public function index(Request $request): View
    {
        $events = Schema::hasTable('events')
            ? Event::latest('event_date')->paginate(15)
            : collect();

        return view('admin.web.events.index', [
            'events' => $events,
        ]);
    }
}
