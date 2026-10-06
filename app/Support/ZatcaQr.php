<?php

namespace App\Support;

use DateTimeInterface;

/**
 * ZATCA Phase 1 TLV QR payload (tags 1-5), Base64 encoded.
 */
class ZatcaQr
{
    public static function payload(
        string $sellerName,
        string $vatNumber,
        DateTimeInterface $timestamp,
        string $totalWithVat,
        string $vatAmount,
    ): string {
        $tlv = self::tag(1, $sellerName)
            .self::tag(2, $vatNumber)
            .self::tag(3, $timestamp->format('Y-m-d\TH:i:s\Z'))
            .self::tag(4, $totalWithVat)
            .self::tag(5, $vatAmount);

        return base64_encode($tlv);
    }

    private static function tag(int $tag, string $value): string
    {
        return chr($tag).chr(strlen($value)).$value;
    }
}
