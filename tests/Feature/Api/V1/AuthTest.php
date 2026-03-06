<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_success_returns_token_and_user(): void
    {
        $password = 'password123';

        User::query()->create([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => Hash::make($password),
            'role' => UserRoleEnum::STAFF,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => $password,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['token', 'user'],
            ]);
    }

    public function test_me_requires_auth(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::query()->create([
            'name' => 'Auth User',
            'email' => 'auth@example.com',
            'password' => Hash::make('password123'),
            'role' => UserRoleEnum::ADMIN,
            'is_active' => true,
        ]);

        $token = $user->createToken('api-token', ['*'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $tokenModel = PersonalAccessToken::findToken($token);
        $this->assertNull($tokenModel);
    }
}
