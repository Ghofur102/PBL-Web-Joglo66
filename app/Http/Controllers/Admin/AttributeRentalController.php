<?php

namespace App\Http\Controllers\Admin;

use App\Models\Attribute;
use App\Models\BookingAttribute;
use App\Services\Admin\AttributeRentalService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRentalRequest;
use App\Http\Controllers\Traits\FieldAccessTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class AttributeRentalController extends Controller
{
    use FieldAccessTrait;

    private const ACCESS_DENIED_MSG = 'Anda tidak memiliki akses ke atribut ini.';
    private const RENTAL_NOT_FOUND_MSG = 'Data penyewaan tidak ditemukan.';

    protected AttributeRentalService $rentalService;

    public function __construct(AttributeRentalService $rentalService)
    {
        $this->rentalService = $rentalService;
    }

    public function getActiveBookings(Request $request): JsonResponse
    {
        $status = 200;
        $data = [];

        try {
            $user = $request->user();
            $fieldIds = [];

            if ($user && $user->role === 'worker') {
                $fieldIds = $this->getAccessibleFieldIds($user);
            }

            $bookings = $this->rentalService->getActiveBookings($fieldIds);
            $data = ['success' => true, 'message' => 'Data booking aktif berhasil diambil.', 'data' => $bookings];
        } catch (Throwable $e) {
            Log::error('Active Bookings Error: ' . $e->getMessage());
            $status = 500;
            $data = ['success' => false, 'message' => 'Gagal memuat data booking aktif: ' . $e->getMessage()];
        }

        return response()->json($data, $status);
    }

    public function store(StoreRentalRequest $request): JsonResponse
    {
        $status = 201;
        $data = [];

        try {
            $user = $request->user();

            foreach ($request->items as $item) {
                $attribute = Attribute::findOrFail($item['fk_attribute_id']);
                if (!$this->checkFieldAccess($user, $attribute->fk_field_id)) {
                    throw new AccessDeniedHttpException(self::ACCESS_DENIED_MSG);
                }
            }

            $result = $this->rentalService->executeRental($request->validated());
            $data = ['success' => true, 'message' => 'Transaksi penyewaan berhasil disimpan.', 'data' => $result];
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
            $data = ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            Log::error('Attribute Rental Store Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $status = 500;
            $data = ['success' => false, 'message' => 'Gagal menyimpan transaksi penyewaan: ' . $e->getMessage()];
        }

        return response()->json($data, $status);
    }

    public function returnItem(Request $request, $id): JsonResponse
    {
        $status = 200;
        $data = [];

        try {
            $rental = BookingAttribute::find($id);
            if (!$rental) {
                throw new NotFoundHttpException(self::RENTAL_NOT_FOUND_MSG);
            }

            $user = $request->user();
            $attribute = Attribute::find($rental->fk_attribute_id);
            if ($attribute && !$this->checkFieldAccess($user, $attribute->fk_field_id)) {
                throw new AccessDeniedHttpException(self::ACCESS_DENIED_MSG);
            }

            $updatedRental = $this->rentalService->processReturn($rental);
            $data = ['success' => true, 'message' => 'Atribut berhasil dikembalikan.', 'data' => $updatedRental];
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
            $data = ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            Log::error('Return Attribute Error: ' . $e->getMessage());
            $status = 500;
            $data = ['success' => false, 'message' => 'Gagal memproses pengembalian: ' . $e->getMessage()];
        }

        return response()->json($data, $status);
    }

    public function history(Request $request): JsonResponse
    {
        $status = 200;
        $data = [];

        try {
            $user = $request->user();
            $fieldIds = [];

            if ($user && $user->role === 'worker') {
                $fieldIds = $this->getAccessibleFieldIds($user);
                if (empty($fieldIds)) {
                    return response()->json(['success' => true, 'message' => 'Data riwayat berhasil diambil.', 'data' => []], 200);
                }
            }

            $filters = $request->only(['search', 'start_date', 'end_date', 'status', 'limit']);
            $historyData = $this->rentalService->getHistory($fieldIds, $filters);

            $data = ['success' => true, 'message' => 'Data riwayat penyewaan berhasil diambil.', 'data' => $historyData];
        } catch (Throwable $e) {
            Log::error('History Attribute Error: ' . $e->getMessage());
            $status = 500;
            $data = ['success' => false, 'message' => 'Gagal memuat data riwayat: ' . $e->getMessage()];
        }

        return response()->json($data, $status);
    }

    public function show($id): JsonResponse
    {
        $status = 200;
        $data = [];

        try {
            $rental = BookingAttribute::with('attribute:id,name,type,price_hour,stock,fk_field_id')->find($id);
            if (!$rental) {
                throw new NotFoundHttpException(self::RENTAL_NOT_FOUND_MSG);
            }

            $data = ['success' => true, 'message' => 'Detail penyewaan berhasil diambil.', 'data' => $rental];
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
            $data = ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            $status = 500;
            $data = ['success' => false, 'message' => 'Gagal memuat detail penyewaan: ' . $e->getMessage()];
        }

        return response()->json($data, $status);
    }
}
