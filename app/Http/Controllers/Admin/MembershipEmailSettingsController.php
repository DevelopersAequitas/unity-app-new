<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class MembershipEmailSettingsController extends Controller
{
    public function index(): View
    {
        $attachments = $this->attachmentRows();

        return view('admin.membership-email-settings.index', [
            'attachments' => $attachments,
            'fileIds' => $attachments->pluck('file_id')->implode("\n"),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file_ids' => ['nullable', 'string'],
        ]);

        $fileIds = collect(preg_split('/[\r\n,]+/', (string) ($validated['file_ids'] ?? '')))
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values();

        $validIds = File::query()
            ->whereIn('id', $fileIds->all())
            ->where('is_orphaned', false)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        DB::transaction(function () use ($validIds): void {
            DB::table('membership_email_attachments')->update(['is_active' => false, 'updated_at' => now()]);

            foreach (array_values($validIds) as $index => $fileId) {
                DB::table('membership_email_attachments')->updateOrInsert(
                    ['file_id' => $fileId],
                    ['sort_order' => $index, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });

        return redirect()->route('admin.membership-email-settings.index')->with('success', 'Membership email attachments updated successfully.');
    }

    private function attachmentRows()
    {
        if (! Schema::hasTable('membership_email_attachments')) {
            return collect();
        }

        return DB::table('membership_email_attachments as mea')
            ->join('files as f', 'f.id', '=', 'mea.file_id')
            ->where('mea.is_active', true)
            ->orderBy('mea.sort_order')
            ->get(['mea.file_id', 'mea.sort_order', 'f.s3_key', 'f.mime_type', 'f.size_bytes', 'f.is_orphaned']);
    }
}
