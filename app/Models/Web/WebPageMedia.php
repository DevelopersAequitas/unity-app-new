<?php

declare(strict_types=1);

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WebPageMedia extends Model
{
    use HasFactory;

    protected $table = 'web_page_medias';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebPageMedia $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function getSectionKeyAttribute(?string $value): string
    {
        return $value ?: ($this->attributes['section_name'] ?? ($this->attributes['slot_key'] ?? 'Main Section'));
    }

    public function getMediaSourceAttribute(?string $value): string
    {
        return $value ?: 'localhost';
    }

    public function getPageTitleAttribute(?string $value): string
    {
        return $value ?: ucfirst($this->attributes['page_id'] ?? 'Home');
    }
}
