<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Http\Requests\Owner\StoreEmployeeRequest;
use App\Http\Requests\Owner\UpdateEmployeeRequest;
use App\Services\Owner\EmployeeService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class EmployeeController extends Controller
{
    protected EmployeeService $employeeService;

    public function __construct(EmployeeService $employeeService)
    {
        $this->employeeService = $employeeService;
    }

    public function index(): JsonResponse
    {
        try {
            $employees = $this->employeeService->getEmployeesList();
            return response()->json([
                'success' => true,
                'data'    => $employees
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data karyawan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        try {
            $employee = $this->employeeService->storeEmployee($request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Karyawan berhasil ditambahkan.',
                'data'    => $employee
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan karyawan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(UpdateEmployeeRequest $request, $id): JsonResponse
    {
        try {
            $employee = Employee::query()->find($id);
            if (!$employee) {
                throw new NotFoundHttpException('Data karyawan tidak ditemukan.');
            }

            $this->employeeService->updateEmployee($employee, $request->validated());
            return response()->json([
                'success' => true,
                'message' => 'Data karyawan berhasil diperbarui.'
            ], 200);
        } catch (HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getStatusCode());
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $employee = Employee::query()->find($id);
            if (!$employee) {
                throw new NotFoundHttpException('Data karyawan tidak ditemukan.');
            }

            $this->employeeService->destroyEmployee($employee);
            return response()->json([
                'success' => true,
                'message' => 'Karyawan berhasil dihapus.'
            ], 200);
        } catch (HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e->getStatusCode());
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data: ' . $e->getMessage()
            ], 500);
        }
    }
}
