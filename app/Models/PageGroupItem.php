<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PageGroupItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'group_id',
        'page_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (PageGroupItem $item): void {
            if (empty($item->id)) {
                $item->id = (string) Str::uuid();
            }
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(PageGroup::class, 'group_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(AdminPage::class, 'page_id');
    }
}
