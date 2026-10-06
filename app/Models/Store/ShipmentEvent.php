<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentEvent extends Model
{
    use HasUuids;

    protected $table = 'shipment_events';

    public $timestamps = false;

    protected $fillable = [
        'shipment_id',
        'provider_event_id',
        'status',
        'location',
        'description',
        'event_at',
        'created_at',
    ];

    protected $casts = [
        'event_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }
}
