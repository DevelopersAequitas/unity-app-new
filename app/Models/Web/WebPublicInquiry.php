<?php

declare(strict_types=1);

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WebPublicInquiry extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'web_public_inquiries';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'inquiry_number',
        'source_type',
        'name',
        'email',
        'phone',
        'city',
        'organisation',
        'subject',
        'message',
        'form_payload',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'form_payload' => 'array',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebPublicInquiry $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->inquiry_number)) {
                $prefix = match ($model->source_type) {
                    'city_request' => 'CTY',
                    'speaker' => 'SPK',
                    'investor' => 'INV',
                    default => 'INQ',
                };
                $model->inquiry_number = $prefix.'-'.date('Y').'-'.strtoupper(Str::random(6));
            }
        });
    }
}
