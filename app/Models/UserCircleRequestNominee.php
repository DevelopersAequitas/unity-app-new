<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserCircleRequestNominee extends Model
{
    use HasFactory;

    protected $table = 'user_circle_request_nominees';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_circle_request_id',
        'nominee_user_id',
        'name',
        'phone',
        'email',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function circleRequest(): BelongsTo
    {
        return $this->belongsTo(UserCircleRequest::class, 'user_circle_request_id');
    }

    public function nomineeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nominee_user_id');
    }
}
