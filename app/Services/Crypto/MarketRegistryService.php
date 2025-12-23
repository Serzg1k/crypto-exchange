<?php

namespace App\Services\Crypto;

use Illuminate\Support\Facades\Cache;

final readonly class MarketRegistryService
{
    /**
     * @param array<int, ExchangeClientInterface> $exchanges
     */
    public function __construct(private array $exchanges) {}

    /**
     * @return array<int, string> Intersection pair keys (e.g. "BTC/USDT") available on ALL exchanges.
     */
    public function intersectionPairs(int $ttlSeconds = 600): array
    {
        if ($ttlSeconds <= 0) {
            return $this->computeIntersection();
        }

        return Cache::remember('crypto:intersection_pairs:v1', $ttlSeconds, function () {
            return $this->computeIntersection();
        });
    }

    private function computeIntersection(): array
    {
        $lists = [];
        foreach ($this->exchanges as $ex) {
            $lists[$ex->name()] = $ex->fetchMarkets();
        }

        foreach ($lists as $lst) {
            if (!is_array($lst) || count($lst) === 0) {
                return [];
            }
        }

        $intersection = array_values(array_unique(array_shift($lists)));

        foreach ($lists as $lst) {
            $intersection = array_values(array_intersect($intersection, array_values(array_unique($lst))));
            if ($intersection === []) {
                return [];
            }
        }

        sort($intersection);
        return $intersection;
    }
}
