<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\BookingDetailResolvableTrait;
use App\Http\Controllers\Traits\FieldAccessTrait;
use App\Http\Controllers\Traits\HandlesBookingActionErrors;
use App\Http\Requests\Admin\RescheduleBookingRequest;
use App\Services\Admin\RescheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RescheduleController extends Controller
{
    use FieldAccessTrait, BookingDetailResolvableTrait, HandlesBookingActionErrors;

    protected RescheduleService $rescheduleService;

    public function __construct(RescheduleService $rescheduleService)
    {
        $this->rescheduleService = $rescheduleService;
    }

    public function __invoke(RescheduleBookingRequest $request, $detail_booking_id): JsonResponse
    {
        return $this->respondToBookingAction(function () use ($request, $detail_booking_id): JsonResponse {
            $detail = $this->resolveDetail($detail_booking_id, $request->user());

            $this->rescheduleService->execute($detail, $request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Jadwal booking berhasil diubah dan finansial disesuaikan.',
                'data' => $detail->fresh(),
            ], 200);
        }, 'Gagal reschedule: ');
    }

    public function approve(Request $request, $detail_booking_id): JsonResponse
    {
        return $this->respondToBookingAction(function () use ($request, $detail_booking_id): JsonResponse {
            $detail = $this->resolveDetail($detail_booking_id, $request->user());

            $this->rescheduleService->approve($detail, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Pengajuan reschedule berhasil disetujui.',
                'data' => $detail->fresh(),
            ], 200);
        }, 'Gagal menyetujui: ');
    }

    public function reject(Request $request, $detail_booking_id): JsonResponse
    {
        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:255', 'not_regex:/<\s*script\b/i'],
        ]);

        return $this->respondToBookingAction(function () use ($request, $detail_booking_id): JsonResponse {
            $detail = $this->resolveDetail($detail_booking_id, $request->user());

            $this->rescheduleService->reject($detail, $request->input('rejection_reason'), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Pengajuan reschedule telah ditolak.',
            ], 200);
        }, 'Gagal menolak: ');
    }
}
