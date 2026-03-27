<?php

namespace App\Enums;

class Currency
{
    public const IDR = 'IDR';
    public const USD = 'USD';
    public const USDT = 'USDT';

    public static function all(): array
    {
        return [
            self::IDR,
            self::USD,
            self::USDT,
        ];
    }

    public static function baseCurrencyOptions(): array
    {
        return [
            self::IDR,
            self::USD,
        ];
    }
}
