<?php

namespace App\Services\Crypto\Exchanges;

use App\Services\Crypto\ExchangeClientInterface;
use App\Services\Crypto\PairKey;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

final class BinanceClient extends BaseHttpExchangeClient implements ExchangeClientInterface
{
    /** @var array<string, string> symbol => pairKey */
    private array $symbolToPair = [];

    public function name(): string
    {
        return 'binance';
    }

    public function fetchMarkets(): array
    {
        $resp = $this->get('/api/v3/exchangeInfo');

        if (!isset($resp['symbols']) || !is_array($resp['symbols'])) {
            return [];
        }

        $pairs = [];
        $this->symbolToPair = [];

        foreach ($resp['symbols'] as $s) {
            if (!is_array($s)) continue;

            if (isset($s['status']) && $s['status'] !== 'TRADING') {
                continue;
            }

            if (array_key_exists('isSpotTradingAllowed', $s) && $s['isSpotTradingAllowed'] === false) {
                continue;
            }

            $isSpot = null;

            if (isset($s['permissions']) && is_array($s['permissions']) && count($s['permissions']) > 0) {
                $isSpot = in_array('SPOT', $s['permissions'], true);
            }

            if ($isSpot === null && isset($s['permissionSets']) && is_array($s['permissionSets'])) {
                $found = false;
                foreach ($s['permissionSets'] as $set) {
                    if (is_array($set) && in_array('SPOT', $set, true)) {
                        $found = true;
                        break;
                    }
                }
                $isSpot = $found;
            }

            if ($isSpot === false) {
                continue;
            }

            $base = (string)($s['baseAsset'] ?? '');
            $quote = (string)($s['quoteAsset'] ?? '');
            $symbol = (string)($s['symbol'] ?? '');

            if ($base === '' || $quote === '' || $symbol === '') continue;

            $pairKey = PairKey::fromBaseQuote($base, $quote);
            $pairs[] = $pairKey;
            $this->symbolToPair[$symbol] = $pairKey;
        }

        return array_values(array_unique($pairs));
    }

    public function fetchPrices(): array
    {
        if ($this->symbolToPair === []) {
            $this->fetchMarkets();
        }

        $resp = $this->get('/api/v3/ticker/price');

        $out = [];
        foreach ($resp as $row) {
            if (!is_array($row)) continue;

            $symbol = (string)($row['symbol'] ?? '');
            $price  = $row['price'] ?? null;

            if ($symbol === '' || $price === null) continue;

            $pairKey = $this->symbolToPair[$symbol] ?? null;
            if ($pairKey === null) continue;

            $out[$pairKey] = (string)$price;
        }

        return $out;
    }
}
