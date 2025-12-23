<?php

namespace Tests\Unit;

use App\Services\Crypto\MarketRegistryService;
use App\Services\Crypto\PriceAggregatorService;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeExchange;
use Tests\TestCase;

final class PriceAggregatorServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    private function makeService(): PriceAggregatorService
    {
        $exchanges = [
            new FakeExchange('binance',
                ['BTC/USDT', 'ETH/USDT', 'XRP/USDT'],
                ['BTC/USDT' => '100', 'ETH/USDT' => '10']
            ),
            new FakeExchange('bybit',
                ['BTC/USDT', 'ETH/USDT'],
                ['BTC/USDT' => '101', 'ETH/USDT' => '9']
            ),
            new FakeExchange('poloniex',
                ['BTC/USDT', 'ETH/USDT', 'DOGE/USDT'],
                ['BTC/USDT' => '99', 'ETH/USDT' => '11']
            ),
            new FakeExchange('whitebit',
                ['BTC/USDT', 'ETH/USDT', 'LTC/USDT'],
                ['BTC/USDT' => '100.5', 'ETH/USDT' => '10.5']
            ),
        ];

        $registry = new MarketRegistryService($exchanges);

        return new PriceAggregatorService($registry, $exchanges);
    }

    public function test_range_for_pair_returns_min_max_and_prices(): void
    {
        $svc = $this->makeService();

        $res = $svc->rangeForPair('BTC/USDT', -1);

        $this->assertNotNull($res);
        $this->assertSame('BTC/USDT', $res['pair']);
        $this->assertSame(['exchange' => 'poloniex', 'price' => '99'], $res['min']);
        $this->assertSame(['exchange' => 'bybit', 'price' => '101'], $res['max']);

        $this->assertSame('100', $res['prices']['binance']);
        $this->assertSame('101', $res['prices']['bybit']);
        $this->assertSame('99', $res['prices']['poloniex']);
        $this->assertSame('100.5', $res['prices']['whitebit']);
    }

    public function test_range_returns_null_if_pair_not_in_intersection(): void
    {
        $svc = $this->makeService();

        // XRP/USDT is not on all exchanges => null
        $this->assertNull($svc->rangeForPair('XRP/USDT', -1));
    }

    public function test_arbitrage_table_is_sorted_by_profit_desc(): void
    {
        $svc = $this->makeService();

        $rows = $svc->arbitrageTable(50, -1);

        $this->assertNotEmpty($rows);

        // ETH profit: (11 - 9)/9*100 = 22.222222...
        $this->assertSame('ETH/USDT', $rows[0]['pair']);
        $this->assertSame('bybit', $rows[0]['buy']['exchange']);
        $this->assertSame('9', $rows[0]['buy']['price']);
        $this->assertSame('poloniex', $rows[0]['sell']['exchange']);
        $this->assertSame('11', $rows[0]['sell']['price']);
        $this->assertSame('22.222222', $rows[0]['profit_pct']);

        // BTC profit: (101 - 99)/99*100 = 2.020202...
        $btc = array_values(array_filter($rows, fn($r) => $r['pair'] === 'BTC/USDT'))[0] ?? null;
        $this->assertNotNull($btc);
        $this->assertSame('2.020202', $btc['profit_pct']);
    }
}
