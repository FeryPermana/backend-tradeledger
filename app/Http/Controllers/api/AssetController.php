<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\ApiResponseService;

class AssetController extends Controller
{
     public function __construct(
        protected ApiResponseService $apiResponse
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Asset::query()
            ->where('user_id', $request->user()->id);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where('symbol', 'like', '%' . $search . '%')
                    ->orWhere('name', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->boolean('watchlist_only')) {
            $query->where('is_watchlist', true);
        }

        $assets = $query
            ->orderByDesc('is_watchlist')
            ->orderBy('symbol')
            ->get();

        return response()->json([
            'message' => [
                'id' => 'Daftar asset berhasil diambil.',
                'en' => 'Asset list retrieved successfully.',
            ],
            'data' => $assets,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAssetRequest $request): JsonResponse
    {
        $symbol = strtoupper($request->validated('symbol'));

        $exists = Asset::query()
            ->where('user_id', $request->user()->id)
            ->where('symbol', $symbol)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => [
                    'id' => 'Symbol asset sudah ada.',
                    'en' => 'Asset symbol already exists.',
                ],
            ], 422);
        }

        $asset = Asset::create([
            'user_id' => $request->user()->id,
            'symbol' => $symbol,
            'name' => $request->validated('name'),
            'market' => $request->validated('market'),
            'category' => $request->validated('category'),
            'is_watchlist' => (bool) $request->validated('is_watchlist', false),
            'tradingview_url' => $request->validated('tradingview_url'),
        ]);

        return $this->apiResponse->success(
            'Asset berhasil dibuat.',
            'Asset created successfully.',
            $asset->toArray(),
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Asset $asset): JsonResponse
    {
        abort_if($asset->user_id !== $request->user()->id, 403);

        return response()->json([
            'message' => [
                'id' => 'Detail asset berhasil diambil.',
                'en' => 'Asset detail retrieved successfully.',
            ],
            'data' => $asset,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Asset $asset)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAssetRequest $request, Asset $asset): JsonResponse
    {
        abort_if($asset->user_id !== $request->user()->id, 403);

        $symbol = strtoupper($request->validated('symbol'));

        $exists = Asset::query()
            ->where('user_id', $request->user()->id)
            ->where('symbol', $symbol)
            ->where('id', '!=', $asset->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => [
                    'id' => 'Symbol asset sudah ada.',
                    'en' => 'Asset symbol already exists.',
                ],
            ], 422);
        }

        $asset->update([
            'symbol' => $symbol,
            'name' => $request->validated('name'),
            'market' => $request->validated('market'),
            'category' => $request->validated('category'),
            'is_watchlist' => (bool) $request->validated('is_watchlist', false),
            'tradingview_url' => $request->validated('tradingview_url'),
        ]);

        return response()->json([
            'message' => [
                'id' => 'Asset berhasil diupdate.',
                'en' => 'Asset updated successfully.',
            ],
            'data' => $asset->fresh()->toArray(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Asset $asset): JsonResponse
    {
        abort_if($asset->user_id !== $request->user()->id, 403);

        $asset->delete();

        return response()->json([
            'message' => [
                'id' => 'Asset berhasil dihapus.',
                'en' => 'Asset deleted successfully.',
            ],
        ]);
    }

    public function toggleWatchlist(Request $request, Asset $asset): JsonResponse
    {
        abort_if($asset->user_id !== $request->user()->id, 403);

        $asset->update([
            'is_watchlist' => ! $asset->is_watchlist,
        ]);

        return response()->json([
            'message' => [
                'id' => 'Watchlist asset berhasil diupdate.',
                'en' => 'Asset watchlist updated successfully.',
            ],
            'data' => $asset->fresh(),
        ]);
    }

    public function updatePrice(Request $request, Asset $asset): JsonResponse
    {
        abort_if($asset->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'current_price' => ['required', 'numeric'],
        ]);

        $asset->update([
            'current_price' => $validated['current_price'],
            'price_updated_at' => now(),
        ]);

        return $this->apiResponse->success(
            'Harga asset berhasil diupdate.',
            'Asset price updated successfully.',
            $asset->fresh()->toArray()
        );
    }
}
