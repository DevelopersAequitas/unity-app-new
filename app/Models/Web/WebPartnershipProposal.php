<?php

declare(strict_types=1);

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WebPartnershipProposal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'web_partnership_proposals';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'proposal_number',
        'name',
        'organisation',
        'role',
        'email',
        'phone',
        'relationship_type',
        'why_peers_global',
        'what_to_build',
        'who_benefits',
        'what_to_contribute',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
        'metadata',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebPartnershipProposal $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->proposal_number)) {
                $model->proposal_number = 'PTS-'.date('Y').'-'.strtoupper(Str::random(6));
            }
        });
    }
}
