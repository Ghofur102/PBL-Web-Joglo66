<?php

namespace App\Http\Controllers;

use App\Services\FinancialReportService;
use App\Http\Controllers\Traits\FieldAccessTrait;
use Carbon\Carbon;
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
            $fieldIds = $this->normalizeFieldIds($request->input('field_ids'));

            if ($user && $user->role === 'worker') {
                $fieldIds = array_values(array_intersect(
                    $this->getAccessibleFieldIds($user),
                    $fieldIds,
                ));
            }

            $month = (int) ($request->month ?? date('m'));
            $year = (int) ($request->year ?? date('Y'));
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $reportType = $request->input('report_type', 'all');

            $this->validateReportFilters($month, $year, $startDate, $endDate, $reportType, $fieldIds);

            $reportData = $this->reportService->getMonthlyReport($month, $year, [
                'field_ids' => $fieldIds,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'report_type' => $reportType,
            ]);

            $data = [
                'success' => true,
                'message' => 'Laporan kas bulanan berhasil diambil.',
                'data'    => $reportData,
            ];
        } catch (Throwable $e) {
            Log::error('Laporan Bulanan Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $status = $e instanceof \Illuminate\Validation\ValidationException ? 422 : 500;
            $data = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }

        return response()->json($data, $status);
    }

    protected function normalizeFieldIds(mixed $fieldIds): array
    {
        if ($fieldIds === null || $fieldIds === '') {
            return [];
        }

        $values = is_array($fieldIds) ? $fieldIds : explode(',', (string) $fieldIds);

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $value) => is_numeric($value) ? (int) $value : null,
            $values,
        ))));
    }

    protected function validateReportFilters(
        int $month,
        int $year,
        mixed $startDate,
        mixed $endDate,
        mixed $reportType,
        array $fieldIds,
    ): void {
        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('Bulan harus berada di antara 1 dan 12.');
        }

        if ($year < 2000 || $year > 2100) {
            throw new \InvalidArgumentException('Tahun tidak valid.');
        }

        $validReportTypes = ['all', 'income', 'expense', 'field', 'rental'];
        if (!in_array((string) $reportType, $validReportTypes, true)) {
            throw new \InvalidArgumentException('Tipe laporan tidak valid.');
        }

        if ($startDate !== null && !$this->isValidDate($startDate)) {
            throw new \InvalidArgumentException('Format tanggal awal tidak valid.');
        }

        if ($endDate !== null && !$this->isValidDate($endDate)) {
            throw new \InvalidArgumentException('Format tanggal akhir tidak valid.');
        }

        if ($startDate !== null && $endDate !== null && $startDate > $endDate) {
            throw new \InvalidArgumentException('Tanggal awal tidak boleh lebih besar dari tanggal akhir.');
        }

        foreach ($fieldIds as $fieldId) {
            if ($fieldId < 1) {
                throw new \InvalidArgumentException('ID lapangan tidak valid.');
            }
        }
    }

    protected function isValidDate(mixed $date): bool
    {
        if (!is_string($date) || $date === '') {
            return false;
        }

        $parsed = Carbon::parse($date);

        return $parsed->format('Y-m-d') === $date;
    }
}
