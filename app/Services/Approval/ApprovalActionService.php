<?php

declare(strict_types=1);

namespace App\Services\Approval;

use App\Enums\ApprovalStatusEnum;
use App\Enums\RequestStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Approval;
use App\Models\Request as PurchaseRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalActionService
{
    public function pendingForUser(User $user): LengthAwarePaginator
    {
        $approvalQuery = Approval::query()
            ->with(['request', 'request.user'])
            ->where('status', ApprovalStatusEnum::PENDING)
            ->whereHas('request', static fn ($query) => $query->where('status', RequestStatusEnum::SUBMITTED));

        if ($user->role !== UserRoleEnum::ADMIN) {
            $approvalQuery->where('approver_id', $user->id);
        }

        return $approvalQuery->latest('id')->paginate(15);
    }

    public function approve(User $actor, PurchaseRequest $request): PurchaseRequest
    {
        return DB::transaction(function () use ($actor, $request): PurchaseRequest {
            $request->refresh();

            if ($request->status !== RequestStatusEnum::SUBMITTED) {
                throw ValidationException::withMessages([
                    'request' => ['Only submitted request can be approved.'],
                ]);
            }

            $approval = $this->resolvePendingApproval($actor, $request);

            $approval->update([
                'status' => ApprovalStatusEnum::APPROVED,
                'approved_at' => now(),
            ]);

            $request->update([
                'status' => RequestStatusEnum::APPROVED,
                'current_level' => $approval->level,
                'completed_at' => now(),
            ]);

            return $request->fresh(['approvals']);
        });
    }

    public function reject(User $actor, PurchaseRequest $request, string $reason): PurchaseRequest
    {
        return DB::transaction(function () use ($actor, $request, $reason): PurchaseRequest {
            $request->refresh();

            if ($request->status !== RequestStatusEnum::SUBMITTED) {
                throw ValidationException::withMessages([
                    'request' => ['Only submitted request can be rejected.'],
                ]);
            }

            $approval = $this->resolvePendingApproval($actor, $request);

            $approval->update([
                'status' => ApprovalStatusEnum::REJECTED,
                'approved_at' => now(),
                'notes' => $reason,
            ]);

            $request->update([
                'status' => RequestStatusEnum::REJECTED,
                'current_level' => $approval->level,
                'completed_at' => now(),
            ]);

            return $request->fresh(['approvals']);
        });
    }

    private function resolvePendingApproval(User $actor, PurchaseRequest $request): Approval
    {
        if ($actor->role === UserRoleEnum::ADMIN) {
            $approval = Approval::query()
                ->where('request_id', $request->id)
                ->where('status', ApprovalStatusEnum::PENDING)
                ->orderBy('id')
                ->first();
        } else {
            $approval = Approval::query()
                ->where('request_id', $request->id)
                ->where('approver_id', $actor->id)
                ->where('status', ApprovalStatusEnum::PENDING)
                ->first();
        }

        if ($approval === null) {
            throw new AuthorizationException('Unauthorized.');
        }

        return $approval;
    }
}
