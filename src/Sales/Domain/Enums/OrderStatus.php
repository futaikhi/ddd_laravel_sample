<?php

declare(strict_types=1);

namespace Src\Sales\Domain\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public static function getDefaultValue(): self
    {
        return self::PENDING;
    }
}
