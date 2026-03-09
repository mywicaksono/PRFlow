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

class RequestNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_approver_receives_notification_on_submit(): void
    {
        $departmentId = $this->createDepartment('Notif Submit');
        $staff = $this->createUser($departmentId, UserRoleEnum::STAFF, 'notif-staff@example.com');
        $supervisor = $this->createUser($departmentId, UserRoleEnum::SUPERVISOR, 'notif-supervisor@example.com');

        $request = $this->createRequest($staff->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202605-0001');

        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/requests/'.$request->id.'/submit')->assertOk();

        $this->assertDatabaseHas('request_notifications', [
            'request_id' => $request->id,
            'user_id' => $supervisor->id,
            'type' => 'request_submitted',
        ]);
    }

    public function test_next_approver_receives_notification_on_intermediate_approval(): void
    {
        $departmentId = $this->createDepartment('Notif Intermediate');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'notif-owner2@example.com');
        $supervisor = $this->createUser($departmentId, UserRoleEnum::SUPERVISOR, 'notif-supervisor2@example.com');
        $manager = $this->createUser($departmentId, UserRoleEnum::MANAGER, 'notif-manager2@example.com');

        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::SUBMITTED, 'PRF-202605-0002', [
            'current_level' => 1,
            'submitted_at' => now(),
        ]);

        $this->createApproval($request->id, $supervisor->id, 1, ApprovalStatusEnum::PENDING);
        $this->createApproval($request->id, $manager->id, 2, ApprovalStatusEnum::PENDING);

        Sanctum::actingAs($supervisor);

        $this->postJson('/api/v1/approvals/'.$request->id.'/approve')->assertOk();

        $this->assertDatabaseHas('request_notifications', [
            'request_id' => $request->id,
            'user_id' => $manager->id,
            'type' => 'request_approval_required',
        ]);
    }

    public function test_owner_receives_notification_on_final_approval(): void
    {
        $departmentId = $this->createDepartment('Notif Final');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'notif-owner3@example.com');
        $supervisor = $this->createUser($departmentId, UserRoleEnum::SUPERVISOR, 'notif-supervisor3@example.com');

        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::SUBMITTED, 'PRF-202605-0003', [
            'current_level' => 1,
            'submitted_at' => now(),
        ]);

        $this->createApproval($request->id, $supervisor->id, 1, ApprovalStatusEnum::PENDING);

        Sanctum::actingAs($supervisor);

        $this->postJson('/api/v1/approvals/'.$request->id.'/approve')->assertOk();

        $this->assertDatabaseHas('request_notifications', [
            'request_id' => $request->id,
            'user_id' => $owner->id,
            'type' => 'request_fully_approved',
        ]);
    }

    public function test_owner_receives_notification_on_reject(): void
    {
        $departmentId = $this->createDepartment('Notif Reject');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'notif-owner4@example.com');
        $supervisor = $this->createUser($departmentId, UserRoleEnum::SUPERVISOR, 'notif-supervisor4@example.com');

        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::SUBMITTED, 'PRF-202605-0004', [
            'current_level' => 1,
            'submitted_at' => now(),
        ]);

        $this->createApproval($request->id, $supervisor->id, 1, ApprovalStatusEnum::PENDING);

        Sanctum::actingAs($supervisor);

        $this->postJson('/api/v1/approvals/'.$request->id.'/reject', [
            'reason' => 'Insufficient budget',
        ])->assertOk();

        $this->assertDatabaseHas('request_notifications', [
            'request_id' => $request->id,
            'user_id' => $owner->id,
            'type' => 'request_rejected',
        ]);
    }

    public function test_authenticated_user_can_list_own_notifications(): void
    {
        $departmentId = $this->createDepartment('Notif List');
        $user = $this->createUser($departmentId, UserRoleEnum::STAFF, 'notif-list-user@example.com');
        $other = $this->createUser($departmentId, UserRoleEnum::STAFF, 'notif-list-other@example.com');
        $request = $this->createRequest($user->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202605-0005');

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

        $response = $this->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $items = $response->json('data.data');
        $this->assertCount(1, $items);
        $this->assertSame($user->id, $items[0]['user_id']);
    }


    public function test_missing_notification_returns_standard_404_error_envelope(): void
    {
        $departmentId = $this->createDepartment('Notif Missing');
        $user = $this->createUser($departmentId, UserRoleEnum::STAFF, 'notif-missing@example.com');

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notifications/999999/read')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Resource not found.')
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ]);
    }

    public function test_user_cannot_read_another_users_notification(): void
    {
        $departmentId = $this->createDepartment('Notif Read Unauthorized');
        $user = $this->createUser($departmentId, UserRoleEnum::STAFF, 'notif-read-user@example.com');
        $other = $this->createUser($departmentId, UserRoleEnum::STAFF, 'notif-read-other@example.com');
        $request = $this->createRequest($user->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202605-0006');

        $notification = RequestNotification::query()->create([
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

        $this->postJson('/api/v1/notifications/'.$notification->id.'/read')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthorized.')
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ]);
    }

    public function test_mark_as_read_updates_is_read_and_read_at(): void
    {
        $departmentId = $this->createDepartment('Notif Read');
        $user = $this->createUser($departmentId, UserRoleEnum::STAFF, 'notif-read-owner@example.com');
        $request = $this->createRequest($user->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202605-0007');

        $notification = RequestNotification::query()->create([
            'request_id' => $request->id,
            'user_id' => $user->id,
            'type' => 'request_submitted',
            'title' => 'To Read',
            'message' => 'To Read',
            'is_read' => false,
            'read_at' => null,
            'meta' => null,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/notifications/'.$notification->id.'/read')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('request_notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);

        $this->assertNotNull(RequestNotification::query()->findOrFail($notification->id)->read_at);
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createRequest(int $userId, int $departmentId, RequestStatusEnum $status, string $requestNumber, array $overrides = []): PurchaseRequest
    {
        return PurchaseRequest::query()->create(array_merge([
            'request_number' => $requestNumber,
            'user_id' => $userId,
            'department_id' => $departmentId,
            'amount' => 1000000,
            'description' => 'Notification request',
            'status' => $status,
            'current_level' => 1,
            'submitted_at' => $status === RequestStatusEnum::DRAFT ? null : now(),
            'completed_at' => null,
        ], $overrides));
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
