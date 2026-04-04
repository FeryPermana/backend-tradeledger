<?php

namespace App\Services;

use App\Models\PortfolioPosition;
use App\Models\User;

class PortfolioService
{
    public function __construct(
        protected CurrencyConverterService $converter
    ) {
    }

    protected function getAccountCurrency(PortfolioPosition $position): string
    {
        return strtoupper($position->account?->currency ?? 'IDR');
    }

    public function getCurrentPrice(PortfolioPosition $position, string $targetCurrency): array
    {
        $accountCurrency = $this->getAccountCurrency($position);

        $price = $position->current_price !== null
            ? (float) $position->current_price
            : (float) $position->avg_price;

        $source = $position->current_price !== null
            ? 'portfolio_position_price'
            : 'fallback_avg_price';

        $convertedPrice = $this->converter->convert(
            $price,
            $accountCurrency,
            strtoupper($targetCurrency)
        );

        return [
            'price' => (float) $convertedPrice,
            'source' => $source,
            'last_updated_at' => optional($position->last_price_updated_at)?->toDateTimeString(),
        ];
    }

    public function getPositionMetrics(PortfolioPosition $position, string $targetCurrency): array
    {
        $targetCurrency = strtoupper($targetCurrency);
        $accountCurrency = $this->getAccountCurrency($position);

        $quantity = (float) $position->quantity;
        $avgPrice = (float) $position->avg_price;
        $fees = (float) ($position->total_fees ?? 0);

        $priceData = $this->getCurrentPrice($position, $targetCurrency);
        $currentPrice = (float) $priceData['price'];

        $avgPriceDisplay = $this->converter->convert(
            $avgPrice,
            $accountCurrency,
            $targetCurrency
        );

        $feesDisplay = $this->converter->convert(
            $fees,
            $accountCurrency,
            $targetCurrency
        );

        $investedValueRaw = ($quantity * $avgPrice) + $fees;

        $investedValue = $this->converter->convert(
            $investedValueRaw,
            $accountCurrency,
            $targetCurrency
        );

        $currentValue = $quantity * $currentPrice;
        $unrealizedPnl = $currentValue - $investedValue;

        $unrealizedPnlPercent = $investedValue > 0
            ? ($unrealizedPnl / $investedValue) * 100
            : 0;

        return [
            'avg_price_display' => round((float) $avgPriceDisplay, 2),
            'total_fees_display' => round((float) $feesDisplay, 2),
            'current_price' => round($currentPrice, 2),
            'current_price_source' => $priceData['source'],
            'price_last_updated_at' => $priceData['last_updated_at'],
            'invested_value' => round((float) $investedValue, 2),
            'current_value' => round((float) $currentValue, 2),
            'unrealized_pnl' => round((float) $unrealizedPnl, 2),
            'unrealized_pnl_percent' => round((float) $unrealizedPnlPercent, 2),
            'display_currency' => $targetCurrency,
        ];
    }

    protected function baseQuery(User $user, array $filters = [])
    {
        $query = PortfolioPosition::query()
            ->with(['asset', 'account'])
            ->where('user_id', $user->id);

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);

            $query->whereHas('asset', function ($q) use ($search) {
                $q->where('symbol', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%');
            });
        }

        if (!empty($filters['conviction_level'])) {
            $query->where('conviction_level', $filters['conviction_level']);
        }

        if (!empty($filters['horizon'])) {
            $query->where('horizon', $filters['horizon']);
        }

        if (!empty($filters['account_id'])) {
            $query->where('account_id', $filters['account_id']);
        }

        if (!empty($filters['asset_id'])) {
            $query->where('asset_id', $filters['asset_id']);
        }

        if (!empty($filters['category'])) {
            $query->whereHas('asset', function ($q) use ($filters) {
                $q->where('category', $filters['category']);
            });
        }

