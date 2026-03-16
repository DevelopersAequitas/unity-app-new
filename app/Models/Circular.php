<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Circular extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const CATEGORY_OPTIONS = [
        'event',
        'announcement',
        'update',
        'opportunity',
        'recognition',
        'policy',
    ];

    public const PRIORITY_OPTIONS = [
        'normal',
        'important',
        'urgent',
    ];

    public const AUDIENCE_OPTIONS = [
        'all_members',
        'circle_members',
        'fempreneur',
        'greenpreneur',
    ];

    protected $fillable = [
        'title',
        'summary',
        'category',
        'priority',
        'featured_image_file_id',
        'featured_image_url',
        'content',
        'attachment_file_id',
        'attachment_url',
        'video_url',
        'audience_type',
        'city_id',
        'circle_id',
        'send_push_notification',
        'allow_comments',
        'is_pinned',
        'status',
        'publish_date',
        'expiry_date',
        'created_by',
        'updated_by',
        'slug',
        'cta_label',
        'cta_url',
        'view_count',
    ];

    protected $casts = [
        'publish_date' => 'datetime',
        'expiry_date' => 'datetime',
        'send_push_notification' => 'boolean',
        'allow_comments' => 'boolean',
        'is_pinned' => 'boolean',
        'view_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Circular $circular): void {
            if (blank($circular->slug)) {
                $circular->slug = static::generateUniqueSlug((string) $circular->title);
            }

            if ($circular->view_count === null) {
                $circular->view_count = 0;
            }
        });

        static::updating(function (Circular $circular): void {
            if ($circular->isDirty('title') && filled($circular->title)) {
                $circular->slug = static::generateUniqueSlug((string) $circular->title, (int) $circular->id);
            }
        });
    }

    public static function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($title);

        if ($baseSlug === '') {
            $baseSlug = 'circular';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (static::query()
            ->when($ignoreId, fn (Builder $query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->withTrashed()
            ->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereRaw('LOWER(COALESCE(status::text, ?)) = ?', ['', 'active']);
    }

    public function scopePublished(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $currentTime = $now ?? now(config('app.timezone'));

        return $query
            ->whereNotNull('publish_date')
            ->where('publish_date', '<=', $currentTime);
    }

    public function scopeNotExpired(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $currentTime = $now ?? now(config('app.timezone'));

        return $query->where(function (Builder $expiryQuery) use ($currentTime): void {
            $expiryQuery->whereNull('expiry_date')->orWhere('expiry_date', '>=', $currentTime);
        });
    }

    public function scopeVisibleInApp(Builder $query, ?CarbonInterface $now = null): Builder
    {
        $currentTime = $now ?? now(config('app.timezone'));

        return $query
            ->whereNull('deleted_at')
            ->active()
            ->published($currentTime)
            ->notExpired($currentTime);
    }

    public static function statusOptions(): array
    {
        if (! Schema::hasTable('circulars') || ! Schema::hasColumn('circulars', 'status')) {
            return ['active', 'inactive', 'draft'];
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            $row = $connection->selectOne("SELECT udt_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'circulars' AND column_name = 'status'");
            $typeName = is_object($row) ? ($row->udt_name ?? null) : null;

            if ($typeName) {
                $enumRows = $connection->select('SELECT enumlabel FROM pg_enum JOIN pg_type ON pg_enum.enumtypid = pg_type.oid WHERE pg_type.typname = ? ORDER BY enumsortorder', [$typeName]);
                $values = collect($enumRows)->pluck('enumlabel')->filter()->values()->all();
                if ($values !== []) {
                    return $values;
                }
            }
        }

        return ['active', 'inactive', 'draft'];
    }
}
