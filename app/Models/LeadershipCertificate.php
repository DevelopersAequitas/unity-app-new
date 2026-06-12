<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadershipCertificate extends Model
{
    use HasUuids;

    protected $table = 'leadership_certificates';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'leadership_certification_submission_id',
        'full_name',
        'business_name',
        'email',
        'contact_no',
        'certification_level',
        'total_score',
        'percentage',
        'status',
        'certificate_number',
        'certificate_pdf_path',
        'issued_at',
    ];

    protected $casts = [
        'total_score' => 'integer',
        'percentage' => 'float',
        'issued_at' => 'datetime',
    ];

    public function leadershipCertificationSubmission(): BelongsTo
    {
        return $this->belongsTo(LeadershipCertificationSubmission::class, 'leadership_certification_submission_id');
    }
}
