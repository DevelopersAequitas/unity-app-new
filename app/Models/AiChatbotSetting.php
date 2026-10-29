<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AiChatbotSetting extends Model
{
    use HasFactory;

    public const DEFAULT_MASTER_PROMPT = <<<'PROMPT'
You are PG Genie, the official B2B networking assistant for Peers Global Unity.
Your tone is calm, professional, trustworthy, concise, and helpful.

RULES:
1. Always rely on `search_knowledge_docs` for official Peers Global rules, policies, Coins/Lives Impacted guidelines, Unity Circles, and Speed Networking procedures. Never invent policy details.
2. When a peer asks to find peers, events, or speed networking sessions, call the respective database tool (`search_peers`, `get_upcoming_events`, `get_active_speed_networking`). For events, prioritize the peer's city; if no events exist in their city, mention that and suggest upcoming events from nearby cities (such as Ahmedabad).
3. Always use the word "peer" or "peers" instead of "member" or "members".
4. Keep answers clear and structured with short bullet points where helpful.
5. Always return your final output in the required JSON schema containing:
   - `reply_markdown`: Your helpful response.
   - `attachments`: Any relevant deep-link action buttons or peer cards.
   - `suggested_follow_ups`: 2 to 3 short, natural follow-up questions the peer can tap next.
PROMPT;

    /**
     * @var array<int, array<string, string|null>>
     */
    public const DEFAULT_INNER_OPTIONS = [
        [
            'id' => 'find_peers',
            'title' => 'Find Peers',
            'subtitle' => 'Search peers by business category or city',
            'prompt' => 'Help me find peers in my city or business category',
            'icon_name' => 'users',
            'route' => null,
        ],
        [
            'id' => 'speed_networking',
            'title' => 'Speed Networking Guide',
            'subtitle' => 'Check active sessions, rules & rewards',
            'prompt' => 'How does Speed Networking work and when is the next session?',
            'icon_name' => 'zap',
            'route' => null,
        ],
        [
            'id' => 'referrals_impact',
            'title' => 'Referrals & Coins',
            'subtitle' => 'Learn how to earn coins and Lives Impacted',
            'prompt' => 'How do I earn Coins and increase my Lives Impacted score?',
            'icon_name' => 'award',
            'route' => null,
        ],
        [
            'id' => 'circles_events',
            'title' => 'Circles & Upcoming Events',
            'subtitle' => 'Explore Unity Circles and scheduled meetings',
            'prompt' => 'Show me upcoming events and how Unity Circles work',
            'icon_name' => 'calendar',
            'route' => null,
        ],
    ];

    /**
     * @var array<int, string>
     */
    public const DEFAULT_STARTER_CHIPS = [
        'What is Peers Global?',
        'How to earn Coins?',
        'Upcoming events near me',
        'How to schedule a P2P meeting?',
    ];

    /**
     * @var array<int, string>
     */
    public const DEFAULT_ENABLED_TOOLS = [
        'search_docs',
        'find_peers',
        'get_events',
        'get_speed_networking',
        'get_my_stats',
    ];

    protected $table = 'ai_chatbot_settings';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'is_enabled',
        'show_floating_button',
        'show_app_bar_icon',
        'show_home_banner',
        'button_title',
        'button_subtitle',
        'bot_name',
        'bot_subtitle',
        'bot_avatar_url',
        'welcome_title',
        'welcome_subtitle',
        'empty_history_title',
        'empty_history_subtitle',
        'input_placeholder',
        'thinking_text',
        'model_name',
        'temperature',
        'master_system_prompt',
        'inner_options_json',
        'starter_chips_json',
        'enabled_tools_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'show_floating_button' => 'boolean',
            'show_app_bar_icon' => 'boolean',
            'show_home_banner' => 'boolean',
            'temperature' => 'float',
            'inner_options_json' => 'array',
            'starter_chips_json' => 'array',
            'enabled_tools_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}

