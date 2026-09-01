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

    protected $table = 'page_group_items';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'page_group_id',
        'page_id',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (PageGroupItem $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function pageGroup(): BelongsTo
    {
        return $this->belongsTo(PageGroup::class, 'page_group_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(AdminPage::class, 'page_id');
    }
}
