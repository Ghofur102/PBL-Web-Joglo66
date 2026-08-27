<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardService;
use App\Http\Controllers\Traits\FieldAccessTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class DashboardController extends Controller
{
    use FieldAccessTrait;

    protected DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function dashboard(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $fieldIds = [];

            if ($user && in_array($user->role, ['worker', 'treasurer'], true)) {
                $fieldIds = $this->getAccessibleFieldIds($user);
            }

            $dashboardData = $this->dashboardService->getDashboardData($fieldIds);

            return response()->json([
                'success' => true,
                'message' => 'Dashboard data retrieved successfully',
                'data'    => $dashboardData
            ], 200);
        } catch (Throwable $e) {

            return response()->json([
                'success' => true,
                'message' => 'Gagal memuat sebagian data dasbor.',
                'data'    => [
                    'today_date'            => date('d M Y'),
                    'total_active_bookings' => 0,
                    'active_bookings'       => 0,
                    'today_income'          => 0,
                    'income'                => 0,
                    'total_income'          => 0,
                    'today_expense'         => 0,
                    'expense'               => 0,
                    'total_expense'         => 0,
                    'total_fields'          => 0,
                    'schedules'             => [],
                    'today_schedules'       => [],
                ]
            ], 200);
        }
    }
}
