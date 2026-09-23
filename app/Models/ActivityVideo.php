<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityVideo extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'activity_videos';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'activity_key',
        'activity_name',
        'video_type',
        'video_url',
        'file_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Standard list of activities supported across the app.
     */
    public const ACTIVITIES = [
        'testimonials'        => 'Testimonials',
        'business_deals'      => 'Business Deals',
        'requirements'        => 'Requirements',
        'referrals'           => 'Referrals',
        'p2p_meetings'        => 'P2P Meetings',
        'connections'         => 'Connections',
        'follows'             => 'Follows',
        'messages'            => 'Messages',
        'become_a_leader'     => 'Become a Leader',
        'recommend_peer'      => 'Recommend Peer',
        'register_visitor'    => 'Register Visitor',
        'stories'             => 'Story Submissions',
        'collaborations'      => 'Collaborations',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(FileModel::class, 'file_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
