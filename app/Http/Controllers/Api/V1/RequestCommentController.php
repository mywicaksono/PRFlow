<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Request\StoreRequestCommentRequest;
use App\Models\Request as PurchaseRequest;
use App\Models\User;
use App\Services\Request\RequestCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequestCommentController extends Controller
{
    public function __construct(
        private readonly RequestCommentService $requestCommentService
    ) {
    }

    public function index(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $purchaseRequest = PurchaseRequest::query()->findOrFail($id);

        $comments = $this->requestCommentService->commentsForUser($user, $purchaseRequest);

        return response()->json([
            'success' => true,
            'message' => 'Request comments fetched successfully.',
            'data' => $comments,
        ]);
    }

    public function store(StoreRequestCommentRequest $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $purchaseRequest = PurchaseRequest::query()->findOrFail($id);

        $comment = $this->requestCommentService->createComment(
            $user,
            $purchaseRequest,
            $request->string('comment')->toString()
        );

        return response()->json([
            'success' => true,
            'message' => 'Request comment added successfully.',
            'data' => $comment,
        ], 201);
    }
}
