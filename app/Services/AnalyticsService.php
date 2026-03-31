<?php

namespace App\Services;

use App\Models\Account;
use App\Models\PortfolioPosition;
use App\Models\Trade;
use App\Models\User;

class AnalyticsService
{
    public function __construct(
        protected CurrencyConverterService $converter
    ) {}

    protected function getBaseCurrency(int $userId): string
    {
        return strtoupper(
            User::query()->where('id', $userId)->value('base_currency') ?? 'IDR'
        );
    }

    protected function getTradeCurrency(Trade $trade): string
    {
        $currency = strtoupper(trim((string) ($trade->account?->currency ?? '')));

        if ($currency !== '') {
            return $currency;
        }

        $currency = Account::query()
            ->where('id', $trade->account_id)
            ->value('currency');

        $currency = strtoupper(trim((string) $currency));

        if ($currency !== '') {
            return $currency;
        }

        return 'IDR';
    }

    protected function convertTradeProfitLossToBase(Trade $trade, string $baseCurrency): float
    {
        $fromCurrency = $this->getTradeCurrency($trade);

        return (float) $this->converter->convert(
            (float) ($trade->profit_loss ?? 0),
            $fromCurrency,
            $baseCurrency
        );
    }

    protected function convertMoney(float $amount, string $fromCurrency, string $baseCurrency): float
    {
        return (float) $this->converter->convert(
            $amount,
            strtoupper(trim($fromCurrency ?: 'IDR')),
            strtoupper(trim($baseCurrency ?: 'IDR'))
        );
    }

    protected function sumPositiveProfitLoss($items, string $key = 'profit_loss'): float
    {
        return (float) $items->sum(function ($item) use ($key) {
            $value = is_array($item)
                ? (float) ($item[$key] ?? 0)
                : (float) ($item->{$key} ?? 0);

            return $value > 0 ? $value : 0;
        });
    }

    protected function sumNegativeProfitLossAbs($items, string $key = 'profit_loss'): float
    {
        $lossSum = (float) $items->sum(function ($item) use ($key) {
            $value = is_array($item)
                ? (float) ($item[$key] ?? 0)
                : (float) ($item->{$key} ?? 0);

            return $value < 0 ? $value : 0;
        });

        return (float) abs($lossSum);
    }

    protected function calculateProfitFactor(float $grossProfit, float $grossLoss): ?float
    {
        if ($grossLoss > 0) {
            return $grossProfit / $grossLoss;
        }

        if ($grossProfit > 0) {
            return null;
        }

        return 0.0;
    }

    protected function roundProfitFactor(?float $profitFactor): ?float
    {
        return is_null($profitFactor) ? null : round($profitFactor, 2);
    }

