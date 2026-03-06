<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ApprovalStatusEnum;
use App\Enums\RequestStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Request as PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RequestSubmitTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_submit_own_draft_request(): void
    {
        $departmentId = $this->createDepartment('IT');

        $staff = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
        ]);

        $supervisor = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::SUPERVISOR,
            'is_active' => true,
            'email' => 'supervisor1@example.com',
        ]);

        $request = $this->createDraftRequest($staff->id, $departmentId);

        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/requests/'.$request->id.'/submit')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Request submitted successfully.');

        $this->assertDatabaseHas('approvals', [
            'request_id' => $request->id,
            'approver_id' => $supervisor->id,
            'level' => 1,
            'status' => ApprovalStatusEnum::PENDING->value,
        ]);
    }

    public function test_submit_under_5000000_assigns_supervisor(): void
    {
        $departmentId = $this->createDepartment('Dept A');
        $staff = $this->createStaff($departmentId, 'staff-amount1@example.com');
        $supervisor = $this->createApprover($departmentId, UserRoleEnum::SUPERVISOR, 'supervisor-amount1@example.com');

        $request = $this->createDraftRequest($staff->id, $departmentId, [
            'amount' => 4999999,
        ]);

        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/requests/'.$request->id.'/submit')->assertOk();

        $this->assertDatabaseHas('approvals', [
            'request_id' => $request->id,
            'approver_id' => $supervisor->id,
            'status' => ApprovalStatusEnum::PENDING->value,
        ]);
    }

    public function test_submit_5000000_to_20000000_assigns_manager(): void
    {
        $departmentId = $this->createDepartment('Dept B');
        $staff = $this->createStaff($departmentId, 'staff-amount2@example.com');
        $manager = $this->createApprover($departmentId, UserRoleEnum::MANAGER, 'manager-amount2@example.com');

        $request = $this->createDraftRequest($staff->id, $departmentId, [
            'amount' => 5000000,
        ]);

        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/requests/'.$request->id.'/submit')->assertOk();

        $this->assertDatabaseHas('approvals', [
            'request_id' => $request->id,
            'approver_id' => $manager->id,
            'status' => ApprovalStatusEnum::PENDING->value,
        ]);
    }

    public function test_submit_above_20000000_assigns_finance(): void
    {
        $departmentId = $this->createDepartment('Dept C');
        $staff = $this->createStaff($departmentId, 'staff-amount3@example.com');
        $finance = $this->createApprover($departmentId, UserRoleEnum::FINANCE, 'finance-amount3@example.com');

        $request = $this->createDraftRequest($staff->id, $departmentId, [
            'amount' => 20000001,
        ]);

        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/requests/'.$request->id.'/submit')->assertOk();

        $this->assertDatabaseHas('approvals', [
            'request_id' => $request->id,
            'approver_id' => $finance->id,
            'status' => ApprovalStatusEnum::PENDING->value,
        ]);
    }

    public function test_request_status_changes_to_submitted(): void
    {
        $departmentId = $this->createDepartment('Ops');
        $staff = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
        ]);

        User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::SUPERVISOR,
            'is_active' => true,
            'email' => 'supervisor2@example.com',
        ]);

        $request = $this->createDraftRequest($staff->id, $departmentId);

        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/requests/'.$request->id.'/submit')
            ->assertOk();

        $this->assertDatabaseHas('requests', [
            'id' => $request->id,
            'status' => RequestStatusEnum::SUBMITTED->value,
            'current_level' => 1,
        ]);
    }

    public function test_submitted_at_is_set(): void
    {
        $departmentId = $this->createDepartment('Finance');
        $staff = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
        ]);

        User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::SUPERVISOR,
            'is_active' => true,
            'email' => 'supervisor3@example.com',
        ]);

        $request = $this->createDraftRequest($staff->id, $departmentId);

        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/requests/'.$request->id.'/submit')->assertOk();

        $this->assertNotNull($request->fresh()->submitted_at);
        $this->assertNull($request->fresh()->completed_at);
    }

    public function test_first_approval_record_is_created(): void
    {
        $departmentId = $this->createDepartment('Sales');
        $staff = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
        ]);

        $supervisor = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::SUPERVISOR,
            'is_active' => true,
            'email' => 'supervisor4@example.com',
        ]);

        $request = $this->createDraftRequest($staff->id, $departmentId);

        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/requests/'.$request->id.'/submit')->assertOk();

        $this->assertDatabaseCount('approvals', 1);
        $this->assertDatabaseHas('approvals', [
            'request_id' => $request->id,
            'approver_id' => $supervisor->id,
            'level' => 1,
            'status' => ApprovalStatusEnum::PENDING->value,
            'approved_at' => null,
        ]);
    }

    public function test_user_cannot_submit_another_users_request(): void
    {
        $departmentId = $this->createDepartment('HR');

        $owner = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
            'email' => 'owner.submit@example.com',
        ]);

        $otherUser = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
            'email' => 'other.submit@example.com',
        ]);

        User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::SUPERVISOR,
            'is_active' => true,
            'email' => 'supervisor5@example.com',
        ]);

        $request = $this->createDraftRequest($owner->id, $departmentId);

        Sanctum::actingAs($otherUser);

        $this->postJson('/api/v1/requests/'.$request->id.'/submit')
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
    }

    public function test_admin_can_submit_any_draft_request(): void
    {
        $departmentId = $this->createDepartment('Procurement');

        $owner = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
        ]);

        $admin = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::ADMIN,
            'email' => 'admin.submit@example.com',
        ]);

        User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::SUPERVISOR,
            'is_active' => true,
            'email' => 'supervisor6@example.com',
        ]);

        $request = $this->createDraftRequest($owner->id, $departmentId);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/requests/'.$request->id.'/submit')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('requests', [
            'id' => $request->id,
            'status' => RequestStatusEnum::SUBMITTED->value,
        ]);
    }

    public function test_non_draft_request_cannot_be_submitted(): void
    {
        $departmentId = $this->createDepartment('Legal');

        $staff = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
        ]);

        User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::SUPERVISOR,
            'is_active' => true,
            'email' => 'supervisor7@example.com',
        ]);

        $request = $this->createDraftRequest($staff->id, $departmentId, [
            'status' => RequestStatusEnum::SUBMITTED,
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/requests/'.$request->id.'/submit')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.status.0', 'Only draft request can be submitted.');
    }

    public function test_request_cannot_be_submitted_if_required_approver_does_not_exist(): void
    {
        $departmentId = $this->createDepartment('Warehouse');

        $staff = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
        ]);

        $request = $this->createDraftRequest($staff->id, $departmentId, [
            'amount' => 6000000,
        ]);

        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/requests/'.$request->id.'/submit')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.approver.0', 'No active manager found in the same department.');

        $this->assertDatabaseMissing('approvals', [
            'request_id' => $request->id,
            'level' => 1,
        ]);

        $this->assertDatabaseHas('requests', [
            'id' => $request->id,
            'status' => RequestStatusEnum::DRAFT->value,
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

    private function createStaff(int $departmentId, string $email): User
    {
        return User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
            'email' => $email,
            'is_active' => true,
        ]);
    }

    private function createApprover(int $departmentId, UserRoleEnum $role, string $email): User
    {
        return User::factory()->create([
            'department_id' => $departmentId,
            'role' => $role,
            'email' => $email,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createDraftRequest(int $userId, int $departmentId, array $overrides = []): PurchaseRequest
    {
        return PurchaseRequest::query()->create(array_merge([
            'request_number' => 'PRF-'.now()->format('Ym').'-0001',
            'user_id' => $userId,
            'department_id' => $departmentId,
            'amount' => 10000,
            'description' => 'Purchase request draft',
            'status' => RequestStatusEnum::DRAFT,
            'current_level' => 1,
            'submitted_at' => null,
            'completed_at' => null,
        ], $overrides));
    }
}
