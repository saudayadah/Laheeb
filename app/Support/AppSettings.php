<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class AppSettings
{
    public const CACHE_KEY = 'app_settings.all';

    /**
     * Default values used until the owner saves their own.
     */
    public const DEFAULTS = [
        'bakery_name_ar' => 'معمل لهيب للخبز',
        'bakery_name_en' => 'Laheeb Bread Bakery',
        'vat_number' => '',
        'cr_number' => '',
        'national_address' => '',
        'phone' => '',
        'vat_enabled' => true,
        'prices_include_vat' => true,
        'vat_rate' => '15.00',
        'default_credit_days' => 30,
        'expense_approval_threshold' => '500',
        'advance_max_multiple' => '2',
    ];

    public static function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::query()
                ->pluck('value', 'key')
                ->map(fn ($value) => $value === null ? null : json_decode($value, true))
                ->all();
        });

        return array_merge(self::DEFAULTS, $stored);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => json_encode($value, JSON_UNESCAPED_UNICODE)]);
        Cache::forget(self::CACHE_KEY);
    }

    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => json_encode($value, JSON_UNESCAPED_UNICODE)]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    public static function vatEnabled(): bool
    {
        return (bool) self::get('vat_enabled');
    }

    public static function vatRate(): string
    {
        return (string) self::get('vat_rate', '15.00');
    }

    public static function pricesIncludeVat(): bool
    {
        return (bool) self::get('prices_include_vat');
    }

    public static function defaultCreditDays(): int
    {
        return (int) self::get('default_credit_days', 30);
    }
}
