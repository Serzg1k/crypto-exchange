<?php

namespace App\Services\Crypto;

final readonly class PriceAggregatorService
{
    /**
     * @param array<int, ExchangeClientInterface> $exchanges
     */
    public function __construct(
        private MarketRegistryService $registry,
        private array                 $exchanges
    ) {}

    /**
     * @return array{
     *   pair: string,
     *   min: array{exchange: string, price: string},
     *   max: array{exchange: string, price: string},
     *   prices: array<string, string>
     * }|null
     */
    public function rangeForPair(string $pairInput, int $marketsTtlSeconds = 600): ?array
    {
        $pairKey = $this->normalizePairInput($pairInput);

        $intersection = $this->registry->intersectionPairs($marketsTtlSeconds);
        if (!in_array($pairKey, $intersection, true)) {

            return null;
        }

        $pricesByExchange = $this->pricesForPairAcrossAllExchanges($pairKey);

        if ($pricesByExchange === null) {

            return null;
        }

        [$minEx, $maxEx] = $this->minMaxExchange($pairKey, $pricesByExchange);

        return [
            'pair' => $pairKey,
            'min' => ['exchange' => $minEx, 'price' => $pricesByExchange[$minEx]],
            'max' => ['exchange' => $maxEx, 'price' => $pricesByExchange[$maxEx]],
            'prices' => $pricesByExchange,
        ];
    }

    /**
     * @return array<int, array{
     *   pair: string,
     *   buy: array{exchange: string, price: string},
     *   sell: array{exchange: string, price: string},
     *   profit_pct: string
     * }>
     */
    public function arbitrageTable(int $limit = 50, int $marketsTtlSeconds = 600): array
    {
        $pairs = $this->registry->intersectionPairs($marketsTtlSeconds);
        if ($pairs === []) {
            return [];
        }

        $pricesAll = [];
        foreach ($this->exchanges as $ex) {
            $pricesAll[$ex->name()] = $ex->fetchPrices();
        }

        $rows = [];

        foreach ($pairs as $pairKey) {
            foreach ($this->exchanges as $ex) {
                $exName = $ex->name();
                if (!isset($pricesAll[$exName][$pairKey])) {
                    continue 2;
                }
            }

            $minEx = null;
            $maxEx = null;

            foreach ($this->exchanges as $ex) {
                $exName = $ex->name();
                $p = (string)$pricesAll[$exName][$pairKey];

                $minEx ??= $exName;
                $maxEx ??= $exName;

                if (bccomp($p, (string)$pricesAll[$minEx][$pairKey], 18) === -1) {
                    $minEx = $exName;
                }
                if (bccomp($p, (string)$pricesAll[$maxEx][$pairKey], 18) === 1) {
                    $maxEx = $exName;
                }
            }

            $minP = (string)$pricesAll[$minEx][$pairKey];
            $maxP = (string)$pricesAll[$maxEx][$pairKey];

            if (bccomp($minP, '0', 18) <= 0) {
                continue;
            }

            $diff = bcsub($maxP, $minP, 18);
            if (bccomp($diff, '0', 18) <= 0) {
                continue;
            }

            $pct = bcmul(bcdiv($diff, $minP, 18), '100', 6);

            $rows[] = [
                'pair' => $pairKey,
                'buy' => ['exchange' => $minEx, 'price' => $minP],
                'sell' => ['exchange' => $maxEx, 'price' => $maxP],
                'profit_pct' => $pct,
            ];
        }

        usort($rows, fn($a, $b) => bccomp($b['profit_pct'], $a['profit_pct'], 6));
        return array_slice($rows, 0, max(1, $limit));
    }

    /**
     * @return array<string, string>|null exchangeName => price
     */
    private function pricesForPairAcrossAllExchanges(string $pairKey): ?array
    {
        $pricesByExchange = [];

        foreach ($this->exchanges as $ex) {
            $all = $ex->fetchPrices();
            $exName = $ex->name();

            if (!isset($all[$pairKey])) {
                return null;
            }

            $pricesByExchange[$exName] = (string)$all[$pairKey];
        }

        return $pricesByExchange;
    }

    /**
     * @param array<string, string> $pricesByExchange
     * @return array{0: string, 1: string} [minExchange, maxExchange]
     */
    private function minMaxExchange(string $pairKey, array $pricesByExchange): array
    {
        $minEx = array_key_first($pricesByExchange);
        $maxEx = array_key_first($pricesByExchange);

        foreach ($pricesByExchange as $exName => $price) {
            if (bccomp($price, $pricesByExchange[$minEx], 18) === -1) {
                $minEx = $exName;
            }
            if (bccomp($price, $pricesByExchange[$maxEx], 18) === 1) {
                $maxEx = $exName;
            }
        }

        return [$minEx, $maxEx];
    }

    private function normalizePairInput(string $pairInput): string
    {
        $s = strtoupper(trim($pairInput));
        $s = str_replace('_', '/', $s);

        if (!str_contains($s, '/')) {
            return $s;
        }

        [$base, $quote] = array_pad(explode('/', $s, 2), 2, '');
        $base = trim($base);
        $quote = trim($quote);

        return $base !== '' && $quote !== '' ? ($base . '/' . $quote) : $s;
    }
}
