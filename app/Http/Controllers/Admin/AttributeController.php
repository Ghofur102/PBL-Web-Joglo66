<?php

namespace App\Http\Controllers\Admin;

use App\Models\Attribute;
use App\Services\Admin\AttributeService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAttributeRequest;
use App\Http\Requests\Admin\UpdateAttributeRequest;
use App\Http\Controllers\Traits\FieldAccessTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class AttributeController extends Controller
{
    use FieldAccessTrait;

    private const NOT_FOUND_MSG = 'Data atribut tidak ditemukan.';
    private const FORBIDDEN_MSG = 'Anda tidak memiliki akses ke atribut ini.';

    protected AttributeService $attributeService;

    public function __construct(AttributeService $attributeService)
    {
        $this->attributeService = $attributeService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $fieldIds = [];

            if ($user && $user->role === 'worker') {
                $fieldIds = $this->getAccessibleFieldIds($user);
                if (empty($fieldIds)) {
                    return response()->json(['success' => true, 'message' => 'Data atribut kosong.', 'data' => []], 200);
                }
            }

            $attributes = $this->attributeService->getAttributesByFields($fieldIds, $request->search);

            return response()->json(['success' => true, 'message' => 'Data atribut berhasil diambil.', 'data' => $attributes], 200);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat data: ' . $e->getMessage()], 500);
        }
    }

    public function getTypes(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $fieldIds = [];

            if ($user && $user->role === 'worker') {
                $fieldIds = $this->getAccessibleFieldIds($user);
            }

            $types = $this->attributeService->getAttributeTypes($fieldIds);

            return response()->json(['success' => true, 'data' => $types], 200);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat jenis atribut.'], 500);
        }
    }

    public function show(Request $request, $id): JsonResponse
    {
        try {
            $attribute = Attribute::with('field:id,name')->find($id);
            if (!$attribute) {
                throw new NotFoundHttpException(self::NOT_FOUND_MSG);
            }

            if (!$this->checkFieldAccess($request->user(), $attribute->fk_field_id)) {
                throw new AccessDeniedHttpException(self::FORBIDDEN_MSG);
            }

            return response()->json(['success' => true, 'message' => 'Detail atribut berhasil diambil.', 'data' => $attribute], 200);
        } catch (HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal memuat data: ' . $e->getMessage()], 500);
        }
    }

    public function store(StoreAttributeRequest $request): JsonResponse
    {
        try {
            if (!$this->checkFieldAccess($request->user(), $request->fk_field_id)) {
                throw new AccessDeniedHttpException('Anda tidak memiliki akses ke lapangan ini.');
            }

            $attribute = $this->attributeService->createAttribute($request->validated());

            return response()->json(['success' => true, 'message' => 'Data atribut berhasil disimpan.', 'data' => $attribute], 201);
        } catch (HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data: ' . $e->getMessage()], 500);
        }
    }

    public function update(UpdateAttributeRequest $request, $id): JsonResponse
    {
        try {
            $attribute = Attribute::find($id);
            if (!$attribute) {
                throw new NotFoundHttpException(self::NOT_FOUND_MSG);
            }

            if (!$this->checkFieldAccess($request->user(), $attribute->fk_field_id)) {
                throw new AccessDeniedHttpException(self::FORBIDDEN_MSG);
            }

            $updated = $this->attributeService->updateAttribute($attribute, $request->validated());

            return response()->json(['success' => true, 'message' => 'Data atribut berhasil diperbarui.', 'data' => $updated], 200);
        } catch (HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $status = 200;
        $data = [];

        try {
            $attribute = Attribute::find($id);
            if (!$attribute) {
                throw new NotFoundHttpException(self::NOT_FOUND_MSG);
            }

            if (!$this->checkFieldAccess($request->user(), $attribute->fk_field_id)) {
                throw new AccessDeniedHttpException(self::FORBIDDEN_MSG);
            }

            $attribute->delete();

            $data = [
                'success' => true,
                'message' => 'Data atribut berhasil dihapus.'
            ];
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
            $data = [
                'success' => false,
                'message' => $e->getMessage()
            ];
        } catch (QueryException $e) {
            $status = ($e->getCode() == "23000") ? 400 : 500;
            $msg = ($e->getCode() == "23000")
                ? 'Atribut tidak dapat dihapus karena masih digunakan pada data penyewaan atau transaksi.'
                : 'Gagal menghapus data (Error Database).';

            $data = [
                'success' => false,
                'message' => $msg
            ];
        } catch (Throwable $e) {
            $status = 500;
            $data = [
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ];
        }

        return response()->json($data, $status);
    }

    public function toggleStatus(Request $request, $id): JsonResponse
    {
        try {
            $attribute = Attribute::find($id);
            if (!$attribute) {
                throw new NotFoundHttpException(self::NOT_FOUND_MSG);
            }

            if (!$this->checkFieldAccess($request->user(), $attribute->fk_field_id)) {
                throw new AccessDeniedHttpException(self::FORBIDDEN_MSG);
            }

            $toggled = $this->attributeService->toggleAttributeStatus($attribute);
            $msg = ($toggled->status === GeneralStatus::ACTIVE->value) ? 'Atribut diaktifkan.' : 'Atribut dinonaktifkan.';

            return response()->json(['success' => true, 'message' => $msg, 'data' => $toggled], 200);
        } catch (HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal mengubah status: ' . $e->getMessage()], 500);
        }
    }
}
