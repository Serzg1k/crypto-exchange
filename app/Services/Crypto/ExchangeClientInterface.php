<?php

namespace App\Services\Crypto;

interface ExchangeClientInterface
{
    public function name(): string;

    /**
     * @return array<int, string> List of normalized pair keys like "BTC/USDT"
     */
    public function fetchMarkets(): array;

    /**
     * @return array<string, string> Map pairKey => lastPrice (decimal string)
     */
    public function fetchPrices(): array;
}
