<?php

namespace Tests\Unit;

use App\Services\Crypto\MarketRegistryService;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeExchange;
use Tests\TestCase;

final class MarketRegistryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Use in-memory cache for tests
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    public function test_it_builds_intersection_across_all_exchanges(): void
    {
        $exchanges = [
            new FakeExchange('binance',  ['BTC/USDT', 'ETH/USDT', 'XRP/USDT'], []),
            new FakeExchange('bybit',    ['BTC/USDT', 'ETH/USDT'], []),
            new FakeExchange('poloniex', ['BTC/USDT', 'ETH/USDT', 'DOGE/USDT'], []),
            new FakeExchange('whitebit', ['BTC/USDT', 'ETH/USDT', 'LTC/USDT'], []),
        ];

        $svc = new MarketRegistryService($exchanges);

        $intersection = $svc->intersectionPairs(-1);

        $this->assertSame(['BTC/USDT', 'ETH/USDT'], $intersection);
    }

    public function test_intersection_is_empty_if_any_exchange_returns_empty_markets(): void
    {
        $exchanges = [
            new FakeExchange('binance',  [], []),
            new FakeExchange('bybit',    ['BTC/USDT'], []),
            new FakeExchange('poloniex', ['BTC/USDT'], []),
            new FakeExchange('whitebit', ['BTC/USDT'], []),
        ];

        $svc = new MarketRegistryService($exchanges);

        $this->assertSame([], $svc->intersectionPairs(-1));
    }
}
