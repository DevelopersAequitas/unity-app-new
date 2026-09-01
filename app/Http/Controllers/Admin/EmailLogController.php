<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmailLogController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'template_key' => ['nullable', 'string', 'max:255'],
            'source_module' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:all,sent,failed,pending,queued'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50,100'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $templateKey = trim((string) ($validated['template_key'] ?? ''));
        $sourceModule = trim((string) ($validated['source_module'] ?? ''));
        $status = (string) ($validated['status'] ?? 'all');
        $dateFrom = (string) ($validated['date_from'] ?? '');
        $dateTo = (string) ($validated['date_to'] ?? '');
        $perPage = (int) ($validated['per_page'] ?? 20);

        $likeOp = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $emailLogs = EmailLog::query()
            ->when($search !== '', function ($builder) use ($search, $likeOp) {
                $likeQuery = '%'.$search.'%';

                $builder->where(function ($inner) use ($likeQuery, $likeOp) {
                    $inner->where('to_email', $likeOp, $likeQuery)
                        ->orWhere('to_name', $likeOp, $likeQuery)
                        ->orWhere('subject', $likeOp, $likeQuery)
                        ->orWhere('template_key', $likeOp, $likeQuery)
                        ->orWhere('source_module', $likeOp, $likeQuery);
                });
            })
            ->when($templateKey !== '', fn ($builder) => $builder->where('template_key', $templateKey))
            ->when($sourceModule !== '', fn ($builder) => $builder->where('source_module', $sourceModule))
            ->when($status !== '' && $status !== 'all', fn ($builder) => $builder->where('status', $status))
            ->when($dateFrom !== '', fn ($builder) => $builder->whereDate('sent_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($builder) => $builder->whereDate('sent_at', '<=', $dateTo))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->appends($request->query());

        $sourceModules = EmailLog::query()
            ->whereNotNull('source_module')
            ->where('source_module', '!=', '')
            ->distinct()
            ->orderBy('source_module')
            ->pluck('source_module');

        $templateKeys = EmailLog::query()
            ->whereNotNull('template_key')
            ->where('template_key', '!=', '')
            ->distinct()
            ->orderBy('template_key')
            ->pluck('template_key');

        return view('admin.email_logs.index', [
            'emailLogs' => $emailLogs,
            'templateKeys' => $templateKeys,
            'sourceModules' => $sourceModules,
            'filters' => [
                'search' => $search,
                'template_key' => $templateKey,
                'source_module' => $sourceModule,
                'status' => in_array($status, ['all', 'sent', 'failed', 'pending', 'queued'], true) ? $status : 'all',
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(EmailLog $emailLog): View
    {
        $bodyHtml = $emailLog->body_html;
        $bodyText = $emailLog->body_text;

        $jsonData = null;
        if ($this->isJson($bodyHtml)) {
            $jsonData = json_decode($bodyHtml, true);
        } elseif ($this->isJson($bodyText)) {
            $jsonData = json_decode($bodyText, true);
        }

        if ($jsonData) {
            $bodyHtml = $this->renderTemplate($jsonData, $emailLog);
        } elseif (empty($bodyHtml) && ! empty($bodyText)) {
            $bodyHtml = $this->renderTemplate(['message' => $bodyText], $emailLog);
        }

        return view('admin.email_logs.show', [
            'emailLog' => $emailLog,
            'bodyHtml' => $bodyHtml,
        ]);
    }

    private function isJson(?string $string): bool
    {
        if (empty($string)) {
            return false;
        }
        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }

    private function renderTemplate(array $data, EmailLog $emailLog): string
    {
        $title = $data['title'] ?? $data['subject'] ?? $emailLog->subject ?? 'Notification';
        $recipientName = $emailLog->to_name ?: 'User';

        $greeting = $data['greeting'] ?? '';
        if (empty($greeting)) {
            $greeting = 'Dear '.$recipientName.',';
        }

        $messageContent = $data['message'] ?? $data['body'] ?? $data['content'] ?? $data['text'] ?? '';

        if (is_array($messageContent)) {
            $messageContent = implode('<br><br>', array_map('htmlspecialchars', $messageContent));
        } else {
            if ($messageContent === strip_tags($messageContent)) {
                $messageContent = nl2br(htmlspecialchars($messageContent));
            }
        }

        $actionButtonHtml = '';
        $actionUrl = $data['action_url'] ?? $data['url'] ?? $data['link'] ?? '';
        if (! empty($actionUrl)) {
            $actionText = $data['action_text'] ?? $data['button_text'] ?? 'Click Here';
            $actionButtonHtml = '
                <div style="margin: 24px 0; text-align: center;">
                    <a href="'.htmlspecialchars($actionUrl).'" style="background-color: #1d4ed8; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 700; display: inline-block;">
                        '.htmlspecialchars($actionText).'
                    </a>
                </div>';
        }

        $additionalInfoHtml = '';
        $excludedKeys = ['title', 'subject', 'greeting', 'message', 'body', 'content', 'text', 'action_url', 'url', 'link', 'action_text', 'button_text', 'footer'];
        $details = array_diff_key($data, array_flip($excludedKeys));
        if (! empty($details)) {
            $additionalInfoHtml .= '<div style="margin-top: 20px; padding: 15px; background-color: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">';
            $additionalInfoHtml .= '<h4 style="margin: 0 0 10px 0; color: #0f172a; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Details</h4>';
            $additionalInfoHtml .= '<table style="width: 100%; border-collapse: collapse; font-size: 14px; color: #334155;">';
            foreach ($details as $key => $val) {
                if (is_array($val)) {
                    $val = json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                }
                $formattedKey = ucwords(str_replace('_', ' ', $key));
                $additionalInfoHtml .= '<tr>';
                $additionalInfoHtml .= '<td style="padding: 6px 0; font-weight: 600; width: 35%; vertical-align: top;">'.htmlspecialchars($formattedKey).':</td>';
                $additionalInfoHtml .= '<td style="padding: 6px 0; vertical-align: top;">'.nl2br(htmlspecialchars((string) $val)).'</td>';
                $additionalInfoHtml .= '</tr>';
            }
            $additionalInfoHtml .= '</table></div>';
        }

        $footerText = $data['footer'] ?? 'This email was sent to notify you about your account activity on Peers Global.';

        return '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>'.htmlspecialchars($title).'</title>
</head>
<body style="margin:0; padding:0; background-color:#f5f7fb; font-family:\'Helvetica Neue\', Arial, sans-serif;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f5f7fb; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" cellpadding="0" cellspacing="0" width="600" style="max-width:600px; width:100%; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e6e9f0; box-shadow:0 10px 30px rgba(17,24,39,0.06);">
                    <tr>
                        <td style="background:linear-gradient(90deg,#1d4ed8,#0ea5e9); padding:22px 28px; color:#ffffff; font-size:20px; font-weight:700; letter-spacing:0.2px;">
                            Peers Global Unity
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <p style="margin:0 0 12px; color:#0f172a; font-size:18px; font-weight:700;">'.htmlspecialchars($greeting).'</p>
                            <p style="margin:0 0 16px; color:#334155; font-size:15px; line-height:22px;">
                                '.$messageContent.'
                            </p>
                            '.$actionButtonHtml.'
                            '.$additionalInfoHtml.'
                            <p style="margin:20px 0 0; color:#94a3b8; font-size:13px; line-height:20px;">
                                Warm regards,<br>
                                Peers Global Unity
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f8fafc; padding:18px 28px; color:#94a3b8; font-size:12px; line-height:18px; border-top:1px solid #e2e8f0;">
                            '.htmlspecialchars($footerText).'
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }
}
