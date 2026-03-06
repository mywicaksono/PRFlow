<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Request\StoreRequestRequest;
use App\Models\Request as PurchaseRequest;
use App\Models\User;
use App\Services\Request\RequestService;
use App\Services\Request\RequestSubmissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

        try {
            $createdRequest = $this->requestService->createDraft($user, $request->validated());
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $exception->errors(),
            ], 422);
        }

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

        try {
            $submittedRequest = $this->requestSubmissionService->submit($user, $purchaseRequest);
        } catch (AuthorizationException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $exception->errors(),
            ], 422);
        }

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

        try {
            $history = $this->requestService->historyForUser($user, $purchaseRequest);
        } catch (AuthorizationException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Request history fetched successfully.',
            'data' => $history,
        ]);
    }
}
