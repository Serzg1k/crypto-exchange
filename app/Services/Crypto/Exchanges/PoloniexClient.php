<?php

namespace App\Services\Crypto\Exchanges;

use App\Services\Crypto\ExchangeClientInterface;
use App\Services\Crypto\PairKey;

final class PoloniexClient extends BaseHttpExchangeClient implements ExchangeClientInterface
{
    public function name(): string
    {
        return 'poloniex';
    }

    public function fetchMarkets(): array
    {
        $resp = $this->get('/markets');

        $pairs = [];
        foreach ($resp as $m) {
            if (!is_array($m)) continue;

            $symbol = (string)($m['symbol'] ?? '');
            if ($symbol === '') continue;

            $state = $m['state'] ?? null;
            if (is_string($state) && in_array(strtoupper($state), ['OFFLINE', 'HALT', 'SUSPENDED'], true)) {
                continue;
            }

            $pairs[] = PairKey::fromUnderscore($symbol);
        }

        return array_values(array_unique($pairs));
    }

    public function fetchPrices(): array
    {
        $resp = $this->get('/markets/price');

        $out = [];
        foreach ($resp as $row) {
            if (!is_array($row)) continue;

            $symbol = (string)($row['symbol'] ?? '');
            $price  = $row['price'] ?? null;

            if ($symbol === '' || $price === null) continue;

            $out[PairKey::fromUnderscore($symbol)] = (string)$price;
        }

        return $out;
    }
}
