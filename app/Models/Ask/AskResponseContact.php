<?php

declare(strict_types=1);

namespace App\Models\Ask;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AskResponseContact extends Model
{
    use HasFactory;

    protected $table = 'ask_response_contacts';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'response_id',
        'full_name',
        'company_name',
        'designation',
        'email',
        'phone',
        'alternate_phone',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(AskResponse::class, 'response_id');
    }
}
