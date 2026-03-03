<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRoleEnum: string
{
    case STAFF = 'staff';
    case SUPERVISOR = 'supervisor';
    case MANAGER = 'manager';
    case FINANCE = 'finance';
    case ADMIN = 'admin';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::STAFF->value => 'Staff',
            self::SUPERVISOR->value => 'Supervisor',
            self::MANAGER->value => 'Manager',
            self::FINANCE->value => 'Finance',
            self::ADMIN->value => 'Administrator',
        ];
    }

    public function isApprover(): bool
    {
        return in_array($this, [
            self::SUPERVISOR,
            self::MANAGER,
            self::FINANCE,
            self::ADMIN,
        ], true);
    }

    public function canManageSystem(): bool
    {
        return $this === self::ADMIN;
    }
}
