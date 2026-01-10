<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }

    public function canBeUpdatedTo(OrderStatus $newStatus): bool
    {
        if ($this === self::CANCELLED) {
            return false;
        }

        if ($this === self::PAID && $newStatus === self::PENDING) {
            return false;
        }

        return true;
    }
}
