<?php

namespace App\Services\Crypto;

final class PairKey
{
    public static function fromBaseQuote(string $base, string $quote): string
    {
        return strtoupper($base) . '/' . strtoupper($quote);
    }

    public static function fromUnderscore(string $symbol): string
    {
        return str_replace('_', '/', strtoupper($symbol));
    }
}
