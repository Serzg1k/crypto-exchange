<?php

namespace Tests\Support;

use App\Services\Crypto\ExchangeClientInterface;

final class FakeExchange implements ExchangeClientInterface
{
    /**
     * @param array<int, string> $markets
     * @param array<string, string> $prices  // pairKey => price
     */
    public function __construct(
        private readonly string $exchangeName,
        private readonly array $markets,
        private readonly array $prices
    ) {}

    public function name(): string
    {
        return $this->exchangeName;
    }

    public function fetchMarkets(): array
    {
        return $this->markets;
    }

    public function fetchPrices(): array
    {
        return $this->prices;
    }
}
