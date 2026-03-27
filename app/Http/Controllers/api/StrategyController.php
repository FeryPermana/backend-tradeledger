<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStrategyRequest;
use App\Http\Requests\UpdateStrategyRequest;
use App\Models\Strategy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\ApiResponseService;

class StrategyController extends Controller
{
     public function __construct(
        protected ApiResponseService $apiResponse
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Strategy::query()
            ->where('user_id', $request->user()->id);

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function($q) use ($search) {
                $q->where('name','like',"%{$search}%");
            });
        }

        $strategies = $query
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => [
                'id' => 'Daftar strategi berhasil diambil.',
                'en' => 'Strategy list retrieved successfully.'
            ],
            'data' => $strategies
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
    public function store(StoreStrategyRequest $request): JsonResponse
    {
        $exists = Strategy::query()
            ->where('user_id',$request->user()->id)
            ->where('name',$request->name)
            ->exists();

        if($exists){
            return response()->json([
                'message'=>[
                    'id'=>'Nama strategi sudah ada.',
                    'en'=>'Strategy name already exists.'
                ]
            ],422);
        }

        $strategy = Strategy::create([
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'description' => $request->description,
            'timeframe' => $request->timeframe,
            'setup_type' => $request->setup_type,
            'risk_model' => $request->risk_model
        ]);

        return $this->apiResponse->success(
            'Strategy berhasil dibuat.',
            'Strategy created successfully.',
            $strategy->toArray(),
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Strategy $strategy): JsonResponse
    {
        abort_if($strategy->user_id !== $request->user()->id, 403);

        return response()->json([
            'message'=>[
                'id'=>'Detail strategi berhasil diambil.',
                'en'=>'Strategy detail retrieved successfully.'
            ],
            'data'=>$strategy
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Strategy $strategy)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStrategyRequest $request, Strategy $strategy): JsonResponse
    {
        abort_if($strategy->user_id !== $request->user()->id, 403);

        $exists = Strategy::query()
            ->where('user_id',$request->user()->id)
            ->where('name',$request->name)
            ->where('id','!=',$strategy->id)
            ->exists();

        if($exists){
            return response()->json([
                'message'=>[
                    'id'=>'Nama strategi sudah ada.',
                    'en'=>'Strategy name already exists.'
                ]
            ],422);
        }

        $strategy->update([
            'name'=>$request->name,
            'description'=>$request->description,
            'timeframe'=>$request->timeframe,
            'setup_type'=>$request->setup_type,
            'risk_model'=>$request->risk_model
        ]);

        return $this->apiResponse->success(
            'Strategy berhasil diupdate.',
            'Strategy updated successfully.',
            $strategy->fresh(),
            201
        );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Strategy $strategy): JsonResponse
    {
        abort_if($strategy->user_id !== $request->user()->id,403);

        $strategy->delete();

        return response()->json([
            'message'=>[
                'id'=>'Strategi berhasil dihapus.',
                'en'=>'Strategy deleted successfully.'
            ]
        ]);
    }
}
