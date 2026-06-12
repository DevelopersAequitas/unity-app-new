<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CertificationSubmission extends Model
{
    use HasUuids;

    public const TYPE_LEADERSHIP = 'leadership';
    public const TYPE_ENTREPRENEUR = 'entrepreneur';

    public const STATUS_NEW = 'new';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $table = 'certification_submissions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'certification_type',
        'user_id',
        'full_name',
        'business_name',
        'email',
        'contact_no',
        'total_score',
        'percentage',
        'certification_level',
        'certification_title',
        'answers',
        'status',
        'admin_note',
        'approved_by',
        'rejected_by',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'answers' => 'array',
        'total_score' => 'integer',
        'percentage' => 'integer',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];
}
