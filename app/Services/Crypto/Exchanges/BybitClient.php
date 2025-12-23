<?php

namespace App\Services\Crypto\Exchanges;

use App\Services\Crypto\ExchangeClientInterface;
use App\Services\Crypto\PairKey;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

final class BybitClient extends BaseHttpExchangeClient implements ExchangeClientInterface
{
    /** @var array<string, string> symbol => pairKey */
    private array $symbolToPair = [];

    public function name(): string
    {
        return 'bybit';
    }

    public function fetchMarkets(): array
    {
        $resp = $this->get('/v5/market/instruments-info', ['category' => 'spot']);

        $pairs = [];
        $this->symbolToPair = [];

        $list = $resp['result']['list'] ?? [];
        foreach ($list as $item) {
            $symbol = (string)($item['symbol'] ?? '');
            $base   = (string)($item['baseCoin'] ?? '');
            $quote  = (string)($item['quoteCoin'] ?? '');

            if ($symbol === '' || $base === '' || $quote === '') continue;

            $status = $item['status'] ?? null;
            if (is_string($status) && strtolower($status) !== 'trading') continue;

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

        $resp = $this->get('/v5/market/tickers', ['category' => 'spot']);

        $out = [];
        $list = $resp['result']['list'] ?? [];
        foreach ($list as $row) {
            $symbol = (string)($row['symbol'] ?? '');
            $last   = $row['lastPrice'] ?? null;

            if ($symbol === '' || $last === null) continue;

            $pairKey = $this->symbolToPair[$symbol] ?? null;
            if ($pairKey === null) continue;

            $out[$pairKey] = (string)$last;
        }

        return $out;
    }
}
