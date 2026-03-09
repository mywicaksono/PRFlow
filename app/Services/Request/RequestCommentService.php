<?php

declare(strict_types=1);

namespace App\Services\Request;

use App\Enums\ApprovalStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Request as PurchaseRequest;
use App\Models\RequestActivity;
use App\Models\RequestComment;
use App\Models\User;
use App\Services\Notification\RequestNotificationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class RequestCommentService
{
    public function __construct(
        private readonly RequestNotificationService $requestNotificationService
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function commentsForUser(User $user, PurchaseRequest $request): array
    {
        $this->ensureCanAccess($user, $request);

        $request->load([
            'comments' => static fn ($query) => $query
                ->with('user')
                ->orderBy('created_at'),
        ]);

        return $request->comments->map(static fn (RequestComment $comment): array => [
            'id' => $comment->id,
            'comment' => $comment->comment,
            'user' => [
                'id' => $comment->user?->id,
                'role' => $comment->user?->role?->value,
                'email' => $comment->user?->email,
            ],
            'created_at' => $comment->created_at?->toISOString(),
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function createComment(User $user, PurchaseRequest $request, string $comment): array
    {
        $this->ensureCanAccess($user, $request);

        return DB::transaction(function () use ($user, $request, $comment): array {
            $created = RequestComment::query()->create([
                'request_id' => $request->id,
                'user_id' => $user->id,
                'comment' => $comment,
            ]);

            RequestActivity::query()->create([
                'request_id' => $request->id,
                'actor_id' => $user->id,
                'action' => 'request_comment_added',
                'description' => 'Request comment added',
                'meta' => [
                    'comment_id' => $created->id,
                ],
            ]);

            $this->requestNotificationService->notifyCommentAdded($request->fresh(['approvals']), $user, $created->id);

            $created->load('user');

            return [
                'id' => $created->id,
                'comment' => $created->comment,
                'user' => [
                    'id' => $created->user?->id,
                    'role' => $created->user?->role?->value,
                    'email' => $created->user?->email,
                ],
                'created_at' => $created->created_at?->toISOString(),
            ];
        });
    }

    private function ensureCanAccess(User $user, PurchaseRequest $request): void
    {
        if ($user->role === UserRoleEnum::ADMIN || $request->user_id === $user->id) {
            return;
        }

        $request->loadMissing('approvals');

        $canAccess = $request->approvals->contains(
            static fn ($approval): bool => $approval->approver_id === $user->id
        );

        if (! $canAccess) {
            throw new AuthorizationException('Unauthorized.');
        }
    }
}
