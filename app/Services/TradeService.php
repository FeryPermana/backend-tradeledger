<?php

namespace App\Services;

class TradeService
{
    protected float $epsilon = 0.00000001;

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
        $closedQuantity = $this->normalizeClosedQuantity($quantity, $closedQuantity);

        if ($closedQuantity <= $this->epsilon && empty($exitDate)) {
            return 'open';
        }

        if ($quantity > 0 && $closedQuantity >= ($quantity - $this->epsilon)) {
            return 'closed';
        }

        if ($closedQuantity > $this->epsilon && $closedQuantity < ($quantity - $this->epsilon)) {
            return 'partial';
        }

        if (! empty($exitDate)) {
            return 'closed';
        }

        return 'open';
    }

    public function normalizeClosedQuantity(float $quantity, float $closedQuantity): float
    {
        if ($closedQuantity < 0) {
            return 0.0;
        }

        if ($closedQuantity > $quantity || abs($closedQuantity - $quantity) < $this->epsilon) {
            return round($quantity, 8);
        }

        return round($closedQuantity, 8);
    }

    public function getRemainingQuantity(float $quantity, float $closedQuantity): float
    {
        $normalizedClosed = $this->normalizeClosedQuantity($quantity, $closedQuantity);
        $remaining = $quantity - $normalizedClosed;

        if (abs($remaining) < $this->epsilon) {
            return 0.0;
        }

        return round(max(0, $remaining), 8);
    }

    public function prepareTradeData(array $data): array
    {
        $positionType = $data['position_type'] ?? null;

        $entryPrice = isset($data['entry_price']) && $data['entry_price'] !== ''
            ? (float) $data['entry_price']
            : 0.0;

        $exitPrice = isset($data['exit_price']) && $data['exit_price'] !== ''
            ? (float) $data['exit_price']
            : null;

        $quantity = isset($data['quantity']) && $data['quantity'] !== ''
            ? (float) $data['quantity']
            : 0.0;

        $closedQuantity = isset($data['closed_quantity']) && $data['closed_quantity'] !== ''
            ? (float) $data['closed_quantity']
            : 0.0;

        $fees = isset($data['fees']) && $data['fees'] !== ''
            ? (float) $data['fees']
            : 0.0;

        $stopLoss = isset($data['stop_loss']) && $data['stop_loss'] !== ''
            ? (float) $data['stop_loss']
            : null;

        $exitDate = $data['exit_date'] ?? null;

        $closedQuantity = $this->normalizeClosedQuantity($quantity, $closedQuantity);

        if ($positionType === 'investment') {
            $data['closed_quantity'] = 0;
            $data['profit_loss'] = null;
            $data['r_multiple'] = null;
            $data['status'] = 'open';
            $data['exit_price'] = null;
            $data['exit_date'] = null;

            return $data;
        }

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
