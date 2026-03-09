<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth' => Authenticate::class,
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $jsonError = static function (string $message, int $status, mixed $errors = null): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
            ], $status);
        };

        $expectsJson = static fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

        $exceptions->render(function (ValidationException $exception, Request $request) use ($jsonError, $expectsJson): ?JsonResponse {
            if (! $expectsJson($request)) {
                return null;
            }

            return $jsonError('Validation failed.', 422, $exception->errors());
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($jsonError, $expectsJson): ?JsonResponse {
            if (! $expectsJson($request)) {
                return null;
            }

            return $jsonError('Unauthenticated.', 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($jsonError, $expectsJson): ?JsonResponse {
            if (! $expectsJson($request)) {
                return null;
            }

            return $jsonError('Unauthorized.', 403);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) use ($jsonError, $expectsJson): ?JsonResponse {
            if (! $expectsJson($request)) {
                return null;
            }

            return $jsonError('Resource not found.', 404);
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) use ($jsonError, $expectsJson): ?JsonResponse {
            if (! $expectsJson($request)) {
                return null;
            }

            return $jsonError('Resource not found.', 404);
        });
    })->create();
