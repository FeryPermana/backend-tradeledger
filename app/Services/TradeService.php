<?php

namespace App\Services;

class TradeService
{
    public function calculateProfitLoss(
        float $entryPrice,
        ?float $exitPrice,
        float $quantity,
        float $fees = 0
    ): ?float {
        if (is_null($exitPrice) || $quantity <= 0) {
            return null;
        }

        $profitLoss = (($exitPrice - $entryPrice) * $quantity) - $fees;

        return round($profitLoss, 2);
    }

    public function calculateRMultiple(
        float $entryPrice,
        ?float $stopLoss,
        float $quantity,
        ?float $profitLoss
    ): ?float {
        if (is_null($stopLoss) || is_null($profitLoss) || $quantity <= 0) {
            return null;
        }

        $riskAmount = ($entryPrice - $stopLoss) * $quantity;

        if ($riskAmount <= 0) {
            return null;
        }

        return round($profitLoss / $riskAmount, 2);
    }

    public function determineTradeStatus(
        float $quantity,
        float $closedQuantity,
        ?string $exitDate
    ): string {
        if ($closedQuantity <= 0 && empty($exitDate)) {
            return 'open';
        }

        if ($closedQuantity > 0 && $closedQuantity < $quantity) {
            return 'partial';
        }

        if ($closedQuantity >= $quantity) {
            return 'closed';
        }

        if (! empty($exitDate)) {
            return 'closed';
        }

        return 'open';
    }

    public function normalizeClosedQuantity(float $quantity, float $closedQuantity): float
    {
        if ($closedQuantity < 0) {
            return 0;
        }

        if ($closedQuantity > $quantity) {
            return $quantity;
        }

        return $closedQuantity;
    }

    public function getRemainingQuantity(float $quantity, float $closedQuantity): float
    {
        return max(0, $quantity - $this->normalizeClosedQuantity($quantity, $closedQuantity));
    }

    public function prepareTradeData(array $data): array
    {
        $positionType = $data['position_type'] ?? null;

        $entryPrice = (float) ($data['entry_price'] ?? 0);
        $exitPrice = isset($data['exit_price']) && $data['exit_price'] !== ''
            ? (float) $data['exit_price']
            : null;

        $quantity = (float) ($data['quantity'] ?? 0);
        $closedQuantity = isset($data['closed_quantity']) && $data['closed_quantity'] !== ''
            ? (float) $data['closed_quantity']
            : 0;

        $fees = isset($data['fees']) && $data['fees'] !== ''
            ? (float) $data['fees']
            : 0;

        $stopLoss = isset($data['stop_loss']) && $data['stop_loss'] !== ''
            ? (float) $data['stop_loss']
            : null;

        $exitDate = $data['exit_date'] ?? null;

        $closedQuantity = $this->normalizeClosedQuantity($quantity, $closedQuantity);

        /*
        |--------------------------------------------------------------------------
        | INVESTMENT
        |--------------------------------------------------------------------------
        | Investment tidak pakai exit dari trade form.
        | close / partial close ditangani dari portfolio.
        */
        if ($positionType === 'investment') {
            $data['closed_quantity'] = 0;
            $data['profit_loss'] = null;
            $data['r_multiple'] = null;
            $data['status'] = 'open';

            return $data;
        }

        /*
        |--------------------------------------------------------------------------
        | NON-INVESTMENT
        |--------------------------------------------------------------------------
        | profit_loss dan r_multiple di trade utama hanya meaningful
        | kalau ada exit_price. Untuk partial close, quantity pnl memakai
        | closed_quantity total yang tersimpan saat ini.
        */
        $pnlQuantity = $closedQuantity > 0 ? $closedQuantity : $quantity;

        $profitLoss = $this->calculateProfitLoss(
            $entryPrice,
            $exitPrice,
            $pnlQuantity,
            $fees
        );

        $rMultiple = $this->calculateRMultiple(
            $entryPrice,
            $stopLoss,
            $pnlQuantity,
            $profitLoss
        );

        $data['closed_quantity'] = $closedQuantity;
        $data['profit_loss'] = $profitLoss;
        $data['r_multiple'] = $rMultiple;
        $data['status'] = $this->determineTradeStatus($quantity, $closedQuantity, $exitDate);

        return $data;
    }
}
