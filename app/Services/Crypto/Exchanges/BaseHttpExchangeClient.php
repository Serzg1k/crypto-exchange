<?php

namespace App\Services\Crypto\Exchanges;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Throwable;

abstract class BaseHttpExchangeClient
{
    public function __construct(protected readonly array $cfg) {}

    protected function get(string $path, array $query = []): array
    {
        $resp = $this->request('GET', $path, $query);
        if ($resp === null) {
            return [];
        }
        try {
            $json = $resp->json();
            return is_array($json) ? $json : [];
        } catch (Throwable $e) {
            Log::warning('Exchange unexpected error', [
                'exchange' => static::class,
                'base_url' => $this->cfg['base_url'] ?? null,
                'path' => $path,
                'message' => $e->getMessage(),
            ]);
            return [];
        }
    }

    protected function request(string $method, string $path, array $query = []): ?Response
    {
        try {
            return Http::timeout($this->cfg['timeout'] ?? 10)
                ->retry(2, 200)
                ->send($method, $this->cfg['base_url'] . $path, [
                    'query' => $query,
                ])
                ->throw();
        } catch (ConnectionException|RequestException $e) {
            Log::warning('Exchange HTTP error', [
                'exchange' => static::class,
                'base_url' => $this->cfg['base_url'] ?? null,
                'path' => $path,
                'method' => $method,
                'status' => method_exists($e, 'response') && $e->response ? $e->response->status() : null,
                'message' => $e->getMessage(),
            ]);

            return null;
        } catch (Throwable $e) {
            Log::warning('Exchange unexpected error', [
                'exchange' => static::class,
                'base_url' => $this->cfg['base_url'] ?? null,
                'path' => $path,
                'method' => $method,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
