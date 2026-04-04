<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePortfolioPositionRequest;
use App\Http\Requests\UpdatePortfolioPositionRequest;
use App\Models\PortfolioPosition;
use App\Models\Trade;
use App\Services\ApiResponseService;
use App\Services\PortfolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortfolioPositionController extends Controller
{
    public function __construct(
        protected ApiResponseService $apiResponse,
        protected PortfolioService $portfolioService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $positions = $this->portfolioService->getPositions($request->user(), $request->all());

        return response()->json([
            'message' => [
                'id' => 'Daftar portfolio berhasil diambil',
                'en' => 'Portfolio list retrieved successfully.',
            ],
            'data' => $positions,
        ]);
    }

    public function store(StorePortfolioPositionRequest $request): JsonResponse
    {
        $exists = PortfolioPosition::query()
            ->where('user_id', $request->user()->id)
            ->where('asset_id', $request->validated('asset_id'))
            ->where('account_id', $request->validated('account_id'))
            ->exists();

        if ($exists) {
            return $this->apiResponse->error(
                'Posisi portofolio untuk aset dan account ini sudah ada.',
                'Portfolio position for this asset and account already exists.',
                422
            );
        }

        $position = PortfolioPosition::create([
            'user_id' => $request->user()->id,
            'account_id' => $request->validated('account_id'),
            'asset_id' => $request->validated('asset_id'),
            'source_type' => 'manual',
            'quantity' => $request->validated('quantity'),
            'avg_price' => $request->validated('avg_price'),
            'total_fees' => $request->validated('total_fees', 0),
            'target_price' => $request->validated('target_price'),
            'horizon' => $request->validated('horizon'),
            'conviction_level' => $request->validated('conviction_level'),
            'thesis' => $request->validated('thesis'),
            'notes' => $request->validated('notes'),
            'current_price' => $request->validated('current_price'),
            'last_price_updated_at' => $request->validated('current_price') !== null ? now() : null,
        ]);

        return $this->apiResponse->success(
            'Posisi portofolio berhasil dibuat.',
            'Portfolio position created successfully.',
            $position->load(['asset', 'account'])->toArray(),
            201
        );
    }

    public function show(Request $request, PortfolioPosition $portfolioPosition): JsonResponse
    {
        abort_if($portfolioPosition->user_id !== $request->user()->id, 403);

        $portfolioPosition->load(['asset', 'account']);

        $accountCurrency = strtoupper($portfolioPosition->account?->currency ?? 'IDR');
        $metrics = $this->portfolioService->getPositionMetrics($portfolioPosition, $accountCurrency);

        return $this->apiResponse->success(
            'Detail portofolio berhasil diambil.',
            'Portfolio detail retrieved successfully.',
            array_merge(
                $portfolioPosition->toArray(),
                $metrics,
                [
                    'display_currency' => $accountCurrency,
                    'avg_price' => $metrics['avg_price_display'],
                    'total_fees' => $metrics['total_fees_display'],
                ]
            )
        );
    }

    public function update(UpdatePortfolioPositionRequest $request, PortfolioPosition $portfolioPosition): JsonResponse
    {
        abort_if($portfolioPosition->user_id !== $request->user()->id, 403);

        if ($portfolioPosition->source_type === 'trade_sync') {
            $portfolioPosition->update([
                'target_price' => $request->validated('target_price'),
                'horizon' => $request->validated('horizon'),
                'conviction_level' => $request->validated('conviction_level'),
                'thesis' => $request->validated('thesis'),
                'notes' => $request->validated('notes'),
                'current_price' => $request->validated('current_price', $portfolioPosition->current_price),
                'last_price_updated_at' => $request->has('current_price')
                    ? now()
                    : $portfolioPosition->last_price_updated_at,
            ]);

            return $this->apiResponse->success(
                'Metadata investment berhasil diupdate.',
                'Investment metadata updated successfully.',
                $portfolioPosition->fresh()->load(['asset', 'account'])->toArray()
            );
        }

        $portfolioPosition->update([
            'account_id' => $request->validated('account_id'),
            'quantity' => $request->validated('quantity'),
            'avg_price' => $request->validated('avg_price'),
            'total_fees' => $request->validated('total_fees', 0),
            'target_price' => $request->validated('target_price'),
            'horizon' => $request->validated('horizon'),
            'conviction_level' => $request->validated('conviction_level'),
            'thesis' => $request->validated('thesis'),
            'notes' => $request->validated('notes'),
            'current_price' => $request->validated('current_price', $portfolioPosition->current_price),
            'last_price_updated_at' => $request->has('current_price')
                ? now()
                : $portfolioPosition->last_price_updated_at,
        ]);

        return $this->apiResponse->success(
            'Posisi portofolio berhasil diupdate.',
            'Portfolio position updated successfully.',
            $portfolioPosition->fresh()->load(['asset', 'account'])->toArray()
        );
    }

    public function destroy(Request $request, PortfolioPosition $portfolioPosition): JsonResponse
    {
        abort_if($portfolioPosition->user_id !== $request->user()->id, 403);

        if ($portfolioPosition->source_type === 'trade_sync') {
            return $this->apiResponse->error(
                'Posisi investment hasil sinkronisasi trade tidak bisa dihapus manual.',
                'Trade synced investment position cannot be deleted manually.',
                422
            );
        }

        $portfolioPosition->delete();

        return $this->apiResponse->success(
            'Posisi portofolio berhasil dihapus.',
            'Portfolio position deleted successfully.'
        );
    }

    public function summary(Request $request): JsonResponse
    {
        $summary = $this->portfolioService->getSummary($request->user(), $request->all());

        return $this->apiResponse->success(
            'Ringkasan portofolio berhasil diambil.',
            'Portfolio summary retrieved successfully.',
            $summary
        );
    }

    public function allocation(Request $request): JsonResponse
    {
        $allocation = $this->portfolioService->getAllocation($request->user(), $request->all());

        return $this->apiResponse->success(
            'Alokasi portofolio berhasil diambil.',
            'Portfolio allocation retrieved successfully.',
            $allocation
        );
    }

    public function updateCurrentPrice(Request $request, PortfolioPosition $portfolioPosition): JsonResponse
    {
        abort_if($portfolioPosition->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'current_price' => ['required', 'numeric', 'min:0'],
        ]);

        $portfolioPosition->update([
            'current_price' => $validated['current_price'],
            'last_price_updated_at' => now(),
        ]);

        $portfolioPosition->load(['asset', 'account']);
        $accountCurrency = strtoupper($portfolioPosition->account?->currency ?? 'IDR');
        $metrics = $this->portfolioService->getPositionMetrics($portfolioPosition, $accountCurrency);

        return $this->apiResponse->success(
            'Harga posisi berhasil diperbarui.',
            'Position price updated successfully.',
            array_merge(
                $portfolioPosition->toArray(),
                $metrics,
                [
                    'display_currency' => $accountCurrency,
                    'avg_price' => $metrics['avg_price_display'],
                    'total_fees' => $metrics['total_fees_display'],
                ]
            )
        );
    }

    public function partialClose(Request $request, PortfolioPosition $portfolioPosition): JsonResponse
    {
        abort_if($portfolioPosition->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'quantity' => ['required', 'numeric'],
            'exit_price' => ['required', 'numeric'],
            'exit_fee' => ['nullable', 'numeric'],
            'exit_date' => ['required', 'date'],
        ]);

        $closeQty = (float) $validated['quantity'];
        $exitPrice = (float) $validated['exit_price'];
        $exitFee = (float) ($validated['exit_fee'] ?? 0);

        $availableQty = (float) $portfolioPosition->quantity;
        $avgPrice = (float) $portfolioPosition->avg_price;

        if ($closeQty > $availableQty) {
            return $this->apiResponse->error(
                'Quantity melebihi posisi.',
                'Quantity exceeds position.',
                422
            );
        }

        $realizedPnl = (($exitPrice - $avgPrice) * $closeQty) - $exitFee;

        DB::transaction(function () use (
            $request,
            $portfolioPosition,
            $closeQty,
            $exitPrice,
            $exitFee,
            $validated,
            $realizedPnl,
        ) {
            Trade::create([
                'user_id' => $request->user()->id,
                'account_id' => $portfolioPosition->account_id,
                'asset_id' => $portfolioPosition->asset_id,
                'strategy_id' => null,
                'position_type' => 'investment',
                'entry_price' => $portfolioPosition->avg_price,
                'exit_price' => $exitPrice,
                'quantity' => $closeQty,
                'closed_quantity' => $closeQty,
                'stop_loss' => null,
                'take_profit' => null,
                'fees' => $exitFee,
                'profit_loss' => round($realizedPnl, 2),
                'r_multiple' => null,
                'entry_date' => now(),
                'exit_date' => $validated['exit_date'],
                'status' => 'closed',
                'notes' => 'Partial Sell (Investment)',
            ]);

            $remainingQty = max(0, $portfolioPosition->quantity - $closeQty);

            if ($remainingQty <= 0) {
                $portfolioPosition->delete();
                return;
            }

            $portfolioPosition->update([
                'quantity' => round($remainingQty, 8),
            ]);
        });

        return $this->apiResponse->success(
            'Partial close berhasil.',
            'Partial close successful.'
        );
    }
}
