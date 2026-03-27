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
            if($request->string('position_type') == 'no_investment') {
                $query->where('position_type', '!=', 'investment');
            } else{
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

        $accountId = $trade->account_id;
        $account = Account::query()
            ->where('id', $accountId)
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
                | Investment tetap tidak memakai partial close dari trade form.
                */
                if ($trade->position_type === 'investment') {
                    unset($input['quantity']);

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
                | Trade yang sudah closed tetap boleh diedit,
                | tapi tidak boleh partial close lagi / bikin generated trade baru.
                */
                if ($trade->status === 'closed') {
                    unset($input['quantity']);
                    unset($input['closed_quantity']);

                    $merged = array_merge($trade->toArray(), $input);
                    $prepared = $this->tradeService->prepareTradeData($merged);

                    $prepared['status'] = 'closed';
                    $prepared['closed_quantity'] = $trade->closed_quantity;

                    $trade->update($prepared);
                    $trade->tags()->sync($tagIds);

                    $this->tradePortfolioSyncService->syncFromTrade($oldTrade);
                    $this->tradePortfolioSyncService->syncFromTrade($trade);

                    return;
                }

                /*
                |--------------------------------------------------------------------------
                | NON-INVESTMENT
                |--------------------------------------------------------------------------
                | Field closed_quantity dari FE dianggap sebagai:
                | "qty tambahan yang ditutup sekarang"
                | bukan total final closed quantity.
                */
                $incrementClose = isset($input['closed_quantity']) && $input['closed_quantity'] !== ''
                    ? (float) $input['closed_quantity']
                    : 0;

                $oldClosedQuantity = (float) ($trade->closed_quantity ?? 0);
                $quantity = (float) $trade->quantity;

                if ($incrementClose < 0) {
                    $incrementClose = 0;
                }

                $newClosedQuantity = $this->tradeService->normalizeClosedQuantity(
                    $quantity,
                    $oldClosedQuantity + $incrementClose
                );

                $actualIncrement = $newClosedQuantity - $oldClosedQuantity;

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
                | UPDATE PARENT TRADE
                |--------------------------------------------------------------------------
                | Parent trade = summary posisi
                | Jangan simpan pnl/exit di parent, biar tidak double merah/hijau.
                */
                unset($input['quantity']);
                $input['closed_quantity'] = $newClosedQuantity;

                $merged = array_merge($trade->toArray(), $input);
                $preparedMainTrade = $this->tradeService->prepareTradeData($merged);

                // parent trade hanya jadi wadah / summary
                $preparedMainTrade['exit_price'] = null;
                $preparedMainTrade['profit_loss'] = null;
                $preparedMainTrade['r_multiple'] = null;

                // kalau full close, parent tetap closed dan boleh simpan exit_date
                if ($newClosedQuantity >= $quantity && !empty($input['exit_date'])) {
                    $preparedMainTrade['exit_date'] = $input['exit_date'];
                    $preparedMainTrade['status'] = 'closed';
                }

                $trade->update($preparedMainTrade);
                $trade->tags()->sync($tagIds);

                /*
                |--------------------------------------------------------------------------
                | GENERATED EXIT TRADE
                |--------------------------------------------------------------------------
                | Saat partial close, buat trade baru sebagai histori exit.
                | PnL dan R dihitung langsung di sini biar tidak null.
                */
                if ($actualIncrement > 0) {
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
                }

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
