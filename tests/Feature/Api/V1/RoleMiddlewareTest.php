<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_admin_only_route(): void
    {
        $admin = User::factory()->create([
            'role' => UserRoleEnum::ADMIN,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/test')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_staff_cannot_access_admin_only_route(): void
    {
        $staff = User::factory()->create([
            'role' => UserRoleEnum::STAFF,
        ]);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/admin/test')
            ->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized.',
            ]);
    }

    public function test_approver_roles_can_access_approver_route(): void
    {
        $approverRoles = [
            UserRoleEnum::SUPERVISOR,
            UserRoleEnum::MANAGER,
            UserRoleEnum::FINANCE,
        ];

        foreach ($approverRoles as $role) {
            $user = User::factory()->create([
                'email' => sprintf('%s@example.com', $role->value),
                'role' => $role,
            ]);

            Sanctum::actingAs($user);

            $this->getJson('/api/v1/approver/test')
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.role', $role->value);
        }
    }

    public function test_unauthenticated_request_gets_401_for_protected_route(): void
    {
        $this->getJson('/api/v1/admin/test')
            ->assertUnauthorized();
    }
}
