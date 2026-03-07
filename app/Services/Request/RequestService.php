<?php

declare(strict_types=1);

namespace App\Services\Request;

use App\Enums\RequestStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Request as PurchaseRequest;
use App\Models\RequestActivity;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestService
{
    public function createDraft(User $user, array $data): PurchaseRequest
    {
        return DB::transaction(function () use ($user, $data): PurchaseRequest {
            $departmentId = $data['department_id'] ?? $user->department_id;

            if ($departmentId === null) {
                throw ValidationException::withMessages([
                    'department_id' => ['Department is required when user has no default department.'],
                ]);
            }

            $requestNumber = $this->generateRequestNumber();

            $request = PurchaseRequest::query()->create([
                'request_number' => $requestNumber,
                'user_id' => $user->id,
                'department_id' => $departmentId,
                'amount' => $data['amount'],
                'description' => $data['description'],
                'status' => RequestStatusEnum::DRAFT,
                'current_level' => 1,
                'submitted_at' => null,
                'completed_at' => null,
            ]);

            RequestActivity::query()->create([
                'request_id' => $request->id,
                'actor_id' => $user->id,
                'action' => 'request_created',
                'description' => 'Request created',
                'meta' => null,
            ]);

            return $request;
        });
    }

    public function listForUser(User $user): LengthAwarePaginator
    {
        $query = PurchaseRequest::query()->latest('id');

        if ($user->role !== UserRoleEnum::ADMIN) {
            $query->where('user_id', $user->id);
        }

        return $query->paginate(15);
    }

    public function showForUser(User $user, PurchaseRequest $request): PurchaseRequest
    {
        if (! $this->canAccessRequest($user, $request)) {
            abort(response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403));
        }

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    public function historyForUser(User $user, PurchaseRequest $request): array
    {
        $request->load([
            'approvals' => static fn ($query) => $query
                ->with('approver')
                ->orderBy('level')
                ->orderBy('created_at'),
        ]);

        if (! $this->canAccessRequest($user, $request)) {
            throw new AuthorizationException('Unauthorized.');
        }

        return [
            'request_id' => $request->id,
            'request_number' => $request->request_number,
            'status' => $request->status->value,
            'history' => $request->approvals->map(static fn ($approval): array => [
                'level' => $approval->level,
                'approver_id' => $approval->approver_id,
                'approver_role' => $approval->approver?->role?->value,
                'status' => $approval->status->value,
                'notes' => $approval->notes,
                'approved_at' => $approval->approved_at?->toISOString(),
            ])->values()->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function activitiesForUser(User $user, PurchaseRequest $request): array
    {
        $request->load([
            'activities' => static fn ($query) => $query
                ->with('actor')
                ->orderBy('created_at'),
        ]);

        if (! $this->canAccessRequest($user, $request)) {
            throw new AuthorizationException('Unauthorized.');
        }

        return $request->activities->map(static fn ($activity): array => [
            'id' => $activity->id,
            'request_id' => $activity->request_id,
            'actor_id' => $activity->actor_id,
            'action' => $activity->action,
            'description' => $activity->description,
            'meta' => $activity->meta,
            'created_at' => $activity->created_at?->toISOString(),
        ])->values()->all();
    }

    private function canAccessRequest(User $user, PurchaseRequest $request): bool
    {
        if ($user->role === UserRoleEnum::ADMIN || $request->user_id === $user->id) {
            return true;
        }

        if (! $request->relationLoaded('approvals')) {
            $request->load('approvals');
        }

        return $request->approvals->contains(
            static fn ($approval): bool => $approval->approver_id === $user->id
        );
    }

    private function generateRequestNumber(): string
    {
        $period = now()->format('Ym');
        $prefix = sprintf('PRF-%s-', $period);

        $lastInPeriod = PurchaseRequest::query()
            ->where('request_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        $sequence = 1;

        if ($lastInPeriod !== null) {
            $lastSequence = (int) substr($lastInPeriod->request_number, -4);
            $sequence = $lastSequence + 1;
        }

        return sprintf('%s%04d', $prefix, $sequence);
    }
}
