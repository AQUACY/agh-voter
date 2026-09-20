<?php

namespace App\Support;

use InvalidArgumentException;

class GhanaPhone
{
    public static function normalize(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '233') && strlen($digits) === 12) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '233'.substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            return '233'.$digits;
        }

        throw new InvalidArgumentException('Enter a valid Ghana phone number.');
    }

    public static function mask(string $e164): string
    {
        $normalized = self::normalize($e164);
        $local = '0'.substr($normalized, 3);

        return substr($local, 0, 3).'****'.substr($local, -4);
    }

    public static function local(string $e164): string
    {
        return '0'.substr(self::normalize($e164), 3);
    }
}
