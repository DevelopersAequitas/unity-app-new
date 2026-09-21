<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\Web\WebSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebSettingController extends Controller
{
    public function index(Request $request): View
    {
        $settings = WebSetting::all()->groupBy('group');

        return view('admin.web.settings.index', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $inputs = $request->except(['_token', '_method']);

        foreach ($inputs as $key => $value) {
            WebSetting::updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value]
            );
        }

        return redirect()->route('admin.web.settings.index')->with('success', 'Website settings saved.');
    }
}
