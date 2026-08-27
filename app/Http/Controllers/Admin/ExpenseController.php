<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ExpenseService;
use App\Http\Controllers\Traits\FieldAccessTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ExpenseController extends Controller
{
    use FieldAccessTrait;

    protected ExpenseService $expenseService;

    public function __construct(ExpenseService $expenseService)
    {
        $this->expenseService = $expenseService;
    }

    public function listExpense(Request $request): JsonResponse
    {
        $status = 200;
        $data = [];

        try {
            $user = $request->user();
            $fieldIds = [];

            if ($user && in_array($user->role, ['worker', 'treasurer'], true)) {
                $fieldIds = $this->getAccessibleFieldIds($user);
            }

            $expenses = $this->expenseService->getExpenses($fieldIds);
            $data = ['success' => true, 'data' => $expenses];
        } catch (Throwable $e) {
            $status = 500;
            $data = ['success' => false, 'message' => 'Gagal memuat data pengeluaran: ' . $e->getMessage()];
        }

        return response()->json($data, $status);
    }

    public function getCategories(Request $request): JsonResponse
    {
        $status = 200;
        $data = [];

        try {
            $user = $request->user();
            $fieldIds = [];

            if ($user && in_array($user->role, ['worker', 'treasurer'], true)) {
                $fieldIds = $this->getAccessibleFieldIds($user);
            }

            $categories = $this->expenseService->getUniqueCategories($fieldIds);
            $data = ['success' => true, 'data' => $categories];
        } catch (Throwable $e) {
            $status = 500;
            $data = ['success' => false, 'message' => 'Gagal memuat daftar kategori: ' . $e->getMessage()];
        }

        return response()->json($data, $status);
    }

    public function addExpense(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:100',
            'category'   => 'required|string|max:50',
            'quantity'   => 'required|numeric|min:1',
            'unit_price' => 'required|numeric|min:0',
            'date'       => 'required|date_format:Y-m-d',
            'note'       => 'nullable|string',
            'image'      => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $expense = $this->expenseService->createExpense($validator->validated(), $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Data pengeluaran berhasil disimpan.',
                'data'    => $expense
            ], 201);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data ke database: ' . $e->getMessage()], 500);
        }
    }

    public function updateExpense(Request $request, $id): JsonResponse
    {
        $return = null;
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:100',
            'category'   => 'required|string|max:50',
            'quantity'   => 'required|numeric|min:1',
            'unit_price' => 'required|numeric|min:0',
            'date'       => 'required|date_format:Y-m-d',
            'note'       => 'nullable|string',
            'image'      => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            $return = response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $updated = $this->expenseService->updateExpense((int)$id, $validator->validated(), $request->user());

            if (!$updated) {
                $return = response()->json(['success' => false, 'message' => 'Data pengeluaran tidak ditemukan.'], 404);
            }

            $return = response()->json(['success' => true, 'message' => 'Data pengeluaran berhasil diperbarui.'], 200);
        } catch (Throwable $e) {
            $return = response()->json(['success' => false, 'message' => 'Gagal mengupdate data: ' . $e->getMessage()], 500);
        }

        return $return;
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $deleted = $this->expenseService->deleteExpense((int)$id);

            if (!$deleted) {
                return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
            }

            return response()->json(['success' => true, 'message' => 'Data pengeluaran berhasil dihapus.'], 200);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menghapus data: ' . $e->getMessage()], 500);
        }
    }
}
