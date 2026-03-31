<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        protected AnalyticsService $analyticsService
    ) {
    }

    public function summary(Request $request): JsonResponse
    {
        $data = $this->analyticsService->getSummary($request->user()->id, $request->all());

        return response()->json([
            'message' => [
                'id' => 'Ringkasan performa berhasil diambil.',
                'en' => 'Summary performance retrieved successfully.'
            ],
            'data' => $data,
        ]);
    }

    public function strategyPerformance(Request $request): JsonResponse
    {
        $data = $this->analyticsService->getStrategyPerformance($request->user()->id, $request->all());

        return response()->json([
            'message' => [
                'id' => 'Performa strategi berhasil diambil.',
                'en' => 'Strategy performance retrieved successfully.'
            ],
            'data' => $data,
        ]);
    }

    public function tagPerformance(Request $request): JsonResponse
    {
        $data = $this->analyticsService->getTagPerformance($request->user()->id, $request->all());

        return response()->json([
            'message' => [
                'id' => 'Performa tag berhasil diambil.',
                'en' => 'Tag performance retrieved successfully.'
            ],
            'data' => $data,
        ]);
    }

    public function monthlyPerformance(Request $request): JsonResponse
    {
        $data = $this->analyticsService->getMonthlyPerformance($request->user()->id, $request->all());

        return response()->json([
            'message' => [
                'id' => 'Performa bulanan berhasil diambil.',
                'en' => 'Monthly performance retrieved successfully.'
            ],
            'data' => $data,
        ]);
    }

    public function assetPerformance(Request $request): JsonResponse
    {
        $data = $this->analyticsService->getAssetPerformance($request->user()->id, $request->all());

        return response()->json([
            'message' => [
                'id' => 'Performa aset berhasil diambil.',
                'en' => 'Asset performance retrieved successfully.'
            ],
            'data' => $data,
        ]);
    }

    public function portfolioSummary(Request $request): JsonResponse
    {
        $data = $this->analyticsService->getPortfolioSummary($request->user()->id);

        return response()->json([
            'message' => [
                'id' => 'Ringkasan portofolio berhasil diambil.',
                'en' => 'Portfolio summary retrieved successfully.'
            ],
            'data' => $data,
        ]);
    }

    public function assetAllocation(Request $request): JsonResponse
    {
        $data = $this->analyticsService->getAssetAllocation($request->user()->id);

        return response()->json([
            'message' => [
                'id' => 'Alokasi aset berhasil diambil.',
                'en' => 'Asset allocation retrieved successfully.'
            ],
            'data' => $data,
        ]);
    }
}
