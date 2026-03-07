<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {
    }

    public function summary(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard summary fetched successfully.',
            'data' => $this->dashboardService->summaryForUser($user),
        ]);
    }

    public function recentRequests(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Recent requests fetched successfully.',
            'data' => $this->dashboardService->recentRequestsForUser($user),
        ]);
    }

    public function recentNotifications(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'message' => 'Recent notifications fetched successfully.',
            'data' => $this->dashboardService->recentNotificationsForUser($user),
        ]);
    }
}
