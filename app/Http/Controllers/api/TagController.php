<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTagRequest;
use App\Models\Tag;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function __construct(
        protected ApiResponseService $apiResponse
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Tag::query()
            ->where('user_id', $request->user()->id);

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where('name', 'like', '%' . $search . '%');
        }

        $tags = $query
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => [
                'id' => 'Daftar tag berhasil diambil.',
                'en' => 'Tag list retrieved successfully.'
            ],
            'data' => $tags
        ]);
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $exists = Tag::query()
            ->where('user_id', $request->user()->id)
            ->where('name', $request->validated('name'))
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => [
                    'id' => 'Nama tag sudah ada.',
                    'en' => 'Tag name already exists.',
                ],
            ], 422);
        }

        $tag = Tag::create([
            'user_id' => $request->user()->id,
            'name' => $request->validated('name'),
        ]);

        return $this->apiResponse->success(
            'Tag berhasil dibuat.',
            'Tag created successfully.',
            $tag->toArray(),
            201
        );
    }

    public function destroy(Request $request, Tag $tag): JsonResponse
    {
        abort_if($tag->user_id !== $request->user()->id, 403);

        $tag->delete();

        return $this->apiResponse->success(
            'Tag berhasil dihapus.',
            'Tag deleted successfully.'
        );
    }
}
