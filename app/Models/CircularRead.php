<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CircularRead extends Model
{
    use HasFactory;

    protected $table = 'circular_reads';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'circular_id',
        'user_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $read): void {
            if (empty($read->id)) {
                $read->id = Str::uuid()->toString();
            }
        });
    }

    public function circular(): BelongsTo
    {
        return $this->belongsTo(Circular::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
