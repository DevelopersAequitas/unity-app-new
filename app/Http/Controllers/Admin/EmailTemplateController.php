<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class EmailTemplateController extends Controller
{
    // Complete catalog of all templates in the system using Bootstrap Icons (bi-*)
    private static array $catalog = [
        'welcome_peer' => [
            'key' => 'welcome_peer',
            'name' => 'Welcome Mail (Membership & Coins)',
            'description' => 'Sent to new users when their paid membership is approved, introducing them to the platform.',
            'file_path' => 'emails/welcome_peer.blade.php',
            'view_name' => 'emails.welcome_peer',
            'dynamic_params' => [
                '{{ $user->first_name }}' => 'First name of the user',
                '{{ $user->last_name }}' => 'Last name of the user',
                '{{ $user->display_name }}' => 'Display name of the user',
            ],
            'icon' => 'bi bi-person-check',
        ],
        'admin_login_otp' => [
            'key' => 'admin_login_otp',
            'name' => 'Admin Login Verification OTP',
            'description' => 'Sent to administrators when logging into the admin panel for 2FA verification.',
            'file_path' => 'emails/auth/admin_login_otp.blade.php',
            'view_name' => 'emails.auth.admin_login_otp',
            'dynamic_params' => [
                '{{ $name }}' => 'Name of the admin user',
                '{{ $otp }}' => 'The generated 6-digit OTP code',
            ],
            'icon' => 'bi bi-shield-lock',
        ],
        'login_otp' => [
            'key' => 'login_otp',
            'name' => 'User Login Verification OTP',
            'description' => 'Sent to regular users when requesting an OTP verification code to log in.',
            'file_path' => 'emails/auth/login_otp.blade.php',
            'view_name' => 'emails.auth.login_otp',
            'dynamic_params' => [
                '{{ $user->first_name }}' => 'First name of the user',
                '{{ $otp }}' => 'The generated 6-digit OTP code',
            ],
            'icon' => 'bi bi-key',
        ],
        'password_reset_otp' => [
            'key' => 'password_reset_otp',
            'name' => 'Password Reset Verification OTP',
            'description' => 'Sent to users when requesting a password reset OTP verification code.',
            'file_path' => 'emails/auth/password_reset_otp.blade.php',
            'view_name' => 'emails.auth.password_reset_otp',
            'dynamic_params' => [
                '{{ $name }}' => 'Name of the user',
                '{{ $otp }}' => 'The generated 6-digit OTP code',
            ],
            'icon' => 'bi bi-arrow-counterclockwise',
        ],
        'support_ticket_response' => [
            'key' => 'support_ticket_response',
            'name' => 'Support Ticket Response',
            'description' => 'Sent to customers as a response or reply to their submitted support ticket.',
            'file_path' => 'emails/support-ticket-response.blade.php',
            'view_name' => 'emails.support-ticket-response',
            'dynamic_params' => [
                '{{ $ticket->contact_name }}' => 'Name of the ticket contact person',
                '{{ $ticket->ticket_number }}' => 'Ticket reference number',
                '{{ $ticket->subject }}' => 'Subject of the ticket',
                '{{ $responseMessage }}' => 'The response message text written by the admin',
            ],
            'icon' => 'bi bi-ticket-perforated',
        ],
        'support_ticket_resolved' => [
            'key' => 'support_ticket_resolved',
            'name' => 'Support Ticket Resolved',
            'description' => 'Sent to customers when their support ticket has been marked as resolved.',
            'file_path' => 'emails/support-ticket-resolved.blade.php',
            'view_name' => 'emails.support-ticket-resolved',
            'dynamic_params' => [
                '{{ $ticket->contact_name }}' => 'Name of the contact',
                '{{ $ticket->ticket_number }}' => 'Ticket reference number',
            ],
            'icon' => 'bi bi-check-circle',
        ],
        'support_ticket_submitted' => [
            'key' => 'support_ticket_submitted',
            'name' => 'Support Ticket Submitted',
            'description' => 'Confirmation email sent to customer after successfully submitting a new support ticket.',
            'file_path' => 'emails/support_ticket_submitted.blade.php',
            'view_name' => 'emails.support_ticket_submitted',
            'dynamic_params' => [
                '{{ $ticket->contact_name }}' => 'Name of the contact',
                '{{ $ticket->ticket_number }}' => 'Ticket reference number',
            ],
            'icon' => 'bi bi-file-earmark-arrow-up',
        ],
        'p2p_sender' => [
            'key' => 'p2p_sender',
            'name' => 'P2P Meeting Requested (Sender)',
            'description' => 'Sent to the sender/initiator of a P2P meeting request confirming registration.',
            'file_path' => 'emails/p2p_sender.blade.php',
            'view_name' => 'emails.p2p_sender',
            'dynamic_params' => [
                '{{ $actorName }}' => 'Name of the sender',
                '{{ $otherName }}' => 'Name of the meeting partner',
                '{{ $meetingDate }}' => 'Date of the meeting',
                '{{ $meetingPlace }}' => 'Location or meeting link',
            ],
            'icon' => 'bi bi-people',
        ],
        'p2p_receiver' => [
            'key' => 'p2p_receiver',
            'name' => 'P2P Meeting Scheduled (Receiver)',
            'description' => 'Sent to the invitee/receiver of a P2P meeting request containing meeting details.',
            'file_path' => 'emails/p2p_receiver.blade.php',
            'view_name' => 'emails.p2p_receiver',
            'dynamic_params' => [
                '{{ $actorName }}' => 'Name of the sender',
                '{{ $otherName }}' => 'Name of the invitee',
                '{{ $meetingDate }}' => 'Date of the meeting',
                '{{ $meetingPlace }}' => 'Location or meeting link',
            ],
            'icon' => 'bi bi-people',
        ],
        'p2p_meeting_workflow' => [
            'key' => 'p2p_meeting_workflow',
            'name' => 'P2P Meeting Workflow Update',
            'description' => 'Sent when a P2P meeting status changes in the workflow.',
            'file_path' => 'emails/p2p_meeting_workflow.blade.php',
            'view_name' => 'emails.p2p_meeting_workflow',
            'dynamic_params' => [
                '{{ $actorName }}' => 'Actor name',
                '{{ $otherName }}' => 'Participant name',
            ],
            'icon' => 'bi bi-arrow-repeat',
        ],
        'referral_sender' => [
            'key' => 'referral_sender',
            'name' => 'Referral Sent (Sender Confirmation)',
            'description' => 'Confirmation sent to the user who referred someone else to the platform.',
            'file_path' => 'emails/referral_sender.blade.php',
            'view_name' => 'emails.referral_sender',
            'dynamic_params' => [
                '{{ $actorName }}' => 'Name of the referrer',
                '{{ $otherName }}' => 'Name of the referred person',
            ],
            'icon' => 'bi bi-send',
        ],
        'referral_receiver' => [
            'key' => 'referral_receiver',
            'name' => 'Referral Invitation (Receiver)',
            'description' => 'Invitation sent to a non-user who has been referred to join the platform.',
            'file_path' => 'emails/referral_receiver.blade.php',
            'view_name' => 'emails.referral_receiver',
            'dynamic_params' => [
                '{{ $actorName }}' => 'Name of the referrer',
                '{{ $otherName }}' => 'Name of the invitee',
            ],
            'icon' => 'bi bi-envelope-open',
        ],
        'referral_joined' => [
            'key' => 'referral_joined',
            'name' => 'Referral Joined Notification',
            'description' => 'Sent to the referrer when their referred contact successfully registers on the app.',
            'file_path' => 'emails/referral_joined.blade.php',
            'view_name' => 'emails.referral_joined',
            'dynamic_params' => [
                '{{ $referrerName }}' => 'Name of the referrer',
                '{{ $referredName }}' => 'Name of the registered user',
            ],
            'icon' => 'bi bi-person-plus',
        ],
        'business_deal_sender' => [
            'key' => 'business_deal_sender',
            'name' => 'Business Deal Logged (Sender)',
            'description' => 'Sent to the deal creator confirming the business transaction has been logged.',
            'file_path' => 'emails/business_deal_sender.blade.php',
            'view_name' => 'emails.business_deal_sender',
            'dynamic_params' => [
                '{{ $actorName }}' => 'Deal logger name',
                '{{ $otherName }}' => 'Deal partner name',
            ],
            'icon' => 'bi bi-briefcase',
        ],
        'business_deal_receiver' => [
            'key' => 'business_deal_receiver',
            'name' => 'Business Deal Registered (Receiver)',
            'description' => 'Sent to the deal partner when a business transaction is logged on their behalf.',
            'file_path' => 'emails/business_deal_receiver.blade.php',
            'view_name' => 'emails.business_deal_receiver',
            'dynamic_params' => [
                '{{ $actorName }}' => 'Deal logger name',
                '{{ $otherName }}' => 'Deal partner name',
            ],
            'icon' => 'bi bi-briefcase',
        ],
        'certification_approved' => [
            'key' => 'certification_approved',
            'name' => 'Certification Approved Notification',
            'description' => 'Sent to the user when their entrepreneur or leadership certification is approved.',
            'file_path' => 'emails/certification_approved.blade.php',
            'view_name' => 'emails.certification_approved',
            'dynamic_params' => [
                '{{ $user->name }}' => 'Name of the certified peer',
            ],
            'icon' => 'bi bi-patch-check',
        ],
        'circle_join_congratulations' => [
            'key' => 'circle_join_congratulations',
            'name' => 'Circle Join Congratulations',
            'description' => 'Sent to a user when their request to join a specific Circle has been accepted.',
            'file_path' => 'emails/circle_join_congratulations.blade.php',
            'view_name' => 'emails.circle_join_congratulations',
            'dynamic_params' => [
                '{{ $user->name }}' => 'Name of the user',
            ],
            'icon' => 'bi bi-award',
        ],
        'circle_join_request_status' => [
            'key' => 'circle_join_request_status',
            'name' => 'Circle Join Request Status Update',
            'description' => 'Sent to a user notifying them of status updates regarding their Circle joining application.',
            'file_path' => 'emails/circle_join_request_status.blade.php',
            'view_name' => 'emails.circle_join_request_status',
            'dynamic_params' => [
                '{{ $user->name }}' => 'Name of the user',
            ],
            'icon' => 'bi bi-info-circle',
        ],
        'coin_claim_approved' => [
            'key' => 'coin_claim_approved',
            'name' => 'Coin Claim Approved',
            'description' => 'Sent to the user when their coin claim request is approved by the admin.',
            'file_path' => 'emails/coin_claim_approved.blade.php',
            'view_name' => 'emails.coin_claim_approved',
            'dynamic_params' => [
                '{{ $user->name }}' => 'Name of the user',
            ],
            'icon' => 'bi bi-coin',
        ],
        'coin_claim_rejected' => [
            'key' => 'coin_claim_rejected',
            'name' => 'Coin Claim Rejected',
            'description' => 'Sent to the user when their coin claim request is rejected by the admin.',
            'file_path' => 'emails/coin_claim_rejected.blade.php',
            'view_name' => 'emails.coin_claim_rejected',
            'dynamic_params' => [
                '{{ $user->name }}' => 'Name of the user',
            ],
            'icon' => 'bi bi-x-circle',
        ],
        'coin_claim_submitted' => [
            'key' => 'coin_claim_submitted',
            'name' => 'Coin Claim Submitted',
            'description' => 'Sent to the user when they successfully submit a new coin claim request.',
            'file_path' => 'emails/coin_claim_submitted.blade.php',
            'view_name' => 'emails.coin_claim_submitted',
            'dynamic_params' => [
                '{{ $user->name }}' => 'Name of the user',
            ],
            'icon' => 'bi bi-file-earmark-plus',
        ],
        'feedback_submitted' => [
            'key' => 'feedback_submitted',
            'name' => 'Feedback Submitted Notification',
            'description' => 'Sent to the user acknowledging receipt of their feedback submission.',
            'file_path' => 'emails/feedback_submitted.blade.php',
            'view_name' => 'emails.feedback_submitted',
            'dynamic_params' => [
                '{{ $feedback->name }}' => 'Name of the submitter',
            ],
            'icon' => 'bi bi-chat-right-text',
        ],
        'impact_approved' => [
            'key' => 'impact_approved',
            'name' => 'Impact Approved Notification',
            'description' => 'Sent to a user when their submitted Life Impact activity is approved.',
            'file_path' => 'emails/impact_approved.blade.php',
            'view_name' => 'emails.impact_approved',
            'dynamic_params' => [
                '{{ $user->name }}' => 'Name of the user',
            ],
            'icon' => 'bi bi-heart',
        ],
        'impact_submitted' => [
            'key' => 'impact_submitted',
            'name' => 'Impact Submitted Confirmation',
            'description' => 'Sent to the user confirming that their Life Impact activity has been successfully logged.',
            'file_path' => 'emails/impact_submitted.blade.php',
            'view_name' => 'emails.impact_submitted',
            'dynamic_params' => [
                '{{ $user->name }}' => 'Name of the user',
            ],
            'icon' => 'bi bi-heart',
        ],
        'membership_approved' => [
            'key' => 'membership_approved',
            'name' => 'Membership Approved Confirmation',
            'description' => 'Sent to the user confirming their paid membership status registration is complete.',
            'file_path' => 'emails/membership-approved.blade.php',
            'view_name' => 'emails.membership-approved',
            'dynamic_params' => [
                '{{ $user->name }}' => 'Name of the user',
            ],
            'icon' => 'bi bi-card-checklist',
        ],
        'registration_approved' => [
            'key' => 'registration_approved',
            'name' => 'Registration Request Approved',
            'description' => 'Sent to the user notifying them that their initial registration request was accepted.',
            'file_path' => 'emails/registration_approved.blade.php',
            'view_name' => 'emails.registration_approved',
            'dynamic_params' => [
                '{{ $user->name }}' => 'Name of the registered peer',
            ],
            'icon' => 'bi bi-check-lg',
        ],
        'registration_rejected' => [
            'key' => 'registration_rejected',
            'name' => 'Registration Request Rejected',
            'description' => 'Sent to the user when their registration application is rejected.',
            'file_path' => 'emails/registration_rejected.blade.php',
            'view_name' => 'emails.registration_rejected',
            'dynamic_params' => [
                '{{ $user->name }}' => 'Name of the user',
            ],
            'icon' => 'bi bi-x-lg',
        ],
        'registration_request_received' => [
            'key' => 'registration_request_received',
            'name' => 'Registration Request Received',
            'description' => 'Confirmation sent to the user that their registration application has been received.',
            'file_path' => 'emails/registration_request_received.blade.php',
            'view_name' => 'emails.registration_request_received',
            'dynamic_params' => [
                '{{ $user->name }}' => 'Name of the user',
            ],
            'icon' => 'bi bi-inbox',
        ],
        'requirement_sender' => [
            'key' => 'requirement_sender',
            'name' => 'Requirement Shared Confirmation',
            'description' => 'Sent to a user confirming their business requirement has been successfully logged.',
            'file_path' => 'emails/requirement_sender.blade.php',
            'view_name' => 'emails.requirement_sender',
            'dynamic_params' => [
                '{{ $actorName }}' => 'Name of the sharing peer',
            ],
            'icon' => 'bi bi-list-task',
        ],
        'story_approved' => [
            'key' => 'story_approved',
            'name' => 'Story Submission Approved',
            'description' => 'Sent to a user when their submitted story or article is approved by admins.',
            'file_path' => 'emails/story_approved.blade.php',
            'view_name' => 'emails.story_approved',
            'dynamic_params' => [],
            'icon' => 'bi bi-journal-check',
        ],
        'story_rejected' => [
            'key' => 'story_rejected',
            'name' => 'Story Submission Rejected',
            'description' => 'Sent to a user when their story submission is rejected by admins.',
            'file_path' => 'emails/story_rejected.blade.php',
            'view_name' => 'emails.story_rejected',
            'dynamic_params' => [],
            'icon' => 'bi bi-journal-x',
        ],
        'story_submitted' => [
            'key' => 'story_submitted',
            'name' => 'Story Submission Received',
            'description' => 'Sent to a user confirming that their story has been successfully received for admin review.',
            'file_path' => 'emails/story_submitted.blade.php',
            'view_name' => 'emails.story_submitted',
            'dynamic_params' => [],
            'icon' => 'bi bi-journal-plus',
        ],
        'support_feedback_thank_you' => [
            'key' => 'support_feedback_thank_you',
            'name' => 'Support Feedback Thank You',
            'description' => 'Sent to users thanking them for submitting feedback on a support ticket.',
            'file_path' => 'emails/support-feedback-thank-you.blade.php',
            'view_name' => 'emails.support-feedback-thank-you',
            'dynamic_params' => [],
            'icon' => 'bi bi-hand-thumbs-up',
        ],
        'testimonial_sender' => [
            'key' => 'testimonial_sender',
            'name' => 'Testimonial Logged (Sender)',
            'description' => 'Sent to the user who logged a testimonial confirming registration.',
            'file_path' => 'emails/testimonial_sender.blade.php',
            'view_name' => 'emails.testimonial_sender',
            'dynamic_params' => [
                '{{ $actorName }}' => 'Name of the testimonial creator',
                '{{ $otherName }}' => 'Name of the recipient partner',
            ],
            'icon' => 'bi bi-chat-quote',
        ],
        'testimonial_receiver' => [
            'key' => 'testimonial_receiver',
            'name' => 'Testimonial Received (Receiver)',
            'description' => 'Sent to the user who is the recipient/partner of the logged testimonial.',
            'file_path' => 'emails/testimonial_receiver.blade.php',
            'view_name' => 'emails.testimonial_receiver',
            'dynamic_params' => [
                '{{ $actorName }}' => 'Name of the creator',
                '{{ $otherName }}' => 'Name of the recipient',
            ],
            'icon' => 'bi bi-chat-quote',
        ],
        'website_form_confirmation' => [
            'key' => 'website_form_confirmation',
            'name' => 'Website Form Confirmation',
            'description' => 'Sent to visitors confirming receipt of their message via website contact forms.',
            'file_path' => 'emails/website_form_confirmation.blade.php',
            'view_name' => 'emails.website_form_confirmation',
            'dynamic_params' => [],
            'icon' => 'bi bi-window',
        ],
    ];

    /**
     * Display a listing of all available templates.
     */
    public function index(): View
    {
        $templates = [];
        $catalog = self::$catalog;

        uasort($catalog, static fn (array $first, array $second): int => strcasecmp($first['name'], $second['name']));

        foreach ($catalog as $key => $tpl) {
            $dbTemplate = EmailTemplate::where('template_key', $key)->first();

            $filePath = resource_path('views/'.$tpl['file_path']);
            $exists = File::exists($filePath);
            $lastModified = $exists ? date('Y-m-d H:i:s', File::lastModified($filePath)) : 'N/A';

            $templates[] = array_merge($tpl, [
                'db_record' => $dbTemplate,
                'file_exists' => $exists,
                'last_modified' => $lastModified,
            ]);
        }

        return view('admin.email_templates.index', compact('templates'));
    }

    /**
     * Show the form for editing the specified template.
     */
    public function edit(string $key): View|RedirectResponse
    {
        if (! isset(self::$catalog[$key])) {
            return redirect()->route('admin.email-templates.index')->with('error', 'Template not found in catalog.');
        }

        $template = self::$catalog[$key];
        $filePath = resource_path('views/'.$template['file_path']);

        if (! File::exists($filePath)) {
            return redirect()->route('admin.email-templates.index')->with('error', 'Template file does not exist on disk.');
        }

        $fullHtml = File::get($filePath);

        // Find all editable blocks
        preg_match_all('/<!-- EDITABLE_START -->(.*?)<!-- EDITABLE_END -->/s', $fullHtml, $matches);
        $editableBlocks = [];
        if (! empty($matches[1])) {
            foreach ($matches[1] as $match) {
                $editableBlocks[] = strip_tags(str_replace(['<br />', '<br>', '<br/>'], "\n", trim($match)));
            }
        } else {
            $editableBlocks[] = strip_tags(str_replace(['<br />', '<br>', '<br/>'], "\n", $fullHtml));
        }

        $dbTemplate = EmailTemplate::firstOrCreate([
            'template_key' => $key,
        ], [
            'name' => $template['name'],
            'file_path' => $template['file_path'],
            'dynamic_params' => $template['dynamic_params'],
        ]);

        return view('admin.email_templates.edit', compact('template', 'dbTemplate', 'fullHtml', 'editableBlocks'));
    }

    /**
     * Update the specified template in storage and write back to file.
     */
    public function update(Request $request, string $key): RedirectResponse
    {
        if (! isset(self::$catalog[$key])) {
            return redirect()->route('admin.email-templates.index')->with('error', 'Template not found.');
        }

        $template = self::$catalog[$key];
        $filePath = resource_path('views/'.$template['file_path']);

        if (! File::exists($filePath)) {
            return redirect()->route('admin.email-templates.index')->with('error', 'Template file not found on disk.');
        }

        $request->validate([
            'mode' => 'required|in:simple,html',
            'subject' => 'nullable|string|max:255',
        ]);

        $fullHtml = File::get($filePath);

        if ($request->input('mode') === 'simple') {
            $request->validate([
                'simple_content' => 'required|array',
            ]);

            $newSimpleContent = $request->input('simple_content');
            $blockIndex = 0;

            $updatedHtml = preg_replace_callback(
                '/<!-- EDITABLE_START -->.*?<!-- EDITABLE_END -->/s',
                function ($matches) use ($newSimpleContent, &$blockIndex) {
                    $text = $newSimpleContent[$blockIndex] ?? '';
                    $blockIndex++;

                    $paragraphs = preg_split('/\n/', str_replace("\r", '', $text));
                    $formattedHtml = '';
                    foreach ($paragraphs as $para) {
                        $trimmed = trim($para);
                        if ($trimmed !== '') {
                            if (str_starts_with($trimmed, '•') || str_starts_with($trimmed, '-')) {
                                $bulletText = ltrim($trimmed, '•- ');
                                $formattedHtml .= '<p style="margin: 0 0 8px 20px; font-size: 15px; line-height: 22px; color: #d9d9d9;">• '.$bulletText.'</p>'."\n";
                            } else {
                                $formattedHtml .= '<p style="margin: 0 0 16px 0; font-size: 15px; line-height: 22px; color: #d9d9d9;">'.$para.'</p>'."\n";
                            }
                        }
                    }

                    return "<!-- EDITABLE_START -->\n".trim($formattedHtml)."\n<!-- EDITABLE_END -->";
                },
                $fullHtml
            );
        } else {
            $request->validate([
                'html_content' => 'required|string',
            ]);
            $updatedHtml = $request->input('html_content');
        }

        try {
            // Write to file
            File::put($filePath, $updatedHtml);

            // Update database record
            $dbTemplate = EmailTemplate::updateOrCreate([
                'template_key' => $key,
            ], [
                'name' => $template['name'],
                'file_path' => $template['file_path'],
                'subject' => $request->input('subject'),
                'custom_html' => $updatedHtml,
                'dynamic_params' => $template['dynamic_params'],
            ]);

            // Clear View Cache immediately
            Artisan::call('view:clear');

            Log::info('email_template.updated_successfully', [
                'key' => $key,
                'mode' => $request->input('mode'),
                'updated_by' => auth()->id(),
            ]);

            return redirect()->route('admin.email-templates.edit', $key)->with('success', 'Template updated successfully.');
        } catch (\Throwable $throwable) {
            Log::error('email_template.update_failed', [
                'key' => $key,
                'error' => $throwable->getMessage(),
            ]);

            return back()->with('error', 'Failed to save template: '.$throwable->getMessage());
        }
    }

    /**
     * Preview the template inside an iframe with dummy data.
     */
    public function preview(string $key)
    {
        if (! isset(self::$catalog[$key])) {
            abort(404, 'Template not found.');
        }

        $template = self::$catalog[$key];
        $dummyData = $this->getDummyDataForTemplate($key);

        try {
            // Render the blade template with dummy data
            return view($template['view_name'], $dummyData)->render();
        } catch (\Throwable $e) {
            return response('Preview rendering failed: '.$e->getMessage(), 500);
        }
    }

    /**
     * Extract the text content wrapped between EDITABLE markers.
     */
    private function getEditableContent(string $html): string
    {
        if (preg_match('/<!-- EDITABLE_START -->(.*?)<!-- EDITABLE_END -->/s', $html, $matches)) {
            return trim($matches[1]);
        }

        return $html;
    }

    /**
     * Replace content inside EDITABLE markers or return whole HTML.
     */
    private function setEditableContent(string $fullHtml, string $newContent): string
    {
        if (preg_match('/<!-- EDITABLE_START -->.*?<!-- EDITABLE_END -->/s', $fullHtml)) {
            return preg_replace('/<!-- EDITABLE_START -->.*?<!-- EDITABLE_END -->/s', "<!-- EDITABLE_START -->\n".$newContent."\n<!-- EDITABLE_END -->", $fullHtml);
        }

        return $newContent;
    }

    /**
     * Generate realistic mock/dummy data for template previews.
     */
    private function getDummyDataForTemplate(string $key): array
    {
        $user = new class
        {
            public string $name = 'John Doe';

            public string $first_name = 'John';

            public string $last_name = 'Doe';

            public string $display_name = 'John Doe';

            public string $email = 'john.doe@example.com';

            public string $phone = '+1234567890';

            public int $life_impacted_count = 12;
        };

        $ticket = new class
        {
            public string $contact_name = 'John Doe';

            public string $ticket_number = 'SUP-20260810-0001';

            public string $subject = 'Cannot Load Wallet Balance';

            public string $description = 'I purchased 100 coins but my wallet balance is still showing 0. Please help.';

            public string $media_url = 'https://peersunity.com/images/screenshot.png';

            public string $admin_note = 'The transaction record was synced and wallet balance updated successfully.';
        };

        $feedback = new class
        {
            public string $name = 'John Doe';

            public string $subject = 'General Feedback';

            public string $category = 'Mobile App Experience';

            public string $question = 'The new dark mode on the mobile dashboard looks absolutely stunning!';
        };

        $story = new class
        {
            public string $title = 'How Collaboration Built My Business';

            public string $story_link = 'https://vyaparjagat.com/stories/collaboration-success';

            public string $rejected_reason = 'Story content is too short. Please add more details.';

            public $user;

            public function __construct()
            {
                $this->user = new class
                {
                    public string $display_name = 'John Doe';

                    public string $first_name = 'John';

                    public string $last_name = 'Doe';
                };
            }
        };

        $claim = new class
        {
            public string $activity_code = 'ACT-COIN-CLAIM-01';

            public int $coins_awarded = 150;

            public string $admin_notes = 'Approved for successful referral registration verification.';

            public $user;

            public function __construct()
            {
                $this->user = new class
                {
                    public string $display_name = 'John Doe';

                    public string $first_name = 'John';
                };
            }
        };

        $meetingRequest = new class
        {
            public $scheduled_at;

            public string $place = 'Conference Room 4 / Zoom Link';

            public function __construct()
            {
                $this->scheduled_at = now()->addDays(2);
            }
        };

        $rescheduleRequest = new class
        {
            public $old_scheduled_at;

            public $new_scheduled_at;

            public string $old_place = 'Coffee Shop';

            public string $new_place = 'Main HQ Conference Room';

            public string $reason = 'Schedule conflict with client meeting';

            public function __construct()
            {
                $this->old_scheduled_at = now()->addDays(2);
                $this->new_scheduled_at = now()->addDays(3);
            }
        };

        $circleJoinRequest = new class
        {
            public $user;

            public $circle;

            public function __construct()
            {
                $this->user = new class
                {
                    public string $display_name = 'John Doe';

                    public string $first_name = 'John';

                    public string $last_name = 'Doe';
                };
                $this->circle = new class
                {
                    public string $name = 'Fintech Entrepreneurs Circle';
                };
            }
        };

        $impact = new class
        {
            public string $action = 'Donated 50 laptops to rural schools';

            public $impact_date;

            public string $story_to_share = 'We visited the school and personally distributed the laptops to students.';

            public string $status = 'approved';

            public int $life_impacted = 50;

            public $user;

            public function __construct()
            {
                $this->impact_date = now()->subDays(1);
                $this->user = new class
                {
                    public string $display_name = 'John Doe';

                    public string $first_name = 'John';

                    public string $last_name = 'Doe';
                };
            }
        };

        switch ($key) {
            case 'welcome_peer':
            case 'login_otp':
                return [
                    'user' => $user,
                    'otp' => '472019',
                ];

            case 'admin_login_otp':
                return [
                    'name' => 'Administrator',
                    'otp' => '583920',
                ];

            case 'password_reset_otp':
                return [
                    'name' => 'John Doe',
                    'user' => $user,
                    'otp' => '940381',
                ];

            case 'support_ticket_response':
                return [
                    'ticket' => $ticket,
                    'responseMessage' => "Hello!\n\nWe have looked into your wallet issue. The balance database has been synced, and you should see the updated coins now.\n\nPlease log out and log back in to verify.",
                ];

            case 'support_ticket_resolved':
            case 'support_ticket_submitted':
                return [
                    'ticket' => $ticket,
                ];

            case 'p2p_sender':
            case 'p2p_receiver':
                return [
                    'actorName' => 'John Doe',
                    'otherName' => 'Jane Smith',
                    'meetingDate' => '2026-08-15 14:30',
                    'meetingPlace' => 'HQ Meeting Room A / Zoom',
                ];

            case 'p2p_meeting_workflow':
                return [
                    'actor' => $user,
                    'recipient' => $user,
                    'actorName' => 'John Doe',
                    'otherName' => 'Jane Smith',
                    'meetingRequest' => $meetingRequest,
                    'rescheduleRequest' => $rescheduleRequest,
                    'eventType' => 'p2p_reschedule_requested',
                    'responseReason' => 'Rescheduled to accommodate guest speaker schedule.',
                ];

            case 'referral_sender':
            case 'referral_receiver':
                return [
                    'actorName' => 'John Doe',
                    'otherName' => 'Jane Smith',
                    'referralOf' => 'Business Partner Prospect',
                ];

            case 'referral_joined':
                return [
                    'referrerName' => 'John Doe',
                    'peerName' => 'Jane Smith',
                    'referralCode' => 'PEER-JANE-99',
                ];

            case 'business_deal_sender':
            case 'business_deal_receiver':
                return [
                    'actorName' => 'John Doe',
                    'otherName' => 'Jane Smith',
                    'dealAmountInr' => '5,00,000',
                ];

            case 'membership_approved':
                return [
                    'user' => $user,
                    'userName' => 'John Doe',
                    'membershipStartsAt' => '2026-08-10',
                    'membershipEndsAt' => '2027-08-10',
                    'logoUrl' => 'https://peersunity.com/images/peersglobal-logo.png',
                ];

            case 'certification_approved':
                $submission = new class
                {
                    public string $certification_type = 'entrepreneur';

                    public string $full_name = 'John Doe';

                    public string $business_name = 'Acme Corp';

                    public string $certification_level = 'Gold';

                    public int $total_score = 95;

                    public int $percentage = 95;

                    public string $certificate_number = 'CERT-2026-001';

                    public $issued_at = null;

                    public string $admin_note = 'Great achievement!';

                    public string $certificate_download_url = '#';
                };

                return [
                    'submission' => $submission,
                    'logoUrl' => 'https://peersunity.com/images/peersglobal-logo.png',
                ];

            case 'circle_join_congratulations':
                return [
                    'displayName' => 'John Doe',
                    'circleName' => 'Fintech Circle',
                    'categoryName' => 'Gold Category',
                    'joinRequestId' => 'REQ-CIRCLE-1002',
                    'formattedAmount' => '₹15,000',
                    'paymentUrl' => 'https://peersunity.com/payments/circle/1002',
                ];

            case 'circle_join_request_status':
                return [
                    'circleJoinRequest' => $circleJoinRequest,
                    'body' => 'Your request to join the Circle has been reviewed.',
                    'statusLabel' => 'Approved',
                    'rejectionReason' => null,
                ];

            case 'coin_claim_approved':
            case 'coin_claim_rejected':
            case 'coin_claim_submitted':
                return [
                    'claim' => $claim,
                ];

            case 'feedback_submitted':
            case 'support_feedback_thank_you':
                return [
                    'user' => $user,
                    'feedbackForm' => $feedback,
                    'feedback' => $feedback,
                ];

            case 'impact_approved':
                return [
                    'submitter' => $user,
                    'impact' => $impact,
                ];

            case 'impact_submitted':
                return [
                    'impact' => $impact,
                ];

            case 'registration_approved':
            case 'registration_rejected':
            case 'registration_request_received':
                return [
                    'user' => $user,
                ];

            case 'requirement_sender':
                return [
                    'actorName' => 'John Doe',
                    'requirementSubject' => 'IT Software Development Services',
                ];

            case 'story_approved':
            case 'story_rejected':
            case 'story_submitted':
                return [
                    'story' => $story,
                ];

            case 'testimonial_sender':
                return [
                    'actorName' => 'John Doe',
                ];

            case 'testimonial_receiver':
                return [
                    'actorName' => 'John Doe',
                    'otherName' => 'Jane Smith',
                    'testimonialContent' => 'John is an incredibly supportive peer and a great collaborative partner!',
                ];

            case 'website_form_confirmation':
                return [
                    'recipientName' => 'John Doe',
                    'formTitle' => 'Contact Us Inquiry',
                    'confirmationMessage' => 'We received your inquiry regarding business partnership options.',
                ];

            default:
                return [];
        }
    }
}
