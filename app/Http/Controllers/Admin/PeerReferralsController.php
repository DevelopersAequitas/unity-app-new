<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\PeerReferral;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PeerReferralsController extends Controller
{
    public function index(Request $request): View
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin) {
            abort(401);
        }

        $query = PeerReferral::query()
            ->with(['referrer.activeCircle', 'referrer.cityRelation', 'referrer.mainBusinessCategory', 'mainCircle', 'circle', 'category'])
            ->latest('created_at');

        // Apply search
        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('referred_name', 'ILIKE', $like)
                    ->orWhere('referred_phone', 'ILIKE', $like)
                    ->orWhere('referred_email', 'ILIKE', $like)
                    ->orWhere('referred_company_name', 'ILIKE', $like)
                    ->orWhereHas('referrer', fn ($r) => $r->where('display_name', 'ILIKE', $like)
                        ->orWhere('first_name', 'ILIKE', $like)
                        ->orWhere('last_name', 'ILIKE', $like));
            });
        }

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('main_circle_id')) {
            $query->where('main_circle_id', $request->query('main_circle_id'));
        }

        if ($request->filled('circle_id')) {
            $query->where('circle_id', $request->query('circle_id'));
        }

        $peerReferrals = $query->paginate(25)->appends($request->query());

        $circles = Circle::query()->select('id', 'name')->orderBy('name')->get();

        return view('admin.peer_referrals.index', [
            'peerReferrals' => $peerReferrals,
            'circles' => $circles,
            'filters' => $request->only(['search', 'status', 'main_circle_id', 'circle_id']),
        ]);
    }

    public function show(string $id): View
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin) {
            abort(401);
        }

        $peerReferral = PeerReferral::with(['referrer.activeCircle', 'referrer.cityRelation', 'referrer.mainBusinessCategory', 'mainCircle', 'circle', 'category'])->findOrFail($id);

        return view('admin.peer_referrals.show', [
            'peerReferral' => $peerReferral,
        ]);
    }

    public function updateStatus(Request $request, string $id): RedirectResponse
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin) {
            abort(401);
        }

        $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'contacted', 'accepted', 'rejected', 'converted'])],
        ]);

        DB::transaction(function () use ($id, $request): void {
            /** @var PeerReferral $peerReferral */
            $peerReferral = PeerReferral::lockForUpdate()->findOrFail($id);

            $oldStatus = $peerReferral->status;
            $peerReferral->status = $request->input('status');
            $peerReferral->save();

            Log::info('peer_referral.status_updated', [
                'peer_referral_id' => $peerReferral->id,
                'old_status' => $oldStatus,
                'new_status' => $peerReferral->status,
                'admin_id' => Auth::guard('admin')->id(),
            ]);
        });

        return back()->with('success', 'Referral status updated successfully.');
    }
}
