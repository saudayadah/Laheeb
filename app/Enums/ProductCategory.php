<?php

namespace App\Enums;

enum ProductCategory: string
{
    case Saj = 'saj';
    case Milk = 'milk';
    case Arabic = 'arabic';
    case Wheat = 'wheat';
    case Red = 'red';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function nameAr(): string
    {
        return match ($this) {
            self::Saj => 'صاج',
            self::Milk => 'حليب',
            self::Arabic => 'عربي',
            self::Wheat => 'بر',
            self::Red => 'خبز أحمر',
        };
    }

    public function nameEn(): string
    {
        return match ($this) {
            self::Saj => 'Saj',
            self::Milk => 'Milk',
            self::Arabic => 'Arabic',
            self::Wheat => 'Whole-wheat',
            self::Red => 'Red bread',
        };
    }
}
