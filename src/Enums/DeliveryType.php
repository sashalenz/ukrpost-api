<?php

declare(strict_types=1);

namespace Sashalenz\UkrPostApi\Enums;

enum DeliveryType: string
{
    case W2W = 'W2W';
    case W2D = 'W2D';
    case D2W = 'D2W';
    case D2D = 'D2D';

    public function hasCourierPickup(): bool
    {
        return str_starts_with($this->value, 'D');
    }

    public function hasCourierDelivery(): bool
    {
        return str_ends_with($this->value, 'D');
    }

    public function usesCourier(): bool
    {
        return $this->hasCourierPickup() || $this->hasCourierDelivery();
    }
}
