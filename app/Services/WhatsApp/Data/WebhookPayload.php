<?php

namespace App\Services\WhatsApp\Data;

final readonly class WebhookPayload
{
    /**
     * @param  list<InboundMessage>  $messages
     * @param  list<StatusUpdate>  $statuses
     */
    public function __construct(
        public array $messages = [],
        public array $statuses = [],
    ) {}
}
