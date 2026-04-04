<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PortfolioPriceSyncService;
use Illuminate\Console\Command;

class SyncPortfolioPricesCommand extends Command
{
    protected $signature = 'portfolio:sync-prices
                            {--user= : Sync only one user by ID}
                            {--force : Force sync ignoring schedule}';

    protected $description = 'Sync portfolio real prices for users whose sync time matches current time';

    public function handle(PortfolioPriceSyncService $syncService): int
    {
        $userId = $this->option('user');
        $force = (bool) $this->option('force');

        $this->info('Now: ' . now()->format('Y-m-d H:i:s'));

        if ($userId) {
            $user = User::query()->find($userId);

            if (!$user) {
                $this->error("User #{$userId} not found.");
                return self::FAILURE;
            }

            $this->line("User #{$user->id}");
            $this->line('  enabled: ' . ($user->price_sync_enabled ? 'true' : 'false'));
            $this->line('  sync times: ' . json_encode($user->price_sync_times ?? []));
            $this->line('  last sync at: ' . ($user->last_price_sync_at?->format('Y-m-d H:i:s') ?? 'null'));

            if (!$force && !$syncService->shouldSyncUserAtCurrentTime($user)) {
                $this->warn("User #{$user->id} is not due at this time.");
                return self::SUCCESS;
            }

            $result = $syncService->syncUserPositions($user);

            $this->info(sprintf(
                'User #%s synced. Total: %d | Synced: %d | Skipped: %d | Failed: %d',
                $user->id,
                $result['total'] ?? 0,
                $result['synced'] ?? 0,
                $result['skipped'] ?? 0,
                $result['failed'] ?? 0,
            ));

            return self::SUCCESS;
        }

        if ($force) {
            $users = User::query()
                ->where('price_sync_enabled', true)
                ->get();

            if ($users->isEmpty()) {
                $this->warn('No enabled users found.');
                return self::SUCCESS;
            }

            foreach ($users as $user) {
                $this->line("Force syncing user #{$user->id}...");
                $this->line('  sync times: ' . json_encode($user->price_sync_times ?? []));
                $this->line('  last sync at: ' . ($user->last_price_sync_at?->format('Y-m-d H:i:s') ?? 'null'));

                $result = $syncService->syncUserPositions($user);

                $this->info(sprintf(
                    'User #%s synced. Total: %d | Synced: %d | Skipped: %d | Failed: %d',
                    $user->id,
                    $result['total'] ?? 0,
                    $result['synced'] ?? 0,
                    $result['skipped'] ?? 0,
                    $result['failed'] ?? 0,
                ));
            }

            return self::SUCCESS;
        }

        $results = $syncService->syncDueUsers();

        if (empty($results)) {
            $this->warn('No users due for portfolio price sync at this time.');

            $users = User::query()
                ->where('price_sync_enabled', true)
                ->get();

            if ($users->isEmpty()) {
                $this->line('No users with auto sync enabled.');
                return self::SUCCESS;
            }

            $this->line('Enabled users:');
            foreach ($users as $user) {
                $this->line(sprintf(
                    '- User #%s | times=%s | last_sync=%s',
                    $user->id,
                    json_encode($user->price_sync_times ?? []),
                    $user->last_price_sync_at?->format('Y-m-d H:i:s') ?? 'null'
                ));
            }

            return self::SUCCESS;
        }

        foreach ($results as $userId => $result) {
            $this->info(sprintf(
                'User #%s synced. Total: %d | Synced: %d | Skipped: %d | Failed: %d',
                $userId,
                $result['total'] ?? 0,
                $result['synced'] ?? 0,
                $result['skipped'] ?? 0,
                $result['failed'] ?? 0,
            ));
        }

        return self::SUCCESS;
    }
}
