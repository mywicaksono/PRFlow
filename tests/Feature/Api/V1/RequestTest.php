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

<<<<<<< HEAD

    public function test_missing_request_returns_standard_404_error_envelope(): void
    {
        $departmentId = $this->createDepartment('Missing Request');
        $staff = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
            'email' => 'missing-request@example.com',
        ]);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/requests/999999')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Resource not found.')
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ]);
    }

=======
>>>>>>> origin/main
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

<<<<<<< HEAD

    public function test_admin_can_filter_by_department(): void
    {
        $deptA = $this->createDepartment('Filter Dept A');
        $deptB = $this->createDepartment('Filter Dept B');

        $admin = User::factory()->create([
            'department_id' => $deptA,
            'role' => UserRoleEnum::ADMIN,
            'email' => 'admin-filter-dept@example.com',
        ]);

        $userA = User::factory()->create([
            'department_id' => $deptA,
            'role' => UserRoleEnum::STAFF,
            'email' => 'user-filter-dept-a@example.com',
        ]);

        $userB = User::factory()->create([
            'department_id' => $deptB,
            'role' => UserRoleEnum::STAFF,
            'email' => 'user-filter-dept-b@example.com',
        ]);

        PurchaseRequest::query()->create([
            'request_number' => 'PRF-202601-0101',
            'user_id' => $userA->id,
            'department_id' => $deptA,
            'amount' => 1000,
            'description' => 'Dept A request',
            'status' => RequestStatusEnum::DRAFT,
            'current_level' => 1,
            'submitted_at' => null,
            'completed_at' => null,
        ]);

        PurchaseRequest::query()->create([
            'request_number' => 'PRF-202601-0102',
            'user_id' => $userB->id,
            'department_id' => $deptB,
            'amount' => 2000,
            'description' => 'Dept B request',
            'status' => RequestStatusEnum::DRAFT,
            'current_level' => 1,
            'submitted_at' => null,
            'completed_at' => null,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/requests?department_id='.$deptA)
            ->assertOk()
            ->assertJsonPath('success', true);

        $items = $response->json('data.data');
        $this->assertCount(1, $items);
        $this->assertSame($deptA, $items[0]['department_id']);
    }

    public function test_filter_by_status_works(): void
    {
        $departmentId = $this->createDepartment('Filter Status');
        $staff = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
            'email' => 'filter-status@example.com',
        ]);

        PurchaseRequest::query()->create([
            'request_number' => 'PRF-202601-0103',
            'user_id' => $staff->id,
            'department_id' => $departmentId,
            'amount' => 1000,
            'description' => 'Draft request',
            'status' => RequestStatusEnum::DRAFT,
            'current_level' => 1,
            'submitted_at' => null,
            'completed_at' => null,
        ]);

        PurchaseRequest::query()->create([
            'request_number' => 'PRF-202601-0104',
            'user_id' => $staff->id,
            'department_id' => $departmentId,
            'amount' => 2000,
            'description' => 'Submitted request',
            'status' => RequestStatusEnum::SUBMITTED,
            'current_level' => 1,
            'submitted_at' => now(),
            'completed_at' => null,
        ]);

        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/v1/requests?status=submitted')
            ->assertOk();

        $items = $response->json('data.data');
        $this->assertCount(1, $items);
        $this->assertSame(RequestStatusEnum::SUBMITTED->value, $items[0]['status']);
    }

    public function test_filter_by_amount_range_works(): void
    {
        $departmentId = $this->createDepartment('Filter Amount');
        $staff = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
            'email' => 'filter-amount@example.com',
        ]);

        PurchaseRequest::query()->create([
            'request_number' => 'PRF-202601-0105',
            'user_id' => $staff->id,
            'department_id' => $departmentId,
            'amount' => 1000,
            'description' => 'Low amount',
            'status' => RequestStatusEnum::DRAFT,
            'current_level' => 1,
            'submitted_at' => null,
            'completed_at' => null,
        ]);

        PurchaseRequest::query()->create([
            'request_number' => 'PRF-202601-0106',
            'user_id' => $staff->id,
            'department_id' => $departmentId,
            'amount' => 5000,
            'description' => 'Mid amount',
            'status' => RequestStatusEnum::DRAFT,
            'current_level' => 1,
            'submitted_at' => null,
            'completed_at' => null,
        ]);

        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/v1/requests?min_amount=3000&max_amount=6000')
            ->assertOk();

        $items = $response->json('data.data');
        $this->assertCount(1, $items);
        $this->assertSame('PRF-202601-0106', $items[0]['request_number']);
    }

    public function test_search_by_request_number_works(): void
    {
        $departmentId = $this->createDepartment('Search Number');
        $staff = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
            'email' => 'search-number@example.com',
        ]);

        PurchaseRequest::query()->create([
            'request_number' => 'PRF-202601-7777',
            'user_id' => $staff->id,
            'department_id' => $departmentId,
            'amount' => 1000,
            'description' => 'Number target',
            'status' => RequestStatusEnum::DRAFT,
            'current_level' => 1,
            'submitted_at' => null,
            'completed_at' => null,
        ]);

        PurchaseRequest::query()->create([
            'request_number' => 'PRF-202601-8888',
            'user_id' => $staff->id,
            'department_id' => $departmentId,
            'amount' => 1000,
            'description' => 'Number other',
            'status' => RequestStatusEnum::DRAFT,
            'current_level' => 1,
            'submitted_at' => null,
            'completed_at' => null,
        ]);

        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/v1/requests?search=7777')
            ->assertOk();

        $items = $response->json('data.data');
        $this->assertCount(1, $items);
        $this->assertSame('PRF-202601-7777', $items[0]['request_number']);
    }

    public function test_search_by_description_works(): void
    {
        $departmentId = $this->createDepartment('Search Description');
        $staff = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
            'email' => 'search-description@example.com',
        ]);

        PurchaseRequest::query()->create([
            'request_number' => 'PRF-202601-0107',
            'user_id' => $staff->id,
            'department_id' => $departmentId,
            'amount' => 1000,
            'description' => 'Need new scanner for office',
            'status' => RequestStatusEnum::DRAFT,
            'current_level' => 1,
            'submitted_at' => null,
            'completed_at' => null,
        ]);

        PurchaseRequest::query()->create([
            'request_number' => 'PRF-202601-0108',
            'user_id' => $staff->id,
            'department_id' => $departmentId,
            'amount' => 1000,
            'description' => 'Need new chairs',
            'status' => RequestStatusEnum::DRAFT,
            'current_level' => 1,
            'submitted_at' => null,
            'completed_at' => null,
        ]);

        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/v1/requests?search=scanner')
            ->assertOk();

        $items = $response->json('data.data');
        $this->assertCount(1, $items);
        $this->assertSame('PRF-202601-0107', $items[0]['request_number']);
    }

    public function test_sort_by_amount_asc_works(): void
    {
        $departmentId = $this->createDepartment('Sort Amount');
        $staff = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
            'email' => 'sort-amount@example.com',
        ]);

        PurchaseRequest::query()->create([
            'request_number' => 'PRF-202601-0109',
            'user_id' => $staff->id,
            'department_id' => $departmentId,
            'amount' => 3000,
            'description' => 'Third',
            'status' => RequestStatusEnum::DRAFT,
            'current_level' => 1,
            'submitted_at' => null,
            'completed_at' => null,
        ]);

        PurchaseRequest::query()->create([
            'request_number' => 'PRF-202601-0110',
            'user_id' => $staff->id,
            'department_id' => $departmentId,
            'amount' => 1000,
            'description' => 'First',
            'status' => RequestStatusEnum::DRAFT,
            'current_level' => 1,
            'submitted_at' => null,
            'completed_at' => null,
        ]);

        PurchaseRequest::query()->create([
            'request_number' => 'PRF-202601-0111',
            'user_id' => $staff->id,
            'department_id' => $departmentId,
            'amount' => 2000,
            'description' => 'Second',
            'status' => RequestStatusEnum::DRAFT,
            'current_level' => 1,
            'submitted_at' => null,
            'completed_at' => null,
        ]);

        Sanctum::actingAs($staff);

        $response = $this->getJson('/api/v1/requests?sort_by=amount&sort_direction=asc')
            ->assertOk();

        $items = $response->json('data.data');
        $amounts = array_map(static fn (array $item): float => (float) $item['amount'], $items);
        $this->assertSame([1000.0, 2000.0, 3000.0], array_slice($amounts, 0, 3));
    }

    public function test_invalid_filter_returns_422_with_standard_error_envelope(): void
    {
        $departmentId = $this->createDepartment('Invalid Filter');
        $staff = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
            'email' => 'invalid-filter@example.com',
        ]);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/requests?sort_by=hacked_field')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ]);
    }

=======
>>>>>>> origin/main
    private function createDepartment(string $name): int
    {
        return (int) DB::table('departments')->insertGetId([
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
