<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Request\UploadAttachmentRequest;
use App\Models\Request as PurchaseRequest;
use App\Models\RequestAttachment;
use App\Models\User;
use App\Services\Request\RequestAttachmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RequestAttachmentController extends Controller
{
    public function __construct(
        private readonly RequestAttachmentService $requestAttachmentService
    ) {
    }

    public function index(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $purchaseRequest = PurchaseRequest::query()->findOrFail($id);

        try {
            $attachments = $this->requestAttachmentService->listForUser($user, $purchaseRequest);
        } catch (AuthorizationException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Request attachments fetched successfully.',
            'data' => $attachments,
        ]);
    }

    public function store(UploadAttachmentRequest $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $purchaseRequest = PurchaseRequest::query()->findOrFail($id);

        try {
            $attachment = $this->requestAttachmentService->uploadForUser(
                $user,
                $purchaseRequest,
                $request->file('file')
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
            'message' => 'Attachment uploaded successfully.',
            'data' => $this->requestAttachmentService->transformAttachment($attachment),
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $attachment = RequestAttachment::query()->findOrFail($id);

        try {
            $this->requestAttachmentService->deleteForUser($user, $attachment);
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
            'message' => 'Attachment deleted successfully.',
            'data' => null,
        ]);
    }
}
