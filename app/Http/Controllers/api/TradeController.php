<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTradeRequest;
use App\Http\Requests\UpdateTradeRequest;
use App\Models\Account;
use App\Models\Trade;
use App\Services\AccountBalanceService;
use App\Services\AnalyticsService;
use App\Services\ApiResponseService;
use App\Services\TradePortfolioSyncService;
use App\Services\TradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TradeController extends Controller
{
    protected float $epsilon = 0.00000001;

    public function __construct(
        protected TradeService $tradeService,
        protected ApiResponseService $apiResponse,
        protected AnalyticsService $analyticsService,
        protected AccountBalanceService $accountBalanceService,
        protected TradePortfolioSyncService $tradePortfolioSyncService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Trade::with(['account', 'asset', 'strategy', 'tags'])
            ->where('user_id', $request->user()->id);

        if ($request->filled('account_id')) {
            $query->where('account_id', $request->integer('account_id'));
        }

        if ($request->filled('asset_id')) {
            $query->where('asset_id', $request->integer('asset_id'));
        }

        if ($request->filled('strategy_id')) {
            $query->where('strategy_id', $request->integer('strategy_id'));
        }

        if ($request->filled('position_type')) {
            if ($request->string('position_type') == 'no_investment') {
                $query->where('position_type', '!=', 'investment');
            } else {
                $query->where('position_type', $request->string('position_type'));
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('entry_date', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('entry_date', '<=', $request->date('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');

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

        $query->orderBy('entry_date', 'desc');

        $trades = $query->paginate(60);

        return $this->apiResponse->success(
            'Daftar trade berhasil diambil.',
            'Trade list retrieved successfully.',
            $trades->toArray()
        );
    }

    public function store(StoreTradeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        $account = Account::query()
            ->where('id', $data['account_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $requiredCash = ((float) $data['entry_price'] * (float) $data['quantity']) + (float) ($data['fees'] ?? 0);

        if (! $this->accountBalanceService->hasEnoughBalance($account, $requiredCash)) {
            return $this->apiResponse->error(
                'Saldo tidak cukup.',
                'Insufficient balance.',
                422
            );
        }

        $data['closed_quantity'] = 0;
        $data['exit_price'] = null;
        $data['exit_date'] = null;

        $data = $this->tradeService->prepareTradeData($data);

        $trade = DB::transaction(function () use ($request, $data) {
            $trade = Trade::create($data);

            $trade->tags()->sync($request->validated('tag_ids', []));

            $this->tradePortfolioSyncService->syncFromTrade($trade);

            return $trade;
        });

        return $this->apiResponse->success(
            'Trade berhasil dibuat.',
            'Trade created successfully.',
            $trade->load(['account', 'asset', 'strategy', 'tags'])->toArray(),
            201
        );
    }

    public function show(Request $request, Trade $trade): JsonResponse
    {
        abort_if($trade->user_id !== $request->user()->id, 403);

        return $this->apiResponse->success(
            'Detail trade berhasil diambil.',
            'Trade detail retrieved successfully.',
            $trade->load(['account', 'asset', 'strategy', 'tags'])->toArray()
        );
    }

    public function update(UpdateTradeRequest $request, Trade $trade): JsonResponse
    {
        abort_if($trade->user_id !== $request->user()->id, 403);

        $input = $request->validated();

        // Proteksi: entry_price dan quantity tidak boleh diubah
        unset($input['entry_price'], $input['quantity']);

        $account = Account::query()
            ->where('id', $trade->account_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $entryPrice = (float) $trade->entry_price;
        $quantity = (float) $trade->quantity;

        // Pastikan fees mengambil input terbaru
        $fees = isset($input['fees']) ? (float) $input['fees'] : (float) ($trade->fees ?? 0);
        $exitDate = $input['exit_date'] ?? $trade->exit_date;

        try {
            DB::transaction(function () use ($request, $trade, $input, $fees, $entryPrice, $quantity) {
                $oldTrade = $trade->replicate();
                $oldTrade->id = $trade->id;
                $tagIds = $request->validated('tag_ids', []);

                // 1. Investment Mode
                if ($trade->position_type === 'investment') {
                    $prepared = $this->tradeService->prepareTradeData(array_merge($trade->toArray(), $input));
                    $prepared['fees'] = $fees;
                    $trade->update($prepared);
                    $trade->tags()->sync($tagIds);
                    $this->syncTrade($oldTrade, $trade);
                    return;
                }

                // 2. Closed Trade Mode
                if ($trade->status === 'closed') {
                    $prepared = $this->tradeService->prepareTradeData(array_merge($trade->toArray(), $input));
                    $prepared['fees'] = $fees;
                    $prepared['quantity'] = $trade->quantity;
                    $prepared['closed_quantity'] = $trade->closed_quantity;

                    $trade->update($prepared);
                    $trade->tags()->sync($tagIds);
                    $this->syncTrade($oldTrade, $trade);
                    return;
                }

                $incrementClose = isset($input['closed_quantity']) ? (float) $input['closed_quantity'] : 0.0;
                $oldClosedQuantity = (float) ($trade->closed_quantity ?? 0);
                $newClosedQuantity = $this->tradeService->normalizeClosedQuantity($quantity, $oldClosedQuantity + $incrementClose);
                $actualIncrement = round($newClosedQuantity - $oldClosedQuantity, 8);
                $isFullyClosed = $this->isEffectivelyClosed($newClosedQuantity, $quantity);

                // 3. Regular Update (No Close)
                if ($actualIncrement <= 0) {
                    $prepared = $this->tradeService->prepareTradeData(array_merge($trade->toArray(), $input));
                    $prepared['fees'] = $fees;
                    $prepared['quantity'] = $trade->quantity;

                    $trade->update($prepared);
                    $trade->tags()->sync($tagIds);
                    $this->syncTrade($oldTrade, $trade);
                    return;
                }

                // 4. Fully Closed via Update
                if ($isFullyClosed) {
                    $exitPrice = (float) $input['exit_price'];
                    $finalProfitLoss = $this->tradeService->calculateProfitLoss($entryPrice, $exitPrice, $quantity, $fees);
                    $finalRMultiple = $this->tradeService->calculateRMultiple($entryPrice, (float)($input['stop_loss'] ?? $trade->stop_loss), $quantity, $finalProfitLoss);

                    $trade->update([
                        'fees' => $fees,
                        'exit_price' => $exitPrice,
                        'closed_quantity' => $quantity,
                        'status' => 'closed',
                        'profit_loss' => $finalProfitLoss,
                        'r_multiple' => $finalRMultiple,
                        'exit_date' => $input['exit_date'],
                        'notes' => $input['notes'] ?? $trade->notes,
                        'stop_loss' => $input['stop_loss'] ?? $trade->stop_loss,
                        'take_profit' => $input['take_profit'] ?? $trade->take_profit,
                    ]);
                    $trade->tags()->sync($tagIds);
                    $this->syncTrade($oldTrade, $trade);
                    return;
                }

                // 5. Partial Close (Create New Trade)
                $trade->update([
                    'closed_quantity' => $newClosedQuantity,
                    'status' => 'partial',
                    'notes' => $input['notes'] ?? $trade->notes,
                ]);

                $generatedTrade = Trade::create([
                    'user_id' => $trade->user_id,
                    'account_id' => $trade->account_id,
                    'asset_id' => $trade->asset_id,
                    'strategy_id' => $trade->strategy_id,
                    'position_type' => $trade->position_type,
                    'entry_price' => $entryPrice,
                    'exit_price' => (float) $input['exit_price'],
                    'quantity' => $actualIncrement,
                    'closed_quantity' => $actualIncrement,
                    'fees' => $fees,
                    'status' => 'closed',
                    'entry_date' => $trade->entry_date,
                    'exit_date' => $input['exit_date'],
                    'notes' => $this->buildGeneratedCloseNote($trade->notes, $actualIncrement),
                ]);

                $generatedTrade->tags()->sync($tagIds);
                $this->syncTrade($oldTrade, $trade);
            });
        } catch (\RuntimeException $e) {
            return $this->apiResponse->error($e->getMessage(), $e->getMessage(), 422);
        }

        return $this->apiResponse->success('Trade updated.', 'Success.', $trade->fresh()->load(['tags'])->toArray());
    }

    private function syncTrade($old, $new) {
        $this->tradePortfolioSyncService->syncFromTrade($old);
        $this->tradePortfolioSyncService->syncFromTrade($new);
    }

    public function destroy(Request $request, Trade $trade): JsonResponse
    {
        abort_if($trade->user_id !== $request->user()->id, 403);

        DB::transaction(function () use ($trade) {
            if ($trade->position_type === 'investment') {
                $groupTrades = Trade::query()
                    ->where('user_id', $trade->user_id)
                    ->where('account_id', $trade->account_id)
                    ->where('asset_id', $trade->asset_id)
                    ->where('position_type', 'investment')
                    ->get();

                foreach ($groupTrades as $t) {
                    $old = $t->replicate();
                    $old->id = $t->id;

                    $t->delete();

                    $this->tradePortfolioSyncService->syncFromTrade($old);
                }

                return;
            }

            $oldTrade = $trade->replicate();
            $oldTrade->id = $trade->id;

            $trade->delete();

            $this->tradePortfolioSyncService->syncFromTrade($oldTrade);
        });

        return $this->apiResponse->success(
            'Trade deleted.',
            'Trade deleted successfully.'
        );
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $userId = $request->user()->id;

        $trades = Trade::with(['account', 'asset', 'strategy'])
            ->where('user_id', $userId)
            ->orderBy('entry_date', 'desc')
            ->get();

        return response()->streamDownload(function () use ($trades) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'ID',
                'Asset',
                'Type',
                'Qty',
                'Entry',
                'Exit',
                'PnL',
            ]);

            foreach ($trades as $t) {
                fputcsv($handle, [
                    $t->id,
                    $t->asset?->symbol,
                    $t->position_type,
                    $t->quantity,
                    $t->entry_price,
                    $t->exit_price,
                    $t->profit_loss,
                ]);
            }

            fclose($handle);
        }, 'trades.csv');
    }

    protected function buildGeneratedCloseNote(?string $originalNotes, float $closedQty): string
    {
        $prefix = 'Generated from partial close';
        $qtyText = 'Qty closed: ' . rtrim(rtrim(number_format($closedQty, 8, '.', ''), '0'), '.');

        if (blank($originalNotes)) {
            return $prefix . ' | ' . $qtyText;
        }

        return $prefix . ' | ' . $qtyText . ' | ' . $originalNotes;
    }

    protected function isEffectivelyZero(float $value): bool
    {
        return abs($value) < $this->epsilon;
    }

    protected function isEffectivelyClosed(float $closedQty, float $totalQty): bool
    {
        return $totalQty > 0 && $closedQty >= ($totalQty - $this->epsilon);
    }
}
