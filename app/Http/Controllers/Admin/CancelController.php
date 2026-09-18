<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelBookingRequest;
use App\Services\Admin\CancelService;
use App\Models\BookingDetail;
use App\Http\Controllers\Traits\FieldAccessTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class CancelController extends Controller
{
    use FieldAccessTrait;

    protected CancelService $cancelService;

    public function __construct(CancelService $cancelService)
    {
        $this->cancelService = $cancelService;
    }

    public function __invoke(CancelBookingRequest $request, $detail_booking_id): JsonResponse
    {
        try {
            $detail = BookingDetail::query()->findOrFail($detail_booking_id);

            if (!$this->checkFieldAccess($request->user(), $detail->booking->fk_field_id)) {
                throw new HttpException(403, 'Unauthorized field access.');
            }

            $this->cancelService->execute($detail, $request->validated(), $request->user());

            return response()->json([
                'status'  => 'success',
                'message' => 'Booking berhasil dibatalkan dengan penyesuaian dana kasir.'
            ], 200);
        } catch (HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal membatalkan: ' . $e->getMessage()], 500);
        }
    }

    public function approve(Request $request, $detail_booking_id): JsonResponse
    {
        $request->validate([
            'status_refund' => 'nullable|string|in:None,Partial,Full,none,partial,full',
            'refund_amount' => 'nullable|integer|min:0',
        ]);

        try {
            $detail = BookingDetail::query()->findOrFail($detail_booking_id);

            if (!$this->checkFieldAccess($request->user(), $detail->booking->fk_field_id)) {
                throw new HttpException(403, 'Unauthorized field access.');
            }

            $this->cancelService->approve(
                $detail,
                $request->user(),
                $request->only(['status_refund', 'refund_amount'])
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Pengajuan pembatalan berhasil disetujui.'
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
                throw new HttpException(403, 'Unauthorized field access.');
            }

            $this->cancelService->reject($detail, $request->input('rejection_reason'), $request->user());

            return response()->json([
                'status'  => 'success',
                'message' => 'Pengajuan pembatalan telah ditolak.'
            ], 200);
        } catch (HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menolak: ' . $e->getMessage()], 500);
        }
    }
}
