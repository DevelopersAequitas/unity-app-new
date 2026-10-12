<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

class MetaWhatsAppException extends Exception
{
    /**
     * @param  array<string, mixed>  $rawError
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        private readonly int $httpStatus = 400,
        private readonly ?int $metaErrorCode = null,
        private readonly ?string $metaErrorType = null,
        private readonly ?int $metaErrorSubcode = null,
        private readonly ?string $metaFbtraceId = null,
        private readonly array $rawError = [],
        private readonly array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getMetaErrorCode(): ?int
    {
        return $this->metaErrorCode;
    }

    public function getMetaErrorType(): ?string
    {
        return $this->metaErrorType;
    }

    public function getMetaErrorSubcode(): ?int
    {
        return $this->metaErrorSubcode;
    }

    public function getMetaFbtraceId(): ?string
    {
        return $this->metaFbtraceId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawError(): array
    {
        return $this->rawError;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Generate a human-friendly admin error explanation based on Meta error code.
     */
    public function getUserFriendlyMessage(): string
    {
        return match ($this->metaErrorCode) {
            190 => 'The configured Meta WhatsApp Access Token is invalid or expired. Please generate a new System User access token.',
            100 => 'Invalid Meta parameter: '.$this->getMessage(),
            131000 => 'Meta WhatsApp service is temporarily unavailable. Please retry later.',
            131008 => 'Required parameter in template message is missing or invalid.',
            131026 => 'Message undeliverable: The recipient number cannot receive WhatsApp messages, or WhatsApp privacy settings prevent delivery.',
            131042 => 'WhatsApp Business Account is payment ineligible or requires business verification.',
            131045 => 'WhatsApp template does not exist or has not been approved in this language.',
            131047 => 'Re-engagement message window expired. More than 24 hours have passed since the customer last messaged.',
            131049 => 'Ecosystem engagement restriction: Meta temporarily throttled delivery to maintain community messaging health.',
            131051 => 'WhatsApp template message type is unsupported.',
            131052 => 'Media download failed: The media URL could not be downloaded by Meta servers.',
            131053 => 'Media upload failed: Uploaded media format is invalid or file size exceeds Meta limits.',
            132000 => 'Template variable count mismatch: The number of parameters provided does not match the approved template.',
            132001 => 'WhatsApp template is currently PAUSED or DISABLED by Meta.',
            133010 => 'Phone number not registered or deregistered from WhatsApp.',
            80007 => 'Rate limit exceeded: Please wait before sending additional WhatsApp messages.',
            default => $this->getMessage(),
        };
    }
}
