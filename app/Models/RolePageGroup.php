<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RolePageGroup extends Model
{
    use HasFactory;

    protected $table = 'role_page_groups';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'role_id',
        'page_group_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (RolePageGroup $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function pageGroup(): BelongsTo
    {
        return $this->belongsTo(PageGroup::class, 'page_group_id');
    }
}
