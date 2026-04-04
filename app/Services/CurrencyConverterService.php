<?php

namespace App\Services;

class CurrencyConverterService
{
    public function __construct(
        protected ExchangeRateService $exchangeRateService
    ) {
    }

    public function convert(float $amount, string $from, string $to): float
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === 'USDT') {
            $from = 'USD';
        }

        if ($to === 'USDT') {
            $to = 'USD';
        }

        if ($from === $to) {
            return round($amount, 2);
        }

        $data = $this->exchangeRateService->getRates();
        $rates = $data['rates'] ?? [];

        if ($from !== 'USD') {
            if (!isset($rates[$from])) {
                return round($amount, 2);
            }

            $amount = $amount / (float) $rates[$from];
        }

        if ($to !== 'USD') {
            if (!isset($rates[$to])) {
                return round($amount, 2);
            }

            $amount = $amount * (float) $rates[$to];
        }

        return round($amount, 2);
    }

    public function convertNullable(?float $amount, string $from, string $to): ?float
    {
        if ($amount === null) {
            return null;
        }

        return $this->convert($amount, $from, $to);
    }
}
