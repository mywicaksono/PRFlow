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

class RequestHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_see_approval_history_of_own_request(): void
    {
        $departmentId = $this->createDepartment('IT');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'owner-history@example.com');
        $supervisor = $this->createUser($departmentId, UserRoleEnum::SUPERVISOR, 'sup-history@example.com');

        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::APPROVED, 'PRF-202603-0001');
        $this->createApproval($request->id, $supervisor->id, ApprovalStatusEnum::APPROVED, 1);

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/requests/'.$request->id.'/history')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Request history fetched successfully.')
            ->assertJsonPath('data.request_id', $request->id)
            ->assertJsonPath('data.request_number', 'PRF-202603-0001')
            ->assertJsonPath('data.status', RequestStatusEnum::APPROVED->value)
            ->assertJsonPath('data.history.0.level', 1)
            ->assertJsonPath('data.history.0.approver_id', $supervisor->id)
            ->assertJsonPath('data.history.0.approver_role', UserRoleEnum::SUPERVISOR->value)
            ->assertJsonPath('data.history.0.status', ApprovalStatusEnum::APPROVED->value);
    }

    public function test_approver_can_see_history(): void
    {
        $departmentId = $this->createDepartment('Ops');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'owner2-history@example.com');
        $manager = $this->createUser($departmentId, UserRoleEnum::MANAGER, 'manager-history@example.com');

        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::SUBMITTED, 'PRF-202603-0002');
        $this->createApproval($request->id, $manager->id, ApprovalStatusEnum::PENDING, 1);

        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/requests/'.$request->id.'/history')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.request_id', $request->id)
            ->assertJsonPath('data.history.0.approver_role', UserRoleEnum::MANAGER->value);
    }

    public function test_unauthorized_user_cannot_access_history(): void
    {
        $departmentId = $this->createDepartment('HR');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'owner3-history@example.com');
        $other = $this->createUser($departmentId, UserRoleEnum::STAFF, 'other-history@example.com');
        $supervisor = $this->createUser($departmentId, UserRoleEnum::SUPERVISOR, 'sup2-history@example.com');

        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::SUBMITTED, 'PRF-202603-0003');
        $this->createApproval($request->id, $supervisor->id, ApprovalStatusEnum::PENDING, 1);

        Sanctum::actingAs($other);

        $this->getJson('/api/v1/requests/'.$request->id.'/history')
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
    }

    public function test_empty_history_when_request_has_not_been_submitted(): void
    {
        $departmentId = $this->createDepartment('Sales');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'owner4-history@example.com');

        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202603-0004');

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/requests/'.$request->id.'/history')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.request_id', $request->id)
            ->assertJsonPath('data.status', RequestStatusEnum::DRAFT->value)
            ->assertJsonPath('data.history', []);
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
            'amount' => 10000,
            'description' => 'History request',
            'status' => $status,
            'current_level' => 1,
            'submitted_at' => $status === RequestStatusEnum::DRAFT ? null : now(),
            'completed_at' => $status->isFinal() ? now() : null,
        ]);
    }

    private function createApproval(int $requestId, int $approverId, ApprovalStatusEnum $status, int $level): Approval
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
