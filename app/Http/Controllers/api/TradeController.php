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

        /*
        |--------------------------------------------------------------------------
        | CREATE TRADE MUST ALWAYS BE CLEAN
        |--------------------------------------------------------------------------
        | Tidak boleh create sambil partial close.
        */
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

        $account = Account::query()
            ->where('id', $trade->account_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $entryPrice = (float) ($input['entry_price'] ?? $trade->entry_price);
        $quantity = (float) $trade->quantity;
        $fees = (float) ($input['fees'] ?? $trade->fees ?? 0);
        $exitDate = $input['exit_date'] ?? $trade->exit_date;

        if (empty($exitDate)) {
            $requiredCash = ($entryPrice * $quantity) + $fees;

            if (! $this->accountBalanceService->hasEnoughBalance($account, $requiredCash, $trade->id)) {
                return $this->apiResponse->error(
                    'Saldo account tidak cukup untuk mengupdate trade ini.',
                    'Account balance is insufficient to update this trade.',
                    422
                );
            }
        }

        try {
            DB::transaction(function () use ($request, $trade, $input) {
                $oldTrade = $trade->replicate();
                $oldTrade->id = $trade->id;

                $tagIds = $request->validated('tag_ids', []);

                /*
                |--------------------------------------------------------------------------
                | INVESTMENT
                |--------------------------------------------------------------------------
                */
                if ($trade->position_type === 'investment') {
                    unset($input['quantity'], $input['closed_quantity']);

                    $merged = array_merge($trade->toArray(), $input);
                    $prepared = $this->tradeService->prepareTradeData($merged);

                    $trade->update($prepared);
                    $trade->tags()->sync($tagIds);

                    $this->tradePortfolioSyncService->syncFromTrade($oldTrade);
                    $this->tradePortfolioSyncService->syncFromTrade($trade);

                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | CLOSED TRADE SAFE EDIT
                |--------------------------------------------------------------------------
                | Closed trade tidak boleh ubah quantity dan closed_quantity
                */
                if ($trade->status === 'closed') {
                    unset($input['quantity'], $input['closed_quantity']);

                    $merged = array_merge($trade->toArray(), $input);
                    $prepared = $this->tradeService->prepareTradeData($merged);

                    $prepared['status'] = 'closed';
                    $prepared['closed_quantity'] = $trade->closed_quantity;
                    $prepared['quantity'] = $trade->quantity;

                    $trade->update($prepared);
                    $trade->tags()->sync($tagIds);

                    $this->tradePortfolioSyncService->syncFromTrade($oldTrade);
                    $this->tradePortfolioSyncService->syncFromTrade($trade);

                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | PARTIAL / FULL CLOSE FLOW
                |--------------------------------------------------------------------------
                | FE closed_quantity = qty tambahan yang ditutup sekarang
                |--------------------------------------------------------------------------
                */
                $incrementClose = isset($input['closed_quantity']) && $input['closed_quantity'] !== ''
                    ? (float) $input['closed_quantity']
                    : 0;

                $oldClosedQuantity = (float) ($trade->closed_quantity ?? 0);
                $totalQuantity = (float) $trade->quantity;

                if ($trade->closed_quantity >= $trade->quantity) {
                    throw new \RuntimeException('Trade sudah fully closed.');
                }

                if ($incrementClose < 0) {
                    $incrementClose = 0;
                }

                $newClosedQuantity = $this->tradeService->normalizeClosedQuantity(
                    $totalQuantity,
                    $oldClosedQuantity + $incrementClose
                );

                $actualIncrement = $newClosedQuantity - $oldClosedQuantity;
                $isFullyClosed = abs($newClosedQuantity - $totalQuantity) < 0.00000001;

                if ($incrementClose > 0 && $actualIncrement <= 0) {
                    throw new \RuntimeException('Tidak ada quantity tersisa untuk partial close.');
                }

                if ($actualIncrement > 0) {
                    if (empty($input['exit_price'])) {
                        throw new \RuntimeException('Exit price wajib diisi untuk partial close.');
                    }

                    if (empty($input['exit_date'])) {
                        throw new \RuntimeException('Exit date wajib diisi untuk partial close.');
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | NORMAL UPDATE TANPA PARTIAL CLOSE
                |--------------------------------------------------------------------------
                */
                if ($actualIncrement <= 0) {
                    unset($input['quantity'], $input['closed_quantity']);

                    /*
                    |--------------------------------------------------------------------------
                    | NO CLOSE ACTION
                    |--------------------------------------------------------------------------
                    | If user is only managing the trade without closing any quantity,
                    | exit-related fields must not affect the trade state or PnL.
                    |--------------------------------------------------------------------------
                    */
                    unset($input['exit_price'], $input['exit_date']);

                    $merged = array_merge($trade->toArray(), $input);
                    $prepared = $this->tradeService->prepareTradeData($merged);

                    $prepared['closed_quantity'] = $trade->closed_quantity;
                    $prepared['status'] = $trade->status;
                    $prepared['quantity'] = $trade->quantity;
                    $prepared['exit_price'] = $trade->exit_price;
                    $prepared['exit_date'] = $trade->exit_date;
                    $prepared['profit_loss'] = $trade->profit_loss;
                    $prepared['r_multiple'] = $trade->r_multiple;

                    $trade->update($prepared);

                    $trade->tags()->sync($tagIds);

                    $this->tradePortfolioSyncService->syncFromTrade($oldTrade);
                    $this->tradePortfolioSyncService->syncFromTrade($trade);

                    return;
                }
                /*
                |--------------------------------------------------------------------------
                | FULL CLOSE
                |--------------------------------------------------------------------------
                | Parent trade langsung jadi closed final
                */
                if ($isFullyClosed) {
                    $exitPrice = (float) $input['exit_price'];
                    $finalFees = (float) ($input['fees'] ?? $trade->fees ?? 0);
                    $finalStopLoss = isset($input['stop_loss']) && $input['stop_loss'] !== ''
                        ? (float) $input['stop_loss']
                        : (isset($trade->stop_loss) ? (float) $trade->stop_loss : null);

                    $finalProfitLoss = $this->tradeService->calculateProfitLoss(
                        (float) $trade->entry_price,
                        $exitPrice,
                        $totalQuantity,
                        $finalFees
                    );

                    $finalRMultiple = $this->tradeService->calculateRMultiple(
                        (float) $trade->entry_price,
                        $finalStopLoss,
                        $totalQuantity,
                        $finalProfitLoss
                    );

                    $trade->update([
                        'exit_price' => $exitPrice,
                        'closed_quantity' => $newClosedQuantity,
                        'stop_loss' => $finalStopLoss,
                        'take_profit' => $input['take_profit'] ?? $trade->take_profit,
                        'fees' => $finalFees,
                        'profit_loss' => $finalProfitLoss,
                        'r_multiple' => $finalRMultiple,
                        'exit_date' => $input['exit_date'],
                        'status' => 'closed',
                        'notes' => $input['notes'] ?? $trade->notes,
                    ]);

                    $trade->tags()->sync($tagIds);

                    $this->tradePortfolioSyncService->syncFromTrade($oldTrade);
                    $this->tradePortfolioSyncService->syncFromTrade($trade);

                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | PARTIAL CLOSE
                |--------------------------------------------------------------------------
                | Parent trade jadi partial
                | Lalu histori partial dibuat sebagai trade baru (closed)
                |--------------------------------------------------------------------------
                */
                $trade->update([
                    'closed_quantity' => $newClosedQuantity,
                    'status' => 'partial',
                ]);

                $trade->tags()->sync($tagIds);

                $generatedEntryPrice = (float) $trade->entry_price;
                $generatedExitPrice = (float) $input['exit_price'];
                $generatedFees = (float) ($input['fees'] ?? 0);
                $generatedStopLoss = isset($input['stop_loss']) && $input['stop_loss'] !== ''
                    ? (float) $input['stop_loss']
                    : (isset($trade->stop_loss) ? (float) $trade->stop_loss : null);

                $generatedProfitLoss = $this->tradeService->calculateProfitLoss(
                    $generatedEntryPrice,
                    $generatedExitPrice,
                    $actualIncrement,
                    $generatedFees
                );

                $generatedRMultiple = $this->tradeService->calculateRMultiple(
                    $generatedEntryPrice,
                    $generatedStopLoss,
                    $actualIncrement,
                    $generatedProfitLoss
                );

                $generatedTrade = Trade::create([
                    'user_id' => $trade->user_id,
                    'account_id' => $trade->account_id,
                    'asset_id' => $trade->asset_id,
                    'strategy_id' => $trade->strategy_id,
                    'position_type' => $trade->position_type,
                    'entry_price' => $generatedEntryPrice,
                    'exit_price' => $generatedExitPrice,
                    'quantity' => $actualIncrement,
                    'closed_quantity' => $actualIncrement,
                    'stop_loss' => $generatedStopLoss,
                    'take_profit' => $input['take_profit'] ?? $trade->take_profit,
                    'fees' => $generatedFees,
                    'profit_loss' => $generatedProfitLoss,
                    'r_multiple' => $generatedRMultiple,
                    'entry_date' => $trade->entry_date,
                    'exit_date' => $input['exit_date'],
                    'status' => 'closed',
                    'notes' => $this->buildGeneratedCloseNote($trade->notes, $actualIncrement),
                ]);

                $generatedTrade->tags()->sync($tagIds);

                $this->tradePortfolioSyncService->syncFromTrade($oldTrade);
                $this->tradePortfolioSyncService->syncFromTrade($trade);
            });
        } catch (\RuntimeException $e) {
            return $this->apiResponse->error(
                $e->getMessage(),
                $e->getMessage(),
                422
            );
        }

        return $this->apiResponse->success(
            'Trade berhasil diupdate.',
            'Trade updated successfully.',
            $trade->fresh()->load(['account', 'asset', 'strategy', 'tags'])->toArray()
        );
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
}
