<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Web;

use App\Http\Controllers\Controller;
use App\Models\Web\WebMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebMessageController extends Controller
{
    public function index(Request $request): View
    {
        $messages = WebMessage::latest()->paginate(15);

        return view('admin.web.messages.index', [
            'messages' => $messages,
        ]);
    }

    public function markRead(string $id): RedirectResponse
    {
        $message = WebMessage::findOrFail($id);
        $message->update(['status' => 'read']);

        return redirect()->route('admin.web.messages.index')->with('success', 'Message marked as read.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $message = WebMessage::findOrFail($id);
        $message->delete();

        return redirect()->route('admin.web.messages.index')->with('success', 'Message removed.');
    }
}
