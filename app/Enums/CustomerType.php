<?php

namespace App\Enums;

enum CustomerType: string
{
    case Wholesale = 'wholesale';
    case Retail = 'retail';
    case WalkIn = 'walkin';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
