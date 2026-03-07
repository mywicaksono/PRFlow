<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\RequestStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Request as PurchaseRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_draft_request(): void
    {
        $departmentId = $this->createDepartment('IT');

        $staff = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
        ]);

        Sanctum::actingAs($staff);

        $response = $this->postJson('/api/v1/requests', [
            'amount' => 150000,
            'description' => 'Laptop procurement',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RequestStatusEnum::DRAFT->value);

        $this->assertDatabaseHas('requests', [
            'user_id' => $staff->id,
            'department_id' => $departmentId,
            'status' => RequestStatusEnum::DRAFT->value,
            'current_level' => 1,
        ]);
    }

    public function test_guest_cannot_create_request(): void
    {
        $departmentId = $this->createDepartment('Finance');

        $this->postJson('/api/v1/requests', [
            'amount' => 100000,
            'description' => 'Printer ink',
            'department_id' => $departmentId,
        ])->assertUnauthorized();
    }

    public function test_request_status_defaults_to_draft(): void
    {
        $departmentId = $this->createDepartment('Operations');

        $staff = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
        ]);

        Sanctum::actingAs($staff);

        $response = $this->postJson('/api/v1/requests', [
            'amount' => 25000,
            'description' => 'Office supplies',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('requests', [
            'status' => RequestStatusEnum::DRAFT->value,
            'submitted_at' => null,
            'completed_at' => null,
        ]);
    }

    public function test_user_can_list_only_own_requests(): void
    {
        $departmentId = $this->createDepartment('Sales');

        $user = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
            'email' => 'owner@example.com',
        ]);

        $otherUser = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
            'email' => 'other@example.com',
        ]);

        PurchaseRequest::query()->create([
            'request_number' => 'PRF-202601-0001',
            'user_id' => $user->id,
            'department_id' => $departmentId,
            'amount' => 12000,
            'description' => 'Own request',
            'status' => RequestStatusEnum::DRAFT,
            'current_level' => 1,
            'submitted_at' => null,
            'completed_at' => null,
        ]);

        PurchaseRequest::query()->create([
            'request_number' => 'PRF-202601-0002',
            'user_id' => $otherUser->id,
            'department_id' => $departmentId,
            'amount' => 13000,
            'description' => 'Other request',
            'status' => RequestStatusEnum::DRAFT,
            'current_level' => 1,
            'submitted_at' => null,
            'completed_at' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/requests');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $items = $response->json('data.data');
        $this->assertCount(1, $items);
        $this->assertSame($user->id, $items[0]['user_id']);
    }

    public function test_admin_can_list_all_requests(): void
    {
        $departmentId = $this->createDepartment('Admin');

        $admin = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::ADMIN,
        ]);

        $staffA = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
            'email' => 'staffa@example.com',
        ]);

        $staffB = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
            'email' => 'staffb@example.com',
        ]);

        PurchaseRequest::query()->create([
            'request_number' => 'PRF-202601-0003',
            'user_id' => $staffA->id,
            'department_id' => $departmentId,
            'amount' => 1000,
            'description' => 'A',
            'status' => RequestStatusEnum::DRAFT,
            'current_level' => 1,
            'submitted_at' => null,
            'completed_at' => null,
        ]);

        PurchaseRequest::query()->create([
            'request_number' => 'PRF-202601-0004',
            'user_id' => $staffB->id,
            'department_id' => $departmentId,
            'amount' => 2000,
            'description' => 'B',
            'status' => RequestStatusEnum::DRAFT,
            'current_level' => 1,
            'submitted_at' => null,
            'completed_at' => null,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/requests');

        $response->assertOk();
        $this->assertCount(2, $response->json('data.data'));
    }

    public function test_user_cannot_view_another_users_request(): void
    {
        $departmentId = $this->createDepartment('HR');

        $owner = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
            'email' => 'owner2@example.com',
        ]);

        $otherUser = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
            'email' => 'other2@example.com',
        ]);

        $request = PurchaseRequest::query()->create([
            'request_number' => 'PRF-202601-0005',
            'user_id' => $owner->id,
            'department_id' => $departmentId,
            'amount' => 5000,
            'description' => 'Confidential',
            'status' => RequestStatusEnum::DRAFT,
            'current_level' => 1,
            'submitted_at' => null,
            'completed_at' => null,
        ]);

        Sanctum::actingAs($otherUser);

        $this->getJson('/api/v1/requests/'.$request->id)
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
    }

    public function test_admin_can_view_any_request(): void
    {
        $departmentId = $this->createDepartment('Procurement');

        $owner = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
        ]);

        $admin = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::ADMIN,
            'email' => 'admin2@example.com',
        ]);

        $request = PurchaseRequest::query()->create([
            'request_number' => 'PRF-202601-0006',
            'user_id' => $owner->id,
            'department_id' => $departmentId,
            'amount' => 8000,
            'description' => 'Server upgrade',
            'status' => RequestStatusEnum::DRAFT,
            'current_level' => 1,
            'submitted_at' => null,
            'completed_at' => null,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/requests/'.$request->id)
            ->assertOk()
            ->assertJsonPath('data.id', $request->id)
            ->assertJsonPath('success', true);
    }

    private function createDepartment(string $name): int
    {
        return (int) DB::table('departments')->insertGetId([
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
