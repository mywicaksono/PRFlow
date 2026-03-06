<?php

declare(strict_types=1);

namespace App\Services\Request;

use App\Enums\ApprovalStatusEnum;
use App\Enums\RequestStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Approval;
use App\Models\Request as PurchaseRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestSubmissionService
{
    public function submit(User $actor, PurchaseRequest $request): PurchaseRequest
    {
        return DB::transaction(function () use ($actor, $request): PurchaseRequest {
            $request->refresh();

            if ($actor->role !== UserRoleEnum::ADMIN && $request->user_id !== $actor->id) {
                throw new AuthorizationException('Unauthorized.');
            }

            if ($request->status !== RequestStatusEnum::DRAFT) {
                throw ValidationException::withMessages([
                    'status' => ['Only draft request can be submitted.'],
                ]);
            }

            if ((float) $request->amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Amount must be greater than 0.'],
                ]);
            }

            if (trim((string) $request->description) === '') {
                throw ValidationException::withMessages([
                    'description' => ['Description is required.'],
                ]);
            }

            if ($request->department_id === null) {
                throw ValidationException::withMessages([
                    'department_id' => ['Department is required.'],
                ]);
            }

            $requiredRole = $this->resolveApprovalRoleByAmount((float) $request->amount);

            $approver = User::query()
                ->where('department_id', $request->department_id)
                ->where('role', $requiredRole->value)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            if ($approver === null) {
                throw ValidationException::withMessages([
                    'approver' => [sprintf('No active %s found in the same department.', $requiredRole->value)],
                ]);
            }

            $request->update([
                'status' => RequestStatusEnum::SUBMITTED,
                'submitted_at' => now(),
                'current_level' => 1,
                'completed_at' => null,
            ]);

            Approval::query()->create([
                'request_id' => $request->id,
                'approver_id' => $approver->id,
                'level' => 1,
                'status' => ApprovalStatusEnum::PENDING,
                'notes' => null,
                'approved_at' => null,
            ]);

            return $request->fresh();
        });
    }

    private function resolveApprovalRoleByAmount(float $amount): UserRoleEnum
    {
        if ($amount < 5000000) {
            return UserRoleEnum::SUPERVISOR;
        }

        if ($amount <= 20000000) {
            return UserRoleEnum::MANAGER;
        }

        return UserRoleEnum::FINANCE;
    }
}
