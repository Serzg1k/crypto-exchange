<?php

namespace App\Console\Commands;

use App\Services\Crypto\PriceAggregatorService;
use Illuminate\Console\Command;

final class CryptoArbitrageCommand extends Command
{
    protected $signature = 'crypto:arbitrage
    {--limit=50 : Top N rows}
    {--min-profit=0 : Minimum profit percent to show}
    {--no-cache : Do not use cached markets intersection}';

    protected $description = 'Build list of pairs with profit % between min buy exchange and max sell exchange (pairs exist on all).';

    public function handle(PriceAggregatorService $svc): int
    {
        $limit = max(1, (int)$this->option('limit'));
        $ttl = $this->option('no-cache') ? -1 : 600;

        $rows = $svc->arbitrageTable($limit, $ttl);

        $minProfit = (string)$this->option('min-profit');
        $rows = array_values(array_filter($rows, static function ($r) use ($minProfit) {
            return bccomp($r['profit_pct'], $minProfit, 6) >= 0;
        }));

        if ($rows === []) {
            $this->warn('No profitable opportunities found (or prices missing).');
            return self::SUCCESS;
        }

        $table = array_map(function ($r) {
            return [
                'pair' => $r['pair'],
                'buy_exchange' => $r['buy']['exchange'],
                'buy_price' => $r['buy']['price'],
                'sell_exchange' => $r['sell']['exchange'],
                'sell_price' => $r['sell']['price'],
                'profit_%' => $r['profit_pct'],
            ];
        }, $rows);

        $this->table(['pair','buy_exchange','buy_price','sell_exchange','sell_price','profit_%'], $table);
        return self::SUCCESS;
    }
}
