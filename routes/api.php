<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PortfolioPositionController;
use App\Http\Controllers\Api\StrategyController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TradeController;
use App\Services\CurrencyConverterService;
use App\Services\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.reset');

    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed'])
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/plan-status', [AuthController::class, 'planStatus']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/password', [AuthController::class, 'updatePassword']);
        Route::post('/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
            ->middleware('throttle:6,1')
            ->name('verification.send');
    });
});

/*
|--------------------------------------------------------------------------
| PROTECTED
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | ACCOUNTS
    |--------------------------------------------------------------------------
    */
    Route::get('accounts', [AccountController::class, 'index']);
    Route::post('accounts', [AccountController::class, 'store'])->middleware('free.account.limit');
    Route::get('accounts/{account}', [AccountController::class, 'show']);
    Route::put('accounts/{account}', [AccountController::class, 'update']);
    Route::delete('accounts/{account}', [AccountController::class, 'destroy']);
    Route::get('accounts/{account}/available-balance', [AccountController::class, 'availableBalance']);

    /*
    |--------------------------------------------------------------------------
    | TRADES
    |--------------------------------------------------------------------------
    */
    Route::get('trades', [TradeController::class, 'index']);
    Route::post('trades', [TradeController::class, 'store'])->middleware('free.trade.limit');
    Route::get('trades/{trade}', [TradeController::class, 'show']);
    Route::put('trades/{trade}', [TradeController::class, 'update']);
    Route::delete('trades/{trade}', [TradeController::class, 'destroy']);
    Route::get('trades/export/csv', [TradeController::class, 'exportCsv']);

    /*
    |--------------------------------------------------------------------------
    | ASSETS
    |--------------------------------------------------------------------------
    */
    Route::get('assets', [AssetController::class, 'index']);
    Route::post('assets', [AssetController::class, 'store']);
    Route::get('assets/{asset}', [AssetController::class, 'show']);
    Route::put('assets/{asset}', [AssetController::class, 'update']);
    Route::delete('assets/{asset}', [AssetController::class, 'destroy']);
    Route::patch('assets/{asset}/price', [AssetController::class, 'updatePrice']);
    Route::patch('assets/{asset}/toggle-watchlist', [AssetController::class, 'toggleWatchlist']);

    /*
    |--------------------------------------------------------------------------
    | STRATEGIES
    |--------------------------------------------------------------------------
    */
    Route::get('strategies', [StrategyController::class, 'index']);
    Route::post('strategies', [StrategyController::class, 'store']);
    Route::get('strategies/{strategy}', [StrategyController::class, 'show']);
    Route::put('strategies/{strategy}', [StrategyController::class, 'update']);
    Route::delete('strategies/{strategy}', [StrategyController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | PORTFOLIO
    |--------------------------------------------------------------------------
    */
    Route::get('portfolio-summary', [PortfolioPositionController::class, 'summary']);
    Route::get('portfolio-allocation', [PortfolioPositionController::class, 'allocation']);
    Route::get('portfolio-positions', [PortfolioPositionController::class, 'index']);
    Route::post('portfolio-positions', [PortfolioPositionController::class, 'store']);
    Route::get('portfolio-positions/{portfolioPosition}', [PortfolioPositionController::class, 'show']);
    Route::put('portfolio-positions/{portfolioPosition}', [PortfolioPositionController::class, 'update']);
    Route::delete('portfolio-positions/{portfolioPosition}', [PortfolioPositionController::class, 'destroy']);
    Route::patch('portfolio-positions/{portfolioPosition}/current-price', [PortfolioPositionController::class, 'updateCurrentPrice']);
    Route::post(
        'portfolio-positions/{portfolioPosition}/partial-close',
        [PortfolioPositionController::class, 'partialClose']
    );

    /*
    |--------------------------------------------------------------------------
    | TAGS
    |--------------------------------------------------------------------------
    */
    Route::get('tags', [TagController::class, 'index']);
    Route::post('tags', [TagController::class, 'store']);
    Route::delete('tags/{tag}', [TagController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | PAYMENTS
    |--------------------------------------------------------------------------
    */
    Route::get('payments', [PaymentController::class, 'index']);
    Route::post('payments', [PaymentController::class, 'store']);

    /*
    |--------------------------------------------------------------------------
    | ANALYTICS (PREMIUM)
    |--------------------------------------------------------------------------
    */
    Route::get('analytics/summary', [AnalyticsController::class, 'summary']);
    Route::prefix('analytics')->middleware('premium')->group(function () {
        Route::get('tag-performance', [AnalyticsController::class, 'tagPerformance']);
        Route::get('strategy-performance', [AnalyticsController::class, 'strategyPerformance']);
        Route::get('monthly-performance', [AnalyticsController::class, 'monthlyPerformance']);
        Route::get('portfolio-summary', [AnalyticsController::class, 'portfolioSummary']);
        Route::get('asset-allocation', [AnalyticsController::class, 'assetAllocation']);
        Route::get('asset-performance', [AnalyticsController::class, 'assetPerformance']);
    });
});

/*
|--------------------------------------------------------------------------
| PAYMENT ADMIN
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'payment.admin'])->prefix('admin')->group(function () {
    Route::get('payments', [PaymentController::class, 'adminIndex']);
    Route::post('payments/{payment}/approve', [PaymentController::class, 'approve']);
    Route::post('payments/{payment}/reject', [PaymentController::class, 'reject']);
});

/*
|--------------------------------------------------------------------------
| DEBUG (OPTIONAL)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->get('/debug/exchange-rate', function (
    Request $request,
    ExchangeRateService $exchangeRateService,
    CurrencyConverterService $currencyConverterService
) {
    $from = $request->get('from', 'USD');
    $to = $request->get('to', 'IDR');
    $amount = (float) $request->get('amount', 1);

    return response()->json([
        'data' => [
            'from' => $from,
            'to' => $to,
            'amount' => $amount,
            'rate' => $exchangeRateService->getRate($from, $to),
            'converted' => $currencyConverterService->convert($amount, $from, $to),
        ],
    ]);
});
