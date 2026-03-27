<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Services\AccountBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\ApiResponseService;

class AccountController extends Controller
{
     public function __construct(
        protected ApiResponseService $apiResponse
    ) {}

    public function availableBalance(Request $request, Account $account, AccountBalanceService $accountBalanceService): JsonResponse
    {
        abort_if($account->user_id !== $request->user()->id, 403);

        $available = $accountBalanceService->getAvailableBalance($account);
        $used = $accountBalanceService->getUsedCapital($account->id);

        return response()->json([
            'data' => [
                'account_id' => $account->id,
                'account_name' => $account->name,
                'currency' => $account->currency,
                'initial_balance' => (float) $account->initial_balance,
                'used_capital' => $used,
                'available_balance' => $available,
            ],
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $accounts = Account::query()
                    ->where('user_id', auth()->user()->id)
                    ->latest()
                    ->get();

        return response()->json([
            'data' => $accounts,
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
    public function store(StoreAccountRequest $request): JsonResponse
    {
        $account = Account::create([
            'user_id' => $request->user()->id,
            'name' => $request->validated('name'),
            'type' => $request->validated('type'),
            'currency' => $request->validated('currency'),
            'initial_balance' => $request->validated('initial_balance', 0),
        ]);

        return $this->apiResponse->success(
            'Account berhasil dibuat.',
            'Account created successfully.',
            $account->toArray(),
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Account $account): JsonResponse
    {
        abort_if($account->user_id !== $request->user()->id, 403);

        return response()->json([
            'data' => $account,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Account $account)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAccountRequest $request, Account $account): JsonResponse
    {
        abort_if($account->user_id !== $request->user()->id, 403);

        $account->update([
            'name' => $request->validated('name'),
            'type' => $request->validated('type'),
            'currency' => $request->validated('currency'),
            'initial_balance' => $request->validated('initial_balance', 0),
        ]);

        return $this->apiResponse->success(
            'Account berhasil diupdate.',
            'Account updated successfully.',
            $account->toArray(),
            201
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Account $account): JsonResponse
    {
        abort_if($account->user_id !== $request->user()->id, 403);

        $account->delete();

        return $this->apiResponse->success(
            'Account berhasil dihapus.',
            'Account deleted successfully.'
        );
    }
}
