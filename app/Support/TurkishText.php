<?php

namespace App\Support;

class TurkishText
{
    /** Türkçe harfleri sadeleştirip küçültür: "İSTANBUL" ve "Istanbul" → "istanbul" (karşılaştırma için). */
    public static function fold(string $text): string
    {
        $text = strtr($text, ['İ' => 'i', 'I' => 'ı', 'Ş' => 'ş', 'Ğ' => 'ğ', 'Ü' => 'ü', 'Ö' => 'ö', 'Ç' => 'ç']);

        return strtr(mb_strtolower($text, 'UTF-8'), ['ı' => 'i', 'ğ' => 'g', 'ü' => 'u', 'ş' => 's', 'ö' => 'o', 'ç' => 'c']);
    }
}
