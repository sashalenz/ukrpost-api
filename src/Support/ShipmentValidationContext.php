<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Support;

final readonly class ShipmentValidationContext
{
    /**
     * @param  array<string, mixed>|null  $sender
     * @param  array<string, mixed>|null  $recipient
     * @param  array<string, mixed>|null  $recipientAddress
     */
    public function __construct(
        public ?array $sender = null,
        public ?array $recipient = null,
        public ?array $recipientAddress = null,
        public bool $recipientPostOfficeMobile = false,
    ) {}
}
