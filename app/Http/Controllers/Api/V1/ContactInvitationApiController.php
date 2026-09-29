<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\ContactInvitation;
use App\Models\ContactPost;
use App\Models\User;
use App\Services\Notifications\ReferralInvitationWhatsappService;
use App\Services\Notifications\WhatsappNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactInvitationApiController extends BaseApiController
{
    public function __construct(
        private readonly ReferralInvitationWhatsappService $whatsappReferralService
    ) {}

    /**
     * Send referral invitations to selected contacts and trigger WhatsApp messages.
     *
     * POST /api/v1/referrals/send-invitations
     * POST /api/v1/contact-invitations/send
     */
    public function sendInvitations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['nullable', 'string'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.contact_name' => ['nullable', 'string', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:50'],
            'contacts.*.mobile' => ['nullable', 'string', 'max:50'],
            'contacts.*.email' => ['nullable', 'string', 'max:255'],
            'contacts.*.contact_post_id' => ['nullable', 'string'],
            'contact_post_ids' => ['nullable', 'array'],
            'contact_post_ids.*' => ['string'],
            'invitation_message' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        // Resolve Sender User
        $userId = $request->input('user_id') ?? optional($request->user())->id;
        if (! $userId) {
            return $this->error('User ID is required.', 422);
        }

        $sender = User::query()->find($userId);
        if (! $sender) {
            return $this->error('Sender user not found.', 404);
        }

        $incomingContacts = $request->input('contacts', []);
        $contactPostIds = $request->input('contact_post_ids', []);

        // If contact_post_ids are provided, load their details from contact_posts table
        if (! empty($contactPostIds)) {
            $matchedPosts = ContactPost::query()
                ->whereIn('id', $contactPostIds)
                ->get();

            foreach ($matchedPosts as $post) {
                $phone = $post->phone;
                if (empty($phone) && is_array($post->phones) && ! empty($post->phones)) {
                    $firstPhone = $post->phones[0];
                    $phone = is_array($firstPhone) ? ($firstPhone['number'] ?? $firstPhone['phone'] ?? '') : (string) $firstPhone;
                }

                $incomingContacts[] = [
                    'contact_post_id' => $post->id,
                    'contact_name' => $post->full_name ?: trim(($post->first_name ?? '').' '.($post->last_name ?? '')) ?: 'Friend',
                    'phone' => (string) $phone,
                    'email' => $post->email,
                ];
            }
        }

        if (empty($incomingContacts)) {
            return $this->error('No contacts selected for invitation.', 422);
        }

        $processedInvitations = [];
        $completedCount = 0;
        $failedCount = 0;
        $customMessage = $request->input('invitation_message');

        foreach ($incomingContacts as $item) {
            $name = trim((string) ($item['contact_name'] ?? $item['name'] ?? 'Friend'));
            $phone = trim((string) ($item['phone'] ?? $item['mobile'] ?? ''));
            $email = trim((string) ($item['email'] ?? '')) ?: null;
            $contactPostId = $item['contact_post_id'] ?? null;

            if ($phone === '') {
                $failedCount++;

                continue;
            }

            $normalizedPhone = WhatsappNotificationService::normalizePhone($phone);

            // If contact_post_id was not provided, attempt matching by phone in user's synced contact_posts
            if (empty($contactPostId) && $normalizedPhone !== '') {
                $foundPost = ContactPost::query()
                    ->where('user_id', $sender->id)
                    ->where(function ($q) use ($phone, $normalizedPhone) {
                        $q->where('phone', $phone)
                            ->orWhere('phone', $normalizedPhone)
                            ->orWhere('phone', 'LIKE', '%'.substr($normalizedPhone, -10).'%');
                    })
                    ->first();

                if ($foundPost) {
                    $contactPostId = $foundPost->id;
                    if ($name === 'Friend' && filled($foundPost->full_name)) {
                        $name = $foundPost->full_name;
                    }
                }
            }

            // Create Contact Invitation entry
            $invitation = ContactInvitation::create([
                'user_id' => $sender->id,
                'contact_post_id' => $contactPostId,
                'contact_name' => $name,
                'contact_phone' => $phone,
                'contact_email' => $email,
                'mobile_normalized' => $normalizedPhone,
                'invitation_message' => $customMessage,
                'status' => 'pending',
                'whatsapp_status' => 'not_completed',
            ]);

            // Dispatch WhatsApp message
            $sent = $this->whatsappReferralService->sendInvitation($invitation, $sender);

            if ($sent) {
                $completedCount++;
            } else {
                $failedCount++;
            }

            $invitation->refresh();
            $processedInvitations[] = [
                'id' => $invitation->id,
                'contact_name' => $invitation->contact_name,
                'contact_phone' => $invitation->contact_phone,
                'mobile_normalized' => $invitation->mobile_normalized,
                'contact_email' => $invitation->contact_email,
                'contact_post_id' => $invitation->contact_post_id,
                'status' => $invitation->status,
                'whatsapp_status' => $invitation->whatsapp_status,
                'whatsapp_sent_at' => $invitation->whatsapp_sent_at?->format('Y-m-d H:i:s'),
                'created_at' => $invitation->created_at?->format('Y-m-d H:i:s'),
                'error_message' => $invitation->error_message,
            ];
        }

        return $this->success([
            'sender' => [
                'user_id' => $sender->id,
                'name' => $sender->adminDisplayName(),
                'phone' => $sender->phone,
                'email' => $sender->email,
                'peer_id' => $sender->peer_id ?? null,
            ],
            'total_selected' => count($incomingContacts),
            'total_completed' => $completedCount,
            'total_failed' => $failedCount,
            'invitations' => $processedInvitations,
        ], 'Invitations processed successfully');
    }

    /**
     * Get list and status of sent invitations.
     *
     * GET /api/v1/referrals/invitation-history
     * GET /api/v1/contact-invitations/history
     */
    public function getInvitationHistory(Request $request): JsonResponse
    {
        $userId = $request->query('user_id') ?? optional($request->user())->id;
        if (! $userId) {
            return $this->error('User ID is required.', 422);
        }

        $status = $request->query('status'); // all | completed | not_completed
        $search = trim((string) $request->query('search', ''));
        $perPage = min((int) $request->query('per_page', 20), 100);

        $query = ContactInvitation::query()
            ->where('user_id', $userId)
            ->when($status && $status !== 'all', function ($q) use ($status) {
                if ($status === 'completed' || $status === 'sent') {
                    $q->where('whatsapp_status', 'completed');
                } elseif ($status === 'not_completed' || $status === 'failed') {
                    $q->where('whatsapp_status', '!=', 'completed');
                }
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sq) use ($search) {
                    $sq->where('contact_name', 'ILIKE', "%{$search}%")
                        ->orWhere('contact_phone', 'ILIKE', "%{$search}%")
                        ->orWhere('contact_email', 'ILIKE', "%{$search}%");
                });
            })
            ->latest('created_at');

        $paginated = $query->paginate($perPage);

        return $this->success([
            'items' => collect($paginated->items())->map(fn ($item) => [
                'id' => $item->id,
                'contact_name' => $item->contact_name,
                'contact_phone' => $item->contact_phone,
                'mobile_normalized' => $item->mobile_normalized,
                'contact_email' => $item->contact_email,
                'status' => $item->status,
                'whatsapp_status' => $item->whatsapp_status,
                'whatsapp_sent_at' => $item->whatsapp_sent_at?->format('Y-m-d H:i:s'),
                'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
                'error_message' => $item->error_message,
            ]),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
            'summary' => [
                'total_invitations' => ContactInvitation::query()->where('user_id', $userId)->count(),
                'total_completed' => ContactInvitation::query()->where('user_id', $userId)->where('whatsapp_status', 'completed')->count(),
                'total_not_completed' => ContactInvitation::query()->where('user_id', $userId)->where('whatsapp_status', '!=', 'completed')->count(),
            ],
        ], 'Invitation history fetched successfully');
    }

    /**
     * Get invitation statistics for user.
     *
     * GET /api/v1/referrals/invitation-stats
     */
    public function getStats(Request $request): JsonResponse
    {
        $userId = $request->query('user_id') ?? optional($request->user())->id;
        if (! $userId) {
            return $this->error('User ID is required.', 422);
        }

        $total = ContactInvitation::query()->where('user_id', $userId)->count();
        $completed = ContactInvitation::query()->where('user_id', $userId)->where('whatsapp_status', 'completed')->count();
        $notCompleted = $total - $completed;

        return $this->success([
            'total_invitations' => $total,
            'completed' => $completed,
            'not_completed' => $notCompleted,
        ], 'Stats retrieved successfully');
    }
}
