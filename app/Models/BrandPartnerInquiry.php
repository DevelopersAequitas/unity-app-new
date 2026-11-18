<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BrandPartnerInquiry extends Model
{
    use HasFactory;

    protected $table = 'brand_partner_inquiries';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'brand_partner_id',
        'user_id',
        'name',
        'phone',
        'email',
        'message',
        'status',
        'email_sent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_sent' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (BrandPartnerInquiry $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Ensure the brand_partner_inquiries table exists.
     */
    public static function ensureTableExists(): void
    {
        try {
            if (! Schema::hasTable('brand_partner_inquiries')) {
                Schema::create('brand_partner_inquiries', function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->uuid('brand_partner_id')->index();
                    $table->uuid('user_id')->nullable()->index();
                    $table->string('name', 180);
                    $table->string('phone', 50);
                    $table->string('email', 254);
                    $table->text('message');
                    $table->string('status', 32)->default('new');
                    $table->boolean('email_sent')->default(false);
                    $table->timestamps();
                });
            }
        } catch (\Throwable) {
            // ignore schema creation error if concurrent
        }
    }

    /**
     * @return BelongsTo<BrandPartner, $this>
     */
    public function brandPartner(): BelongsTo
    {
        return $this->belongsTo(BrandPartner::class, 'brand_partner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
