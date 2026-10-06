<?php

namespace App\Enums;

enum CreditScope: string
{
    case Group = 'group';
    case Branch = 'branch';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
