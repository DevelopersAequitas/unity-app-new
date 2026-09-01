<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SmeBusinessStorySubmission extends Model
{
    use HasUuids;

    protected $table = 'sme_business_story_submissions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'full_name',
        'email',
        'contact_number',
        'business_name',
        'company_introduction',
        'co_founders_and_partners_details',
        'status',
        'notes',
        'user_id',
        'title',
        'story',
        'short_description',
        'cover_image',
        'attachments',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejected_reason',
        'designation',
        'company_name',
        'website',
        'profile_photo',
        'company_logo',
        'entrepreneurial_journey',
        'business_description',
        'biggest_challenge',
        'biggest_achievement',
        'business_impact',
        'future_goals',
        'advice_for_entrepreneurs',
        'linkedin_url',
        'facebook_url',
        'instagram_url',
        'twitter_url',
        'consent',
        'admin_remark',
        'reviewed_at',
        'story_link',
    ];

    protected $casts = [
        'attachments' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'consent' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function coverImageFile()
    {
        return $this->belongsTo(FileModel::class, 'cover_image');
    }

    public function profilePhotoFile()
    {
        return $this->belongsTo(FileModel::class, 'profile_photo');
    }

    public function companyLogoFile()
    {
        return $this->belongsTo(FileModel::class, 'company_logo');
    }

    public function approver()
    {
        return $this->belongsTo(AdminUser::class, 'approved_by');
    }
}
