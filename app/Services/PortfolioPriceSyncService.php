<?php

namespace App\Services;

use App\Models\PortfolioPosition;
use App\Models\User;
use Illuminate\Support\Collection;

class PortfolioPriceSyncService
{
    public function __construct(
        protected MarketPriceService $marketPriceService,
        protected CurrencyConverterService $converter,
    ) {
    }

    public function syncUserPositions(User $user): array
    {
        $positions = PortfolioPosition::query()
            ->with(['asset', 'account'])
            ->where('user_id', $user->id)
            ->get();

        return $this->syncPositions($positions, $user);
    }

    public function syncPositions(Collection $positions, ?User $user = null): array
    {
        $synced = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($positions as $position) {
            $result = $this->syncSinglePosition($position);

            if ($result === 'synced') {
                $synced++;
            } elseif ($result === 'skipped') {
                $skipped++;
            } else {
                $failed++;
            }
        }

        if ($user) {
            $user->update([
                'last_price_sync_at' => now(),
            ]);
        }

        return [
            'synced' => $synced,
            'skipped' => $skipped,
            'failed' => $failed,
            'total' => $positions->count(),
        ];
    }

    public function syncSinglePosition(PortfolioPosition $position): string
    {
        $position->loadMissing(['asset', 'account']);

        $asset = $position->asset;
        $account = $position->account;

        if (!$asset || !$account) {
            return 'failed';
        }

        $category = strtolower(trim((string) $asset->category));
        $symbol = strtoupper(trim((string) $asset->symbol));
        $accountCurrency = strtoupper(trim((string) ($account->currency ?? 'IDR')));

        if ($category === 'commodity') {
            return 'skipped';
        }

        $marketData = $this->marketPriceService->getCurrentPrice($symbol, $category);

        if (!$marketData || !isset($marketData['price'], $marketData['currency'])) {
            return 'failed';
        }

        $rawPrice = (float) $marketData['price'];
        $rawCurrency = strtoupper(trim((string) $marketData['currency']));

        $convertedPrice = $this->converter->convert(
            $rawPrice,
            $rawCurrency,
            $accountCurrency
        );

        $position->update([
            'current_price' => round((float) $convertedPrice, 8),
            'last_price_updated_at' => now(),
        ]);

        return 'synced';
    }

    public function shouldSyncUserAtCurrentTime(User $user): bool
    {
        if (!$user->price_sync_enabled) {
            return false;
        }

        $times = collect($user->price_sync_times ?? [])
            ->filter(fn ($time) => is_string($time) && trim($time) !== '')
            ->map(fn ($time) => trim($time))
            ->unique()
            ->values();

        if ($times->isEmpty()) {
            return false;
        }

        $nowTime = now()->format('H:i');

        if (!$times->contains($nowTime)) {
            return false;
        }

        $lastSyncAt = $user->last_price_sync_at;

        if (!$lastSyncAt) {
            return true;
        }

        if ($lastSyncAt->format('Y-m-d H:i') === now()->format('Y-m-d H:i')) {
            return false;
        }

        return true;
    }

    public function syncDueUsers(): array
    {
        $users = User::query()
            ->where('price_sync_enabled', true)
            ->get();

        $results = [];

        foreach ($users as $user) {
            if (!$this->shouldSyncUserAtCurrentTime($user)) {
                continue;
            }

            $results[$user->id] = $this->syncUserPositions($user);
        }

        return $results;
    }
}
