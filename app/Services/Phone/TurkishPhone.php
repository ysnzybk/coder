<?php

namespace App\Services\Phone;

class TurkishPhone
{
    public const TYPE_MOBILE = 'mobile';

    public const TYPE_LANDLINE = 'landline';

    public const TYPE_NONE = 'none';

    /**
     * "0532 123 45 67", "+90 532 123 45 67", "5321234567" → "+905321234567".
     * Türkiye numarası değilse null döner.
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '0090')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '90') && strlen($digits) === 12) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) !== 10) {
            return null;
        }

        return '+90'.$digits;
    }

    /** Türkiye'de cep numaraları +905xx ile başlar. */
    public static function isMobile(?string $phone): bool
    {
        $normalized = static::normalize($phone);

        return $normalized !== null && (bool) preg_match('/^\+905\d{9}$/', $normalized);
    }

    public static function type(?string $phone): string
    {
        if (static::normalize($phone) === null) {
            return self::TYPE_NONE;
        }

        return static::isMobile($phone) ? self::TYPE_MOBILE : self::TYPE_LANDLINE;
    }

    /** wa.me formatı: başında + olmadan ülke kodlu numara. */
    public static function forWhatsApp(?string $phone): ?string
    {
        $normalized = static::normalize($phone);

        return $normalized ? ltrim($normalized, '+') : null;
    }

    /** Ekranda gösterim: 0532 123 45 67 */
    public static function display(?string $phone): ?string
    {
        $n = static::normalize($phone);

        if (! $n) {
            return $phone;
        }

        $d = substr($n, 3);

        return sprintf('0%s %s %s %s', substr($d, 0, 3), substr($d, 3, 3), substr($d, 6, 2), substr($d, 8, 2));
    }
}
