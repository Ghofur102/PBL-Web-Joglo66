<?php

namespace App\Http\Controllers;

use App\Services\FinancialReportService;
use App\Http\Controllers\Traits\FieldAccessTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReportController extends Controller
{
    use FieldAccessTrait;

    protected FinancialReportService $reportService;

    public function __construct(FinancialReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $fieldIds = [];

            if ($user && $user->hasRestrictedFieldAccess()) {
                $fieldIds = $this->getAccessibleFieldIds($user);
            }

            $month = (int) ($request->month ?? now()->month);
            $year = (int) ($request->year ?? now()->year);

            $reportData = $this->reportService->getMonthlyReport($month, $year, $fieldIds);

            return response()->json([
                'success' => true,
                'message' => 'Laporan kas bulanan berhasil diambil.',
                'data'    => $reportData,
            ], 200);
        } catch (Throwable $e) {
            Log::error('Laporan Bulanan Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat laporan kas: ' . $e->getMessage(),
            ], 500);
        }
    }
}
