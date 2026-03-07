<?php

declare(strict_types=1);

namespace App\Services\Request;

use App\Enums\RequestStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Request as PurchaseRequest;
use App\Models\RequestActivity;
use App\Models\RequestAttachment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RequestAttachmentService
{
    public function uploadForUser(User $user, PurchaseRequest $request, UploadedFile $file): RequestAttachment
    {
        if (! $this->canManageAttachments($user, $request)) {
            throw new AuthorizationException('Unauthorized.');
        }

        if ($request->status !== RequestStatusEnum::DRAFT) {
            throw ValidationException::withMessages([
                'request' => ['Attachments can only be modified while request is draft.'],
            ]);
        }

        return DB::transaction(function () use ($user, $request, $file): RequestAttachment {
            $disk = (string) config('filesystems.default', 'local');
            $storedPath = $file->store('request-attachments/'.$request->id, $disk);

            $attachment = RequestAttachment::query()->create([
                'request_id' => $request->id,
                'uploaded_by' => $user->id,
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => basename($storedPath),
                'disk' => $disk,
                'path' => $storedPath,
                'mime_type' => (string) $file->getMimeType(),
                'size' => $file->getSize(),
            ]);

            RequestActivity::query()->create([
                'request_id' => $request->id,
                'actor_id' => $user->id,
                'action' => 'request_attachment_uploaded',
                'description' => 'Request attachment uploaded',
                'meta' => [
                    'attachment_id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                ],
            ]);

            return $attachment->fresh(['uploader']);
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(User $user, PurchaseRequest $request): array
    {
        if (! $this->canViewAttachments($user, $request)) {
            throw new AuthorizationException('Unauthorized.');
        }

        $request->load([
            'attachments' => static fn ($query) => $query->with('uploader')->orderByDesc('id'),
        ]);

        return $request->attachments->map(static fn (RequestAttachment $attachment): array => [
            'id' => $attachment->id,
            'request_id' => $attachment->request_id,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'uploaded_by' => $attachment->uploaded_by,
            'uploader' => [
                'id' => $attachment->uploader?->id,
                'email' => $attachment->uploader?->email,
                'role' => $attachment->uploader?->role?->value,
            ],
            'created_at' => $attachment->created_at?->toISOString(),
        ])->values()->all();
    }

    public function deleteForUser(User $user, RequestAttachment $attachment): void
    {
        $attachment->loadMissing('request');

        if (! $this->canManageAttachments($user, $attachment->request)) {
            throw new AuthorizationException('Unauthorized.');
        }

        if ($attachment->request->status !== RequestStatusEnum::DRAFT) {
            throw ValidationException::withMessages([
                'request' => ['Attachments can only be modified while request is draft.'],
            ]);
        }

        DB::transaction(function () use ($user, $attachment): void {
            Storage::disk($attachment->disk)->delete($attachment->path);

            RequestActivity::query()->create([
                'request_id' => $attachment->request_id,
                'actor_id' => $user->id,
                'action' => 'request_attachment_deleted',
                'description' => 'Request attachment deleted',
                'meta' => [
                    'attachment_id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                ],
            ]);

            $attachment->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function transformAttachment(RequestAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'request_id' => $attachment->request_id,
            'original_name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'size' => $attachment->size,
            'uploaded_by' => $attachment->uploaded_by,
            'created_at' => $attachment->created_at?->toISOString(),
        ];
    }

    private function canManageAttachments(User $user, PurchaseRequest $request): bool
    {
        if ($user->role === UserRoleEnum::ADMIN) {
            return true;
        }

        return $request->user_id === $user->id;
    }

    private function canViewAttachments(User $user, PurchaseRequest $request): bool
    {
        if ($this->canManageAttachments($user, $request)) {
            return true;
        }

        $request->loadMissing('approvals');

        return $request->approvals->contains(
            static fn ($approval): bool => $approval->approver_id === $user->id
        );
    }
}
