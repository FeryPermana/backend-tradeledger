<?php

namespace App\Services;

use App\Models\PortfolioPosition;
use App\Models\User;

class PortfolioService
{
    public function __construct(
        protected CurrencyConverterService $converter
    ) {}

    public function getCurrentPrice(PortfolioPosition $position, string $targetCurrency): array
    {
        $asset = $position->asset;
        $accountCurrency = strtoupper($position->account?->currency ?? 'IDR');

        $price = (float) ($asset?->current_price ?? $position->avg_price);
        $source = $asset?->current_price !== null ? 'manual_asset_price' : 'fallback';

        $convertedPrice = $this->converter->convert(
            $price,
            $accountCurrency,
            $targetCurrency
        );

        return [
            'price' => (float) $convertedPrice,
            'source' => $source,
            'last_updated_at' => optional($asset?->price_updated_at)?->toDateTimeString(),
        ];
    }

    public function getPositionMetrics(PortfolioPosition $position, string $targetCurrency): array
    {
        $quantity = (float) $position->quantity;
        $avgPrice = (float) $position->avg_price;
        $fees = (float) ($position->total_fees ?? 0);
        $accountCurrency = strtoupper($position->account?->currency ?? 'IDR');

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

    public function getSummary(User $user, array $filters = []): array
    {
        $baseCurrency = strtoupper($user->base_currency ?? 'IDR');

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

        $positions = $query->get();
        $totalInvested = 0;
        $totalValue = 0;
        $totalPnl = 0;

        foreach ($positions as $position) {
            $metrics = $this->getPositionMetrics($position, $baseCurrency);
            $totalInvested += $metrics['invested_value'];
            $totalValue += $metrics['current_value'];
            $totalPnl += $metrics['unrealized_pnl'];
        }

        $pnlPercent = $totalInvested > 0 ? ($totalPnl / $totalInvested) * 100 : 0;

        return [
            'total_positions' => $positions->count(),
            'total_invested' => round($totalInvested, 2),
            'total_value' => round($totalValue, 2),
            'pnl' => round($totalPnl, 2),
            'pnl_percent' => round($pnlPercent, 2),
            'display_currency' => $baseCurrency,
        ];
    }

    public function getAllocation(User $user, array $filters = []): array
    {
        $baseCurrency = strtoupper($user->base_currency ?? 'IDR');

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

        $positions = $query->get();
        $assetRows = [];
        $categoryRows = [];
        $totalValue = 0;

        foreach ($positions as $position) {
            $metrics = $this->getPositionMetrics($position, $baseCurrency);
            $value = (float) $metrics['current_value'];

            $symbol = $position->asset?->symbol ?? 'Unknown';
            $category = $position->asset?->category ?? 'unknown';

            if (!isset($assetRows[$symbol])) {
                $assetRows[$symbol] = ['label' => $symbol, 'value' => 0];
            }

            if (!isset($categoryRows[$category])) {
                $categoryRows[$category] = ['label' => $category, 'value' => 0];
            }

            $assetRows[$symbol]['value'] += $value;
            $categoryRows[$category]['value'] += $value;
            $totalValue += $value;
        }

        $mapFunc = function ($row) use ($totalValue) {
            $row['value'] = round($row['value'], 2);
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
}
