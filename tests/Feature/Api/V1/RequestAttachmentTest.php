<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ApprovalStatusEnum;
use App\Enums\RequestStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\Approval;
use App\Models\Request as PurchaseRequest;
use App\Models\RequestActivity;
use App\Models\RequestAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RequestAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_upload_attachment_to_draft_request(): void
    {
        Storage::fake('local');

        $departmentId = $this->createDepartment('Attachment Owner Upload');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'attachment-owner-upload@example.com');
        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202606-0001');

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/requests/'.$request->id.'/attachments', [
            'file' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.original_name', 'document.pdf');

        $this->assertDatabaseHas('request_attachments', [
            'request_id' => $request->id,
            'uploaded_by' => $owner->id,
            'original_name' => 'document.pdf',
        ]);
    }

    public function test_admin_can_upload_attachment_to_draft_request(): void
    {
        Storage::fake('local');

        $departmentId = $this->createDepartment('Attachment Admin Upload');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'attachment-owner-admin-upload@example.com');
        $admin = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::ADMIN,
            'email' => 'attachment-admin-upload@example.com',
            'is_active' => true,
        ]);
        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202606-0002');

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/requests/'.$request->id.'/attachments', [
            'file' => UploadedFile::fake()->image('proof.png')->size(150),
        ])->assertCreated();

        $this->assertDatabaseHas('request_attachments', [
            'request_id' => $request->id,
            'uploaded_by' => $admin->id,
            'original_name' => 'proof.png',
        ]);
    }

    public function test_cannot_upload_attachment_to_submitted_request(): void
    {
        Storage::fake('local');

        $departmentId = $this->createDepartment('Attachment Submitted');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'attachment-owner-submitted@example.com');
        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::SUBMITTED, 'PRF-202606-0003');

        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/requests/'.$request->id.'/attachments', [
            'file' => UploadedFile::fake()->create('submitted.pdf', 120, 'application/pdf'),
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.');
    }

    public function test_approver_can_list_attachments(): void
    {
        Storage::fake('local');

        $departmentId = $this->createDepartment('Attachment Approver List');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'attachment-owner-list@example.com');
        $approver = $this->createUser($departmentId, UserRoleEnum::SUPERVISOR, 'attachment-approver-list@example.com');
        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::SUBMITTED, 'PRF-202606-0004');
        $this->createApproval($request->id, $approver->id, 1, ApprovalStatusEnum::PENDING);

        RequestAttachment::query()->create([
            'request_id' => $request->id,
            'uploaded_by' => $owner->id,
            'original_name' => 'invoice.pdf',
            'stored_name' => 'stored-invoice.pdf',
            'disk' => 'local',
            'path' => 'request-attachments/'.$request->id.'/stored-invoice.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ]);

        Sanctum::actingAs($approver);

        $this->getJson('/api/v1/requests/'.$request->id.'/attachments')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.original_name', 'invoice.pdf');
    }

    public function test_unauthorized_user_gets_403_on_attachment_list(): void
    {
        $departmentId = $this->createDepartment('Attachment Unauthorized');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'attachment-owner-unauthorized@example.com');
        $other = $this->createUser($departmentId, UserRoleEnum::STAFF, 'attachment-other-unauthorized@example.com');
        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202606-0005');

        Sanctum::actingAs($other);

        $this->getJson('/api/v1/requests/'.$request->id.'/attachments')
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
    }

    public function test_owner_can_delete_attachment_while_draft(): void
    {
        Storage::fake('local');

        $departmentId = $this->createDepartment('Attachment Delete Draft');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'attachment-owner-delete@example.com');
        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202606-0006');

        Storage::disk('local')->put('request-attachments/'.$request->id.'/to-delete.pdf', 'content');

        $attachment = RequestAttachment::query()->create([
            'request_id' => $request->id,
            'uploaded_by' => $owner->id,
            'original_name' => 'to-delete.pdf',
            'stored_name' => 'to-delete.pdf',
            'disk' => 'local',
            'path' => 'request-attachments/'.$request->id.'/to-delete.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        Sanctum::actingAs($owner);

        $this->deleteJson('/api/v1/attachments/'.$attachment->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('request_attachments', [
            'id' => $attachment->id,
        ]);

        Storage::disk('local')->assertMissing('request-attachments/'.$request->id.'/to-delete.pdf');
    }

    public function test_cannot_delete_attachment_after_submit(): void
    {
        $departmentId = $this->createDepartment('Attachment Delete Submitted');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'attachment-owner-delete-submitted@example.com');
        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::SUBMITTED, 'PRF-202606-0007');

        $attachment = RequestAttachment::query()->create([
            'request_id' => $request->id,
            'uploaded_by' => $owner->id,
            'original_name' => 'locked.pdf',
            'stored_name' => 'locked.pdf',
            'disk' => 'local',
            'path' => 'request-attachments/'.$request->id.'/locked.pdf',
            'mime_type' => 'application/pdf',
            'size' => 120,
        ]);

        Sanctum::actingAs($owner);

        $this->deleteJson('/api/v1/attachments/'.$attachment->id)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.');
    }

    public function test_activity_log_created_on_upload(): void
    {
        Storage::fake('local');

        $departmentId = $this->createDepartment('Attachment Upload Activity');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'attachment-owner-upload-activity@example.com');
        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202606-0008');

        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/requests/'.$request->id.'/attachments', [
            'file' => UploadedFile::fake()->create('activity.pdf', 50, 'application/pdf'),
        ])->assertCreated();

        $attachmentId = (int) $response->json('data.id');

        $this->assertDatabaseHas('request_activities', [
            'request_id' => $request->id,
            'actor_id' => $owner->id,
            'action' => 'request_attachment_uploaded',
        ]);

        $activity = RequestActivity::query()
            ->where('request_id', $request->id)
            ->where('action', 'request_attachment_uploaded')
            ->firstOrFail();

        $this->assertSame($attachmentId, $activity->meta['attachment_id']);
    }

    public function test_activity_log_created_on_delete(): void
    {
        Storage::fake('local');

        $departmentId = $this->createDepartment('Attachment Delete Activity');
        $owner = $this->createUser($departmentId, UserRoleEnum::STAFF, 'attachment-owner-delete-activity@example.com');
        $request = $this->createRequest($owner->id, $departmentId, RequestStatusEnum::DRAFT, 'PRF-202606-0009');

        Storage::disk('local')->put('request-attachments/'.$request->id.'/delete-activity.pdf', 'content');

        $attachment = RequestAttachment::query()->create([
            'request_id' => $request->id,
            'uploaded_by' => $owner->id,
            'original_name' => 'delete-activity.pdf',
            'stored_name' => 'delete-activity.pdf',
            'disk' => 'local',
            'path' => 'request-attachments/'.$request->id.'/delete-activity.pdf',
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        Sanctum::actingAs($owner);

        $this->deleteJson('/api/v1/attachments/'.$attachment->id)->assertOk();

        $this->assertDatabaseHas('request_activities', [
            'request_id' => $request->id,
            'actor_id' => $owner->id,
            'action' => 'request_attachment_deleted',
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
            'description' => 'Attachment test request',
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
