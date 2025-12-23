<?php

namespace App\Services\Crypto\Exchanges;

use App\Services\Crypto\ExchangeClientInterface;
use App\Services\Crypto\PairKey;

final class WhitebitClient extends BaseHttpExchangeClient implements ExchangeClientInterface
{
    public function name(): string
    {
        return 'whitebit';
    }

    public function fetchMarkets(): array
    {
        $resp = $this->get('/api/v4/public/markets');

        $pairs = [];
        foreach ($resp as $m) {
            if (!is_array($m)) continue;

            if (($m['type'] ?? null) !== 'spot') {
                continue;
            }

            $name = (string)($m['name'] ?? '');
            if ($name === '') continue;

            $pairs[] = PairKey::fromUnderscore($name);
        }

        return array_values(array_unique($pairs));
    }

    public function fetchPrices(): array
    {
        $resp = $this->get('/api/v4/public/ticker');

        $out = [];
        foreach ($resp as $market => $data) {
            if (!is_array($data)) continue;

            $pairKey = PairKey::fromUnderscore((string)$market);
            if (!isset($data['last_price'])) continue;

            $out[$pairKey] = (string)$data['last_price'];
        }

        return $out;
    }
}
