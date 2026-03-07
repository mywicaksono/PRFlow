<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ApprovalStatusEnum;
use App\Enums\RequestStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Approval;
use App\Models\Request as PurchaseRequest;
use App\Models\RequestComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RequestCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_comment(): void
    {
        $departmentId = $this->createDepartment('Comment Owner');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'comment-owner@example.com');
        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202608-0001');

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/requests/'.$request->id.'/comments', [
            'comment' => 'Owner comment',
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.comment', 'Owner comment');
    }

    public function test_assigned_approver_can_comment(): void
    {
        $departmentId = $this->createDepartment('Comment Approver');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'comment-approver-owner@example.com');
        $approver = $this->createUser($departmentId, UserRoleEnum::SUPERVISOR, 'comment-approver@example.com');
        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::SUBMITTED, 'PRF-202608-0002', 1);
        $this->createApproval($request->id, $approver->id, 1, ApprovalStatusEnum::PENDING);

        Sanctum::actingAs($approver);

        $this->postJson('/api/v1/requests/'.$request->id.'/comments', [
            'comment' => 'Approver comment',
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $approver->id);
    }

    public function test_admin_can_comment(): void
    {
        $departmentId = $this->createDepartment('Comment Admin');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'comment-admin-owner@example.com');
        $admin = $this->createUser($departmentId, UserRoleEnum::ADMIN, 'comment-admin@example.com');
        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202608-0003');

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/requests/'.$request->id.'/comments', [
            'comment' => 'Admin comment',
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $admin->id);
    }

    public function test_unauthorized_user_gets_403(): void
    {
        $departmentId = $this->createDepartment('Comment Unauthorized');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'comment-owner-unauth@example.com');
        $other = $this->createUser($departmentId, UserRoleEnum::STAFF, 'comment-other-unauth@example.com');
        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202608-0004');

        Sanctum::actingAs($other);

        $this->postJson('/api/v1/requests/'.$request->id.'/comments', [
            'comment' => 'Not allowed',
        ])->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthorized.');
    }

    public function test_comments_list_returns_correct_order(): void
    {
        $departmentId = $this->createDepartment('Comment Order');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'comment-order-owner@example.com');
        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202608-0005');

        RequestComment::query()->create([
            'request_id' => $request->id,
            'user_id' => $owner->id,
            'comment' => 'First comment',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        RequestComment::query()->create([
            'request_id' => $request->id,
            'user_id' => $owner->id,
            'comment' => 'Second comment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/requests/'.$request->id.'/comments')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('First comment', $response->json('data.0.comment'));
        $this->assertSame('Second comment', $response->json('data.1.comment'));
    }

    public function test_activity_log_created_on_comment(): void
    {
        $departmentId = $this->createDepartment('Comment Activity');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'comment-activity-owner@example.com');
        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202608-0006');

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/requests/'.$request->id.'/comments', [
            'comment' => 'Activity comment',
        ])->assertCreated();

        $commentId = (int) $response->json('data.id');

        $this->assertDatabaseHas('request_activities', [
            'request_id' => $request->id,
            'actor_id' => $owner->id,
            'action' => 'request_comment_added',
        ]);

        $this->assertDatabaseHas('request_activities', [
            'request_id' => $request->id,
            'action' => 'request_comment_added',
            'meta->comment_id' => $commentId,
        ]);
    }

    public function test_notifications_created_for_owner_and_current_approver_excluding_author(): void
    {
        $departmentId = $this->createDepartment('Comment Notification');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'comment-notif-owner@example.com');
        $supervisor = $this->createUser($departmentId, UserRoleEnum::SUPERVISOR, 'comment-notif-supervisor@example.com');
        $manager = $this->createUser($departmentId, UserRoleEnum::MANAGER, 'comment-notif-manager@example.com');

        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::SUBMITTED, 'PRF-202608-0007', 1);
        $this->createApproval($request->id, $supervisor->id, 1, ApprovalStatusEnum::PENDING);
        $this->createApproval($request->id, $manager->id, 2, ApprovalStatusEnum::PENDING);

        Sanctum::actingAs($supervisor);

        $this->postJson('/api/v1/requests/'.$request->id.'/comments', [
            'comment' => 'Please provide more details',
        ])->assertCreated();

        $this->assertDatabaseHas('request_notifications', [
            'request_id' => $request->id,
            'user_id' => $owner->id,
            'type' => 'request_commented',
        ]);

        $this->assertDatabaseMissing('request_notifications', [
            'request_id' => $request->id,
            'user_id' => $supervisor->id,
            'type' => 'request_commented',
        ]);

        $this->assertDatabaseMissing('request_notifications', [
            'request_id' => $request->id,
            'user_id' => $manager->id,
            'type' => 'request_commented',
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
            'description' => 'Comment request',
            'status' => $status,
            'current_level' => $currentLevel,
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
            'approved_at' => $status === ApprovalStatusEnum::PENDING ? null : now(),
        ]);
    }
}
