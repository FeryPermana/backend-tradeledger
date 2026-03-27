<?php

namespace App\Services;

use App\Models\PortfolioPosition;
use App\Models\Trade;
use Illuminate\Support\Facades\DB;

class TradePortfolioSyncService
{
    public function syncFromTrade(Trade $trade): void
    {
        if ($trade->position_type !== 'investment') {
            return;
        }

        DB::transaction(function () use ($trade) {
            $investmentTrades = Trade::query()
                ->where('user_id', $trade->user_id)
                ->where('account_id', $trade->account_id)
                ->where('asset_id', $trade->asset_id)
                ->where('position_type', 'investment')
                ->whereIn('status', ['open', 'partial'])
                ->get();

            $activeTrades = $investmentTrades->filter(function ($item) {
                return $this->getRemainingQuantity($item) > 0;
            });

            if ($activeTrades->isEmpty()) {
                PortfolioPosition::query()
                    ->where('user_id', $trade->user_id)
                    ->where('account_id', $trade->account_id)
                    ->where('asset_id', $trade->asset_id)
                    ->delete();

                return;
            }

            $totalQuantity = (float) $activeTrades->sum(function ($item) {
                return $this->getRemainingQuantity($item);
            });

            /*
            |--------------------------------------------------------------------------
            | BUY COST
            |--------------------------------------------------------------------------
            | Untuk investment:
            | - fee BUY masuk ke avg buy
            | - jadi total cost = (entry_price * qty) + allocated buy fee
            */
            $totalCost = (float) $activeTrades->sum(function ($item) {
                $remainingQuantity = $this->getRemainingQuantity($item);
                $originalQuantity = (float) ($item->quantity ?? 0);
                $buyFee = (float) ($item->fees ?? 0);

                if ($originalQuantity <= 0 || $remainingQuantity <= 0) {
                    return 0;
                }

                /*
                |--------------------------------------------------------------------------
                | Fee allocation
                |--------------------------------------------------------------------------
                | Kalau suatu hari ada trade investment yang qty-nya tidak full lagi,
                | fee buy dialokasikan proporsional ke sisa quantity aktif.
                */
                $allocatedBuyFee = $buyFee * ($remainingQuantity / $originalQuantity);

                return ((float) $item->entry_price * $remainingQuantity) + $allocatedBuyFee;
            });

            /*
            |--------------------------------------------------------------------------
            | TOTAL BUY FEES
            |--------------------------------------------------------------------------
            | Ini hanya fee BUY dari posisi aktif yang tersisa.
            */
            $totalFees = (float) $activeTrades->sum(function ($item) {
                $remainingQuantity = $this->getRemainingQuantity($item);
                $originalQuantity = (float) ($item->quantity ?? 0);
                $buyFee = (float) ($item->fees ?? 0);

                if ($originalQuantity <= 0 || $remainingQuantity <= 0) {
                    return 0;
                }

                return $buyFee * ($remainingQuantity / $originalQuantity);
            });

            $avgPrice = $totalQuantity > 0
                ? $totalCost / $totalQuantity
                : 0;

            PortfolioPosition::updateOrCreate(
                [
                    'user_id' => $trade->user_id,
                    'account_id' => $trade->account_id,
                    'asset_id' => $trade->asset_id,
                ],
                [
                    'source_type' => 'trade_sync',
                    'quantity' => round($totalQuantity, 8),
                    'avg_price' => round($avgPrice, 8),
                    'total_fees' => round($totalFees, 8),
                ]
            );
        });
    }

    protected function getRemainingQuantity(Trade $trade): float
    {
        $quantity = (float) ($trade->quantity ?? 0);
        $closedQuantity = (float) ($trade->closed_quantity ?? 0);

        return max(0, $quantity - $closedQuantity);
    }
}
