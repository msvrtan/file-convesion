<?php

declare(strict_types=1);

namespace App\Model;

enum ConversionStatus: int
{
    case Accepted = 0;
    case InProgress = 2;
    case Failed = 4;
    case Completed = 7;

    public function asString(): string
    {
        return match ($this) {
            self::Accepted => 'accepted',
            self::InProgress => 'inprogress',
            self::Failed => 'failed',
            self::Completed => 'completed',
        };
    }
}