    public function getSummary(int $userId, array $filters = []): array
    {
        $baseCurrency = $this->getBaseCurrency($userId);

        $query = Trade::query()
            ->with(['account', 'asset', 'strategy', 'tags'])
            ->where('user_id', $userId)
            ->whereNotNull('profit_loss');

        if (!empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        if (!empty($filters['asset_id'])) {
            $query->where('asset_id', $filters['asset_id']);
        }

        if (!empty($filters['strategy_id'])) {
            $query->where('strategy_id', $filters['strategy_id']);
        }

        if (!empty($filters['position_type'])) {
            $query->where('position_type', $filters['position_type']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('entry_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('entry_date', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', "%{$search}%")
                    ->orWhereHas('asset', function ($assetQuery) use ($search) {
                        $assetQuery->where('symbol', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('strategy', function ($strategyQuery) use ($search) {
                        $strategyQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('tags', function ($tagQuery) use ($search) {
                        $tagQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $trades = $query->get();

        $normalizedTrades = $trades->map(function (Trade $trade) use ($baseCurrency) {
            return [
                'profit_loss' => $this->convertTradeProfitLossToBase($trade, $baseCurrency),
            ];
        });

        $totalTrades = $normalizedTrades->count();

        $winningTrades = $normalizedTrades->filter(function (array $trade) {
            return (float) $trade['profit_loss'] > 0;
        })->count();

        $losingTrades = $normalizedTrades->filter(function (array $trade) {
            return (float) $trade['profit_loss'] < 0;
        })->count();

        $grossProfit = $this->sumPositiveProfitLoss($normalizedTrades);
        $grossLoss = $this->sumNegativeProfitLossAbs($normalizedTrades);
        $netProfit = (float) $normalizedTrades->sum(function (array $trade) {
            return (float) ($trade['profit_loss'] ?? 0);
        });

        $averageWin = $winningTrades > 0 ? $grossProfit / $winningTrades : 0;
        $averageLoss = $losingTrades > 0 ? $grossLoss / $losingTrades : 0;
        $winRate = $totalTrades > 0 ? ($winningTrades / $totalTrades) * 100 : 0;
        $profitFactor = $this->calculateProfitFactor($grossProfit, $grossLoss);

        return [
            'total_trades' => $totalTrades,
            'winning_trades' => $winningTrades,
            'losing_trades' => $losingTrades,
            'win_rate' => round($winRate, 2),
            'net_profit' => round($netProfit, 2),
            'gross_profit' => round($grossProfit, 2),
            'gross_loss' => round($grossLoss, 2),
            'average_win' => round($averageWin, 2),
            'average_loss' => round($averageLoss, 2),
            'profit_factor' => $this->roundProfitFactor($profitFactor),
            'display_currency' => $baseCurrency,
        ];
    }

    public function getTagPerformance(int $userId, array $filters = []): array
    {
        $baseCurrency = $this->getBaseCurrency($userId);

        $query = Trade::query()
            ->with(['tags', 'account'])
            ->where('user_id', $userId)
            ->whereNotNull('profit_loss');

        if (!empty($filters['date_from'])) {
            $query->whereDate('entry_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('entry_date', '<=', $filters['date_to']);
        }

        $trades = $query->get();

        $tagMap = [];

        foreach ($trades as $trade) {
            $profitLoss = $this->convertTradeProfitLossToBase($trade, $baseCurrency);

            foreach ($trade->tags as $tag) {
                $tagId = $tag->id;

                if (!isset($tagMap[$tagId])) {
                    $tagMap[$tagId] = [
                        'tag_id' => $tag->id,
                        'tag_name' => $tag->name,
                        'total_trades' => 0,
                        'winning_trades' => 0,
                        'losing_trades' => 0,
                        'net_profit' => 0.0,
                        'gross_profit' => 0.0,
                        'gross_loss' => 0.0,
                    ];
                }

                $tagMap[$tagId]['total_trades']++;
                $tagMap[$tagId]['net_profit'] += $profitLoss;

                if ($profitLoss > 0) {
                    $tagMap[$tagId]['winning_trades']++;
                    $tagMap[$tagId]['gross_profit'] += $profitLoss;
                }

                if ($profitLoss < 0) {
                    $tagMap[$tagId]['losing_trades']++;
                    $tagMap[$tagId]['gross_loss'] += abs($profitLoss);
                }
            }
        }

        return collect($tagMap)->map(function (array $item) use ($baseCurrency) {
            $totalTrades = (int) $item['total_trades'];
            $grossProfit = (float) $item['gross_profit'];
            $grossLoss = (float) $item['gross_loss'];
            $netProfit = (float) $item['net_profit'];

            $winRate = $totalTrades > 0
                ? ($item['winning_trades'] / $totalTrades) * 100
                : 0;

            $profitFactor = $this->calculateProfitFactor($grossProfit, $grossLoss);

            return [
                'tag_id' => $item['tag_id'],
                'tag_name' => $item['tag_name'],
                'total_trades' => $totalTrades,
                'winning_trades' => (int) $item['winning_trades'],
                'losing_trades' => (int) $item['losing_trades'],
                'win_rate' => round($winRate, 2),
                'net_profit' => round($netProfit, 2),
                'gross_profit' => round($grossProfit, 2),
                'gross_loss' => round($grossLoss, 2),
                'profit_factor' => $this->roundProfitFactor($profitFactor),
                'display_currency' => $baseCurrency,
            ];
        })->sortByDesc('net_profit')->values()->toArray();
    }

    public function getStrategyPerformance(int $userId, array $filters = []): array
    {
        $baseCurrency = $this->getBaseCurrency($userId);

        $query = Trade::query()
            ->with(['strategy', 'account'])
            ->where('user_id', $userId)
            ->whereNotNull('profit_loss');

            if (!empty($filters['date_from'])) {
                $query->whereDate('entry_date', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->whereDate('entry_date', '<=', $filters['date_to']);
            }

        $trades = $query->get()
                ->groupBy('strategy_id');

        $results = [];

        foreach ($trades as $strategyId => $group) {
            $normalized = $group->map(function (Trade $trade) use ($baseCurrency) {
                return [
                    'profit_loss' => $this->convertTradeProfitLossToBase($trade, $baseCurrency),
                ];
            });

            $totalTrades = $normalized->count();

            $winningTrades = $normalized->filter(function (array $trade) {
                return (float) $trade['profit_loss'] > 0;
            })->count();

            $losingTrades = $normalized->filter(function (array $trade) {
                return (float) $trade['profit_loss'] < 0;
            })->count();

            $grossProfit = $this->sumPositiveProfitLoss($normalized);
            $grossLoss = $this->sumNegativeProfitLossAbs($normalized);
            $netProfit = (float) $normalized->sum(function (array $trade) {
                return (float) ($trade['profit_loss'] ?? 0);
            });

            $winRate = $totalTrades > 0 ? ($winningTrades / $totalTrades) * 100 : 0;
            $profitFactor = $this->calculateProfitFactor($grossProfit, $grossLoss);

            $strategyName = $group->first()?->strategy?->name ?? 'No Strategy';

            $results[] = [
                'strategy_id' => $strategyId,
                'strategy_name' => $strategyName,
                'total_trades' => $totalTrades,
                'winning_trades' => $winningTrades,
                'losing_trades' => $losingTrades,
                'win_rate' => round($winRate, 2),
                'net_profit' => round($netProfit, 2),
                'gross_profit' => round($grossProfit, 2),
                'gross_loss' => round($grossLoss, 2),
                'profit_factor' => $this->roundProfitFactor($profitFactor),
                'display_currency' => $baseCurrency,
            ];
        }

        usort($results, function ($a, $b) {
            return $b['net_profit'] <=> $a['net_profit'];
        });

        return $results;
    }

    public function getMonthlyPerformance(int $userId, array $filters = []): array
    {
        $baseCurrency = $this->getBaseCurrency($userId);

        $query = Trade::query()
            ->with('account')
            ->where('user_id', $userId)
            ->whereNotNull('profit_loss')
            ->whereNotNull('exit_date')
            ->orderBy('exit_date');

        if (!empty($filters['date_from'])) {
            $query->whereDate('entry_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('entry_date', '<=', $filters['date_to']);
        }

        $trades = $query->get()
                ->map(function (Trade $trade) use ($baseCurrency) {
                    $trade->profit_loss = $this->convertTradeProfitLossToBase($trade, $baseCurrency);
                    return $trade;
                })
                ->groupBy(function (Trade $trade) {
                    return $trade->exit_date->format('Y-m');
                });

        $results = [];

        foreach ($trades as $month => $group) {
            $totalTrades = $group->count();

            $winningTrades = $group->filter(function (Trade $trade) {
                return (float) $trade->profit_loss > 0;
            })->count();

            $losingTrades = $group->filter(function (Trade $trade) {
                return (float) $trade->profit_loss < 0;
            })->count();

            $grossProfit = $this->sumPositiveProfitLoss($group);
            $grossLoss = $this->sumNegativeProfitLossAbs($group);
            $netProfit = (float) $group->sum(function (Trade $trade) {
                return (float) ($trade->profit_loss ?? 0);
            });

            $winRate = $totalTrades > 0 ? ($winningTrades / $totalTrades) * 100 : 0;
            $profitFactor = $this->calculateProfitFactor($grossProfit, $grossLoss);

            $results[] = [
                'month' => $month,
                'total_trades' => $totalTrades,
                'winning_trades' => $winningTrades,
                'losing_trades' => $losingTrades,
                'win_rate' => round($winRate, 2),
                'net_profit' => round($netProfit, 2),
                'gross_profit' => round($grossProfit, 2),
                'gross_loss' => round($grossLoss, 2),
                'profit_factor' => $this->roundProfitFactor($profitFactor),
                'display_currency' => $baseCurrency,
            ];
        }

        return array_values($results);
    }

    public function getPortfolioSummary(int $userId): array
    {
        $baseCurrency = $this->getBaseCurrency($userId);

        $positions = PortfolioPosition::query()
            ->with(['asset', 'account'])
            ->where('user_id', $userId)
            ->get();

        $normalized = $positions->map(function ($position) use ($baseCurrency) {
            $fromCurrency = strtoupper(trim((string) ($position->account?->currency ?? 'IDR')));
            $investedValue = (float) $position->quantity * (float) $position->avg_price;

            return [
                'position' => $position,
                'quantity' => (float) $position->quantity,
                'invested_value' => $this->convertMoney($investedValue, $fromCurrency, $baseCurrency),
            ];
        });

        $totalPositions = $positions->count();
        $totalQuantity = (float) $positions->sum('quantity');
        $totalInvested = (float) $normalized->sum('invested_value');

        $largest = $normalized->sortByDesc('invested_value')->first();

        return [
            'total_positions' => $totalPositions,
            'total_quantity' => round($totalQuantity, 8),
            'total_invested' => round($totalInvested, 2),
            'largest_position' => $largest ? [
                'asset' => $largest['position']->asset?->symbol ?? '-',
                'quantity' => (float) $largest['position']->quantity,
                'invested_value' => round((float) $largest['invested_value'], 2),
            ] : null,
            'display_currency' => $baseCurrency,
        ];
    }

    public function getAssetAllocation(int $userId): array
    {
        $baseCurrency = $this->getBaseCurrency($userId);

        $positions = PortfolioPosition::query()
            ->with(['asset', 'account'])
            ->where('user_id', $userId)
            ->get();

        $values = $positions->map(function ($position) use ($baseCurrency) {
            $fromCurrency = strtoupper(trim((string) ($position->account?->currency ?? 'IDR')));
            $rawValue = (float) $position->quantity * (float) $position->avg_price;

            return [
                'asset' => $position->asset?->symbol ?? '-',
                'value' => $this->convertMoney($rawValue, $fromCurrency, $baseCurrency),
            ];
        });

        $total = (float) $values->sum('value');

        return $values->map(function (array $item) use ($total, $baseCurrency) {
            return [
                'asset' => $item['asset'],
                'value' => round((float) $item['value'], 2),
                'percentage' => $total > 0
                    ? round(((float) $item['value'] / $total) * 100, 2)
                    : 0,
                'display_currency' => $baseCurrency,
            ];
        })->values()->toArray();
    }
}
