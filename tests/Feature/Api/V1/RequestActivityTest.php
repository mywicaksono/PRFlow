<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ApprovalStatusEnum;
use App\Enums\RequestStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Approval;
use App\Models\Request as PurchaseRequest;
use App\Models\RequestActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RequestActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_creation_creates_activity_log(): void
    {
        $departmentId = $this->createDepartment('Create Activity');
        $staff = $this->createUser($departmentId, UserRoleEnum::STAFF, 'create-activity@example.com');

        Sanctum::actingAs($staff);

        $response = $this->postJson('/api/v1/requests', [
            'amount' => 1000000,
            'description' => 'New request',
            'department_id' => $departmentId,
        ])->assertCreated();

        $requestId = (int) $response->json('data.id');

        $this->assertDatabaseHas('request_activities', [
            'request_id' => $requestId,
            'actor_id' => $staff->id,
            'action' => 'request_created',
            'description' => 'Request created',
        ]);
    }

    public function test_request_submission_creates_activity_log(): void
    {
        $departmentId = $this->createDepartment('Submit Activity');
        $staff = $this->createUser($departmentId, UserRoleEnum::STAFF, 'submit-activity@example.com');
        $this->createUser($departmentId, UserRoleEnum::SUPERVISOR, 'submit-supervisor@example.com');

        $request = $this->createRequest($staff->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202604-0001');

        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/requests/'.$request->id.'/submit')->assertOk();

        $this->assertDatabaseHas('request_activities', [
            'request_id' => $request->id,
            'actor_id' => $staff->id,
            'action' => 'request_submitted',
            'description' => 'Request submitted',
        ]);
    }

    public function test_approval_creates_activity_log(): void
    {
        $departmentId = $this->createDepartment('Approve Activity');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'approve-owner@example.com');
        $supervisor = $this->createUser($departmentId, UserRoleEnum::SUPERVISOR, 'approve-supervisor@example.com');

        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::SUBMITTED, 'PRF-202604-0002');
        $this->createApproval($request->id, $supervisor->id, 1, ApprovalStatusEnum::PENDING);

        Sanctum::actingAs($supervisor);

        $this->postJson('/api/v1/approvals/'.$request->id.'/approve')->assertOk();

        $this->assertDatabaseHas('request_activities', [
            'request_id' => $request->id,
            'actor_id' => $supervisor->id,
            'action' => 'request_approved',
            'description' => 'Request approved at level 1',
        ]);
    }

    public function test_reject_creates_activity_log(): void
    {
        $departmentId = $this->createDepartment('Reject Activity');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'reject-owner@example.com');
        $supervisor = $this->createUser($departmentId, UserRoleEnum::SUPERVISOR, 'reject-supervisor@example.com');

        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::SUBMITTED, 'PRF-202604-0003');
        $this->createApproval($request->id, $supervisor->id, 1, ApprovalStatusEnum::PENDING);

        Sanctum::actingAs($supervisor);

        $this->postJson('/api/v1/approvals/'.$request->id.'/reject', [
            'reason' => 'Budget not sufficient',
        ])->assertOk();

        $this->assertDatabaseHas('request_activities', [
            'request_id' => $request->id,
            'actor_id' => $supervisor->id,
            'action' => 'request_rejected',
            'description' => 'Request rejected at level 1',
        ]);
    }

    public function test_authorized_user_can_view_activities(): void
    {
        $departmentId = $this->createDepartment('Activity View');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'activity-owner@example.com');

        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202604-0004');
        RequestActivity::query()->create([
            'request_id' => $request->id,
            'actor_id' => $owner->id,
            'action' => 'request_created',
            'description' => 'Request created',
            'meta' => null,
        ]);

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/requests/'.$request->id.'/activities')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Request activities fetched successfully.')
            ->assertJsonPath('data.0.action', 'request_created')
            ->assertJsonPath('data.0.actor.id', $owner->id)
            ->assertJsonPath('data.0.actor.role', UserRoleEnum::STAFF->value)
            ->assertJsonPath('data.0.actor.email', 'activity-owner@example.com')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [[
                    'id',
                    'action',
                    'description',
                    'actor' => ['id', 'role', 'email'],
                    'meta',
                    'created_at',
                ]],
            ]);
    }

    public function test_unauthorized_user_cannot_view_activities(): void
    {
        $departmentId = $this->createDepartment('Activity Unauthorized');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'activity-owner2@example.com');
        $otherUser = $this->createUser($departmentId, UserRoleEnum::STAFF, 'activity-other@example.com');

        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202604-0005');
        RequestActivity::query()->create([
            'request_id' => $request->id,
            'actor_id' => $owner->id,
            'action' => 'request_created',
            'description' => 'Request created',
            'meta' => null,
        ]);

        Sanctum::actingAs($otherUser);

        $this->getJson('/api/v1/requests/'.$request->id.'/activities')
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
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

    private function createRequest(int $userId, int $departmentId, RequestStatusEnum $status, string $requestNumber): PurchaseRequest
    {
        return PurchaseRequest::query()->create([
            'request_number' => $requestNumber,
            'user_id' => $userId,
            'department_id' => $departmentId,
            'amount' => 1000000,
            'description' => 'Activity test request',
            'status' => $status,
            'current_level' => 1,
            'submitted_at' => $status === RequestStatusEnum::DRAFT ? null : now(),
            'completed_at' => null,
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
            'approved_at' => null,
        ]);
    }
}
