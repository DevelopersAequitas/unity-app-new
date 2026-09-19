<?php

declare(strict_types=1);

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WebMedia extends Model
{
    use HasFactory;

    protected $table = 'web_media';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'title',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'category',
        'alt_text',
        'dimensions',
        'metadata',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebMedia $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function getUrlAttribute(): string
    {
        if (Str::startsWith($this->file_path, ['http://', 'https://', '/'])) {
            return $this->file_path;
        }

        return asset('uploads/web-media/'.$this->file_path);
    }
}
