<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Field;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class OwnerFieldController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $fields = Field::with('fieldPrices')->get();

            return response()->json(['success' => true, 'data' => $fields], 200);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat data lapangan.'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'category' => 'required|string|max:50',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        try {
            $field = Field::create($validator->validated());

            return response()->json(['success' => true, 'message' => 'Lapangan berhasil ditambahkan.', 'data' => $field], 201);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menambahkan lapangan.'], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        $message = null;
        $status_response = null;
        $success = null;

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'category' => 'required|string|max:50',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            $success = false;
            $message = $validator->errors()->first();
            $status_response = 422;
        }

        try {
            $field = Field::find($id);
            if (!$field) {
                $success = false;
                $message = 'Lapangan tidak ditemukan.';
                $status_response = 404;
            }

            if ($message) {
                return response()->json(['success' => $success, 'message' => $message], $status_response);
            }

            $field->update($validator->validated());
            $success = true;
            $message = 'Lapangan berhasil diperbarui.';
            $status_response = 200;

            return response()->json(['success' => $success, 'message' => $message, 'data' => $field], $status_response);
        } catch (Throwable $e) {
            $success = false;
            $message = 'Gagal mengupdate lapangan.';
            $status_response = 500;
        }
        return response()->json(['success' => $success, 'message' => $message], $status_response);
    }
}
