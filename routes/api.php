<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ApprovalController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\RequestAttachmentController;
use App\Http\Controllers\Api\V1\RequestController;
use App\Http\Controllers\Api\V1\RequestCommentController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    Route::middleware(['auth:sanctum', 'role:admin'])->group(function (): void {
        Route::get('/admin/test', static fn (): JsonResponse => response()->json([
            'success' => true,
            'message' => 'Admin access granted.',
        ]));
    });

    Route::middleware(['auth:sanctum', 'role:supervisor,manager,finance'])->group(function (): void {
        Route::get('/approver/test', static fn (Request $request): JsonResponse => response()->json([
            'success' => true,
            'message' => 'Approver access granted.',
            'data' => [
                'role' => $request->user()?->role?->value,
            ],
        ]));
    });

    Route::middleware(['auth:sanctum', 'role:staff'])->group(function (): void {
        Route::get('/staff/test', static fn (): JsonResponse => response()->json([
            'success' => true,
            'message' => 'Staff access granted.',
        ]));
    });

    Route::prefix('requests')->middleware('auth:sanctum')->group(function (): void {
        Route::post('/', [RequestController::class, 'store'])->middleware('role:staff,admin');
        Route::get('/', [RequestController::class, 'index']);
        Route::get('/{id}', [RequestController::class, 'show']);
        Route::get('/{id}/history', [RequestController::class, 'history']);
        Route::get('/{id}/activities', [RequestController::class, 'activities']);
        Route::post('/{id}/submit', [RequestController::class, 'submit'])->middleware('role:staff,admin');
        Route::get('/{id}/attachments', [RequestAttachmentController::class, 'index']);
        Route::post('/{id}/attachments', [RequestAttachmentController::class, 'store'])->middleware('role:staff,admin');
        Route::get('/{id}/comments', [RequestCommentController::class, 'index']);
        Route::post('/{id}/comments', [RequestCommentController::class, 'store']);
    });

    Route::prefix('approvals')->middleware('auth:sanctum')->group(function (): void {
        Route::get('/pending', [ApprovalController::class, 'pending'])
            ->middleware('role:supervisor,manager,finance,admin');

        Route::middleware('role:supervisor,manager,finance')->group(function (): void {
            Route::post('/{purchaseRequest}/approve', [ApprovalController::class, 'approve']);
            Route::post('/{purchaseRequest}/reject', [ApprovalController::class, 'reject']);
        });
    });


    Route::prefix('attachments')->middleware('auth:sanctum')->group(function (): void {
        Route::delete('/{id}', [RequestAttachmentController::class, 'destroy'])->middleware('role:staff,admin');
    });


    Route::prefix('dashboard')->middleware('auth:sanctum')->group(function (): void {
        Route::get('/summary', [DashboardController::class, 'summary']);
        Route::get('/recent-requests', [DashboardController::class, 'recentRequests']);
        Route::get('/recent-notifications', [DashboardController::class, 'recentNotifications']);
    });

    Route::prefix('notifications')->middleware('auth:sanctum')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/{id}/read', [NotificationController::class, 'read']);
    });

});
