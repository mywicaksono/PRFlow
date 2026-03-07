<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ApprovalStatusEnum;
use App\Enums\RequestStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Approval;
use App\Models\Request as PurchaseRequest;
use App\Models\RequestNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_summary_counts(): void
    {
        $departmentId = $this->createDepartment('Dashboard Staff');
        $staff = $this->createUser($departmentId, UserRoleEnum::STAFF, 'dash-staff@example.com');

        $this->createRequest($staff->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202607-0001');
        $this->createRequest($staff->id, $departmentId, RequestStatusEnum::SUBMITTED, 'PRF-202607-0002');
        $this->createRequest($staff->id, $departmentId, RequestStatusEnum::APPROVED, 'PRF-202607-0003');
        $this->createRequest($staff->id, $departmentId, RequestStatusEnum::REJECTED, 'PRF-202607-0004');

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.total_requests', 4)
            ->assertJsonPath('data.draft_requests', 1)
            ->assertJsonPath('data.submitted_requests', 1)
            ->assertJsonPath('data.approved_requests', 1)
            ->assertJsonPath('data.rejected_requests', 1);
    }

    public function test_approver_summary_counts(): void
    {
        $departmentId = $this->createDepartment('Dashboard Approver');
        $approver = $this->createUser($departmentId, UserRoleEnum::SUPERVISOR, 'dash-approver@example.com');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'dash-owner@example.com');

        $submitted = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::SUBMITTED, 'PRF-202607-0010', 1);
        $approvedReq = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::APPROVED, 'PRF-202607-0011', 1);
        $rejectedReq = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::REJECTED, 'PRF-202607-0012', 1);

        $this->createApproval($submitted->id, $approver->id, 1, ApprovalStatusEnum::PENDING);
        $this->createApproval($approvedReq->id, $approver->id, 1, ApprovalStatusEnum::APPROVED);
        $this->createApproval($rejectedReq->id, $approver->id, 1, ApprovalStatusEnum::REJECTED);

        Sanctum::actingAs($approver);

        $this->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.pending_approvals', 1)
            ->assertJsonPath('data.approved_by_me', 1)
            ->assertJsonPath('data.rejected_by_me', 1);
    }

    public function test_admin_summary_counts(): void
    {
        $departmentId = $this->createDepartment('Dashboard Admin');
        $admin = $this->createUser($departmentId, UserRoleEnum::ADMIN, 'dash-admin@example.com');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'dash-admin-owner@example.com');
        $supervisor = $this->createUser($departmentId, UserRoleEnum::SUPERVISOR, 'dash-admin-supervisor@example.com');

        $submitted = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::SUBMITTED, 'PRF-202607-0020', 1);
        $approved = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::APPROVED, 'PRF-202607-0021', 1);
        $rejected = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::REJECTED, 'PRF-202607-0022', 1);

        $this->createApproval($submitted->id, $supervisor->id, 1, ApprovalStatusEnum::PENDING);
        $this->createApproval($approved->id, $supervisor->id, 1, ApprovalStatusEnum::APPROVED);
        $this->createApproval($rejected->id, $supervisor->id, 1, ApprovalStatusEnum::REJECTED);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.total_requests', 3)
            ->assertJsonPath('data.total_submitted_requests', 1)
            ->assertJsonPath('data.total_approved_requests', 1)
            ->assertJsonPath('data.total_rejected_requests', 1)
            ->assertJsonPath('data.total_pending_approvals', 1);
    }

    public function test_staff_recent_requests_only_show_own_requests(): void
    {
        $departmentId = $this->createDepartment('Dashboard Staff Recent');
        $staff = $this->createUser($departmentId, UserRoleEnum::STAFF, 'dash-staff-recent@example.com');
        $other = $this->createUser($departmentId, UserRoleEnum::STAFF, 'dash-other-recent@example.com');

        $own = $this->createRequest($staff->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202607-0030');
        $otherRequest = $this->createRequest($other->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202607-0031');

        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/v1/dashboard/recent-requests')
            ->assertOk()
            ->assertJsonPath('success', true);

        $ids = array_column($response->json('data'), 'id');

        $this->assertContains($own->id, $ids);
        $this->assertNotContains($otherRequest->id, $ids);
    }

    public function test_approver_recent_requests_show_related_approval_requests(): void
    {
        $departmentId = $this->createDepartment('Dashboard Approver Recent');
        $approver = $this->createUser($departmentId, UserRoleEnum::SUPERVISOR, 'dash-approver-recent@example.com');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'dash-owner-recent@example.com');

        $related = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::SUBMITTED, 'PRF-202607-0040', 1);
        $notRelated = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::SUBMITTED, 'PRF-202607-0041', 1);

        $this->createApproval($related->id, $approver->id, 1, ApprovalStatusEnum::PENDING);

        Sanctum::actingAs($approver);

        $response = $this->getJson('/api/v1/dashboard/recent-requests')
            ->assertOk()
            ->assertJsonPath('success', true);

        $ids = array_column($response->json('data'), 'id');

        $this->assertContains($related->id, $ids);
        $this->assertNotContains($notRelated->id, $ids);
    }

    public function test_recent_notifications_only_show_current_users_notifications(): void
    {
        $departmentId = $this->createDepartment('Dashboard Notifications');
        $user = $this->createUser($departmentId, UserRoleEnum::STAFF, 'dash-notif-user@example.com');
        $other = $this->createUser($departmentId, UserRoleEnum::STAFF, 'dash-notif-other@example.com');
        $request = $this->createRequest($user->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202607-0050');

        RequestNotification::query()->create([
            'request_id' => $request->id,
            'user_id' => $user->id,
            'type' => 'request_submitted',
            'title' => 'Mine',
            'message' => 'Mine',
            'is_read' => false,
            'read_at' => null,
            'meta' => null,
        ]);

        RequestNotification::query()->create([
            'request_id' => $request->id,
            'user_id' => $other->id,
            'type' => 'request_submitted',
            'title' => 'Other',
            'message' => 'Other',
            'is_read' => false,
            'read_at' => null,
            'meta' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard/recent-notifications')
            ->assertOk()
            ->assertJsonPath('success', true);

        $items = $response->json('data');
        $this->assertCount(1, $items);
        $this->assertSame('Mine', $items[0]['title']);
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson('/api/v1/dashboard/summary')->assertUnauthorized();
        $this->getJson('/api/v1/dashboard/recent-requests')->assertUnauthorized();
        $this->getJson('/api/v1/dashboard/recent-notifications')->assertUnauthorized();
    }

    private function createDepartment(string $name): int
    {
        return (int) DB::table('departments')->insertGetId([
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createUser(int $departmentId, UserRoleEnum $role, string $email): User
    {
        return User::factory()->create([
            'department_id' => $departmentId,
            'role' => $role,
            'email' => $email,
            'is_active' => true,
        ]);
    }

    private function createRequest(
        int $userId,
        int $departmentId,
        RequestStatusEnum $status,
        string $requestNumber,
        int $currentLevel = 1
    ): PurchaseRequest {
        return PurchaseRequest::query()->create([
            'request_number' => $requestNumber,
            'user_id' => $userId,
            'department_id' => $departmentId,
            'amount' => 1000000,
            'description' => 'Dashboard request',
            'status' => $status,
            'current_level' => $currentLevel,
            'submitted_at' => $status === RequestStatusEnum::DRAFT ? null : now(),
            'completed_at' => $status->isFinal() ? now() : null,
        ]);
    }

    private function createApproval(int $requestId, int $approverId, int $level, ApprovalStatusEnum $status): Approval
    {
        return Approval::query()->create([
            'request_id' => $requestId,
            'approver_id' => $approverId,
            'level' => $level,
            'status' => $status,
            'notes' => null,
            'approved_at' => $status === ApprovalStatusEnum::PENDING ? null : now(),
        ]);
    }
}
