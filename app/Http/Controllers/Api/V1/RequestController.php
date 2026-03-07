<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Request\StoreRequestRequest;
use App\Models\Request as PurchaseRequest;
use App\Models\User;
use App\Services\Request\RequestService;
use App\Services\Request\RequestSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    public function __construct(
        private readonly RequestService $requestService,
        private readonly RequestSubmissionService $requestSubmissionService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Requests fetched successfully.',
            'data' => $this->requestService->listForUser($user),
        ]);
    }

    public function store(StoreRequestRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $createdRequest = $this->requestService->createDraft($user, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Request created successfully.',
            'data' => $createdRequest,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $purchaseRequest = PurchaseRequest::query()->findOrFail($id);
        $purchaseRequest = $this->requestService->showForUser($user, $purchaseRequest);

        return response()->json([
            'success' => true,
            'message' => 'Request fetched successfully.',
            'data' => $purchaseRequest,
        ]);
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $purchaseRequest = PurchaseRequest::query()->findOrFail($id);

        $submittedRequest = $this->requestSubmissionService->submit($user, $purchaseRequest);

        return response()->json([
            'success' => true,
            'message' => 'Request submitted successfully.',
            'data' => $submittedRequest,
        ]);
    }

    public function history(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $purchaseRequest = PurchaseRequest::query()->findOrFail($id);

        $history = $this->requestService->historyForUser($user, $purchaseRequest);

        return response()->json([
            'success' => true,
            'message' => 'Request history fetched successfully.',
            'data' => $history,
        ]);
    }

    public function activities(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $purchaseRequest = PurchaseRequest::query()->findOrFail($id);

        $activities = $this->requestService->activitiesForUser($user, $purchaseRequest);

        return response()->json([
            'success' => true,
            'message' => 'Request activities fetched successfully.',
            'data' => $activities,
        ]);
    }
}
