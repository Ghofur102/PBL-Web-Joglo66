<?php

namespace App\Http\Controllers\Owner;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\OwnerFieldRequest;
use App\Models\Field;
use App\Services\RefundRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Throwable;

class OwnerFieldController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            if (Auth::user()?->role !== UserRole::OWNER->value) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
            }

            $fields = Field::with(['fieldPrices', 'refundRules'])
                ->where('fk_user_id', Auth::id())
                ->get();

            return response()->json(['success' => true, 'data' => $fields], 200);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat data lapangan.'], 500);
        }
    }

    public function store(OwnerFieldRequest $request, RefundRuleService $refundRuleService): JsonResponse
    {
        try {
            $payload = $request->validated();
            $refundRules = $payload['refund_rules'] ?? RefundRuleService::defaultRules();
            unset($payload['refund_rules']);
            $payload['fk_user_id'] = Auth::id();

            $field = Field::query()->getConnection()->transaction(function () use ($payload, $refundRules, $refundRuleService) {
                $field = Field::create($payload);
                $refundRuleService->syncRules($field, $refundRules);

                return $field->load(['fieldPrices', 'refundRules']);
            });

            return response()->json(['success' => true, 'message' => 'Lapangan berhasil ditambahkan.', 'data' => $field], 201);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => $e->validator->errors()->first(), 'errors' => $e->errors()], 422);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menambahkan lapangan.'], 500);
        }
    }

    public function update(OwnerFieldRequest $request, $id, RefundRuleService $refundRuleService): JsonResponse
    {
        $status = 200;
        $responseData = [];

        try {
            $field = Field::where('id', $id)
                ->where('fk_user_id', Auth::id())
                ->first();

            if (! $field) {
                $status = 404;
                $responseData = ['success' => false, 'message' => 'Lapangan tidak ditemukan atau bukan milik Anda.'];
            } else {
                $payload = $request->validated();
                $refundRules = $payload['refund_rules'] ?? null;
                unset($payload['refund_rules']);

                $field = $field->getConnection()->transaction(function () use ($field, $payload, $refundRules, $refundRuleService) {
                    $field->update($payload);
                    if ($refundRules !== null) {
                        $refundRuleService->syncRules($field, $refundRules);
                    }

                    return $field->fresh(['fieldPrices', 'refundRules']);
                });

                $responseData = ['success' => true, 'message' => 'Lapangan berhasil diperbarui.', 'data' => $field];
            }
        } catch (ValidationException $e) {
            $status = 422;
            $responseData = ['success' => false, 'message' => $e->validator->errors()->first(), 'errors' => $e->errors()];
        } catch (Throwable $e) {
            $status = 500;
            $responseData = ['success' => false, 'message' => 'Gagal mengupdate lapangan.'];
        }

        return response()->json($responseData, $status);
    }
}
