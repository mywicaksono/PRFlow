<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * @return array{token: string, user: User}
     */
    public function login(string $email, string $password): array
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are invalid.'],
            ]);
        }

        $abilities = $this->resolveAbilities($user->role);
        $token = $user->createToken('api-token', $abilities)->plainTextToken;

        return [
            'token' => $token,
            'user' => $user,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function me(User $user): User
    {
        return $user;
    }

    /**
     * @return array<int, string>
     */
    private function resolveAbilities(UserRoleEnum $role): array
    {
        return match ($role) {
            UserRoleEnum::ADMIN => ['*'],
            UserRoleEnum::FINANCE, UserRoleEnum::MANAGER, UserRoleEnum::SUPERVISOR => ['approve', 'read'],
            UserRoleEnum::STAFF => ['create', 'read'],
        };
    }
}
