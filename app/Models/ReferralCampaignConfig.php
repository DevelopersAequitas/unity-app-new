<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReferralCampaignConfig extends Model
{
    use HasFactory;

    protected $table = 'referral_campaign_configs';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'show_floating_badge',
        'auto_open_badge_screen',
        'floating_badge_delay_seconds',
        'auto_open_delay_seconds',
        'reward_title',
        'reward_subtitle',
        'reward_image_url',
        'show_send_invite_button',
        'is_active',
    ];

    protected $casts = [
        'show_floating_badge' => 'boolean',
        'auto_open_badge_screen' => 'boolean',
        'floating_badge_delay_seconds' => 'integer',
        'auto_open_delay_seconds' => 'integer',
        'reward_title' => 'string',
        'reward_subtitle' => 'string',
        'reward_image_url' => 'string',
        'show_send_invite_button' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
