<?php

namespace App\Providers;

use App\Services\Crypto\Exchanges\BinanceClient;
use App\Services\Crypto\Exchanges\BybitClient;
use App\Services\Crypto\Exchanges\PoloniexClient;
use App\Services\Crypto\Exchanges\WhitebitClient;
use App\Services\Crypto\MarketRegistryService;
use App\Services\Crypto\PriceAggregatorService;
use Illuminate\Support\ServiceProvider;

final class CryptoExchangeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('crypto.exchanges', function () {
            $cfg = config('crypto_exchanges');

            return [
                new BinanceClient($cfg['binance']),
                new BybitClient($cfg['bybit']),
                new PoloniexClient($cfg['poloniex']),
                new WhitebitClient($cfg['whitebit']),
            ];
        });

        $this->app->singleton(MarketRegistryService::class, function ($app) {
            /** @var array $exchanges */
            $exchanges = $app->make('crypto.exchanges');
            return new MarketRegistryService($exchanges);
        });

        $this->app->singleton(PriceAggregatorService::class, function ($app) {
            /** @var array $exchanges */
            $exchanges = $app->make('crypto.exchanges');
            $registry = $app->make(MarketRegistryService::class);

            return new PriceAggregatorService($registry, $exchanges);
        });
    }
}
