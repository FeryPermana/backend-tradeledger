<?php

namespace App\Services;

use App\Models\Account;
use App\Models\PortfolioPosition;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AnalyticsService
{
    public function __construct(
        protected CurrencyConverterService $converter
    ) {
    }

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

        return $currency !== '' ? $currency : 'IDR';
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

    protected function toArrayValue($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, fn ($item) => $item !== null && $item !== ''));
        }

        if ($value === null || $value === '') {
            return [];
        }

        return [$value];
    }

    protected function applyTradeFilters(Builder $query, array $filters = []): Builder
    {
        $accountIds = $this->toArrayValue($filters['account_id'] ?? null);
        if (!empty($accountIds)) {
            $query->whereIn('account_id', $accountIds);
        }

        $assetIds = $this->toArrayValue($filters['asset_id'] ?? null);
        if (!empty($assetIds)) {
            $query->whereIn('asset_id', $assetIds);
        }

        $strategyIds = $this->toArrayValue($filters['strategy_id'] ?? null);
        if (!empty($strategyIds)) {
            $query->whereIn('strategy_id', $strategyIds);
        }

        $tagIds = $this->toArrayValue($filters['tag_id'] ?? null);
        if (!empty($tagIds)) {
            $query->whereHas('tags', function ($q) use ($tagIds) {
                $q->whereIn('tags.id', $tagIds);
            });
        }

        $categories = $this->toArrayValue($filters['category'] ?? null);
        if (!empty($categories)) {
            $query->whereHas('asset', function ($q) use ($categories) {
                $q->whereIn('category', $categories);
            });
        }

        $positionTypes = $this->toArrayValue($filters['position_type'] ?? null);
        if (!empty($positionTypes)) {
            if (count($positionTypes) === 1 && $positionTypes[0] === 'no_investment') {
                $query->where('position_type', '!=', 'investment');
            } else {
                $query->whereIn('position_type', $positionTypes);
            }
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('entry_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('entry_date', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);

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

        return $query;
    }

    protected function normalizeTrades(Collection $trades, string $baseCurrency): Collection
    {
        return $trades->map(function (Trade $trade) use ($baseCurrency) {
            return [
                'trade_id' => $trade->id,
                'asset_id' => $trade->asset_id,
                'asset_symbol' => $trade->asset?->symbol ?? '-',
                'asset_name' => $trade->asset?->name ?? '-',
                'category' => $trade->asset?->category,
                'strategy_id' => $trade->strategy_id,
                'strategy_name' => $trade->strategy?->name ?? 'No Strategy',
                'profit_loss' => $this->convertTradeProfitLossToBase($trade, $baseCurrency),
                'entry_date' => $trade->entry_date,
                'exit_date' => $trade->exit_date,
            ];
        });
    }

    protected function sumPositiveProfitLoss(iterable $items, string $key = 'profit_loss'): float
    {
        $sum = 0;

        foreach ($items as $item) {
            $value = is_array($item)
                ? (float) ($item[$key] ?? 0)
                : (float) ($item->{$key} ?? 0);

            if ($value > 0) {
                $sum += $value;
            }
        }

        return (float) $sum;
    }

    protected function sumNegativeProfitLossAbs(iterable $items, string $key = 'profit_loss'): float
    {
        $sum = 0;

        foreach ($items as $item) {
            $value = is_array($item)
                ? (float) ($item[$key] ?? 0)
                : (float) ($item->{$key} ?? 0);

            if ($value < 0) {
                $sum += abs($value);
            }
        }

        return (float) $sum;
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

    protected function makePerformancePayload(Collection $items, string $baseCurrency): array
    {
        $totalTrades = $items->count();

        $winningTrades = $items->filter(fn ($item) => (float) ($item['profit_loss'] ?? 0) > 0)->count();
        $losingTrades = $items->filter(fn ($item) => (float) ($item['profit_loss'] ?? 0) < 0)->count();

        $grossProfit = $this->sumPositiveProfitLoss($items);
        $grossLoss = $this->sumNegativeProfitLossAbs($items);
        $netProfit = (float) $items->sum(fn ($item) => (float) ($item['profit_loss'] ?? 0));

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

    public function getSummary(int $userId, array $filters = []): array
    {
        $baseCurrency = $this->getBaseCurrency($userId);

        $query = Trade::query()
            ->with(['account', 'asset', 'strategy', 'tags'])
            ->where('user_id', $userId)
            ->whereNotNull('profit_loss');

        $this->applyTradeFilters($query, $filters);

        $normalizedTrades = $this->normalizeTrades($query->get(), $baseCurrency);

        return $this->makePerformancePayload($normalizedTrades, $baseCurrency);
    }

    public function getStrategyPerformance(int $userId, array $filters = []): array
    {
        $baseCurrency = $this->getBaseCurrency($userId);

        $query = Trade::query()
            ->with(['strategy', 'account', 'asset', 'tags'])
            ->where('user_id', $userId)
            ->whereNotNull('profit_loss');

        $this->applyTradeFilters($query, $filters);

        $normalizedTrades = $this->normalizeTrades($query->get(), $baseCurrency);

        return $normalizedTrades
            ->groupBy('strategy_id')
            ->map(function (Collection $group) use ($baseCurrency) {
                $payload = $this->makePerformancePayload($group, $baseCurrency);

                return [
                    'strategy_id' => $group->first()['strategy_id'],
                    'strategy_name' => $group->first()['strategy_name'],
                    ...$payload,
                ];
            })
            ->sortByDesc('net_profit')
            ->values()
            ->toArray();
    }

    public function getTagPerformance(int $userId, array $filters = []): array
    {
        $baseCurrency = $this->getBaseCurrency($userId);

        $query = Trade::query()
            ->with(['tags', 'account', 'asset', 'strategy'])
            ->where('user_id', $userId)
            ->whereNotNull('profit_loss');

        $this->applyTradeFilters($query, $filters);

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
                        'rows' => [],
                    ];
                }

                $tagMap[$tagId]['rows'][] = [
                    'profit_loss' => $profitLoss,
                ];
            }
        }

        return collect($tagMap)
            ->map(function (array $item) use ($baseCurrency) {
                $rows = collect($item['rows']);
                $payload = $this->makePerformancePayload($rows, $baseCurrency);

                return [
                    'tag_id' => $item['tag_id'],
                    'tag_name' => $item['tag_name'],
                    ...$payload,
                ];
            })
            ->sortByDesc('net_profit')
            ->values()
            ->toArray();
    }

    public function getMonthlyPerformance(int $userId, array $filters = []): array
    {
        $baseCurrency = $this->getBaseCurrency($userId);

        $query = Trade::query()
            ->with(['account', 'asset', 'strategy', 'tags'])
            ->where('user_id', $userId)
            ->whereNotNull('profit_loss')
            ->whereNotNull('exit_date')
            ->orderBy('exit_date');

        $this->applyTradeFilters($query, $filters);

        $normalizedTrades = $this->normalizeTrades($query->get(), $baseCurrency);

        return $normalizedTrades
            ->groupBy(function (array $trade) {
                return $trade['exit_date']
                    ? $trade['exit_date']->format('Y-m')
                    : 'unknown';
            })
            ->map(function (Collection $group, string $month) use ($baseCurrency) {
                $payload = $this->makePerformancePayload($group, $baseCurrency);

                return [
                    'month' => $month,
                    ...$payload,
                ];
            })
            ->values()
            ->toArray();
    }

    public function getAssetPerformance(int $userId, array $filters = []): array
    {
        $baseCurrency = $this->getBaseCurrency($userId);

        $query = Trade::query()
            ->with(['asset', 'account', 'strategy', 'tags'])
            ->where('user_id', $userId)
            ->whereNotNull('profit_loss');

        $this->applyTradeFilters($query, $filters);

        $normalizedTrades = $this->normalizeTrades($query->get(), $baseCurrency);

        return $normalizedTrades
            ->groupBy('asset_id')
            ->map(function (Collection $group) use ($baseCurrency) {
                $first = $group->first();
                $payload = $this->makePerformancePayload($group, $baseCurrency);

                return [
                    'asset_id' => $first['asset_id'],
                    'asset_symbol' => $first['asset_symbol'],
                    'asset_name' => $first['asset_name'],
                    'category' => $first['category'],
                    ...$payload,
                ];
            })
            ->sortByDesc('net_profit')
            ->values()
            ->toArray();
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

        $grouped = $positions
            ->map(function ($position) use ($baseCurrency) {
                $fromCurrency = strtoupper(trim((string) ($position->account?->currency ?? 'IDR')));
                $rawValue = (float) $position->quantity * (float) $position->avg_price;

                return [
                    'asset' => $position->asset?->symbol ?? '-',
                    'value' => $this->convertMoney($rawValue, $fromCurrency, $baseCurrency),
                ];
            })
            ->groupBy('asset')
            ->map(function (Collection $items, string $asset) {
                return [
                    'asset' => $asset,
                    'value' => (float) $items->sum('value'),
                ];
            })
            ->values();

        $total = (float) $grouped->sum('value');

        return $grouped
            ->map(function (array $item) use ($total, $baseCurrency) {
                return [
                    'asset' => $item['asset'],
                    'value' => round((float) $item['value'], 2),
                    'percentage' => $total > 0
                        ? round(((float) $item['value'] / $total) * 100, 2)
                        : 0,
                    'display_currency' => $baseCurrency,
                ];
            })
            ->sortByDesc('value')
            ->values()
            ->toArray();
    }
}
