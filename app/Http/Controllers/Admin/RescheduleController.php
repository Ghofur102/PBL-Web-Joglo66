<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\FieldAccessTrait;
use App\Http\Requests\Admin\RescheduleBookingRequest;
use App\Models\BookingDetail;
use App\Services\Admin\RescheduleService;
use DomainException;
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
            $detail = $this->resolveDetail($detail_booking_id, $request->user());

            $this->rescheduleService->execute($detail, $request->validated(), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Jadwal booking berhasil diubah dan finansial disesuaikan.',
                'data' => $detail->fresh(),
            ], 200);
        } catch (HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal reschedule: '.$e->getMessage()], 500);
        }
    }

    public function approve(Request $request, $detail_booking_id): JsonResponse
    {
        try {
            $detail = $this->resolveDetail($detail_booking_id, $request->user());

            $this->rescheduleService->approve($detail, $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Pengajuan reschedule berhasil disetujui.',
                'data' => $detail->fresh(),
            ], 200);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        } catch (HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menyetujui: '.$e->getMessage()], 500);
        }
    }

    public function reject(Request $request, $detail_booking_id): JsonResponse
    {
        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:255', 'not_regex:/<\s*script\b/i'],
        ]);

        try {
            $detail = $this->resolveDetail($detail_booking_id, $request->user());

            $this->rescheduleService->reject($detail, $request->input('rejection_reason'), $request->user());

            return response()->json([
                'status' => 'success',
                'message' => 'Pengajuan reschedule telah ditolak.',
            ], 200);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        } catch (HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Gagal menolak: '.$e->getMessage()], 500);
        }
    }

    private function resolveDetail(int|string $detailBookingId, ?object $user): BookingDetail
    {
        $detail = BookingDetail::query()->findOrFail($detailBookingId);

        if (! $this->checkFieldAccess($user, $detail->booking->fk_field_id)) {
            throw new HttpException(403, self::UNAUTHORIZED_MESSAGE);
        }

        return $detail;
    }
}
