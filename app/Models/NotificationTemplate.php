<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NotificationTemplate extends Model
{
    protected $table = 'notification_templates';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'template_key',
        'name',
        'title_template',
        'body_template',
        'default_payload',
        'dynamic_params',
    ];

    protected $casts = [
        'default_payload' => 'array',
        'dynamic_params' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function (NotificationTemplate $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
