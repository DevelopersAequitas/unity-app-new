<?php

declare(strict_types=1);

namespace App\Models\Leadership;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LeadershipNominationDocument extends Model
{
    use HasFactory;

    protected $table = 'web_leadership_nomination_documents';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'nomination_id',
        'document_type',
        'original_filename',
        'storage_disk',
        'storage_key',
        'mime_type',
        'file_size_bytes',
        'verification_status',
        'verification_remarks',
        'uploaded_by',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'verified_at' => 'datetime',
    ];

    protected $appends = [
        'file_name',
        'status',
        'file_url',
    ];

    public function getFileNameAttribute(): string
    {
        return $this->original_filename ?? basename($this->storage_key ?? 'document');
    }

    public function getStatusAttribute(): string
    {
        return $this->verification_status ?? 'pending';
    }

    public function getFileUrlAttribute(): ?string
    {
        if (! empty($this->storage_key)) {
            if (str_starts_with($this->storage_key, 'http://') || str_starts_with($this->storage_key, 'https://')) {
                return $this->storage_key;
            }

            return \Illuminate\Support\Facades\Storage::disk($this->storage_disk ?? 'public')->url($this->storage_key);
        }

        return null;
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function nomination(): BelongsTo
    {
        return $this->belongsTo(LeadershipNomination::class, 'nomination_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
