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
    ];

    protected $casts = [
        'attachments' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function coverImageFile()
    {
        return $this->belongsTo(FileModel::class, 'cover_image');
    }

    public function approver()
    {
        return $this->belongsTo(AdminUser::class, 'approved_by');
    }
}
