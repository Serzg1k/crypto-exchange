<?php

namespace App\Console\Commands;

use App\Services\Crypto\PriceAggregatorService;
use Illuminate\Console\Command;

final class CryptoRangeCommand extends Command
{
    protected $signature = 'crypto:range {pair : e.g. BTC/USDT} {--no-cache : Do not use cached markets intersection}';

    protected $description = 'Show min/max last price for a pair across exchanges (only if pair exists on all).';

    public function handle(PriceAggregatorService $svc): int
    {
        $pair = (string)$this->argument('pair');
        $ttl = $this->option('no-cache') ? -1 : 600;

        $res = $svc->rangeForPair($pair, $ttl);

        if ($res === null) {
            $this->error("Pair '{$pair}' is not available on all exchanges OR price is missing on at least one exchange.");
            return self::FAILURE;
        }

        $this->info($res['pair']);
        $this->line("MIN: {$res['min']['price']} @ {$res['min']['exchange']}");
        $this->line("MAX: {$res['max']['price']} @ {$res['max']['exchange']}");

        $table = [];
        foreach ($res['prices'] as $ex => $price) {
            $table[] = ['exchange' => $ex, 'price' => $price];
        }

        $this->newLine();
        $this->table(['exchange', 'price'], $table);

        return self::SUCCESS;
    }

}
