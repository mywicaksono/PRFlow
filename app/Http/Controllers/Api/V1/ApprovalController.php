<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Approval\RejectApprovalRequest;
use App\Models\Request as PurchaseRequest;
use App\Models\User;
use App\Services\Approval\ApprovalActionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly ApprovalActionService $approvalActionService
    ) {
    }

    public function pending(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Pending approvals fetched successfully.',
            'data' => $this->approvalActionService->pendingForUser($user),
        ]);
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $updated = $this->approvalActionService->approve($user, $purchaseRequest);
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
            'message' => 'Request approved successfully.',
            'data' => $updated,
        ]);
    }

    public function reject(RejectApprovalRequest $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $updated = $this->approvalActionService->reject(
                $user,
                $purchaseRequest,
                $request->string('reason')->toString()
            );
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
            'message' => 'Request rejected successfully.',
            'data' => $updated,
        ]);
    }
}
