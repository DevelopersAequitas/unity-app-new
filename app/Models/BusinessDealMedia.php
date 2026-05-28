<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessDealMedia extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'business_deal_media';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'business_deal_id',
        'file_id',
        'media_path',
        'media_url',
        'media_type',
        'mime_type',
        'original_name',
        'size_bytes',
    ];

    public function businessDeal(): BelongsTo
    {
        return $this->belongsTo(BusinessDeal::class, 'business_deal_id');
    }
}
