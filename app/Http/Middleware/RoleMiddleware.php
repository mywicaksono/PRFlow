<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRoleEnum;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * @param  array<int, string>  $roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        $allowedRoles = collect($roles)
            ->flatMap(static fn (string $role): array => array_map('trim', explode(',', $role)))
            ->filter()
            ->map(static fn (string $role): ?UserRoleEnum => UserRoleEnum::tryFrom($role))
            ->filter()
            ->values();

        if ($allowedRoles->isEmpty() || ! $allowedRoles->contains($user->role)) {
            return $this->unauthorizedResponse();
        }

        return $next($request);
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthorized.',
        ], 403);
    }
}
