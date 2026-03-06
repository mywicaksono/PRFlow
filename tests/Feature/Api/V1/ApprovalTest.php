<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ApprovalStatusEnum;
use App\Enums\RequestStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Approval;
use App\Models\Request as PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_view_own_pending_approvals(): void
    {
        $departmentId = $this->createDepartment('IT');
        $owner = $this->createStaff($departmentId, 'owner1@example.com');
        $supervisor = $this->createSupervisor($departmentId, 'supervisor1@example.com');

        $pendingRequest = $this->createSubmittedRequest($owner->id, $departmentId, 'PRF-202601-0001');
        $this->createPendingApproval($pendingRequest->id, $supervisor->id);

        $otherSupervisor = $this->createSupervisor($departmentId, 'supervisor2@example.com');
        $otherRequest = $this->createSubmittedRequest($owner->id, $departmentId, 'PRF-202601-0002');
        $this->createPendingApproval($otherRequest->id, $otherSupervisor->id);

        Sanctum::actingAs($supervisor);

        $response = $this->getJson('/api/v1/approvals/pending');

        $response->assertOk()->assertJsonPath('success', true);
        $items = $response->json('data.data');

        $this->assertCount(1, $items);
        $this->assertSame($pendingRequest->id, $items[0]['request_id']);
    }

    public function test_staff_cannot_access_approval_endpoints(): void
    {
        $departmentId = $this->createDepartment('Ops');
        $staff = $this->createStaff($departmentId, 'staff1@example.com');

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/approvals/pending')
            ->assertForbidden();
    }

    public function test_assigned_approver_can_approve_submitted_request(): void
    {
        $departmentId = $this->createDepartment('Finance');
        $owner = $this->createStaff($departmentId, 'owner2@example.com');
        $supervisor = $this->createSupervisor($departmentId, 'supervisor3@example.com');

        $request = $this->createSubmittedRequest($owner->id, $departmentId, 'PRF-202601-0003');
        $approval = $this->createPendingApproval($request->id, $supervisor->id);

        Sanctum::actingAs($supervisor);

        $this->postJson('/api/v1/approvals/'.$request->id.'/approve')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('approvals', [
            'id' => $approval->id,
            'status' => ApprovalStatusEnum::APPROVED->value,
        ]);
    }

    public function test_assigned_approver_can_reject_submitted_request_with_reason(): void
    {
        $departmentId = $this->createDepartment('Sales');
        $owner = $this->createStaff($departmentId, 'owner3@example.com');
        $supervisor = $this->createSupervisor($departmentId, 'supervisor4@example.com');

        $request = $this->createSubmittedRequest($owner->id, $departmentId, 'PRF-202601-0004');
        $approval = $this->createPendingApproval($request->id, $supervisor->id);

        Sanctum::actingAs($supervisor);

        $this->postJson('/api/v1/approvals/'.$request->id.'/reject', [
            'reason' => 'Budget not sufficient',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('approvals', [
            'id' => $approval->id,
            'status' => ApprovalStatusEnum::REJECTED->value,
            'notes' => 'Budget not sufficient',
        ]);
    }

    public function test_request_status_becomes_approved_after_approve(): void
    {
        $departmentId = $this->createDepartment('Legal');
        $owner = $this->createStaff($departmentId, 'owner4@example.com');
        $supervisor = $this->createSupervisor($departmentId, 'supervisor5@example.com');

        $request = $this->createSubmittedRequest($owner->id, $departmentId, 'PRF-202601-0005');
        $this->createPendingApproval($request->id, $supervisor->id);

        Sanctum::actingAs($supervisor);

        $this->postJson('/api/v1/approvals/'.$request->id.'/approve')->assertOk();

        $this->assertDatabaseHas('requests', [
            'id' => $request->id,
            'status' => RequestStatusEnum::APPROVED->value,
            'current_level' => 1,
        ]);

        $this->assertNotNull(PurchaseRequest::query()->findOrFail($request->id)->completed_at);
    }

    public function test_request_status_becomes_rejected_after_reject(): void
    {
        $departmentId = $this->createDepartment('HR');
        $owner = $this->createStaff($departmentId, 'owner5@example.com');
        $supervisor = $this->createSupervisor($departmentId, 'supervisor6@example.com');

        $request = $this->createSubmittedRequest($owner->id, $departmentId, 'PRF-202601-0006');
        $this->createPendingApproval($request->id, $supervisor->id);

        Sanctum::actingAs($supervisor);

        $this->postJson('/api/v1/approvals/'.$request->id.'/reject', [
            'reason' => 'Invalid justification',
        ])->assertOk();

        $this->assertDatabaseHas('requests', [
            'id' => $request->id,
            'status' => RequestStatusEnum::REJECTED->value,
        ]);

        $this->assertNotNull(PurchaseRequest::query()->findOrFail($request->id)->completed_at);
    }

    public function test_non_assigned_approver_cannot_approve_request(): void
    {
        $departmentId = $this->createDepartment('Warehouse');
        $owner = $this->createStaff($departmentId, 'owner6@example.com');
        $assigned = $this->createSupervisor($departmentId, 'supervisor7@example.com');
        $other = $this->createSupervisor($departmentId, 'supervisor8@example.com');

        $request = $this->createSubmittedRequest($owner->id, $departmentId, 'PRF-202601-0007');
        $this->createPendingApproval($request->id, $assigned->id);

        Sanctum::actingAs($other);

        $this->postJson('/api/v1/approvals/'.$request->id.'/approve')
            ->assertForbidden()
            ->assertJsonPath('message', 'Unauthorized.');
    }

    public function test_cannot_approve_already_processed_request(): void
    {
        $departmentId = $this->createDepartment('Marketing');
        $owner = $this->createStaff($departmentId, 'owner7@example.com');
        $supervisor = $this->createSupervisor($departmentId, 'supervisor9@example.com');

        $request = $this->createSubmittedRequest($owner->id, $departmentId, 'PRF-202601-0008', [
            'status' => RequestStatusEnum::APPROVED,
        ]);

        $this->createPendingApproval($request->id, $supervisor->id);

        Sanctum::actingAs($supervisor);

        $this->postJson('/api/v1/approvals/'.$request->id.'/approve')
            ->assertStatus(422)
            ->assertJsonPath('errors.request.0', 'Only submitted request can be approved.');
    }

    public function test_reject_requires_reason(): void
    {
        $departmentId = $this->createDepartment('Admin');
        $owner = $this->createStaff($departmentId, 'owner8@example.com');
        $supervisor = $this->createSupervisor($departmentId, 'supervisor10@example.com');

        $request = $this->createSubmittedRequest($owner->id, $departmentId, 'PRF-202601-0009');
        $this->createPendingApproval($request->id, $supervisor->id);

        Sanctum::actingAs($supervisor);

        $this->postJson('/api/v1/approvals/'.$request->id.'/reject', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.reason.0', 'The reason field is required.');
    }

    public function test_admin_can_list_all_pending_approvals(): void
    {
        $departmentA = $this->createDepartment('DeptA');
        $departmentB = $this->createDepartment('DeptB');

        $ownerA = $this->createStaff($departmentA, 'owner9@example.com');
        $ownerB = $this->createStaff($departmentB, 'owner10@example.com');

        $supervisorA = $this->createSupervisor($departmentA, 'supervisor11@example.com');
        $supervisorB = $this->createSupervisor($departmentB, 'supervisor12@example.com');

        $requestA = $this->createSubmittedRequest($ownerA->id, $departmentA, 'PRF-202601-0010');
        $requestB = $this->createSubmittedRequest($ownerB->id, $departmentB, 'PRF-202601-0011');

        $this->createPendingApproval($requestA->id, $supervisorA->id);
        $this->createPendingApproval($requestB->id, $supervisorB->id);

        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
            'is_active' => true,
            'email' => 'admin.approval@example.com',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/approvals/pending');

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertCount(2, $response->json('data.data'));
    }

    private function createDepartment(string $name): int
    {
        return (int) DB::table('departments')->insertGetId([
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createStaff(int $departmentId, string $email): User
    {
        return User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
            'is_active' => true,
            'email' => $email,
        ]);
    }

    private function createSupervisor(int $departmentId, string $email): User
    {
        return User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::SUPERVISOR,
            'is_active' => true,
            'email' => $email,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSubmittedRequest(int $userId, int $departmentId, string $number, array $overrides = []): PurchaseRequest
    {
        return PurchaseRequest::query()->create(array_merge([
            'request_number' => $number,
            'user_id' => $userId,
            'department_id' => $departmentId,
            'amount' => 15000,
            'description' => 'Submitted request',
            'status' => RequestStatusEnum::SUBMITTED,
            'current_level' => 1,
            'submitted_at' => now(),
            'completed_at' => null,
        ], $overrides));
    }

    private function createPendingApproval(int $requestId, int $approverId): Approval
    {
        return Approval::query()->create([
            'request_id' => $requestId,
            'approver_id' => $approverId,
            'level' => 1,
            'status' => ApprovalStatusEnum::PENDING,
            'notes' => null,
            'approved_at' => null,
        ]);
    }
}