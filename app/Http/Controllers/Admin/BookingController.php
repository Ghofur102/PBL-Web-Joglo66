<?php

namespace App\Http\Controllers\Admin;

use App\Models\BookingDetail;
use App\Services\Admin\BookingService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBookingRequest;
use App\Http\Controllers\Traits\FieldAccessTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;
use App\Http\Requests\Admin\ExtendBookingRequest;
use App\Services\Admin\BookingExtensionService;

class BookingController extends Controller
{
    use FieldAccessTrait;

    protected BookingService $bookingService;
    protected BookingExtensionService $extensionService;

    public function __construct(BookingExtensionService $extensionService, BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
        $this->extensionService = $extensionService;
    }

    public function index(Request $request): JsonResponse
    {
        $status = 200;
        $data = [];

        try {
            $user = $request->user();
            $fieldIds = [];

            if ($user && $user->role === 'worker') {
                $fieldIds = $this->getAccessibleFieldIds($user);
            }

            $filters = $request->only(['field_id', 'search', 'start_date', 'end_date', 'limit']);
            $result = $this->bookingService->getBookingList($fieldIds, $filters);

            $data = [
                'success' => true,
                'message' => 'Booking list retrieved successfully',
                'data'    => $result
            ];
        } catch (Throwable $e) {
            $status = 500;
            $data = [
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ];
        }

        return response()->json($data, $status);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $status = 201;
        $data = [];

        try {
            if (!$this->checkFieldAccess($request->user(), $request->field_id)) {
                throw new AccessDeniedHttpException('Anda tidak memiliki hak akses untuk membuat pesanan di lapangan ini.');
            }

            $booking = $this->bookingService->createBooking($request->validated());

            $data = [
                'success' => true,
                'message' => 'Booking created successfully.',
                'data'    => [
                    'booking_id' => $booking->id
                ],
            ];
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
            $data = ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            $status = 500;
            $data = [
                'success' => false,
                'message' => 'Failed to create booking. Please try again.',
                'error'   => $e->getMessage(),
            ];
        }

        return response()->json($data, $status);
    }

    public function show(Request $request, $detail_booking_id): JsonResponse
    {
        $status = 200;
        $data = [];

        try {
            $detail = BookingDetail::query()
                ->with(['booking.payments', 'booking.details'])
                ->find($detail_booking_id);

            if (!$detail) {
                throw new NotFoundHttpException('Booking detail not found.');
            }

            if (!$this->checkFieldAccess($request->user(), $detail->booking->fk_field_id)) {
                throw new AccessDeniedHttpException('Unauthorized. Anda tidak memiliki akses ke lapangan ini.');
            }

            $result = $this->bookingService->getBookingDetailInfo($detail);
            $data = ['success' => true, 'data' => $result];
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
            $data = ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            $status = 500;
            $data = [
                'success' => false,
                'message' => 'Internal server error.',
                'error'   => $e->getMessage()
            ];
        }

        return response()->json($data, $status);
    }
    
    public function extendTime(ExtendBookingRequest $request): JsonResponse 
    {
        try {
            $validated = $request->validated();
            $extendMinutes = $validated['extend_minutes'] ?? 30;

            $result = $this->extensionService->extendSessionTime(
                (int) $validated['fk_booking_detail_id'],
                (int) $extendMinutes
            );

            return response()->json([
                'success' => true,
                'message' => "Waktu bermain berhasil diperpanjang {$extendMinutes} menit.",
                'data'    => $result,
            ], 200);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperpanjang waktu bermain: ' . $e->getMessage(),
            ], 500);
        }
    }
}
