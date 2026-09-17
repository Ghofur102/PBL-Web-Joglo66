<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Field;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Throwable;

class OwnerFieldController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $fields = Field::with('fieldPrices')
                ->where('fk_user_id', Auth::id())
                ->get();

            return response()->json(['success' => true, 'data' => $fields], 200);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat data lapangan.'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'                 => 'required|string|max:50',
            'category'             => 'required|in:futsal,mini soccer',
            'description'          => 'nullable|string',
            'image_url'            => 'nullable|string|max:255',
            'min_cancel_days'      => 'nullable|integer|min:0|max:30',
            'min_reschedule_days'  => 'nullable|integer|min:0|max:30',
            'max_reschedule_times' => 'nullable|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $payload = $validator->validated();
            $payload['fk_user_id'] = Auth::id();

            $field = Field::create($payload);

            return response()->json(['success' => true, 'message' => 'Lapangan berhasil ditambahkan.', 'data' => $field], 201);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menambahkan lapangan.'], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'                 => 'required|string|max:50',
            'category'             => 'required|in:futsal,mini soccer',
            'description'          => 'nullable|string',
            'image_url'            => 'nullable|string|max:255',
            'min_cancel_days'      => 'nullable|integer|min:0|max:30',
            'min_reschedule_days'  => 'nullable|integer|min:0|max:30',
            'max_reschedule_times' => 'nullable|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $field = Field::where('id', $id)
                ->where('fk_user_id', Auth::id())
                ->first();

            if (!$field) {
                return response()->json(['success' => false, 'message' => 'Lapangan tidak ditemukan atau bukan milik Anda.'], 404);
            }

            $field->update($validator->validated());

            return response()->json(['success' => true, 'message' => 'Lapangan berhasil diperbarui.', 'data' => $field], 200);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal mengupdate lapangan.'], 500);
        }
    }
}
