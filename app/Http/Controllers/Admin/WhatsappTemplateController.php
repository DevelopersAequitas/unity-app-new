<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateWhatsappTemplateRequest;
use App\Models\WhatsappTemplate;
use App\Services\WhatsApp\WhatsappTemplateCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class WhatsappTemplateController extends Controller
{
    public function __construct(
        private readonly WhatsappTemplateCatalogService $catalogService
    ) {}

    /**
     * Display the WhatsApp Communication Center list.
     */
    public function index(Request $request): View|JsonResponse
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $category = $request->input('category');
        $triggerType = $request->input('trigger_type');

        $templates = $this->catalogService->getAllTemplates(
            is_string($search) ? $search : null,
            is_string($status) ? $status : null,
            is_string($category) ? $category : null,
            is_string($triggerType) ? $triggerType : null
        );

        $stats = $this->catalogService->getMetrics();

        if ($request->wantsJson() && ! $request->isXmlHttpRequest()) {
            return response()->json([
                'success' => true,
                'stats' => $stats,
                'templates' => $templates,
            ]);
        }

        return view('admin.whatsapp_templates.index', [
            'templates' => $templates,
            'stats' => $stats,
            'search' => $search,
            'status' => $status,
            'category' => $category,
            'triggerType' => $triggerType,
        ]);
    }

    /**
     * Display detailed metadata and workflow for a single template.
     */
    public function show(string $idOrKey): JsonResponse
    {
        $details = $this->catalogService->getTemplateDetails($idOrKey);

        if (! $details) {
            return response()->json([
                'success' => false,
                'message' => 'WhatsApp template not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'template' => $details,
        ]);
    }

    /**
     * Show edit data for a template.
     */
    public function edit(string $idOrKey): JsonResponse
    {
        $template = WhatsappTemplate::query()
            ->where('id', $idOrKey)
            ->orWhere('template_key', $idOrKey)
            ->first();

        if (! $template) {
            return response()->json([
                'success' => false,
                'message' => 'WhatsApp template not found.',
            ], 404);
        }

        $details = $this->catalogService->getTemplateDetails($template->id);

        if (! $details) {
            return response()->json([
                'success' => false,
                'message' => 'WhatsApp template not found.',
            ], 404);
        }

        $secret = (string) ($template->webhook_secret ?? '');
        $details['webhook_secret'] = $secret;

        return response()->json([
            'success' => true,
            'template' => $details,
            'webhook_secret' => $secret,
        ]);
    }

    /**
     * Update an existing WhatsApp template.
     */
    public function update(UpdateWhatsappTemplateRequest $request, string $idOrKey): RedirectResponse|JsonResponse
    {
        $template = WhatsappTemplate::query()
            ->where('id', $idOrKey)
            ->orWhere('template_key', $idOrKey)
            ->first();

        if (! $template) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'WhatsApp template not found.',
                ], 404);
            }

            return redirect()->route('admin.whatsapp-templates.index')
                ->with('error', 'WhatsApp template not found.');
        }

        $validated = $request->validated();

        $newName = trim((string) $validated['template_name']);
        $newDescription = isset($validated['description']) ? trim((string) $validated['description']) : (string) ($template->description ?? '');
        $newWebhookUrl = trim((string) $validated['webhook_url']);
        $newWebhookSecret = trim((string) $validated['webhook_secret']);
        $newIsActive = (bool) $validated['is_active'];

        $nameChanged = ($template->template_name !== $newName);
        $urlChanged = ((string) ($template->webhook_url ?? '') !== $newWebhookUrl);
        $secretChanged = ((string) ($template->webhook_secret ?? '') !== $newWebhookSecret);

        if (! ($nameChanged && $urlChanged && $secretChanged)) {
            $errorMessage = 'Please update all three fields before saving.';

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'errors' => [
                        'general' => [$errorMessage],
                    ],
                ], 422);
            }

            return redirect()->route('admin.whatsapp-templates.index')
                ->with('error', $errorMessage);
        }

        $template->update([
            'template_name' => $newName,
            'description' => $newDescription,
            'webhook_url' => $newWebhookUrl,
            'webhook_secret' => $newWebhookSecret,
            'is_active' => $newIsActive,
        ]);

        Log::info('[WhatsappTemplateController] WhatsApp template updated.', [
            'template_key' => $template->template_key,
            'template_id' => $template->id,
            'updated_by' => Auth::guard('admin')->id() ?? 'system',
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'WhatsApp template updated successfully.',
                'template' => $this->catalogService->getTemplateDetails($template->id),
            ]);
        }

        return redirect()->route('admin.whatsapp-templates.index')
            ->with('success', "WhatsApp template '{$template->template_name}' updated successfully.");
    }

    /**
     * Toggle Active/Inactive status.
     */
    public function toggleStatus(Request $request, string $idOrKey): JsonResponse
    {
        $template = WhatsappTemplate::query()
            ->where('id', $idOrKey)
            ->orWhere('template_key', $idOrKey)
            ->first();

        if (! $template) {
            return response()->json([
                'success' => false,
                'message' => 'WhatsApp template not found.',
            ], 404);
        }

        $newState = ! $template->is_active;
        $template->update(['is_active' => $newState]);

        Log::info('[WhatsappTemplateController] WhatsApp template status toggled.', [
            'template_key' => $template->template_key,
            'is_active' => $newState,
            'admin_id' => Auth::guard('admin')->id() ?? 'system',
        ]);

        $message = $newState
            ? 'Template activated. WhatsApp notifications using this template are enabled again.'
            : 'Template deactivated. Future WhatsApp notifications using this template will be skipped.';

        return response()->json([
            'success' => true,
            'is_active' => $newState,
            'status_label' => $newState ? 'Active' : 'Inactive',
            'message' => $message,
        ]);
    }

    /**
     * Securely reveal the webhook secret for an authorized admin.
     */
    public function revealSecret(Request $request, string $idOrKey): JsonResponse
    {
        $template = WhatsappTemplate::query()
            ->where('id', $idOrKey)
            ->orWhere('template_key', $idOrKey)
            ->first();

        if (! $template) {
            return response()->json([
                'success' => false,
                'message' => 'WhatsApp template not found.',
            ], 404);
        }

        $secret = (string) ($template->webhook_secret ?? '');

        Log::info('[WhatsappTemplateController] Webhook secret revealed by admin.', [
            'template_key' => $template->template_key,
            'admin_id' => Auth::guard('admin')->id() ?? 'system',
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'secret' => $secret,
            'has_secret' => trim($secret) !== '',
        ]);
    }
}
