<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnPhoto extends Model
{
    use HasUuids;

    protected $table = 'return_photos';

    public $timestamps = false;

    protected $fillable = [
        'return_id',
        'file_url',
        'file_type',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function returnModel(): BelongsTo
    {
        return $this->belongsTo(StoreReturn::class, 'return_id');
    }
}
