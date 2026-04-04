<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MarketPriceService
{
    public function getCurrentPrice(string $symbol, string $category): ?array
    {
        $symbol = strtoupper(trim($symbol));
        $category = strtolower(trim($category));

        return match ($category) {
            'crypto' => $this->getCryptoPrice($symbol),
            'stock_us' => $this->getStockUsPrice($symbol),
            'stock_idx' => $this->getStockIdxPrice($symbol),
            'commodity' => null,
            default => null,
        };
    }

    protected function getCryptoPrice(string $dbSymbol): ?array
    {
        if (blank(config('services.twelve_data.api_key'))) {
            Log::warning('Twelve Data API key is missing for crypto', [
                'symbol' => $dbSymbol,
            ]);
            return null;
        }

        $resolved = $this->resolveCryptoInstrument($dbSymbol);

        if (!$resolved) {
            Log::warning('Crypto symbol could not be resolved from catalog', [
                'symbol' => $dbSymbol,
            ]);
            return null;
        }

        try {
            $response = Http::baseUrl(config('services.twelve_data.base_url'))
                ->timeout(15)
                ->get('/price', [
                    'symbol' => $resolved['symbol'],
                    'exchange' => $resolved['exchange'],
                    'apikey' => config('services.twelve_data.api_key'),
                ]);

            if (!$response->successful()) {
                Log::warning('Twelve Data crypto price request failed', [
                    'db_symbol' => $dbSymbol,
                    'resolved' => $resolved,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $json = $response->json();

            if ((isset($json['status']) && $json['status'] === 'error') || !isset($json['price'])) {
                Log::warning('Twelve Data crypto invalid response', [
                    'db_symbol' => $dbSymbol,
                    'resolved' => $resolved,
                    'response' => $json,
                ]);
                return null;
            }

            return [
                'price' => (float) $json['price'],
                'currency' => 'USD',
            ];
        } catch (\Throwable $e) {
            Log::error('Twelve Data crypto exception', [
                'db_symbol' => $dbSymbol,
                'resolved' => $resolved ?? null,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function getStockUsPrice(string $dbSymbol): ?array
    {
        if (blank(config('services.twelve_data.api_key'))) {
            Log::warning('Twelve Data API key is missing for stock_us', [
                'symbol' => $dbSymbol,
            ]);
            return null;
        }

        $resolved = $this->resolveStockInstrument($dbSymbol, 'US');

        if (!$resolved) {
            Log::warning('US stock symbol could not be resolved from catalog', [
                'symbol' => $dbSymbol,
            ]);
            return null;
        }

        try {
            $response = Http::baseUrl(config('services.twelve_data.base_url'))
                ->timeout(15)
                ->get('/price', [
                    'symbol' => $resolved['symbol'],
                    'exchange' => $resolved['exchange'],
                    'apikey' => config('services.twelve_data.api_key'),
                ]);

            if (!$response->successful()) {
                Log::warning('Twelve Data stock_us price request failed', [
                    'db_symbol' => $dbSymbol,
                    'resolved' => $resolved,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $json = $response->json();

            if ((isset($json['status']) && $json['status'] === 'error') || !isset($json['price'])) {
                Log::warning('Twelve Data stock_us invalid response', [
                    'db_symbol' => $dbSymbol,
                    'resolved' => $resolved,
                    'response' => $json,
                ]);
                return null;
            }

            return [
                'price' => (float) $json['price'],
                'currency' => 'USD',
            ];
        } catch (\Throwable $e) {
            Log::error('Twelve Data stock_us exception', [
                'db_symbol' => $dbSymbol,
                'resolved' => $resolved ?? null,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function getStockIdxPrice(string $symbol): ?array
    {
        if (blank(config('services.eodhd.api_key'))) {
            Log::warning('EODHD API key is missing for stock_idx', [
                'symbol' => $symbol,
            ]);
            return null;
        }

        $resolvedSymbol = str_contains($symbol, '.') ? strtoupper($symbol) : strtoupper($symbol) . '.JK';

        try {
            $response = Http::baseUrl(config('services.eodhd.base_url'))
                ->timeout(15)
                ->get("/real-time/{$resolvedSymbol}", [
                    'api_token' => config('services.eodhd.api_key'),
                    'fmt' => 'json',
                ]);

            if (!$response->successful()) {
                Log::warning('EODHD stock_idx request failed', [
                    'symbol' => $resolvedSymbol,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $json = $response->json();

            $price = $json['close'] ?? $json['price'] ?? null;

            if ($price === null) {
                Log::warning('EODHD stock_idx invalid response', [
                    'symbol' => $resolvedSymbol,
                    'response' => $json,
                ]);
                return null;
            }

            return [
                'price' => (float) $price,
                'currency' => 'IDR',
            ];
        } catch (\Throwable $e) {
            Log::error('EODHD stock_idx exception', [
                'symbol' => $resolvedSymbol,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function resolveCryptoInstrument(string $dbSymbol): ?array
    {
        return Cache::remember("td_crypto_resolve_{$dbSymbol}", now()->addHours(12), function () use ($dbSymbol) {
            [$base, $quote] = $this->splitCryptoPair($dbSymbol);

            if (!$base || !$quote) {
                return null;
            }

            $catalogSymbol = "{$base}/{$quote}";

            try {
                $response = Http::baseUrl(config('services.twelve_data.base_url'))
                    ->timeout(15)
                    ->get('/cryptocurrencies', [
                        'symbol' => $catalogSymbol,
                        'apikey' => config('services.twelve_data.api_key'),
                    ]);

                if (!$response->successful()) {
                    Log::warning('Twelve Data crypto catalog request failed', [
                        'db_symbol' => $dbSymbol,
                        'catalog_symbol' => $catalogSymbol,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return null;
                }

                $json = $response->json();
                $rows = $json['data'] ?? [];

                if (!is_array($rows) || empty($rows)) {
                    return null;
                }

                $first = collect($rows)->first(function ($row) use ($catalogSymbol) {
                    return strtoupper((string) ($row['symbol'] ?? '')) === strtoupper($catalogSymbol);
                }) ?? $rows[0];

                $availableExchanges = collect($first['available_exchanges'] ?? [])
                    ->map(fn ($item) => (string) $item)
                    ->values();

                $preferredExchange = $availableExchanges->first(function ($exchange) {
                    return strtoupper($exchange) === 'BINANCE';
                }) ?? $availableExchanges->first();

                if (!$preferredExchange) {
                    return null;
                }

                return [
                    'symbol' => (string) ($first['symbol'] ?? $catalogSymbol),
                    'exchange' => $preferredExchange,
                ];
            } catch (\Throwable $e) {
                Log::error('Twelve Data crypto catalog exception', [
                    'db_symbol' => $dbSymbol,
                    'catalog_symbol' => $catalogSymbol,
                    'message' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    protected function resolveStockInstrument(string $dbSymbol, string $countryCode): ?array
    {
        return Cache::remember("td_stock_resolve_{$countryCode}_{$dbSymbol}", now()->addHours(12), function () use ($dbSymbol, $countryCode) {
            try {
                $response = Http::baseUrl(config('services.twelve_data.base_url'))
                    ->timeout(15)
                    ->get('/stocks', [
                        'symbol' => $dbSymbol,
                        'apikey' => config('services.twelve_data.api_key'),
                    ]);

                if (!$response->successful()) {
                    Log::warning('Twelve Data stock catalog request failed', [
                        'db_symbol' => $dbSymbol,
                        'country' => $countryCode,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return null;
                }

                $json = $response->json();
                $rows = collect($json['data'] ?? []);

                if ($rows->isEmpty()) {
                    return null;
                }

                $filtered = $rows->filter(function ($row) use ($dbSymbol, $countryCode) {
                    $symbol = strtoupper((string) ($row['symbol'] ?? ''));
                    $country = strtoupper((string) ($row['country'] ?? ''));

                    if ($symbol !== strtoupper($dbSymbol)) {
                        return false;
                    }

                    if ($countryCode === 'ID') {
                        return str_contains($country, 'INDONESIA') || $country === 'ID';
                    }

                    if ($countryCode === 'US') {
                        return str_contains($country, 'UNITED STATES') || $country === 'US';
                    }

                    return false;
                });

                $picked = $filtered->first() ?? $rows->first();

                if (!$picked) {
                    return null;
                }

                return [
                    'symbol' => (string) ($picked['symbol'] ?? $dbSymbol),
                    'exchange' => (string) ($picked['exchange'] ?? ''),
                ];
            } catch (\Throwable $e) {
                Log::error('Twelve Data stock catalog exception', [
                    'db_symbol' => $dbSymbol,
                    'country' => $countryCode,
                    'message' => $e->getMessage(),
                ]);
                return null;
            }
        });
    }

    protected function splitCryptoPair(string $dbSymbol): array
    {
        $knownQuotes = ['USDT', 'USD', 'BUSD', 'USDC', 'IDR', 'BTC', 'ETH'];

        foreach ($knownQuotes as $quote) {
            if (Str::endsWith($dbSymbol, $quote) && strlen($dbSymbol) > strlen($quote)) {
                $base = substr($dbSymbol, 0, -strlen($quote));
                $normalizedQuote = $quote === 'USDT' ? 'USD' : $quote;

                return [$base, $normalizedQuote];
            }
        }

        return [null, null];
    }
}
