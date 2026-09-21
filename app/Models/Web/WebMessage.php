<?php

declare(strict_types=1);

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WebMessage extends Model
{
    use HasFactory;

    protected $table = 'web_messages';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'email',
        'phone',
        'company',
        'inquiry_type',
        'subject',
        'message',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebMessage $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
