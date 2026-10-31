<?php

declare(strict_types=1);

namespace App\Modules\DynamicAccess\Models;

use App\Models\Circle;
use App\Models\District;
use App\Models\Industry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class UserOrganizationalAssignment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'user_organizational_assignments';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'role_id',
        'scope_type',
        'circle_id',
        'district_id',
        'industry_id',
        'country_code',
        'committee_key',
        'title_override',
        'is_primary',
        'status',
        'effective_from',
        'effective_to',
        'assigned_by',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class, 'industry_id');
    }
}