        return $query;
    }

    public function getSummary(User $user, array $filters = []): array
    {
        $baseCurrency = strtoupper($user->base_currency ?? 'IDR');
        $positions = $this->baseQuery($user, $filters)->get();

        $totalInvested = 0;
        $totalValue = 0;
        $totalPnl = 0;

        foreach ($positions as $position) {
            $metrics = $this->getPositionMetrics($position, $baseCurrency);
            $totalInvested += (float) $metrics['invested_value'];
            $totalValue += (float) $metrics['current_value'];
            $totalPnl += (float) $metrics['unrealized_pnl'];
        }

        $pnlPercent = $totalInvested > 0
            ? ($totalPnl / $totalInvested) * 100
            : 0;

        return [
            'total_positions' => $positions->count(),
            'total_invested' => round($totalInvested, 2),
            'total_value' => round($totalValue, 2),
            'pnl' => round($totalPnl, 2),
            'pnl_percent' => round($pnlPercent, 2),
            'display_currency' => $baseCurrency,
            'auto_price_sync_enabled' => (bool) $user->price_sync_enabled,
            'auto_price_sync_times' => $user->price_sync_times ?? [],
            'last_price_sync_at' => optional($user->last_price_sync_at)?->toDateTimeString(),
        ];
    }

    public function getAllocation(User $user, array $filters = []): array
    {
        $baseCurrency = strtoupper($user->base_currency ?? 'IDR');
        $positions = $this->baseQuery($user, $filters)->get();

        $assetRows = [];
        $categoryRows = [];
        $totalValue = 0;

        foreach ($positions as $position) {
            $metrics = $this->getPositionMetrics($position, $baseCurrency);
            $value = (float) $metrics['current_value'];

            $symbol = $position->asset?->symbol ?? 'Unknown';
            $category = $position->asset?->category ?? 'unknown';

            if (!isset($assetRows[$symbol])) {
                $assetRows[$symbol] = [
                    'label' => $symbol,
                    'value' => 0,
                ];
            }

            if (!isset($categoryRows[$category])) {
                $categoryRows[$category] = [
                    'label' => $category,
                    'value' => 0,
                ];
            }

            $assetRows[$symbol]['value'] += $value;
            $categoryRows[$category]['value'] += $value;
            $totalValue += $value;
        }

        $mapFunc = function ($row) use ($totalValue) {
            $row['value'] = round((float) $row['value'], 2);
            $row['percentage'] = $totalValue > 0
                ? round(($row['value'] / $totalValue) * 100, 2)
                : 0;

            return $row;
        };

        $assetRows = array_values(array_map($mapFunc, $assetRows));
        $categoryRows = array_values(array_map($mapFunc, $categoryRows));

        usort($assetRows, fn ($a, $b) => $b['value'] <=> $a['value']);
        usort($categoryRows, fn ($a, $b) => $b['value'] <=> $a['value']);

        return [
            'display_currency' => $baseCurrency,
            'total_value' => round($totalValue, 2),
            'by_asset' => $assetRows,
            'by_category' => $categoryRows,
        ];
    }

    public function getPositions(User $user, array $filters = []): array
    {
        $baseCurrency = strtoupper($user->base_currency ?? 'IDR');
        $positions = $this->baseQuery($user, $filters)->latest()->get();

        return $positions->map(function (PortfolioPosition $position) use ($baseCurrency) {
            $metrics = $this->getPositionMetrics($position, $baseCurrency);

            return [
                'id' => $position->id,
                'asset_id' => $position->asset_id,
                'asset_symbol' => $position->asset?->symbol,
                'asset_name' => $position->asset?->name,
                'asset_category' => $position->asset?->category,
                'account_id' => $position->account_id,
                'account_name' => $position->account?->name,
                'account_currency' => strtoupper($position->account?->currency ?? 'IDR'),
                'quantity' => (float) $position->quantity,
                'avg_price' => (float) $position->avg_price,
                'target_price' => $position->target_price !== null ? (float) $position->target_price : null,
                'horizon' => $position->horizon,
                'conviction_level' => $position->conviction_level,
                'thesis' => $position->thesis,
                'notes' => $position->notes,
                ...$metrics,
                'created_at' => optional($position->created_at)?->toDateTimeString(),
                'updated_at' => optional($position->updated_at)?->toDateTimeString(),
            ];
        })->toArray();
    }
}
