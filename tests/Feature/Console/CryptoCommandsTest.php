<?php

namespace Tests\Feature\Console;

use App\Services\Crypto\MarketRegistryService;
use App\Services\Crypto\PriceAggregatorService;
use Illuminate\Support\Facades\Cache;
use Tests\Support\FakeExchange;
use Tests\TestCase;

final class CryptoCommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();

        $exchanges = [
            new FakeExchange('binance',
                ['BTC/USDT', 'ETH/USDT'],
                ['BTC/USDT' => '100', 'ETH/USDT' => '10']
            ),
            new FakeExchange('bybit',
                ['BTC/USDT', 'ETH/USDT'],
                ['BTC/USDT' => '101', 'ETH/USDT' => '9']
            ),
            new FakeExchange('poloniex',
                ['BTC/USDT', 'ETH/USDT'],
                ['BTC/USDT' => '99', 'ETH/USDT' => '11']
            ),
            new FakeExchange('whitebit',
                ['BTC/USDT', 'ETH/USDT'],
                ['BTC/USDT' => '100.5', 'ETH/USDT' => '10.5']
            ),
        ];

        $this->app->singleton('crypto.exchanges', fn() => $exchanges);

        $this->app->singleton(MarketRegistryService::class, function ($app) {
            return new MarketRegistryService($app->make('crypto.exchanges'));
        });

        $this->app->singleton(PriceAggregatorService::class, function ($app) {
            return new PriceAggregatorService(
                $app->make(MarketRegistryService::class),
                $app->make('crypto.exchanges')
            );
        });
    }

    public function test_crypto_range_command_outputs_min_max(): void
    {
        $this->artisan('crypto:range', ['pair' => 'BTC/USDT', '--no-cache' => true])
            ->expectsOutput('BTC/USDT')
            ->expectsOutput('MIN: 99 @ poloniex')
            ->expectsOutput('MAX: 101 @ bybit')
            ->assertExitCode(0);
    }

    public function test_crypto_arbitrage_command_respects_min_profit_filter(): void
    {
        // assuming command supports --min-profit
        $this->artisan('crypto:arbitrage', ['--limit' => 50, '--min-profit' => 5, '--no-cache' => true])
            ->expectsTable(
                ['pair','buy_exchange','buy_price','sell_exchange','sell_price','profit_%'],
                [
                    [
                        'pair' => 'ETH/USDT',
                        'buy_exchange' => 'bybit',
                        'buy_price' => '9',
                        'sell_exchange' => 'poloniex',
                        'sell_price' => '11',
                        'profit_%' => '22.222222',
                    ],
                ]
            )
            ->assertExitCode(0);
    }
}
