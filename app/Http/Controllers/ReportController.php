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
        $status = 200;
        $data = [];

        try {
            $user = $request->user();
            $fieldIds = [];

            if ($user && $user->role === 'worker') {
                $fieldIds = $this->getAccessibleFieldIds($user);
            }

            $month = (int) ($request->month ?? date('m'));
            $year = (int) ($request->year ?? date('Y'));

            $reportData = $this->reportService->getMonthlyReport($month, $year, $fieldIds);

            $data = [
                'success' => true,
                'message' => 'Laporan kas bulanan berhasil diambil.',
                'data'    => $reportData,
            ];
        } catch (Throwable $e) {
            Log::error('Laporan Bulanan Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $status = 500;
            $data = [
                'success' => false,
                'message' => 'Gagal memuat laporan kas: ' . $e->getMessage(),
            ];
        }

        return response()->json($data, $status);
    }
}
