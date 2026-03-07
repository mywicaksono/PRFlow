<?php

declare(strict_types=1);

namespace App\Services\Request;

use App\Enums\ApprovalStatusEnum;
use App\Enums\RequestStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Approval;
use App\Models\Request as PurchaseRequest;
use App\Models\RequestActivity;
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

            $approvalRoles = $this->resolveApprovalRolesByAmount((float) $request->amount);

            $approvers = collect($approvalRoles)->map(function (UserRoleEnum $role) use ($request): User {
                $approver = User::query()
                    ->where('department_id', $request->department_id)
                    ->where('role', $role->value)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->first();

                if ($approver === null) {
                    throw ValidationException::withMessages([
                        'approver' => [sprintf('No active %s found in the same department.', $role->value)],
                    ]);
                }

                return $approver;
            });

            $request->update([
                'status' => RequestStatusEnum::SUBMITTED,
                'submitted_at' => now(),
                'current_level' => 1,
                'completed_at' => null,
            ]);

            foreach ($approvers as $index => $approver) {
                Approval::query()->create([
                    'request_id' => $request->id,
                    'approver_id' => $approver->id,
                    'level' => $index + 1,
                    'status' => ApprovalStatusEnum::PENDING,
                    'notes' => null,
                    'approved_at' => null,
                ]);
            }

            RequestActivity::query()->create([
                'request_id' => $request->id,
                'actor_id' => $actor->id,
                'action' => 'request_submitted',
                'description' => 'Request submitted',
                'meta' => [
                    'current_level' => 1,
                ],
            ]);

            return $request->fresh();
        });
    }

    /**
     * @return array<int, UserRoleEnum>
     */
    private function resolveApprovalRolesByAmount(float $amount): array
    {
        if ($amount < 5000000) {
            return [UserRoleEnum::SUPERVISOR];
        }

        if ($amount <= 20000000) {
            return [UserRoleEnum::SUPERVISOR, UserRoleEnum::MANAGER];
        }

        return [UserRoleEnum::SUPERVISOR, UserRoleEnum::MANAGER, UserRoleEnum::FINANCE];
    }
}