<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RescheduleBookingRequest;
use App\Services\Admin\RescheduleService;
use App\Models\BookingDetail;
use App\Http\Controllers\Traits\FieldAccessTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class RescheduleController extends Controller
{
    use FieldAccessTrait;

    protected RescheduleService $rescheduleService;
    const UNAUTHORIZED_MESSAGE = 'Unauthorized field access.';

    public function __construct(RescheduleService $rescheduleService)
    {
        $this->rescheduleService = $rescheduleService;
    }

    public function __invoke(RescheduleBookingRequest $request, $detail_booking_id): JsonResponse
    {
        try {
            $detail = BookingDetail::query()->findOrFail($detail_booking_id);

            if (!$this->checkFieldAccess($request->user(), $detail->booking->fk_field_id)) {
                throw new HttpException(403, self::UNAUTHORIZED_MESSAGE);
            }

            $this->rescheduleService->execute($detail, $request->validated(), $request->user());

            return response()->json([
                'status'  => 'success',
                'message' => 'Jadwal booking berhasil diubah dan finansial disesuaikan.',
                'data'    => $detail->fresh()
            ], 200);
        } catch (HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal reschedule: ' . $e->getMessage()], 500);
        }
    }

    public function approve(Request $request, $detail_booking_id): JsonResponse
    {
        try {
            $detail = BookingDetail::query()->findOrFail($detail_booking_id);

            if (!$this->checkFieldAccess($request->user(), $detail->booking->fk_field_id)) {
                throw new HttpException(403, self::UNAUTHORIZED_MESSAGE);
            }

            $this->rescheduleService->approve($detail, $request->user());

            return response()->json([
                'status'  => 'success',
                'message' => 'Pengajuan reschedule berhasil disetujui.',
                'data'    => $detail->fresh()
            ], 200);
        } catch (HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menyetujui: ' . $e->getMessage()], 500);
        }
    }

    public function reject(Request $request, $detail_booking_id): JsonResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:255',
        ]);

        try {
            $detail = BookingDetail::query()->findOrFail($detail_booking_id);

            if (!$this->checkFieldAccess($request->user(), $detail->booking->fk_field_id)) {
                throw new HttpException(403, self::UNAUTHORIZED_MESSAGE);
            }

            $this->rescheduleService->reject($detail, $request->input('rejection_reason'), $request->user());

            return response()->json([
                'status'  => 'success',
                'message' => 'Pengajuan reschedule telah ditolak.'
            ], 200);
        } catch (HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menolak: ' . $e->getMessage()], 500);
        }
    }
}
