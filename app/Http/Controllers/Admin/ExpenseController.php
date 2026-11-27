<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\FieldAccessTrait;
use App\Models\Expense;
use App\Services\Admin\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ExpenseController extends Controller
{
    use FieldAccessTrait;

    private const SCRIPT_VALIDATION_RULE = 'not_regex:/<\s*script\b/i';

    private const EXPENSE_VALIDATION_RULES = [
        'name' => ['required', 'string', 'max:100', self::SCRIPT_VALIDATION_RULE],
        'category' => 'required|string|max:50',
        'quantity' => 'required|numeric|min:1',
        'unit_price' => 'required|numeric|min:0',
        'date' => 'required|date_format:Y-m-d',
        'note' => ['nullable', 'string', self::SCRIPT_VALIDATION_RULE],
        'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
    ];

    protected ExpenseService $expenseService;

    public function __construct(ExpenseService $expenseService)
    {
        $this->expenseService = $expenseService;
    }

    public function listExpense(Request $request): JsonResponse
    {
        try {
            $expenses = $this->expenseService->getExpenses($this->resolveFieldIds($request));

            return response()->json(['success' => true, 'data' => $expenses]);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'Gagal memuat data pengeluaran: ');
        }
    }

    public function getCategories(Request $request): JsonResponse
    {
        try {
            $categories = $this->expenseService->getUniqueCategories($this->resolveFieldIds($request));

            return response()->json(['success' => true, 'data' => $categories]);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'Gagal memuat daftar kategori: ');
        }
    }

    private function resolveFieldIds(Request $request): array
    {
        $user = $request->user();

        if ($user && in_array($user->role, ['worker', 'treasurer'], true)) {
            return $this->getAccessibleFieldIds($user);
        }

        return [];
    }

    public function addExpense(Request $request): JsonResponse
    {
        $validated = $this->validatedExpenseData($request);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        try {
            $expense = $this->expenseService->createExpense($validated, $request->user());

            return response()->json([
                'success' => true,
                'message' => 'Data pengeluaran berhasil disimpan.',
                'data' => $expense,
            ], 201);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'Gagal menyimpan data ke database: ');
        }
    }

    public function updateExpense(Request $request, $id): JsonResponse
    {
        $validated = $this->validatedExpenseData($request);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        try {
            $expense = Expense::query()->find((int) $id);

            if (! $expense) {
                return response()->json(['success' => false, 'message' => 'Data pengeluaran tidak ditemukan.'], 404);
            }

            if (! $this->checkFieldAccess($request->user(), $expense->fk_field_id)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized field access.'], 403);
            }

            $updated = $this->expenseService->updateExpense((int) $id, $validated);

            if (! $updated) {
                return response()->json(['success' => false, 'message' => 'Data pengeluaran tidak ditemukan.'], 404);
            }

            return response()->json(['success' => true, 'message' => 'Data pengeluaran berhasil diperbarui.'], 200);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'Gagal mengupdate data: ');
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $deleted = $this->expenseService->deleteExpense((int) $id);

            if (! $deleted) {
                return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
            }

            return response()->json(['success' => true, 'message' => 'Data pengeluaran berhasil dihapus.'], 200);
        } catch (Throwable $e) {
            return $this->errorResponse($e, 'Gagal menghapus data: ');
        }
    }

    private function validatedExpenseData(Request $request): array|JsonResponse
    {
        $validator = Validator::make($request->all(), self::EXPENSE_VALIDATION_RULES);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        return $validator->validated();
    }

    private function errorResponse(Throwable $exception, string $message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message.$exception->getMessage()], 500);
    }
}
