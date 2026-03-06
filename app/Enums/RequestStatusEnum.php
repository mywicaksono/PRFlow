<?php

declare(strict_types=1);

namespace App\Enums;

enum RequestStatusEnum: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case COMPLETED = 'completed';

    public function isFinal(): bool
    {
        return in_array($this, [
            self::APPROVED,
            self::REJECTED,
            self::COMPLETED,
        ], true);
    }

    public function canBeEdited(): bool
    {
        return $this === self::DRAFT;
    }

    public function canBeSubmitted(): bool
    {
        return $this === self::DRAFT;
    }
}
