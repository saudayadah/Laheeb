<?php

namespace App\Support;

/**
 * One line's money math, honoring the VAT-inclusive/exclusive setting.
 * Rounding happens once, at line level, half-up.
 */
class VatMath
{
    /**
     * @return array{subtotal: string, vat: string, total: string}
     */
    public static function line(int $qty, string $unitPrice, string $vatRate, bool $pricesIncludeVat): array
    {
        $amount = Money::line($qty, $unitPrice);

        if ((float) $vatRate <= 0) {
            return ['subtotal' => $amount, 'vat' => '0.00', 'total' => $amount];
        }

        if ($pricesIncludeVat) {
            $vat = Money::vatFromInclusive($amount, $vatRate);

            return ['subtotal' => Money::subtract($amount, $vat), 'vat' => $vat, 'total' => $amount];
        }

        $vat = Money::vatFromExclusive($amount, $vatRate);

        return ['subtotal' => $amount, 'vat' => $vat, 'total' => Money::add($amount, $vat)];
    }
}
