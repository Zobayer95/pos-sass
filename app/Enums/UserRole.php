<?php

namespace App\Enums;

enum UserRole: string
{
    case OWNER = 'owner';
    case STAFF = 'staff';

    public function isOwner(): bool
    {
        return $this === self::OWNER;
    }

    public function isStaff(): bool
    {
        return $this === self::STAFF;
    }
}
