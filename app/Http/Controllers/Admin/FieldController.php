<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\FieldAccessTrait;
use App\Http\Requests\Admin\CloseFieldRequest;
use App\Http\Requests\Admin\UpdateFieldRequest;
use App\Models\Field;
use App\Services\Admin\FieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class FieldController extends Controller
{
    use FieldAccessTrait;

    private const MSG_INTERNAL_ERROR = 'Internal server error.';

    private const MSG_FORBIDDEN_FIELD = 'Forbidden. Anda tidak memiliki akses ke lapangan ini.';

    protected FieldService $fieldService;

    public function __construct(FieldService $fieldService)
    {
        $this->fieldService = $fieldService;
    }

    public function index(Request $request): JsonResponse
    {
        $status = 200;
        $data = [];

        try {
            $user = $request->user();
            $fieldIds = [];
            $restrictedRole = $user && in_array($user->role, [UserRole::OWNER->value, UserRole::WORKER->value, UserRole::TREASURER->value], true);

            if ($restrictedRole) {
                $fieldIds = $this->getAccessibleFieldIds($user);
            }

            $limit = $request->limit ?? 20;
            $fieldsList = $restrictedRole && empty($fieldIds)
                ? []
                : $this->fieldService->getFieldList($fieldIds, $request->search, (int) $limit);

            $data = [
                'success' => true,
                'message' => 'Field list retrieved successfully',
                'data' => $fieldsList,
            ];
        } catch (Throwable $e) {
            $status = 500;
            $data = [
                'success' => false,
                'message' => 'Internal server error: '.$e->getMessage(),
            ];
        }

        return response()->json($data, $status);
    }

    public function show(Request $request, $field_id): JsonResponse
    {
        $status = 200;
        $data = [];

        try {
            if (! $this->checkFieldAccess($request->user(), $field_id)) {
                throw new AccessDeniedHttpException(self::MSG_FORBIDDEN_FIELD);
            }

            $field = Field::query()->with(['fieldPrices', 'refundRules'])->find($field_id);
            if (! $field) {
                throw new NotFoundHttpException('Data lapangan tidak ditemukan.');
            }

            $data = [
                'success' => true,
                'message' => 'Detail lapangan berhasil diambil.',
                'data' => $field,
            ];
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
            $data = ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            $status = 500;
            $data = ['success' => false, 'message' => self::MSG_INTERNAL_ERROR];
        }

        return response()->json($data, $status);
    }

    public function update(UpdateFieldRequest $request): JsonResponse
    {
        $status = 200;
        $data = [];

        try {
            if ($request->user()?->role === UserRole::TREASURER->value && $request->has('refund_rules')) {
                throw new AccessDeniedHttpException('Bendahara tidak berwenang mengubah aturan refund.');
            }

            if (! $this->checkFieldAccess($request->user(), $request->id)) {
                throw new AccessDeniedHttpException('Forbidden. Anda tidak memiliki akses untuk mengupdate lapangan ini.');
            }

            $field = Field::findOrFail($request->id);
            $updatedField = $this->fieldService->updateFieldAndPricing($field, $request->validated(), $request);

            $data = [
                'success' => true,
                'message' => 'Field and pricing rules updated successfully',
                'field' => $updatedField,
            ];
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
            $data = ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            $status = 400;
            $data = ['success' => false, 'message' => $e->getMessage()];
        }

        return response()->json($data, $status);
    }

    public function checkAvailability(Request $request, int $field_id, string $date): JsonResponse
    {
        $status = 200;
        $data = [];

        try {
            $validator = Validator::make(['date' => $date], ['date' => 'required|date_format:Y-m-d']);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'message' => 'Format tanggal salah. Gunakan Y-m-d.'], 422);
            }

            if (! $this->checkFieldAccess($request->user(), $field_id)) {
                throw new AccessDeniedHttpException(self::MSG_FORBIDDEN_FIELD);
            }

            $availability = $this->fieldService->checkSlotAvailability($field_id, $date);
            $data = ['success' => true, 'available_slots' => $availability];
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
            $data = ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            $status = 500;
            $data = ['success' => false, 'message' => self::MSG_INTERNAL_ERROR];
        }

        return response()->json($data, $status);
    }

    public function closeField(CloseFieldRequest $request): JsonResponse
    {
        $status = 200;
        $data = [];

        try {
            if (! $this->checkFieldAccess($request->user(), $request->fk_field_id)) {
                throw new AccessDeniedHttpException('Forbidden. Anda tidak memiliki akses untuk menutup lapangan ini.');
            }

            $result = $this->fieldService->executeFieldClosure($request->validated(), $request->user()->id);

            $data = [
                'success' => true,
                'data_field_closure' => $result['closure'],
                'affected_bookings' => $result['affected_bookings'],
            ];
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
            $data = ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            $status = 500;
            $data = ['success' => false, 'message' => self::MSG_INTERNAL_ERROR];
        }

        return response()->json($data, $status);
    }
}
