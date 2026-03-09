<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_protected_endpoint_returns_standard_401_envelope(): void
    {
        $this->getJson('/api/v1/requests')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonStructure(['success', 'message', 'errors']);
    }


    public function test_unauthenticated_plain_api_request_does_not_require_login_route(): void
    {
        $this->get('/api/v1/requests')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonStructure(['success', 'message', 'errors']);
    }
    public function test_forbidden_endpoint_returns_standard_403_envelope(): void
    {
        $departmentId = $this->createDepartment('Contract Forbidden');
        $staff = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
            'email' => 'contract-forbidden@example.com',
            'is_active' => true,
        ]);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/approvals/pending')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthorized.')
            ->assertJsonStructure(['success', 'message', 'errors']);
    }

    public function test_missing_resource_returns_standard_404_envelope(): void
    {
        $departmentId = $this->createDepartment('Contract Missing');
        $staff = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
            'email' => 'contract-missing@example.com',
            'is_active' => true,
        ]);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/requests/999999')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Resource not found.')
            ->assertJsonStructure(['success', 'message', 'errors']);
    }

    public function test_validation_failure_returns_standard_422_envelope(): void
    {
        $departmentId = $this->createDepartment('Contract Validation');
        $staff = User::factory()->create([
            'department_id' => $departmentId,
            'role' => UserRoleEnum::STAFF,
            'email' => 'contract-validation@example.com',
            'is_active' => true,
        ]);

        Sanctum::actingAs($staff);

        $this->postJson('/api/v1/requests', [
            'amount' => 0,
            'description' => '',
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['success', 'message', 'errors']);
    }

    public function test_success_response_contains_success_message_data(): void
    {
        User::query()->create([
            'name' => 'Contract User',
            'email' => 'contract-user@example.com',
            'password' => Hash::make('password123'),
            'role' => UserRoleEnum::STAFF,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'contract-user@example.com',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data']);
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
