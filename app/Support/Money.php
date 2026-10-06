<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * All money arithmetic goes through here. Never use floats for money.
 * Unit prices carry 4 decimals; line and document amounts carry 2,
 * rounded half-up once at line level.
 */
class Money
{
    /** Null/empty-safe wrapper around BigDecimal::of(). */
    private static function of(mixed $value): BigDecimal
    {
        return BigDecimal::of($value === null || $value === '' ? '0' : $value);
    }

    /**
     * qty x unit price, rounded half-up to 2 decimals. Returns a decimal string.
     */
    public static function line(int $qty, string $unitPrice): string
    {
        return (string) self::of($unitPrice)
            ->multipliedBy($qty)
            ->toScale(2, RoundingMode::HALF_UP);
    }

    /**
     * Sum a list of 2-decimal amount strings. Returns a decimal string.
     */
    public static function sum(iterable $amounts): string
    {
        $total = BigDecimal::of('0.00');

        foreach ($amounts as $amount) {
            $total = $total->plus(self::of($amount));
        }

        return (string) $total->toScale(2, RoundingMode::HALF_UP);
    }

    /**
     * VAT portion of a VAT-inclusive amount: amount x rate / (100 + rate).
     */
    public static function vatFromInclusive(string $amount, string $ratePercent): string
    {
        return (string) self::of($amount)
            ->multipliedBy(self::of($ratePercent))
            ->dividedBy(BigDecimal::of('100')->plus(self::of($ratePercent)), 2, RoundingMode::HALF_UP);
    }

    /**
     * VAT added on top of a VAT-exclusive amount: amount x rate / 100.
     */
    public static function vatFromExclusive(string $amount, string $ratePercent): string
    {
        return (string) self::of($amount)
            ->multipliedBy(self::of($ratePercent))
            ->dividedBy(BigDecimal::of('100'), 2, RoundingMode::HALF_UP);
    }

    public static function add(string $a, string $b): string
    {
        return (string) self::of($a)->plus(self::of($b))->toScale(2, RoundingMode::HALF_UP);
    }

    public static function subtract(string $a, string $b): string
    {
        return (string) self::of($a)->minus(self::of($b))->toScale(2, RoundingMode::HALF_UP);
    }

    public static function isZero(string $amount): bool
    {
        return self::of($amount)->isZero();
    }

    public static function compare(string $a, string $b): int
    {
        return self::of($a)->compareTo(self::of($b));
    }
}
