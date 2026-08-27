<?php

namespace App\Http\Controllers\Treasure;

use App\Http\Controllers\Controller;
use App\Http\Requests\Treasure\GetSalaryRequest;
use App\Http\Requests\Treasure\UpdateSalaryRequest;
use App\Http\Requests\Treasure\SyncSalaryRequest;
use App\Services\Treasure\SalaryService;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class SalaryController extends Controller
{
    private string $statusAccessDenied = 'Akses ditolak.';
    protected SalaryService $salaryService;

    public function __construct(SalaryService $salaryService)
    {
        $this->salaryService = $salaryService;
    }

    public function index(GetSalaryRequest $request): JsonResponse
    {
        $status = 200;
        $data = [];

        try {
            $user = $request->user();
            if (!$user || $user->role !== UserRole::TREASURER->value) {
                throw new AccessDeniedHttpException($this->statusAccessDenied);
            }

            $result = $this->salaryService->getSalaryList((int)$request->bulan, (int)$request->tahun);

            $data = [
                'success' => true,
                'message' => 'Data gaji berhasil diambil.',
                'data'    => $result
            ];
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
            $data = ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            $status = 500;
            $data = ['success' => false, 'message' => 'Terjadi kesalahan sistem.', 'error' => $e->getMessage()];
        }

        return response()->json($data, $status);
    }

    public function update(UpdateSalaryRequest $request): JsonResponse
    {
        $status = 200;
        $data = [];

        try {
            $user = $request->user();
            if (!$user || $user->role !== UserRole::TREASURER->value) {
                throw new AccessDeniedHttpException($this->statusAccessDenied);
            }

            $this->salaryService->updateOrCalculateSalary($request->validated(), $user->id);

            $data = [
                'success' => true,
                'message' => 'Gaji berhasil disimpan.'
            ];
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
            $data = ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            $status = 500;
            $data = ['success' => false, 'message' => 'Gagal menyimpan gaji.', 'error' => $e->getMessage()];
        }

        return response()->json($data, $status);
    }

    public function sync(SyncSalaryRequest $request): JsonResponse
    {
        $status = 200;
        $data = [];

        try {
            $user = $request->user();
            if (!$user || $user->role !== UserRole::TREASURER->value) {
                throw new AccessDeniedHttpException($this->statusAccessDenied);
            }

            $syncedCount = $this->salaryService->syncMonthlySalaries($request->validated(), $user->id);

            $data = [
                'success' => true,
                'message' => "{$syncedCount} data gaji baru berhasil disinkronisasi."
            ];
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
            $data = ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            $status = 500;
            $data = ['success' => false, 'message' => 'Gagal melakukan sinkronisasi.', 'error' => $e->getMessage()];
        }

        return response()->json($data, $status);
    }
}
