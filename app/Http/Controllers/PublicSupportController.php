<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PublicSupportStatusRequest;
use App\Http\Requests\PublicSupportTicketRequest;
use App\Models\User;
use App\Services\Support\PublicSupportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PublicSupportController extends Controller
{
    public function __construct(
        protected readonly PublicSupportService $supportService
    ) {}

    /**
     * Display the public support & helpdesk portal.
     */
    public function show(Request $request): View
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        $trackedTicket = null;
        if ($request->filled('ticket_number') && $request->filled('email')) {
            $trackedTicket = $this->supportService->findTicketForStatus(
                (string) $request->input('ticket_number'),
                (string) $request->input('email')
            );
        }

        return view('public.support', [
            'user' => $user,
            'trackedTicket' => $trackedTicket,
        ]);
    }

    /**
     * Submit a new support ticket.
     */
    public function submit(PublicSupportTicketRequest $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        $media = $request->hasFile('media') ? $request->file('media') : null;

        $ticket = $this->supportService->createTicket(
            $request->validated(),
            $media,
            $user
        );

        return redirect()->route('support.show')->with([
            'ticket_success' => 'Your support request has been submitted successfully!',
            'ticket_number' => $ticket->ticket_number,
            'ticket_email' => $ticket->email,
        ]);
    }

    /**
     * Track an existing ticket's status.
     */
    public function checkStatus(PublicSupportStatusRequest $request): View
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        $trackedTicket = $this->supportService->findTicketForStatus(
            (string) $request->input('ticket_number'),
            (string) $request->input('email')
        );

        return view('public.support', [
            'user' => $user,
            'trackedTicket' => $trackedTicket,
            'searchAttempted' => true,
            'searchedTicketNumber' => (string) $request->input('ticket_number'),
            'searchedEmail' => (string) $request->input('email'),
        ]);
    }
}
