<?php

namespace App\Services\WhatsApp\Data;

use Carbon\CarbonImmutable;

final readonly class InboundMessage
{
    public function __construct(
        public string $from,
        public string $body,
        public ?string $providerMessageId = null,
        public ?string $contactName = null,
        public ?CarbonImmutable $receivedAt = null,
        public string $type = 'text',
    ) {}
}
