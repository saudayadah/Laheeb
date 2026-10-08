<?php

namespace App\Support;

use Carbon\CarbonImmutable;
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
            // The trailing Z asserts UTC, so the instant must BE UTC.
            .self::tag(2, $vatNumber)
            .self::tag(3, CarbonImmutable::instance($timestamp)->utc()->format('Y-m-d\TH:i:s\Z'))
            .self::tag(4, $totalWithVat)
            .self::tag(5, $vatAmount);

        return base64_encode($tlv);
    }

    private static function tag(int $tag, string $value): string
    {
        // Single-byte TLV length: clamp on a UTF-8 boundary rather than let
        // chr() wrap at 256 and corrupt every tag after a long seller name.
        if (strlen($value) > 255) {
            $value = mb_strcut($value, 0, 255, 'UTF-8');
        }

        return chr($tag).chr(strlen($value)).$value;
    }
}
