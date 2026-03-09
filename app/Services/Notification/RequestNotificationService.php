<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Enums\ApprovalStatusEnum;
use App\Enums\RequestStatusEnum;
use App\Models\Request as PurchaseRequest;
use App\Models\RequestNotification;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RequestNotificationService
{
    public function notifySubmitted(PurchaseRequest $request): void
    {
        $request->loadMissing('approvals');

        $approval = $request->approvals
            ->where('level', $request->current_level)
            ->first();

        if ($approval === null) {
            return;
        }

        RequestNotification::query()->create([
            'request_id' => $request->id,
            'user_id' => $approval->approver_id,
            'type' => 'request_submitted',
            'title' => 'Request needs your approval',
            'message' => sprintf('Request %s requires your approval.', $request->request_number),
            'is_read' => false,
            'read_at' => null,
            'meta' => [
                'level' => $request->current_level,
            ],
        ]);
    }

    public function notifyApproved(PurchaseRequest $request, User $actor, int $level): void
    {
        if ($request->status === RequestStatusEnum::SUBMITTED) {
            $request->loadMissing('approvals');

            $nextApproval = $request->approvals
                ->where('level', $request->current_level)
                ->first();

            if ($nextApproval === null) {
                return;
            }

            RequestNotification::query()->create([
                'request_id' => $request->id,
                'user_id' => $nextApproval->approver_id,
                'type' => 'request_approval_required',
                'title' => 'Request moved to your approval level',
                'message' => sprintf('Request %s is now waiting for your approval.', $request->request_number),
                'is_read' => false,
                'read_at' => null,
                'meta' => [
                    'level' => $request->current_level,
                    'approved_by' => $actor->id,
                    'previous_level' => $level,
                ],
            ]);

            return;
        }

        if ($request->status === RequestStatusEnum::APPROVED) {
            RequestNotification::query()->create([
                'request_id' => $request->id,
                'user_id' => $request->user_id,
                'type' => 'request_fully_approved',
                'title' => 'Request fully approved',
                'message' => sprintf('Request %s has been fully approved.', $request->request_number),
                'is_read' => false,
                'read_at' => null,
                'meta' => [
                    'level' => $level,
                    'approved_by' => $actor->id,
                ],
            ]);
        }
    }

    public function notifyRejected(PurchaseRequest $request, User $actor, int $level, string $reason): void
    {
        RequestNotification::query()->create([
            'request_id' => $request->id,
            'user_id' => $request->user_id,
            'type' => 'request_rejected',
            'title' => 'Request rejected',
            'message' => sprintf('Request %s was rejected.', $request->request_number),
            'is_read' => false,
            'read_at' => null,
            'meta' => [
                'level' => $level,
                'approver_id' => $actor->id,
                'reason' => $reason,
            ],
        ]);
    }


    public function notifyCommentAdded(PurchaseRequest $request, User $actor, int $commentId): void
    {
        $request->loadMissing('approvals');

        $recipientIds = collect([$request->user_id]);

        $currentApproval = $request->approvals
            ->where('level', $request->current_level)
            ->where('status', ApprovalStatusEnum::PENDING)
            ->first();

        if ($currentApproval !== null) {
            $recipientIds->push($currentApproval->approver_id);
        }

        $recipientIds
            ->unique()
            ->filter(static fn (int $userId): bool => $userId !== $actor->id)
            ->each(function (int $userId) use ($request, $actor, $commentId): void {
                RequestNotification::query()->create([
                    'request_id' => $request->id,
                    'user_id' => $userId,
                    'type' => 'request_commented',
                    'title' => 'New request comment',
                    'message' => sprintf('New comment added to request %s.', $request->request_number),
                    'is_read' => false,
                    'read_at' => null,
                    'meta' => [
                        'comment_id' => $commentId,
                        'commented_by' => $actor->id,
                    ],
                ]);
            });
    }

    public function listForUser(User $user): LengthAwarePaginator
    {
        return RequestNotification::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->paginate(20);
    }

    public function markAsRead(User $user, int $id): RequestNotification
    {
        $notification = RequestNotification::query()->findOrFail($id);

        if ($notification->user_id !== $user->id) {
            throw new AuthorizationException('Unauthorized.');
        }

        $notification->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return $notification->fresh();
    }
}
