<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BirthdayCreativeConfig extends Model
{
    use HasFactory;

    protected $table = 'birthday_creative_configs';

    protected $fillable = [
        'is_enabled',
        'template_file_id',
        'background_gradient_start',
        'background_gradient_end',
        'text_color',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function templateFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'template_file_id');
    }
}
