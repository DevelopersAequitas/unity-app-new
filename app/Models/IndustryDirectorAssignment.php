<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IndustryDirectorAssignment extends Model
{
    use HasFactory;

    protected $table = 'industry_director_assignments';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'admin_user_id',
        'user_id',
        'industry_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (IndustryDirectorAssignment $assignment): void {
            if (empty($assignment->id)) {
                $assignment->id = (string) Str::uuid();
            }
        });
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class, 'industry_id');
    }
}
