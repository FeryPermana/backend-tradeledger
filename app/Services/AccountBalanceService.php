<?php

namespace App\Services;

use App\Models\PortfolioPosition;
use App\Models\Trade;
use App\Models\Account;

class AccountBalanceService
{
    public function getUsedCapital(int $accountId, ?int $excludeTradeId = null, ?int $excludePortfolioId = null): float
    {
        $tradeQuery = Trade::query()
            ->where('account_id', $accountId)
            ->whereNull('exit_date');

        if ($excludeTradeId) {
            $tradeQuery->where('id', '!=', $excludeTradeId);
        }

        $usedByOpenTrades = $tradeQuery->get()->sum(function ($trade) {
            return ((float) $trade->entry_price * (float) $trade->quantity) + (float) ($trade->fees ?? 0);
        });

        $portfolioQuery = PortfolioPosition::query()
            ->where('account_id', $accountId);

        if ($excludePortfolioId) {
            $portfolioQuery->where('id', '!=', $excludePortfolioId);
        }

        $usedByPortfolio = $portfolioQuery->get()->sum(function ($position) {
            return ((float) $position->avg_price * (float) $position->quantity) + (float) ($position->total_fees ?? 0);
        });

        return round($usedByOpenTrades + $usedByPortfolio, 2);
    }

    public function getAvailableBalance(Account $account, ?int $excludeTradeId = null, ?int $excludePortfolioId = null): float
    {
        $initialBalance = (float) $account->initial_balance;
        $usedCapital = $this->getUsedCapital($account->id, $excludeTradeId, $excludePortfolioId);

        return round($initialBalance - $usedCapital, 2);
    }

    public function hasEnoughBalance(
        Account $account,
        float $requiredAmount,
        ?int $excludeTradeId = null,
        ?int $excludePortfolioId = null
    ): bool {
        return $this->getAvailableBalance($account, $excludeTradeId, $excludePortfolioId) >= $requiredAmount;
    }
}
