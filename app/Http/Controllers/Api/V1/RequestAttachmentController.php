<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Request\UploadAttachmentRequest;
use App\Models\Request as PurchaseRequest;
use App\Models\RequestAttachment;
use App\Models\User;
use App\Services\Request\RequestAttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $attachments = $this->requestAttachmentService->listForUser($user, $purchaseRequest);

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

        $attachment = $this->requestAttachmentService->uploadForUser(
            $user,
            $purchaseRequest,
            $request->file('file')
        );

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

        $this->requestAttachmentService->deleteForUser($user, $attachment);

        return response()->json([
            'success' => true,
            'message' => 'Attachment deleted successfully.',
            'data' => null,
        ]);
    }
}
