<?php

namespace App\Services\WhatsApp\Data;

use App\Enums\MessageStatus;
use Carbon\CarbonImmutable;

final readonly class StatusUpdate
{
    public function __construct(
        public string $providerMessageId,
        public MessageStatus $status,
        public ?CarbonImmutable $occurredAt = null,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
    ) {}
}
