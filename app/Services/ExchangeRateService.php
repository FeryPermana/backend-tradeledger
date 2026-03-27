<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ExchangeRateService
{
    protected string $endpoint = 'https://api.exchangerate-api.com/v4/latest/USD';

    public function getRates(): array
    {
        return Cache::remember('exchange_rates_usd_v4', now()->addHours(6), function () {
            $response = Http::timeout(10)->get($this->endpoint);

            if (! $response->successful()) {
                throw new \RuntimeException('Failed to fetch exchange rates.');
            }

            $data = $response->json();

            if (! isset($data['rates']) || ! is_array($data['rates'])) {
                throw new \RuntimeException('Invalid exchange rates response.');
            }

            return $data;
        });
    }

    public function getRate(string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return 1.0;
        }

        // MVP: USDT dianggap sama dengan USD
        if ($from === 'USDT') {
            $from = 'USD';
        }

        if ($to === 'USDT') {
            $to = 'USD';
        }

        $data = $this->getRates();
        $rates = $data['rates'];

        if ($from === 'USD') {
            if (! isset($rates[$to])) {
                throw new \RuntimeException("Rate USD to {$to} not found.");
            }

            return (float) $rates[$to];
        }

        if ($to === 'USD') {
            if (! isset($rates[$from])) {
                throw new \RuntimeException("Rate {$from} to USD not found.");
            }

            return 1 / (float) $rates[$from];
        }

        if (! isset($rates[$from], $rates[$to])) {
            throw new \RuntimeException("Rate {$from} or {$to} not found.");
        }

        return (1 / (float) $rates[$from]) * (float) $rates[$to];
    }
}
