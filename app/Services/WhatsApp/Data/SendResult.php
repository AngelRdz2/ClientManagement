<?php

namespace App\Services\WhatsApp\Data;

use App\Enums\MessageStatus;

final readonly class SendResult
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public bool $success,
        public ?string $providerMessageId = null,
        public MessageStatus $status = MessageStatus::Sent,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public bool $retryable = false,
        public array $raw = [],
    ) {}

    /** @param array<string, mixed> $raw */
    public static function ok(string $providerMessageId, MessageStatus $status = MessageStatus::Sent, array $raw = []): self
    {
        return new self(true, $providerMessageId, $status, raw: $raw);
    }

    /** @param array<string, mixed> $raw */
    public static function failed(string $errorCode, string $errorMessage, bool $retryable = false, array $raw = []): self
    {
        return new self(false, null, MessageStatus::Failed, $errorCode, $errorMessage, $retryable, $raw);
    }
}
