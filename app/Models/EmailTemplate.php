<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasUuids;

    protected $table = 'email_templates';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'template_key',
        'name',
        'file_path',
        'subject',
        'dynamic_params',
        'custom_html',
    ];

    protected $casts = [
        'dynamic_params' => 'array',
    ];
}
