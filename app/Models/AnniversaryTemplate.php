<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnniversaryTemplate extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'anniversary_templates';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'image_path',
        'message',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
