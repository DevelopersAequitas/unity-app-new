<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    /**
     * Display a listing of the support tickets.
     */
    public function index(Request $request): View
    {
        $query = SupportTicket::query()->with('user');

        // Filter by Status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by Priority
        if ($request->filled('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        // Search term
        if ($request->filled('search')) {
            $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->search).'%';
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'ilike', $search)
                    ->orWhere('contact_name', 'ilike', $search)
                    ->orWhere('email', 'ilike', $search)
                    ->orWhere('subject', 'ilike', $search);
            });
        }

        $tickets = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('admin.support_tickets.index', compact('tickets'));
    }

    /**
     * Display the specified support ticket detail.
     */
    public function show(string $id): View
    {
        $ticket = SupportTicket::query()->with('user')->findOrFail($id);

        return view('admin.support_tickets.show', compact('ticket'));
    }

    /**
     * Update the specified support ticket (status, priority, admin note).
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:open,in_progress,resolved,closed'],
            'priority' => ['required', 'string', 'in:low,normal,high,urgent'],
            'admin_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $ticket = SupportTicket::query()->findOrFail($id);
        $previousStatus = $ticket->status;

        $ticket->fill($validated);

        if ($validated['status'] === 'resolved' && $previousStatus !== 'resolved') {
            $ticket->resolved_at = now();
        } elseif ($validated['status'] !== 'resolved') {
            $ticket->resolved_at = null;
        }

        $ticket->save();

        return redirect()->route('admin.support-tickets.show', $id)
            ->with('success', 'Support ticket updated successfully.');
    }
}
