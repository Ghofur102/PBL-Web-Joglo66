<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\BookingDetailResolvableTrait;
use App\Http\Controllers\Traits\FieldAccessTrait;
use App\Http\Controllers\Traits\HandlesBookingActionErrors;
use App\Http\Requests\Admin\CancelBookingRequest;
use App\Services\Admin\CancelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CancelController extends Controller
{
    use FieldAccessTrait, BookingDetailResolvableTrait, HandlesBookingActionErrors;

    protected CancelService $cancelService;

    public function __construct(CancelService $cancelService)
    {
        $this->cancelService = $cancelService;
    }

    public function __invoke(CancelBookingRequest $request, $detail_booking_id): JsonResponse
    {
        return $this->respondToBookingAction(function () use ($request, $detail_booking_id): JsonResponse {
            $detail = $this->resolveDetail($detail_booking_id, $request->user());

            $this->cancelService->execute($detail, $request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Booking berhasil dibatalkan dengan penyesuaian dana kasir.',
            ], 200);
        }, 'Gagal membatalkan: ');
    }

    public function approve(Request $request, $detail_booking_id): JsonResponse
    {
        $request->validate([
            'status_refund' => 'nullable|string|in:None,Partial,Full,none,partial,full',
            'refund_amount' => 'nullable|integer|min:0',
            'refund_percent' => 'sometimes|numeric|min:0|max:100',
        ]);

        return $this->respondToBookingAction(function () use ($request, $detail_booking_id): JsonResponse {
            $detail = $this->resolveDetail($detail_booking_id, $request->user());

            $this->cancelService->approve(
                $detail,
                $request->user(),
                $request->only(['status_refund', 'refund_amount', 'refund_percent'])
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Pengajuan pembatalan berhasil disetujui.',
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

            $this->cancelService->reject($detail, $request->input('rejection_reason'), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Pengajuan pembatalan telah ditolak.',
            ], 200);
        }, 'Gagal menolak: ');
    }
}
