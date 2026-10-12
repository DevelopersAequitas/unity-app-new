<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappTemplate extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'whatsapp_templates';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'meta_template_id',
        'name',
        'language',
        'category',
        'status',
        'quality',
        'parameter_format',
        'components',
        'meta_response',
        'last_synced_at',
        // Legacy fields for Resobrand / FlexiMsg backward compatibility
        'template_key',
        'template_name',
        'webhook_url',
        'webhook_secret',
        'description',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'components' => 'array',
            'meta_response' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Check if this template is approved and sendable in Meta WhatsApp.
     */
    public function isSendable(): bool
    {
        $status = strtoupper(trim((string) $this->status));

        return in_array($status, [
            'APPROVED',
            'ACTIVE',
            'ACTIVE - QUALITY PENDING',
            'ACTIVE - HIGH QUALITY',
            'ACTIVE - MEDIUM QUALITY',
            'ACTIVE - LOW QUALITY',
        ], true);
    }

    /**
     * Get messages sent using this template.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'whatsapp_template_id', 'id');
    }

    /**
     * Get Meta's rejection code if available.
     */
    public function getRejectedReason(): ?string
    {
        if (is_array($this->meta_response) && ! empty($this->meta_response['rejected_reason'])) {
            return (string) $this->meta_response['rejected_reason'];
        }

        return null;
    }

    /**
     * Get user-friendly explanation of Meta's rejection reason.
     */
    public function getRejectedReasonDescription(): ?string
    {
        $reason = $this->getRejectedReason();
        if (! $reason) {
            return null;
        }

        return match ($reason) {
            'INVALID_FORMAT' => 'Meta flagged the template formatting as invalid. This usually happens when single curly braces like {1} are used instead of double curly braces {{1}}, or variables are consecutive without words/spaces in between.',
            'INCORRECT_CATEGORY' => 'Meta determined that the content does not match the chosen category (for example, the message is promotional/marketing, but was submitted under UTILITY).',
            'TAG_CONTENT_MISMATCH' => 'The sample variable values provided do not match the expected format or context of the template text.',
            'SCAM' => 'Meta flagged the template for violating spam or misleading content guidelines.',
            'POLICY_VIOLATION' => 'Meta rejected the template for violating WhatsApp Business or Commerce policies.',
            default => 'Meta rejected this template: '.str_replace('_', ' ', $reason),
        };
    }
}
