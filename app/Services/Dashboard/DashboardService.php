<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\ApprovalStatusEnum;
use App\Enums\RequestStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Approval;
use App\Models\Request as PurchaseRequest;
use App\Models\RequestNotification;
use App\Models\User;

class DashboardService
{
    /**
     * @return array<string, int>
     */
    public function summaryForUser(User $user): array
    {
        if ($user->role === UserRoleEnum::ADMIN) {
            return $this->adminSummary();
        }

        if ($this->isApproverRole($user)) {
            return $this->approverSummary($user);
        }

        return $this->staffSummary($user);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentRequestsForUser(User $user): array
    {
        if ($user->role === UserRoleEnum::ADMIN) {
            $requests = PurchaseRequest::query()
                ->latest('id')
                ->limit(5)
                ->get();

            return $this->transformRequests($requests->all());
        }

        if ($this->isApproverRole($user)) {
            $requests = PurchaseRequest::query()
                ->whereHas('approvals', static function ($query) use ($user): void {
                    $query->where('approver_id', $user->id);
                })
                ->with(['approvals' => static function ($query) use ($user): void {
                    $query->where('approver_id', $user->id);
                }])
                ->orderByRaw("CASE WHEN EXISTS (
                    SELECT 1 FROM approvals a
                    WHERE a.request_id = requests.id
                        AND a.approver_id = ?
                        AND a.status = ?
                        AND a.level = requests.current_level
                ) THEN 0 ELSE 1 END", [$user->id, ApprovalStatusEnum::PENDING->value])
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            return $this->transformRequests($requests->all());
        }

        $requests = PurchaseRequest::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(5)
            ->get();

        return $this->transformRequests($requests->all());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentNotificationsForUser(User $user): array
    {
        $notifications = RequestNotification::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(5)
            ->get();

        return $notifications->map(static fn (RequestNotification $notification): array => [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'is_read' => $notification->is_read,
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ])->values()->all();
    }

    /**
     * @return array<string, int>
     */
    private function staffSummary(User $user): array
    {
        $baseQuery = PurchaseRequest::query()->where('user_id', $user->id);

        return [
            'total_requests' => (clone $baseQuery)->count(),
            'draft_requests' => (clone $baseQuery)->where('status', RequestStatusEnum::DRAFT)->count(),
            'submitted_requests' => (clone $baseQuery)->where('status', RequestStatusEnum::SUBMITTED)->count(),
            'approved_requests' => (clone $baseQuery)->where('status', RequestStatusEnum::APPROVED)->count(),
            'rejected_requests' => (clone $baseQuery)->where('status', RequestStatusEnum::REJECTED)->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function approverSummary(User $user): array
    {
        $approvalBase = Approval::query()->where('approver_id', $user->id);

        $pendingApprovals = (clone $approvalBase)
            ->where('status', ApprovalStatusEnum::PENDING)
            ->whereHas('request', static function ($query): void {
                $query->where('status', RequestStatusEnum::SUBMITTED)
                    ->whereColumn('approvals.level', 'requests.current_level');
            })
            ->count();

        return [
            'pending_approvals' => $pendingApprovals,
            'approved_by_me' => (clone $approvalBase)->where('status', ApprovalStatusEnum::APPROVED)->count(),
            'rejected_by_me' => (clone $approvalBase)->where('status', ApprovalStatusEnum::REJECTED)->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function adminSummary(): array
    {
        $requestBase = PurchaseRequest::query();

        $totalPendingApprovals = Approval::query()
            ->where('status', ApprovalStatusEnum::PENDING)
            ->whereHas('request', static function ($query): void {
                $query->where('status', RequestStatusEnum::SUBMITTED)
                    ->whereColumn('approvals.level', 'requests.current_level');
            })
            ->count();

        return [
            'total_requests' => (clone $requestBase)->count(),
            'total_submitted_requests' => (clone $requestBase)->where('status', RequestStatusEnum::SUBMITTED)->count(),
            'total_approved_requests' => (clone $requestBase)->where('status', RequestStatusEnum::APPROVED)->count(),
            'total_rejected_requests' => (clone $requestBase)->where('status', RequestStatusEnum::REJECTED)->count(),
            'total_pending_approvals' => $totalPendingApprovals,
        ];
    }

    private function isApproverRole(User $user): bool
    {
        return in_array($user->role, [
            UserRoleEnum::SUPERVISOR,
            UserRoleEnum::MANAGER,
            UserRoleEnum::FINANCE,
        ], true);
    }

    /**
     * @param  array<int, PurchaseRequest>  $requests
     * @return array<int, array<string, mixed>>
     */
    private function transformRequests(array $requests): array
    {
        return collect($requests)->map(static fn (PurchaseRequest $request): array => [
            'id' => $request->id,
            'request_number' => $request->request_number,
            'status' => $request->status->value,
            'amount' => (float) $request->amount,
            'current_level' => $request->current_level,
            'submitted_at' => $request->submitted_at?->toISOString(),
            'completed_at' => $request->completed_at?->toISOString(),
        ])->values()->all();
    }
}
